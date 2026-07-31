/**
 * Produces dist/ - the folder you upload to Apache.
 *
 * Layout of the result (no public/ wrapper):
 *
 *   dist/
 *     .htaccess          rewrite rules + hardening
 *     index.php          front controller
 *     admin/index.php    dashboard entry
 *     assets/            hashed css, js, fonts, favicon + manifest.json
 *     app/               application code (denied by .htaccess)
 *     bin/               cron helpers
 *     database/          schema.sql used by the installer
 *     storage/           upload target (denied by .htaccess)
 */

import { build } from 'vite';
import { rm, mkdir, cp, readFile, writeFile, readdir, stat, access } from 'node:fs/promises';
import { existsSync } from 'node:fs';
import { join, resolve, relative, sep } from 'node:path';

const root = resolve(import.meta.dirname, '..');
const dist = join(root, 'dist');
const serverSrc = join(root, 'src', 'server');
const staticSrc = join(root, 'src', 'frontend', 'static');

const log = (message) => process.stdout.write(`${message}\n`);

async function clean() {
  await rm(dist, { recursive: true, force: true });
  await mkdir(dist, { recursive: true });
}

async function buildAssets() {
  log('› building css/js with vite');
  await build({ configFile: join(root, 'vite.config.mjs'), logLevel: 'warn' });
}

/**
 * Turns the Vite manifest into a tiny map the PHP layer can read:
 * { "js": "assets/app-abc.js", "css": "assets/app-def.css" }
 */
async function writeManifest() {
  const candidates = [
    join(dist, '.vite', 'manifest.json'),
    join(dist, 'manifest.json'),
    join(dist, 'assets', '.vite', 'manifest.json'),
  ];

  let raw = null;
  let source = null;

  for (const candidate of candidates) {
    if (existsSync(candidate)) {
      raw = JSON.parse(await readFile(candidate, 'utf8'));
      source = candidate;
      break;
    }
  }

  if (!raw) {
    throw new Error('Vite manifest not found - did the asset build succeed?');
  }

  const entry = Object.values(raw).find((chunk) => chunk.isEntry) ?? Object.values(raw)[0];

  if (!entry) {
    throw new Error('No entry chunk in the Vite manifest.');
  }

  let css = entry.css ?? [];

  // Safety net: pick up standalone stylesheets that Vite did not attach
  // to the entry chunk.
  if (css.length === 0) {
    css = Object.values(raw)
      .map((chunk) => chunk.file)
      .filter((file) => typeof file === 'string' && file.endsWith('.css'));
  }

  if (css.length === 0) {
    const assetFiles = existsSync(join(dist, 'assets')) ? await readdir(join(dist, 'assets')) : [];
    css = assetFiles.filter((file) => file.endsWith('.css')).map((file) => `assets/${file}`);
  }

  if (css.length === 0) {
    throw new Error('No stylesheet produced by the asset build.');
  }

  const manifest = {
    js: entry.file,
    css: css.join(','),
    built_at: new Date().toISOString(),
  };

  await mkdir(join(dist, 'assets'), { recursive: true });
  await writeFile(join(dist, 'assets', 'manifest.json'), `${JSON.stringify(manifest, null, 2)}\n`, 'utf8');

  // The raw Vite manifest is not needed at runtime.
  await rm(join(dist, '.vite'), { recursive: true, force: true });
  if (source && source !== join(dist, 'assets', 'manifest.json') && existsSync(source)) {
    await rm(source, { force: true });
  }

  log(`› assets: ${manifest.js}${manifest.css ? ` + ${manifest.css}` : ''}`);
}

async function copyServer() {
  log('› copying php application');
  await cp(serverSrc, dist, { recursive: true, force: true });

  // The build output must not ship a stale .env
  await rm(join(dist, '.env'), { force: true });
}

async function copyStatic() {
  if (!existsSync(staticSrc)) {
    return;
  }

  await mkdir(join(dist, 'assets'), { recursive: true });
  const files = await readdir(staticSrc);

  for (const file of files) {
    await cp(join(staticSrc, file), join(dist, 'assets', file), { recursive: true, force: true });
  }

  log(`› static assets: ${files.join(', ')}`);
}

async function copySupportFiles() {
  await mkdir(join(dist, 'database'), { recursive: true });
  await cp(join(root, 'database', 'schema.sql'), join(dist, 'database', 'schema.sql'), { force: true });
  await cp(join(root, '.env.example'), join(dist, '.env.example'), { force: true });

  if (existsSync(join(root, 'README.md'))) {
    await cp(join(root, 'README.md'), join(dist, 'README.md'), { force: true });
  }
}

async function createRuntimeDirs() {
  const dirs = [
    join(dist, 'storage', 'uploads'),
    join(dist, 'storage', 'cache', 'thumbs'),
    join(dist, 'storage', 'logs'),
  ];

  for (const dir of dirs) {
    await mkdir(dir, { recursive: true });
    await writeFile(join(dir, '.gitkeep'), '', 'utf8');
  }
}

async function directorySize(dir) {
  let total = 0;
  let files = 0;

  const walk = async (current) => {
    const entries = await readdir(current, { withFileTypes: true });

    for (const entry of entries) {
      const full = join(current, entry.name);

      if (entry.isDirectory()) {
        await walk(full);
      } else {
        const info = await stat(full);
        total += info.size;
        files += 1;
      }
    }
  };

  await walk(dir);

  return { total, files };
}

function formatBytes(bytes) {
  const units = ['B', 'kB', 'MB'];
  let value = bytes;
  let unit = 0;

  while (value >= 1024 && unit < units.length - 1) {
    value /= 1024;
    unit += 1;
  }

  return `${value.toFixed(value < 10 && unit > 0 ? 1 : 0)} ${units[unit]}`;
}

async function verify() {
  const required = [
    '.htaccess',
    'index.php',
    'admin/index.php',
    'app/bootstrap.php',
    'app/routes.php',
    'app/locales/cs.php',
    'app/locales/en.php',
    'assets/manifest.json',
    'assets/favicon.svg',
    'database/schema.sql',
    'storage/.htaccess',
    'app/.htaccess',
  ];

  const missing = [];

  for (const file of required) {
    try {
      await access(join(dist, ...file.split('/')));
    } catch {
      missing.push(file);
    }
  }

  if (missing.length > 0) {
    throw new Error(`Build incomplete, missing: ${missing.join(', ')}`);
  }
}

async function main() {
  const started = Date.now();

  await clean();
  await buildAssets();
  await writeManifest();
  await copyServer();
  await copyStatic();
  await copySupportFiles();
  await createRuntimeDirs();
  await verify();

  const { total, files } = await directorySize(dist);

  log('');
  log(`✓ build ready in ${relative(root, dist)}${sep}`);
  log(`  ${files} files · ${formatBytes(total)} · ${Date.now() - started} ms`);
  log('');
  log('  Upload the contents of dist/ into your Apache document root,');
  log('  then open https://your-domain/install to finish the setup.');
}

main().catch((error) => {
  process.stderr.write(`\n✗ ${error.message}\n`);
  process.exit(1);
});
