import { useEffect, useMemo, useState } from "react";
import { createPortal } from "react-dom";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Loader2, Package, Search, ChevronLeft, ChevronRight, MoreHorizontal, Pencil, Eye, History, Trash2, FileText, X } from "lucide-react";

import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Separator } from "@/components/ui/separator";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { DialogFooter } from "@/components/ui/dialog";
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
  const [deleteOpen, setDeleteOpen] = useState(false);
  const [quoteOpen, setQuoteOpen] = useState(false);
  const [viewOpen, setViewOpen] = useState(false);
  const [selectedInput, setSelectedInput] = useState(null);
  const [createError, setCreateError] = useState(null);
  const [editError, setEditError] = useState(null);
  const [deleteError, setDeleteError] = useState(null);
  const [quoteError, setQuoteError] = useState(null);
  const [viewSearch, setViewSearch] = useState("");
  const [viewPerPage, setViewPerPage] = useState(25);
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

  const { data: quoteHistoryData, isLoading: quoteHistoryLoading } = useQuery({
    queryKey: ["input-quote-history", selectedInput?.id_insumo],
    queryFn: async () => {
      const response = await apiClient.get(`/v1/inputs/${selectedInput.id_insumo}/quotes/history`);
      return response.data;
    },
    enabled: viewOpen && Boolean(selectedInput?.id_insumo),
  });

  const items = data?.data?.items ?? [];
  const meta = data?.data?.meta ?? { current_page: 1, per_page: perPage, total: 0 };
  const totalPages = Math.max(1, Math.ceil((meta.total || 0) / (meta.per_page || perPage)));
  const inputTypes = contextData?.data?.types ?? [];
  const unitMeasures = contextData?.data?.unit_measures ?? [];
  const statuses = contextData?.data?.statuses ?? [];
  const quoteHistoryItems = quoteHistoryData?.data?.items ?? [];

  const filteredQuoteHistory = quoteHistoryItems.filter((quote) => {
    const term = viewSearch.trim().toLowerCase();

    if (!term) {
      return true;
    }

    return [
      quote.fecha,
      quote.archivo,
      quote.archivo1,
      quote.archivo2,
    ].some((value) => String(value ?? "").toLowerCase().includes(term));
  });

  const visibleQuoteHistory = filteredQuoteHistory.slice(0, viewPerPage);

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

  const quoteMutation = useMutation({
    mutationFn: async ({ id, payload }) => {
      const token = localStorage.getItem("token");
      const response = await fetch(`${apiClient.defaults.baseURL}/v1/inputs/${id}/quotes`, {
        method: "POST",
        headers: {
          Accept: "application/json",
          ...(token ? { Authorization: `Bearer ${token}` } : {}),
        },
        body: payload,
      });

      const data = await response.json().catch(() => ({}));

      if (response.status === 401) {
        localStorage.removeItem("token");
        window.location.href = "/login";
        throw new Error("Unauthorized");
      }

      if (!response.ok) {
        const error = new Error(data?.message || "No se pudo registrar la cotización.");
        error.response = { data, status: response.status };
        throw error;
      }

      return data;
    },
    onSuccess: (_, variables) => {
      queryClient.invalidateQueries({ queryKey: ["inputs"] });
      queryClient.invalidateQueries({ queryKey: ["input-quote-history", variables.id] });
      closeQuote();
      alert("Cotización registrada correctamente.");
    },
  });

  const deleteAuthorizationMutation = useMutation({
    mutationFn: (id) => inputsService.requestDeleteAuthorization(id),
    onSuccess: () => {
      closeDelete();
      alert("Solicitud de autorizacion enviada correctamente.");
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

  const handlePerPageChange = (event) => {
    setPerPage(Number(event.target.value));
    setPage(1);
  };

  const handleEdit = (item) => {
    setSelectedInput(item);
    setEditError(null);
    setEditForm({
      descripcion: item.descripcion ?? "",
      precio: item.precio ?? "",
      unidad_medida: String(item.unidad_medida ?? ""),
      tipo: String(item.tipo ?? ""),
      fecha_cotiz: item.fecha_cotiz ?? "",
      observacion: item.observacion ?? "",
      estado: item.estado ?? "AC",
      cod: item.cod ?? "",
    });
    setEditOpen(true);
  };

  const handleView = (item) => {
    setSelectedInput(item);
    setViewSearch("");
    setViewPerPage(25);
    setViewOpen(true);
  };

  const handleOpenQuote = (item) => {
    setSelectedInput(item);
    setQuoteError(null);
    setQuoteOpen(true);
  };

  const handleOpenDelete = (item) => {
    setSelectedInput(item);
    setDeleteError(null);
    setDeleteOpen(true);
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

  const closeDelete = () => {
    setDeleteOpen(false);
    setDeleteError(null);
    setSelectedInput(null);
  };

  const closeQuote = () => {
    setQuoteOpen(false);
    setQuoteError(null);
    setSelectedInput(null);
  };

  const closeView = () => {
    setViewOpen(false);
    setViewSearch("");
    setViewPerPage(25);
    setSelectedInput(null);
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

  const handleQuoteSubmit = async (event) => {
    event.preventDefault();

    if (!selectedInput?.id_insumo) {
      return;
    }

    const formData = new FormData(event.currentTarget);
    const payload = new FormData();
    const valido = formData.get("valido");
    const propuesto1 = formData.get("propuesto_1");
    const propuesto2 = formData.get("propuesto_2");

    if (valido instanceof File && valido.size > 0) {
      payload.append("valido", valido);
    }

    if (propuesto1 instanceof File && propuesto1.size > 0) {
      payload.append("propuesto_1", propuesto1);
    }

    if (propuesto2 instanceof File && propuesto2.size > 0) {
      payload.append("propuesto_2", propuesto2);
    }

    if (![valido, propuesto1, propuesto2].some((file) => file instanceof File && file.size > 0)) {
      setQuoteError("Adjunta al menos un archivo de cotización.");
      return;
    }

    setQuoteError(null);

    try {
      await quoteMutation.mutateAsync({
        id: selectedInput.id_insumo,
        payload,
      });
    } catch (err) {
      const fieldErrors = err.response?.data?.errors;
      const firstFieldError = fieldErrors ? Object.values(fieldErrors).flat().find(Boolean) : null;
      setQuoteError(firstFieldError || err.response?.data?.message || "No se pudo registrar la cotización.");
    }
  };

  const handleRequestDeleteAuthorization = async () => {
    if (!selectedInput?.id_insumo) {
      return;
    }

    setDeleteError(null);

    try {
      await deleteAuthorizationMutation.mutateAsync(selectedInput.id_insumo);
    } catch (err) {
      const fieldErrors = err.response?.data?.errors;
      const firstFieldError = fieldErrors ? Object.values(fieldErrors).flat().find(Boolean) : null;
      setDeleteError(firstFieldError || err.response?.data?.message || "No se pudo solicitar la autorizacion de eliminación.");
    }
  };

  return (
    <div className={`flex flex-col gap-6 animate-in fade-in duration-500 ${createOpen || editOpen || deleteOpen || quoteOpen || viewOpen ? "blur-sm" : ""}`}>
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
                              <DropdownMenuItem className="flex items-center gap-2 rounded-xl px-3 py-2 cursor-pointer" onClick={() => handleOpenQuote(item)}>
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
                              <DropdownMenuItem className="flex items-center gap-2 rounded-xl px-3 py-2 cursor-pointer">
                                <History className="h-4 w-4 text-muted-foreground" />
                                <span>Ver historial de insumo</span>
                              </DropdownMenuItem>
                              <DropdownMenuSeparator className="my-1 bg-border/50" />
                              <DropdownMenuItem className="flex items-center gap-2 rounded-xl px-3 py-2 cursor-pointer" onClick={() => handleOpenDelete(item)}>
                                <Trash2 className="h-4 w-4 text-muted-foreground" />
                                <span>Eliminar</span>
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

      {quoteOpen && createPortal(
        <div className="fixed inset-0 z-[80] flex justify-end bg-slate-950/20 backdrop-blur-[1px]">
          <div className="w-full max-w-4xl overflow-y-auto border-l border-border/70 bg-background/96 p-4 shadow-[0_0_60px_rgba(15,23,42,0.16)] backdrop-blur xl:p-6">
            <Card className="border border-border/70 bg-white/92 shadow-[0_24px_90px_rgba(15,23,42,0.08)]">
              <CardHeader className="border-b border-border/70 bg-muted/20">
                <div className="flex items-start justify-between gap-4">
                  <div>
                    <CardTitle className="text-2xl tracking-[-0.04em]">Adjuntar Cotizacion</CardTitle>
                    <p className="text-sm text-muted-foreground">
                      Adjunta los archivos de cotización del insumo.
                    </p>
                  </div>

                  <Button variant="ghost" size="icon-sm" className="rounded-full" onClick={closeQuote}>
                    <X />
                  </Button>
                </div>
              </CardHeader>

              <CardContent className="p-5 sm:p-6">
                <form className="flex flex-col gap-6" onSubmit={handleQuoteSubmit} encType="multipart/form-data">
                  {quoteError && (
                    <Alert variant="destructive" className="rounded-2xl">
                      <AlertDescription>{quoteError}</AlertDescription>
                    </Alert>
                  )}

                  <div className="flex flex-col gap-2">
                    <Label htmlFor="quote_input_name" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">Insumo</Label>
                    <Input id="quote_input_name" value={selectedInput?.descripcion ?? ""} className="h-12 rounded-2xl border-border/80 bg-background/90" disabled />
                  </div>

                  <div className="grid gap-6 md:grid-cols-3">
                    <div className="flex flex-col gap-2">
                      <Label htmlFor="quote_valido" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">Cotización Válida</Label>
                      <Input id="quote_valido" name="valido" type="file" className="h-12 rounded-2xl border-border/80 bg-background/90 file:mr-4 file:rounded-full file:border-0 file:bg-muted file:px-4 file:py-2" />
                    </div>

                    <div className="flex flex-col gap-2">
                      <Label htmlFor="quote_propuesto_1" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">Cotización Propuesta 1</Label>
                      <Input id="quote_propuesto_1" name="propuesto_1" type="file" className="h-12 rounded-2xl border-border/80 bg-background/90 file:mr-4 file:rounded-full file:border-0 file:bg-muted file:px-4 file:py-2" />
                    </div>

                    <div className="flex flex-col gap-2">
                      <Label htmlFor="quote_propuesto_2" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">Cotización Propuesta 2</Label>
                      <Input id="quote_propuesto_2" name="propuesto_2" type="file" className="h-12 rounded-2xl border-border/80 bg-background/90 file:mr-4 file:rounded-full file:border-0 file:bg-muted file:px-4 file:py-2" />
                    </div>
                  </div>

                  <DialogFooter className="mt-2 justify-center gap-2 sm:justify-center">
                    <Button type="button" variant="outline" className="min-w-36 rounded-full" onClick={closeQuote}>
                      Cancelar
                    </Button>
                    <Button type="submit" className="min-w-36 rounded-full bg-emerald-600 text-white hover:bg-emerald-700" disabled={quoteMutation.isPending}>
                      {quoteMutation.isPending ? (
                        <>
                          <Loader2 className="mr-2 size-4 animate-spin" />
                          Guardando...
                        </>
                      ) : "Guardar"}
                    </Button>
                  </DialogFooter>
                </form>
              </CardContent>
            </Card>
          </div>
        </div>,
        document.body,
      )}

      {deleteOpen && createPortal(
        <div className="fixed inset-0 z-[85] flex items-center justify-center bg-slate-950/20 p-4 backdrop-blur-[1px]">
          <Card className="w-full max-w-xl border border-border/70 bg-white/92 shadow-[0_24px_90px_rgba(15,23,42,0.12)]">
            <CardHeader className="border-b border-border/70 bg-muted/20">
              <div className="flex items-start justify-between gap-4">
                <div>
                  <CardTitle className="text-2xl tracking-[-0.04em]">Eliminar Insumo</CardTitle>
                  <p className="text-sm text-muted-foreground">
                    Solicita una autorización para eliminar el insumo seleccionado.
                  </p>
                </div>

                <Button variant="ghost" size="icon-sm" className="rounded-full" onClick={closeDelete}>
                  <X />
                </Button>
              </div>
            </CardHeader>

            <CardContent className="p-5 sm:p-6">
              <div className="flex flex-col gap-6">
                {deleteError && (
                  <Alert variant="destructive" className="rounded-2xl">
                    <AlertDescription>{deleteError}</AlertDescription>
                  </Alert>
                )}

                <div className="flex flex-col gap-2">
                  <Label htmlFor="delete_input_name" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                    Insumo
                  </Label>
                  <Input
                    id="delete_input_name"
                    value={selectedInput?.descripcion ?? ""}
                    className="h-12 rounded-2xl border-border/80 bg-background/90"
                    disabled
                  />
                </div>

                <p className="text-sm text-muted-foreground">
                  Esta acción enviará una solicitud de autorización para continuar con la eliminación del insumo.
                </p>

                <DialogFooter className="mt-2 justify-center gap-2 sm:justify-center">
                  <Button type="button" variant="outline" className="min-w-36 rounded-full" onClick={closeDelete}>
                    Cancelar
                  </Button>
                  <Button
                    type="button"
                    className="min-w-52 rounded-full bg-rose-600 text-white hover:bg-rose-700"
                    onClick={handleRequestDeleteAuthorization}
                    disabled={deleteAuthorizationMutation.isPending}
                  >
                    {deleteAuthorizationMutation.isPending ? (
                      <>
                        <Loader2 className="mr-2 size-4 animate-spin" />
                        Enviando...
                      </>
                    ) : "Solicitar Autorizacion"}
                  </Button>
                </DialogFooter>
              </div>
            </CardContent>
          </Card>
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

      {viewOpen && createPortal(
        <div className="fixed inset-0 z-[80] flex justify-end bg-slate-950/20 backdrop-blur-[1px]">
          <div className="w-full max-w-5xl overflow-y-auto border-l border-border/70 bg-background/96 p-4 shadow-[0_0_60px_rgba(15,23,42,0.16)] backdrop-blur xl:p-6">
            <Card className="border border-border/70 bg-white/92 shadow-[0_24px_90px_rgba(15,23,42,0.08)]">
              <CardHeader className="border-b border-border/70 bg-muted/20">
                <div className="flex items-start justify-between gap-4">
                  <div>
                    <CardTitle className="text-2xl tracking-[-0.04em]">Ver Cotizacion Actual</CardTitle>
                    <p className="text-sm text-muted-foreground">
                      Consulta las cotizaciones registradas del insumo.
                    </p>
                  </div>

                  <Button variant="ghost" size="icon-sm" className="rounded-full" onClick={closeView}>
                    <X />
                  </Button>
                </div>
              </CardHeader>

              <CardContent className="flex flex-col gap-6 p-5 sm:p-6">
                <div className="flex flex-col gap-2">
                  <Label htmlFor="view_input_name" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">Insumo</Label>
                  <Input id="view_input_name" value={selectedInput?.descripcion ?? ""} className="h-12 rounded-2xl border-border/80 bg-background/90" disabled />
                </div>

                <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                  <div className="flex items-end gap-3">
                    <div className="flex flex-col gap-2">
                      <span className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">Mostrar</span>
                      <div className="relative">
                        <select
                          className="h-12 min-w-32 appearance-none rounded-2xl border border-border/80 bg-background/90 px-4 pr-10 text-sm text-foreground outline-none transition focus:border-foreground/20"
                          value={viewPerPage}
                          onChange={(event) => setViewPerPage(Number(event.target.value))}
                        >
                          <option value={10}>10</option>
                          <option value={25}>25</option>
                          <option value={50}>50</option>
                        </select>
                        <span className="pointer-events-none absolute inset-y-0 right-4 flex items-center text-muted-foreground">▾</span>
                      </div>
                    </div>
                  </div>

                  <div className="flex w-full max-w-sm flex-col gap-2">
                    <span className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">Buscar</span>
                    <div className="relative">
                      <Search className="pointer-events-none absolute left-4 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                      <Input
                        placeholder="Buscar cotización..."
                        className="h-12 rounded-2xl border-border/80 bg-background/90 pl-11"
                        value={viewSearch}
                        onChange={(event) => setViewSearch(event.target.value)}
                      />
                    </div>
                  </div>
                </div>

                <div className="overflow-hidden rounded-[28px] border border-border/70 bg-background/90">
                  <div className="overflow-x-auto">
                    <table className="min-w-full border-collapse text-sm">
                      <thead>
                        <tr className="border-b border-border/70 bg-muted/30 text-left">
                          <th className="px-5 py-4 font-semibold text-foreground">N°</th>
                          <th className="px-5 py-4 font-semibold text-foreground">Fecha</th>
                          <th className="px-5 py-4 font-semibold text-foreground">Propuesta oficial</th>
                          <th className="px-5 py-4 font-semibold text-foreground">Propuesta Alternativa 1</th>
                          <th className="px-5 py-4 font-semibold text-foreground">Propuesta Alternativa 2</th>
                        </tr>
                      </thead>
                      <tbody>
                        {quoteHistoryLoading && (
                          <tr>
                            <td colSpan={5} className="px-5 py-8 text-center text-muted-foreground">
                              <Loader2 className="mr-2 inline size-4 animate-spin" /> Cargando cotizaciones...
                            </td>
                          </tr>
                        )}
                        {!quoteHistoryLoading && visibleQuoteHistory.map((quote, index) => (
                          <tr key={quote.id_cotizacion} className={index < visibleQuoteHistory.length - 1 ? "border-b border-border/60" : ""}>
                            <td className="px-5 py-4 align-top text-foreground">{index + 1}</td>
                            <td className="px-5 py-4 align-top text-muted-foreground">{quote.fecha ?? "-"}</td>
                            <td className="px-5 py-4 align-top">
                              {quote.archivo ? <a href={`/storage/${quote.archivo}`} target="_blank" rel="noreferrer" className="text-emerald-700 hover:underline">Ver archivo</a> : <span className="text-muted-foreground">Sin archivo</span>}
                            </td>
                            <td className="px-5 py-4 align-top">
                              {quote.archivo1 ? <a href={`/storage/${quote.archivo1}`} target="_blank" rel="noreferrer" className="text-emerald-700 hover:underline">Ver archivo</a> : <span className="text-muted-foreground">Sin archivo</span>}
                            </td>
                            <td className="px-5 py-4 align-top">
                              {quote.archivo2 ? <a href={`/storage/${quote.archivo2}`} target="_blank" rel="noreferrer" className="text-emerald-700 hover:underline">Ver archivo</a> : <span className="text-muted-foreground">Sin archivo</span>}
                            </td>
                          </tr>
                        ))}
                        {!quoteHistoryLoading && visibleQuoteHistory.length === 0 && (
                          <tr>
                            <td colSpan={5} className="px-5 py-8 text-center text-muted-foreground">
                              No hay cotizaciones registradas.
                            </td>
                          </tr>
                        )}
                      </tbody>
                    </table>
                  </div>

                  <div className="flex flex-col gap-4 px-5 py-4 text-sm text-muted-foreground lg:flex-row lg:items-center lg:justify-between">
                    <p>Mostrando {visibleQuoteHistory.length} de {filteredQuoteHistory.length} cotizaciones</p>
                    <Button type="button" variant="outline" className="rounded-full" onClick={closeView}>
                      Cerrar
                    </Button>
                  </div>
                </div>
              </CardContent>
            </Card>
          </div>
        </div>,
        document.body,
      )}
    </div>
  );
}
