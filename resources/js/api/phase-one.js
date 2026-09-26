import { apiRequest } from './http-client.js';
const json = (method, body) => ({ method, body: JSON.stringify(body) });
export const phaseOneApi = {
 context:()=>apiRequest('/api/v1/shop/context'), staff:()=>apiRequest('/api/v1/shop/staff'), createStaff:(v)=>apiRequest('/api/v1/shop/staff',json('POST',v)), staffStatus:(id,v)=>apiRequest(`/api/v1/shop/staff/${id}/status`,json('PATCH',v)), permission:(id,v,grant)=>apiRequest(`/api/v1/shop/staff/${id}/permissions`,json(grant?'POST':'DELETE',v)),
 settings:()=>apiRequest('/api/v1/shop/settings'), saveSettings:(v)=>apiRequest('/api/v1/shop/settings',json('PUT',v)), branding:()=>apiRequest('/api/v1/shop/branding'), saveBranding:(v)=>apiRequest('/api/v1/shop/branding',json('PUT',v)), assets:()=>apiRequest('/api/v1/shop/assets'), upload:(v)=>apiRequest('/api/v1/shop/assets',{method:'POST',body:v}), domains:()=>apiRequest('/api/v1/shop/domains'), requestDomain:(v)=>apiRequest('/api/v1/shop/domains',json('POST',v)), subscription:()=>apiRequest('/api/v1/shop/subscription'), onboard:(v)=>apiRequest('/api/v1/platform/tenants',json('POST',v)),
};
