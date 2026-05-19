export function useRoleFormatter() {
  const formatRole = (role) => {
    const labels = { admin_officer: 'Office Owner', admin: 'Admin' };
    return labels[role] ?? role;
  };
  return { formatRole };
}
