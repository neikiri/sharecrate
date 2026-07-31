/**
 * Runs `php -l` over every PHP file in src/server.
 */

import { readdir } from 'node:fs/promises';
import { execFile } from 'node:child_process';
import { join, resolve, relative } from 'node:path';
import { promisify } from 'node:util';

const run = promisify(execFile);
const root = resolve(import.meta.dirname, '..');
const target = join(root, 'src', 'server');

async function collect(dir) {
  const entries = await readdir(dir, { withFileTypes: true });
  const files = [];

  for (const entry of entries) {
    const full = join(dir, entry.name);

    if (entry.isDirectory()) {
      files.push(...(await collect(full)));
    } else if (entry.name.endsWith('.php')) {
      files.push(full);
    }
  }

  return files;
}

const files = await collect(target);
const failures = [];

await Promise.all(
  files.map(async (file) => {
    try {
      await run('php', ['-l', '-d', 'display_errors=1', file]);
    } catch (error) {
      failures.push(`${relative(root, file)}\n${error.stdout ?? ''}${error.stderr ?? ''}`);
    }
  })
);

if (failures.length > 0) {
  process.stderr.write(`\n✗ ${failures.length} file(s) with syntax errors:\n\n`);
  failures.forEach((failure) => process.stderr.write(`${failure}\n`));
  process.exit(1);
}

process.stdout.write(`✓ ${files.length} PHP files, no syntax errors\n`);
