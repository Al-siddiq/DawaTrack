import { UiButton, UiLoading, UiAlert, UiEmptyState, UiInformationPanel, UiIcon } from './ui/index.js';
import { apiRequest } from '../api/http-client.js';
import { OperationalDashboardPanel } from './operational-dashboard-panel.js';

const { h, ref, onMounted, onBeforeUnmount, computed } = window.Vue;

export const WorkspaceHome = {
    props: {
        context: { type: Object, required: true },
        access: { type: Object, required: true },
        userName: String,
        items: { type: Array, default: () => [] },
    },
    emits: ['navigate'],
    setup(props, { emit }) {
        const drafts = ref([]);
        const loading = ref(false);
        const error = ref('');
        const baseline = ref(undefined);
        let alive = true;

        const allowed = id => props.items.some(item => item.id === id);
        const canSell = computed(() => props.access.mode === 'FULL'
            && allowed('sale-drafts')
            && props.context.permissions.includes('sale.record'));
        const canViewReports = computed(() => props.context.permissions.includes('report.view'));

        async function load() {
            if (loading.value || ['BLOCKED', 'CLOSED'].includes(props.access.mode)) return;
            loading.value = true;
            error.value = '';
            try {
                const jobs = [];
                if (allowed('sale-drafts')) {
                    jobs.push(apiRequest('/api/v1/shop/sale-drafts?per_page=5&page=1')
                        .then(result => { if (alive) drafts.value = result.data; }));
                }
                if (allowed('opening-cash')) {
                    jobs.push(apiRequest('/api/v1/shop/opening-cash')
                        .then(result => { if (alive) baseline.value = result.data.baseline; }));
                }
                await Promise.all(jobs);
            } catch (failure) {
                if (alive) error.value = failure.message;
            } finally {
                if (alive) loading.value = false;
            }
        }

        onMounted(load);
        onBeforeUnmount(() => { alive = false; });

        const go = hash => emit('navigate', hash);

        function shortcut(title, description, icon, hash) {
            return h('a', {
                href: hash,
                class: ['ui-home-shortcut', { 'ui-home-shortcut--primary': hash === '#sale-drafts/new' }],
                onClick: event => {
                    if (!event.ctrlKey && !event.metaKey && !event.shiftKey && !event.altKey) {
                        event.preventDefault();
                        go(hash);
                    }
                },
            }, [
                h(UiIcon, { name: icon, size: 'navigation' }),
                h('strong', title),
                h('span', description),
            ]);
        }

        function unfinishedSalesSection() {
            if (!allowed('sale-drafts')) return null;
            return h('section', { class: 'ui-home-unfinished-sales', 'aria-labelledby': 'ui-home-unfinished-title' }, [
                h('h2', { id: 'ui-home-unfinished-title' }, 'Continue an unfinished sale'),
                loading.value && h(UiLoading, { label: 'Loading unfinished sales…' }),
                error.value && h(UiAlert, { message: error.value, severity: 'error', dismissible: false }),
                error.value && h(UiButton, { variant: 'secondary', icon: 'refresh', onClick: load }, () => 'Reload saved work'),
                !loading.value && !error.value && !drafts.value.length
                    && h(UiEmptyState, {
                        title: 'No unfinished sales',
                        message: canSell.value ? 'Choose New sale when you are ready.' : 'Saved unfinished sales will appear here.',
                    }),
                ...(!loading.value && !error.value ? drafts.value.map(draft => h('article', {
                    key: draft.public_id,
                    class: 'ui-home-draft',
                }, [
                    h('div', [
                        h('h3', draft.customer_name || 'Walk-in customer'),
                        h('p', `Estimate: ₦${draft.total_amount}`),
                        h('small', `Saved: ${draft.updated_at} UTC`),
                    ]),
                    h(UiButton, {
                        icon: 'next',
                        variant: 'secondary',
                        onClick: () => go(`#sale-draft/${draft.public_id}`),
                    }, () => 'Continue sale'),
                ])) : []),
                !loading.value && !error.value && h(UiButton, {
                    variant: 'secondary',
                    onClick: () => go('#sale-drafts'),
                }, () => 'View all unfinished sales'),
            ]);
        }

        function quickActionsSection() {
            const actions = [
                canSell.value && shortcut('New sale', 'Add products and take payment.', 'sell', '#sale-drafts/new'),
                allowed('products') && shortcut('Find a product', 'Check price and recorded stock.', 'stock', '#products'),
                allowed('customers') && shortcut(
                    'Customers',
                    props.context.permissions.includes('customer.manage') ? 'Find a customer or add someone.' : 'Find a customer.',
                    'customers',
                    '#customers',
                ),
                allowed('sale-history') && shortcut('Sales', 'View saved sales and receipts.', 'sales', '#sale-history'),
            ].filter(Boolean);

            return h('section', { class: 'ui-home-quick-actions', 'aria-labelledby': 'ui-home-actions-title' }, [
                h('h2', { id: 'ui-home-actions-title' }, 'Quick actions'),
                h('div', { class: 'ui-home-actions' }, actions),
                allowed('subscription') && props.access.mode === 'READ_ONLY'
                    && shortcut('View plan', 'See your current shop access.', 'plan', '#subscription'),
            ]);
        }

        return () => h('section', { class: 'ui-home' }, [
            // Owner priority: business overview is the first Home content.
            canViewReports.value && h(OperationalDashboardPanel, {
                permissions: props.context.permissions,
                capabilities: props.context.capabilities,
                access: props.access,
                onNavigate: go,
            }),

            // The next section is always the unfinished-sale workspace when available.
            unfinishedSalesSection(),

            // Four primary shortcuts follow the operational work in progress.
            quickActionsSection(),

            h('section', { class: 'ui-home-intro' }, [
                h('h2', `Hello${props.userName ? `, ${props.userName}` : ''}`),
                h('p', ['BLOCKED', 'CLOSED'].includes(props.access.mode)
                    ? 'Check the shop access message above for the next step.'
                    : 'What would you like to do?'),
            ]),

            allowed('opening-cash') && canSell.value && baseline.value === null
                && h(UiInformationPanel, {
                    title: 'Record your starting cash',
                    message: 'Do this once before recording cash sales. This is the existing starting cash record, not a daily reset.',
                }, () => [
                    h('p', 'Record your starting cash once before recording cash sales.'),
                    h(UiButton, { variant: 'secondary', icon: 'cash', onClick: () => go('#opening-cash') }, () => 'Set starting cash'),
                ]),
        ]);

    },
};
