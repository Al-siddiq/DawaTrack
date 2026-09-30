export function groupedPermissions(catalogue, query = '') {
    const normalizedQuery = query.trim().toLowerCase();
    return catalogue.reduce((groups, permission) => {
        if (normalizedQuery && !`${permission.code} ${permission.label} ${permission.group}`.toLowerCase().includes(normalizedQuery)) return groups;
        const group = groups.find((item) => item.name === permission.group);
        if (group) group.permissions.push(permission);
        else groups.push({ name: permission.group, permissions: [permission] });
        return groups;
    }, []);
}
