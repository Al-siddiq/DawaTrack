import { UiIcon, UiButton, UiAlert, UiSheet, UiDialog, UiNavigation } from './index.js';
import { useNavigationGuard } from '../../shared/workspace-navigation.js';
const { h, ref, watch, nextTick, onMounted, onBeforeUnmount } = window.Vue;

export const WorkspaceShell = {
    props: { title: { type: String, required: true }, parent: { type: String, default: '' }, backHash: { type: String, default: '' }, brandName: { type: String, default: 'DawaTrack' }, logo: { type: String, default: '' }, userName: { type: String, default: '' }, roleLabel: { type: String, default: '' }, area: { type: String, default: 'Shop' }, items: { type: Array, default: () => [] }, bottomItems: { type: Array, default: () => [] }, currentId: String, currentGroup: String, navigation: { type: Object, required: true }, trackEdits: Boolean },
    setup(props, { slots }) {
        const drawer = ref(false), root = ref(null), keyboardOpen = ref(false);
        let observer = null;
        const baselines = new WeakMap(), dirty = new Map(), revisions = new WeakMap(), submissions = new WeakMap();
        const eligible = node => props.trackEdits && node?.matches?.('input,select,textarea') && !node.closest('.inventory-filters,.commercial-filters,.audit-filters,.intake-filters') && !['search','submit','button','hidden'].includes(node.type);
        const value = node => node.type === 'password' ? Boolean(node.value) : ['checkbox', 'radio'].includes(node.type) ? node.checked : node.value;
        function trackFocus(event) { if (eligible(event.target) && !dirty.has(event.target)) baselines.set(event.target, value(event.target)); }
        function trackEdit(event) {
            const node = event.target;
            if (!eligible(node)) return;
            revisions.set(node, (revisions.get(node) || 0) + 1);
            if (!baselines.has(node)) baselines.set(node, node.type === 'password' ? false : node.defaultValue ?? '');
            if (node.type === 'password' ? !node.value : value(node) === baselines.get(node)) dirty.delete(node); else dirty.set(node, true);
        }
        function clearDirty() {
            dirty.forEach((_, node) => baselines.set(node, value(node)));
            dirty.clear();
        }
        useNavigationGuard(() => {
            dirty.forEach((_, node) => { if (!node.isConnected || value(node) === baselines.get(node)) dirty.delete(node); });
            return dirty.size ? { message: 'Some changes are not saved. Keep editing, or leave without saving these changes.' } : null;
        });
        function captureSubmit(event) {
            const form = event.target.closest?.('form');
            if (form) submissions.set(form, [...form.elements].map(node => [node, revisions.get(node) || 0]));
        }
        function savedForm(event) {
            const snapshot = submissions.get(event.detail?.form);
            if (!snapshot) return;
            snapshot.forEach(([node, revision]) => {
                if ((revisions.get(node) || 0) === revision) { dirty.delete(node); baselines.set(node, value(node)); }
            });
            submissions.delete(event.detail.form);
        }
        const closeOnNavigate = () => { drawer.value = false; clearDirty(); };
        function viewportChanged() {
            const active = document.activeElement;
            keyboardOpen.value = !!window.visualViewport && window.innerHeight - window.visualViewport.height > 150 && active?.matches('input,textarea,select');
            if (active?.matches('input,textarea,select')) window.requestAnimationFrame(() => active.scrollIntoView({ block: 'nearest' }));
        }
        function measure() {
            const style = document.getElementById('workspace-layout-style');
            if (!style || !root.value) return;
            const header = root.value.querySelector('.ui-workspace-header')?.getBoundingClientRect().height || 72;
            const bottom = root.value.querySelector('.ui-workspace-bottom')?.getBoundingClientRect().height || 0;
            style.textContent = `.ui-app-shell{--ui-header-space:${Math.ceil(header)}px;--ui-bottom-space:${Math.ceil(bottom)}px}`;
        }
        onMounted(() => {
            if ('ResizeObserver' in window) {
                observer = new ResizeObserver(measure);
                root.value.querySelectorAll('.ui-workspace-header,.ui-workspace-bottom').forEach(node => observer.observe(node));
            }
            measure();
            window.visualViewport?.addEventListener('resize', viewportChanged);
            window.addEventListener('dt:navigated', closeOnNavigate); window.addEventListener('dt:form-saved', savedForm); });
        onBeforeUnmount(() => { observer?.disconnect(); window.visualViewport?.removeEventListener('resize', viewportChanged); window.removeEventListener('dt:navigated', closeOnNavigate); window.removeEventListener('dt:form-saved', savedForm); });
        watch(() => props.currentId, () => { drawer.value = false; });
        async function navigate(item, event) {
            if (item.id === 'menu') { event?.preventDefault(); drawer.value = true; return; }
            if (event && (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || event.button > 0)) return;
            event?.preventDefault();
            if (drawer.value) { drawer.value = false; await nextTick(); }
            props.navigation.request(item.hash || item.href);
        }
        const brand = () => h('div', { class: 'ui-workspace-brand' }, [props.logo ? h('img', { src: props.logo, alt: '', onError: event => { event.currentTarget.hidden = true; } }) : h('span', { class: 'ui-workspace-mark', 'aria-hidden': 'true' }, props.brandName.slice(0, 1)), h('strong', props.brandName)]);
        const account = () => h('div', { class: 'ui-workspace-account' }, [h('strong', props.userName), h('span', props.roleLabel), h('a', { href: '/logout' }, [h(UiIcon, { name: 'sign-out' }), 'Sign out'])]);
        function sections() {
            const groups = new Map();
            props.items.filter(item => item.sidebar !== false).forEach(item => { const group = item.section || 'Work'; if (!groups.has(group)) groups.set(group, []); groups.get(group).push({ ...item, href: item.hash }); });
            return [...groups].map(([label, items]) => h('section', { class: 'ui-workspace-nav-group', key: label }, [h('h2', label), h(UiNavigation, { items, currentId: props.currentId, currentGroup: props.items.find(item => item.id === props.currentId)?.parentId || '', label: `${label} navigation`, onNavigate: navigate })]));
        }
        return () => h('div', { class: ['ui-app-shell', props.area === 'Admin' && 'ui-app-shell--admin', keyboardOpen.value && 'ui-keyboard-open'], ref: root }, [
            h('aside', { class: 'ui-workspace-sidebar', 'aria-label': `${props.area} workspace` }, [brand(), h('div', { class: 'ui-workspace-links' }, sections()), account()]),
            h('div', { class: 'ui-workspace-main' }, [
                h('header', { class: 'ui-workspace-header' }, [
                    h(UiButton, { variant: 'plain', icon: 'menu', class: 'ui-icon-button ui-workspace-menu-button', 'aria-label': 'Open navigation', 'aria-expanded': String(drawer.value), onClick: () => { drawer.value = true; } }),
                    props.backHash && h(UiButton, { variant: 'plain', icon: 'back', class: 'ui-icon-button', 'aria-label': 'Back to previous list', onClick: () => props.navigation.request(props.backHash) }),
                    h('div', { class: 'ui-workspace-heading' }, [h('span', { class: 'ui-workspace-context' }, props.area === 'Admin' ? 'Admin workspace' : props.brandName), props.parent && h('span', { class: 'ui-workspace-parent' }, `${props.parent} /`), h('h1', { id: 'workspace-heading', tabindex: '-1' }, props.title)]),
                    h('span', { class: 'ui-workspace-area' }, props.area),
                ]),
                h('main', { id: 'workspace-content', tabindex: '-1', class: 'ui-workspace-content', onFocusin: trackFocus, onInput: trackEdit, onChange: trackEdit, onSubmitCapture: captureSubmit, onClickCapture: event => { if (event.target.closest?.('button')) captureSubmit(event); } }, [
                    props.navigation.message.value && h(UiAlert, { severity: 'warning', message: props.navigation.message.value, onDismiss: () => { props.navigation.message.value = ''; } }),
                    slots.default?.(),
                ]),
            ]),
            props.bottomItems.length > 0 && h('div', { class: 'ui-workspace-bottom' }, [h(UiNavigation, { items: props.bottomItems.map(item => ({ ...item, href: item.hash })), variant: 'bottom', currentId: props.currentId, currentGroup: props.currentGroup, label: 'Frequent destinations', onNavigate: navigate })]),
            h(UiSheet, { open: drawer.value, title: 'Menu', placement: 'start', 'onUpdate:open': value => { drawer.value = value; }, onBlocked: () => { drawer.value = false; } }, { default: () => [brand(), ...sections(), account()] }),
            h(UiDialog, { open: !!props.navigation.pending.value, title: 'Leave without saving?', 'onUpdate:open': value => { if (!value) props.navigation.pending.value = null; } }, {
                default: () => h('p', props.navigation.pending.value?.message),
                footer: () => [h(UiButton, { variant: 'secondary', onClick: () => { props.navigation.pending.value = null; } }, () => 'Keep editing'), h(UiButton, { variant: 'danger-secondary', onClick: () => props.navigation.confirmLeave() }, () => 'Leave without saving')],
            }),
        ]);
    },
};
