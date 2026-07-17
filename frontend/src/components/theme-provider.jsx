/* eslint-disable react-refresh/only-export-components */
import { createContext, useContext, useEffect, useMemo, useState } from "react";

import { applyTheme, normalizeTheme, THEME_STORAGE_KEY } from "@/lib/theme";

const ThemeContext = createContext(null);

function readStoredTheme() {
  try {
    return normalizeTheme(localStorage.getItem(THEME_STORAGE_KEY));
  } catch {
    return "system";
  }
}

export function ThemeProvider({ children }) {
  const [theme, setThemeState] = useState(readStoredTheme);

  useEffect(() => {
    const media = window.matchMedia("(prefers-color-scheme: dark)");
    const syncTheme = () => applyTheme(theme);

    syncTheme();

    if (theme === "system") {
      media.addEventListener("change", syncTheme);
    }

    return () => media.removeEventListener("change", syncTheme);
  }, [theme]);

  useEffect(() => {
    const handleStorage = (event) => {
      if (event.key === THEME_STORAGE_KEY) {
        setThemeState(normalizeTheme(event.newValue));
      }
    };

    window.addEventListener("storage", handleStorage);
    return () => window.removeEventListener("storage", handleStorage);
  }, []);

  const value = useMemo(() => ({
    theme,
    setTheme(nextTheme) {
      const normalizedTheme = normalizeTheme(nextTheme);

      try {
        localStorage.setItem(THEME_STORAGE_KEY, normalizedTheme);
      } catch {
        // The selected theme still applies for this tab when storage is unavailable.
      }

      setThemeState(normalizedTheme);
    },
  }), [theme]);

  return <ThemeContext.Provider value={value}>{children}</ThemeContext.Provider>;
}

export function useTheme() {
  const context = useContext(ThemeContext);

  if (!context) {
    throw new Error("useTheme debe usarse dentro de ThemeProvider");
  }

  return context;
}
