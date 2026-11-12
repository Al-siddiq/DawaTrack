import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

// Operational release inventory, not a browser/business acceptance test suite.
const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const at = value => path.resolve(root, value);
const digest = buffer => crypto.createHash('sha256').update(buffer).digest('hex');
const checking = process.argv.includes('--check');
const fixLineEndings = process.argv.includes('--fix-line-endings');
if (fixLineEndings && !checking) throw Error('--fix-line-endings requires --check and a recorded inventory.');
const outputRelative = process.argv.find(value => value.startsWith('--output='))?.slice('--output='.length)
    || 'docs/ui/releases/ui4-a-artifact-manifest.json';
const outputPath = at(outputRelative);
if (!outputPath.startsWith(`${at('docs')}${path.sep}`) || !outputPath.endsWith('.json')) throw Error('Release inventory output must be a JSON file under docs/.');
// A Git archive has no .git directory. Verify its recorded file list without requiring Git.
const expected = checking ? JSON.parse(fs.readFileSync(outputPath, 'utf8')) : null;
const tracked = checking && !fs.existsSync(at('.git'))
    ? expected.files.map(file => file.path)
    : execFileSync('git', ['ls-files', '-z'], { cwd: root, encoding: 'utf8' }).split('\0').filter(Boolean);
const current = tracked.filter(file => fs.existsSync(at(file)));
const selected = current.filter(file => /^(app\/|public\/|resources\/(js|css|ui)\/)/.test(file) || ['.gitattributes', 'composer.json', 'composer.lock', 'package.json', 'package-lock.json'].includes(file));

