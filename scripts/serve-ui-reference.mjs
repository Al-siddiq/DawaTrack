import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const types = { '.html': 'text/html', '.css': 'text/css', '.js': 'text/javascript', '.svg': 'image/svg+xml', '.json': 'application/json', '.md': 'text/plain', '.csv': 'text/plain', '.txt': 'text/plain' };
// Loopback-only, read-only documentation server. Never serves PHP, env/config or business APIs.
http.createServer((request, response) => {
    response.setHeader('Content-Security-Policy', "default-src 'none'; script-src 'self'; style-src 'self'; img-src 'self'; connect-src 'none'; font-src 'self'; base-uri 'none'; frame-ancestors 'none'");
    response.setHeader('X-Content-Type-Options', 'nosniff');
    response.setHeader('Cache-Control', 'no-store');
    let pathname;
    try { pathname = decodeURIComponent(new URL(request.url, 'http://localhost').pathname); }
    catch { response.writeHead(400).end(); return; }
    if (pathname === '/') pathname = '/docs/ui/reference/index.html';
    if (pathname.startsWith('/assets/ui/')) pathname = '/public' + pathname;
    const allowed = ['/docs/ui/', '/public/assets/css/', '/public/assets/ui/', '/public/assets/js/components/ui/', '/public/assets/js/shared/', '/public/assets/js/api/', '/public/assets/vendor/vue/'];
    const file = path.resolve(root, `.${pathname}`);
    const extension = path.extname(file);
    const canonical = '/' + path.relative(root, file).split(path.sep).join('/');
    if (!['GET', 'HEAD'].includes(request.method) || !allowed.some(prefix => canonical.startsWith(prefix)) || canonical.split('/').some(part => part.startsWith('.')) || !file.startsWith(`${root}${path.sep}`) || !types[extension]) {
        response.writeHead(403).end('Not available in the UI reference.'); return;
    }
    fs.stat(file, (error, stat) => {
        if (error || !stat.isFile()) { response.writeHead(404).end('Not found. Run npm run build and npm run ui:reference.'); return; }
        response.writeHead(200, { 'Content-Type': `${types[extension]}; charset=utf-8` });
        if (request.method === 'HEAD') response.end();
        else fs.createReadStream(file).pipe(response);
    });
}).listen(4173, '127.0.0.1', () => console.log('UI design reference: http://127.0.0.1:4173 (Ctrl+C to stop).'));
