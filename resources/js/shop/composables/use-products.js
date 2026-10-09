import { productsApi } from '../../api/products.js';

export function useProducts({ ref, reactive, computed }, access, context) {
    const items = ref([]);
    const history = ref([]);
    const selected = ref(null);
    const loading = ref(false);
    const pending = ref(false);
    const error = ref('');
    const notice = ref('');
    const fieldErrors = reactive({});
    const filters = reactive({ search: '', status: 'ACTIVE', low_stock: '', page: 1, per_page: 20 });
    const pagination = reactive({ page: 1, per_page: 20, total: 0, total_pages: 0 });
    const form = reactive({ name: '', unit_name: '', current_selling_price: '' });
    const threshold = ref('0');
    const deactivateReason = ref('');
    const editing = ref(false);
    const similarProducts = ref([]);
    const canView = computed(() => context.permissions.includes('inventory.view'));
    const canManage = computed(() => context.permissions.includes('product.manage') && access.mode === 'FULL');
    const canAdjust = computed(() => context.permissions.includes('inventory.adjust') && access.mode === 'FULL');

    function clearFeedback() { error.value = ''; notice.value = ''; Object.keys(fieldErrors).forEach((key) => delete fieldErrors[key]); }
    function capture(exception) { error.value = exception.message; Object.assign(fieldErrors, exception.details ?? {}); }
    async function load() { loading.value = true; clearFeedback(); try { const response = await productsApi.list(filters); items.value = response.data; Object.assign(pagination, response.meta.pagination ?? {}); } catch (e) { capture(e); } finally { loading.value = false; } }
    async function checkSimilar() {
        const normalized = form.name.trim().replace(/\s+/g, ' ').toLocaleLowerCase();
        if (!normalized) { similarProducts.value = []; return; }
        try {
            const response = await productsApi.list({ search: form.name, status: 'ALL', page: 1, per_page: 50 });
            similarProducts.value = response.data.filter((item) => item.normalized_name === normalized && item.public_id !== selected.value?.public_id);
        } catch (_) { similarProducts.value = []; }
    }
    async function open(product) { loading.value = true; clearFeedback(); try { const [detail, movements] = await Promise.all([productsApi.detail(product.public_id), productsApi.history(product.public_id)]); selected.value = detail.data; history.value = movements.data; threshold.value = selected.value.low_stock_threshold; editing.value = false; } catch (e) { capture(e); } finally { loading.value = false; } }
    function beginCreate() { selected.value = null; editing.value = true; Object.assign(form, { name: '', unit_name: '', current_selling_price: '' }); clearFeedback(); }
    function beginEdit() { editing.value = true; Object.assign(form, { name: selected.value.name, unit_name: selected.value.unit_name, current_selling_price: selected.value.current_selling_price }); clearFeedback(); }
    async function save() { const wasEditing = Boolean(selected.value); pending.value = true; clearFeedback(); try { const response = selected.value ? await productsApi.update(selected.value.public_id, form) : await productsApi.create(form); selected.value = response.data; editing.value = false; await load(); notice.value = wasEditing ? 'Product saved.' : 'Product created.'; } catch (e) { capture(e); } finally { pending.value = false; } }
    async function updateThreshold() { pending.value = true; clearFeedback(); try { selected.value = (await productsApi.threshold(selected.value.public_id, threshold.value)).data; threshold.value = selected.value.low_stock_threshold; await load(); notice.value = 'Low-stock level saved.'; } catch (e) { capture(e); } finally { pending.value = false; } }
    async function deactivate() { pending.value = true; clearFeedback(); try { await productsApi.deactivate(selected.value.public_id, deactivateReason.value); deactivateReason.value = ''; await open(selected.value); await load(); notice.value = 'Product deactivated. Its stock and records remain available.'; } catch (e) { capture(e); } finally { pending.value = false; } }
    function close() { selected.value = null; editing.value = false; clearFeedback(); }

    return { items, history, selected, loading, pending, error, notice, fieldErrors, similarProducts, filters, pagination, form, threshold, deactivateReason, editing, canView, canManage, canAdjust, checkSimilar, load, open, beginCreate, beginEdit, save, updateThreshold, deactivate, close };
}
