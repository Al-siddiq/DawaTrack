let activeMutations = 0;
export const activeMutationCount = () => activeMutations;
function mutationState(delta) {
    activeMutations += delta;

}

function securityRedirect(path) {
    window.dispatchEvent(new Event('dt:security-redirect'));
    window.location.assign(path);
}

export class ApiError extends Error {
    constructor({ code, message, status, details = {}, requestId = null }) {
        super(message);
        this.name = 'ApiError';
        this.code = code;
        this.status = status;
        this.details = details;
        this.requestId = requestId;
    }
}

export async function apiRequest(path, options = {}) {
    const controller = new AbortController();
    const timeout = window.setTimeout(() => controller.abort(), options.timeout ?? 10000);
    // Both the caller's cancellation and our deadline must reach the same fetch signal.
    const callerSignal = options.signal;
    const callerAbort = () => controller.abort();
    if (callerSignal?.aborted) controller.abort();
    else callerSignal?.addEventListener('abort', callerAbort, { once: true });
    const csrfHeader = document.querySelector('meta[name="csrf-header"]')?.content;
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    const headers = new Headers(options.headers ?? {});

    headers.set('Accept', 'application/json');
    headers.set('X-Request-ID', crypto.randomUUID());
    if (options.body && !(options.body instanceof FormData)) headers.set('Content-Type', 'application/json');
    if (csrfHeader && csrfToken && !['GET', 'HEAD'].includes(options.method ?? 'GET')) headers.set(csrfHeader, csrfToken);

    const sourceForm = document.activeElement?.closest?.('form') ?? null;
    const mutating = !['GET', 'HEAD'].includes((options.method ?? 'GET').toUpperCase());
    if (mutating) mutationState(1);
    try {
        const response = await fetch(path, { ...options, credentials: 'same-origin', headers, signal: controller.signal });
        const refreshedCsrfToken = csrfHeader ? response.headers.get(csrfHeader) : null;
        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        if (refreshedCsrfToken && csrfMeta) csrfMeta.content = refreshedCsrfToken;
        const payload = await response.json().catch(() => null);
        if (response.status === 401) {
            securityRedirect('/auth?expired=1');
            throw new ApiError({ code: payload?.error?.code ?? 'SESSION_EXPIRED', message: 'Your session has expired. Please sign in again.', status: 401 });
        }
        if (payload?.error?.code === 'MFA_REQUIRED') {
            securityRedirect('/platform-admin/mfa');
            throw new ApiError({ code: 'MFA_REQUIRED', message: payload.error.message, status: 403 });
        }
        // A successful status with an unreadable/incomplete envelope does not prove a mutation failed.
        if (response.ok && (payload?.success !== true || !Object.prototype.hasOwnProperty.call(payload, 'data'))) {
            throw new ApiError({ code: 'RESPONSE_UNCONFIRMED', message: 'The server reply could not be confirmed. Check saved status before trying again.', status: 0 });
        }
        if (!response.ok || !payload?.success) {
            throw new ApiError({ code: payload?.error?.code ?? 'HTTP_ERROR', message: payload?.error?.message ?? 'The request could not be completed.', status: response.status, details: payload?.error?.details, requestId: payload?.meta?.request_id ?? response.headers.get('X-Request-ID') });
        }
        if (mutating && sourceForm) window.dispatchEvent(new CustomEvent('dt:form-saved', { detail: { form: sourceForm } }));
        return payload;
    } catch (error) {
        if (error instanceof ApiError) throw error;
        if (error.name === 'AbortError') throw new ApiError({ code: 'REQUEST_TIMEOUT', message: 'The request took too long. Please try again.', status: 0 });
        throw new ApiError({ code: 'NETWORK_ERROR', message: 'DawaTrack could not reach the server.', status: 0 });
    } finally {
        window.clearTimeout(timeout);
        callerSignal?.removeEventListener('abort', callerAbort);
        if (mutating) mutationState(-1);
    }
}
