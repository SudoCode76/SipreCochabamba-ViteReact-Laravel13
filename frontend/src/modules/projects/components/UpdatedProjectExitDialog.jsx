import { AlertTriangle, Loader2 } from "lucide-react";

import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";

export function UpdatedProjectExitDialog({
  open,
  isFinalizing = false,
  onCancel,
  onFinalize,
  onKeepActive,
}) {
  return (
    <Dialog open={open} onOpenChange={(nextOpen) => {
      if (!nextOpen && !isFinalizing) {
        onCancel?.();
      }
    }}>
      <DialogContent className="max-w-xl">
        <DialogHeader>
          <div className="mb-2 flex size-11 items-center justify-center rounded-2xl bg-amber-100 text-amber-700">
            <AlertTriangle className="size-5" />
          </div>
          <DialogTitle>Versión actualizada</DialogTitle>
          <DialogDescription>
            Esta versión seguirá tomando precios, composiciones y porcentajes actuales mientras no se finalice.
          </DialogDescription>
        </DialogHeader>

        <div className="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
          Puedes finalizar el proyecto para congelar esta versión ahora, o mantenerlo activo para seguir trabajando con valores actualizados.
        </div>

        <DialogFooter className="flex-col-reverse sm:flex-row sm:justify-end">
          <Button type="button" variant="outline" className="rounded-full" onClick={onCancel} disabled={isFinalizing}>
            Cancelar
          </Button>
          <Button type="button" variant="outline" className="rounded-full" onClick={onKeepActive} disabled={isFinalizing}>
            Mantener proyecto activo
          </Button>
          <Button type="button" className="rounded-full bg-teal-600 text-white hover:bg-teal-500" onClick={onFinalize} disabled={isFinalizing}>
            {isFinalizing ? (
              <>
                <Loader2 className="mr-2 size-4 animate-spin" />
                Finalizando...
              </>
            ) : (
              "Finalizar proyecto"
            )}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