// A clean Git status can hide CRLF checkout conversion. Keep raw-byte verification;
// repair only when removing CR from CRLF exactly recovers the reviewed bytes/hash.
if (checking) {
    const recorded = new Map(expected.files.map(file => [file.path, file]));
    const selectedSet = new Set(selected);
    const lineEndings = [], differences = [];
    for (const file of expected.files) {
        if (!fs.existsSync(at(file.path))) { differences.push(`MISSING ${file.path}`); continue; }
        if (!selectedSet.has(file.path)) { differences.push(`NOT_TRACKED ${file.path}`); continue; }
        const buffer = fs.readFileSync(at(file.path));
        if (buffer.length === file.bytes && digest(buffer) === file.sha256) continue;
        const text = buffer.toString('utf8');
        const normalized = Buffer.from(text.replace(/\r\n/g, '\n'), 'utf8');
        const recoverable = !buffer.includes(0) && Buffer.from(text, 'utf8').equals(buffer)
            && normalized.length === file.bytes && digest(normalized) === file.sha256;
        if (recoverable) lineEndings.push({ path: file.path, normalized });
        else differences.push(`CHANGED ${file.path} (content differs beyond CRLF line endings)`);
    }
    for (const file of selected) if (!recorded.has(file)) differences.push(`ADDED ${file}`);
    if (differences.length || (lineEndings.length && !fixLineEndings)) {
        const details = [...differences, ...lineEndings.map(file => `LINE_ENDINGS_ONLY ${file.path}`)];
        for (const detail of details.slice(0, 25)) console.error(detail);
        if (details.length > 25) console.error(`... ${details.length - 25} more differing files.`);
        console.error(`Content/set differences: ${differences.length}; CRLF-only files: ${lineEndings.length}. No files were changed.`);
        if (!differences.length) throw Error('Only Windows CRLF checkout bytes differ. Run this same --check command with --fix-line-endings to restore the exact recorded LF bytes. Do not regenerate the inventory.');
        throw Error('Release inventory differs from the recorded candidate. Review the listed content/set changes. Line-ending repair cannot approve changed content.');
    }
    for (const file of lineEndings) fs.writeFileSync(at(file.path), file.normalized);
    if (lineEndings.length) console.log(`Restored exact recorded LF bytes in ${lineEndings.length} files; no inventory regenerated.`);
}
const required = ['public/assets/css/app.css', 'public/assets/vendor/vue/vue.runtime.global.prod.js', 'public/assets/vendor/vue/LICENSE.txt', 'public/assets/vendor/dexie/LICENSE.txt', 'public/assets/vendor/bootstrap/LICENSE.txt', 'public/assets/ui/bootstrap-icons-LICENSE.txt', 'app/Views/auth/email_2fa_show.php', 'app/Views/auth/email_2fa_verify.php', 'app/Views/auth/email_activate_show.php'];
for (const file of required) if (!fs.existsSync(at(file))) throw Error(`Missing release file: ${file}. Run npm run build and stage added release files.`);
const sourceModules = current.filter(file => file.startsWith('resources/js/') && file.endsWith('.js'));
for (const source of sourceModules) {
    const published = source.replace('resources/js/', 'public/assets/js/');
    if (!fs.existsSync(at(published)) || digest(fs.readFileSync(at(source))) !== digest(fs.readFileSync(at(published)))) throw Error(`Published module differs: ${source}. Run npm run build.`);
}
const publicModules = current.filter(file => file.startsWith('public/assets/js/') && file.endsWith('.js'));
for (const file of publicModules) {
    if (!sourceModules.includes(file.replace('public/assets/js/', 'resources/js/'))) throw Error(`Orphan public module: ${file}`);
    const text = fs.readFileSync(at(file), 'utf8');
    for (const match of text.matchAll(/\b(?:import|export)\s+(?:[^;]*?\s+from\s*)?["']([^"']+)["']/g)) {
        const specifier = match[1];
        if (!specifier.startsWith('.')) throw Error(`Nonlocal module import in ${file}: ${specifier}`);
        if (!fs.existsSync(path.resolve(path.dirname(at(file)), specifier))) throw Error(`Unresolved module import in ${file}: ${specifier}`);
    }
}
const icon = JSON.parse(fs.readFileSync(at('public/assets/ui/manifest.json'), 'utf8'));
const sprite = `public${icon.sprite}`;
if (!/^public\/assets\/ui\/icons-[a-f0-9]+\.svg$/.test(sprite) || digest(fs.readFileSync(at(sprite))) !== icon.sha256) throw Error('Icon manifest/sprite mismatch.');
if (icon.bytes > icon.budget.rawBytes || icon.gzipBytes > icon.budget.gzipBytes) throw Error('Icon budget exceeded.');
const packageData = JSON.parse(fs.readFileSync(at('package.json'), 'utf8'));
const lock = JSON.parse(fs.readFileSync(at('package-lock.json'), 'utf8'));
for (const [name, version] of Object.entries(packageData.devDependencies)) if (lock.packages[`node_modules/${name}`]?.version !== version) throw Error(`Dependency pin differs from lock: ${name}`);
const activeEntrypoints = ['public/assets/js/shop/app.js', 'public/assets/js/admin/app.js', 'public/assets/js/auth/entry.js'];
for (const file of required) if (!selected.includes(file)) throw Error(`New release file not staged: ${file}`);
const files = selected.sort().map(file => {
    const buffer = fs.readFileSync(at(file));
    return { path: file, bytes: buffer.length, sha256: digest(buffer) };
});
const contentDigest = digest(Buffer.from(files.map(file => `${file.path}\0${file.sha256}\0${file.bytes}`).join('\n')));
const output = {
    schema: 1,
    scope: 'Tracked app/public and UI source/dependency lock inventory; excludes vendor PHP, environment, writable data, uploads and database.',
    gitBinding: 'Record the exact reviewed/deployed commit separately in release-decision.csv. Content hashes do not imply deployment or acceptance.',
    contentSha256: contentDigest,
    buildNode: process.version,
    dependencyPins: packageData.devDependencies,
    sourceModules: sourceModules.length,
    publishedModules: publicModules.length,
    activeEntrypoints,
    icon: { version: icon.version, sprite: icon.sprite, sha256: icon.sha256, bytes: icon.bytes, gzipBytes: icon.gzipBytes },
    files,
};
fs.mkdirSync(path.dirname(outputPath), { recursive: true });
if (checking) {
    if (expected.contentSha256 !== contentDigest) throw Error('Release inventory differs from the recorded candidate. Rebuild/review before regenerating.');
    console.log(`UI release inventory matches: ${contentDigest} (${files.length} files).`);
} else {
    fs.writeFileSync(outputPath, `${JSON.stringify(output, null, 2)}\n`);
    console.log(`UI release inventory written: ${contentDigest} (${files.length} files). Native acceptance is separate.`);
}
