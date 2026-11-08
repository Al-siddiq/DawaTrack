import { UiIcon } from '/public/assets/js/components/ui/icon.js';
import { iconSymbols } from '/public/assets/js/components/ui/icon-catalogue.js';

const byId = id => document.getElementById(id);
const sectionButtons = document.querySelectorAll('[data-section]');
sectionButtons.forEach(button => button.addEventListener('click', () => {
    sectionButtons.forEach(item => {
        const active = item === button;
        item.setAttribute('aria-pressed', String(active));
        item.classList.toggle('ui-button--secondary', !active);
        byId(item.dataset.section).hidden = !active;
    });
}));
const scenes = document.querySelectorAll('.design-scene');
function selectScene() { scenes.forEach(scene => { scene.hidden = scene.id !== byId('scene').value; }); }
byId('scene').addEventListener('change', selectScene);
selectScene();

// Deliberate local visual demonstrations. No API calls or business operations.
byId('save-example').addEventListener('click', event => {
    const button = event.currentTarget;
    if (button.disabled) return;
    const original = button.innerHTML;
    button.disabled = true;
    button.setAttribute('aria-busy', 'true');
    button.innerHTML = '<span class="ui-spinner" aria-hidden="true"></span><span>Saving sale…</span>';
    byId('save-status').textContent = 'Showing the loading state. No sale is being saved.';
    window.setTimeout(() => {
        button.disabled = false;
        button.removeAttribute('aria-busy');
        button.innerHTML = original;
        byId('save-status').textContent = 'Example finished. No records were changed.';
    }, 2500);
});

let dialogTrigger = null;
for (const [trigger, target] of [['open-modal', 'confirmation-example'], ['open-sheet', 'customer-example']]) {
    byId(trigger).addEventListener('click', event => {
        dialogTrigger = event.currentTarget;
        const dialog = byId(target);
        dialog.showModal();
        dialog.querySelector('h2').focus();
    });
}
document.querySelectorAll('dialog').forEach(dialog => {
    dialog.querySelectorAll('[data-close-dialog]').forEach(button => button.addEventListener('click', () => dialog.close()));
    dialog.addEventListener('close', () => dialogTrigger?.focus());
});

let successTimer;
let remaining = 9000;
let startedAt;
const success = byId('success-example');
function pause() {
    if (!successTimer) return;
    window.clearTimeout(successTimer);
    successTimer = undefined;
    remaining = Math.max(0, remaining - (performance.now() - startedAt));
}
function resume() {
    if (success.hidden || success.matches(':hover') || success.contains(document.activeElement)) return;
    pause();
    startedAt = performance.now();
    successTimer = window.setTimeout(() => { success.hidden = true; successTimer = undefined; }, remaining);
}
byId('show-success').addEventListener('click', () => { pause(); remaining = 9000; success.hidden = false; resume(); });
byId('close-success').addEventListener('click', () => { pause(); success.hidden = true; byId('show-success').focus(); });
success.addEventListener('pointerenter', pause);
success.addEventListener('pointerleave', resume);
success.addEventListener('focusin', pause);
success.addEventListener('focusout', () => window.setTimeout(resume, 0));

const more = byId('more-actions');
const list = byId('action-list');
function closeActions(restore = false) { list.hidden = true; more.setAttribute('aria-expanded', 'false'); if (restore) more.focus(); }
more.addEventListener('click', () => { list.hidden = !list.hidden; more.setAttribute('aria-expanded', String(!list.hidden)); });
list.addEventListener('keydown', event => { if (event.key === 'Escape') closeActions(true); });
list.querySelectorAll('button').forEach(button => button.addEventListener('click', () => closeActions(true)));
document.addEventListener('click', event => { if (!event.target.closest('.reference-dropdown')) closeActions(); });

// Real UI2-A component integration, with no application context or API calls.
const { createApp, h } = window.Vue;
createApp({
    setup() {
        return () => h('ul', { class: 'reference-icons' }, Object.keys(iconSymbols).map(name =>
            h('li', { key: name }, [h(UiIcon, { name }), h('span', name)])
        ));
    },
}).mount('#runtime-icon-example');
