const VALID_ORDERS = new Set(["legacy", "recent", "oldest"]);

export function loadListOrder(page) {
  try {
    const order = window.localStorage.getItem(`sipre:list-order:${page}`);
    return VALID_ORDERS.has(order) ? order : "legacy";
  } catch {
    return "legacy";
  }
}

export function saveListOrder(page, order) {
  if (!VALID_ORDERS.has(order)) {
    return false;
  }

  try {
    window.localStorage.setItem(`sipre:list-order:${page}`, order);
    return true;
  } catch {
    return false;
  }
}
