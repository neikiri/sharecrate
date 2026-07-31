<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;

/**
 * Thin PDO wrapper. Every query is prepared, identifiers are whitelisted.
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        self::$pdo = self::connect([
            'host' => (string) Config::get('DB_HOST', 'localhost'),
            'port' => (string) Config::get('DB_PORT', '3306'),
            'name' => (string) Config::get('DB_NAME', ''),
            'user' => (string) Config::get('DB_USER', ''),
            'pass' => (string) Config::get('DB_PASS', ''),
            'charset' => (string) Config::get('DB_CHARSET', 'utf8mb4'),
        ]);

        return self::$pdo;
    }

    /**
     * @param array{host:string,port:string,name:string,user:string,pass:string,charset?:string} $cfg
     */
    public static function connect(array $cfg): PDO
    {
        $charset = $cfg['charset'] ?? 'utf8mb4';
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $cfg['host'],
            $cfg['port'],
            $cfg['name'],
            $charset
        );

        try {
            $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
            ]);
        } catch (PDOException $e) {
            throw new RuntimeException('Database connection failed: ' . $e->getMessage(), (int) $e->getCode(), $e);
        }

        // Keep the session timezone aligned with PHP (UTC) so NOW() matches.
        $pdo->exec("SET time_zone = '+00:00'");

        return $pdo;
    }

    public static function isAvailable(): bool
    {
        try {
            self::pdo();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /** @param array<string, mixed> $params */
    public static function run(string $sql, array $params = []): PDOStatement
    {
        $statement = self::pdo()->prepare($sql);

        foreach ($params as $key => $value) {
            $name = is_int($key) ? $key + 1 : (str_starts_with((string) $key, ':') ? (string) $key : ':' . $key);
            $type = match (true) {
                is_bool($value) => PDO::PARAM_BOOL,
                is_int($value) => PDO::PARAM_INT,
                $value === null => PDO::PARAM_NULL,
                default => PDO::PARAM_STR,
            };
            $statement->bindValue($name, $value, $type);
        }

        $statement->execute();

        return $statement;
    }

    /**
     * @param array<string, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    public static function all(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>|null
     */
    public static function one(string $sql, array $params = []): ?array
    {
        $row = self::run($sql, $params)->fetch();

        return $row === false ? null : $row;
    }

    /** @param array<string, mixed> $params */
    public static function value(string $sql, array $params = []): mixed
    {
        $value = self::run($sql, $params)->fetchColumn();

        return $value === false ? null : $value;
    }

    /** @param array<string, mixed> $data */
    public static function insert(string $table, array $data): int
    {
        $columns = array_keys($data);
        self::guardIdentifiers([$table, ...$columns]);

        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $table,
            implode(', ', array_map(static fn ($c) => "`{$c}`", $columns)),
            implode(', ', array_map(static fn ($c) => ":{$c}", $columns))
        );

        self::run($sql, $data);

        return (int) self::pdo()->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $where
     */
    public static function update(string $table, array $data, array $where): int
    {
        if ($data === [] || $where === []) {
            return 0;
        }

        self::guardIdentifiers([$table, ...array_keys($data), ...array_keys($where)]);

        $set = implode(', ', array_map(static fn ($c) => "`{$c}` = :set_{$c}", array_keys($data)));
        $conditions = implode(' AND ', array_map(static fn ($c) => "`{$c}` = :where_{$c}", array_keys($where)));

        $params = [];
        foreach ($data as $key => $value) {
            $params['set_' . $key] = $value;
        }
        foreach ($where as $key => $value) {
            $params['where_' . $key] = $value;
        }

        return self::run("UPDATE `{$table}` SET {$set} WHERE {$conditions}", $params)->rowCount();
    }

    /** @param array<string, mixed> $where */
    public static function delete(string $table, array $where): int
    {
        if ($where === []) {
            return 0;
        }

        self::guardIdentifiers([$table, ...array_keys($where)]);
        $conditions = implode(' AND ', array_map(static fn ($c) => "`{$c}` = :{$c}", array_keys($where)));

        return self::run("DELETE FROM `{$table}` WHERE {$conditions}", $where)->rowCount();
    }

    public static function transaction(callable $callback): mixed
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();

        try {
            $result = $callback();
            $pdo->commit();

            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    public static function tableExists(string $table): bool
    {
        self::guardIdentifiers([$table]);

        try {
            self::pdo()->query("SELECT 1 FROM `{$table}` LIMIT 1");

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Executes a multi statement SQL script (used by the installer).
     */
    public static function runScript(string $sql, ?PDO $pdo = null): void
    {
        $pdo ??= self::pdo();

        foreach (self::splitStatements($sql) as $statement) {
            $pdo->exec($statement);
        }
    }

    /** @return string[] */
    public static function splitStatements(string $sql): array
    {
        // Strip line comments, then split on semicolons at the end of a line.
        $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
        $parts = preg_split('/;\s*(?:\r\n|\n|$)/', $sql) ?: [];

        return array_values(array_filter(array_map('trim', $parts), static fn ($s) => $s !== ''));
    }

    /** @param string[] $identifiers */
    private static function guardIdentifiers(array $identifiers): void
    {
        foreach ($identifiers as $identifier) {
            if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', (string) $identifier) !== 1) {
                throw new RuntimeException('Unsafe SQL identifier: ' . $identifier);
            }
        }
    }
}
