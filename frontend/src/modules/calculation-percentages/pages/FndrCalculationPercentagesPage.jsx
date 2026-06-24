import { useMemo, useState } from "react";
import { createPortal } from "react-dom";
import {
  ChevronLeft,
  ChevronRight,
  Loader2,
  Percent,
  Pencil,
  RefreshCcw,
  X,
} from "lucide-react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";

import { Alert, AlertDescription } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { ClearableSearchInput } from "@/components/ui/clearable-search-input";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Separator } from "@/components/ui/separator";
import { useToast } from "@/components/ui/toast";
import { cn } from "@/lib/utils";
import { fndrCalculationPercentagesService } from "@/modules/calculation-percentages/services/fndr-calculation-percentages.service";

const statusClassMap = {
  AC: "bg-emerald-600 text-white",
  DC: "bg-slate-900 text-white",
};

const initialForm = {
  code: "",
  description: "",
  percentage: "",
  observation: "",
  status: "ACTIVO",
};

function buildForm(item) {
  return {
    code: item?.code ?? item?.codigo ?? "",
    description: item?.description ?? item?.descripcion ?? "",
    percentage: item?.percentage ?? item?.porcentaje ?? "",
    observation: item?.observation ?? item?.observacion ?? "",
    status: item?.status_label ?? (item?.status === "DC" ? "INACTIVO" : "ACTIVO"),
  };
}

function buildRowKey(item, index) {
  return [
    item?.id,
    item?.internal_id,
    item?.id_porcentaje,
    item?.display_id,
    item?.code,
    item?.codigo,
    index,
  ]
    .filter((value) => value !== undefined && value !== null && value !== "")
    .join("-");
}

