/* eslint-disable react-refresh/only-export-components */
import { useCallback, useMemo, useState } from "react";
import { AlertCircle, CheckCircle2, Info, X } from "lucide-react";

import { Button } from "@/components/ui/button";
import { ToastContext, useToast } from "@/components/ui/toast-context";
import { cn } from "@/lib/utils";

const toastStyles = {
  success: {
    icon: CheckCircle2,
    className: "border-success/30 bg-success/10 text-success",
    iconClassName: "text-success",
  },
  error: {
    icon: AlertCircle,
    className: "border-destructive/30 bg-destructive/10 text-destructive",
    iconClassName: "text-destructive",
  },
  info: {
    icon: Info,
    className: "border-info/30 bg-info/10 text-info",
    iconClassName: "text-info",
  },
};

export function ToastProvider({ children }) {
  const [toasts, setToasts] = useState([]);

  const dismiss = useCallback((id) => {
    setToasts((current) => current.filter((toast) => toast.id !== id));
  }, []);

  const notify = useCallback((type, message, options = {}) => {
    const id = crypto.randomUUID?.() ?? `${Date.now()}-${Math.random()}`;
    const toast = { id, type, message, ...options };

    setToasts((current) => [...current, toast].slice(-4));
    window.setTimeout(() => dismiss(id), options.duration ?? 3500);

    return id;
  }, [dismiss]);

  const value = useMemo(() => ({
    success: (message, options) => notify("success", message, options),
    error: (message, options) => notify("error", message, options),
    info: (message, options) => notify("info", message, options),
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
              role={toast.onClick ? "button" : "status"}
              tabIndex={toast.onClick ? 0 : undefined}
              onClick={() => {
                if (!toast.onClick) return;
                toast.onClick();
                dismiss(toast.id);
              }}
              onKeyDown={(event) => {
                if (event.target !== event.currentTarget || !toast.onClick || !["Enter", " "].includes(event.key)) return;
                event.preventDefault();
                toast.onClick();
                dismiss(toast.id);
              }}
              className={cn(
                "pointer-events-auto flex items-start gap-3 rounded-2xl border px-4 py-3 text-sm shadow-[0_18px_45px_rgba(15,23,42,0.16)] backdrop-blur-sm",
                toast.onClick && "cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring",
                styles.className,
              )}
            >
              <Icon className={cn("mt-0.5 size-5 shrink-0", styles.iconClassName)} />
              <p className="min-w-0 flex-1 font-medium leading-5">{toast.message}</p>
              <Button
                type="button"
                variant="ghost"
                size="icon"
                className="size-7 shrink-0 rounded-full text-current/70 hover:bg-current/10 hover:text-current"
                onClick={(event) => {
                  event.stopPropagation();
                  dismiss(toast.id);
                }}
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
