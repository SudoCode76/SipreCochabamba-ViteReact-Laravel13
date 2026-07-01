import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { FileSignature, Loader2 } from "lucide-react";

import { Alert, AlertDescription } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { useToast } from "@/components/ui/toast";
import { projectService } from "../services/project.service";

export default function SignableProjectReportsPage() {
  const queryClient = useQueryClient();
  const toast = useToast();

  const { data, isLoading, isError, error, isFetching } = useQuery({
    queryKey: ["signable-project-reports"],
    queryFn: projectService.signableReports,
    placeholderData: (previousData) => previousData,
  });

  const updateMutation = useMutation({
    mutationFn: projectService.updateSignableReport,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["signable-project-reports"] });
      toast.success("Configuración actualizada correctamente.");
    },
    onError: (err) => {
      toast.error(err.response?.data?.message || "No se pudo actualizar el reporte firmable.");
    },
  });

  const items = data?.data?.items ?? [];
  const canManage = Boolean(data?.data?.permissions?.can_manage);

  return (
    <Card className="border border-border/70 bg-white/86 shadow-[0_24px_90px_rgba(15,23,42,0.08)]">
      <CardHeader className="gap-4 border-b border-border/70 bg-muted/25">
        <div className="flex items-center gap-3">
          <div className="flex size-11 items-center justify-center rounded-2xl bg-slate-900 text-white">
            <FileSignature className="size-5" />
          </div>
          <div>
            <CardTitle className="text-2xl tracking-[-0.04em]">Reportes firmables</CardTitle>
            <CardDescription>Habilita o deshabilita globalmente qué PDFs de proyecto pueden firmarse.</CardDescription>
          </div>
        </div>
      </CardHeader>

      <CardContent className="flex flex-col gap-5 p-5 sm:p-6">
        {isLoading && (
          <div className="flex items-center justify-center rounded-2xl border border-border/70 bg-background/70 p-8 text-muted-foreground">
            <Loader2 className="mr-2 size-4 animate-spin" /> Cargando...
          </div>
        )}

        {isError && (
          <Alert variant="destructive" className="rounded-2xl">
            <AlertDescription>{error?.response?.data?.message || "Error al cargar reportes firmables."}</AlertDescription>
          </Alert>
        )}

        {!isLoading && !isError && (
          <div className="overflow-hidden rounded-[28px] border border-border/70 bg-background/90">
            <table className="min-w-full divide-y divide-border/70 text-sm">
              <thead className="bg-muted/40 text-left text-xs uppercase tracking-[0.16em] text-muted-foreground">
                <tr>
                  <th className="px-5 py-4">Reporte</th>
                  <th className="px-5 py-4">Estado</th>
                  <th className="px-5 py-4 text-right">Acción</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-border/60">
                {items.map((item) => (
                  <tr key={item.report_key}>
                    <td className="px-5 py-4">
                      <p className="font-semibold text-foreground">{item.name}</p>
                      <p className="text-sm text-muted-foreground">{item.description}</p>
                    </td>
                    <td className="px-5 py-4">
                      <Badge className={item.is_enabled ? "bg-emerald-600 text-white" : "bg-slate-500 text-white"}>
                        {item.is_enabled ? "HABILITADO" : "DESHABILITADO"}
                      </Badge>
                    </td>
                    <td className="px-5 py-4 text-right">
                      <Button
                        type="button"
                        variant={item.is_enabled ? "outline" : "default"}
                        disabled={!canManage || updateMutation.isPending || isFetching}
                        onClick={() => updateMutation.mutate({
                          reportKey: item.report_key,
                          payload: { is_enabled: !item.is_enabled },
                        })}
                      >
                        {item.is_enabled ? "Deshabilitar" : "Habilitar"}
                      </Button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </CardContent>
    </Card>
  );
}
