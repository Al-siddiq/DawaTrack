/** Staff reason state/validation adapter. UiDialog owns focus, background and Escape. */
export function useReasonDialog({ reactive, ref }) {
    const state = reactive({ open: false, type: '', member: null, permission: null, reason: '', error: '', pending: false });
    const reasonInput = ref(null);
    async function open(values) {
        if (state.pending) return;
        Object.assign(state, { open: true, type: values.type, member: values.member ?? null, permission: values.permission ?? null, reason: values.reason ?? '', error: '', pending: false });
    }
    function close() {
        if (state.pending) return;
        Object.assign(state, { open: false, type: '', member: null, permission: null, reason: '', error: '', pending: false });
    }
    function validate() {
        const reason = state.reason.trim();
        if (!reason) {
            state.error = 'Enter a reason before continuing.';
        } else if (reason.length > 500) {
            state.error = 'Use 500 characters or fewer.';
        } else {
            state.error = '';
        }
        if (state.error) { reasonInput.value?.focus(); return null; }
        return reason;
    }
    return { state, reasonInput, open, close, validate };
}
