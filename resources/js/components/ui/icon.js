import { iconSprite, iconSymbols } from './icon-catalogue.js';

const sizes = new Set(['small', 'action', 'navigation']);

/** Render a trusted local symbol. Visible button/link text normally supplies its name. */
export function renderUiIcon(h, name, { size = 'action', label = '' } = {}) {
    if (!Object.hasOwn(iconSymbols, name)) throw new TypeError(`Unknown UI icon: ${name}`);
    if (!sizes.has(size)) throw new TypeError(`Unknown UI icon size: ${size}`);
    return h('svg', {
        class: ['ui-icon', `ui-icon--${size}`], viewBox: '0 0 16 16',
        fill: 'currentColor', focusable: 'false',
        'aria-hidden': label ? undefined : 'true',
        'aria-label': label || undefined,
        role: label ? 'img' : undefined,
    }, [h('use', { href: `${iconSprite}#${iconSymbols[name]}` })]);
}

export const UiIcon = {
    props: {
        name: { type: String, required: true },
        size: { type: String, default: 'action' },
        label: { type: String, default: '' },
    },
    setup(props) {
        return () => renderUiIcon(window.Vue.h, props.name, { size: props.size, label: props.label });
    },
};
