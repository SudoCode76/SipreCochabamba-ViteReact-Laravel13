import { useDeferredValue, useEffect, useMemo, useState } from "react";
import { createPortal } from "react-dom";
import {
  ChevronLeft,
  ChevronRight,
  Loader2,
  MoreHorizontal,
  RefreshCcw,
  Search,
  ShieldCheck,
  X,
} from "lucide-react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";

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
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Separator } from "@/components/ui/separator";
import { cn } from "@/lib/utils";
import { authorizationsService } from "@/modules/authorizations/services/authorizations.service";

const statusClassMap = {
  PE: "bg-amber-500 text-black",
  AP: "bg-emerald-600 text-white",
  NP: "bg-slate-900 text-white",
};

const initialProcessForm = {
  status: "",
};

function buildProcessSuccessMessage(response) {
  const authorization = response?.data?.authorization;
  const statusLabel = authorization?.status_label;
  const authorizationNumber = authorization?.authorization_number;

  if (statusLabel === "AUTORIZADO") {
    return authorizationNumber
      ? `Autorizacion aprobada correctamente. N° ${authorizationNumber}.`
      : "Autorizacion aprobada correctamente.";
  }

  if (statusLabel === "NO PROCEDE") {
    return "Autorizacion marcada como no procede correctamente.";
  }

  return response?.message || "Autorizacion procesada correctamente.";
}

