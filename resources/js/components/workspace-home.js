import { UiButton, UiLoading, UiAlert, UiEmptyState, UiInformationPanel, UiIcon } from './ui/index.js';
import { apiRequest } from '../api/http-client.js';
const { h, ref, onMounted, onBeforeUnmount, computed } = window.Vue;
export const WorkspaceHome = {
    props: { context: { type: Object, required: true }, access: { type: Object, required: true }, userName: String, items: { type: Array, default: () => [] } },
    emits: ['navigate'],
    setup(props, { emit }) {
        const drafts = ref([]), loading = ref(false), error = ref(''), baseline = ref(undefined);
        let alive = true;
        const allowed = id => props.items.some(item => item.id === id);
        const canSell = computed(() => props.access.mode === 'FULL' && allowed('sale-drafts') && props.context.permissions.includes('sale.record'));
        async function load() {
            if (loading.value || ['BLOCKED', 'CLOSED'].includes(props.access.mode)) return;
            loading.value = true; error.value = '';
            try {
                const jobs = [];
                if (allowed('sale-drafts')) jobs.push(apiRequest('/api/v1/shop/sale-drafts?per_page=5&page=1').then(result => { if (alive) drafts.value = result.data; }));
                if (allowed('opening-cash')) jobs.push(apiRequest('/api/v1/shop/opening-cash').then(result => { if (alive) baseline.value = result.data.baseline; }));
                await Promise.all(jobs);
            } catch (failure) { if (alive) error.value = failure.message; }
            finally { if (alive) loading.value = false; }
        }
        onMounted(load);
        onBeforeUnmount(() => { alive = false; });
        const go = hash => emit('navigate', hash);
        return () => h('section', { class: 'ui-home' }, [
            h('h2', `Hello${props.userName ? `, ${props.userName}` : ''}`),
            h('p', ['BLOCKED', 'CLOSED'].includes(props.access.mode) ? 'Check the shop access message above for the next step.' : 'What would you like to do?'),
            allowed('opening-cash') && canSell.value && baseline.value === null && h(UiInformationPanel, { title: 'Record your starting cash', message: 'Do this once before recording cash sales. This is the existing starting cash record, not a daily reset.' }, () => [h('p', 'Record your starting cash once before recording cash sales.'), h(UiButton, { variant: 'secondary', icon: 'cash', onClick: () => go('#opening-cash') }, () => 'Set starting cash')]),
            h('div', { class: 'ui-home-actions' }, [
                canSell.value && shortcut('New sale', 'Add products and take payment.', 'sell', '#sale-drafts/new'),
                allowed('products') && shortcut('Find a product', 'Check price and recorded stock.', 'stock', '#products'),
                allowed('customers') && shortcut('Customers', props.context.permissions.includes('customer.manage') ? 'Find a customer or add someone.' : 'Find a customer.', 'customers', '#customers'),
                allowed('sale-history') && shortcut('Sales', 'View saved sales and receipts.', 'sales', '#sale-history'),
                allowed('subscription') && props.access.mode === 'READ_ONLY' && shortcut('View plan', 'See your current shop access.', 'plan', '#subscription'),
            ].filter(Boolean)),
            error.value && h(UiAlert, { message: error.value, severity: 'error', dismissible: false }),
            loading.value && h(UiLoading, { label: 'Loading saved work…' }),
            error.value && h(UiButton, { variant: 'secondary', icon: 'refresh', onClick: load }, () => 'Reload saved work'),
            !loading.value && !error.value && allowed('sale-drafts') && h('section', [
                h('h2', 'Continue an unfinished sale'),
                !drafts.value.length && h(UiEmptyState, { title: 'No unfinished sales', message: canSell.value ? 'Choose New sale when you are ready.' : 'Saved unfinished sales will appear here.' }),
                ...drafts.value.map(draft => h('article', { key: draft.public_id, class: 'ui-home-draft' }, [
                    h('div', [h('h3', draft.customer_name || 'Walk-in customer'), h('p', `Estimate: ₦${draft.total_amount}`), h('small', `Saved: ${draft.updated_at} UTC`)]),
                    h(UiButton, { icon: 'next', variant: 'secondary', onClick: () => go(`#sale-draft/${draft.public_id}`) }, () => 'Continue sale'),
                ])),
                h(UiButton, { variant: 'secondary', onClick: () => go('#sale-drafts') }, () => 'View all unfinished sales'),
            ]),
        ]);
        function shortcut(title, description, icon, hash) {
            return h('a', { href: hash, class: ['ui-home-shortcut', { 'ui-home-shortcut--primary': hash === '#sale-drafts/new' }], onClick: event => { if (!event.ctrlKey && !event.metaKey && !event.shiftKey && !event.altKey) { event.preventDefault(); go(hash); } } }, [h(UiIcon, { name: icon, size: 'navigation' }), h('strong', title), h('span', description)]);
        }
    },
};
