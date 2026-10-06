const FOCUSABLE = [
    'button:not([disabled])',
    '[href]',
    'input:not([disabled])',
    'select:not([disabled])',
    'textarea:not([disabled])',
    '[tabindex]:not([tabindex="-1"])',
].join(',');

export function useReasonDialog({ reactive, ref, nextTick }) {
    const state = reactive({ open: false, type: '', member: null, permission: null, reason: '', error: '', pending: false });
    const reasonInput = ref(null);
    const dialogElement = ref(null);
    let trigger = null;

    async function open(values) {
        trigger = document.activeElement;
        Object.assign(state, { open: true, type: values.type, member: values.member ?? null, permission: values.permission ?? null, reason: values.reason ?? '', error: '', pending: false });
        await nextTick();
        reasonInput.value?.focus();
    }

    async function close() {
        if (state.pending) return;
        Object.assign(state, { open: false, type: '', member: null, permission: null, reason: '', error: '', pending: false });
        await nextTick();
        trigger?.focus();
        trigger = null;
    }

    function validate() {
        const reason = state.reason.trim();
        if (!reason) state.error = 'Enter a reason before continuing.';
        else if (reason.length > 500) state.error = 'Use 500 characters or fewer.';
        else state.error = '';
        if (state.error) {
            reasonInput.value?.focus();
            return null;
        }
        return reason;
    }

    function handleKeydown(event) {
        if (event.key === 'Escape') {
            event.preventDefault();
            close();
            return;
        }
        if (event.key !== 'Tab') return;
        const focusable = [...(dialogElement.value?.querySelectorAll(FOCUSABLE) ?? [])];
        if (!focusable.length) return;
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    }

    return { state, reasonInput, dialogElement, open, close, validate, handleKeydown };
}
