<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Tiny validation helper: collects field => message pairs.
 */
final class Validator
{
    /** @var array<string, string> */
    private array $errors = [];

    /** @var array<string, mixed> */
    private array $data;

    /** @param array<string, mixed> $data */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /** @param array<string, mixed> $data */
    public static function make(array $data): self
    {
        return new self($data);
    }

    public function value(string $field): mixed
    {
        $value = $this->data[$field] ?? null;

        return is_string($value) ? trim($value) : $value;
    }

    public function required(string $field, ?string $message = null): self
    {
        $value = $this->value($field);

        if ($value === null || $value === '' || $value === []) {
            $this->fail($field, $message ?? I18n::t('validation.required'));
        }

        return $this;
    }

    public function min(string $field, int $length, ?string $message = null): self
    {
        $value = (string) ($this->value($field) ?? '');

        if ($value !== '' && mb_strlen($value) < $length) {
            $this->fail($field, $message ?? I18n::t('validation.min', ['min' => $length]));
        }

        return $this;
    }

    public function max(string $field, int $length, ?string $message = null): self
    {
        $value = (string) ($this->value($field) ?? '');

        if (mb_strlen($value) > $length) {
            $this->fail($field, $message ?? I18n::t('validation.max', ['max' => $length]));
        }

        return $this;
    }

    public function email(string $field, ?string $message = null): self
    {
        $value = (string) ($this->value($field) ?? '');

        if ($value !== '' && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            $this->fail($field, $message ?? I18n::t('validation.email'));
        }

        return $this;
    }

    public function in(string $field, array $allowed, ?string $message = null): self
    {
        $value = $this->value($field);

        if ($value !== null && $value !== '' && !in_array($value, $allowed, true)) {
            $this->fail($field, $message ?? I18n::t('validation.invalid'));
        }

        return $this;
    }

    public function regex(string $field, string $pattern, ?string $message = null): self
    {
        $value = (string) ($this->value($field) ?? '');

        if ($value !== '' && preg_match($pattern, $value) !== 1) {
            $this->fail($field, $message ?? I18n::t('validation.invalid'));
        }

        return $this;
    }

    public function matches(string $field, string $otherField, ?string $message = null): self
    {
        if ((string) ($this->value($field) ?? '') !== (string) ($this->value($otherField) ?? '')) {
            $this->fail($field, $message ?? I18n::t('validation.confirmed'));
        }

        return $this;
    }

    public function integerBetween(string $field, int $min, int $max, ?string $message = null): self
    {
        $value = $this->value($field);

        if ($value === null || $value === '') {
            return $this;
        }

        if (!is_numeric($value) || (int) $value < $min || (int) $value > $max) {
            $this->fail($field, $message ?? I18n::t('validation.between', ['min' => $min, 'max' => $max]));
        }

        return $this;
    }

    public function date(string $field, ?string $message = null): self
    {
        $value = (string) ($this->value($field) ?? '');

        if ($value !== '' && strtotime($value) === false) {
            $this->fail($field, $message ?? I18n::t('validation.date'));
        }

        return $this;
    }

    public function when(bool $condition, callable $callback): self
    {
        if ($condition) {
            $callback($this);
        }

        return $this;
    }

    public function fail(string $field, string $message): self
    {
        $this->errors[$field] ??= $message;

        return $this;
    }

    public function passes(): bool
    {
        return $this->errors === [];
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    /** @return array<string, string> */
    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(): ?string
    {
        foreach ($this->errors as $message) {
            return $message;
        }

        return null;
    }
}
