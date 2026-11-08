import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { execFileSync } from 'node:child_process';

// Resolve from this script so paths work on Windows and regardless of the caller's cwd.
const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const at = (...parts) => path.join(root, ...parts);

execFileSync(process.execPath, [at('scripts', 'build-ui-icons.mjs')], { cwd: root, stdio: 'inherit' });

execFileSync(process.execPath, [at('scripts', 'compile-shop-template.mjs')], {
    cwd: root,
    stdio: 'inherit',
});

for (const [source, destination] of [
    ['node_modules/vue/dist/vue.runtime.global.prod.js', 'public/assets/vendor/vue/vue.runtime.global.prod.js'],
    ['node_modules/vue/dist/vue.global.prod.js', 'public/assets/vendor/vue/vue.global.prod.js'],
    ['node_modules/dexie/dist/dexie.min.js', 'public/assets/vendor/dexie/dexie.min.js'],
]) {
    const target = at(destination);
    fs.mkdirSync(path.dirname(target), { recursive: true });
    fs.copyFileSync(at(source), target);
}

for (const directory of ['shop', 'admin', 'auth', 'shared', 'api', 'components']) {
    const target = at('public', 'assets', 'js', directory);
    fs.rmSync(target, { recursive: true, force: true });
    fs.cpSync(at('resources', 'js', directory), target, { recursive: true });
}

fs.rmSync(at('public/assets/js/shop/template.html'), { force: true });
for (const file of fs.readdirSync(at('public/assets/js/shop'))) {
    if (file.endsWith('.bak')) fs.rmSync(at('public/assets/js/shop', file), { force: true });
}
console.log('Frontend assets built.');
