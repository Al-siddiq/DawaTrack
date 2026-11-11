export const shopPages = Object.freeze([
    { id: 'home', label: 'Home', icon: 'home', hash: '#home', group: 'home' },
    { id: 'sale-drafts', label: 'Sell', icon: 'sell', hash: '#sale-drafts', prefixes: ['#sale-draft/', '#sale-drafts/'], capability: 'view_sale_drafts', group: 'sell' },
    { id: 'products', label: 'Stock', icon: 'stock', hash: '#products', capability: 'view_inventory', group: 'stock', trackEdits: true },
    { id: 'customers', label: 'Customers', icon: 'customers', hash: '#customers', capability: 'view_customers', group: 'customers' },
    { id: 'sale-history', label: 'Sales', icon: 'sales', hash: '#sale-history', prefixes: ['#sale/'], capability: 'view_sale_drafts', group: 'menu' },
    { id: 'intakes', label: 'Stock deliveries', icon: 'add', hash: '#intakes', capability: 'view_inventory', group: 'stock', parent: 'Stock', parentId: 'products', sidebar: false, trackEdits: true },
    { id: 'staff', label: 'Staff', icon: 'staff', hash: '#staff', capability: 'manage_staff', group: 'menu', section: 'Manage shop', trackEdits: true },
    { id: 'settings', label: 'Shop details', icon: 'settings', hash: '#settings', capability: 'manage_shop_settings', group: 'menu', parent: 'Shop settings', section: 'Shop settings', trackEdits: true },
    { id: 'opening-cash', label: 'Starting cash', icon: 'cash', hash: '#opening-cash', capability: 'manage_opening_cash', group: 'menu', parent: 'Shop settings', section: 'Shop settings', trackEdits: true },
    { id: 'branding', label: 'Shop appearance', icon: 'appearance', hash: '#branding', capability: 'manage_branding', group: 'menu', parent: 'Shop settings', section: 'Shop settings', trackEdits: true },
    { id: 'domains', label: 'Web address', icon: 'web-address', hash: '#domains', capability: 'manage_domains', group: 'menu', parent: 'Shop settings', section: 'Shop settings', trackEdits: true },
    { id: 'subscription', label: 'Plan', icon: 'plan', hash: '#subscription', capability: 'view_subscription', group: 'menu', parent: 'Shop settings', section: 'Shop settings' },
    { id: 'audit', label: 'Activity', icon: 'activity', hash: '#audit', capability: 'view_audit', group: 'menu', section: 'Manage shop' },
]);
export function permittedShopPages(context, mode) {
    return shopPages.filter(item => (!item.capability || context.capabilities?.[item.capability])
        && (!['BLOCKED', 'CLOSED'].includes(mode) || ['home', 'audit'].includes(item.id)));
}
export const adminPages = Object.freeze([
    { id: 'shops', label: 'Shops', icon: 'shop', hash: '#shops', permission: 'platform.access', trackEdits: true },
    { id: 'onboard', label: 'Add shop', icon: 'add', hash: '#onboard', permission: 'platform.onboard', trackEdits: true },
    { id: 'plans', label: 'Plans', icon: 'plan', hash: '#plans', permission: 'platform.subscriptions', trackEdits: true },
    { id: 'domains', label: 'Web addresses', icon: 'web-address', hash: '#domains', permission: 'platform.domains', trackEdits: true },
    { id: 'activity', label: 'Activity', icon: 'activity', hash: '#activity', permission: 'platform.audit' },
]);
export function readWorkspacePresentation(element) {
    let permissions = [];
    try { permissions = JSON.parse(element?.dataset.permissions || '[]'); } catch { /* No grant is the safe fallback. */ }
    return { brandName: element?.dataset.brandName || 'DawaTrack', logo: element?.dataset.brandLogo || '', userName: element?.dataset.userName || 'Signed-in user', permissions: Array.isArray(permissions) ? permissions : [] };
}
