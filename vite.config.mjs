import { defineConfig } from 'vite';
import tailwindcss from '@tailwindcss/vite';
import { resolve } from 'node:path';

const root = import.meta.dirname;

/**
 * Vite is only used at build time. The generated files are plain static assets
 * that any Apache server can serve - no Node runtime is needed in production.
 */
export default defineConfig({
  root,
  plugins: [tailwindcss()],
  build: {
    // dist/ is the final Apache document root (assets/, admin/, app/, ...)
    outDir: 'dist',
    assetsDir: 'assets',
    emptyOutDir: false,
    manifest: true,
    // Keep the stylesheet attached to the entry chunk so the PHP manifest
    // can resolve it (cssCodeSplit: false would orphan it).
    cssCodeSplit: true,
    sourcemap: false,
    rollupOptions: {
      input: {
        app: resolve(root, 'src/frontend/app.js'),
      },
      output: {
        entryFileNames: 'assets/[name]-[hash].js',
        chunkFileNames: 'assets/[name]-[hash].js',
        assetFileNames: 'assets/[name]-[hash][extname]',
      },
    },
  },
});
