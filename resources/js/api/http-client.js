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
    const csrfHeader = document.querySelector('meta[name="csrf-header"]')?.content;
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    const headers = new Headers(options.headers ?? {});

    headers.set('Accept', 'application/json');
    headers.set('X-Request-ID', crypto.randomUUID());
    if (options.body && !(options.body instanceof FormData)) headers.set('Content-Type', 'application/json');
    if (csrfHeader && csrfToken && !['GET', 'HEAD'].includes(options.method ?? 'GET')) headers.set(csrfHeader, csrfToken);

    try {
        const response = await fetch(path, { ...options, credentials: 'same-origin', headers, signal: options.signal ?? controller.signal });
        const payload = await response.json().catch(() => null);
        if (response.status === 401) { window.location.assign('/auth?expired=1'); throw new ApiError({ code: 'SESSION_EXPIRED', message: 'Your session has expired. Please sign in again.', status: 401 }); }
        if (!response.ok || !payload?.success) {
            throw new ApiError({ code: payload?.error?.code ?? 'HTTP_ERROR', message: payload?.error?.message ?? 'The request could not be completed.', status: response.status, details: payload?.error?.details, requestId: payload?.meta?.request_id ?? response.headers.get('X-Request-ID') });
        }
        return payload;
    } catch (error) {
        if (error instanceof ApiError) throw error;
        if (error.name === 'AbortError') throw new ApiError({ code: 'REQUEST_TIMEOUT', message: 'The request took too long. Please try again.', status: 0 });
        throw new ApiError({ code: 'NETWORK_ERROR', message: 'DawaTrack could not reach the server.', status: 0 });
    } finally {
        window.clearTimeout(timeout);
    }
}