export default function FndrCalculationPercentagesPage() {
  const queryClient = useQueryClient();
  const toast = useToast();

  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(10);
  const [searchTerm, setSearchTerm] = useState("");
  const [statusFilter, setStatusFilter] = useState("");
  const [isFormOpen, setIsFormOpen] = useState(false);
  const [editingId, setEditingId] = useState(null);
  const [isDetailLoading, setIsDetailLoading] = useState(false);
  const [form, setForm] = useState(initialForm);
  const [formErrors, setFormErrors] = useState({});
  const [feedback, setFeedback] = useState(null);

  const contextQuery = useQuery({
    queryKey: ["fndr-calculation-context"],
    queryFn: fndrCalculationPercentagesService.context,
  });

  const listQuery = useQuery({
    queryKey: ["fndr-calculation-percentages", { page, perPage, searchTerm, statusFilter }],
    queryFn: () => fndrCalculationPercentagesService.list({
      page,
      perPage,
      search: searchTerm.trim(),
      status: statusFilter,
    }),
    placeholderData: (previousData) => previousData,
  });

  const items = useMemo(() => listQuery.data?.data?.items ?? [], [listQuery.data]);
  const meta = listQuery.data?.data?.meta ?? { current_page: 1, per_page: perPage, total: 0, from: 0, to: 0, last_page: 1 };
  const statuses = contextQuery.data?.data?.statuses ?? [];

  const totalPages = Math.max(1, meta.last_page || Math.ceil((meta.total || 0) / (meta.per_page || perPage)));
  const visiblePages = useMemo(() => {
    const start = Math.max(1, meta.current_page - 2);
    const end = Math.min(totalPages, start + 4);
    const first = Math.max(1, end - 4);
    return Array.from({ length: end - first + 1 }, (_, index) => first + index);
  }, [meta.current_page, totalPages]);

  const resetForm = () => {
    setForm(initialForm);
    setFormErrors({});
    setEditingId(null);
  };

  const closeForm = () => {
    setIsFormOpen(false);
    resetForm();
  };

  const openEdit = async (id) => {
    setFormErrors({});
    setEditingId(id);
    setIsFormOpen(true);
    setIsDetailLoading(true);

    try {
      const detail = await queryClient.fetchQuery({
        queryKey: ["fndr-calculation-detail", id],
        queryFn: () => fndrCalculationPercentagesService.getById(id),
      });

      const item = detail?.data?.calculation_percentage;
      if (item) {
        setForm(buildForm(item));
      }
    } catch (error) {
      setFeedback({
        type: "error",
        message: error.response?.data?.message || "No se pudo cargar el detalle del porcentaje FNDR.",
      });
      closeForm();
    } finally {
      setIsDetailLoading(false);
    }
  };

  const createMutation = useMutation({
    mutationFn: fndrCalculationPercentagesService.create,
    onSuccess: () => {
      setFeedback({ type: "success", message: "Porcentaje FNDR creado correctamente." });
      toast.success("Porcentaje FNDR creado correctamente.");
      closeForm();
      queryClient.invalidateQueries({ queryKey: ["fndr-calculation-percentages"] });
    },
    onError: (error) => {
      setFormErrors(error.response?.data?.errors ?? {});
      setFeedback({
        type: "error",
        message: error.response?.status === 403
          ? "Acceso denegado para crear porcentajes FNDR."
          : error.response?.data?.message || "No se pudo crear el porcentaje FNDR.",
      });
    },
  });

  const updateMutation = useMutation({
    mutationFn: ({ id, payload }) => fndrCalculationPercentagesService.update(id, payload),
    onSuccess: () => {
      setFeedback({ type: "success", message: "Porcentaje FNDR actualizado correctamente." });
      toast.success("Porcentaje FNDR actualizado correctamente.");
      closeForm();
      queryClient.invalidateQueries({ queryKey: ["fndr-calculation-percentages"] });
      queryClient.invalidateQueries({ queryKey: ["fndr-calculation-detail"] });
    },
    onError: (error) => {
      setFormErrors(error.response?.data?.errors ?? {});
      setFeedback({
        type: "error",
        message: error.response?.status === 403
          ? "Acceso denegado para editar porcentajes FNDR."
          : error.response?.data?.message || "No se pudo actualizar el porcentaje FNDR.",
      });
    },
  });

  const isSubmitting = createMutation.isPending || updateMutation.isPending;
  const isEditing = editingId !== null;

  const handlePerPageChange = (event) => {
    setPerPage(Number(event.target.value));
    setPage(1);
  };

  const handleFormChange = (event) => {
    const { id, value } = event.target;
    setForm((current) => ({ ...current, [id]: value }));
  };

  const handleSubmitForm = (event) => {
    event.preventDefault();
    setFeedback(null);

    const nextErrors = {};
    if (!form.code.trim()) nextErrors.code = ["El código es obligatorio."];
    if (!form.description.trim()) nextErrors.description = ["La descripción es obligatoria."];
    if (form.percentage === "") nextErrors.percentage = ["El porcentaje es obligatorio."];
    if (!form.status) nextErrors.status = ["El estado es obligatorio."];

    setFormErrors(nextErrors);

    if (Object.keys(nextErrors).length > 0) {
      return;
    }

    const payload = {
      code: form.code.trim(),
      description: form.description.trim(),
      percentage: Number(form.percentage),
      observation: form.observation.trim(),
      status: form.status,
    };

    if (isEditing) {
      updateMutation.mutate({ id: editingId, payload });
      return;
    }

    createMutation.mutate(payload);
  };

  return (
    <div className="flex flex-col gap-6 animate-in fade-in duration-500">
      <Card className="border border-border/70 bg-white/86 shadow-[0_24px_90px_rgba(15,23,42,0.08)] backdrop-blur">
        <CardHeader className="gap-4 border-b border-border/70 bg-muted/25">
          <div className="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
            <div className="flex items-center gap-3">
              <div className="flex size-11 items-center justify-center rounded-2xl bg-foreground text-background">
                <Percent className="size-5" />
              </div>
              <div>
                <CardTitle className="text-2xl tracking-[-0.04em]">Porcentaje de Cálculo FNDR</CardTitle>
                <CardDescription>
                  Administración de porcentajes de cálculo FNDR para los procesos del sistema.
                </CardDescription>
              </div>
            </div>

            <div className="flex flex-wrap gap-2">
              <Button
                variant="outline"
                className="rounded-full border-border/70 bg-background/80"
                onClick={() => listQuery.refetch()}
                disabled={listQuery.isFetching}
              >
                <RefreshCcw data-icon="inline-start" className={cn(listQuery.isFetching && "animate-spin")} />
                Refrescar
              </Button>
            </div>
          </div>
        </CardHeader>

        <CardContent className="flex flex-col gap-6 p-5 sm:p-6">
          {feedback && (
            <Alert
              variant={feedback.type === "error" ? "destructive" : "default"}
              className={cn(
                "rounded-2xl border-border/70",
                feedback.type === "success" && "border-emerald-200 bg-emerald-50 text-emerald-900",
              )}
            >
              <AlertDescription>{feedback.message}</AlertDescription>
            </Alert>
          )}

          <div className="grid gap-4 xl:grid-cols-[minmax(0,1fr)_220px_140px] xl:items-end">
            <div className="flex flex-col gap-2">
              <Label htmlFor="searchTerm" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                Buscador
              </Label>
              <ClearableSearchInput
                id="searchTerm"
                placeholder="Buscar por código o descripción"
                className="h-12 rounded-2xl border-border/80 bg-background/90"
                value={searchTerm}
                onChange={(event) => {
                  setSearchTerm(event.target.value);
                  setPage(1);
                }}
                onClear={() => {
                  setSearchTerm("");
                  setPage(1);
                }}
                isLoading={listQuery.isFetching && !listQuery.isLoading}
              />
            </div>

            <div className="flex flex-col gap-2">
              <Label htmlFor="statusFilter" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                Estado
              </Label>
              <select
                id="statusFilter"
                className="h-12 rounded-2xl border border-border/80 bg-background/90 px-4 text-sm text-foreground outline-none"
                value={statusFilter}
                onChange={(event) => {
                  setStatusFilter(event.target.value);
                  setPage(1);
                }}
              >
                <option value="">Todos</option>
                {statuses.map((status) => (
                  <option key={status.code} value={status.code}>{status.label}</option>
                ))}
              </select>
            </div>

            <div className="flex flex-col gap-2">
              <Label className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                Mostrar
              </Label>
              <select
                className="h-12 rounded-2xl border border-border/80 bg-background/90 px-4 text-sm text-foreground outline-none"
                value={perPage}
                onChange={handlePerPageChange}
              >
                <option value={10}>10</option>
                <option value={25}>25</option>
                <option value={50}>50</option>
              </select>
            </div>

            {listQuery.isFetching && !listQuery.isLoading && (
              <div className="xl:col-span-3 flex items-center gap-2 rounded-2xl border border-border/70 bg-background/80 px-4 py-3 text-sm text-muted-foreground">
                <Loader2 className="size-4 animate-spin" />
                Buscando porcentajes FNDR...
              </div>
            )}
          </div>

          {listQuery.isLoading && (
            <div className="flex items-center justify-center rounded-2xl border border-border/70 bg-background/70 p-8 text-muted-foreground">
              <Loader2 className="mr-2 size-4 animate-spin" /> Cargando porcentajes FNDR...
            </div>
          )}

          {listQuery.isError && (
            <Alert variant="destructive" className="rounded-2xl">
              <AlertDescription>
                {listQuery.error?.response?.status === 403
                  ? "No tienes permisos para acceder a Porcentaje de Cálculo FNDR."
                  : listQuery.error?.response?.data?.message || "No se pudieron cargar los porcentajes FNDR."}
              </AlertDescription>
            </Alert>
          )}

          {!listQuery.isLoading && !listQuery.isError && (
            <>
              <div className="hidden overflow-hidden rounded-[28px] border border-border/70 bg-background/90 lg:block">
                <div className="overflow-x-auto">
                  <table className={cn("min-w-full border-collapse text-sm transition-opacity", listQuery.isFetching && "opacity-60")}>
                    <thead>
                      <tr className="border-b border-border/70 bg-muted/30 text-left">
                        <th className="px-5 py-4 font-semibold text-foreground">N°</th>
                        <th className="px-5 py-4 font-semibold text-foreground">ID</th>
                        <th className="px-5 py-4 font-semibold text-foreground">Descripción</th>
                        <th className="px-5 py-4 font-semibold text-foreground">% de Cálculo</th>
                        <th className="px-5 py-4 font-semibold text-foreground">Estado</th>
                        <th className="px-5 py-4 font-semibold text-foreground text-right">Opciones</th>
                      </tr>
                    </thead>
                    <tbody>
                      {items.map((item, index) => (
                        <tr key={buildRowKey(item, index)} className={index < items.length - 1 ? "border-b border-border/60" : ""}>
                          <td className="px-5 py-4 align-top text-foreground">{(meta.from || 1) + index}</td>
                          <td className="px-5 py-4 align-top text-muted-foreground">{item.display_id || item.code || item.codigo || "-"}</td>
                          <td className="px-5 py-4 align-top text-foreground">{item.description || item.descripcion || "-"}</td>
                          <td className="px-5 py-4 align-top text-muted-foreground">{item.percentage ?? item.porcentaje ?? "-"}</td>
                          <td className="px-5 py-4 align-top">
                            <Badge className={`rounded-full px-3 py-1 text-[11px] uppercase tracking-[0.18em] ${statusClassMap[item.status] || "bg-slate-500 text-white"}`}>
                              {item.status_label}
                            </Badge>
                          </td>
                          <td className="px-5 py-4 align-top text-right">
                            {item.available_actions?.edit ? (
                              <Button variant="outline" className="rounded-full border-border/70" onClick={() => openEdit(item.id || item.internal_id || item.id_porcentaje)}>
                                <Pencil data-icon="inline-start" />
                                Editar
                              </Button>
                            ) : null}
                          </td>
                        </tr>
                      ))}
                      {items.length === 0 && (
                        <tr>
                          <td colSpan={6} className="px-5 py-8 text-center text-muted-foreground">
                            No se encontraron porcentajes FNDR para los filtros seleccionados.
                          </td>
                        </tr>
                      )}
                    </tbody>
                  </table>
                </div>
              </div>

              <div className={cn("grid gap-4 transition-opacity lg:hidden", listQuery.isFetching && "opacity-60")}>
                {items.length === 0 ? (
                  <Card className="border border-border/70 bg-background/90">
                    <CardContent className="p-6 text-center text-muted-foreground">
                      No se encontraron porcentajes FNDR para los filtros seleccionados.
                    </CardContent>
                  </Card>
                ) : items.map((item, index) => (
                  <Card key={buildRowKey(item, index)} className="border border-border/70 bg-background/90">
                    <CardContent className="flex flex-col gap-4 p-5">
                      <div className="flex items-start justify-between gap-3">
                        <div>
                          <p className="font-medium text-foreground">{item.description || item.descripcion || "-"}</p>
                          <p className="text-sm text-muted-foreground">{item.display_id || item.code || item.codigo || "-"}</p>
                        </div>
                        <Badge className={`rounded-full px-3 py-1 text-[11px] uppercase tracking-[0.18em] ${statusClassMap[item.status] || "bg-slate-500 text-white"}`}>
                          {item.status_label}
                        </Badge>
                      </div>
                      <div className="grid gap-2 text-sm text-muted-foreground">
                        <p><span className="font-medium text-foreground">Porcentaje:</span> {item.percentage ?? item.porcentaje ?? "-"}</p>
                      </div>
                      <div className="flex gap-2">
                        {item.available_actions?.edit ? (
                          <Button variant="outline" className="rounded-full border-border/70" onClick={() => openEdit(item.id || item.internal_id || item.id_porcentaje)}>
                            <Pencil data-icon="inline-start" />
                            Editar
                          </Button>
                        ) : null}
                      </div>
                    </CardContent>
                  </Card>
                ))}
              </div>

              <div className="flex flex-col gap-4 px-1 text-sm text-muted-foreground lg:flex-row lg:items-center lg:justify-between">
                <p>
                  Mostrando registros del {meta.from || 0} al {meta.to || 0} de un total de {meta.total || 0} registros
                </p>

                <div className="flex items-center gap-2 self-end lg:self-auto">
                  <Button
                    variant="ghost"
                    size="sm"
                    className="rounded-full text-muted-foreground"
                    onClick={() => setPage((current) => Math.max(1, current - 1))}
                    disabled={meta.current_page <= 1 || listQuery.isFetching}
                  >
                    <ChevronLeft data-icon="inline-start" />
                    Anterior
                  </Button>

                  <div className="flex items-center gap-1">
                    {visiblePages.map((pageNumber) => (
                      <Button
                        key={pageNumber}
                        variant={pageNumber === meta.current_page ? "default" : "ghost"}
                        size="icon-sm"
                        className={pageNumber === meta.current_page ? "rounded-full bg-foreground text-background" : "rounded-full text-muted-foreground"}
                        onClick={() => setPage(pageNumber)}
                        disabled={listQuery.isFetching}
                      >
                        {pageNumber}
                      </Button>
                    ))}
                  </div>

                  <Button
                    variant="ghost"
                    size="sm"
                    className="rounded-full text-muted-foreground"
                    onClick={() => setPage((current) => Math.min(totalPages, current + 1))}
                    disabled={meta.current_page >= totalPages || listQuery.isFetching}
                  >
                    Siguiente
                    <ChevronRight data-icon="inline-end" />
                  </Button>
                </div>
              </div>
            </>
          )}
        </CardContent>
      </Card>

      {isFormOpen && createPortal(
        <div className="fixed inset-0 z-[80] flex justify-end bg-slate-950/20 backdrop-blur-[1px]">
          <div className="w-full max-w-2xl overflow-y-auto border-l border-border/70 bg-background/96 p-4 shadow-[0_0_60px_rgba(15,23,42,0.16)] backdrop-blur xl:p-6">
            <Card className="border border-border/70 bg-white/92 shadow-[0_24px_90px_rgba(15,23,42,0.08)]">
              <CardHeader className="border-b border-border/70 bg-muted/20">
                <div className="flex items-start justify-between gap-4">
                  <div>
                    <CardTitle className="text-2xl tracking-[-0.04em]">
                      {isEditing ? "Editar FNDR" : "Registrar FNDR"}
                    </CardTitle>
                    <CardDescription>
                      {isEditing
                        ? "Actualiza el porcentaje de cálculo FNDR seleccionado."
                        : "Registra un nuevo porcentaje de cálculo FNDR."}
                    </CardDescription>
                  </div>

                  <Button variant="ghost" size="icon-sm" className="rounded-full" onClick={closeForm}>
                    <X />
                  </Button>
                </div>
              </CardHeader>

              <CardContent className="p-5 sm:p-6">
                {isEditing && isDetailLoading ? (
                  <div className="flex items-center justify-center rounded-2xl border border-border/70 bg-background/70 p-8 text-muted-foreground">
                    <Loader2 className="mr-2 size-4 animate-spin" /> Cargando detalle del porcentaje...
                  </div>
                ) : (
                  <form className="flex flex-col gap-5" onSubmit={handleSubmitForm}>
                    <div className="grid gap-5 sm:grid-cols-2">
                      <div className="flex flex-col gap-2">
                        <Label htmlFor="code" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                          Código
                        </Label>
                        <Input id="code" value={form.code} onChange={handleFormChange} className="h-12 rounded-2xl border-border/80 bg-background/90" />
                        {formErrors.code && <p className="text-sm text-destructive">{formErrors.code[0]}</p>}
                        {formErrors.codigo && <p className="text-sm text-destructive">{formErrors.codigo[0]}</p>}
                      </div>

                      <div className="flex flex-col gap-2">
                        <Label htmlFor="status" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                          Estado
                        </Label>
                        <select id="status" value={form.status} onChange={handleFormChange} className="h-12 rounded-2xl border border-border/80 bg-background/90 px-4 text-sm text-foreground outline-none">
                          {statuses.map((status) => (
                            <option key={status.code} value={status.label}>{status.label}</option>
                          ))}
                        </select>
                        {formErrors.status && <p className="text-sm text-destructive">{formErrors.status[0]}</p>}
                        {formErrors.estado && <p className="text-sm text-destructive">{formErrors.estado[0]}</p>}
                      </div>

                      <div className="flex flex-col gap-2 sm:col-span-2">
                        <Label htmlFor="description" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                          Descripción
                        </Label>
                        <Input id="description" value={form.description} onChange={handleFormChange} className="h-12 rounded-2xl border-border/80 bg-background/90" />
                        {formErrors.description && <p className="text-sm text-destructive">{formErrors.description[0]}</p>}
                        {formErrors.descripcion && <p className="text-sm text-destructive">{formErrors.descripcion[0]}</p>}
                      </div>

                      <div className="flex flex-col gap-2">
                        <Label htmlFor="percentage" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                          Porcentaje % Asignado
                        </Label>
                        <Input id="percentage" type="number" value={form.percentage} onChange={handleFormChange} className="h-12 rounded-2xl border-border/80 bg-background/90" />
                        {formErrors.percentage && <p className="text-sm text-destructive">{formErrors.percentage[0]}</p>}
                        {formErrors.porcentaje && <p className="text-sm text-destructive">{formErrors.porcentaje[0]}</p>}
                      </div>

                      <div className="flex flex-col gap-2">
                        <Label htmlFor="observation" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                          Observación
                        </Label>
                        <Input id="observation" value={form.observation} onChange={handleFormChange} className="h-12 rounded-2xl border-border/80 bg-background/90" />
                        {formErrors.observation && <p className="text-sm text-destructive">{formErrors.observation[0]}</p>}
                        {formErrors.observacion && <p className="text-sm text-destructive">{formErrors.observacion[0]}</p>}
                      </div>
                    </div>

                    <Separator className="bg-border/70" />

                    <div className="flex flex-wrap justify-end gap-2">
                      <Button type="button" variant="outline" className="rounded-full border-border/70 bg-background/80" onClick={closeForm}>
                        Cancelar
                      </Button>
                      <Button type="submit" className="rounded-full bg-foreground text-background hover:bg-foreground/90" disabled={isSubmitting}>
                        {isSubmitting && <Loader2 data-icon="inline-start" className="animate-spin" />}
                        {isEditing ? "Guardar cambios" : "Registrar FNDR"}
                      </Button>
                    </div>
                  </form>
                )}
              </CardContent>
            </Card>
          </div>
        </div>,
        document.body,
      )}
    </div>
  );
}
