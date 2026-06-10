const STORAGE_PREFIX = "items-maintenance-alert-dismissed:";

const storageKey = (user) => {
  const userIdentifier = user?.id ?? user?.username;

  return userIdentifier ? `${STORAGE_PREFIX}${userIdentifier}` : null;
};

export const isItemsMaintenanceAlertDismissed = (user) => {
  const key = storageKey(user);

  if (!key) return false;

  try {
    return sessionStorage.getItem(key) === "true";
  } catch {
    return false;
  }
};

export const dismissItemsMaintenanceAlert = (user) => {
  const key = storageKey(user);

  if (!key) return;

  try {
    sessionStorage.setItem(key, "true");
  } catch {
    // The alert still closes in memory when session storage is unavailable.
  }
};

export const clearItemsMaintenanceAlertDismissal = (user) => {
  const key = storageKey(user);

  if (!key) return;

  try {
    sessionStorage.removeItem(key);
  } catch {
    // Logout must continue even when session storage is unavailable.
  }
};
