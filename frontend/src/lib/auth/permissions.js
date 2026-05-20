const normalize = (value) => String(value || "").trim().toUpperCase();

export function isAdmin(user) {
  return Boolean(user?.is_admin) || normalize(user?.role?.name) === "ADMINISTRADOR";
}

export function hasPermission(user, className, functionNames = []) {
  if (isAdmin(user)) {
    return true;
  }

  const expectedClass = normalize(className);
  const expectedFunctions = new Set([].concat(functionNames).map(normalize).filter(Boolean));

  if (!expectedClass || expectedFunctions.size === 0) {
    return false;
  }

  return (user?.permissions || []).some((permission) => {
    const systemFunction = permission?.function;

    return normalize(systemFunction?.class) === expectedClass
      && expectedFunctions.has(normalize(systemFunction?.name));
  });
}

export function canAccessNavigationItem(user, item) {
  if (!item?.permission) {
    return true;
  }

  return hasPermission(user, item.permission.className, item.permission.functions);
}
