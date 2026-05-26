const normalize = (value) => String(value || "").trim().toUpperCase();

function roleName(user) {
  return normalize(user?.role?.name);
}

function permissionFunction(permission) {
  return permission?.function || permission?.systemFunction || permission?.system_function || permission;
}

function permissionClass(permission) {
  const systemFunction = permissionFunction(permission);

  return systemFunction?.class
    ?? systemFunction?.clase
    ?? permission?.class
    ?? permission?.clase;
}

function permissionName(permission) {
  const systemFunction = permissionFunction(permission);

  return systemFunction?.name
    ?? systemFunction?.nombre_funcion
    ?? permission?.name
    ?? permission?.nombre_funcion;
}

export function isAdmin(user) {
  return Boolean(user?.is_admin) || roleName(user) === "ADMINISTRADOR";
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

  return (user?.permissions || []).some((permission) => (
    normalize(permissionClass(permission)) === expectedClass
      && expectedFunctions.has(normalize(permissionName(permission)))
  ));
}

export function canAccessNavigationItem(user, item) {
  if (!item?.permission) {
    return true;
  }

  return hasPermission(user, item.permission.className, item.permission.functions);
}
