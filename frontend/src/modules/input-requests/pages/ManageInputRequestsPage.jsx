import { useMemo, useState } from "react";
import { createPortal } from "react-dom";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import {
  ChevronLeft,
  ChevronRight,
  FileText,
  Loader2,
  MapPin,
  Pencil,
  RefreshCcw,
  RotateCcw,
  Search,
  ShieldAlert,
  SquarePen,
  X,
} from "lucide-react";

import { Alert, AlertDescription } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
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
import { Separator } from "@/components/ui/separator";
import { authService } from "@/modules/auth/services/auth.service";
import { manageInputRequestsService } from "@/modules/input-requests/services/manage-input-requests.service";
import { cn } from "@/lib/utils";

const approvalStatusClass = {
  PD: "bg-amber-500 text-white",
  AP: "bg-emerald-600 text-white",
  RC: "bg-rose-600 text-white",
};

const initialManageForm = {
  descripcion: "",
  precio: "",
  unidad_medida: "",
  ubicacion: "",
  solicitante: "",
  justificacion: "",
  notificacion: "",
  estado_aprobacion: "AP",
};

const initialRevertForm = {
  observacion: "",
};

function getRequestId(item) {
  return item?.id || item?.id_solicitud || null;
}

function formatDateInput(date = new Date()) {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");
  return `${year}-${month}-${day}`;
}

function normalizeDecimalInput(value) {
  if (typeof value !== "string") {
    return value;
  }

  return value.replace(",", ".");
}

function getCurrentUserId(profile) {
  return profile?.data?.user?.id
    || profile?.data?.user?.id_usuario
    || profile?.data?.user?.usuario_id
    || profile?.user?.id
    || profile?.user?.id_usuario
    || profile?.id
    || profile?.id_usuario
    || null;
}

function buildManageForm(detail) {
  return {
    descripcion: detail?.descripcion ?? "",
    precio: detail?.precio ?? "",
    unidad_medida: detail?.unidad_medida_id ?? detail?.unidad_medida ?? "",
    ubicacion: detail?.ubicacion ?? "",
    solicitante: detail?.nombre_completo ?? "",
    justificacion: detail?.justificacion ?? "",
    notificacion: detail?.notificacion ?? "",
    estado_aprobacion: "AP",
  };
}

