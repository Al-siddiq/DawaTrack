import { UiDialog, UiButton, UiAlert } from '../components/ui/index.js';
import { SaleHistoryPanel } from '../components/sale-history-panel.js';
import { phaseOneApi } from '../api/phase-one.js';
import { groupedPermissions } from './permission-groups.js';
import { render } from './render.js';
import { CustomerPanel } from '../components/customer-panel.js';
import { SaleDraftPanel } from '../components/sale-draft-panel.js';
import { OpeningCashPanel } from '../components/opening-cash-panel.js';
import { useReasonDialog } from '../components/reason-dialog.js';
import { AuditPanel } from '../components/audit-panel.js';
import { useProducts } from './composables/use-products.js';
import { useIntakes } from './composables/use-intakes.js';
import { useInventoryActions } from './composables/use-inventory-actions.js';

const {
    createApp,
    ref,
    reactive,
    computed,
    onMounted,
    onUnmounted,
    nextTick,
    watch
} = window.Vue;

createApp({
    render,
    components: { UiDialog, UiButton, UiAlert, AuditPanel, OpeningCashPanel, CustomerPanel, SaleDraftPanel, SaleHistoryPanel },

    setup() {
        const page = ref(location.hash.startsWith('#sale-draft') ? 'sale-drafts' : location.hash.startsWith('#sale/') || location.hash === '#sale-history' ? 'sale-history' : 'home');
        const loading = ref(true);
        const busy = ref(false);
        const error = ref('');
        const notice = ref('');
        const context = reactive({ role: '', permissions: [], capabilities: {}, shop: {} });
        const access = reactive({ mode: 'BLOCKED', message: '' });
        const staff = ref([]);
        const settings = reactive({});
        const branding = reactive({});
        const assets = ref([]);
        const domains = ref([]);
        const subscription = reactive({});
        const hostname = ref('');
        const dnsChallenge = reactive({});
        const asset = ref(null);
        const assetType = ref('LOGO');
        const fieldErrors = reactive({});
        const permissionStates = reactive({});
        const permissionSearch = reactive({});
        const reasonDialog = useReasonDialog({ reactive, ref, nextTick });
        const dialog = reasonDialog.state;
        const dialogReason = reasonDialog.reasonInput;
        const temporaryCredential = ref('');
        const staffForm = reactive({ name: '', email: '', password: '', reason: '' });

        const allNavigation = [
            { id: 'home', label: 'Home', capability: null },
            { id: 'opening-cash', label: 'Opening Cash', capability: 'manage_opening_cash' },
            { id: 'customers', label: 'Customers', capability: 'view_customers' },
            { id: 'sale-drafts', label: 'Record Sale', capability: 'view_sale_drafts' },
            { id: 'sale-history', label: 'Sale history', capability: 'view_sale_drafts' },
            { id: 'products', label: 'Products & stock', capability: 'view_inventory' },
            { id: 'intakes', label: 'Stock coming in', capability: 'view_inventory' },
            { id: 'staff', label: 'Staff', capability: 'manage_staff' },
            { id: 'settings', label: 'Shop settings', capability: 'manage_shop_settings' },
            { id: 'branding', label: 'Branding', capability: 'manage_branding' },
            { id: 'domains', label: 'Domains', capability: 'manage_domains' },
            { id: 'subscription', label: 'Subscription', capability: 'view_subscription' },
            { id: 'audit', label: 'Activity', capability: 'view_audit' },
        ];
        const navigation = computed(() => {
            if (['BLOCKED', 'CLOSED'].includes(access.mode)) {
                return allNavigation.filter((item) => ['home', 'audit'].includes(item.id));
            }
            return allNavigation.filter((item) => !item.capability || context.capabilities[item.capability]);
        });
        const readOnly = computed(() => access.mode !== 'FULL');
        const products = reactive(useProducts({ ref, reactive, computed }, access, context));
        const intakes = reactive(useIntakes({ ref, reactive, computed }, access, context, products));
        const inventoryActions = reactive(useInventoryActions({ ref, reactive, computed, nextTick }, access, context, products, intakes));
        const owner = computed(() => context.role === 'PRIMARY_OWNER');
        const previewStyle = computed(() => ({ '--preview-primary': branding.primary_colour, '--preview-background': branding.background_colour }));

        
        const previewStyleElement =
            document.getElementById(
                'shop-preview-style'
            );

        function safeColour(
            value,
            fallback
        ) {
            return /^#[0-9a-f]{6}$/i.test(
                value ?? ''
            )
                ? value
                : fallback;
        }

        watch(
            [
                () => branding.primary_colour,
                () => branding.background_colour,
            ],
            () => {
                if (!previewStyleElement) {
                    return;
                }

                const primary = safeColour(
                    branding.primary_colour,
                    '#155D46'
                );

                const background = safeColour(
                    branding.background_colour,
                    '#F4F7F5'
                );

                previewStyleElement.textContent =
                    `.brand-preview{`
                    + `--preview-primary:${primary};`
                    + `--preview-background:${background};`
                    + `}`;
            },
            {
                immediate: true,
            }
        );
        
        const settingFields = [
            ['name', 'Shop name', 'text', true], ['contact_phone', 'Phone', 'tel'],
            ['contact_email', 'Email', 'email'], ['address_line_1', 'Address', 'text', true],
            ['address_line_2', 'Address line 2'], ['city_town', 'City/town', 'text', true],
            ['state_region', 'State', 'text', true], ['timezone_name', 'Timezone', 'text', true],
        ].map(([key, label, type, required]) => ({ key, label, type, required }));
        const brandFields = [
            ['brand_display_name', 'Brand name'], ['tagline', 'Tagline'], ['public_description', 'Description'],
            ['primary_colour', 'Primary colour', true], ['secondary_colour', 'Secondary colour', true],
            ['accent_colour', 'Accent colour', true], ['theme_colour', 'Theme colour', true],
            ['background_colour', 'Background colour', true], ['application_title', 'Application title'],
            ['pwa_full_name', 'PWA full name'], ['pwa_short_name', 'PWA short name'],
        ].map(([key, label, colour]) => ({ key, label, colour }));
        const assetTypes = ['LOGO', 'FAVICON', 'PWA_ICON_192', 'PWA_ICON_512', 'LOGIN_BACKGROUND'];

        function clearFeedback() {
            error.value = '';
            notice.value = '';
            Object.keys(fieldErrors).forEach((key) => delete fieldErrors[key]);
        }

        async function load() {
            loading.value = true;
            clearFeedback();
            try {
                const response = await phaseOneApi.context();
                Object.assign(context, response.data);
                Object.assign(access, response.data.access);
                const requests = [];
                if (!['BLOCKED', 'CLOSED'].includes(access.mode)) {
                    if (context.capabilities.manage_staff) requests.push(loadStaff());
                    if (context.capabilities.manage_shop_settings) requests.push(loadSettings());
                    if (context.capabilities.manage_branding) requests.push(loadBranding());
                    if (context.capabilities.manage_domains) requests.push(loadDomains());
                    if (context.capabilities.view_subscription) requests.push(loadSubscription());
                }
                await Promise.all(requests);
                if (context.capabilities.view_inventory) { await products.load(); await intakes.load(); }
                if (!navigation.value.some((item) => item.id === page.value)) page.value = 'home';
            } catch (exception) {
                error.value = exception.message;
            } finally {
                loading.value = false;
            }
        }

        async function loadStaff() { staff.value = (await phaseOneApi.staff()).data; }
        async function loadSettings() { Object.assign(settings, (await phaseOneApi.settings()).data); }
        async function loadBranding() {
            const [profile, currentAssets] = await Promise.all([phaseOneApi.branding(), phaseOneApi.assets()]);
            Object.assign(branding, profile.data);
            assets.value = currentAssets.data;
        }
        async function loadDomains() { domains.value = (await phaseOneApi.domains()).data; }
        async function loadSubscription() { Object.assign(subscription, (await phaseOneApi.subscription()).data); }

        async function run(operation, message, reload = null) {
            busy.value = true;
            clearFeedback();
            try {
                const result = await operation();
                notice.value = message;
                if (reload) await reload();
                return result;
            } catch (exception) {
                error.value = exception.message;
                Object.assign(fieldErrors, exception.details ?? {});
                throw exception;
            } finally {
                busy.value = false;
            }
        }

        async function createStaff() {
            try {
                await run(() => phaseOneApi.createStaff(staffForm), 'Staff account created.', loadStaff);
                Object.assign(staffForm, { name: '', email: '', password: '', reason: '' });
            } catch (_) {
                staffForm.password = '';
            }
        }

        async function loadPermissions(member) {
            const state = permissionStates[member.id] ?? reactive({ loading: true, error: '', catalogue: [], active: [], history: [] });
            permissionStates[member.id] = state;
            state.loading = true;
            state.error = '';
            try {
                Object.assign(state, (await phaseOneApi.permissions(member.id)).data);
            } catch (exception) {
                state.error = exception.message;
            } finally {
                state.loading = false;
            }
        }

        function permissionGroups(member) {
            const state = permissionStates[member.id];
            return groupedPermissions(state?.catalogue ?? [], permissionSearch[member.id] ?? '');
        }

        async function openDialog(type, member, permission = null) {
            temporaryCredential.value = '';
            await reasonDialog.open({ type, member, permission });
        }

        async function closeDialog() {
            temporaryCredential.value = '';
            await reasonDialog.close();
        }

        async function confirmDialog() {
            if (dialog.pending) return;
            const reason = reasonDialog.validate();
            if (reason === null) return;
            const member = dialog.member;
            dialog.pending = true;
            try {
                if (dialog.type === 'status') {
                    const active = member.status !== 'ACTIVE';
                    await run(() => phaseOneApi.staffStatus(member.id, { active, reason }), 'Staff status updated.', loadStaff);
                } else if (dialog.type === 'permission') {
                    const state = permissionStates[member.id];
                    const granted = state.active.includes(dialog.permission.code);
                    const response = await run(
                        () => phaseOneApi.permission(member.id, { permission_code: dialog.permission.code, reason }, !granted),
                        'Permission state updated.',
                    );
                    Object.assign(state, response.data);
                } else if (dialog.type === 'reset') {
                    const response = await run(() => phaseOneApi.resetStaffCredentials(member.id, { reason }), 'Staff credentials reset.');
                    temporaryCredential.value = response.data.temporary_password;
                    return;
                }
                dialog.pending = false;
                closeDialog();
            } catch (exception) {
                dialog.error = exception.message;
            } finally {
                dialog.pending = false;
            }
        }

        async function saveSettings() {
            try { await run(() => phaseOneApi.saveSettings(settings), 'Shop settings saved.', loadSettings); } catch (_) {}
        }
        async function saveBranding() {
            try { await run(() => phaseOneApi.saveBranding(branding), 'Branding saved.', loadBranding); } catch (_) {}
        }
        async function uploadAsset() {
            const form = new FormData();
            form.append('asset_type', assetType.value);
            form.append('asset', asset.value.files[0]);
            try { await run(() => phaseOneApi.uploadAsset(form), 'Brand asset replaced.', loadBranding); } catch (_) {}
        }
        async function requestDomain() {
            try {
                const response = await run(() => phaseOneApi.requestDomain({ hostname: hostname.value }), 'Domain verification requested.', loadDomains);
                Object.assign(dnsChallenge, response.data);
            } catch (_) {}
        }
        async function rotateDomain(domain) { try { const response=await run(()=>phaseOneApi.rotateDomain(domain.public_id),'DNS challenge rotated.',loadDomains);Object.assign(dnsChallenge,response.data); } catch (_) {} }
        async function verifyDomain(domain) { try { await run(()=>phaseOneApi.verifyDomain(domain.public_id),'Domain ownership verified.',loadDomains); } catch (_) {} }

        const navigateHash = () => { if (location.hash.startsWith('#sale-draft')) page.value = 'sale-drafts'; else if (location.hash.startsWith('#sale/') || location.hash === '#sale-history') page.value = 'sale-history'; };
        onMounted(() => { load(); window.addEventListener('hashchange', navigateHash); });
        onUnmounted(() => window.removeEventListener('hashchange', navigateHash));
        return {
            page, loading, busy, error, notice, context, access, staff, settings, branding, assets,
            domains, subscription, hostname, dnsChallenge, asset, assetType, navigation, readOnly, owner,
            previewStyle, settingFields, brandFields, assetTypes, staffForm, fieldErrors, permissionStates,
            permissionSearch, permissionGroups, dialog, dialogReason, temporaryCredential, createStaff,
            loadPermissions, openDialog, closeDialog, confirmDialog, saveSettings, saveBranding,
            uploadAsset, requestDomain, rotateDomain, verifyDomain, products, intakes, inventoryActions,
        };
    },
}).mount('#shop-app');
