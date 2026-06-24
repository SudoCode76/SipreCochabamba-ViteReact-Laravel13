/* eslint-disable react-refresh/only-export-components */
import { useCallback, useMemo, useState } from "react";
import { AlertCircle, CheckCircle2, Info, X } from "lucide-react";

import { Button } from "@/components/ui/button";
import { ToastContext, useToast } from "@/components/ui/toast-context";
import { cn } from "@/lib/utils";

const toastStyles = {
  success: {
    icon: CheckCircle2,
    className: "border-emerald-200 bg-emerald-50 text-emerald-950",
    iconClassName: "text-emerald-600",
  },
  error: {
    icon: AlertCircle,
    className: "border-rose-200 bg-rose-50 text-rose-950",
    iconClassName: "text-rose-600",
  },
  info: {
    icon: Info,
    className: "border-sky-200 bg-sky-50 text-sky-950",
    iconClassName: "text-sky-600",
  },
};

export function ToastProvider({ children }) {
  const [toasts, setToasts] = useState([]);

  const dismiss = useCallback((id) => {
    setToasts((current) => current.filter((toast) => toast.id !== id));
  }, []);

  const notify = useCallback((type, message) => {
    const id = crypto.randomUUID?.() ?? `${Date.now()}-${Math.random()}`;
    const toast = { id, type, message };

    setToasts((current) => [...current, toast].slice(-4));
    window.setTimeout(() => dismiss(id), 3500);

    return id;
  }, [dismiss]);

  const value = useMemo(() => ({
    success: (message) => notify("success", message),
    error: (message) => notify("error", message),
    info: (message) => notify("info", message),
    dismiss,
  }), [dismiss, notify]);

  return (
    <ToastContext.Provider value={value}>
      {children}
      <div className="pointer-events-none fixed right-4 top-4 z-[200] flex w-[calc(100vw-2rem)] max-w-sm flex-col gap-3 sm:right-6 sm:top-6" aria-live="polite" aria-atomic="true">
        {toasts.map((toast) => {
          const styles = toastStyles[toast.type] ?? toastStyles.info;
          const Icon = styles.icon;

          return (
            <div
              key={toast.id}
              role="status"
              className={cn(
                "pointer-events-auto flex items-start gap-3 rounded-2xl border px-4 py-3 text-sm shadow-[0_18px_45px_rgba(15,23,42,0.16)] backdrop-blur-sm",
                styles.className,
              )}
            >
              <Icon className={cn("mt-0.5 size-5 shrink-0", styles.iconClassName)} />
              <p className="min-w-0 flex-1 font-medium leading-5">{toast.message}</p>
              <Button
                type="button"
                variant="ghost"
                size="icon"
                className="size-7 shrink-0 rounded-full text-current/70 hover:bg-black/5 hover:text-current"
                onClick={() => dismiss(toast.id)}
                aria-label="Cerrar notificación"
              >
                <X className="size-4" />
              </Button>
            </div>
          );
        })}
      </div>
    </ToastContext.Provider>
  );
}

export { useToast };
