import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const at = (...parts) => path.join(root, ...parts);
const vectors = JSON.parse(fs.readFileSync(at('docs/ui/reference/icon-vectors.json'), 'utf8'));
const escape = value => String(value).replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('"', '&quot;');
const icon = (name, x, y, size = 20, color = '#155d46') => `<svg x="${x}" y="${y}" width="${size}" height="${size}" viewBox="0 0 16 16" fill="${color}" aria-hidden="true">${vectors[name]}</svg>`;
const destination = { Home: 'home', Sell: 'sell', Stock: 'stock', Customers: 'customers', Menu: 'menu', Sales: 'sales', Staff: 'staff', 'Shop settings': 'settings', Activity: 'activity', Shops: 'shop', 'Add shop': 'add', Plans: 'plan', 'Web addresses': 'web-address', 'Starting cash': 'cash', 'Shop appearance': 'appearance', 'Web address': 'web-address', Plan: 'plan', 'My account': 'account' };
const actions = { 'Set starting cash': 'cash', 'Continue sale': 'next', 'Next: payment': 'next', 'Add stock': 'add', 'Add shop': 'add', Add: 'add', 'New sale': 'add', 'Review sale': 'next', 'Review return': 'next', 'Save return': 'success', 'View sale': 'sales', 'Print return receipt': 'print', 'Check saved status': 'refresh', 'View plan': 'plan', 'View sales': 'sales', 'View stock': 'stock', 'Sign in': 'next', 'Add customer': 'add', 'Save sale': 'success', 'Back to payment': 'back' };
const ids = fs.readdirSync(at('docs/ui/UI1/screens')).filter(f => f.endsWith('.svg')).sort();
fs.mkdirSync(at('docs/ui/reference/screens'), { recursive: true });
const sceneData = ids.map(file => {
    const id = file.replace('.svg', '');
    const desktop = id.startsWith('desktop-');
    let svg = fs.readFileSync(at('docs/ui/UI1/screens', file), 'utf8');
    svg = svg.replace(/<text\b([^>]*)>([^<]*)<\/text>/g, (whole, attrs, text) => {
        const x = Number(attrs.match(/\bx="([\d.]+)"/)?.[1]);
        const y = Number(attrs.match(/\by="([\d.]+)"/)?.[1]);
        const color = attrs.match(/\bfill="([^"]+)"/)?.[1] ?? '#155d46';
        if (desktop && text === 'Account     Sign out') {
            return icon('account', 24, y - 17, 18, color) + '<text x="48" y="' + y + '" font-family="DejaVu Sans,Arial,sans-serif" font-size="13" fill="' + color + '">Account</text>'
                + icon('sign-out', 116, y - 17, 18, color) + '<text x="140" y="' + y + '" font-family="DejaVu Sans,Arial,sans-serif" font-size="13" fill="' + color + '">Sign out</text>';
        }
        const name = destination[text];
        if (!desktop && y === 811 && name) {
            return icon(name, x + 17.5, 787, 22, color) + `<text ${attrs.replace(/\bx="[^"]+"/, `x="${x + 28.5}"`).replace(/\by="[^"]+"/, 'y="826"')} text-anchor="middle">${text}</text>`;
        }
        if (desktop && x === 30 && y >= 112 && y < 700 && name) {
            return icon(name, x, y - 17, 20, color) + `<text ${attrs.replace(/\bx="[^"]+"/, 'x="62"')}>${text}</text>`;
        }
        if (id === 'mobile-menu' && x === 40 && y >= 198 && name) {
            return icon(name, x, y - 17, 20, color) + `<text ${attrs.replace(/\bx="[^"]+"/, 'x="72"')}>${text}</text>`;
        }
        if (actions[text] && /font-size="15"/.test(attrs) && /font-weight="700"/.test(attrs)) {
            return icon(actions[text], x, y - 16, 18, color) + `<text ${attrs.replace(/\bx="[^"]+"/, `x="${x + 26}"`)}>${text}</text>`;
        }
        if (/^Back to /.test(text) && x === 24) {
            return icon('back', x, y - 16, 18, color) + `<text ${attrs.replace(/\bx="[^"]+"/, 'x="50"')}>${text}</text>`;
        }
        return whole;
    });
    fs.writeFileSync(at('docs/ui/reference/screens', file), svg);
    return { id, desktop, title: svg.match(/aria-label="([^"]+)"/)?.[1] ?? id, svg };
});
const selected = (name, label, active = false) => `<button type="button" class="reference-nav-item${active ? ' is-active' : ''}"${active ? ' aria-current="page"' : ''}>${inline(name)}<span>${label}</span></button>`;
function inline(name) { return `<svg class="ui-icon" viewBox="0 0 16 16" fill="currentColor" focusable="false" aria-hidden="true">${vectors[name]}</svg>`; }
const options = sceneData.map(s => `<option value="${s.id}">${escape(s.title)} — ${s.desktop ? 'Desktop' : 'Phone'} (${s.id})</option>`).join('');
const figures = sceneData.map(s => `<figure class="design-scene${s.desktop ? ' design-scene--desktop' : ''}" id="${s.id}" hidden><figcaption>${escape(s.title)} · ${s.id}</figcaption>${s.svg}</figure>`).join('');
const catalogue = Object.entries(JSON.parse(fs.readFileSync(at('resources/ui/icons.json'), 'utf8')).icons).sort().map(([name, upstream]) => `<li>${inline(name)}<span><strong>${name}</strong><br><small>${upstream}</small></span></li>`).join('');
const page = `<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>DawaTrack UI2-B component reference</title><link rel="stylesheet" href="/public/assets/css/app.css"><link rel="stylesheet" href="/docs/ui/reference/reference.css"><script src="/public/assets/vendor/vue/vue.runtime.global.prod.js" defer></script><script type="module" src="/docs/ui/reference/reference.js"></script></head>
<body class="ui-scope reference-body"><a class="reference-skip" href="#reference-main">Skip to design review</a>
<header class="reference-header"><div><p class="reference-eyebrow">DawaTrack · UI2-B</p><h1>Icons and interaction design</h1><p>Design examples only. No customer records or payments are read or saved. The interaction section uses the real shared components with local fixtures; no business records are changed.</p></div><a class="ui-button ui-button--secondary" href="/docs/ui/README.md">Developer reference</a></header>
<main id="reference-main" tabindex="-1"><nav class="reference-tabs" aria-label="Design sections"><button type="button" class="ui-button" data-section="screens" aria-pressed="true">Screen designs</button><button type="button" class="ui-button ui-button--secondary" data-section="states" aria-pressed="false">Interaction examples</button><button type="button" class="ui-button ui-button--secondary" data-section="icons" aria-pressed="false">Icon catalogue</button></nav>
<section id="screens"><h2>18 representative designs with icons</h2><div class="ui-field reference-selector"><label for="scene">Choose a screen</label><select id="scene">${options}</select></div>${figures}</section>
<section id="states" hidden><h2>Interaction examples</h2><p>These local demonstrations specify visual and interaction intent. Their timers and sample records are not production save or recovery behavior.</p><div class="reference-grid">
<article class="ui-panel"><h3>Navigation with labels</h3><nav class="reference-bottom" aria-label="Sample phone navigation">${selected('home','Home',true)}${selected('sell','Sell')}${selected('stock','Stock')}${selected('customers','Customers')}${selected('menu','Menu')}</nav><p>Use the same meaning in the sidebar and buttons.</p><div class="ui-actions"><button class="ui-button ui-button--secondary" type="button">${inline('back')} Back to stock</button><button class="ui-button" type="button">${inline('add')} Add stock</button></div><p class="reference-caption">Navigation examples stay here; inspect the Screens section for layouts.</p></article>
<article class="ui-panel"><h3>Saving and loading</h3><p>Button text stays visible and the width does not jump.</p><button id="save-example" class="ui-button reference-save" type="button">${inline('success')} <span>Save sale</span></button><p id="save-status" role="status">Try the saving example.</p><div class="ui-information" aria-busy="true"><span class="ui-spinner" aria-hidden="true"></span><span>Loading products…</span></div></article>
<article class="ui-panel"><h3>Optional details</h3><details class="reference-accordion"><summary>${inline('info')} Sale details ${inline('expand')}</summary><p>Saved today, 10:42 am. Reference DT-1043.</p></details><div class="ui-information">${inline('info')}<p>Sale total and amount still owed remain visible outside the accordion.</p></div></article>
<article class="ui-panel"><h3>Confirmations and sheets</h3><p>One focused task; keep the parent basket intact.</p><div class="ui-actions"><button id="open-modal" class="ui-button ui-button--secondary" type="button">${inline('cancel-record')} Cancel saved sale</button><button id="open-sheet" class="ui-button" type="button">${inline('customers')} Choose customer</button></div></article>
<article class="ui-panel"><h3>Correct a payment</h3><div class="ui-field"><label for="example-cash">Paid in cash (₦)</label><input id="example-cash" value="8,000.00" aria-invalid="true" aria-describedby="example-cash-error"><span class="ui-error-text" id="example-cash-error">Enter ₦7,500.00 or less. Payment cannot exceed the sale total.</span></div><p>Your products stay in the basket while you correct the payment.</p></article>
<article class="ui-panel"><h3>Unknown save outcome</h3><div class="ui-information ui-information--warning">${inline('warning')}<p>We could not confirm whether the sale was saved. Keep this sale open and check its status before recording it again.</p></div><button class="ui-button ui-button--secondary" type="button">${inline('refresh')} Check saved status</button><p class="reference-caption">Persistent state. This reference button makes no request.</p></article>
<article class="ui-panel"><h3>Success message</h3><button id="show-success" class="ui-button ui-button--secondary" type="button">${inline('success')} Show success example</button><div id="success-example" class="ui-information reference-success" role="status" hidden><span>${inline('success')} Sale saved. Reference DT-1043.</span><button id="close-success" class="ui-button ui-button--secondary ui-button--square" type="button" aria-label="Close success message">${inline('close')}</button></div><p id="saved-evidence">Saved record evidence remains on the page after the message closes.</p></article>
<article class="ui-panel"><h3>Secondary actions</h3><div class="reference-dropdown"><button id="more-actions" type="button" class="ui-button ui-button--secondary" aria-expanded="false" aria-controls="action-list">More actions ${inline('expand')}</button><div id="action-list" class="reference-dropdown-list" hidden><button type="button">${inline('print')} Print receipt</button><button type="button">${inline('sales')} View sale</button></div></div><p>Main actions remain outside this list.</p></article>
</div></section>
<section id="icons" hidden><h2>34 locally bundled symbols</h2><p>Bootstrap Icons 1.13.2 · MIT licence retained in the asset bundle. This inline catalogue also remains visible with its network disabled.</p><ul class="reference-icons">${catalogue}</ul><h3>Application icon renderer</h3><p>This second catalogue exercises the real runtime-only Vue component and local fingerprinted sprite. Both catalogues must display the same symbols.</p><div id="runtime-icon-example"></div></section>
</main>
<dialog id="confirmation-example" class="reference-dialog" aria-labelledby="confirmation-title" aria-describedby="confirmation-description"><h2 id="confirmation-title" tabindex="-1">Cancel this saved sale?</h2><p id="confirmation-description">The original sale stays in the history. Review the stock, payment and amount-owed changes before confirming.</p><div class="ui-information ui-information--warning">${inline('warning')}<p>This is a design example. No sale will be cancelled.</p></div><div class="ui-field"><label for="cancel-reason">Why are you cancelling this sale?</label><textarea id="cancel-reason" rows="3"></textarea></div><div class="ui-actions"><button type="button" class="ui-button ui-button--secondary" data-close-dialog>Keep sale</button><button type="button" class="ui-button ui-button--danger" data-close-dialog>Close example</button></div></dialog>
<dialog id="customer-example" class="reference-dialog reference-sheet" aria-labelledby="customer-title"><div class="reference-dialog-heading"><h2 id="customer-title" tabindex="-1">Choose customer</h2><button type="button" class="ui-button ui-button--secondary ui-button--square" data-close-dialog aria-label="Close customer selection">${inline('close')}</button></div><div class="ui-field"><label for="customer-search">Find a customer</label><input id="customer-search" type="search" placeholder="Customer name"></div><button class="reference-customer ui-button ui-button--secondary" type="button" data-close-dialog>${inline('account')} Amina Bello</button><p>Example selection only. Closing returns to the unchanged parent screen.</p></dialog>
</body></html>`;
const componentPage = page.replace(/<section id="states" hidden>[\s\S]*?<\/section>/, '<section id="states" hidden><h2>Shared interaction components</h2><p>Real UI2-B components. Sample data and delayed responses stay on this page; no business API is called.</p><div id="component-examples"></div></section>').replace(/<dialog[\s\S]*?<\/dialog>/g, '');
fs.writeFileSync(at('docs/ui/reference/index.html'), componentPage);
console.log(`UI design reference built: ${sceneData.length} enhanced screens.`);
