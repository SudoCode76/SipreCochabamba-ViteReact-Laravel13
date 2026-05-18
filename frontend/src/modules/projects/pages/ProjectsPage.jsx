import { useDeferredValue, useMemo, useState } from "react";
import { createPortal } from "react-dom";
import { useNavigate } from "react-router-dom";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Package, Search, ChevronLeft, ChevronRight, MoreHorizontal, Pencil, ListPlus, Calculator, RefreshCw, PieChart, FileSpreadsheet, Layers, X, Loader2 } from "lucide-react";

import { Alert, AlertDescription } from "@/components/ui/alert";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from "@/components/ui/dropdown-menu";
import ProjectEditForm from "../components/ProjectEditForm";
import { projectService } from "../services/project.service";

const statusClass = {
  AC: "bg-emerald-600 text-white",
  DC: "bg-rose-600 text-white",
};

const approvalClass = {
  PD: "bg-amber-500 text-white",
  RV: "bg-blue-500 text-white",
  AP: "bg-emerald-600 text-white",
};

export default function ProjectsPage() {
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const [perPage, setPerPage] = useState(15);
  const [search, setSearch] = useState("");
  const [page, setPage] = useState(1);
  const [editOpen, setEditOpen] = useState(false);
  const [editProject, setEditProject] = useState(null);
  const [recalculateOpen, setRecalculateOpen] = useState(false);
  const [recalculateProject, setRecalculateProject] = useState(null);
  const [recalculateDate, setRecalculateDate] = useState("");
  const [recalculateStatus, setRecalculateStatus] = useState(null);
  const [feedback, setFeedback] = useState(null);
  const deferredSearch = useDeferredValue(search.trim());

  const { data, isLoading, isError, error, isFetching } = useQuery({
    queryKey: ["projects", { page, perPage, search: deferredSearch }],
    queryFn: () => projectService.list({ page, perPage, search: deferredSearch }),
    placeholderData: (previousData) => previousData,
  });

  const projects = data?.data?.items ?? [];
  const meta = data?.data?.meta ?? { current_page: 1, per_page: perPage, total: 0 };
  const totalPages = Math.max(1, Math.ceil((meta.total || 0) / (meta.per_page || perPage)));

  const visiblePages = useMemo(() => {
    const start = Math.max(1, meta.current_page - 2);
    const end = Math.min(totalPages, start + 4);
    const first = Math.max(1, end - 4);
    return Array.from({ length: end - first + 1 }, (_, i) => first + i);
  }, [meta.current_page, totalPages]);

  const startRecord = meta.total === 0 ? 0 : (meta.current_page - 1) * meta.per_page + 1;
  const endRecord = Math.min(meta.current_page * meta.per_page, meta.total);

  const handlePerPageChange = (event) => {
    setPage(1);
    setPerPage(Number(event.target.value));
  };

  const handleSearchChange = (event) => {
    setPage(1);
    setSearch(event.target.value);
  };

  const clearSearch = () => {
    setPage(1);
    setSearch("");
  };

  const openEdit = (project) => {
    setEditProject(project);
    setEditOpen(true);
  };

  const closeEdit = () => {
    setEditOpen(false);
    setEditProject(null);
  };

  const openItems = (project) => {
    navigate(`/Proyecto/${project.id_proyecto}/items`);
  };

  const openRecalculate = (project) => {
    setRecalculateProject(project);
    setRecalculateDate("");
    setRecalculateStatus(null);
    setRecalculateOpen(true);
  };

  const closeRecalculate = () => {
    setRecalculateOpen(false);
    setRecalculateProject(null);
    setRecalculateDate("");
    setRecalculateStatus(null);
  };

  const recalculateMutation = useMutation({
    mutationFn: ({ projectId, payload }) => projectService.budgetRecalculation(projectId, payload),
    onSuccess: async (response) => {
      await queryClient.invalidateQueries({ queryKey: ["projects"] });
      setRecalculateStatus({
        type: "success",
        message: response?.message || "Precio del proyecto recalculado correctamente.",
      });
      window.setTimeout(() => {
        closeRecalculate();
      }, 800);
    },
  });

  const handleRecalculateSubmit = async (event) => {
    event.preventDefault();
    if (!recalculateProject?.id_proyecto || !recalculateDate) {
      return;
    }

    setRecalculateStatus(null);

    try {
      await recalculateMutation.mutateAsync({
        projectId: recalculateProject.id_proyecto,
        payload: { fecha: recalculateDate },
      });
    } catch (mutationError) {
      const fieldErrors = mutationError?.response?.data?.errors;
      const firstFieldError = fieldErrors ? Object.values(fieldErrors).flat().find(Boolean) : null;
      setRecalculateStatus({
        type: "error",
        message: firstFieldError || mutationError?.response?.data?.message || "No se pudo recalcular el precio del proyecto.",
      });
    }
  };

  const handleOpenBudgetByGroupPdf = async (project) => {
    setFeedback(null);
    const popup = window.open("", "_blank");

    if (popup) {
      popup.document.write("<p>Generando PDF...</p>");
    }

    try {
      const blob = await projectService.downloadBudgetByGroupPdf(project.id_proyecto);

      if (!blob || blob.size === 0 || blob.type !== "application/pdf") {
        throw new Error("La respuesta no contiene un PDF valido.");
      }

      const url = URL.createObjectURL(blob);

      if (popup) {
        popup.location.href = url;
      } else {
        window.open(url, "_blank");
      }
    } catch (pdfError) {
      if (popup) {
        popup.close();
      }

      setFeedback({
        type: "error",
        message: pdfError?.response?.data?.message || pdfError.message || "No se pudo generar el presupuesto por rubros.",
      });
    }
  };

  const formatProjectNameLines = (value) => {
    const words = String(value || "").trim().split(/\s+/).filter(Boolean);
    const lines = [];

    for (let i = 0; i < words.length; i += 2) {
      lines.push(words.slice(i, i + 2).join(" "));
    }

    return lines.length ? lines : ["-"];
  };

  return (
    <div className="flex flex-col gap-6">
      <Card className="border border-border/70 bg-white/86 shadow-[0_24px_90px_rgba(15,23,42,0.08)]">
        <CardHeader className="gap-4 border-b border-border/70 bg-muted/25">
          <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div className="flex items-center gap-3">
              <div className="flex size-11 items-center justify-center rounded-2xl bg-sky-600 text-white">
                <Package className="size-5" />
              </div>
              <div>
                <CardTitle className="text-2xl tracking-[-0.04em]">Proyectos</CardTitle>
              </div>
            </div>
          </div>
        </CardHeader>

        <CardContent className="flex flex-col gap-6 p-5 sm:p-6">
          {feedback && (
            <Alert variant={feedback.type === "error" ? "destructive" : "default"} className="rounded-2xl">
              <AlertDescription>{feedback.message}</AlertDescription>
            </Alert>
          )}
          <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div className="flex items-end gap-3">
              <div className="flex flex-col gap-2">
                <label className="text-xs font-medium text-muted-foreground">Buscar</label>
                <div className="flex gap-2">
                  <Input
                    placeholder="Buscar proyecto..."
                    className="h-9 w-64 rounded-xl border-border/80"
                    value={search}
                    onChange={handleSearchChange}
                  />
                  <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    className="h-9 rounded-xl"
                    onClick={clearSearch}
                    disabled={!search}
                  >
                    {search ? <X className="size-4" /> : <Search className="size-4" />}
                  </Button>
                </div>
              </div>
            </div>

            <div className="flex items-center gap-3">
              <label className="text-xs font-medium text-muted-foreground">Mostrar</label>
              <select
                value={perPage}
                onChange={handlePerPageChange}
                className="h-9 rounded-xl border border-border/80 px-3 text-sm"
              >
                <option value={10}>10</option>
                <option value={15}>15</option>
                <option value={25}>25</option>
                <option value={50}>50</option>
              </select>
            </div>
          </div>

          {isLoading && (
            <div className="flex items-center justify-center rounded-2xl border border-border/70 bg-background/70 p-8 text-muted-foreground">
              Cargando proyectos...
            </div>
          )}

          {isError && (
            <div className="rounded-2xl border border-destructive/50 bg-destructive/10 p-4 text-destructive">
              {error?.response?.data?.message || "Error al cargar proyectos"}
            </div>
          )}

          {!isLoading && !isError && (
            <div className="overflow-hidden rounded-[28px] border border-border/70 bg-background/90">
              {isFetching && (
                <div className="border-b border-border/70 bg-muted/20 px-5 py-2 text-xs text-muted-foreground">
                  Buscando proyectos...
                </div>
              )}
              <div className="overflow-x-auto overflow-y-hidden">
                <table className="min-w-full border-collapse text-sm">
                  <thead>
                    <tr className="border-b border-border/70 bg-muted/30 text-left">
                      <th className="px-5 py-4 font-semibold text-foreground">N°</th>
                      <th className="px-5 py-4 font-semibold text-foreground">Proyecto</th>
                      <th className="px-5 py-4 font-semibold text-foreground">Ubicación</th>
                      <th className="px-5 py-4 font-semibold text-foreground">Fecha</th>
                      <th className="px-5 py-4 font-semibold text-foreground">Responsable</th>
                      <th className="px-5 py-4 font-semibold text-foreground">Solicitante</th>
                      <th className="px-5 py-4 font-semibold text-foreground">Condición</th>
                      <th className="px-5 py-4 font-semibold text-foreground">Observación</th>
                      <th className="px-5 py-4 font-semibold text-foreground">Estado</th>
                      <th className="px-5 py-4 font-semibold text-foreground text-center">Opciones</th>
                    </tr>
                  </thead>
                  <tbody>
                    {projects.map((project, index) => (
                      <tr key={project.id_proyecto} className={index < projects.length - 1 ? "border-b border-border/60" : ""}>
                        <td className="px-5 py-4 align-top text-foreground">
                          {index + 1}
                        </td>
                        <td className="px-5 py-4 align-top text-foreground max-w-[250px]">
                          <div className="leading-7 whitespace-normal break-words">
                            {formatProjectNameLines(project.nombre_proyecto).map((line, lineIndex) => (
                              <div key={`${project.id_proyecto}-name-line-${lineIndex}`}>{line}</div>
                            ))}
                          </div>
                        </td>
                        <td className="px-5 py-4 align-top text-muted-foreground max-w-[180px]">
                          <div className="truncate">{project.ubicacion}</div>
                        </td>
                        <td className="px-5 py-4 align-top text-muted-foreground">
                          {project.fecha}
                        </td>
                        <td className="px-5 py-4 align-top text-foreground">
                          {project.responsable_nombre || project.responsable}
                        </td>
                        <td className="px-5 py-4 align-top text-foreground">
                          {project.solicitante_nombre || project.solicitante}
                        </td>
                        <td className="px-5 py-4 align-top">
                          <Badge className={`rounded-full px-3 py-1 text-[11px] uppercase tracking-[0.18em] ${approvalClass[project.aprobado] || "bg-slate-500 text-white"}`}>
                            {project.aprobado === "PD" ? "PENDIENTE" : project.aprobado === "RV" ? "REVISADO" : "APROBADO"}
                          </Badge>
                        </td>
                        <td className="px-5 py-4 align-top text-muted-foreground max-w-[150px]">
                          <div className="truncate">{project.observaciones || "-"}</div>
                        </td>
                        <td className="px-5 py-4 align-top">
                          <Badge className={`rounded-full px-3 py-1 text-[11px] uppercase tracking-[0.18em] ${statusClass[project.estado] || "bg-slate-500 text-white"}`}>
                            {project.estado === "AC" ? "ACTIVO" : "INACTIVO"}
                          </Badge>
                        </td>
                        <td className="px-5 py-4 align-top text-center">
                          <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                              <Button variant="ghost" size="sm" className="h-8 w-8 p-0 rounded-full">
                                <MoreHorizontal className="h-4 w-4" />
                              </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-72 rounded-2xl border border-border/70 bg-background/95 p-1 shadow-lg">
                              <DropdownMenuItem
                                className="flex items-center gap-2 rounded-xl px-3 py-2 cursor-pointer"
                                onClick={() => openEdit(project)}
                              >
                                <Pencil className="h-4 w-4 text-muted-foreground" />
                                <span>Editar Proyecto</span>
                              </DropdownMenuItem>
                              <DropdownMenuItem className="flex items-center gap-2 rounded-xl px-3 py-2 cursor-pointer" onClick={() => openItems(project)}>
                                <ListPlus className="h-4 w-4 text-muted-foreground" />
                                <span>Agregar Items al Proyecto</span>
                              </DropdownMenuItem>
                              <DropdownMenuItem className="flex items-center gap-2 rounded-xl px-3 py-2 cursor-pointer" onClick={() => void handleOpenBudgetByGroupPdf(project)}>
                                <Calculator className="h-4 w-4 text-muted-foreground" />
                                <span>Presupuesto por Rubros</span>
                              </DropdownMenuItem>
                              <DropdownMenuItem className="flex items-center gap-2 rounded-xl px-3 py-2 cursor-pointer" onClick={() => openRecalculate(project)}>
                                <RefreshCw className="h-4 w-4 text-muted-foreground" />
                                <span>Recalcular Precio por Rubro</span>
                              </DropdownMenuItem>
                              <DropdownMenuItem className="flex items-center gap-2 rounded-xl px-3 py-2 cursor-pointer">
                                <PieChart className="h-4 w-4 text-muted-foreground" />
                                <span>Resumen por Insidencia</span>
                              </DropdownMenuItem>
                              <DropdownMenuItem className="flex items-center gap-2 rounded-xl px-3 py-2 cursor-pointer">
                                <FileSpreadsheet className="h-4 w-4 text-muted-foreground" />
                                <span>Presupuesto General</span>
                              </DropdownMenuItem>
                              <DropdownMenuItem className="flex items-center gap-2 rounded-xl px-3 py-2 cursor-pointer">
                                <Layers className="h-4 w-4 text-muted-foreground" />
                                <span>Desglose de Insumos del Proyecto</span>
                              </DropdownMenuItem>
                            </DropdownMenuContent>
                          </DropdownMenu>
                        </td>
                      </tr>
                    ))}
                    {projects.length === 0 && (
                      <tr>
                        <td colSpan={10} className="px-5 py-8 text-center text-muted-foreground">
                          No se encontraron proyectos.
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
          <div className="w-full max-w-3xl overflow-y-auto border-l border-border/70 bg-background/96 p-4 shadow-[0_0_60px_rgba(15,23,42,0.16)] backdrop-blur xl:p-6">
            <Card className="border border-border/70 bg-white/92 shadow-[0_24px_90px_rgba(15,23,42,0.08)]">
              <CardHeader className="border-b border-border/70 bg-muted/20">
                <div className="flex items-start justify-between gap-4">
                  <div>
                    <CardTitle className="text-2xl tracking-[-0.04em]">Editar Proyecto</CardTitle>
                    <CardDescription>
                      {editProject?.nombre_proyecto ? `Proyecto: ${editProject.nombre_proyecto}` : "Actualiza la información base del proyecto seleccionado."}
                    </CardDescription>
                  </div>

                  <Button variant="ghost" size="icon-sm" className="rounded-full" onClick={closeEdit}>
                    <X />
                  </Button>
                </div>
              </CardHeader>

              <CardContent className="p-5 sm:p-6">
                {editProject && (
                  <ProjectEditForm
                    projectId={editProject.id_proyecto}
                    onCancel={closeEdit}
                    onSuccess={closeEdit}
                  />
                )}
              </CardContent>
            </Card>
          </div>
        </div>,
        document.body,
      )}

      {recalculateOpen && createPortal(
        <div className="fixed inset-0 z-[80] flex justify-end bg-slate-950/20 backdrop-blur-[1px]">
          <div className="w-full max-w-3xl overflow-y-auto border-l border-border/70 bg-background/96 p-4 shadow-[0_0_60px_rgba(15,23,42,0.16)] backdrop-blur xl:p-6">
            <Card className="border border-border/70 bg-white/92 shadow-[0_24px_90px_rgba(15,23,42,0.08)]">
              <CardHeader className="border-b border-border/70 bg-muted/20">
                <div className="flex items-start justify-between gap-4">
                  <div>
                    <CardTitle className="text-2xl tracking-[-0.04em]">Recalcular precio proyecto</CardTitle>
                    <CardDescription>Recalcular Precios Unitarios por periodos de tiempo</CardDescription>
                  </div>

                  <Button variant="ghost" size="icon-sm" className="rounded-full" onClick={closeRecalculate}>
                    <X />
                  </Button>
                </div>
              </CardHeader>

              <CardContent className="p-5 sm:p-6">
                {recalculateStatus && (
                  <Alert className="mb-4 rounded-2xl" variant={recalculateStatus.type === "error" ? "destructive" : "default"}>
                    <AlertDescription>{recalculateStatus.message}</AlertDescription>
                  </Alert>
                )}

                <form className="flex flex-col gap-5" onSubmit={handleRecalculateSubmit}>
                  <div className="flex flex-col gap-2">
                    <Label htmlFor="project_recalculate" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">Proyecto</Label>
                    <Input
                      id="project_recalculate"
                      value={recalculateProject?.nombre_proyecto ?? ""}
                      className="h-12 rounded-2xl border-border/80 bg-background/90"
                      disabled
                    />
                  </div>

                  <div className="flex flex-col gap-2">
                    <Label htmlFor="project_recalculate_date" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">Seleccione Fecha de Impresion/calculo</Label>
                    <Input
                      id="project_recalculate_date"
                      type="date"
                      value={recalculateDate}
                      onChange={(event) => setRecalculateDate(event.target.value)}
                      className="h-12 rounded-2xl border-border/80 bg-background/90"
                      required
                    />
                  </div>

                  <div className="flex justify-end gap-2 pt-2">
                    <Button type="button" variant="outline" className="rounded-full border-border/70 bg-background/80" onClick={closeRecalculate}>
                      Cancelar
                    </Button>
                    <Button type="submit" className="rounded-full bg-foreground text-background hover:bg-foreground/90" disabled={recalculateMutation.isPending}>
                      {recalculateMutation.isPending ? (
                        <>
                          <Loader2 className="mr-2 size-4 animate-spin" />
                          Recalculando...
                        </>
                      ) : "Recalcular"}
                    </Button>
                  </div>
                </form>
              </CardContent>
            </Card>
          </div>
        </div>,
        document.body,
      )}

    </div>
  );
}
