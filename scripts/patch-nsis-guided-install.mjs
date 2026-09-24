#!/usr/bin/env node
/**
 * patch-nsis-guided-install.mjs
 *
 * NativePHP's bundled electron-builder.mjs hardcodes the NSIS `nsis` block and
 * does not expose a way to enable the guided (non-one-click) Windows installer.
 * By default electron-builder uses `oneClick: true`, which installs silently
 * without letting the user choose the installation folder.
 *
 * This script runs as a `prebuild` hook (see config/nativephp.php) AFTER
 * `native:install` has mirrored the vendor electron template into
 * nativephp/electron/, and BEFORE electron-builder executes. It injects
 * `oneClick: false` and `allowToChangeInstallationDirectory: true` into the
 * `nsis` block so the installed app shows the standard "choose a folder"
 * dialog during installation.
 *
 * The patch is idempotent — it only adds the keys when they are absent, so it
 * is safe to run multiple times (e.g. local + CI builds).
 */

import { readFileSync, writeFileSync, existsSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const repoRoot = join(__dirname, '..');
const builderPath = join(repoRoot, 'nativephp', 'electron', 'electron-builder.mjs');

if (!existsSync(builderPath)) {
    console.log('[patch-nsis] electron-builder.mjs not found — skipping (expected if not building with NativePHP).');
    process.exit(0);
}

let source = readFileSync(builderPath, 'utf8');

if (source.includes('oneClick:')) {
    console.log('[patch-nsis] guided-install keys already present — nothing to do.');
    process.exit(0);
}

// Inject the guided-install keys into the `nsis` block. The block always ends
// with the `deleteAppDataOnUninstall` line, so anchor on that.
const anchor = 'deleteAppDataOnUninstall: deleteAppDataOnUninstall,';
if (!source.includes(anchor)) {
    console.error('[patch-nsis] could not find the nsis block anchor — aborting.');
    process.exit(1);
}

const injection = anchor + "\n        oneClick: false,\n        allowToChangeInstallationDirectory: true,";
source = source.replace(anchor, injection);

writeFileSync(builderPath, source, 'utf8');
console.log('[patch-nsis] injected guided-install NSIS options (oneClick=false, allowToChangeInstallationDirectory=true).');
