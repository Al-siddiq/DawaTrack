import { UiIcon } from './icon.js';
const { h, ref, computed, watch, nextTick, onBeforeUnmount, useId } = window.Vue;
const uid = prefix => `${prefix}-${useId()}`;

export const UiButton = {
    inheritAttrs: false,
    props: {
        type: { type: String, default: 'button' }, variant: { type: String, default: 'primary' },
        icon: { type: String, default: '' }, loading: Boolean, disabled: Boolean,
        busyLabel: { type: String, default: 'Please wait…' },
    },
    emits: ['click'],
    setup(props, { attrs, slots, emit }) {
        let activationPending = false;
        function activate(event) {
            if (props.disabled || props.loading || activationPending) { event.preventDefault(); return; }
            activationPending = true;
            emit('click', event);
            // Covers repeated same-tick activation; the operation owner supplies loading.
            nextTick(() => { activationPending = false; });
        }
        return () => h('button', {
            ...attrs, type: props.type, class: ['ui-button', `ui-button--${props.variant}`, attrs.class],
            disabled: props.disabled || props.loading, 'aria-busy': props.loading ? 'true' : undefined,
            onClick: activate,
        }, [
            // Both labels occupy the same grid cell so their widest state reserves space.
            h('span', { class: ['ui-button-content', props.loading && 'is-hidden'], 'aria-hidden': props.loading ? 'true' : undefined }, [
                props.icon && h(UiIcon, { name: props.icon }), slots.default?.(),
            ]),
            h('span', { class: ['ui-button-content', !props.loading && 'is-hidden'], 'aria-hidden': !props.loading ? 'true' : undefined }, [
                h('span', { class: 'ui-spinner', 'aria-hidden': 'true' }),
                h('span', { class: !slots.default ? 'ui-sr-only' : undefined }, props.busyLabel),
            ]),
        ]);
    },
};

export const UiField = {
    props: { id: { type: String, default: '' }, label: { type: String, required: true }, help: { type: String, default: '' }, errors: { type: Array, default: () => [] }, required: Boolean, describedBy: { type: String, default: '' } },
    setup(props, { slots }) {
        const generatedId = uid('field');
        return () => {
            const id = props.id || generatedId;
            const descriptions = [props.describedBy, props.help && `${id}-help`, props.errors.length && `${id}-errors`].filter(Boolean).join(' ');
            return h('div', { class: 'ui-field' }, [
                h('label', { for: id }, [props.label, props.required ? h('span', { class: 'ui-field-required' }, ' (required)') : null]),
                slots.default?.({ controlAttrs: { id, 'aria-describedby': descriptions || undefined, 'aria-invalid': props.errors.length ? 'true' : undefined } }),
                props.help && h('span', { id: `${id}-help`, class: 'ui-help' }, props.help),
                props.errors.length > 0 && h('ul', { id: `${id}-errors`, class: 'ui-error-text' }, props.errors.map((error, index) => h('li', { key: index }, String(error)))),
            ]);
        };
    },
};

export const UiErrorSummary = {
    props: { errors: { type: Array, default: () => [] }, title: { type: String, default: 'Please check these details' }, focusOnChange: { type: Boolean, default: true } },
    setup(props) {
        const element = ref(null);
        const titleId = uid('error-summary');
        watch(() => props.errors, async errors => {
            if (errors.length && props.focusOnChange) { await nextTick(); element.value?.focus(); }
        }, { deep: true, immediate: true });
        function focusField(event, id) {
            const target = document.getElementById(id);
            if (!target) return;
            event.preventDefault(); target.focus(); target.scrollIntoView({ block: 'center' });
        }
        return () => props.errors.length ? h('section', { ref: element, tabindex: '-1', class: 'ui-error-summary', 'aria-labelledby': titleId }, [
            h('h3', { id: titleId }, props.title),
            h('ul', props.errors.map((error, index) => h('li', { key: `${error.id || ''}-${index}` }, error.id
                ? h('a', { href: `#${encodeURIComponent(error.id)}`, onClick: event => focusField(event, error.id) }, error.message)
                : error.message))),
        ]) : null;
    },
};

