import * as React from "react";
import { Loader2, Search, X } from "lucide-react";

import { Input } from "@/components/ui/input";
import { cn } from "@/lib/utils";

const ClearableSearchInput = React.forwardRef(({
  className,
  clearButtonClassName,
  containerClassName,
  clearLabel = "Limpiar búsqueda",
  iconClassName,
  isLoading = false,
  loadingIndicatorClassName,
  loadingLabel = "Buscando...",
  onClear,
  value,
  ...props
}, ref) => {
  const hasValue = String(value ?? "").length > 0;
  const rightPadding = hasValue && isLoading ? "pr-16" : hasValue || isLoading ? "pr-11" : "pr-4";

  return (
    <div className={cn("relative", containerClassName)}>
      <Search className={cn("pointer-events-none absolute left-4 top-1/2 size-4 -translate-y-1/2 text-muted-foreground", iconClassName)} />
      <Input
        ref={ref}
        value={value}
        className={cn("pl-11", rightPadding, className)}
        {...props}
      />
      {isLoading && (
        <Loader2
          role="status"
          aria-label={loadingLabel}
          className={cn(
            "pointer-events-none absolute top-1/2 size-4 -translate-y-1/2 animate-spin text-muted-foreground",
            hasValue ? "right-11" : "right-4",
            loadingIndicatorClassName,
          )}
        />
      )}
      {hasValue && (
        <button
          type="button"
          aria-label={clearLabel}
          title={clearLabel}
          className={cn(
            "absolute right-3 top-1/2 flex size-7 -translate-y-1/2 items-center justify-center rounded-full text-muted-foreground transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2",
            clearButtonClassName,
          )}
          onClick={onClear}
        >
          <X className="size-4" />
        </button>
      )}
    </div>
  );
});

ClearableSearchInput.displayName = "ClearableSearchInput";

export { ClearableSearchInput };