export default function ManageInputRequestsPage() {
  const queryClient = useQueryClient();

  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(10);
  const [searchTerm, setSearchTerm] = useState("");
  const [feedback, setFeedback] = useState(null);
  const [manageModalOpen, setManageModalOpen] = useState(false);
  const [revertModalOpen, setRevertModalOpen] = useState(false);
  const [selectedRequestId, setSelectedRequestId] = useState(null);
  const [isDetailLoading, setIsDetailLoading] = useState(false);
  const [manageForm, setManageForm] = useState(initialManageForm);
  const [revertForm, setRevertForm] = useState(initialRevertForm);
  const [formErrors, setFormErrors] = useState({});

  const profileQuery = useQuery({
    queryKey: ["manage-input-requests-profile"],
    queryFn: authService.getProfile,
    retry: false,
  });

  const contextQuery = useQuery({
    queryKey: ["manage-input-requests-context"],
    queryFn: manageInputRequestsService.context,
  });

  const listQuery = useQuery({
    queryKey: ["manage-input-requests", { page, perPage, searchTerm }],
    queryFn: () => manageInputRequestsService.list({
      page,
      perPage,
      search: searchTerm,
    }),
    placeholderData: (previousData) => previousData,
  });

  const items = useMemo(() => listQuery.data?.data?.items ?? [], [listQuery.data]);
  const meta = listQuery.data?.data?.meta ?? { current_page: 1, per_page: perPage, total: 0, from: 0, to: 0, last_page: 1 };
  const totalPages = Math.max(1, meta.last_page || Math.ceil((meta.total || 0) / (meta.per_page || perPage)));
  const visiblePages = useMemo(() => {
    const start = Math.max(1, meta.current_page - 2);
    const end = Math.min(totalPages, start + 4);
    const first = Math.max(1, end - 4);
    return Array.from({ length: end - first + 1 }, (_, index) => first + index);
  }, [meta.current_page, totalPages]);

  const approvalStatuses = contextQuery.data?.data?.approval_statuses ?? [];
  const unitMeasures = contextQuery.data?.data?.unit_measures ?? [];
  const currentUserId = getCurrentUserId(profileQuery.data);

  const closeManageModal = () => {
    setManageModalOpen(false);
    setSelectedRequestId(null);
    setManageForm(initialManageForm);
    setFormErrors({});
  };

  const closeRevertModal = () => {
    setRevertModalOpen(false);
    setSelectedRequestId(null);
    setRevertForm(initialRevertForm);
    setFormErrors({});
  };

  const openManageModal = async (id) => {
    setFeedback(null);
    setFormErrors({});
    setSelectedRequestId(id);
    setManageModalOpen(true);
    setIsDetailLoading(true);

    try {
      const detailResponse = await queryClient.fetchQuery({
        queryKey: ["input-request-manage-detail", id],
        queryFn: () => manageInputRequestsService.getById(id),
      });

      const detail = detailResponse?.data?.request ?? detailResponse?.data ?? null;
      setManageForm(buildManageForm(detail));
    } catch (error) {
      setFeedback({
        type: "error",
        message: error.response?.data?.message || "No se pudo cargar el detalle de la solicitud.",
      });
      closeManageModal();
    } finally {
      setIsDetailLoading(false);
    }
  };

  const openRevertModal = async (id) => {
    setFeedback(null);
    setFormErrors({});
    setSelectedRequestId(id);
    setRevertModalOpen(true);
    setIsDetailLoading(true);

    try {
      const detailResponse = await queryClient.fetchQuery({
        queryKey: ["input-request-revert-detail", id],
        queryFn: () => manageInputRequestsService.getById(id),
      });

      const detail = detailResponse?.data?.request ?? detailResponse?.data ?? null;
      setRevertForm({ observacion: detail?.observacion ?? "" });
    } catch (error) {
      setFeedback({
        type: "error",
        message: error.response?.data?.message || "No se pudo cargar el detalle de la solicitud.",
      });
      closeRevertModal();
    } finally {
      setIsDetailLoading(false);
    }
  };

  const manageMutation = useMutation({
    mutationFn: ({ id, payload }) => manageInputRequestsService.manage(id, payload),
    onSuccess: () => {
      setFeedback({ type: "success", message: "Solicitud gestionada correctamente." });
      closeManageModal();
      queryClient.invalidateQueries({ queryKey: ["manage-input-requests"] });
    },
    onError: (error) => {
      setFormErrors(error.response?.data?.errors ?? {});
      setFeedback({
        type: "error",
        message: error.response?.data?.message || "No se pudo gestionar la solicitud.",
      });
    },
  });

  const revertMutation = useMutation({
    mutationFn: ({ id, payload }) => manageInputRequestsService.revert(id, payload),
    onSuccess: () => {
      setFeedback({ type: "success", message: "Solicitud revertida correctamente." });
      closeRevertModal();
      queryClient.invalidateQueries({ queryKey: ["manage-input-requests"] });
    },
    onError: (error) => {
      setFormErrors(error.response?.data?.errors ?? {});
      setFeedback({
        type: "error",
        message: error.response?.data?.message || "No se pudo revertir la solicitud.",
      });
    },
  });

  const handleManageChange = (event) => {
    const { id, value } = event.target;
    setManageForm((current) => ({
      ...current,
      [id]: id === "precio" ? normalizeDecimalInput(value) : value,
    }));
    setFormErrors((current) => {
      if (!current[id]) {
        return current;
      }

      const next = { ...current };
      delete next[id];
      return next;
    });
  };

  const handleRevertChange = (event) => {
    const { id, value } = event.target;
    setRevertForm((current) => ({ ...current, [id]: value }));
  };

  const handleManageSubmit = (event) => {
    event.preventDefault();
    setFeedback(null);

    const nextErrors = {};
    if (manageForm.precio === "") nextErrors.precio = ["El precio es obligatorio."];
    if (manageForm.precio !== "" && Number.isNaN(Number(normalizeDecimalInput(manageForm.precio)))) {
      nextErrors.precio = ["El precio debe ser numérico."];
    }
    if (!manageForm.unidad_medida) nextErrors.unidad_medida = ["La unidad de medida es obligatoria."];
    if (!manageForm.estado_aprobacion) nextErrors.estado_aprobacion = ["El estado es obligatorio."];
    if (!manageForm.justificacion.trim()) nextErrors.justificacion = ["La justificación es obligatoria."];
    if (!manageForm.notificacion.trim()) nextErrors.notificacion = ["La notificación es obligatoria."];
    if (!currentUserId) nextErrors.usuario_aprobacion = ["No se pudo identificar al usuario actual."];

    setFormErrors(nextErrors);
    if (Object.keys(nextErrors).length > 0) {
      return;
    }

    manageMutation.mutate({
      id: selectedRequestId,
        payload: {
          estado_aprobacion: manageForm.estado_aprobacion,
          precio: Number(normalizeDecimalInput(manageForm.precio)),
          unidad_medida: Number(manageForm.unidad_medida),
          ubicacion: manageForm.ubicacion.trim(),
          justificacion: manageForm.justificacion.trim(),
          notificacion: manageForm.notificacion.trim(),
          usuario_aprobacion: currentUserId,
          fecha_aprobacion: formatDateInput(),
        },
    });
  };

  const handleRevertSubmit = (event) => {
    event.preventDefault();
    setFeedback(null);

    const nextErrors = {};
    if (!revertForm.observacion.trim()) nextErrors.observacion = ["La observación es obligatoria."];
    if (!currentUserId) nextErrors.usuario_rev = ["No se pudo identificar al usuario actual."];

    setFormErrors(nextErrors);
    if (Object.keys(nextErrors).length > 0) {
      return;
    }

    revertMutation.mutate({
      id: selectedRequestId,
      payload: {
        observacion: revertForm.observacion.trim(),
        usuario_rev: currentUserId,
        fecha_rev: formatDateInput(),
      },
    });
  };

  const isManaging = manageMutation.isPending;
  const isReverting = revertMutation.isPending;

  return (
    <div className="flex flex-col gap-6 animate-in fade-in duration-500">
      <Card className="border border-border/70 bg-white/86 shadow-[0_24px_90px_rgba(15,23,42,0.08)] backdrop-blur">
        <CardHeader className="gap-4 border-b border-border/70 bg-muted/25">
          <div className="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
            <div className="flex items-center gap-3">
              <div className="flex size-11 items-center justify-center rounded-2xl bg-sky-600 text-white">
                <FileText className="size-5" />
              </div>
              <div>
                <CardTitle className="text-2xl tracking-[-0.04em]">Gestionar Solicitud</CardTitle>
                <CardDescription>
                  Revisión y gestión de solicitudes de insumo pendientes, aprobadas y rechazadas.
                </CardDescription>
              </div>
            </div>

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

          <div className="grid gap-4 xl:grid-cols-[minmax(0,1fr)_140px] xl:items-end">
            <div className="flex flex-col gap-2">
              <Label htmlFor="searchTerm" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                Buscador
              </Label>
              <div className="relative">
                <Search className="pointer-events-none absolute left-4 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                  id="searchTerm"
                  placeholder="Buscar por descripción, solicitante o ubicación"
                  className="h-12 rounded-2xl border-border/80 bg-background/90 pl-11"
                  value={searchTerm}
                  onChange={(event) => {
                    setSearchTerm(event.target.value);
                    setPage(1);
                  }}
                />
              </div>
            </div>

            <div className="flex flex-col gap-2">
              <Label className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                Mostrar
              </Label>
              <select
                className="h-12 rounded-2xl border border-border/80 bg-background/90 px-4 text-sm text-foreground outline-none"
                value={perPage}
                onChange={(event) => {
                  setPerPage(Number(event.target.value));
                  setPage(1);
                }}
              >
                <option value={10}>10</option>
                <option value={25}>25</option>
                <option value={50}>50</option>
              </select>
            </div>

            {listQuery.isFetching && !listQuery.isLoading && (
              <div className="xl:col-span-2 flex items-center gap-2 rounded-2xl border border-border/70 bg-background/80 px-4 py-3 text-sm text-muted-foreground">
                <Loader2 className="size-4 animate-spin" />
                Buscando solicitudes...
              </div>
            )}
          </div>

          {listQuery.isLoading && (
            <div className="flex items-center justify-center rounded-2xl border border-border/70 bg-background/70 p-8 text-muted-foreground">
              <Loader2 className="mr-2 size-4 animate-spin" /> Cargando solicitudes para gestión...
            </div>
          )}

          {listQuery.isError && (
            <Alert variant="destructive" className="rounded-2xl">
              <AlertDescription>
                {listQuery.error?.response?.data?.message || "No se pudieron cargar las solicitudes para gestión."}
              </AlertDescription>
            </Alert>
          )}

          {!listQuery.isLoading && !listQuery.isError && (
            <>
              <div className="hidden overflow-hidden rounded-[28px] border border-border/70 bg-background/90 xl:block">
                <div className="overflow-x-auto">
                  <table className={cn("min-w-full border-collapse text-sm transition-opacity", listQuery.isFetching && "opacity-60")}>
                    <thead>
                      <tr className="border-b border-border/70 bg-muted/30 text-left">
                        <th className="px-4 py-4 font-semibold text-foreground">N°</th>
                        <th className="px-4 py-4 font-semibold text-foreground">Descripción</th>
                        <th className="px-4 py-4 font-semibold text-foreground">Precio</th>
                        <th className="px-4 py-4 font-semibold text-foreground">Unidad de Medida</th>
                        <th className="px-4 py-4 font-semibold text-foreground">Fecha</th>
                        <th className="px-4 py-4 font-semibold text-foreground">Estado</th>
                        <th className="px-4 py-4 font-semibold text-foreground">Solicitante</th>
                        <th className="px-4 py-4 font-semibold text-foreground">Ubicación</th>
                        <th className="px-4 py-4 font-semibold text-foreground">Justificación</th>
                        <th className="px-4 py-4 font-semibold text-foreground">Notificación</th>
                        <th className="px-4 py-4 font-semibold text-foreground text-right">Opciones</th>
                      </tr>
                    </thead>
                    <tbody>
                      {items.map((item, index) => (
                        <tr key={getRequestId(item) ?? `${item.descripcion}-${index}`} className={index < items.length - 1 ? "border-b border-border/60" : ""}>
                          <td className="px-4 py-4 align-top text-foreground">{(meta.from || 1) + index}</td>
                          <td className="px-4 py-4 align-top text-foreground whitespace-pre-line">{item.descripcion || "-"}</td>
                          <td className="px-4 py-4 align-top text-muted-foreground">{item.precio ?? "-"}</td>
                          <td className="px-4 py-4 align-top text-muted-foreground">{item.nombre_unidad_medida || "-"}</td>
                          <td className="px-4 py-4 align-top text-muted-foreground">{item.fecha || "-"}</td>
                          <td className="px-4 py-4 align-top">
                            <Badge className={`rounded-full px-3 py-1 text-[11px] uppercase tracking-[0.18em] ${approvalStatusClass[item.approval_status] || "bg-slate-500 text-white"}`}>
                              {item.approval_status_label || item.estado_aprobacion || "-"}
                            </Badge>
                          </td>
                          <td className="px-4 py-4 align-top text-foreground">{item.nombre_completo || "-"}</td>
                          <td className="px-4 py-4 align-top text-muted-foreground">{item.ubicacion || "-"}</td>
                          <td className="px-4 py-4 align-top text-muted-foreground">{item.justificacion || "-"}</td>
                          <td className="px-4 py-4 align-top text-muted-foreground">{item.notificacion || "-"}</td>
                          <td className="px-4 py-4 align-top text-right">
                            <div className="flex justify-end gap-1.5">
                              {item.available_actions?.gestionar && (
                                <Button
                                  size="icon-sm"
                                  className="size-9 rounded-lg bg-[#209c8d] text-white hover:bg-[#1a8578] shadow-sm transition-colors"
                                  onClick={() => openManageModal(getRequestId(item))}
                                  title="Editar gestión"
                                >
                                  <SquarePen className="size-4" />
                                </Button>
                              )}
                              {item.available_actions?.revertir && (
                                <Button
                                  size="icon-sm"
                                  className="size-9 rounded-lg bg-[#337ab7] text-white hover:bg-[#286090] shadow-sm transition-colors"
                                  onClick={() => openRevertModal(getRequestId(item))}
                                  title="Revertir"
                                >
                                  <RotateCcw className="size-4" />
                                </Button>
                              )}
                            </div>
                          </td>
                        </tr>
                      ))}
                      {items.length === 0 && (
                        <tr>
                          <td colSpan={11} className="px-4 py-8 text-center text-muted-foreground">
                            No se encontraron solicitudes para gestión.
                          </td>
                        </tr>
                      )}
                    </tbody>
                  </table>
                </div>
              </div>

              <div className={cn("grid gap-4 xl:hidden", listQuery.isFetching && "opacity-60")}>
                {items.length === 0 ? (
                  <Card className="border border-border/70 bg-background/90">
                    <CardContent className="p-6 text-center text-muted-foreground">
                      No se encontraron solicitudes para gestión.
                    </CardContent>
                  </Card>
                ) : items.map((item, index) => (
                  <Card key={getRequestId(item) ?? `${item.descripcion}-${index}`} className="border border-border/70 bg-background/90">
                    <CardContent className="flex flex-col gap-4 p-5">
                      <div className="flex items-start justify-between gap-3">
                        <div>
                          <p className="font-medium text-foreground whitespace-pre-line">{item.descripcion || "-"}</p>
                          <p className="text-sm text-muted-foreground">{item.nombre_unidad_medida || "-"}</p>
                        </div>
                        <Badge className={`rounded-full px-3 py-1 text-[11px] uppercase tracking-[0.18em] ${approvalStatusClass[item.approval_status] || "bg-slate-500 text-white"}`}>
                          {item.approval_status_label || item.estado_aprobacion || "-"}
                        </Badge>
                      </div>
                      <div className="grid gap-2 text-sm text-muted-foreground">
                        <p><span className="font-medium text-foreground">Precio:</span> {item.precio ?? "-"}</p>
                        <p><span className="font-medium text-foreground">Fecha:</span> {item.fecha || "-"}</p>
                        <p><span className="font-medium text-foreground">Solicitante:</span> {item.nombre_completo || "-"}</p>
                        <p><span className="font-medium text-foreground">Ubicación:</span> {item.ubicacion || "-"}</p>
                        <p><span className="font-medium text-foreground">Justificación:</span> {item.justificacion || "-"}</p>
                        <p><span className="font-medium text-foreground">Notificación:</span> {item.notificacion || "-"}</p>
                      </div>
                      <div className="flex flex-wrap gap-1.5">
                        {item.available_actions?.gestionar && (
                          <Button
                            size="icon-sm"
                            className="size-9 rounded-lg bg-[#209c8d] text-white hover:bg-[#1a8578] shadow-sm transition-colors"
                            onClick={() => openManageModal(getRequestId(item))}
                          >
                            <SquarePen className="size-4" />
                          </Button>
                        )}
                        {item.available_actions?.revertir && (
                          <Button
                            size="icon-sm"
                            className="size-9 rounded-lg bg-[#337ab7] text-white hover:bg-[#286090] shadow-sm transition-colors"
                            onClick={() => openRevertModal(getRequestId(item))}
                          >
                            <RotateCcw className="size-4" />
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

      {manageModalOpen && createPortal(
        <div className="fixed inset-0 z-[80] flex justify-end bg-slate-950/20 backdrop-blur-[1px]">
          <div className="w-full max-w-3xl overflow-y-auto border-l border-border/70 bg-background/96 p-4 shadow-[0_0_60px_rgba(15,23,42,0.16)] backdrop-blur xl:p-6">
            <Card className="border border-border/70 bg-white/92 shadow-[0_24px_90px_rgba(15,23,42,0.08)]">
              <CardHeader className="border-b border-border/70 bg-muted/20">
                <div className="flex items-start justify-between gap-4">
                  <div>
                    <CardTitle className="text-2xl font-bold tracking-tight text-slate-700">Aprobar/Rechazar Solicitud</CardTitle>
                  </div>
                  <Button variant="ghost" size="icon-sm" className="rounded-full" onClick={closeManageModal}>
                    <X />
                  </Button>
                </div>
              </CardHeader>

              <CardContent className="p-5 sm:p-6">
                {isDetailLoading ? (
                  <div className="flex items-center justify-center rounded-2xl border border-border/70 bg-background/70 p-8 text-muted-foreground">
                    <Loader2 className="mr-2 size-4 animate-spin" /> Cargando detalle de la solicitud...
                  </div>
                ) : (
                  <form className="flex flex-col gap-5" onSubmit={handleManageSubmit}>
                    <div className="flex flex-col gap-5">
                      <div className="flex flex-col gap-1.5">
                        <Label htmlFor="descripcion" className="text-sm font-semibold text-slate-700">
                          Descripción
                        </Label>
                        <textarea
                          id="descripcion"
                          value={manageForm.descripcion}
                          readOnly
                          className="min-h-24 rounded-lg border border-border/80 bg-muted/20 px-4 py-3 text-sm text-muted-foreground outline-none cursor-not-allowed"
                        />
                      </div>

                      <div className="flex flex-col gap-1.5">
                        <Label htmlFor="precio" className="text-sm font-semibold text-slate-700">
                          Precio
                        </Label>
                        <Input id="precio" type="number" step="0.01" value={manageForm.precio} onChange={handleManageChange} className="h-12 rounded-lg border-border/80 bg-background/90" />
                        {formErrors.precio && <p className="text-sm text-destructive">{formErrors.precio[0]}</p>}
                      </div>

                      <div className="flex flex-col gap-1.5">
                        <Label htmlFor="unidad_medida" className="text-sm font-semibold text-slate-700">
                          Unidad de Medida
                        </Label>
                        <select
                          id="unidad_medida"
                          value={manageForm.unidad_medida}
                          onChange={handleManageChange}
                          className="h-12 rounded-lg border border-border/80 bg-background/90 px-4 text-sm text-foreground outline-none"
                        >
                          <option value="">Seleccionar unidad de medida</option>
                          {unitMeasures.map((unit) => (
                            <option key={unit.id_unidad_medida} value={unit.id_unidad_medida}>
                              {unit.descripcion}{unit.abreviatura ? ` (${unit.abreviatura})` : ""}
                            </option>
                          ))}
                        </select>
                        {formErrors.unidad_medida && <p className="text-sm text-destructive">{formErrors.unidad_medida[0]}</p>}
                      </div>

                      <div className="flex flex-col gap-1.5">
                        <Label htmlFor="ubicacion" className="text-sm font-semibold text-slate-700">
                          Ubicación
                        </Label>
                        <div className="relative">
                          <MapPin className="absolute left-4 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                          <Input id="ubicacion" value={manageForm.ubicacion} onChange={handleManageChange} className="h-12 rounded-lg border-border/80 bg-background/90 pl-11" />
                        </div>
                      </div>

                      <div className="flex flex-col gap-1.5">
                        <Label htmlFor="solicitante" className="text-sm font-semibold text-slate-700">
                          Solicitante
                        </Label>
                        <Input id="solicitante" value={manageForm.solicitante} readOnly className="h-12 rounded-lg border-border/80 bg-muted/20 text-muted-foreground cursor-not-allowed" />
                      </div>

                      <div className="flex flex-col gap-1.5">
                        <Label htmlFor="justificacion" className="text-sm font-semibold text-slate-700">
                          Justificación
                        </Label>
                        <textarea id="justificacion" value={manageForm.justificacion} onChange={handleManageChange} className="min-h-28 rounded-lg border border-border/80 bg-background/90 px-4 py-3 text-sm text-foreground outline-none" />
                        {formErrors.justificacion && <p className="text-sm text-destructive">{formErrors.justificacion[0]}</p>}
                      </div>

                      <div className="flex flex-col gap-1.5">
                        <Label htmlFor="notificacion" className="text-sm font-semibold text-slate-700">
                          Detalle la razón por la cual se esta aprobando o rechazando la solicitud
                        </Label>
                        <textarea id="notificacion" value={manageForm.notificacion} onChange={handleManageChange} className="min-h-24 rounded-lg border border-border/80 bg-background/90 px-4 py-3 text-sm text-foreground outline-none" />
                        {formErrors.notificacion && <p className="text-sm text-destructive">{formErrors.notificacion[0]}</p>}
                      </div>

                      <div className="flex flex-col gap-1.5">
                        <Label htmlFor="estado_aprobacion" className="text-sm font-semibold text-slate-700">
                          Estado
                        </Label>
                        <select id="estado_aprobacion" value={manageForm.estado_aprobacion} onChange={handleManageChange} className="h-12 rounded-lg border border-border/80 bg-background/90 px-4 text-sm text-foreground outline-none">
                          {approvalStatuses
                            .filter((status) => status.code === "AP" || status.code === "RC")
                            .map((status) => (
                              <option key={status.code} value={status.code}>{status.label}</option>
                            ))}
                        </select>
                        {formErrors.estado_aprobacion && <p className="text-sm text-destructive">{formErrors.estado_aprobacion[0]}</p>}
                      </div>
                    </div>

                    {(formErrors.usuario_aprobacion || profileQuery.isError) && (
                      <Alert variant="destructive" className="rounded-2xl">
                        <AlertDescription>
                          {formErrors.usuario_aprobacion?.[0] || "No se pudo obtener el usuario autenticado para registrar la aprobación."}
                        </AlertDescription>
                      </Alert>
                    )}

                    <Separator className="bg-border/70" />

                    <div className="flex justify-center">
                      <Button type="submit" className="h-12 w-full max-w-xs rounded-lg bg-[#209c8d] text-white hover:bg-[#1a8578] shadow-sm transition-colors" disabled={isManaging}>
                        {isManaging && <Loader2 data-icon="inline-start" className="animate-spin" />}
                        Guardar Cambios
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

      {revertModalOpen && createPortal(
        <div className="fixed inset-0 z-[80] flex justify-end bg-slate-950/20 backdrop-blur-[1px]">
          <div className="w-full max-w-2xl overflow-y-auto border-l border-border/70 bg-background/96 p-4 shadow-[0_0_60px_rgba(15,23,42,0.16)] backdrop-blur xl:p-6">
            <Card className="border border-border/70 bg-white/92 shadow-[0_24px_90px_rgba(15,23,42,0.08)]">
              <CardHeader className="border-b border-border/70 bg-muted/20">
                <div className="flex items-start justify-between gap-4">
                  <div>
                    <CardTitle className="text-2xl font-bold tracking-tight text-slate-700">Revertir solicitud</CardTitle>
                    <CardDescription className="hidden">
                      Devuelve la solicitud a estado pendiente y registra el motivo de la reversión.
                    </CardDescription>
                  </div>
                  <Button variant="ghost" size="icon-sm" className="rounded-full" onClick={closeRevertModal}>
                    <X />
                  </Button>
                </div>
              </CardHeader>

              <CardContent className="p-5 sm:p-6">
                {isDetailLoading ? (
                  <div className="flex items-center justify-center rounded-2xl border border-border/70 bg-background/70 p-8 text-muted-foreground">
                    <Loader2 className="mr-2 size-4 animate-spin" /> Cargando detalle de la solicitud...
                  </div>
                ) : (
                  <form className="flex flex-col gap-5" onSubmit={handleRevertSubmit}>
                    <div className="flex flex-col gap-1.5">
                      <Label htmlFor="observacion" className="text-sm font-semibold text-slate-700">
                        Observación de reversión
                      </Label>
                      <div className="relative">
                        <ShieldAlert className="absolute left-4 top-4 size-4 text-muted-foreground" />
                        <textarea id="observacion" value={revertForm.observacion} onChange={handleRevertChange} className="min-h-32 w-full rounded-lg border border-border/80 bg-background/90 px-11 py-3 text-sm text-foreground outline-none" placeholder="Describe el motivo de la reversión" />
                      </div>
                      {formErrors.observacion && <p className="text-sm text-destructive">{formErrors.observacion[0]}</p>}
                    </div>

                    {(formErrors.usuario_rev || profileQuery.isError) && (
                      <Alert variant="destructive" className="rounded-2xl">
                        <AlertDescription>
                          {formErrors.usuario_rev?.[0] || "No se pudo obtener el usuario autenticado para registrar la reversión."}
                        </AlertDescription>
                      </Alert>
                    )}

                    <Separator className="bg-border/70" />

                    <div className="flex justify-center">
                      <Button type="submit" className="h-12 w-full max-w-xs rounded-lg bg-[#209c8d] text-white hover:bg-[#1a8578] shadow-sm transition-colors" disabled={isReverting}>
                        {isReverting && <Loader2 data-icon="inline-start" className="animate-spin" />}
                        Confirmar reversión
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
