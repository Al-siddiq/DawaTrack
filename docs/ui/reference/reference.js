import { UiIcon } from '/public/assets/js/components/ui/icon.js';
import { iconSymbols } from '/public/assets/js/components/ui/icon-catalogue.js';
import './component-examples.js';
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
const { createApp, h } = window.Vue;
createApp({
    setup() {
        return () => h('ul', { class: 'reference-icons' }, Object.keys(iconSymbols).map(name =>
            h('li', { key: name }, [h(UiIcon, { name }), h('span', name)])
        ));
    },
}).mount('#runtime-icon-example');
