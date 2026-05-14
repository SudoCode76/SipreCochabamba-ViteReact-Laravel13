import { useEffect, useMemo, useState } from "react";
import { createPortal } from "react-dom";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Loader2, Package, Search, ChevronLeft, ChevronRight, MoreHorizontal, Pencil, Eye, History, Power, FileText, X } from "lucide-react";

import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Separator } from "@/components/ui/separator";
import { Alert, AlertDescription } from "@/components/ui/alert";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
  DialogFooter,
} from "@/components/ui/dialog";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { inputsService } from "@/modules/inputs/services/inputs.service";
import apiClient from "@/lib/api/client";

const statusClass = {
  AC: "bg-emerald-600 text-white",
  DC: "bg-rose-600 text-white",
};

export default function InputsPage() {
  const queryClient = useQueryClient();
  const [perPage, setPerPage] = useState(15);
  const [search, setSearch] = useState("");
  const [searchQuery, setSearchQuery] = useState("");
  const [page, setPage] = useState(1);
  const [createOpen, setCreateOpen] = useState(false);
  const [editOpen, setEditOpen] = useState(false);
  const [viewOpen, setViewOpen] = useState(false);
  const [selectedInput, setSelectedInput] = useState(null);
  const [createError, setCreateError] = useState(null);
  const [editError, setEditError] = useState(null);
  const [createForm, setCreateForm] = useState({
    descripcion: "",
    precio: "",
    unidad_medida: "",
    tipo: "",
    fecha_cotiz: new Date().toISOString().split("T")[0],
    observacion: "",
    estado: "AC",
    cod: "",
  });
  const [editForm, setEditForm] = useState({
    descripcion: "",
    precio: "",
    unidad_medida: "",
    tipo: "",
    fecha_cotiz: "",
    observacion: "",
    estado: "AC",
    cod: "",
  });

  const { data, isLoading, isError, error, isFetching } = useQuery({
    queryKey: ["inputs", { page, perPage, description: search }],
    queryFn: () => inputsService.list({ page, perPage, description: search }),
    placeholderData: (previousData) => previousData,
  });

  const { data: contextData, isLoading: contextLoading } = useQuery({
    queryKey: ["inputs-context"],
    queryFn: async () => {
      const response = await apiClient.get("/v1/inputs/context");
      return response.data;
    },
    enabled: createOpen || editOpen,
  });

  const items = data?.data?.items ?? [];
  const meta = data?.data?.meta ?? { current_page: 1, per_page: perPage, total: 0 };
  const totalPages = Math.max(1, Math.ceil((meta.total || 0) / (meta.per_page || perPage)));
  const inputTypes = contextData?.data?.types ?? [];
  const unitMeasures = contextData?.data?.unit_measures ?? [];
  const statuses = contextData?.data?.statuses ?? [];

  const createMutation = useMutation({
    mutationFn: async (payload) => {
      const response = await apiClient.post("/v1/inputs", payload);
      return response.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["inputs"] });
      closeCreate();
    },
  });

  const updateMutation = useMutation({
    mutationFn: async ({ id, payload }) => {
      const response = await apiClient.put(`/v1/inputs/${id}`, payload);
      return response.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["inputs"] });
      closeEdit();
    },
  });

  const visiblePages = useMemo(() => {
    const start = Math.max(1, meta.current_page - 2);
    const end = Math.min(totalPages, start + 4);
    const first = Math.max(1, end - 4);
    return Array.from({ length: end - first + 1 }, (_, i) => first + i);
  }, [meta.current_page, totalPages]);

  const startRecord = meta.total === 0 ? 0 : (meta.current_page - 1) * meta.per_page + 1;
  const endRecord = Math.min(meta.current_page * meta.per_page, meta.total);

  useEffect(() => {
    const timeoutId = setTimeout(() => {
      setPage(1);
      setSearch(searchQuery.trim());
    }, 300);

    return () => clearTimeout(timeoutId);
  }, [searchQuery]);

  useEffect(() => {
    if (!selectedInput || !editOpen) {
      return;
    }

    setEditForm({
      descripcion: selectedInput.descripcion ?? "",
      precio: selectedInput.precio ?? "",
      unidad_medida: String(selectedInput.unidad_medida ?? ""),
      tipo: String(selectedInput.tipo ?? ""),
      fecha_cotiz: selectedInput.fecha_cotiz ?? "",
      observacion: selectedInput.observacion ?? "",
      estado: selectedInput.estado ?? "AC",
      cod: selectedInput.cod ?? "",
    });
  }, [selectedInput, editOpen]);

  const handlePerPageChange = (event) => {
    setPerPage(Number(event.target.value));
    setPage(1);
  };

  const handleEdit = (item) => {
    setSelectedInput(item);
    setEditError(null);
    setEditOpen(true);
  };

  const handleView = (item) => {
    setSelectedInput(item);
    setViewOpen(true);
  };

  const closeCreate = () => {
    setCreateOpen(false);
    setCreateError(null);
    setCreateForm({
      descripcion: "",
      precio: "",
      unidad_medida: "",
      tipo: "",
      fecha_cotiz: new Date().toISOString().split("T")[0],
      observacion: "",
      estado: "AC",
      cod: "",
    });
  };

  const closeEdit = () => {
    setEditOpen(false);
    setEditError(null);
    setSelectedInput(null);
    setEditForm({
      descripcion: "",
      precio: "",
      unidad_medida: "",
      tipo: "",
      fecha_cotiz: "",
      observacion: "",
      estado: "AC",
      cod: "",
    });
  };

  const handleCreateChange = (field, value) => {
    setCreateForm((current) => ({ ...current, [field]: value }));
  };

  const handleCreateSubmit = async (event) => {
    event.preventDefault();
    setCreateError(null);

    try {
      await createMutation.mutateAsync({
        descripcion: createForm.descripcion.trim(),
        precio: Number(createForm.precio),
        unidad_medida: Number(createForm.unidad_medida),
        tipo: Number(createForm.tipo),
        fecha_cotiz: createForm.fecha_cotiz,
        observacion: createForm.observacion.trim() || null,
        estado: createForm.estado,
        cod: createForm.cod.trim() || null,
      });
    } catch (err) {
      const fieldErrors = err.response?.data?.errors;
      const firstFieldError = fieldErrors ? Object.values(fieldErrors).flat().find(Boolean) : null;
      setCreateError(firstFieldError || err.response?.data?.message || "No se pudo registrar el insumo.");
    }
  };

  const handleEditChange = (field, value) => {
    setEditForm((current) => ({ ...current, [field]: value }));
  };

  const handleEditSubmit = async (event) => {
    event.preventDefault();

    if (!selectedInput?.id_insumo) {
      return;
    }

    setEditError(null);

    try {
      await updateMutation.mutateAsync({
        id: selectedInput.id_insumo,
        payload: {
          descripcion: editForm.descripcion.trim(),
          precio: Number(editForm.precio),
          unidad_medida: Number(editForm.unidad_medida),
          tipo: Number(editForm.tipo),
          fecha_cotiz: editForm.fecha_cotiz,
          observacion: editForm.observacion.trim() || null,
          estado: editForm.estado,
          cod: editForm.cod.trim() || null,
        },
      });
    } catch (err) {
      const fieldErrors = err.response?.data?.errors;
      const firstFieldError = fieldErrors ? Object.values(fieldErrors).flat().find(Boolean) : null;
      setEditError(firstFieldError || err.response?.data?.message || "No se pudo actualizar el insumo.");
    }
  };

  return (
    <div className="flex flex-col gap-6 animate-in fade-in duration-500">
      <Card className="border border-border/70 bg-white/86 shadow-[0_24px_90px_rgba(15,23,42,0.08)]">
        <CardHeader className="gap-4 border-b border-border/70 bg-muted/25">
          <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div className="flex items-center gap-3">
              <div className="flex size-11 items-center justify-center rounded-2xl bg-emerald-600 text-white">
                <Package className="size-5" />
              </div>
              <div>
                <CardTitle className="text-2xl tracking-[-0.04em]">Insumos</CardTitle>
              </div>
            </div>
          </div>
        </CardHeader>

        <CardContent className="flex flex-col gap-6 p-5 sm:p-6">
          <div className="flex justify-end">
            <Button className="rounded-full bg-emerald-600 text-white hover:bg-emerald-700" onClick={() => setCreateOpen(true)}>
              Registrar nuevo insumo
            </Button>
          </div>

          <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div className="flex items-end gap-3">
              <div className="flex flex-col gap-2">
                <span className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                  Mostrar
                </span>
                <div className="relative">
                  <select
                    className="h-12 min-w-32 appearance-none rounded-2xl border border-border/80 bg-background/90 px-4 pr-10 text-sm text-foreground outline-none transition focus:border-foreground/20"
                    value={perPage}
                    onChange={handlePerPageChange}
                  >
                    <option value={10}>10</option>
                    <option value={15}>15</option>
                    <option value={25}>25</option>
                    <option value={50}>50</option>
                  </select>
                  <span className="pointer-events-none absolute inset-y-0 right-4 flex items-center text-muted-foreground">▾</span>
                </div>
              </div>
            </div>

            <div className="flex w-full max-w-sm flex-col gap-2">
              <span className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                Buscar
              </span>
              <div className="relative">
                <Search className="pointer-events-none absolute left-4 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                  placeholder="Buscar insumo..."
                  className="h-12 rounded-2xl border-border/80 bg-background/90 pl-11"
                  value={searchQuery}
                  onChange={(event) => setSearchQuery(event.target.value)}
                />
              </div>
            </div>
          </div>

          {isLoading && (
            <div className="flex items-center justify-center rounded-2xl border border-border/70 bg-background/70 p-8 text-muted-foreground">
              <Loader2 className="mr-2 size-4 animate-spin" /> Cargando insumos...
            </div>
          )}

          {isError && (
            <Alert variant="destructive" className="rounded-2xl">
              <AlertDescription>
                {error?.response?.data?.message || "No se pudieron cargar los insumos."}
              </AlertDescription>
            </Alert>
          )}

          {!isLoading && !isError && (
            <div className="overflow-hidden rounded-[28px] border border-border/70 bg-background/90">
              <div className="overflow-x-auto max-h-[500px] overflow-y-auto">
                <table className="min-w-full border-collapse text-sm">
                  <thead>
                    <tr className="border-b border-border/70 bg-muted/30 text-left">
                      <th className="px-5 py-4 font-semibold text-foreground">N°</th>
                      <th className="px-5 py-4 font-semibold text-foreground">Insumo</th>
                      <th className="px-5 py-4 font-semibold text-foreground">Tipo</th>
                      <th className="px-5 py-4 font-semibold text-foreground">Precio</th>
                      <th className="px-5 py-4 font-semibold text-foreground">Unidad</th>
                      <th className="px-5 py-4 font-semibold text-foreground">Fecha Cotización</th>
                      <th className="px-5 py-4 font-semibold text-foreground">Estado</th>
                      <th className="px-5 py-4 font-semibold text-foreground text-center">Opciones</th>
                    </tr>
                  </thead>
                  <tbody>
                    {items.map((item, index) => (
                      <tr key={item.id_insumo} className={index < items.length - 1 ? "border-b border-border/60" : ""}>
                        <td className="px-5 py-4 align-top text-foreground">
                          {index + 1}
                        </td>
                        <td className="px-5 py-4 align-top text-foreground max-w-[300px]">
                          <div className="truncate">{item.descripcion}</div>
                        </td>
                        <td className="px-5 py-4 align-top text-muted-foreground">
                          {item.nombre_tipo}
                        </td>
                        <td className="px-5 py-4 align-top text-foreground">
                          {Number(item.precio).toFixed(2)}
                        </td>
                        <td className="px-5 py-4 align-top text-muted-foreground">
                          {item.abreviatura}
                        </td>
                        <td className="px-5 py-4 align-top text-muted-foreground">
                          {item.fecha_cotiz}
                        </td>
                        <td className="px-5 py-4 align-top">
                          <Badge className={`rounded-full px-3 py-1 text-[11px] uppercase tracking-[0.18em] ${statusClass[item.estado] || "bg-slate-500 text-white"}`}>
                            {item.estado === "AC" ? "HABILITADO" : "INHABILITADO"}
                          </Badge>
                        </td>
                        <td className="px-5 py-4 align-top text-center">
                          <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                              <Button variant="ghost" size="sm" className="h-8 w-8 p-0">
                                <MoreHorizontal className="h-4 w-4" />
                              </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-56 rounded-2xl border border-border/70 bg-background/95 p-1 shadow-lg">
                              <DropdownMenuItem 
                                className="flex items-center gap-2 rounded-xl px-3 py-2 cursor-pointer"
                                onClick={() => handleEdit(item)}
                              >
                                <Pencil className="h-4 w-4 text-muted-foreground" />
                                <span>Editar insumo</span>
                              </DropdownMenuItem>
                              <DropdownMenuItem className="flex items-center gap-2 rounded-xl px-3 py-2 cursor-pointer">
                                <FileText className="h-4 w-4 text-muted-foreground" />
                                <span>Adjuntar cotizacion</span>
                              </DropdownMenuItem>
                              <DropdownMenuItem 
                                className="flex items-center gap-2 rounded-xl px-3 py-2 cursor-pointer"
                                onClick={() => handleView(item)}
                              >
                                <Eye className="h-4 w-4 text-muted-foreground" />
                                <span>Ver cotizacion actual</span>
                              </DropdownMenuItem>
                              <DropdownMenuSeparator className="my-1 bg-border/50" />
                              <DropdownMenuItem className="flex items-center gap-2 rounded-xl px-3 py-2 cursor-pointer">
                                <Power className="h-4 w-4 text-muted-foreground" />
                                <span>Eliminar</span>
                              </DropdownMenuItem>
                              <DropdownMenuItem className="flex items-center gap-2 rounded-xl px-3 py-2 cursor-pointer">
                                <History className="h-4 w-4 text-muted-foreground" />
                                <span>Ver historial de insumo</span>
                              </DropdownMenuItem>
                            </DropdownMenuContent>
                          </DropdownMenu>
                        </td>
                      </tr>
                    ))}
                    {items.length === 0 && (
                      <tr>
                        <td colSpan={8} className="px-5 py-8 text-center text-muted-foreground">
                          No se encontraron insumos.
                        </td>
                      </tr>
                    )}
                  </tbody>
                </table>
              </div>

              <div className="flex flex-col gap-4 px-5 py-4 text-sm text-muted-foreground lg:flex-row lg:items-center lg:justify-between">
                <p>Mostrando registros del {startRecord} al {endRecord} de un total de {meta.total} registros</p>

                <div className="flex items-center gap-2 self-end lg:self-auto">
                  <Button
                    variant="ghost"
                    size="sm"
                    className="rounded-full text-muted-foreground"
                    onClick={() => setPage((prev) => Math.max(1, prev - 1))}
                    disabled={meta.current_page <= 1 || isFetching}
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
                        disabled={isFetching}
                      >
                        {pageNumber}
                      </Button>
                    ))}
                  </div>
                  <Button
                    variant="ghost"
                    size="sm"
                    className="rounded-full text-muted-foreground"
                    onClick={() => setPage((prev) => Math.min(totalPages, prev + 1))}
                    disabled={meta.current_page >= totalPages || isFetching}
                  >
                    Siguiente
                    <ChevronRight data-icon="inline-end" />
                  </Button>
                </div>
              </div>
            </div>
          )}
        </CardContent>
      </Card>

      {editOpen && createPortal(
        <div className="fixed inset-0 z-[80] flex justify-end bg-slate-950/20 backdrop-blur-[1px]">
          <div className="w-full max-w-4xl overflow-y-auto border-l border-border/70 bg-background/96 p-4 shadow-[0_0_60px_rgba(15,23,42,0.16)] backdrop-blur xl:p-6">
            <Card className="border border-border/70 bg-white/92 shadow-[0_24px_90px_rgba(15,23,42,0.08)]">
              <CardHeader className="border-b border-border/70 bg-muted/20">
                <div className="flex items-start justify-between gap-4">
                  <div>
                    <CardTitle className="text-2xl tracking-[-0.04em]">Editar Insumo</CardTitle>
                    <p className="text-sm text-muted-foreground">
                      Completa los datos para actualizar el insumo.
                    </p>
                  </div>

                  <Button variant="ghost" size="icon-sm" className="rounded-full" onClick={closeEdit}>
                    <X />
                  </Button>
                </div>
              </CardHeader>

              <CardContent className="p-5 sm:p-6">
                {selectedInput && (
                  <form className="flex flex-col gap-6" onSubmit={handleEditSubmit}>
                    {editError && (
                      <Alert variant="destructive" className="rounded-2xl">
                        <AlertDescription>{editError}</AlertDescription>
                      </Alert>
                    )}

                    <div className="flex flex-col gap-2">
                      <Label htmlFor="edit_descripcion" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">Descripción</Label>
                      <Input
                        id="edit_descripcion"
                        value={editForm.descripcion}
                        onChange={(event) => handleEditChange("descripcion", event.target.value)}
                        className="h-12 rounded-2xl border-border/80 bg-background/90"
                        required
                      />
                    </div>

                    <div className="grid gap-6 md:grid-cols-2">
                      <div className="flex flex-col gap-2">
                        <Label htmlFor="edit_precio" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">Precio</Label>
                        <Input
                          id="edit_precio"
                          type="number"
                          min="0"
                          step="0.01"
                          value={editForm.precio}
                          onChange={(event) => handleEditChange("precio", event.target.value)}
                          className="h-12 rounded-2xl border-border/80 bg-background/90"
                          required
                        />
                      </div>

                      <div className="flex flex-col gap-2">
                        <Label htmlFor="edit_unidad_medida" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">Unidad de Medida</Label>
                        <select
                          id="edit_unidad_medida"
                          value={editForm.unidad_medida}
                          onChange={(event) => handleEditChange("unidad_medida", event.target.value)}
                          className="h-12 rounded-2xl border border-border/80 bg-background/90 px-3 text-sm"
                          required
                        >
                          <option value="">Seleccionar</option>
                          {unitMeasures.map((unit) => (
                            <option key={unit.id_unidad_medida} value={unit.id_unidad_medida}>
                              {unit.descripcion}
                            </option>
                          ))}
                        </select>
                      </div>

                      <div className="flex flex-col gap-2">
                        <Label htmlFor="edit_fecha_cotiz" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">Fecha Cotización</Label>
                        <Input
                          id="edit_fecha_cotiz"
                          type="date"
                          value={editForm.fecha_cotiz}
                          onChange={(event) => handleEditChange("fecha_cotiz", event.target.value)}
                          className="h-12 rounded-2xl border-border/80 bg-background/90"
                          required
                        />
                      </div>

                      <div className="flex flex-col gap-6">
                        <div className="flex flex-col gap-2">
                          <Label htmlFor="edit_tipo" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">Tipo Insumo</Label>
                          <select
                            id="edit_tipo"
                            value={editForm.tipo}
                            onChange={(event) => handleEditChange("tipo", event.target.value)}
                            className="h-12 rounded-2xl border border-border/80 bg-background/90 px-3 text-sm"
                            required
                          >
                            <option value="">Seleccionar</option>
                            {inputTypes.map((type) => (
                              <option key={type.id_tipo} value={type.id_tipo}>
                                {type.descripcion}
                              </option>
                            ))}
                          </select>
                        </div>

                        <div className="flex flex-col gap-2">
                          <Label htmlFor="edit_estado" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">Estado</Label>
                          <select
                            id="edit_estado"
                            value={editForm.estado}
                            onChange={(event) => handleEditChange("estado", event.target.value)}
                            className="h-12 rounded-2xl border border-border/80 bg-background/90 px-3 text-sm"
                          >
                            {statuses.map((status) => (
                              <option key={status.code} value={status.code}>{status.label}</option>
                            ))}
                          </select>
                        </div>
                      </div>

                      <div className="flex flex-col gap-2 md:col-span-2">
                        <Label htmlFor="edit_observacion" className="text-base font-medium text-foreground">Observación</Label>
                        <textarea
                          id="edit_observacion"
                          value={editForm.observacion}
                          onChange={(event) => handleEditChange("observacion", event.target.value)}
                          className="min-h-28 w-full rounded-none border border-border/80 bg-background/90 px-3 py-3 text-base"
                        />
                      </div>
                    </div>

                    <DialogFooter className="mt-2 justify-center gap-2 sm:justify-center">
                      <Button type="button" variant="outline" className="min-w-36 rounded-full" onClick={closeEdit}>
                        Cancelar
                      </Button>
                      <Button type="submit" className="min-w-36 rounded-full bg-emerald-600 text-white hover:bg-emerald-700" disabled={updateMutation.isPending || contextLoading}>
                        {updateMutation.isPending ? (
                          <>
                            <Loader2 className="mr-2 size-4 animate-spin" />
                            Guardando...
                          </>
                        ) : "Guardar"}
                      </Button>
                    </DialogFooter>
                  </form>
                )}
              </CardContent>
            </Card>
          </div>
        </div>,
        document.body,
      )}

      {createOpen && createPortal(
        <div className="fixed inset-0 z-[80] flex justify-end bg-slate-950/20 backdrop-blur-[1px]">
          <div className="w-full max-w-3xl overflow-y-auto border-l border-border/70 bg-background/96 p-4 shadow-[0_0_60px_rgba(15,23,42,0.16)] backdrop-blur xl:p-6">
            <Card className="border border-border/70 bg-white/92 shadow-[0_24px_90px_rgba(15,23,42,0.08)]">
              <CardHeader className="border-b border-border/70 bg-muted/20">
                <div className="flex items-start justify-between gap-4">
                  <div>
                    <CardTitle className="text-2xl tracking-[-0.04em]">Registrar Insumo</CardTitle>
                    <p className="text-sm text-muted-foreground">
                      Completa los datos para registrar un nuevo insumo.
                    </p>
                  </div>

                  <Button variant="ghost" size="icon-sm" className="rounded-full" onClick={closeCreate}>
                    <X />
                  </Button>
                </div>
              </CardHeader>

              <CardContent className="p-5 sm:p-6">
                {contextLoading ? (
                  <div className="flex items-center justify-center p-8">
                    <Loader2 className="size-5 animate-spin" />
                  </div>
                ) : (
                  <>
                    {createError && (
                      <Alert variant="destructive" className="mb-5 rounded-2xl">
                        <AlertDescription>{createError}</AlertDescription>
                      </Alert>
                    )}

                    <form className="flex flex-col gap-5" onSubmit={handleCreateSubmit}>
                      <div className="flex flex-col gap-2">
                        <Label htmlFor="descripcion_nueva" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                          Descripcion
                        </Label>
                        <Input
                          id="descripcion_nueva"
                          value={createForm.descripcion}
                          onChange={(event) => handleCreateChange("descripcion", event.target.value)}
                          className="h-12 rounded-2xl border-border/80 bg-background/90"
                          required
                        />
                      </div>

                      <div className="grid gap-5 sm:grid-cols-2">
                        <div className="flex flex-col gap-2">
                          <Label htmlFor="precio_nuevo" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                            Precio
                          </Label>
                          <Input
                            id="precio_nuevo"
                            type="number"
                            min="0"
                            step="0.01"
                            value={createForm.precio}
                            onChange={(event) => handleCreateChange("precio", event.target.value)}
                            className="h-12 rounded-2xl border-border/80 bg-background/90"
                            required
                          />
                        </div>

                        <div className="flex flex-col gap-2">
                          <Label htmlFor="unidad_nueva" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                            Unidad de Medida
                          </Label>
                          <select
                            id="unidad_nueva"
                            value={createForm.unidad_medida}
                            onChange={(event) => handleCreateChange("unidad_medida", event.target.value)}
                            className="h-12 rounded-2xl border border-border/80 bg-background/90 px-3 text-sm"
                            required
                          >
                            <option value="">Seleccionar</option>
                            {unitMeasures.map((unit) => (
                              <option key={unit.id_unidad_medida} value={unit.id_unidad_medida}>
                                {unit.descripcion} ({unit.abreviatura})
                              </option>
                            ))}
                          </select>
                        </div>

                        <div className="flex flex-col gap-2">
                          <Label htmlFor="tipo_nuevo" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                            Tipo Insumo
                          </Label>
                          <select
                            id="tipo_nuevo"
                            value={createForm.tipo}
                            onChange={(event) => handleCreateChange("tipo", event.target.value)}
                            className="h-12 rounded-2xl border border-border/80 bg-background/90 px-3 text-sm"
                            required
                          >
                            <option value="">Seleccionar</option>
                            {inputTypes.map((type) => (
                              <option key={type.id_tipo} value={type.id_tipo}>
                                {type.descripcion}
                              </option>
                            ))}
                          </select>
                        </div>

                        <div className="flex flex-col gap-2">
                          <Label htmlFor="fecha_cotiz_nueva" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                            Fecha Cotizacion
                          </Label>
                          <Input
                            id="fecha_cotiz_nueva"
                            type="date"
                            value={createForm.fecha_cotiz}
                            onChange={(event) => handleCreateChange("fecha_cotiz", event.target.value)}
                            className="h-12 rounded-2xl border-border/80 bg-background/90"
                            required
                          />
                        </div>

                        <div className="flex flex-col gap-2">
                          <Label htmlFor="observacion_nueva" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                            Observacion
                          </Label>
                          <Input
                            id="observacion_nueva"
                            value={createForm.observacion}
                            onChange={(event) => handleCreateChange("observacion", event.target.value)}
                            className="h-12 rounded-2xl border-border/80 bg-background/90"
                          />
                        </div>

                        <div className="flex flex-col gap-2">
                          <Label htmlFor="estado_nuevo" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                            Estado
                          </Label>
                          <select
                            id="estado_nuevo"
                            value={createForm.estado}
                            onChange={(event) => handleCreateChange("estado", event.target.value)}
                            className="h-12 rounded-2xl border border-border/80 bg-background/90 px-3 text-sm"
                          >
                            {statuses.map((status) => (
                              <option key={status.code} value={status.code}>{status.label}</option>
                            ))}
                          </select>
                        </div>
                      </div>

                      <Separator className="bg-border/70" />

                      <div className="flex flex-wrap justify-end gap-2">
                        <Button type="button" variant="outline" className="rounded-full border-border/70 bg-background/80" onClick={closeCreate}>
                          Cancelar
                        </Button>
                        <Button type="submit" className="rounded-full bg-foreground text-background hover:bg-foreground/90" disabled={createMutation.isPending}>
                          {createMutation.isPending ? (
                            <>
                              <Loader2 className="mr-2 size-4 animate-spin" />
                              Guardando...
                            </>
                          ) : "Guardar cambios"}
                        </Button>
                      </div>
                    </form>
                  </>
                )}
              </CardContent>
            </Card>
          </div>
        </div>,
        document.body,
      )}

      <Dialog open={viewOpen} onOpenChange={setViewOpen}>
        <DialogContent className="max-w-lg rounded-2xl">
          <DialogHeader>
            <DialogTitle>Detalles del Insumo</DialogTitle>
            <DialogDescription>
              ID: {selectedInput?.id_insumo}
            </DialogDescription>
          </DialogHeader>

          {selectedInput && (
            <div className="space-y-3">
              <div className="flex justify-between">
                <span className="text-muted-foreground">Descripción:</span>
                <span className="font-medium">{selectedInput.descripcion}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-muted-foreground">Tipo:</span>
                <span>{selectedInput.nombre_tipo}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-muted-foreground">Precio:</span>
                <span className="font-medium">{Number(selectedInput.precio).toFixed(2)} Bs.</span>
              </div>
              <div className="flex justify-between">
                <span className="text-muted-foreground">Unidad:</span>
                <span>{selectedInput.nombre_unidad_medida} ({selectedInput.abreviatura})</span>
              </div>
              <div className="flex justify-between">
                <span className="text-muted-foreground">Fecha Cotización:</span>
                <span>{selectedInput.fecha_cotiz}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-muted-foreground">Estado:</span>
                <Badge className={`rounded-full px-3 py-1 text-[11px] uppercase tracking-[0.18em] ${statusClass[selectedInput.estado] || "bg-slate-500 text-white"}`}>
                  {selectedInput.estado === "AC" ? "HABILITADO" : "INHABILITADO"}
                </Badge>
              </div>
              {selectedInput.observacion && (
                <div className="pt-2">
                  <span className="text-muted-foreground">Observación:</span>
                  <p className="mt-1 text-sm">{selectedInput.observacion}</p>
                </div>
              )}
            </div>
          )}

          <DialogFooter className="mt-4">
            <Button type="button" variant="outline" className="flex-1" onClick={() => setViewOpen(false)}>
              Cerrar
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}
