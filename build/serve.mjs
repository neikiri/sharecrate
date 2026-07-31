/**
 * Local preview of the built site using PHP's built in server.
 *   npm run build && npm run serve
 */

import { spawn } from 'node:child_process';
import { existsSync } from 'node:fs';
import { join, resolve } from 'node:path';

const root = resolve(import.meta.dirname, '..');
const dist = join(root, 'dist');

if (!existsSync(dist)) {
  process.stderr.write('dist/ does not exist yet - run "npm run build" first.\n');
  process.exit(1);
}

const host = process.env.HOST ?? '127.0.0.1';
const port = process.env.PORT ?? '8080';

process.stdout.write(`PHP dev server on http://${host}:${port}\n`);

const child = spawn(
  'php',
  ['-S', `${host}:${port}`, '-t', dist, join(root, 'build', 'router.php')],
  { stdio: 'inherit', shell: false }
);

child.on('exit', (code) => process.exit(code ?? 0));