export const UiAlert = {
    props: { message: { type: String, default: '' }, severity: { type: String, default: 'info' }, dismissible: { type: Boolean, default: true }, persistent: { type: Boolean, default: true }, duration: { type: Number, default: 9000 } },
    emits: ['dismiss'],
    setup(props, { slots, emit }) {
        const hidden = ref(false);
        let timer = null, remaining = 9000, started = 0, hovered = false, focused = false, alive = true;
        const root = ref(null);
        function pause() {
            if (timer === null) return;
            window.clearTimeout(timer); timer = null;
            remaining = Math.max(0, remaining - (performance.now() - started));
        }
        function dismiss(reason = 'close') { pause(); hidden.value = true; emit('dismiss', reason); }
        function resume() {
            pause();
            if (!alive || hidden.value || hovered || focused || props.persistent || props.severity !== 'success') return;
            started = performance.now(); timer = window.setTimeout(() => dismiss('timeout'), remaining);
        }
        watch(() => [props.message, props.persistent, props.severity, props.duration], () => {
            pause(); hidden.value = false; remaining = Math.min(10000, Math.max(8000, props.duration || 9000)); resume();
        }, { immediate: true });
        onBeforeUnmount(() => { alive = false; pause(); });
        const icons = { info: 'info', success: 'success', warning: 'warning', error: 'error' };
        return () => hidden.value ? null : h('div', {
            ref: root, class: ['ui-alert', `ui-alert--${props.severity}`],
            role: props.severity === 'error' ? 'alert' : 'status',
            onPointerenter: () => { hovered = true; pause(); }, onPointerleave: () => { hovered = false; resume(); },
            onFocusin: () => { focused = true; pause(); },
            onFocusout: event => { focused = root.value?.contains(event.relatedTarget) ?? false; resume(); },
        }, [
            h(UiIcon, { name: icons[props.severity] || 'info' }),
            h('div', { class: 'ui-alert-content' }, slots.default?.() ?? props.message),
            props.dismissible && h(UiButton, { variant: 'plain', icon: 'close', class: 'ui-icon-button', 'aria-label': 'Close message', onClick: () => dismiss() }),
        ]);
    },
};

export const UiLoading = {
    props: { label: { type: String, default: 'Loading…' }, variant: { type: String, default: 'spinner' } },
    setup(props) {
        return () => h('div', { class: 'ui-loading', role: 'status', 'aria-busy': 'true' }, [
            h('span', { class: props.variant === 'skeleton' ? 'ui-skeleton' : 'ui-spinner', 'aria-hidden': 'true' }), h('span', props.label),
        ]);
    },
};
export const UiEmptyState = {
    props: { title: { type: String, default: 'No records yet' }, message: { type: String, default: '' }, icon: { type: String, default: 'info' } },
    setup(props, { slots }) {
        return () => h('div', { class: 'ui-empty-state' }, [h(UiIcon, { name: props.icon }), h('h3', props.title), props.message && h('p', props.message), slots.default?.()]);
    },
};
export const UiInformationPanel = {
    props: { title: { type: String, default: '' }, severity: { type: String, default: 'info' }, message: { type: String, default: '' } },
    setup(props, { slots }) {
        return () => h('aside', { class: ['ui-information-panel', `ui-information-panel--${props.severity}`] }, [
            h(UiIcon, { name: props.severity === 'warning' ? 'warning' : props.severity === 'error' ? 'error' : 'info' }),
            h('div', [props.title && h('strong', props.title), slots.default?.() ?? h('p', props.message)]),
        ]);
    },
};

export const UiBadge = {
    props: { label: { type: String, required: true }, severity: { type: String, default: 'neutral' }, icon: { type: String, default: '' } },
    setup(props) {
        return () => h('span', { class: ['ui-badge', `ui-badge--${props.severity}`] }, [props.icon && h(UiIcon, { name: props.icon, size: 'small' }), props.label]);
    },
};