export default function AuthorizationsPage() {
  const queryClient = useQueryClient();

  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(10);
  const [searchTerm, setSearchTerm] = useState("");
  const [statusFilter, setStatusFilter] = useState("");
  const [moduleFilter, setModuleFilter] = useState("");
  const [isProcessOpen, setIsProcessOpen] = useState(false);
  const [selectedAuthorizationId, setSelectedAuthorizationId] = useState(null);
  const [isDetailLoading, setIsDetailLoading] = useState(false);
  const [processForm, setProcessForm] = useState(initialProcessForm);
  const [formErrors, setFormErrors] = useState({});
  const [feedback, setFeedback] = useState(null);

  const deferredSearchTerm = useDeferredValue(searchTerm.trim());

  useEffect(() => {
    if (!feedback) {
      return undefined;
    }

    const timeoutId = window.setTimeout(() => {
      setFeedback(null);
    }, 4000);

    return () => window.clearTimeout(timeoutId);
  }, [feedback]);

  const contextQuery = useQuery({
    queryKey: ["authorizations-context"],
    queryFn: authorizationsService.context,
  });

  const listQuery = useQuery({
    queryKey: ["authorizations", { page, perPage, deferredSearchTerm, statusFilter, moduleFilter }],
    queryFn: () => authorizationsService.list({
      page,
      perPage,
      search: deferredSearchTerm,
      status: statusFilter,
      module: moduleFilter,
    }),
    placeholderData: (previousData) => previousData,
  });

  const detailQuery = useQuery({
    queryKey: ["authorization-detail", selectedAuthorizationId],
    queryFn: () => authorizationsService.getById(selectedAuthorizationId),
    enabled: selectedAuthorizationId !== null,
  });

  const impactQuery = useQuery({
    queryKey: ["authorization-impact", selectedAuthorizationId],
    queryFn: () => authorizationsService.impact(selectedAuthorizationId),
    enabled: isProcessOpen && selectedAuthorizationId !== null,
    retry: false,
  });

  const authorizations = useMemo(() => listQuery.data?.data?.items ?? [], [listQuery.data]);
  const meta = listQuery.data?.data?.meta ?? { current_page: 1, per_page: perPage, total: 0, from: 0, to: 0, last_page: 1 };
  const statuses = contextQuery.data?.data?.statuses ?? [];
  const processableStatuses = contextQuery.data?.data?.processable_statuses ?? [];
  const modules = contextQuery.data?.data?.modules ?? [];

  const totalPages = Math.max(1, meta.last_page || Math.ceil((meta.total || 0) / (meta.per_page || perPage)));
  const visiblePages = useMemo(() => {
    const start = Math.max(1, meta.current_page - 2);
    const end = Math.min(totalPages, start + 4);
    const first = Math.max(1, end - 4);
    return Array.from({ length: end - first + 1 }, (_, index) => first + index);
  }, [meta.current_page, totalPages]);

  const resetProcessForm = () => {
    setProcessForm(initialProcessForm);
    setFormErrors({});
    setSelectedAuthorizationId(null);
  };

  const closeProcess = () => {
    setIsProcessOpen(false);
    resetProcessForm();
  };

  const openProcess = async (authorizationId) => {
    setFormErrors({});
    setSelectedAuthorizationId(authorizationId);
    setIsProcessOpen(true);
    setIsDetailLoading(true);

    try {
      const detail = await queryClient.fetchQuery({
        queryKey: ["authorization-detail", authorizationId],
        queryFn: () => authorizationsService.getById(authorizationId),
      });

        const authorization = detail?.data?.authorization;
        if (authorization) {
          setProcessForm({
            status: authorization.status === "PE" ? "" : authorization.status_label,
          });
        }
    } catch (error) {
      setFeedback({
        type: "error",
        message: error.response?.data?.message || "No se pudo cargar el detalle de la autorización.",
      });
      closeProcess();
    } finally {
      setIsDetailLoading(false);
    }
  };

  const processMutation = useMutation({
    mutationFn: ({ authorizationId, payload }) => authorizationsService.updateStatus(authorizationId, payload),
    onSuccess: (response) => {
      setFeedback({ type: "success", message: buildProcessSuccessMessage(response) });
      closeProcess();
      queryClient.invalidateQueries({ queryKey: ["authorizations"] });
      queryClient.invalidateQueries({ queryKey: ["authorization-detail"] });
      queryClient.invalidateQueries({ queryKey: ["authorization-impact"] });
      queryClient.invalidateQueries({ queryKey: ["inputs"] });
      queryClient.invalidateQueries({ queryKey: ["input-delete-impact"] });
    },
    onError: (error) => {
      setFormErrors(error.response?.data?.errors ?? {});
      setFeedback({
        type: "error",
        message: error.response?.status === 403
          ? "Acceso denegado para procesar autorizaciones."
          : error.response?.data?.message || "No se pudo procesar la autorización.",
      });
    },
  });

  const handlePerPageChange = (event) => {
    setPerPage(Number(event.target.value));
    setPage(1);
  };

  const handleProcessChange = (event) => {
    const { id, value } = event.target;
    setProcessForm((current) => ({ ...current, [id]: value }));
  };

  const handleSubmitProcess = (event) => {
    event.preventDefault();
    setFeedback(null);

    const nextErrors = {};

    if (!processForm.status) {
      nextErrors.status = ["Selecciona un estado para procesar la autorización."];
    }

    setFormErrors(nextErrors);

    if (Object.keys(nextErrors).length > 0) {
      return;
    }

    const payload = {
      status: processForm.status,
    };

    processMutation.mutate({ authorizationId: selectedAuthorizationId, payload });
  };

  const detailAuthorization = detailQuery.data?.data?.authorization ?? null;
  const authorizationImpact = impactQuery.data?.data ?? null;
  const impactedItems = authorizationImpact?.items ?? [];
  const impactedProjects = authorizationImpact?.pending_projects ?? [];
  const impactSummary = authorizationImpact?.summary ?? { items_count: 0, pending_projects_count: 0 };

  return (
    <div className="flex flex-col gap-6 animate-in fade-in duration-500">
      <Card className="border border-border/70 bg-white/86 shadow-[0_24px_90px_rgba(15,23,42,0.08)] backdrop-blur">
        <CardHeader className="gap-4 border-b border-border/70 bg-muted/25">
          <div className="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
            <div className="flex items-center gap-3">
              <div className="flex size-11 items-center justify-center rounded-2xl bg-foreground text-background">
                <ShieldCheck className="size-5" />
              </div>
              <div>
                <CardTitle className="text-2xl tracking-[-0.04em]">Administración de autorizaciones</CardTitle>
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

          <div className="grid gap-4 xl:grid-cols-[minmax(0,1fr)_minmax(220px,0.38fr)_minmax(220px,0.38fr)_minmax(140px,0.2fr)] xl:items-end">
            <div className="flex flex-col gap-2">
              <Label htmlFor="searchTerm" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                Buscador
              </Label>
              <div className="relative">
                <Search className="pointer-events-none absolute left-4 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                  id="searchTerm"
                  placeholder="Buscar elemento, módulo, solicitante o N° autorización"
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
              <Label htmlFor="moduleFilter" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                Módulo
              </Label>
              <select
                id="moduleFilter"
                className="h-12 rounded-2xl border border-border/80 bg-background/90 px-4 text-sm text-foreground outline-none"
                value={moduleFilter}
                onChange={(event) => {
                  setModuleFilter(event.target.value);
                  setPage(1);
                }}
              >
                <option value="">Todos</option>
                {modules.map((module) => (
                  <option key={module.value} value={module.value}>{module.label}</option>
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
          </div>

          {listQuery.isLoading && (
            <div className="flex items-center justify-center rounded-2xl border border-border/70 bg-background/70 p-8 text-muted-foreground">
              <Loader2 className="mr-2 size-4 animate-spin" /> Cargando autorizaciones...
            </div>
          )}

          {listQuery.isError && (
            <Alert variant="destructive" className="rounded-2xl">
              <AlertDescription>
                {listQuery.error?.response?.status === 403
                  ? "No tienes permisos para acceder a la administración de autorizaciones."
                  : listQuery.error?.response?.data?.message || "No se pudieron cargar las autorizaciones."}
              </AlertDescription>
            </Alert>
          )}

          {!listQuery.isLoading && !listQuery.isError && (
            <>
              <div className="hidden overflow-hidden rounded-[28px] border border-border/70 bg-background/90 lg:block">
                <div className="overflow-x-auto">
                  <table className="min-w-full border-collapse text-sm">
                    <thead>
                      <tr className="border-b border-border/70 bg-muted/30 text-left">
                        <th className="px-5 py-4 font-semibold text-foreground">N°</th>
                        <th className="px-5 py-4 font-semibold text-foreground">Fecha</th>
                        <th className="px-5 py-4 font-semibold text-foreground">Elemento</th>
                        <th className="px-5 py-4 font-semibold text-foreground">Módulo</th>
                        <th className="px-5 py-4 font-semibold text-foreground">Solicitante</th>
                        <th className="px-5 py-4 font-semibold text-foreground">N° autorización</th>
                        <th className="px-5 py-4 font-semibold text-foreground">Estado</th>
                        <th className="px-5 py-4 font-semibold text-foreground text-right">Opciones</th>
                      </tr>
                    </thead>
                    <tbody>
                      {authorizations.map((item, index) => (
                        <tr key={item.id} className={index < authorizations.length - 1 ? "border-b border-border/60" : ""}>
                          <td className="px-5 py-4 align-top text-foreground">{(meta.from || 1) + index}</td>
                          <td className="px-5 py-4 align-top text-muted-foreground whitespace-nowrap">{item.date || "-"}</td>
                          <td className="px-5 py-4 align-top text-foreground">{item.element}</td>
                          <td className="px-5 py-4 align-top text-muted-foreground uppercase">{item.module}</td>
                          <td className="px-5 py-4 align-top text-muted-foreground">{item.requester || "-"}</td>
                          <td className="px-5 py-4 align-top text-muted-foreground">{item.authorization_number || "-"}</td>
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
                                  {(item.available_actions?.process || item.available_actions?.view) && (
                                    <DropdownMenuItem className="rounded-xl px-3 py-2 cursor-pointer" onClick={() => openProcess(item.id)}>
                                      <ShieldCheck className="mr-2 size-4" />
                                      {item.available_actions?.process ? "Procesar autorización" : "Ver autorización"}
                                    </DropdownMenuItem>
                                  )}
                                </DropdownMenuContent>
                              </DropdownMenu>
                            </div>
                          </td>
                        </tr>
                      ))}
                      {authorizations.length === 0 && (
                        <tr>
                          <td colSpan={8} className="px-5 py-8 text-center text-muted-foreground">
                            No se encontraron autorizaciones para los filtros seleccionados.
                          </td>
                        </tr>
                      )}
                    </tbody>
                  </table>
                </div>
              </div>

              <div className="grid gap-4 lg:hidden">
                {authorizations.length === 0 ? (
                  <Card className="border border-border/70 bg-background/90">
                    <CardContent className="p-6 text-center text-muted-foreground">
                      No se encontraron autorizaciones para los filtros seleccionados.
                    </CardContent>
                  </Card>
                ) : authorizations.map((item) => (
                  <Card key={item.id} className="border border-border/70 bg-background/90">
                    <CardContent className="flex flex-col gap-4 p-5">
                      <div className="flex items-start justify-between gap-3">
                        <div>
                          <p className="font-medium text-foreground">{item.element}</p>
                          <p className="text-sm text-muted-foreground">{item.requester || "-"}</p>
                        </div>
                        <Badge className={`rounded-full px-3 py-1 text-[11px] uppercase tracking-[0.18em] ${statusClassMap[item.status] || "bg-slate-500 text-white"}`}>
                          {item.status_label}
                        </Badge>
                      </div>
                      <div className="grid gap-2 text-sm text-muted-foreground">
                        <p><span className="font-medium text-foreground">Fecha:</span> {item.date || "-"}</p>
                        <p><span className="font-medium text-foreground">Módulo:</span> {item.module}</p>
                        <p><span className="font-medium text-foreground">N° autorización:</span> {item.authorization_number || "-"}</p>
                      </div>
                      <div className="flex gap-2">
                        <Button variant="outline" className="rounded-full border-border/70" onClick={() => openProcess(item.id)}>
                          <ShieldCheck data-icon="inline-start" />
                          {item.available_actions?.process ? "Procesar" : "Ver"}
                        </Button>
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

      {isProcessOpen && createPortal(
        <div className="fixed inset-0 z-[80] flex justify-end bg-slate-950/20 backdrop-blur-[1px]">
          <div className="w-full max-w-2xl overflow-y-auto border-l border-border/70 bg-background/96 p-4 shadow-[0_0_60px_rgba(15,23,42,0.16)] backdrop-blur xl:p-6">
            <Card className="border border-border/70 bg-white/92 shadow-[0_24px_90px_rgba(15,23,42,0.08)]">
              <CardHeader className="border-b border-border/70 bg-muted/20">
                <div className="flex items-start justify-between gap-4">
                  <div>
                    <CardTitle className="text-2xl tracking-[-0.04em]">Procesar autorización</CardTitle>
                    <CardDescription>
                      Revisa el detalle de la solicitud y procesa su estado según el flujo administrativo.
                    </CardDescription>
                  </div>

                  <Button variant="ghost" size="icon-sm" className="rounded-full" onClick={closeProcess}>
                    <X />
                  </Button>
                </div>
              </CardHeader>

              <CardContent className="p-5 sm:p-6">
                {isDetailLoading ? (
                  <div className="flex items-center justify-center rounded-2xl border border-border/70 bg-background/70 p-8 text-muted-foreground">
                    <Loader2 className="mr-2 size-4 animate-spin" /> Cargando detalle de la autorización...
                  </div>
                ) : (
                  <form className="flex flex-col gap-5" onSubmit={handleSubmitProcess}>
                    <div className="grid gap-5">
                      <div className="flex flex-col gap-2">
                        <Label className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                          Elemento
                        </Label>
                        <Input value={detailAuthorization?.element || ""} readOnly className="h-12 rounded-2xl border-border/80 bg-background/90" />
                      </div>

                      <div className="grid gap-5 sm:grid-cols-2">
                        <div className="flex flex-col gap-2">
                          <Label className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                            Estado actual
                          </Label>
                          <Input value={detailAuthorization?.status_label || ""} readOnly className="h-12 rounded-2xl border-border/80 bg-background/90" />
                        </div>

                        <div className="flex flex-col gap-2">
                          <Label className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                            Solicitante
                          </Label>
                          <Input value={detailAuthorization?.requester || ""} readOnly className="h-12 rounded-2xl border-border/80 bg-background/90" />
                        </div>
                      </div>

                      <div className="flex flex-col gap-2">
                        <Label htmlFor="status" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                          Nuevo estado
                        </Label>
                        <select id="status" value={processForm.status} onChange={handleProcessChange} className="h-12 rounded-2xl border border-border/80 bg-background/90 px-4 text-sm text-foreground outline-none">
                          <option value="">Selecciona un estado</option>
                          {processableStatuses.map((status) => (
                            <option key={status.code} value={status.label}>{status.label}</option>
                          ))}
                        </select>
                        {formErrors.status && <p className="text-sm text-destructive">{formErrors.status[0]}</p>}
                        {formErrors.estado && <p className="text-sm text-destructive">{formErrors.estado[0]}</p>}
                      </div>

                      {detailAuthorization?.authorization_number && (
                        <div className="flex flex-col gap-2">
                          <Label className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                            N° autorización
                          </Label>
                          <Input value={detailAuthorization.authorization_number} readOnly className="h-12 rounded-2xl border-border/80 bg-background/90" />
                        </div>
                      )}

                      <div className="rounded-2xl border border-border/70 bg-muted/20 p-4">
                        {impactQuery.isLoading ? (
                          <div className="flex items-center gap-2 text-sm text-muted-foreground">
                            <Loader2 className="size-4 animate-spin" />
                            Cargando impacto de la autorización...
                          </div>
                        ) : impactQuery.isError ? (
                          <Alert variant="destructive" className="rounded-2xl">
                            <AlertDescription>No se pudo cargar el impacto de esta autorización.</AlertDescription>
                          </Alert>
                        ) : (
                          <div className="flex flex-col gap-4">
                            <div className="grid gap-3 sm:grid-cols-2">
                              <div className="rounded-xl border border-border/70 bg-background/80 p-3">
                                <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-muted-foreground">Ítems asociados</p>
                                <p className="mt-1 text-2xl font-semibold tracking-[-0.04em] text-foreground">{impactSummary.items_count}</p>
                              </div>
                              <div className="rounded-xl border border-border/70 bg-background/80 p-3">
                                <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-muted-foreground">Proyectos pendientes</p>
                                <p className="mt-1 text-2xl font-semibold tracking-[-0.04em] text-foreground">{impactSummary.pending_projects_count}</p>
                              </div>
                            </div>

                            {impactedItems.length === 0 && impactedProjects.length === 0 ? (
                              <p className="text-sm text-muted-foreground">
                                No se encontraron ítems ni proyectos pendientes afectados.
                              </p>
                            ) : (
                              <div className="grid gap-4 lg:grid-cols-2">
                                <div className="flex flex-col gap-2">
                                  <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-muted-foreground">Ítems afectados</p>
                                  <div className="max-h-44 overflow-y-auto rounded-xl border border-border/70 bg-background/80">
                                    {impactedItems.length === 0 ? (
                                      <p className="px-3 py-3 text-sm text-muted-foreground">Sin ítems afectados.</p>
                                    ) : impactedItems.map((item) => (
                                      <div key={item.id_item} className="flex items-start justify-between gap-3 border-b border-border/60 px-3 py-2 last:border-b-0">
                                        <div>
                                          <p className="text-sm font-medium text-foreground">{item.name}</p>
                                          <p className="text-xs text-muted-foreground">ID {item.id_item}</p>
                                        </div>
                                        <Badge className={`shrink-0 rounded-full px-2 py-1 text-[10px] uppercase ${item.status === "AC" ? "bg-emerald-600 text-white" : "bg-rose-600 text-white"}`}>
                                          {item.status_label}
                                        </Badge>
                                      </div>
                                    ))}
                                  </div>
                                </div>

                                <div className="flex flex-col gap-2">
                                  <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-muted-foreground">Proyectos pendientes afectados</p>
                                  <div className="max-h-44 overflow-y-auto rounded-xl border border-border/70 bg-background/80">
                                    {impactedProjects.length === 0 ? (
                                      <p className="px-3 py-3 text-sm text-muted-foreground">Sin proyectos pendientes afectados.</p>
                                    ) : impactedProjects.map((project) => (
                                      <div key={project.id_proyecto} className="border-b border-border/60 px-3 py-2 last:border-b-0">
                                        <p className="text-sm font-medium text-foreground">{project.name}</p>
                                        <p className="text-xs text-muted-foreground">
                                          ID {project.id_proyecto} · {project.items_count} ítem(s) afectado(s)
                                        </p>
                                      </div>
                                    ))}
                                  </div>
                                </div>
                              </div>
                            )}
                          </div>
                        )}
                      </div>
                    </div>

                    <Separator className="bg-border/70" />

                    <div className="flex flex-wrap justify-end gap-2">
                      <Button type="button" variant="outline" className="rounded-full border-border/70 bg-background/80" onClick={closeProcess}>
                        Cancelar
                      </Button>
                      <Button type="submit" className="rounded-full bg-foreground text-background hover:bg-foreground/90" disabled={processMutation.isPending || !detailAuthorization?.available_actions?.process}>
                        {processMutation.isPending && <Loader2 data-icon="inline-start" className="animate-spin" />}
                        Guardar decisión
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
