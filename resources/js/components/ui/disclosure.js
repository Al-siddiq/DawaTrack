import { UiIcon } from './icon.js';
import { UiButton } from './primitives.js';
const { h, ref, computed, nextTick, onMounted, onBeforeUnmount, useId } = window.Vue;
export const UiAccordion = {
    props: { title: { type: String, required: true }, expanded: { type: Boolean, default: undefined }, defaultExpanded: Boolean, level: { type: Number, default: 3 } },
    emits: ['update:expanded'],
    setup(props, { emit, slots }) {
        const internal = ref(props.defaultExpanded), id = `accordion-${useId()}`;
        const open = computed(() => props.expanded ?? internal.value);
        return () => h('section', { class: 'ui-accordion', 'onDt:reveal-field': () => { internal.value = true; emit('update:expanded', true); } }, [
            h(`h${Math.min(6, Math.max(2, props.level))}`, [h('button', { type: 'button', id: `${id}-trigger`, 'aria-expanded': String(open.value), 'aria-controls': id, onClick: () => { internal.value = !open.value; emit('update:expanded', internal.value); } }, [props.title, h(UiIcon, { name: 'expand' })])]),
            h('div', { id, hidden: !open.value, class: 'ui-accordion-content', 'aria-labelledby': `${id}-trigger` }, slots.default?.()),
        ]);
    },
};
export const UiDropdown = {
    props: { label: { type: String, default: 'More actions' }, items: { type: Array, default: () => [] }, open: { type: Boolean, default: undefined } },
    emits: ['update:open', 'select'],
    setup(props, { emit }) {
        const internal = ref(false), root = ref(null), trigger = ref(null), list = ref(null), id = `actions-${useId()}`;
        const open = computed(() => props.open ?? internal.value);
        function change(value, restore = false) {
            internal.value = value; emit('update:open', value);
            if (restore) trigger.value?.focus();
        }
        async function enter(last = false) {
            change(true); await nextTick();
            const targets = list.value?.querySelectorAll('button:not([disabled]),a[href]') ?? [];
            targets[last ? targets.length - 1 : 0]?.focus();
        }
        function keydown(event) {
            if (event.key === 'Escape' && open.value) { event.preventDefault(); event.stopPropagation(); change(false, true); }
            if (!open.value || !['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) return;
            const targets = [...(list.value?.querySelectorAll('button:not([disabled]),a[href]') ?? [])];
            if (!targets.length) return;
            event.preventDefault();
            const index = targets.indexOf(document.activeElement);
            const next = event.key === 'Home' ? 0 : event.key === 'End' ? targets.length - 1 : (index + (event.key === 'ArrowDown' ? 1 : -1) + targets.length) % targets.length;
            targets[next]?.focus();
        }
        const outside = event => { if (open.value && !root.value?.contains(event.target)) change(false); };
        onMounted(() => document.addEventListener('pointerdown', outside));
        onBeforeUnmount(() => document.removeEventListener('pointerdown', outside));
        return () => h('div', { class: 'ui-dropdown', ref: root, onKeydown: keydown, onFocusout: event => { if (event.relatedTarget && !root.value?.contains(event.relatedTarget)) change(false); } }, [
            h('button', { class: 'ui-button ui-button--secondary', type: 'button', ref: trigger, 'aria-expanded': String(open.value), 'aria-controls': id,
                onClick: () => change(!open.value), onKeydown: event => { if (['ArrowDown', 'ArrowUp'].includes(event.key)) { event.preventDefault(); event.stopPropagation(); enter(event.key === 'ArrowUp'); } },
            }, [props.label, h(UiIcon, { name: 'expand' })]),
            h('div', { id, ref: list, hidden: !open.value, class: 'ui-dropdown-list' }, props.items.map(item => h(item.href && !item.disabled ? 'a' : 'button', {
                key: item.id, href: item.disabled ? undefined : item.href, type: item.href && !item.disabled ? undefined : 'button', disabled: item.disabled,
                onClick: event => { if (item.disabled) { event.preventDefault(); return; } emit('select', item, event); change(false, !item.href); },
            }, [item.icon && h(UiIcon, { name: item.icon }), item.label]))),
        ]);
    },
};
