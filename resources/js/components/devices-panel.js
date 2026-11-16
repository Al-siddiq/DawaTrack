import { apiRequest } from '../api/http-client.js';
import { field, action, submit, feedback, message } from './commercial-ui.js';
import { useTradingConfirm } from './management-ui.js';
import { UiLoading, UiInformationPanel } from './ui/index.js';
import { useNavigationGuard } from '../shared/workspace-navigation.js';

const { h, ref, computed, onMounted, onUnmounted } = window.Vue;
const root = '/api/v1/shop/devices';
const uuid = value => typeof value === 'string' && /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/.test(value);

export const DevicesPanel = {
    props: { isOwner: Boolean, readOnly: Boolean, recoveryScope: { type: String, required: true } },
    setup(props) {
        const current = ref(null), rows = ref([]), page = ref(1), pages = ref(0);
        const name = ref(''), reason = ref(''), error = ref(''), notice = ref('');
        const loading = ref(true), pending = ref(false), unknown = ref(false), online = ref(navigator.onLine);
        const marker = ref(null), storageError = ref(false), edits = ref({});
        const confirmation = useTradingConfirm();
        const key = `dt:device-installation:${location.host}:${props.recoveryScope}`;
        const blocked = computed(() => loading.value || pending.value || props.readOnly || !online.value || storageError.value);
        useNavigationGuard(() => unknown.value
            ? { hard: true, message: 'Check this installation’s saved status before leaving.' }
            : (name.value.trim() || reason.value.trim() || Object.values(edits.value).some(Boolean))
                ? { message: 'Your Device name or reason is not saved.' } : null);

        function readMarker() {
            if (!props.recoveryScope) throw Error('The current Shop recovery context is unavailable. Reload the Shop.');
            const raw = localStorage.getItem(key);
            if (raw) {
                const value = JSON.parse(raw);
                if (!uuid(value.public_id) || !uuid(value.device_uuid)) throw Error('This installation reference needs Owner review. Do not clear browser storage to retry.');
                marker.value = { public_id: value.public_id, device_uuid: value.device_uuid };
            }
        }
        function ensureMarker() {
            if (!marker.value) {
                const value = { public_id: crypto.randomUUID(), device_uuid: crypto.randomUUID() };
                localStorage.setItem(key, JSON.stringify(value));
                marker.value = value;
            }
            return marker.value;
        }
        async function load() {
            loading.value = true;
            error.value = '';
            try {
                // Clear previous scope data on failed refresh; this panel never keeps an offline record cache.
                current.value = null;
                rows.value = [];
                const response = await apiRequest(`${root}/current`);
                current.value = response.data.device;
                if (props.isOwner) {
                    const list = await apiRequest(`${root}?page=${page.value}&per_page=20`);
                    rows.value = list.data.devices;
                    pages.value = list.meta.pagination.total_pages;
                }
                unknown.value = false;
            } catch (e) {
                current.value = null;
                rows.value = [];
                error.value = message(e);
            } finally { loading.value = false; }
        }
        async function command(path, body, label) {
            if (blocked.value || unknown.value) return;
            pending.value = true;
            error.value = '';
            notice.value = '';
            try {
                await apiRequest(path, { method: 'POST', body: JSON.stringify(body) });
                notice.value = label;
                name.value = '';
                reason.value = '';
                edits.value = {};
                await load();
            } catch (e) {
                error.value = message(e);
                if (!e.status || e.status >= 500) unknown.value = true;
            } finally { pending.value = false; }
        }
        async function register(event) {
            event.preventDefault();
            if (blocked.value || unknown.value) return;
            try {
                const identity = ensureMarker();
                await command(root, { ...identity, display_name: name.value }, 'This browser is registered.');
            } catch (e) { storageError.value = true; error.value = message(e); }
        }
        async function newInstallation() {
            if (blocked.value || unknown.value || current.value?.status !== 'REVOKED') return;
            if (!await confirmation.ask({ title: 'Enroll a new installation?', message: 'The revoked record will remain in history. This browser will use a new installation reference.', confirm: 'Start new enrollment' })) return;
            try {
                marker.value = null;
                const identity = { public_id: crypto.randomUUID(), device_uuid: crypto.randomUUID() };
                localStorage.setItem(key, JSON.stringify(identity));
                marker.value = identity;
                current.value = null;
                notice.value = 'Enter a recognizable name to register the new installation.';
            } catch (e) { storageError.value = true; error.value = message(e); }
        }
        async function recover() {
            if (blocked.value || unknown.value || !marker.value || !reason.value.trim()) return;
            if (!await confirmation.ask({ title: 'Recover this browser installation?', message: 'The Owner will replace its installation proof. Previous proof stops working. This does not reactivate a revoked device.', confirm: 'Recover installation' })) return;
            await command(`${root}/${marker.value.public_id}/recover`, { device_uuid: marker.value.device_uuid, reason: reason.value }, 'This installation has been recovered.');
        }
        async function revoke(row) {
            if (blocked.value || unknown.value || !reason.value.trim()) return;
            if (!await confirmation.ask({ title: 'Revoke this Device?', message: `${row.display_name} will no longer be trusted as an active installation. Existing business records stay.`, confirm: 'Revoke Device', danger: true })) return;
            await command(`${root}/${row.public_id}/revoke`, { reason: reason.value }, 'Device revoked.');
        }
        const connection = () => {
            online.value = navigator.onLine;
            if (!online.value) { current.value = null; rows.value = []; }
        };
        onMounted(() => {
            try { readMarker(); } catch (e) { storageError.value = true; error.value = message(e); loading.value = false; return; }
            load();
            window.addEventListener('online', connection);
            window.addEventListener('offline', connection);
        });
        onUnmounted(() => {
            window.removeEventListener('online', connection);
            window.removeEventListener('offline', connection);
        });
        return () => h('section', { class: 'commercial-panel', 'aria-labelledby': 'devices-title' }, [
            h('h1', { id: 'devices-title' }, 'Shop devices'),
            h('p', 'An installation identifies this browser in this Shop. Your login and permissions still control what you can do. Device status does not enable offline business work.'),
            ...feedback(error.value, notice.value),
            !online.value && h(UiInformationPanel, { title: 'Connect to check devices', message: 'Device management needs the internet. Previously displayed status has been cleared.' }),
            unknown.value && h(UiInformationPanel, { title: 'Check saved status', message: 'The reply was not confirmed. Refresh before another action. If registration saved without its browser proof, the Owner can use explicit recovery with the same installation reference.' }),
            action('Check saved status', load, loading.value || pending.value || !online.value),
            loading.value ? h(UiLoading, { label: 'Loading devices' }) : h('div', [
                h('h2', 'This browser'),
                current.value ? h('div', { class: 'ui-card' }, [
                    h('p', `${current.value.display_name} — ${current.value.status === 'ACTIVE' ? 'Active' : current.value.status === 'REVOKED' ? 'Revoked' : 'Pending'}`),
                    h('p', `Last checked online (UTC): ${current.value.last_validated_at || 'Not yet checked'}`),
                    props.isOwner && current.value.status === 'REVOKED' && action('Start new enrollment', newInstallation, blocked.value || unknown.value),
                    current.value.status === 'ACTIVE' && action('Check this installation online', () => command(`${root}/current/validate`, {}, 'Current installation checked online.'), blocked.value || unknown.value),
                ]) : h('p', 'No trusted installation proof was found for this browser. Ask the Owner to register it or recover an existing registration.'),
                props.isOwner && !current.value && h('form', { onSubmit: register, class: 'commercial-form' }, [
                    field('device-name', 'Name for this browser', name.value, value => { name.value = value; }, { required: true, maxlength: 100, disabled: blocked.value || unknown.value }),
                    submit('Register this browser', blocked.value || unknown.value, { loading: pending.value }),
                ]),
                props.isOwner && h('div', [
                    field('device-reason', 'Reason for revocation or installation recovery', reason.value, value => { reason.value = value; }, { multiline: true, maxlength: 500, disabled: blocked.value || unknown.value }),
                    !current.value && marker.value && action('Recover this installation', recover, blocked.value || unknown.value || !reason.value.trim()),
                    h('h2', 'Registered shop devices'),
                    !rows.value.length && h('p', 'No devices to show on this page.'),
                    ...rows.value.map(row => h('article', { key: row.public_id, class: 'ui-card' }, [
                        h('h3', row.display_name), h('p', row.status),
                        h('p', `Last seen online (UTC): ${row.last_seen_at || 'Not yet seen'}`),
                        row.revocation_reason && h('p', `Revocation reason: ${row.revocation_reason}`),
                        row.status === 'ACTIVE' && h('form', { class: 'commercial-form', onSubmit: event => {
                            event.preventDefault(); command(`${root}/${row.public_id}/rename`, { display_name: edits.value[row.public_id] || row.display_name }, 'Device name saved.');
                        } }, [
                            field(`device-name-${row.public_id}`, 'Change Device name', edits.value[row.public_id] ?? row.display_name,
                                value => { edits.value = { ...edits.value, [row.public_id]: value }; }, { required: true, maxlength: 100, disabled: blocked.value || unknown.value }),
                            submit('Save Device name', blocked.value || unknown.value, { loading: pending.value }),
                            action('Revoke Device', () => revoke(row), blocked.value || unknown.value || !reason.value.trim()),
                        ]),
                    ])),
                    h('nav', { 'aria-label': 'Device pages' }, [
                        action('Previous page', () => { page.value--; load(); }, blocked.value || page.value <= 1),
                        h('span', ` Page ${page.value} of ${Math.max(1, pages.value)} `),
                        action('Next page', () => { page.value++; load(); }, blocked.value || page.value >= pages.value),
                    ]),
                ]),
            ]),
            confirmation.render(),
        ]);
    },
};
