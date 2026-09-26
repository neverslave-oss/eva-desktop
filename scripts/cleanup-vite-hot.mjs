#!/usr/bin/env node
/**
 * cleanup-vite-hot.mjs
 *
 * Removes the stale Vite dev-server `public/hot` file before a production
 * NativePHP build.
 *
 * Laravel's `@vite()` Blade directive checks for the existence of `public/hot`.
 * If it exists, it assumes the Vite dev server is running and generates asset
 * URLs pointing to the dev server (e.g. http://[::1]:5173) instead of using
 * the production manifest in `public/build`.
 *
 * In a packaged NativePHP app there is no Vite dev server, so those URLs are
 * unreachable and the app renders with no CSS/JS (unstyled). The `hot` file is
 * created whenever `npm run dev` is started and is gitignored, so it silently
 * persists across builds.
 *
 * This script runs as a `prebuild` hook (see config/nativephp.php) to guarantee
 * a clean production asset build every time.
 */

import { existsSync, rmSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const hotPath = join(__dirname, '..', 'public', 'hot');

if (existsSync(hotPath)) {
    rmSync(hotPath, { force: true });
    console.log('[cleanup-vite-hot] removed stale public/hot (Vite dev-server marker).');
} else {
    console.log('[cleanup-vite-hot] no public/hot file present — nothing to do.');
}
