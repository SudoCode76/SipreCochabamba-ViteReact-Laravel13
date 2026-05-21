import { useDeferredValue, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Copy, Loader2, MoreHorizontal, Power, Search, X } from "lucide-react";

import { Alert, AlertDescription } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from "@/components/ui/dropdown-menu";
import { Input } from "@/components/ui/input";
import { projectService } from "../services/project.service";

const statusClass = {
  AC: "bg-emerald-600 text-white",
  DC: "bg-rose-600 text-white",
};

export default function ProjectTemplatesPage() {
  const queryClient = useQueryClient();
  const [search, setSearch] = useState("");
  const [feedback, setFeedback] = useState(null);
  const deferredSearch = useDeferredValue(search.trim());

  const { data, isLoading, isError, error, isFetching } = useQuery({
    queryKey: ["project-templates", deferredSearch],
    queryFn: () => projectService.templates({ perPage: 100, search: deferredSearch }),
    placeholderData: (previousData) => previousData,
  });

  const templates = data?.data?.items ?? [];

  const updateMutation = useMutation({
    mutationFn: projectService.updateTemplate,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["project-templates"] });
      setFeedback({ type: "success", message: "La planilla se actualizó correctamente." });
    },
    onError: (mutationError) => {
      setFeedback({
        type: "error",
        message: mutationError?.response?.data?.message || "No se pudo actualizar la planilla.",
      });
    },
  });

  const toggleStatus = (template) => {
    updateMutation.mutate({
      id: template.id_proyecto,
      payload: {
        nombre_proyecto: template.nombre_proyecto,
        fecha: template.fecha,
        ubicacion: template.ubicacion,
        latitud: template.latitud,
        longitud: template.longitud,
        responsable: template.responsable,
        solicitante: template.solicitante,
        observaciones: template.observaciones,
        estado: template.estado === "AC" ? "DC" : "AC",
        aprobado: template.aprobado || "PD",
        fecha_aprob: template.fecha_aprob,
        distrito: template.distrito,
        zona: template.zona,
        otb: template.otb,
      },
    });
  };

  return (
    <div className="flex flex-col gap-6 animate-in fade-in duration-500">
      <Card className="border border-border/70 bg-white/86 shadow-[0_24px_90px_rgba(15,23,42,0.08)]">
        <CardHeader className="gap-4 border-b border-border/70 bg-muted/25">
          <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div className="flex items-center gap-3">
              <div className="flex size-11 items-center justify-center rounded-2xl bg-sky-600 text-white">
                <Copy className="size-5" />
              </div>
              <CardTitle className="text-2xl tracking-[-0.04em]">Planillas de proyecto</CardTitle>
            </div>

            <div className="relative w-full lg:max-w-sm">
              <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
              <Input
                value={search}
                onChange={(event) => setSearch(event.target.value)}
                placeholder="Buscar planilla"
                className="h-11 rounded-2xl border-border/80 bg-background/90 pl-10 pr-10"
              />
              {search && (
                <Button type="button" variant="ghost" size="icon-sm" className="absolute right-1 top-1/2 -translate-y-1/2 rounded-full" onClick={() => setSearch("")}>
                  <X className="size-4" />
                </Button>
              )}
            </div>
          </div>
        </CardHeader>

        <CardContent className="flex flex-col gap-6 p-5 sm:p-6">
          {feedback && (
            <Alert variant={feedback.type === "error" ? "destructive" : "default"} className="rounded-2xl">
              <AlertDescription>{feedback.message}</AlertDescription>
            </Alert>
          )}

          {isFetching && !isLoading && <p className="text-sm text-muted-foreground">Actualizando planillas...</p>}

          {isLoading && (
            <div className="flex items-center justify-center rounded-2xl border border-border/70 bg-background/70 p-8 text-muted-foreground">
              <Loader2 className="mr-2 size-4 animate-spin" /> Cargando planillas...
            </div>
          )}

          {isError && (
            <Alert variant="destructive" className="rounded-2xl">
              <AlertDescription>
                {error?.response?.data?.message || "No se pudieron cargar las planillas de proyecto."}
              </AlertDescription>
            </Alert>
          )}

          {!isLoading && !isError && (
            <div className="overflow-hidden rounded-[28px] border border-border/70 bg-background/90">
              <div className="overflow-x-auto max-h-[560px] overflow-y-auto">
                <table className="min-w-full border-collapse text-sm">
                  <thead>
                    <tr className="border-b border-border/70 bg-muted/30 text-left">
                      <th className="px-5 py-4 font-semibold text-foreground">N°</th>
                      <th className="px-5 py-4 font-semibold text-foreground">Planilla</th>
                      <th className="px-5 py-4 font-semibold text-foreground">Ubicación</th>
                      <th className="px-5 py-4 font-semibold text-foreground">Responsable</th>
                      <th className="px-5 py-4 font-semibold text-foreground">Estado</th>
                      <th className="px-5 py-4 font-semibold text-foreground text-center">Opciones</th>
                    </tr>
                  </thead>
                  <tbody>
                    {templates.map((template, index) => {
                      const status = template.estado || "DC";
                      const statusLabel = status === "AC" ? "ACTIVO" : "INACTIVO";

                      return (
                        <tr key={template.id_proyecto} className={index < templates.length - 1 ? "border-b border-border/60" : ""}>
                          <td className="px-5 py-4 align-top text-foreground">{index + 1}</td>
                          <td className="px-5 py-4 align-top font-medium text-foreground">{template.nombre_proyecto}</td>
                          <td className="px-5 py-4 align-top text-muted-foreground">{template.ubicacion || "-"}</td>
                          <td className="px-5 py-4 align-top text-muted-foreground">{template.nombre_responsable || "-"}</td>
                          <td className="px-5 py-4 align-top">
                            <Badge className={`rounded-full px-3 py-1 text-[11px] uppercase tracking-[0.18em] ${statusClass[status] || "bg-slate-500 text-white"}`}>
                              {statusLabel}
                            </Badge>
                          </td>
                          <td className="px-5 py-4 align-top text-center">
                            <DropdownMenu>
                              <DropdownMenuTrigger asChild>
                                <Button variant="ghost" size="sm" className="h-8 w-8 p-0 rounded-full">
                                  <MoreHorizontal className="h-4 w-4" />
                                </Button>
                              </DropdownMenuTrigger>
                              <DropdownMenuContent align="end" className="w-52 rounded-2xl border border-border/70 bg-background/95 p-1 shadow-lg">
                                <DropdownMenuItem className="flex items-center gap-2 rounded-xl px-3 py-2 cursor-pointer" onClick={() => toggleStatus(template)}>
                                  <Power className="h-4 w-4 text-muted-foreground" />
                                  <span>{status === "AC" ? "Desactivar" : "Activar"}</span>
                                </DropdownMenuItem>
                              </DropdownMenuContent>
                            </DropdownMenu>
                          </td>
                        </tr>
                      );
                    })}
                    {templates.length === 0 && (
                      <tr>
                        <td colSpan={6} className="px-5 py-8 text-center text-muted-foreground">
                          No se encontraron planillas de proyecto.
                        </td>
                      </tr>
                    )}
                  </tbody>
                </table>
              </div>
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
}
