import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { FileSignature, Loader2 } from "lucide-react";

import { Alert, AlertDescription } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
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
  const isBusy = updateMutation.isPending || isFetching;
  const groupedReports = [
    { key: "project", title: "Proyecto", items: items.filter((item) => (item.scope || "project") === "project") },
    { key: "item", title: "Ítems", items: items.filter((item) => item.scope === "item") },
  ].filter((group) => group.items.length > 0);

  const handleValidityBlur = (item, event) => {
    const currentValue = Number(item.validity_days ?? 30);
    const nextValue = Number(event.currentTarget.value);

    if (!Number.isInteger(nextValue) || nextValue < 1 || nextValue > 3650) {
      event.currentTarget.value = String(currentValue);
      toast.error("La vigencia debe estar entre 1 y 3650 días.");
      return;
    }

    if (nextValue === currentValue) {
      return;
    }

    updateMutation.mutate({
      reportKey: item.report_key,
      payload: { validity_days: nextValue },
    });
  };

  return (
    <Card className="border border-border/70 bg-white/86 shadow-[0_24px_90px_rgba(15,23,42,0.08)]">
      <CardHeader className="gap-4 border-b border-border/70 bg-muted/25">
        <div className="flex items-center gap-3">
          <div className="flex size-11 items-center justify-center rounded-2xl bg-slate-900 text-white">
            <FileSignature className="size-5" />
          </div>
          <div>
            <CardTitle className="text-2xl tracking-[-0.04em]">Reportes firmables</CardTitle>
            <CardDescription>
              Habilita qué PDFs de proyecto e ítems pueden firmarse y define su vigencia.
            </CardDescription>
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
          <div className="flex flex-col gap-6">
            {groupedReports.map((group) => (
              <div key={group.key} className="overflow-hidden rounded-[28px] border border-border/70 bg-background/90">
                <div className="border-b border-border/70 bg-muted/25 px-5 py-4">
                  <h2 className="text-sm font-semibold uppercase tracking-[0.18em] text-muted-foreground">{group.title}</h2>
                </div>
                <table className="min-w-full divide-y divide-border/70 text-sm">
                  <thead className="bg-muted/40 text-left text-xs uppercase tracking-[0.16em] text-muted-foreground">
                    <tr>
                      <th className="px-5 py-4">Reporte</th>
                      <th className="px-5 py-4">Estado</th>
                      <th className="px-5 py-4">Requisito</th>
                      <th className="px-5 py-4">Vigencia</th>
                      <th className="px-5 py-4 text-right">Acción</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-border/60">
                    {group.items.map((item) => (
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
                        <td className="px-5 py-4">
                          {item.scope === "item" ? (
                            <span className="text-sm text-muted-foreground">No aplica para ítems</span>
                          ) : (
                            <Badge className="bg-blue-600 text-white">SOLO FINALIZADOS</Badge>
                          )}
                        </td>
                        <td className="px-5 py-4">
                          <div className="flex items-center gap-2">
                            <Input
                              type="number"
                              min="1"
                              max="3650"
                              defaultValue={item.validity_days ?? 30}
                              disabled={!canManage || isBusy}
                              className="h-10 w-28 rounded-xl"
                              aria-label={`Vigencia en días para ${item.name}`}
                              onBlur={(event) => handleValidityBlur(item, event)}
                              onKeyDown={(event) => {
                                if (event.key === "Enter") {
                                  event.currentTarget.blur();
                                }
                              }}
                            />
                            <span className="text-sm text-muted-foreground">días</span>
                          </div>
                        </td>
                        <td className="px-5 py-4 text-right">
                          <Button
                            type="button"
                            variant={item.is_enabled ? "outline" : "default"}
                            disabled={!canManage || isBusy}
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
            ))}
          </div>
        )}
      </CardContent>
    </Card>
  );
}
