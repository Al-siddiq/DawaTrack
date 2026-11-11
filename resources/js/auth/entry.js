import { iconSprite, iconSymbols } from '../components/ui/icon-catalogue.js';

// Progressive enhancement only: native forms, CSRF, Shield and MFA remain authoritative.
const originalButtons = new Map();
document.querySelectorAll('form').forEach(form => {
    let submitting = false;
    form.addEventListener('submit', event => {
        if (submitting) { event.preventDefault(); return; }
        if (!form.noValidate && !form.checkValidity()) return;
        submitting = true;
        form.querySelectorAll('button[type="submit"],button:not([type])').forEach(button => {
            originalButtons.set(button, { content: button.innerHTML, disabled: button.disabled });
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
            const spinner = document.createElement('span');
            spinner.className = 'ui-spinner'; spinner.setAttribute('aria-hidden', 'true');
            button.replaceChildren(spinner, document.createTextNode(form.dataset.busyLabel || 'Please wait…'));
        });
    });
    window.addEventListener('pageshow', () => { submitting = false; });
});
window.addEventListener('pageshow', () => {
    originalButtons.forEach((original, button) => { button.innerHTML = original.content; button.disabled = original.disabled; button.removeAttribute('aria-busy'); });
    originalButtons.clear();
});
document.querySelectorAll('input[type="password"][id]').forEach(input => {
    const wrapper = document.createElement('div');
    wrapper.className = 'ui-password-control';
    input.before(wrapper); wrapper.append(input);
    const toggle = document.createElement('button');
    toggle.type = 'button'; toggle.className = 'ui-button ui-button--secondary ui-icon-button';
    toggle.setAttribute('aria-controls', input.id);
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('viewBox', '0 0 16 16'); svg.setAttribute('class', 'ui-icon'); svg.setAttribute('fill', 'currentColor'); svg.setAttribute('aria-hidden', 'true');
    const use = document.createElementNS('http://www.w3.org/2000/svg', 'use'); svg.append(use); toggle.append(svg);
    function update() {
        const shown = input.type === 'text';
        toggle.setAttribute('aria-label', shown ? 'Hide password' : 'Show password');
        toggle.setAttribute('aria-pressed', String(shown));
        use.setAttribute('href', `${iconSprite}#${iconSymbols[shown ? 'visibility-off' : 'visibility']}`);
    }
    toggle.addEventListener('click', () => { input.type = input.type === 'password' ? 'text' : 'password'; update(); });
    window.addEventListener('pageshow', () => { input.type = 'password'; update(); });
    update(); wrapper.append(toggle);
});
