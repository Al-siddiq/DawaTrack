import { apiRequest } from './http-client.js';

const json = (method, body) => ({
    method,
    body: JSON.stringify(body),
});

export const phaseOneApi = {
    context: () => apiRequest('/api/v1/shop/context'),
    staff: () => apiRequest('/api/v1/shop/staff'),
    createStaff: (values) => apiRequest('/api/v1/shop/staff', json('POST', values)),
    staffStatus: (id, values) => apiRequest(`/api/v1/shop/staff/${id}/status`, json('PATCH', values)),
    permissions: (id) => apiRequest(`/api/v1/shop/staff/${id}/permissions`),
    permission: (id, values, grant) => apiRequest(
        `/api/v1/shop/staff/${id}/permissions`,
        json(grant ? 'POST' : 'DELETE', values)
    ),
    resetStaffCredentials: (id, values) => apiRequest(
        `/api/v1/shop/staff/${id}/reset-password`,
        json('POST', values)
    ),
    settings: () => apiRequest('/api/v1/shop/settings'),
    saveSettings: (values) => apiRequest('/api/v1/shop/settings', json('PUT', values)),
    branding: () => apiRequest('/api/v1/shop/branding'),
    saveBranding: (values) => apiRequest('/api/v1/shop/branding', json('PUT', values)),
    assets: () => apiRequest('/api/v1/shop/assets'),
    uploadAsset: (values) => apiRequest('/api/v1/shop/assets', { method: 'POST', body: values }),
    domains: () => apiRequest('/api/v1/shop/domains'),
    requestDomain: (values) => apiRequest('/api/v1/shop/domains', json('POST', values)),
    rotateDomain: (id) => apiRequest(`/api/v1/shop/domains/${id}/rotate`, json('POST', {})),
    verifyDomain: (id) => apiRequest(`/api/v1/shop/domains/${id}/verify`, json('POST', {})),
    platformDomains: () => apiRequest('/api/v1/platform/domains'),
    domainAction: (id, action, values) => apiRequest(
        `/api/v1/platform/domains/${id}/${action}`,
        json('POST', values)
    ),
    platformOperations: () => apiRequest('/api/v1/platform/operations/tenants'),
    subscriptionAction: (id, values) => apiRequest(
        `/api/v1/platform/operations/tenants/${id}/subscriptions`,
        json('POST', values)
    ),
    tenantAction: (id, action, values) => apiRequest(
        `/api/v1/platform/operations/tenants/${id}/${action}`,
        json('POST', values)
    ),
    shopAction: (id, action, values) => apiRequest(
        `/api/v1/platform/operations/shops/${id}/${action}`,
        json('POST', values)
    ),
    subscription: () => apiRequest('/api/v1/shop/subscription'),
    onboard: (values) => apiRequest('/api/v1/platform/tenants', json('POST', values)),
    auditOwner: (query = '') => apiRequest(`/api/v1/shop/audit-events${query}`),
    auditOwnerDetail: (id) => apiRequest(`/api/v1/shop/audit-events/${id}`),
    platformAudit: (query = '') => apiRequest(`/api/v1/platform/audit-events${query}`),
    platformAuditDetail: (id) => apiRequest(`/api/v1/platform/audit-events/${id}`),
    supportAudit: (values) => apiRequest(
        '/api/v1/platform/audit-events/support/query',
        json('POST', values)
    ),
    supportAuditDetail: (id, values) => apiRequest(
        `/api/v1/platform/audit-events/support/events/${id}`,
        json('POST', values)
    ),
};
