/* eslint-disable react-refresh/only-export-components */
import { Toaster, toast } from "sonner";

import { useTheme } from "@/components/theme-provider";
import { ToastContext, useToast } from "@/components/ui/toast-context";

function notify(type, message, options = {}) {
  const { duration = 3500, onClick, ...rest } = options;

  return toast[type](message, {
    ...rest,
    duration,
    ...(onClick ? { action: { label: "Abrir", onClick } } : {}),
  });
}

const toastApi = {
  success: (message, options) => notify("success", message, options),
  error: (message, options) => notify("error", message, options),
  info: (message, options) => notify("info", message, options),
  dismiss: toast.dismiss,
};

export function ToastProvider({ children }) {
  const { theme } = useTheme();

  return (
    <ToastContext.Provider value={toastApi}>
      {children}
      <Toaster
        theme={theme}
        position="top-right"
        closeButton
        visibleToasts={4}
        toastOptions={{ className: "font-sans" }}
      />
    </ToastContext.Provider>
  );
}

export { useToast };
