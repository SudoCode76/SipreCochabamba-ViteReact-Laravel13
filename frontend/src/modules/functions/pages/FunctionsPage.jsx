import { useDeferredValue, useMemo, useState } from "react";
import { createPortal } from "react-dom";
import {
  Check,
  ChevronLeft,
  ChevronRight,
  Filter,
  Loader2,
  MoreHorizontal,
  Pencil,
  Plus,
  RefreshCcw,
  Shield,
  Waypoints,
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
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Separator } from "@/components/ui/separator";
import { cn } from "@/lib/utils";
import { functionsService } from "@/modules/functions/services/functions.service";

const statusClassMap = {
  AC: "bg-emerald-600 text-white",
  DC: "bg-slate-900 text-white",
};

const initialForm = {
  name: "",
  description: "",
  controller: "",
  status: "ACTIVO",
};

function buildFormFromFunction(item) {
  return {
    name: item?.name ?? item?.nombre_funcion ?? "",
    description: item?.description ?? item?.descripcion ?? "",
    controller: item?.controller ?? item?.class ?? item?.clase ?? "",
    status: item?.status_label ?? (item?.status === "DC" ? "INACTIVO" : "ACTIVO"),
  };
}

export default function FunctionsPage() {
  const queryClient = useQueryClient();

  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(10);
  const [searchTerm, setSearchTerm] = useState("");
  const [statusFilter, setStatusFilter] = useState("");
  const [classFilter, setClassFilter] = useState("");
  const [isFormOpen, setIsFormOpen] = useState(false);
  const [editingFunctionId, setEditingFunctionId] = useState(null);
  const [isDetailLoading, setIsDetailLoading] = useState(false);
  const [form, setForm] = useState(initialForm);
  const [formErrors, setFormErrors] = useState({});
  const [feedback, setFeedback] = useState(null);

  const deferredSearchTerm = useDeferredValue(searchTerm.trim());

  const contextQuery = useQuery({
    queryKey: ["functions-context"],
    queryFn: functionsService.context,
  });

  const matrixQuery = useQuery({
    queryKey: ["functions-permissions-matrix"],
    queryFn: functionsService.permissionsMatrix,
  });

  const functionsQuery = useQuery({
    queryKey: ["functions", { page, perPage, deferredSearchTerm, classFilter, statusFilter }],
    queryFn: () => functionsService.list({
      page,
      perPage,
      search: deferredSearchTerm,
      className: classFilter,
      status: statusFilter,
    }),
    placeholderData: (previousData) => previousData,
  });

  const functionsList = useMemo(() => functionsQuery.data?.data?.items ?? [], [functionsQuery.data]);
  const meta = functionsQuery.data?.data?.meta ?? { current_page: 1, per_page: perPage, total: 0, from: 0, to: 0, last_page: 1 };
  const statuses = contextQuery.data?.data?.statuses ?? [];
  const availableClasses = contextQuery.data?.data?.available_classes ?? [];
  const matrixRoles = matrixQuery.data?.data?.roles ?? [];

  const permissionsByFunctionId = useMemo(() => new Map(
    (matrixQuery.data?.data?.functions ?? []).map((item) => [
      item.id,
      new Map((item.permissions ?? []).map((permission) => [permission.role_id, permission.allowed])),
    ]),
  ), [matrixQuery.data]);

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
    setEditingFunctionId(null);
  };

  const closeForm = () => {
    setIsFormOpen(false);
    resetForm();
  };

  const openCreate = () => {
    resetForm();
    setIsFormOpen(true);
  };

  const openEdit = async (functionId) => {
    setFormErrors({});
    setEditingFunctionId(functionId);
    setIsFormOpen(true);
    setIsDetailLoading(true);

    try {
      const detail = await queryClient.fetchQuery({
        queryKey: ["function-detail", functionId],
        queryFn: () => functionsService.getById(functionId),
      });

      const item = detail?.data?.function;
      if (item) {
        setForm(buildFormFromFunction(item));
      }
    } catch (error) {
      setFeedback({
        type: "error",
        message: error.response?.data?.message || "No se pudo cargar el detalle de la función.",
      });
      closeForm();
    } finally {
      setIsDetailLoading(false);
    }
  };

  const createMutation = useMutation({
    mutationFn: functionsService.create,
    onSuccess: () => {
      setFeedback({ type: "success", message: "Función creada correctamente." });
      closeForm();
      queryClient.invalidateQueries({ queryKey: ["functions"] });
      queryClient.invalidateQueries({ queryKey: ["functions-context"] });
    },
    onError: (error) => {
      setFormErrors(error.response?.data?.errors ?? {});
      setFeedback({
        type: "error",
        message: error.response?.status === 403
          ? "Acceso denegado para registrar funciones."
          : error.response?.data?.message || "No se pudo crear la función.",
      });
    },
  });

  const updateMutation = useMutation({
    mutationFn: ({ functionId, payload }) => functionsService.update(functionId, payload),
    onSuccess: () => {
      setFeedback({ type: "success", message: "Función actualizada correctamente." });
      closeForm();
      queryClient.invalidateQueries({ queryKey: ["functions"] });
      queryClient.invalidateQueries({ queryKey: ["function-detail"] });
      queryClient.invalidateQueries({ queryKey: ["functions-context"] });
    },
    onError: (error) => {
      setFormErrors(error.response?.data?.errors ?? {});
      setFeedback({
        type: "error",
        message: error.response?.status === 403
          ? "Acceso denegado para editar funciones."
          : error.response?.data?.message || "No se pudo actualizar la función.",
      });
    },
  });

  const statusMutation = useMutation({
    mutationFn: ({ functionId, payload }) => functionsService.updateStatus(functionId, payload),
    onSuccess: () => {
      setFeedback({ type: "success", message: "Estado actualizado correctamente." });
      queryClient.invalidateQueries({ queryKey: ["functions"] });
    },
    onError: (error) => {
      setFeedback({
        type: "error",
        message: error.response?.status === 403
          ? "Acceso denegado para cambiar estado de la función."
          : error.response?.data?.message || "No se pudo actualizar el estado.",
      });
    },
  });

  const isSubmitting = createMutation.isPending || updateMutation.isPending;
  const isEditing = editingFunctionId !== null;

  const handlePerPageChange = (event) => {
    setPerPage(Number(event.target.value));
    setPage(1);
  };

  const handleFormChange = (event) => {
    const { id, value } = event.target;
    setForm((current) => ({ ...current, [id]: value }));
  };

  const validateForm = () => {
    const nextErrors = {};

    if (!form.name.trim()) nextErrors.name = ["El nombre de la función es obligatorio."];
    if (!form.description.trim()) nextErrors.description = ["La descripción es obligatoria."];
    if (!form.controller.trim()) nextErrors.controller = ["El controlador es obligatorio."];
    if (!form.status) nextErrors.status = ["El estado es obligatorio."];

    setFormErrors(nextErrors);
    return Object.keys(nextErrors).length === 0;
  };

  const handleSubmitForm = (event) => {
    event.preventDefault();
    setFeedback(null);

    if (!validateForm()) {
      return;
    }

    const payload = {
      name: form.name.trim(),
      description: form.description.trim(),
      controlador: form.controller.trim(),
      status: form.status,
    };

    if (isEditing) {
      updateMutation.mutate({ functionId: editingFunctionId, payload });
      return;
    }

    createMutation.mutate(payload);
  };

  const handleToggleStatus = (item) => {
    const nextStatus = item.status === "AC" ? "INACTIVO" : "ACTIVO";
    statusMutation.mutate({ functionId: item.id, payload: { status: nextStatus } });
  };

  return (
    <div className="flex flex-col gap-6 animate-in fade-in duration-500">
      <Card className="border border-border/70 bg-white/86 shadow-[0_24px_90px_rgba(15,23,42,0.08)] backdrop-blur">
        <CardHeader className="gap-4 border-b border-border/70 bg-muted/25">
          <div className="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
            <div className="flex items-center gap-3">
              <div className="flex size-11 items-center justify-center rounded-2xl bg-foreground text-background">
                <Waypoints className="size-5" />
              </div>
              <div>
                <CardTitle className="text-2xl tracking-[-0.04em]">Administración de funciones</CardTitle>
              </div>
            </div>

            <div className="flex flex-wrap gap-2">
              <Button
                variant="outline"
                className="rounded-full border-border/70 bg-background/80"
                onClick={() => functionsQuery.refetch()}
                disabled={functionsQuery.isFetching}
              >
                <RefreshCcw data-icon="inline-start" className={cn(functionsQuery.isFetching && "animate-spin")} />
                Refrescar
              </Button>
              <Button className="rounded-full bg-foreground text-background hover:bg-foreground/90" onClick={openCreate}>
                <Plus data-icon="inline-start" />
                Registrar Función
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

          <div className="grid gap-4 xl:grid-cols-[minmax(0,1.1fr)_repeat(3,minmax(0,0.5fr))] xl:items-end">
            <div className="flex flex-col gap-2">
              <Label htmlFor="searchTerm" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                Búsqueda global
              </Label>
              <ClearableSearchInput
                id="searchTerm"
                placeholder="Buscar por nombre, descripción o controlador"
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
                isLoading={functionsQuery.isFetching && !functionsQuery.isLoading}
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
              <Label htmlFor="classFilter" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                Controlador
              </Label>
              <Input
                id="classFilter"
                className="h-12 rounded-2xl border-border/80 bg-background/90"
                value={classFilter}
                onChange={(event) => {
                  setClassFilter(event.target.value);
                  setPage(1);
                }}
                placeholder="Todos"
                list="function-classes-filter"
              />
              <datalist id="function-classes-filter">
                {availableClasses.map((item) => (
                  <option key={item.value} value={item.value} />
                ))}
              </datalist>
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

            <div className="xl:col-span-4 flex flex-wrap items-center justify-between gap-3">
              <div className="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                <Filter className="size-3.5" /> Filtros administrativos
              </div>
            </div>
          </div>

          {functionsQuery.isLoading && (
            <div className="flex items-center justify-center rounded-2xl border border-border/70 bg-background/70 p-8 text-muted-foreground">
              <Loader2 className="mr-2 size-4 animate-spin" /> Cargando funciones...
            </div>
          )}

          {functionsQuery.isError && (
            <Alert variant="destructive" className="rounded-2xl">
              <AlertDescription>
                {functionsQuery.error?.response?.status === 403
                  ? "No tienes permisos para acceder a la administración de funciones."
                  : functionsQuery.error?.response?.data?.message || "No se pudieron cargar las funciones."}
              </AlertDescription>
            </Alert>
          )}

          {!functionsQuery.isLoading && !functionsQuery.isError && (
            <>
              <div className="hidden overflow-hidden rounded-[28px] border border-border/70 bg-background/90 lg:block">
                <div className="overflow-x-auto">
                  <table className="min-w-full border-collapse text-sm">
                    <thead>
                      <tr className="border-b border-border/70 bg-muted/30 text-left">
                        <th className="px-5 py-4 font-semibold text-foreground">N°</th>
                        <th className="px-5 py-4 font-semibold text-foreground">Descripción</th>
                        <th className="px-5 py-4 font-semibold text-foreground">Controlador</th>
                        <th className="px-5 py-4 font-semibold text-foreground">Nombre de la Función</th>
                        {matrixRoles.map((role) => (
                          <th key={role.id} className="px-5 py-4 font-semibold text-foreground min-w-36">
                            {role.name}
                          </th>
                        ))}
                        <th className="px-5 py-4 font-semibold text-foreground">Estado</th>
                        <th className="px-5 py-4 font-semibold text-foreground text-right">Opciones</th>
                      </tr>
                    </thead>
                    <tbody>
                      {functionsList.map((item, index) => (
                        <tr key={item.id} className={index < functionsList.length - 1 ? "border-b border-border/60" : ""}>
                          <td className="px-5 py-4 align-top text-foreground">{(meta.from || 1) + index}</td>
                          <td className="px-5 py-4 align-top text-foreground">{item.description}</td>
                          <td className="px-5 py-4 align-top text-muted-foreground">{item.controller}</td>
                          <td className="px-5 py-4 align-top text-muted-foreground">{item.name}</td>
                          {matrixRoles.map((role) => {
                            const allowed = permissionsByFunctionId.get(item.id)?.get(role.id) ?? false;

                            return (
                              <td key={`${item.id}-${role.id}`} className="px-5 py-4 align-top text-center">
                                {allowed ? (
                                  <span className="inline-flex size-8 items-center justify-center rounded-full bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200">
                                    <Check className="size-4" />
                                  </span>
                                ) : null}
                              </td>
                            );
                          })}
                          <td className="px-5 py-4 align-top">
                            <Badge className={`rounded-full px-3 py-1 text-[11px] uppercase tracking-[0.18em] ${statusClassMap[item.status] || "bg-slate-500 text-white"}`}>
                              {item.status_label}
                            </Badge>
                          </td>
                          <td className="px-5 py-4 align-top">
                            <div className="flex justify-end">
                              <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                  <Button variant="ghost" size="icon-sm" className="rounded-full">
                                    <MoreHorizontal />
                                  </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end" className="w-56 rounded-2xl border-border/70 bg-background/95 p-2 shadow-xl backdrop-blur-xl">
                                  <DropdownMenuItem className="rounded-xl px-3 py-2 cursor-pointer" onClick={() => openEdit(item.id)}>
                                    <Pencil className="mr-2 size-4" />
                                    Editar función
                                  </DropdownMenuItem>
                                  {item.available_actions?.update_status && (
                                    <>
                                      <DropdownMenuSeparator className="bg-border/70" />
                                      <DropdownMenuItem className="rounded-xl px-3 py-2 cursor-pointer" onClick={() => handleToggleStatus(item)}>
                                        <Shield className="mr-2 size-4" />
                                        {item.status === "AC" ? "Desactivar" : "Activar"}
                                      </DropdownMenuItem>
                                    </>
                                  )}
                                </DropdownMenuContent>
                              </DropdownMenu>
                            </div>
                          </td>
                        </tr>
                      ))}
                      {functionsList.length === 0 && (
                        <tr>
                          <td colSpan={6 + matrixRoles.length} className="px-5 py-8 text-center text-muted-foreground">
                            No se encontraron funciones para los filtros seleccionados.
                          </td>
                        </tr>
                      )}
                    </tbody>
                  </table>
                </div>
              </div>

              <div className="grid gap-4 lg:hidden">
                {functionsList.length === 0 ? (
                  <Card className="border border-border/70 bg-background/90">
                    <CardContent className="p-6 text-center text-muted-foreground">
                      No se encontraron funciones para los filtros seleccionados.
                    </CardContent>
                  </Card>
                ) : functionsList.map((item) => (
                  <Card key={item.id} className="border border-border/70 bg-background/90">
                    <CardContent className="flex flex-col gap-4 p-5">
                      <div className="flex items-start justify-between gap-3">
                        <div>
                          <p className="font-medium text-foreground">{item.description}</p>
                          <p className="text-sm text-muted-foreground">{item.name}</p>
                        </div>
                        <Badge className={`rounded-full px-3 py-1 text-[11px] uppercase tracking-[0.18em] ${statusClassMap[item.status] || "bg-slate-500 text-white"}`}>
                          {item.status_label}
                        </Badge>
                      </div>
                      <div className="grid gap-2 text-sm text-muted-foreground">
                        <p><span className="font-medium text-foreground">Controlador:</span> {item.controller}</p>
                        <div>
                          <span className="font-medium text-foreground">Roles con permiso:</span>
                          <div className="mt-2 flex flex-wrap gap-2">
                            {matrixRoles.filter((role) => permissionsByFunctionId.get(item.id)?.get(role.id)).length > 0 ? (
                              matrixRoles
                                .filter((role) => permissionsByFunctionId.get(item.id)?.get(role.id))
                                .map((role) => (
                                  <Badge key={`${item.id}-${role.id}`} variant="outline" className="rounded-full border-border/70 bg-muted/30 px-3 py-1 text-[11px] uppercase tracking-[0.18em] text-foreground">
                                    {role.name}
                                  </Badge>
                                ))
                            ) : (
                              <span>Sin permisos asignados.</span>
                            )}
                          </div>
                        </div>
                      </div>
                      <div className="flex gap-2">
                        <Button variant="outline" className="rounded-full border-border/70" onClick={() => openEdit(item.id)}>
                          <Pencil data-icon="inline-start" />
                          Editar
                        </Button>
                        {item.available_actions?.update_status && (
                          <Button variant="outline" className="rounded-full border-border/70" onClick={() => handleToggleStatus(item)}>
                            <Shield data-icon="inline-start" />
                            {item.status === "AC" ? "Desactivar" : "Activar"}
                          </Button>
                        )}
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
                    disabled={meta.current_page <= 1 || functionsQuery.isFetching}
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
                        disabled={functionsQuery.isFetching}
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
                    disabled={meta.current_page >= totalPages || functionsQuery.isFetching}
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
                      {isEditing ? "Editar función" : "Registrar función"}
                    </CardTitle>
                    <CardDescription>
                      {isEditing
                        ? "Actualiza la descripción, controlador y estado de la función seleccionada."
                        : "Registra una nueva función del sistema siguiendo el mismo patrón del legado."}
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
                    <Loader2 className="mr-2 size-4 animate-spin" /> Cargando detalle de la función...
                  </div>
                ) : (
                  <form className="flex flex-col gap-5" onSubmit={handleSubmitForm}>
                    <div className="grid gap-5 sm:grid-cols-2">
                      <div className="flex flex-col gap-2 sm:col-span-2">
                        <Label htmlFor="description" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                          Descripción
                        </Label>
                        <Input id="description" value={form.description} onChange={handleFormChange} className="h-12 rounded-2xl border-border/80 bg-background/90" />
                        {formErrors.description && <p className="text-sm text-destructive">{formErrors.description[0]}</p>}
                      </div>

                      <div className="flex flex-col gap-2 sm:col-span-2">
                        <Label htmlFor="controller" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                          Controlador
                        </Label>
                        <Input
                          id="controller"
                          value={form.controller}
                          onChange={handleFormChange}
                          className="h-12 rounded-2xl border-border/80 bg-background/90"
                          list="function-classes-form"
                          placeholder="Selecciona o escribe una clase"
                        />
                        <datalist id="function-classes-form">
                          {availableClasses.map((item) => (
                            <option key={item.value} value={item.value} />
                          ))}
                        </datalist>
                        {formErrors.controlador && <p className="text-sm text-destructive">{formErrors.controlador[0]}</p>}
                        {formErrors.clase && <p className="text-sm text-destructive">{formErrors.clase[0]}</p>}
                        {formErrors.controller && <p className="text-sm text-destructive">{formErrors.controller[0]}</p>}
                      </div>

                      <div className="flex flex-col gap-2">
                        <Label htmlFor="name" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                          Nombre de la función
                        </Label>
                        <Input id="name" value={form.name} onChange={handleFormChange} className="h-12 rounded-2xl border-border/80 bg-background/90" />
                        {formErrors.name && <p className="text-sm text-destructive">{formErrors.name[0]}</p>}
                        {formErrors.nombre_funcion && <p className="text-sm text-destructive">{formErrors.nombre_funcion[0]}</p>}
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
                    </div>

                    <Separator className="bg-border/70" />

                    <div className="flex flex-wrap justify-end gap-2">
                      <Button type="button" variant="outline" className="rounded-full border-border/70 bg-background/80" onClick={closeForm}>
                        Cancelar
                      </Button>
                      <Button type="submit" className="rounded-full bg-foreground text-background hover:bg-foreground/90" disabled={isSubmitting}>
                        {isSubmitting && <Loader2 data-icon="inline-start" className="animate-spin" />}
                        {isEditing ? "Guardar cambios" : "Registrar función"}
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
