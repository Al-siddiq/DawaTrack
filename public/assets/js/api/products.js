import { apiRequest } from './http-client.js';

const json = (method, body) => ({ method, body: JSON.stringify(body) });
const query = (values) => new URLSearchParams(Object.entries(values).filter(([, value]) => value !== '' && value !== null && value !== undefined)).toString();

export const productsApi = {
    list: (filters) => apiRequest(`/api/v1/shop/products?${query(filters)}`),
    detail: (id) => apiRequest(`/api/v1/shop/products/${id}`),
    history: (id, page = 1) => apiRequest(`/api/v1/shop/products/${id}/stock-history?page=${page}&per_page=20`),
    create: (values) => apiRequest('/api/v1/shop/products', json('POST', values)),
    update: (id, values) => apiRequest(`/api/v1/shop/products/${id}`, json('PUT', values)),
    deactivate: (id, reason) => apiRequest(`/api/v1/shop/products/${id}/deactivate`, json('POST', { reason })),
    threshold: (id, value) => apiRequest(`/api/v1/shop/products/${id}/low-stock-threshold`, json('PUT', { low_stock_threshold: value })),
};
