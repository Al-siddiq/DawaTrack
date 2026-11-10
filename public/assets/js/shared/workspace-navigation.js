import { activeMutationCount } from '../api/http-client.js';
const { ref, onMounted, onBeforeUnmount, nextTick, computed } = window.Vue;
const guards = new Set();

/** Register only caller-owned edit/unknown-state checks; no business action is performed here. */
export function useNavigationGuard(check) {
    guards.add(check);
    onBeforeUnmount(() => guards.delete(check));
}
export function announceWorkspaceHash(hash) {
    history.replaceState(null, '', hash);
    window.dispatchEvent(new Event('dt:location'));
}

export function useWorkspaceNavigation({ items, fallback = 'home' }) {
    const currentId = ref(fallback), pending = ref(null), message = ref(''), currentHash = ref(location.hash);
    let replaying = false, alive = true, securityLeaving = false;
    const current = computed(() => items.value.find(item => item.id === currentId.value));
    const resolve = hash => items.value.find(item => item.hash === hash || item.prefixes?.some(prefix => hash.startsWith(prefix)));
    function blockers(targetHash = location.hash) {
        if (activeMutationCount()) return [{ hard: true, message: 'A change is being saved. Wait for the result before leaving this task.' }];
        return [...guards].map(check => {
            try { return check({ hash: targetHash }); }
            catch { return { hard: true, message: 'Wait for this task to finish updating before leaving.' }; }
        }).filter(Boolean);
    }
    async function apply(hash, replace = false, announce = true) {
        const item = resolve(hash);
        if (!item) { message.value = 'This page is not available with your current access.'; return; }
        const samePage = item.id === currentId.value;
        if (location.hash !== hash) history[replace ? 'replaceState' : 'pushState'](null, '', hash);
        currentId.value = item.id; currentHash.value = location.hash; pending.value = null; message.value = '';
        window.dispatchEvent(new Event('dt:navigated'));
        await nextTick();
        if (!alive) return;
        if (announce && samePage) {
            replaying = true; window.dispatchEvent(new Event('hashchange')); replaying = false;
        } else if (!samePage) document.getElementById('workspace-heading')?.focus();
    }
    function request(hash, { replace = false, skipDirty = false } = {}) {
        if (!resolve(hash)) { message.value = 'This page is not available with your current access.'; return; }
        if (hash === location.hash && resolve(hash)?.id === currentId.value) return;
        if (document.querySelector('dialog[open],[role="dialog"][aria-modal="true"]')) { message.value = 'Close the current dialog before changing tasks.'; return; }
        const issues = blockers(hash);
        const hard = issues.find(issue => issue.hard);
        if (hard) { message.value = hard.message; return; }
        if (issues.length && !skipDirty) { pending.value = { hash, replace, message: issues[0].message }; return; }
        return apply(hash, replace);
    }
    async function confirmLeave() {
        const target = pending.value;
        pending.value = null;
        await nextTick();
        if (target) request(target.hash, { replace: target.replace, skipDirty: true });
    }
    function initialize() {
        const item = resolve(location.hash);
        const target = item ?? items.value.find(item => item.id === fallback) ?? items.value[0];
        if (target) {
            if (!item && location.hash) message.value = 'This page is not available with your current access.';
            currentId.value = target.id;
            if (!item) history.replaceState(null, '', target.hash);
            currentHash.value = location.hash;
        }
    }
    function hashChange(event) {
        if (replaying) return;
        event.stopImmediatePropagation();
        const nextHash = location.hash;
        const oldHash = event.oldURL ? new URL(event.oldURL).hash : currentHash.value;
        history.replaceState(null, '', oldHash || current.value?.hash || '#home');
        request(nextHash, { replace: true });
    }
    function locationNotice() {
        const item = resolve(location.hash);
        if (item) { currentId.value = item.id; currentHash.value = location.hash; }
    }
    const securityRedirect = () => { securityLeaving = true; };
    function beforeUnload(event) {
        if (!securityLeaving && blockers().length) { event.preventDefault(); event.returnValue = ''; }
    }
    onMounted(() => {
        window.addEventListener('hashchange', hashChange, true);
        window.addEventListener('dt:location', locationNotice);
        window.addEventListener('beforeunload', beforeUnload);
        window.addEventListener('dt:security-redirect', securityRedirect);
    });
    onBeforeUnmount(() => {
        alive = false;
        window.removeEventListener('hashchange', hashChange, true);
        window.removeEventListener('dt:location', locationNotice);
        window.removeEventListener('beforeunload', beforeUnload);
        window.removeEventListener('dt:security-redirect', securityRedirect);
    });
    return { currentId, current, hash: currentHash, pending, message, request, confirmLeave, initialize };
}
