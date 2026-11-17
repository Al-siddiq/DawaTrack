import { UiButton } from './primitives.js';
const { h, ref, watch, nextTick, onMounted, onBeforeUnmount, useId } = window.Vue;
let activeOverlay = null;

/** One native modal at a time. The browser supplies the inert background; Tab stays within the dialog. */
export const UiDialog = {
    inheritAttrs: false,
    props: {
        open: Boolean, title: { type: String, required: true }, description: { type: String, default: '' },
        busy: Boolean, closable: { type: Boolean, default: true }, beforeClose: Function,
        initialFocus: { type: String, default: '' }, variant: { type: String, default: 'dialog' }, placement: { type: String, default: 'bottom' },
    },
    emits: ['update:open', 'request-close', 'blocked', 'close-error'],
    setup(props, { slots, attrs, emit, expose }) {
        const element = ref(null), heading = ref(null);
        const id = `dialog-${useId()}`;
        const owner = Symbol('overlay');
        let trigger = null, alive = true, guardPending = false, mounted = false;
        function release() {
            if (activeOverlay !== owner) return;
            activeOverlay = null; document.body.classList.remove('ui-overlay-open');
            const target = trigger; trigger = null;
            nextTick(() => {
                if (activeOverlay) return;
                const usable = target?.isConnected && target !== document.body && !target.disabled && !target.closest('[inert]');
                const destination = usable ? target : document.querySelector('#main-content, main[tabindex]');
                destination?.focus();
            });
        }
        async function synchronize() {
            if (!mounted || !alive) return;
            if (!props.open) { element.value?.close(); release(); return; }
            if (!trigger) trigger = document.activeElement;
            await nextTick();
            if (!alive || !props.open || element.value?.open) return;
            if (activeOverlay && activeOverlay !== owner) { trigger = null; emit('blocked', 'Another dialog is already open.'); return; }
            activeOverlay = owner;
            try {
                element.value.showModal();
                document.body.classList.add('ui-overlay-open');
                const target = props.initialFocus ? element.value.querySelector(props.initialFocus) : null;
                (target ?? heading.value)?.focus();
            } catch (error) { element.value?.close(); release(); emit('close-error', error); }
        }
        function containTab(event) {
            if (event.key !== 'Tab' || event.altKey || event.ctrlKey || event.metaKey || event.defaultPrevented) return;
            const dialog = element.value;
            if (!dialog?.open) return;
            const targets = [...dialog.querySelectorAll('a[href], button, input, select, textarea, [tabindex]')]
                .filter(node => node.tabIndex >= 0 && !node.matches(':disabled') && !node.closest('[inert]')
                    && node.getClientRects().length && getComputedStyle(node).visibility !== 'hidden');
            const first = targets[0], last = targets.at(-1), current = document.activeElement;
            if (!first || !targets.includes(current) || (event.shiftKey ? current === first : current === last)) {
                event.preventDefault();
                (event.shiftKey ? last : first)?.focus();
                if (!first) heading.value?.focus();
            }
        }
        async function requestClose(reason = 'close') {
            if (!props.closable || props.busy || guardPending) return;
            guardPending = true;
            try {
                if (props.beforeClose && !(await props.beforeClose(reason))) return;
                if (!alive || props.busy) return;
                emit('request-close', reason);
                emit('update:open', false);
            } catch (error) { emit('close-error', error); }
            finally { guardPending = false; }
        }
        watch(() => props.open, synchronize);
        onMounted(() => { mounted = true; synchronize(); });
        onBeforeUnmount(() => { alive = false; element.value?.close(); release(); });
        expose({ focus: () => heading.value?.focus(), requestClose });
        return () => h('dialog', {
            ...attrs, ref: element, class: ['ui-dialog', props.variant === 'sheet' && ['ui-sheet', `ui-sheet--${props.placement}`], attrs.class],
            'aria-labelledby': `${id}-title`, 'aria-describedby': props.description ? `${id}-description` : undefined,
            'aria-modal': 'true', 'aria-busy': props.busy ? 'true' : undefined,
            onKeydown: [attrs.onKeydown, containTab].flat().filter(Boolean),
            onCancel: event => { event.preventDefault(); requestClose('escape'); },
            onClose: async () => {
                if (!alive || !props.open) { release(); return; }
                await requestClose('native');
                await nextTick();
                if (alive && props.open) synchronize();
            },
        }, props.open ? [
            h('header', { class: 'ui-dialog-header' }, [
                h('h2', { id: `${id}-title`, tabindex: '-1', ref: heading }, props.title),
                props.closable && h(UiButton, { icon: 'close', variant: 'plain', class: 'ui-icon-button', disabled: props.busy, 'aria-label': `Close ${props.title}`, onClick: () => requestClose() }),
            ]),
            props.description && h('p', { id: `${id}-description` }, props.description),
            slots.default?.(), slots.footer && h('footer', { class: 'ui-actions' }, slots.footer()),
        ] : []);
    },
};
export const UiSheet = {
    inheritAttrs: false,
    setup(_, { attrs, slots }) { return () => h(UiDialog, { ...attrs, variant: 'sheet' }, slots); },
};
