export const THEME_STORAGE_KEY = "sipre:theme";
export const THEME_OPTIONS = ["system", "light", "dark"];

export function normalizeTheme(theme) {
  return THEME_OPTIONS.includes(theme) ? theme : "system";
}

export function resolveTheme(theme, prefersDark = false) {
  const normalizedTheme = normalizeTheme(theme);

  return normalizedTheme === "system"
    ? (prefersDark ? "dark" : "light")
    : normalizedTheme;
}

export function applyTheme(theme) {
  const prefersDark = window.matchMedia("(prefers-color-scheme: dark)").matches;
  const resolvedTheme = resolveTheme(theme, prefersDark);
  const root = document.documentElement;

  root.classList.toggle("dark", resolvedTheme === "dark");
  root.style.colorScheme = resolvedTheme;

  return resolvedTheme;
}
