const { ref, reactive, onBeforeUnmount } = window.Vue;

/** Per-operation guard. Callers retain domain identity and authoritative result handling. */
export function useUiTask({ kind = 'read' } = {}) {
    const pending = ref(false), status = ref('ready'), error = ref(null);
    let inFlight = null;
    function run(work) {
        if (inFlight) return inFlight;
        if (kind === 'write' && status.value === 'unknown') return Promise.reject(new Error('Check saved status before trying this change again.'));
        pending.value = true; error.value = null; status.value = 'loading';
        inFlight = Promise.resolve().then(work).then(result => {
            status.value = 'saved'; return result;
        }).catch(failure => {
            error.value = failure;
            const uncertain = failure.status === 0 || failure.status >= 500 || [403, 404].includes(failure.status) || ['AbortError', 'TimeoutError'].includes(failure.name);
            status.value = kind === 'write' && uncertain ? 'unknown' : 'error';
            throw failure;
        }).finally(() => { pending.value = false; inFlight = null; });
        return inFlight;
    }
    function resolveOutcome(outcome) {
        if (pending.value || !['saved', 'not-saved'].includes(outcome)) throw new Error('Resolve only a confirmed saved or not-saved outcome.');
        status.value = outcome === 'saved' ? 'saved' : 'ready'; error.value = null;
    }
    return { pending, status, error, run, resolveOutcome };
}

/** Read-only list loader: cancels superseded reads and ignores stale results. Never use for writes. */
export function useUiList(fetchPage, { perPage = 20 } = {}) {
    const rows = ref([]), pending = ref(false), error = ref('');
    const pagination = reactive({ page: 1, per_page: perPage, total: 0, total_pages: 0 });
    let generation = 0, controller = null, alive = true, currentQuery = {};
    async function load(page = 1, query = currentQuery) {
        currentQuery = { ...query };
        const request = ++generation;
        controller?.abort(); controller = new AbortController();
        pending.value = true; error.value = '';
        try {
            const result = await fetchPage({ ...currentQuery, page, per_page: perPage }, controller.signal);
            if (!alive || request !== generation) return;
            rows.value = result.rows; Object.assign(pagination, result.pagination);
        } catch (failure) {
            if (alive && request === generation) error.value = failure.message || 'We could not load these records.';
        } finally { if (alive && request === generation) pending.value = false; }
    }
    onBeforeUnmount(() => { alive = false; generation++; controller?.abort(); });
    return { rows, pending, error, pagination, load, setQuery: query => load(1, query) };
}
