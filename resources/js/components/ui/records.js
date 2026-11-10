import { UiIcon } from './icon.js';
import { UiButton, UiAlert, UiLoading, UiEmptyState } from './primitives.js';
const { h } = window.Vue;
export const UiNavigation = {
    props: { items: { type: Array, default: () => [] }, currentId: { type: String, default: '' }, currentGroup: { type: String, default: '' }, label: { type: String, default: 'Main navigation' }, variant: { type: String, default: 'sidebar' } },
    emits: ['navigate'],
    setup(props, { emit }) {
        return () => h('nav', { class: ['ui-navigation', `ui-navigation--${props.variant}`], 'aria-label': props.label }, props.items.map(item => {
            const current = item.id === props.currentId, active = current || item.id === props.currentGroup;
            return h(item.href ? 'a' : 'button', {
                key: item.id, href: item.href, type: item.href ? undefined : 'button',
                class: ['ui-navigation-item', active && 'is-active'], 'aria-current': current ? 'page' : active ? 'location' : undefined,
                'aria-expanded': item.expanded === undefined ? undefined : String(item.expanded),
                onClick: event => emit('navigate', item, event),
            }, [item.icon && h(UiIcon, { name: item.icon, size: 'navigation' }), h('span', item.label)]);
        }));
    },
};
export const UiPagination = {
    props: { page: { type: Number, default: 1 }, totalPages: { type: Number, default: 0 }, busy: Boolean },
    emits: ['page-request'],
    setup(props, { emit }) {
        return () => h('nav', { class: 'ui-pagination', 'aria-label': 'Record pages' }, [
            h(UiButton, { variant: 'secondary', icon: 'back', disabled: props.busy || props.page <= 1, onClick: () => emit('page-request', props.page - 1) }, () => 'Previous'),
            h('span', `Page ${props.page} of ${Math.max(1, props.totalPages)}`),
            h(UiButton, { variant: 'secondary', icon: 'next', disabled: props.busy || props.page >= props.totalPages, onClick: () => emit('page-request', props.page + 1) }, () => 'Next'),
        ]);
    },
};
export const UiRecordList = {
    props: { records: { type: Array, default: () => [] }, recordKey: { type: [String, Function], default: 'public_id' }, busy: Boolean, error: { type: String, default: '' }, loadingLabel: { type: String, default: 'Loading records…' }, emptyTitle: { type: String, default: 'No matching records' }, emptyMessage: { type: String, default: '' } },
    setup(props, { slots }) {
        return () => h('div', { class: 'ui-record-list', 'aria-busy': props.busy ? 'true' : undefined }, [
            props.error && h(UiAlert, { severity: 'error', message: props.error, dismissible: false }),
            props.busy && h(UiLoading, { label: props.loadingLabel }),
            !props.busy && !props.error && !props.records.length && h(UiEmptyState, { title: props.emptyTitle, message: props.emptyMessage }),
            ...props.records.map((record, index) => {
                const key = typeof props.recordKey === 'function' ? props.recordKey(record) : record[props.recordKey];
                if (key === undefined || key === null) throw new TypeError('UiRecordList needs a stable record key.');
                return h('div', { key, class: 'ui-record-list-item' }, slots.record?.({ record, index }));
            }),
        ]);
    },
};
export const UiStatusPanel = {
    props: { state: { type: String, default: 'ready' }, message: { type: String, default: '' }, checking: Boolean, canCheck: { type: Boolean, default: true } },
    emits: ['check-status'],
    setup(props, { slots }) {
        return () => {
            if (props.state === 'ready') return null;
            if (props.state === 'loading') return h(UiLoading, { label: props.message || 'Loading…' });
            const messages = { unknown: 'We could not confirm whether this was saved. Keep it open and check its status before recording it again.', offline: 'Connect to continue. This change has not been queued.', readonly: 'You can view saved records. Changes are currently unavailable.', denied: 'You are not allowed to use this task.', error: 'We could not complete this request. Check the details before continuing.', conflict: 'Some details have changed. Review the latest information before saving.' };
            return h('section', { class: 'ui-status-panel' }, [
                h(UiAlert, { message: props.message || messages[props.state] || 'Check the current task status.', severity: props.state === 'error' ? 'error' : 'warning', persistent: true, dismissible: false }),
                props.state === 'unknown'
                    ? h(UiButton, { variant: 'secondary', icon: 'refresh', loading: props.checking, disabled: !props.canCheck, busyLabel: 'Checking saved status…', onClick: () => emit('check-status') }, () => 'Check saved status')
                    : slots.actions?.(),
            ]);
        };
    },
};
