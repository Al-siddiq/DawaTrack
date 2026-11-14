import { FinancialMovementsPanel } from '../components/financial-movements-panel.js';
import { OwnerPanel } from '../components/owner-panel.js';
import { permissionLabel } from '../components/management-ui.js';
import { ProductsPanel } from '../components/products-panel.js';
import { IntakesPanel } from '../components/intakes-panel.js';
import { WorkspaceShell } from '../components/ui/workspace-shell.js';
import { WorkspaceHome } from '../components/workspace-home.js';
import { UiLoading, UiInformationPanel } from '../components/ui/index.js';
import { permittedShopPages, readWorkspacePresentation } from '../shared/workspace-registry.js';
import { useWorkspaceNavigation } from '../shared/workspace-navigation.js';
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

export const ShopApp = {
    render,
    components: { FinancialMovementsPanel, OwnerPanel, ProductsPanel, IntakesPanel, WorkspaceShell, WorkspaceHome, UiLoading, UiInformationPanel, UiDialog, UiButton, UiAlert, AuditPanel, OpeningCashPanel, CustomerPanel, SaleDraftPanel, SaleHistoryPanel },

    setup() {
        const presentation = readWorkspacePresentation(document.getElementById('shop-app'));
        const contextReady = ref(false);
        const loading = ref(true);
        const busy = ref(false), ownerLoading = ref(false);
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

        const navigation = computed(() => contextReady.value ? permittedShopPages(context, access.mode) : []);
        const workspaceNavigation = useWorkspaceNavigation({ items: navigation });
        const page = workspaceNavigation.currentId;
        const currentNav = workspaceNavigation.current;
        const bottomNavigation = computed(() => [
            ...[['home', 'home'], ['sale-drafts', 'sell'], ['products', 'stock'], ['customers', 'customers']]
                .filter(([id]) => navigation.value.some(item => item.id === id))
                .map(([id, group]) => { const item = navigation.value.find(item => item.id === id); return { ...item, id: group }; }),
            ...(contextReady.value ? [{ id: 'menu', label: 'Menu', icon: 'menu' }] : []),
        ]);
        const backHash = computed(() => workspaceNavigation.hash.value.startsWith('#sale-draft/') ? '#sale-drafts'
            : workspaceNavigation.hash.value.startsWith('#sale/') ? '#sale-history'
            : currentNav.value?.parentId === 'products' ? '#products' : '');
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
            ['state_region', 'State', 'text', true], ['timezone_name', 'Local time zone', 'text', true],
        ].map(([key, label, type, required]) => ({ key, label, type, required }));
        const brandFields = [
            ['brand_display_name', 'Shop display name'], ['tagline', 'Tagline'], ['public_description', 'Description'],
            ['primary_colour', 'Main brand colour', true], ['secondary_colour', 'Secondary brand colour', true],
            ['accent_colour', 'Small accent colour', true], ['theme_colour', 'Browser bar colour', true],
            ['background_colour', 'Page background colour', true], ['application_title', 'Browser page title'],
            ['pwa_full_name', 'Installed app name'], ['pwa_short_name', 'Short app name'],
        ].map(([key, label, colour]) => ({ key, label, colour }));
        const assetTypes = ['LOGO', 'FAVICON', 'PWA_ICON_192', 'PWA_ICON_512', 'LOGIN_BACKGROUND'];

        function clearFeedback() {
            error.value = '';
            notice.value = '';
            Object.keys(fieldErrors).forEach((key) => delete fieldErrors[key]);
        }

        async function load() {
            loading.value = true;
            contextReady.value = false;
            clearFeedback();
            try {
                const response = await phaseOneApi.context();
                Object.assign(context, response.data);
                Object.assign(access, response.data.access);
                contextReady.value = true;
                workspaceNavigation.initialize();
                await loadPage(page.value);
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
            try { await run(() => phaseOneApi.saveSettings(settings), 'Shop details saved.', loadSettings); } catch (_) {}
        }
        async function saveBranding() {
            try { await run(() => phaseOneApi.saveBranding(branding), 'Shop appearance saved. Reload to see it everywhere.', loadBranding); } catch (_) {}
        }
        async function uploadAsset(selectedFile = null) {
            const form = new FormData();
            form.append('asset_type', assetType.value);
            const image = selectedFile || asset.value?.files?.[0];
            if (!image) { error.value = 'Choose an image first.'; return; }
            form.append('asset', image);
            try { await run(() => phaseOneApi.uploadAsset(form), 'Shop image updated.', loadBranding); } catch (_) {}
        }
        async function requestDomain() {
            try {
                const response = await run(() => phaseOneApi.requestDomain({ hostname: hostname.value }), 'Address setup details are ready. Copy the value below.', loadDomains);
                Object.assign(dnsChallenge, response.data);
            } catch (_) {}
        }
        async function rotateDomain(domain) { try { const response=await run(()=>phaseOneApi.rotateDomain(domain.public_id),'A new address setup value is ready. Copy it below.',loadDomains);Object.assign(dnsChallenge,response.data); } catch (_) {} }
        async function verifyDomain(domain) { try { await run(()=>phaseOneApi.verifyDomain(domain.public_id),'Address ownership checked.',loadDomains); } catch (_) {} }

        async function loadPage(id) {
            if (!contextReady.value || !navigation.value.some(item => item.id === id)) return;
            const ownerPage = ['staff','settings','branding','domains','subscription'].includes(id);
            if (ownerPage) ownerLoading.value = true;
            try {
                if (id === 'staff') await loadStaff();
                else if (id === 'settings') await loadSettings();
                else if (id === 'branding') await loadBranding();
                else if (id === 'domains') await loadDomains();
                else if (id === 'subscription') await loadSubscription();
                else if (id === 'products') await products.load();
                else if (id === 'intakes') await intakes.load();
            } catch (failure) { error.value = failure.message; }
            finally { if (ownerPage) ownerLoading.value = false; }
        }
        watch(page, (id, previous) => { if (id !== previous && !loading.value) { staffForm.password = ''; if (previous === 'domains') delete dnsChallenge.dns_record_value; clearFeedback(); loadPage(id); } });
        let refreshingAccess = false;
        async function refreshAccess() {
            if (!contextReady.value || refreshingAccess || document.hidden) return;
            refreshingAccess = true;
            try {
                const response = await phaseOneApi.context();
                Object.assign(context, response.data);
                Object.assign(access, response.data.access);
                if (!navigation.value.some(item => item.id === page.value)) workspaceNavigation.initialize();
            } catch (failure) { error.value = failure.message; }
            finally { refreshingAccess = false; }
        }
        onMounted(() => { load(); window.addEventListener('focus', refreshAccess); window.addEventListener('visibilitychange', refreshAccess); });
        onUnmounted(() => { window.removeEventListener('focus', refreshAccess); window.removeEventListener('visibilitychange', refreshAccess); });
        const ownerTools = reactive({ staff, settings, branding, assets, domains, subscription, hostname, dnsChallenge, assetType, assetTypes, staffForm, fieldErrors, permissionStates, permissionSearch, busy, ownerLoading, settingFields, brandFields, permissionGroups, createStaff, loadPermissions, openDialog, saveSettings, saveBranding, uploadAsset, requestDomain, rotateDomain, verifyDomain, reload: () => loadPage(page.value) });
        return {
            ownerTools, permissionLabel, presentation, contextReady, workspaceNavigation, currentNav, bottomNavigation, backHash, load,
            page, loading, busy, error, notice, context, access, staff, settings, branding, assets,
            domains, subscription, hostname, dnsChallenge, asset, assetType, navigation, readOnly, owner,
            previewStyle, settingFields, brandFields, assetTypes, staffForm, fieldErrors, permissionStates,
            permissionSearch, permissionGroups, dialog, dialogReason, temporaryCredential, createStaff,
            loadPermissions, openDialog, closeDialog, confirmDialog, saveSettings, saveBranding,
            uploadAsset, requestDomain, rotateDomain, verifyDomain, products, intakes, inventoryActions,
        };
    },
};
if (document.getElementById('shop-app')) createApp(ShopApp).mount('#shop-app');
