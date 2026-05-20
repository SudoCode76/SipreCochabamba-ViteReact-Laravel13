import { useState } from "react";
import { useParams, useNavigate } from "react-router-dom";
import { Loader2, ArrowLeft } from "lucide-react";
import { useMutation } from "@tanstack/react-query";

import { Alert, AlertDescription } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import apiClient from "@/lib/api/client";
import { cn } from "@/lib/utils";

export default function PriceRecalculationPage() {
  const { itemId } = useParams();
  const navigate = useNavigate();
  const searchParams = new URLSearchParams(window.location.search);
  const mode = searchParams.get("mode") || "fndr";

  const [formData, setFormData] = useState({ fecha: "" });
  const [status, setStatus] = useState({ type: "", message: "" });

  const mutation = useMutation({
    mutationFn: async (data) => {
      const response = await apiClient.post(`/v1/items/${itemId}/price-recalculation`, {
        ...data,
        mode,
      });
      return response.data;
    },
    onSuccess: () => {
      setStatus({ type: "success", message: "Precio recalculado correctamente." });
      setTimeout(() => {
        navigate(-1);
      }, 2000);
    },
    onError: (error) => {
      const message = error.response?.data?.message || "Error al recalcular el precio.";
      setStatus({ type: "error", message });
    },
  });

  const handleSubmit = (event) => {
    event.preventDefault();
    setStatus({ type: "", message: "" });
    mutation.mutate(formData);
  };

  const modeLabel = {
    fndr: "FNDR",
    upre: "UPRE",
    fps: "FPS",
    obras: "Obras Públicas",
    proman: "Proman",
    general: "General",
  };

  return (
    <div className="flex flex-col gap-6 animate-in fade-in duration-500">
      <div className="flex items-center gap-4">
        <Button variant="ghost" size="sm" onClick={() => navigate(-1)} className="rounded-full">
          <ArrowLeft className="mr-2 h-4 w-4" />
          Volver
        </Button>
      </div>

      <Card className="border border-border/70 bg-white/86 shadow-[0_24px_90px_rgba(15,23,42,0.08)] backdrop-blur max-w-lg mx-auto w-full">
        <CardHeader className="gap-2 border-b border-border/70 bg-muted/25 pb-4">
          <CardTitle className="text-xl tracking-[-0.04em]">Recalcular Precio</CardTitle>
          <CardDescription>
            Item ID: {itemId} | Mode: {modeLabel[mode] || mode}
          </CardDescription>
        </CardHeader>

        <CardContent className="p-6">
          {status.message && (
            <Alert
              variant={status.type === "error" ? "destructive" : "default"}
              className={cn(
                "mb-4 rounded-xl",
                status.type === "success" && "border-emerald-200 bg-emerald-50 text-emerald-900"
              )}
            >
              <AlertDescription>{status.message}</AlertDescription>
            </Alert>
          )}

          <form onSubmit={handleSubmit} className="space-y-4">
            <div className="space-y-2">
              <Label htmlFor="fecha" className="text-sm font-medium">
                Fecha de Referencia
              </Label>
              <Input
                id="fecha"
                type="date"
                className="h-10 rounded-xl border-border/80"
                value={formData.fecha}
                onChange={(e) => setFormData({ ...formData, fecha: e.target.value })}
                required
              />
            </div>

            <div className="flex gap-3 pt-2">
              <Button
                type="submit"
                className="h-10 rounded-full bg-foreground px-6 text-background hover:bg-foreground/90 flex-1"
                disabled={mutation.isPending}
              >
                {mutation.isPending ? (
                  <>
                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                    Procesando
                  </>
                ) : (
                  "Aceptar"
                )}
              </Button>
              <Button
                type="button"
                variant="outline"
                className="h-10 rounded-full flex-1"
                onClick={() => navigate(-1)}
                disabled={mutation.isPending}
              >
                Cancelar
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>
    </div>
  );
}
