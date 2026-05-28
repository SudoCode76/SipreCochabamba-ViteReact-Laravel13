import { useDeferredValue, useMemo, useState } from "react";
import { createPortal } from "react-dom";
import { useNavigate } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";
import { Package, Search, ChevronLeft, ChevronRight, MoreHorizontal, Pencil, ListPlus, Calculator, RefreshCw, PieChart, FileSpreadsheet, Layers, ClipboardList, X, Loader2, History, Copy } from "lucide-react";

import { Alert, AlertDescription } from "@/components/ui/alert";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from "@/components/ui/dropdown-menu";
import { formatDate, formatDateTime } from "@/lib/utils";
import { openPdfViewer } from "@/lib/utils/pdf";
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

const historyActionLabels = {
  created: "Creacion",
  updated: "Actualizacion",
  items_synced: "Items",
  budget_recalculated: "Recalculo",
  pdf_generated: "PDF",
  template_created: "Planilla",
  created_from_template: "Planilla",
};

const historyActionOptions = [
  { value: "", label: "Todas las acciones" },
  { value: "created", label: "Creacion" },
  { value: "updated", label: "Actualizacion" },
  { value: "items_synced", label: "Items sincronizados" },
  { value: "budget_recalculated", label: "Presupuesto recalculado" },
  { value: "pdf_generated", label: "PDF generado" },
  { value: "template_created", label: "Planilla creada" },
  { value: "created_from_template", label: "Creado desde planilla" },
];

const hasMetadata = (metadata) => metadata && Object.keys(metadata).length > 0;

const historyFieldLabels = {
  name: "Nombre",
  nombre_proyecto: "Nombre",
  location: "Ubicacion",
  ubicacion: "Ubicacion",
  status: "Estado",
  estado: "Estado",
  approval_status: "Aprobacion",
  aprobado: "Aprobacion",
  responsible: "Responsable",
  responsable: "Responsable",
  requester_id: "Solicitante",
  solicitante: "Solicitante",
  fecha: "Fecha",
  observaciones: "Observaciones",
  precio: "Precio",
  cantidad: "Cantidad",
  prioridad: "Prioridad",
  report_type: "Reporte",
  template_name: "Planilla",
  items_copied: "Items copiados",
  format: "Formato",
  type: "Tipo",
  reference_date: "Fecha de referencia",
  total: "Total",
  direct_cost: "Costo directo",
  materiales: "Materiales",
  mano_obra: "Mano de obra",
  herramientas: "Herramientas",
};

const historyStatusLabels = {
  AC: "Activo",
  DC: "Inactivo",
  PD: "Pendiente",
  RV: "Revisado",
  AP: "Aprobado",
  1: "Material",
  2: "Mano de obra",
  3: "Maquinaria y herramientas",
};

const labelHistoryField = (field) => historyFieldLabels[field] || field.replaceAll("_", " ");

const formatHistoryValue = (field, value) => {
  if (value === null || value === undefined || value === "") {
    return "-";
  }

  const normalizedField = String(field).toLowerCase();

  if (normalizedField.includes("fecha") || normalizedField.includes("date")) {
    return formatDate(value);
  }

  const normalized = String(value);

  if (historyStatusLabels[normalized]) {
    return historyStatusLabels[normalized];
  }

  if (typeof value === "number") {
    return new Intl.NumberFormat("es-BO", { maximumFractionDigits: 4 }).format(value);
  }

  return normalized;
};

const metadataRow = (label, value) => (
  <div key={label} className="rounded-xl border border-border/70 bg-background px-3 py-2">
    <div className="text-[11px] font-semibold uppercase tracking-[0.16em] text-muted-foreground">{label}</div>
    <div className="mt-1 text-sm text-foreground">{formatHistoryValue(label, value)}</div>
  </div>
);

const itemLabel = (item) => `Item #${item?.id_item || "-"}`;

const renderChangedFields = (changes) => {
  const entries = Object.entries(changes || {});

  if (entries.length === 0) {
    return <p className="text-sm text-muted-foreground">No se registraron cambios en campos visibles.</p>;
  }

  return (
    <div className="space-y-2">
      {entries.map(([field, change]) => (
        <div key={field} className="rounded-xl border border-border/70 bg-background px-3 py-2">
          <div className="text-sm font-medium text-foreground">{labelHistoryField(field)}</div>
          <div className="mt-1 text-sm text-muted-foreground">
            Antes: <span className="text-foreground">{formatHistoryValue(change?.from)}</span>
            <span className="px-2">-&gt;</span>
            Ahora: <span className="text-foreground">{formatHistoryValue(change?.to)}</span>
          </div>
        </div>
      ))}
    </div>
  );
};

const renderItemChangeList = (title, items, mode) => {
  if (!items?.length) {
    return null;
  }

  return (
    <div className="space-y-2">
      <h4 className="text-sm font-semibold text-foreground">{title}</h4>
      {items.map((item, index) => (
        <div key={`${title}-${item.id_item || index}`} className="rounded-xl border border-border/70 bg-background px-3 py-2">
          <div className="text-sm font-medium text-foreground">{itemLabel(item)}</div>
          {mode === "updated" ? (
            <div className="mt-2">{renderChangedFields(item.changes)}</div>
          ) : (
            <div className="mt-1 grid gap-2 sm:grid-cols-3">
              {metadataRow("Cantidad", item.cantidad)}
              {metadataRow("Precio", item.precio)}
              {metadataRow("Prioridad", item.prioridad)}
            </div>
          )}
        </div>
      ))}
    </div>
  );
};

const renderHistoryMetadata = (entry) => {
  const metadata = entry.metadata || {};

  if (entry.action === "created" && metadata.project) {
    return (
      <dl className="grid gap-2 sm:grid-cols-2">
        {Object.entries(metadata.project).map(([field, value]) => metadataRow(labelHistoryField(field), value))}
      </dl>
    );
  }

  if (entry.action === "updated") {
    return renderChangedFields(metadata.changes);
  }

  if (entry.action === "items_synced") {
    return (
      <div className="space-y-4">
        {renderItemChangeList("Items agregados", metadata.added, "created")}
        {renderItemChangeList("Items modificados", metadata.updated, "updated")}
        {renderItemChangeList("Items quitados", metadata.removed, "removed")}
      </div>
    );
  }

  if (entry.action === "budget_recalculated") {
    return (
      <dl className="grid gap-2 sm:grid-cols-2">
        {metadata.reference_date && metadataRow("Fecha de referencia", metadata.reference_date)}
        {Object.entries(metadata.totals || {}).map(([field, value]) => metadataRow(labelHistoryField(field), value))}
      </dl>
    );
  }

  if (entry.action === "pdf_generated") {
    return (
      <dl className="grid gap-2 sm:grid-cols-2">
        {Object.entries(metadata).map(([field, value]) => metadataRow(labelHistoryField(field), value))}
      </dl>
    );
  }

  return (
    <dl className="grid gap-2 sm:grid-cols-2">
      {Object.entries(metadata).map(([field, value]) => metadataRow(labelHistoryField(field), value))}
    </dl>
  );
};

export default function ProjectsPage() {
  const navigate = useNavigate();
  const [perPage, setPerPage] = useState(15);
  const [order, setOrder] = useState("legacy");
  const [search, setSearch] = useState("");
  const [page, setPage] = useState(1);
  const [editOpen, setEditOpen] = useState(false);
  const [editProject, setEditProject] = useState(null);
  const [recalculateOpen, setRecalculateOpen] = useState(false);
  const [recalculateProject, setRecalculateProject] = useState(null);
  const [recalculateDate, setRecalculateDate] = useState("");
  const [recalculateStatus, setRecalculateStatus] = useState(null);
  const [recalculatePending, setRecalculatePending] = useState(false);
  const [incidenceOpen, setIncidenceOpen] = useState(false);
  const [incidenceProject, setIncidenceProject] = useState(null);
  const [incidenceFormat, setIncidenceFormat] = useState("PCA");
  const [incidenceStatus, setIncidenceStatus] = useState(null);
  const [generalBudgetOpen, setGeneralBudgetOpen] = useState(false);
  const [generalBudgetProject, setGeneralBudgetProject] = useState(null);
  const [generalBudgetFormat, setGeneralBudgetFormat] = useState("PCA");
  const [generalBudgetStatus, setGeneralBudgetStatus] = useState(null);
  const [inputBreakdownOpen, setInputBreakdownOpen] = useState(false);
  const [inputBreakdownProject, setInputBreakdownProject] = useState(null);
  const [inputBreakdownType, setInputBreakdownType] = useState("1");
  const [inputBreakdownStatus, setInputBreakdownStatus] = useState(null);
  const [historyOpen, setHistoryOpen] = useState(false);
  const [historyProject, setHistoryProject] = useState(null);
  const [historyPage, setHistoryPage] = useState(1);
  const [historyFilters, setHistoryFilters] = useState({
    action: "",
    user: "",
    dateFrom: "",
    dateTo: "",
  });
  const [templateOpen, setTemplateOpen] = useState(false);
  const [templateProject, setTemplateProject] = useState(null);
  const [templateName, setTemplateName] = useState("");
  const [templatePending, setTemplatePending] = useState(false);
  const [templateStatus, setTemplateStatus] = useState(null);
  const [feedback, setFeedback] = useState(null);
  const deferredSearch = useDeferredValue(search.trim());

  const { data, isLoading, isError, error, isFetching } = useQuery({
    queryKey: ["projects", { page, perPage, search: deferredSearch, order }],
    queryFn: () => projectService.list({ page, perPage, search: deferredSearch, order }),
    placeholderData: (previousData) => previousData,
  });
  const { data: contextData } = useQuery({
    queryKey: ["projects-context"],
    queryFn: projectService.context,
    retry: false,
  });

  const projects = data?.data?.items ?? [];
  const permissions = contextData?.data?.permissions ?? {};
  const canEditProject = Boolean(permissions.can_edit);
  const canSyncProjectItems = Boolean(permissions.can_sync_items);
  const canViewHistory = Boolean(permissions.can_view);
  const canViewBudgetByGroup = Boolean(permissions.can_view_budget_by_group);
  const canRecalculateBudget = Boolean(permissions.can_recalculate_budget);
  const canViewIncidenceSummary = Boolean(permissions.can_view_incidence_summary);
  const canViewGeneralBudget = Boolean(permissions.can_view_general_budget);
  const canViewInputBreakdown = Boolean(permissions.can_view_input_breakdown);
  const canViewInputsReport = Boolean(permissions.can_view_inputs_report);
  const canManageTemplates = Boolean(permissions.can_manage_templates || permissions.can_create || permissions.can_edit);
  const hasProjectRowActions = canEditProject
    || canSyncProjectItems
    || canViewHistory
    || canManageTemplates
    || canViewBudgetByGroup
    || canRecalculateBudget
    || canViewIncidenceSummary
    || canViewGeneralBudget
    || canViewInputBreakdown
    || canViewInputsReport;
  const meta = data?.data?.meta ?? { current_page: 1, per_page: perPage, total: 0 };
  const totalPages = Math.max(1, Math.ceil((meta.total || 0) / (meta.per_page || perPage)));
  const historyProjectId = historyProject?.id_proyecto;

  const {
    data: historyData,
    isLoading: historyLoading,
    isFetching: historyFetching,
    isError: historyIsError,
    error: historyError,
  } = useQuery({
    queryKey: ["project-history", historyProjectId, historyPage, historyFilters],
    queryFn: () => projectService.history(historyProjectId, {
      page: historyPage,
      perPage: 10,
      action: historyFilters.action,
      user: historyFilters.user,
      dateFrom: historyFilters.dateFrom,
      dateTo: historyFilters.dateTo,
    }),
    enabled: historyOpen && Boolean(historyProjectId),
    placeholderData: (previousData) => previousData,
  });

  const historyItems = historyData?.data?.items ?? [];
  const historyMeta = historyData?.data?.meta ?? { current_page: 1, per_page: 10, total: 0 };
  const historyTotalPages = Math.max(1, Math.ceil((historyMeta.total || 0) / (historyMeta.per_page || 10)));

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

  const handleOrderChange = (event) => {
    setPage(1);
    setOrder(event.target.value);
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

  const openHistory = (project) => {
    setHistoryProject(project);
    setHistoryPage(1);
    setHistoryOpen(true);
  };

  const closeHistory = () => {
    setHistoryOpen(false);
    setHistoryProject(null);
    setHistoryPage(1);
    setHistoryFilters({
      action: "",
      user: "",
      dateFrom: "",
      dateTo: "",
    });
  };

  const updateHistoryFilter = (key, value) => {
    setHistoryPage(1);
    setHistoryFilters((current) => ({ ...current, [key]: value }));
  };

  const clearHistoryFilters = () => {
    setHistoryPage(1);
    setHistoryFilters({
      action: "",
      user: "",
      dateFrom: "",
      dateTo: "",
    });
  };

  const openTemplateModal = (project) => {
    setTemplateProject(project);
    setTemplateName(`PLANILLA - ${project.nombre_proyecto || ""}`.trim());
    setTemplateStatus(null);
    setTemplateOpen(true);
  };

  const closeTemplateModal = () => {
    setTemplateOpen(false);
    setTemplateProject(null);
    setTemplateName("");
    setTemplateStatus(null);
    setTemplatePending(false);
  };

  const handleTemplateSubmit = async (event) => {
    event.preventDefault();

    if (!templateProject?.id_proyecto || !templateName.trim()) {
      return;
    }

    setTemplatePending(true);
    setTemplateStatus(null);

    try {
      await projectService.createTemplateFromProject(templateProject.id_proyecto, {
        nombre_proyecto: templateName.trim(),
      });
      setFeedback({
        type: "success",
        message: "La planilla del proyecto se creó correctamente.",
      });
      closeTemplateModal();
    } catch (templateError) {
      const fieldErrors = templateError?.response?.data?.errors;
      const firstFieldError = fieldErrors ? Object.values(fieldErrors).flat().find(Boolean) : null;
      setTemplateStatus({
        type: "error",
        message: firstFieldError || templateError?.response?.data?.message || "No se pudo crear la planilla del proyecto.",
      });
    } finally {
      setTemplatePending(false);
    }
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
    setRecalculatePending(false);
  };

  const handleRecalculateSubmit = async (event) => {
    event.preventDefault();
    if (!recalculateProject?.id_proyecto || !recalculateDate) {
      return;
    }

    setRecalculateStatus(null);
    setRecalculatePending(true);

    try {
      openPdfViewer(projectService.budgetRecalculationPdfUrl(recalculateProject.id_proyecto, recalculateDate), {
        title: "Presupuesto recalculado del proyecto",
        errorMessage: "No se pudo generar el presupuesto recalculado del proyecto.",
      });
      closeRecalculate();
    } catch (pdfError) {
      const fieldErrors = pdfError?.response?.data?.errors;
      const firstFieldError = fieldErrors ? Object.values(fieldErrors).flat().find(Boolean) : null;
      setRecalculateStatus({
        type: "error",
        message: firstFieldError || pdfError?.response?.data?.message || pdfError.message || "No se pudo generar el presupuesto recalculado del proyecto.",
      });
    } finally {
      setRecalculatePending(false);
    }
  };

  const handleOpenBudgetByGroupPdf = async (project) => {
    setFeedback(null);

    try {
      openPdfViewer(projectService.budgetByGroupPdfUrl(project.id_proyecto), {
        title: "Presupuesto por rubros",
        errorMessage: "No se pudo generar el presupuesto por rubros.",
      });
    } catch (pdfError) {
      setFeedback({
        type: "error",
        message: pdfError?.response?.data?.message || pdfError.message || "No se pudo generar el presupuesto por rubros.",
      });
    }
  };

  const handleOpenInputsReportPdf = async (project) => {
    setFeedback(null);

    try {
      openPdfViewer(projectService.inputsReportPdfUrl(project.id_proyecto), {
        title: "Reporte de insumos del proyecto",
        errorMessage: "No se pudo generar el reporte de insumos del proyecto.",
      });
    } catch (pdfError) {
      setFeedback({
        type: "error",
        message: pdfError?.response?.data?.message || pdfError.message || "No se pudo generar el reporte de insumos del proyecto.",
      });
    }
  };

  const openIncidenceSummary = (project) => {
    setIncidenceProject(project);
    setIncidenceFormat("PCA");
    setIncidenceStatus(null);
    setIncidenceOpen(true);
  };

  const closeIncidenceSummary = () => {
    setIncidenceOpen(false);
    setIncidenceProject(null);
    setIncidenceFormat("PCA");
    setIncidenceStatus(null);
  };

  const handleIncidenceSummarySubmit = async (event) => {
    event.preventDefault();

    if (!incidenceProject?.id_proyecto) {
      return;
    }

    setIncidenceStatus(null);

    try {
      openPdfViewer(projectService.incidenceSummaryPdfUrl(incidenceProject.id_proyecto, incidenceFormat), {
        title: "Resumen por incidencia",
        errorMessage: "No se pudo generar el resumen por incidencia.",
      });
      closeIncidenceSummary();
    } catch (pdfError) {
      setIncidenceStatus({
        type: "error",
        message: pdfError?.response?.data?.message || pdfError.message || "No se pudo generar el resumen por incidencia.",
      });
    }
  };

  const openGeneralBudget = (project) => {
    setGeneralBudgetProject(project);
    setGeneralBudgetFormat("PCA");
    setGeneralBudgetStatus(null);
    setGeneralBudgetOpen(true);
  };

  const closeGeneralBudget = () => {
    setGeneralBudgetOpen(false);
    setGeneralBudgetProject(null);
    setGeneralBudgetFormat("PCA");
    setGeneralBudgetStatus(null);
  };

  const handleGeneralBudgetSubmit = async (event) => {
    event.preventDefault();

    if (!generalBudgetProject?.id_proyecto) {
      return;
    }

    setGeneralBudgetStatus(null);

    try {
      openPdfViewer(projectService.generalBudgetPdfUrl(generalBudgetProject.id_proyecto, generalBudgetFormat), {
        title: "Presupuesto general",
        errorMessage: "No se pudo generar el presupuesto general.",
      });
      closeGeneralBudget();
    } catch (pdfError) {
      setGeneralBudgetStatus({
        type: "error",
        message: pdfError?.response?.data?.message || pdfError.message || "No se pudo generar el presupuesto general.",
      });
    }
  };

  const openInputBreakdown = (project) => {
    setInputBreakdownProject(project);
    setInputBreakdownType("1");
    setInputBreakdownStatus(null);
    setInputBreakdownOpen(true);
  };

  const closeInputBreakdown = () => {
    setInputBreakdownOpen(false);
    setInputBreakdownProject(null);
    setInputBreakdownType("1");
    setInputBreakdownStatus(null);
  };

  const handleInputBreakdownSubmit = async (event) => {
    event.preventDefault();

    if (!inputBreakdownProject?.id_proyecto) {
      return;
    }

    setInputBreakdownStatus(null);

    try {
      openPdfViewer(projectService.inputBreakdownPdfUrl(inputBreakdownProject.id_proyecto, inputBreakdownType), {
        title: "Desglose de insumos del proyecto",
        errorMessage: "No se pudo generar el desglose de insumos del proyecto.",
      });
      closeInputBreakdown();
    } catch (pdfError) {
      setInputBreakdownStatus({
        type: "error",
        message: pdfError?.response?.data?.message || pdfError.message || "No se pudo generar el desglose de insumos del proyecto.",
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
      <Card className="min-w-0 border border-border/70 bg-white/86 shadow-[0_24px_90px_rgba(15,23,42,0.08)]">
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

        <CardContent className="flex min-w-0 flex-col gap-6 p-5 sm:p-6">
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
              <label className="text-xs font-medium text-muted-foreground">Orden</label>
              <select
                value={order}
                onChange={handleOrderChange}
                className="h-9 rounded-xl border border-border/80 px-3 text-sm"
              >
                <option value="legacy">Predeterminado</option>
                <option value="recent">Recientes</option>
                <option value="oldest">Antiguos</option>
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
            <div className="max-w-full overflow-hidden rounded-[28px] border border-border/70 bg-background/90">
              {isFetching && (
                <div className="border-b border-border/70 bg-muted/20 px-5 py-2 text-xs text-muted-foreground">
                  Buscando proyectos...
                </div>
              )}
              <div className="max-w-full overflow-x-auto overflow-y-hidden">
                <table className="w-full table-fixed border-collapse text-[13px]">
                  <colgroup>
                    <col className="w-[4%]" />
                    <col className="w-[15%]" />
                    <col className="w-[12%]" />
                    <col className="w-[8%]" />
                    <col className="w-[10%]" />
                    <col className="w-[13%]" />
                    <col className="w-[10%]" />
                    <col className="w-[12%]" />
                    <col className="w-[10%]" />
                    <col className="w-[6%]" />
                  </colgroup>
                  <thead>
                    <tr className="border-b border-border/70 bg-muted/30 text-left">
                      <th className="whitespace-nowrap px-2 py-3 font-semibold text-foreground">N°</th>
                      <th className="px-2 py-3 font-semibold text-foreground">Proyecto</th>
                      <th className="px-2 py-3 font-semibold text-foreground">Ubicación</th>
                      <th className="whitespace-nowrap px-2 py-3 font-semibold text-foreground">Fecha</th>
                      <th className="px-2 py-3 font-semibold text-foreground">Responsable</th>
                      <th className="px-2 py-3 font-semibold text-foreground">Solicitante</th>
                      <th className="whitespace-nowrap px-2 py-3 font-semibold text-foreground">Condición</th>
                      <th className="px-2 py-3 font-semibold text-foreground">Observación</th>
                      <th className="whitespace-nowrap px-2 py-3 font-semibold text-foreground">Estado</th>
                      <th className="whitespace-nowrap px-2 py-3 text-center font-semibold text-foreground">Opc.</th>
                    </tr>
                  </thead>
                  <tbody>
                    {projects.map((project, index) => (
                      <tr key={project.id_proyecto} className={index < projects.length - 1 ? "border-b border-border/60" : ""}>
                        <td className="whitespace-nowrap px-2 py-3 align-top text-foreground">
                          {index + 1}
                        </td>
                        <td className="px-2 py-3 align-top text-foreground">
                          <div className="line-clamp-3 whitespace-normal break-words leading-6" title={project.nombre_proyecto || ""}>
                            {formatProjectNameLines(project.nombre_proyecto).map((line, lineIndex) => (
                              <div key={`${project.id_proyecto}-name-line-${lineIndex}`}>{line}</div>
                            ))}
                          </div>
                        </td>
                        <td className="px-2 py-3 align-top text-muted-foreground">
                          <div className="truncate" title={project.ubicacion || ""}>{project.ubicacion}</div>
                        </td>
                        <td className="whitespace-nowrap px-2 py-3 align-top text-muted-foreground">
                          {formatDate(project.fecha)}
                        </td>
                        <td className="px-2 py-3 align-top text-foreground">
                          <div className="truncate" title={String(project.responsable_nombre || project.responsable || "")}>
                            {project.responsable_nombre || project.responsable}
                          </div>
                        </td>
                        <td className="px-2 py-3 align-top text-foreground">
                          <div className="line-clamp-3 whitespace-normal break-words leading-5" title={String(project.solicitante_nombre || project.solicitante || "")}>
                            {project.solicitante_nombre || project.solicitante}
                          </div>
                        </td>
                        <td className="whitespace-nowrap px-2 py-3 align-top">
                          <Badge className={`rounded-full px-2 py-1 text-[10px] uppercase tracking-[0.08em] ${approvalClass[project.aprobado] || "bg-slate-500 text-white"}`}>
                            {project.aprobado === "PD" ? "PENDIENTE" : project.aprobado === "RV" ? "REVISADO" : "APROBADO"}
                          </Badge>
                        </td>
                        <td className="px-2 py-3 align-top text-muted-foreground">
                          <div className="truncate" title={project.observaciones || "-"}>{project.observaciones || "-"}</div>
                        </td>
                        <td className="whitespace-nowrap px-2 py-3 align-top">
                          <Badge className={`rounded-full px-2 py-1 text-[10px] uppercase tracking-[0.08em] ${statusClass[project.estado] || "bg-slate-500 text-white"}`}>
                            {project.estado === "AC" ? "ACTIVO" : "INACTIVO"}
                          </Badge>
                        </td>
                        <td className="whitespace-nowrap px-2 py-3 text-center align-top">
                          <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                              <Button variant="ghost" size="sm" className="h-8 w-8 p-0 rounded-full">
                                <MoreHorizontal className="h-4 w-4" />
                              </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-72 rounded-2xl border border-border/70 bg-background/95 p-1 shadow-lg">
                              {canEditProject && (
                                <DropdownMenuItem
                                  className="flex items-center gap-2 rounded-xl px-3 py-2 cursor-pointer"
                                  onClick={() => openEdit(project)}
                                >
                                  <Pencil className="h-4 w-4 text-muted-foreground" />
                                  <span>Editar Proyecto</span>
                                </DropdownMenuItem>
                              )}
                              {canSyncProjectItems && (
                                <DropdownMenuItem className="flex items-center gap-2 rounded-xl px-3 py-2 cursor-pointer" onClick={() => openItems(project)}>
                                  <ListPlus className="h-4 w-4 text-muted-foreground" />
                                  <span>Agregar Items al Proyecto</span>
                                </DropdownMenuItem>
                              )}
                              {canViewHistory && (
                                <DropdownMenuItem className="flex items-center gap-2 rounded-xl px-3 py-2 cursor-pointer" onClick={() => openHistory(project)}>
                                  <History className="h-4 w-4 text-muted-foreground" />
                                  <span>Historial</span>
                                </DropdownMenuItem>
                              )}
                              {canManageTemplates && (
                                <DropdownMenuItem className="flex items-center gap-2 rounded-xl px-3 py-2 cursor-pointer" onClick={() => openTemplateModal(project)}>
                                  <Copy className="h-4 w-4 text-muted-foreground" />
                                  <span>Crear planilla desde este proyecto</span>
                                </DropdownMenuItem>
                              )}
                              {canViewBudgetByGroup && (
                                <DropdownMenuItem className="flex items-center gap-2 rounded-xl px-3 py-2 cursor-pointer" onClick={() => void handleOpenBudgetByGroupPdf(project)}>
                                  <Calculator className="h-4 w-4 text-muted-foreground" />
                                  <span>Presupuesto por Rubros</span>
                                </DropdownMenuItem>
                              )}
                              {canRecalculateBudget && (
                                <DropdownMenuItem className="flex items-center gap-2 rounded-xl px-3 py-2 cursor-pointer" onClick={() => openRecalculate(project)}>
                                  <RefreshCw className="h-4 w-4 text-muted-foreground" />
                                  <span>Recalcular Precio por Rubro</span>
                                </DropdownMenuItem>
                              )}
                              {canViewIncidenceSummary && (
                                <DropdownMenuItem className="flex items-center gap-2 rounded-xl px-3 py-2 cursor-pointer" onClick={() => openIncidenceSummary(project)}>
                                  <PieChart className="h-4 w-4 text-muted-foreground" />
                                  <span>Resumen por Insidencia</span>
                                </DropdownMenuItem>
                              )}
                              {canViewGeneralBudget && (
                                <DropdownMenuItem className="flex items-center gap-2 rounded-xl px-3 py-2 cursor-pointer" onClick={() => openGeneralBudget(project)}>
                                  <FileSpreadsheet className="h-4 w-4 text-muted-foreground" />
                                  <span>Presupuesto General</span>
                                </DropdownMenuItem>
                              )}
                              {canViewInputBreakdown && (
                                <DropdownMenuItem className="flex items-center gap-2 rounded-xl px-3 py-2 cursor-pointer" onClick={() => openInputBreakdown(project)}>
                                  <Layers className="h-4 w-4 text-muted-foreground" />
                                  <span>Desglose de Insumos del Proyecto</span>
                                </DropdownMenuItem>
                              )}
                              {canViewInputsReport && (
                                <DropdownMenuItem className="flex items-center gap-2 rounded-xl px-3 py-2 cursor-pointer" onClick={() => void handleOpenInputsReportPdf(project)}>
                                  <ClipboardList className="h-4 w-4 text-muted-foreground" />
                                  <span>Reporte de Insumos</span>
                                </DropdownMenuItem>
                              )}
                              {!hasProjectRowActions && (
                                <DropdownMenuItem className="flex items-center gap-2 rounded-xl px-3 py-2 text-muted-foreground" disabled>
                                  Sin acciones disponibles
                                </DropdownMenuItem>
                              )}
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

      {templateOpen && createPortal(
        <div className="fixed inset-0 z-[80] flex justify-end bg-slate-950/20 backdrop-blur-[1px]">
          <div className="w-full max-w-xl overflow-y-auto border-l border-border/70 bg-background/96 p-4 shadow-[0_0_60px_rgba(15,23,42,0.16)] backdrop-blur xl:p-6">
            <Card className="border border-border/70 bg-white/92 shadow-[0_24px_90px_rgba(15,23,42,0.08)]">
              <CardHeader className="border-b border-border/70 bg-muted/20">
                <div className="flex items-start justify-between gap-4">
                  <div>
                    <CardTitle className="text-2xl tracking-[-0.04em]">Crear planilla</CardTitle>
                    <CardDescription>{templateProject?.nombre_proyecto || "Define el nombre de la planilla."}</CardDescription>
                  </div>

                  <Button variant="ghost" size="icon-sm" className="rounded-full" onClick={closeTemplateModal}>
                    <X />
                  </Button>
                </div>
              </CardHeader>

              <CardContent className="p-5 sm:p-6">
                <form className="flex flex-col gap-5" onSubmit={handleTemplateSubmit}>
                  {templateStatus && (
                    <Alert variant="destructive" className="rounded-2xl">
                      <AlertDescription>{templateStatus.message}</AlertDescription>
                    </Alert>
                  )}

                  <div className="flex flex-col gap-2">
                    <Label htmlFor="template-name">Nombre de la planilla</Label>
                    <Input
                      id="template-name"
                      value={templateName}
                      onChange={(event) => setTemplateName(event.target.value)}
                      placeholder="Nombre de la planilla"
                      required
                    />
                  </div>

                  <div className="flex justify-end gap-3">
                    <Button type="button" variant="outline" onClick={closeTemplateModal}>Cancelar</Button>
                    <Button type="submit" disabled={templatePending}>
                      {templatePending ? (
                        <>
                          <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                          Creando
                        </>
                      ) : (
                        "Crear planilla"
                      )}
                    </Button>
                  </div>
                </form>
              </CardContent>
            </Card>
          </div>
        </div>,
        document.body,
      )}

      {historyOpen && createPortal(
        <div className="fixed inset-0 z-[80] flex justify-end bg-slate-950/20 backdrop-blur-[1px]">
          <div className="w-full max-w-3xl overflow-y-auto border-l border-border/70 bg-background/96 p-4 shadow-[0_0_60px_rgba(15,23,42,0.16)] backdrop-blur xl:p-6">
            <Card className="border border-border/70 bg-white/92 shadow-[0_24px_90px_rgba(15,23,42,0.08)]">
              <CardHeader className="border-b border-border/70 bg-muted/20">
                <div className="flex items-start justify-between gap-4">
                  <div>
                    <CardTitle className="flex items-center gap-2 text-2xl tracking-[-0.04em]">
                      <History className="h-5 w-5" />
                      Historial
                    </CardTitle>
                    <CardDescription>{historyProject?.nombre_proyecto || "Proyecto seleccionado"}</CardDescription>
                  </div>

                  <Button variant="ghost" size="icon-sm" className="rounded-full" onClick={closeHistory}>
                    <X />
                  </Button>
                </div>
              </CardHeader>

              <CardContent className="p-5 sm:p-6">
                <div className="grid gap-3 md:grid-cols-[1.2fr_1fr_1fr]">
                  <div className="flex flex-col gap-2">
                    <Label htmlFor="project-history-action">Accion</Label>
                    <select
                      id="project-history-action"
                      value={historyFilters.action}
                      onChange={(event) => updateHistoryFilter("action", event.target.value)}
                      className="h-10 rounded-xl border border-border/80 bg-background px-3 text-sm"
                    >
                      {historyActionOptions.map((option) => (
                        <option key={option.value || "all"} value={option.value}>{option.label}</option>
                      ))}
                    </select>
                  </div>

                  <div className="flex flex-col gap-2">
                    <Label htmlFor="project-history-from">Desde</Label>
                    <Input
                      id="project-history-from"
                      type="date"
                      value={historyFilters.dateFrom}
                      onChange={(event) => updateHistoryFilter("dateFrom", event.target.value)}
                      className="h-10 rounded-xl"
                    />
                  </div>

                  <div className="flex flex-col gap-2">
                    <Label htmlFor="project-history-to">Hasta</Label>
                    <Input
                      id="project-history-to"
                      type="date"
                      value={historyFilters.dateTo}
                      onChange={(event) => updateHistoryFilter("dateTo", event.target.value)}
                      className="h-10 rounded-xl"
                    />
                  </div>
                </div>

                <div className="mt-3 flex flex-col gap-3 md:flex-row md:items-end">
                  <div className="flex flex-1 flex-col gap-2">
                    <Label htmlFor="project-history-user">Usuario</Label>
                    <Input
                      id="project-history-user"
                      value={historyFilters.user}
                      onChange={(event) => updateHistoryFilter("user", event.target.value)}
                      placeholder="Nombre o ID de usuario"
                      className="h-10 rounded-xl"
                    />
                  </div>

                  <Button type="button" variant="outline" className="rounded-full" onClick={clearHistoryFilters}>
                    Limpiar filtros
                  </Button>
                </div>

                {historyIsError && (
                  <Alert variant="destructive" className="mt-4 rounded-2xl">
                    <AlertDescription>{historyError?.response?.data?.message || "No se pudo cargar el historial del proyecto."}</AlertDescription>
                  </Alert>
                )}

                <div className="mt-6 space-y-4">
                  {(historyLoading || historyFetching) && historyItems.length === 0 && (
                    <div className="flex items-center justify-center gap-2 rounded-2xl border border-dashed border-border/80 px-4 py-10 text-sm text-muted-foreground">
                      <Loader2 className="h-4 w-4 animate-spin" />
                      Cargando historial...
                    </div>
                  )}

                  {!historyLoading && historyItems.length === 0 && (
                    <div className="rounded-2xl border border-dashed border-border/80 px-4 py-10 text-center text-sm text-muted-foreground">
                      No hay movimientos registrados para este proyecto.
                    </div>
                  )}

                  {historyItems.map((entry) => (
                    <div key={entry.id} className="relative rounded-2xl border border-border/70 bg-background/85 p-4">
                      <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div className="min-w-0">
                          <div className="flex flex-wrap items-center gap-2">
                            <Badge className="rounded-full bg-slate-900 px-3 py-1 text-[11px] uppercase tracking-[0.18em] text-white">
                              {historyActionLabels[entry.action] || entry.action}
                            </Badge>
                            <span className="text-xs text-muted-foreground">{formatDateTime(entry.occurred_at)}</span>
                          </div>
                          <h3 className="mt-3 text-base font-semibold text-foreground">{entry.title}</h3>
                          <p className="mt-1 text-sm leading-6 text-muted-foreground">{entry.detail || "Sin detalle adicional."}</p>
                        </div>

                        <div className="shrink-0 text-left text-xs text-muted-foreground sm:text-right">
                          <div className="font-medium text-foreground">{entry.user_name || "Usuario no registrado"}</div>
                          <div>{entry.ip || "Sin IP"}</div>
                        </div>
                      </div>

                      {hasMetadata(entry.metadata) && (
                        <details className="mt-4 rounded-xl border border-border/70 bg-muted/20 p-3">
                          <summary className="cursor-pointer text-sm font-medium text-foreground">Ver detalles</summary>
                          <div className="mt-3">
                            {renderHistoryMetadata(entry)}
                          </div>
                        </details>
                      )}
                    </div>
                  ))}
                </div>

                <div className="mt-5 flex flex-col gap-3 text-sm text-muted-foreground sm:flex-row sm:items-center sm:justify-between">
                  <span>{historyMeta.total || 0} movimientos registrados</span>

                  <div className="flex items-center gap-2 self-end sm:self-auto">
                    <Button
                      variant="ghost"
                      size="sm"
                      className="rounded-full text-muted-foreground"
                      onClick={() => setHistoryPage((current) => Math.max(1, current - 1))}
                      disabled={historyMeta.current_page <= 1 || historyFetching}
                    >
                      <ChevronLeft data-icon="inline-start" />
                      Anterior
                    </Button>
                    <span className="min-w-16 text-center">Pag. {historyMeta.current_page}</span>
                    <Button
                      variant="ghost"
                      size="sm"
                      className="rounded-full text-muted-foreground"
                      onClick={() => setHistoryPage((current) => Math.min(historyTotalPages, current + 1))}
                      disabled={historyMeta.current_page >= historyTotalPages || historyFetching}
                    >
                      Siguiente
                      <ChevronRight data-icon="inline-end" />
                    </Button>
                  </div>
                </div>
              </CardContent>
            </Card>
          </div>
        </div>,
        document.body,
      )}

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
                    <Button type="submit" className="rounded-full bg-foreground text-background hover:bg-foreground/90" disabled={recalculatePending}>
                      {recalculatePending ? (
                        <>
                          <Loader2 className="mr-2 size-4 animate-spin" />
                          Generando PDF...
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

      {incidenceOpen && createPortal(
        <div className="fixed inset-0 z-[80] flex justify-end bg-slate-950/20 backdrop-blur-[1px]">
          <div className="w-full max-w-xl overflow-y-auto border-l border-border/70 bg-background/96 p-4 shadow-[0_0_60px_rgba(15,23,42,0.16)] backdrop-blur xl:p-6">
            <Card className="border border-border/70 bg-white/92 shadow-[0_24px_90px_rgba(15,23,42,0.08)]">
              <CardHeader className="border-b border-border/70 bg-muted/20">
                <div className="flex items-start justify-between gap-4">
                  <div>
                    <CardTitle className="text-2xl tracking-[-0.04em]">Resumen por incidencia</CardTitle>
                    <CardDescription>{incidenceProject?.nombre_proyecto || "Selecciona el formato del reporte."}</CardDescription>
                  </div>

                  <Button variant="ghost" size="icon-sm" className="rounded-full" onClick={closeIncidenceSummary}>
                    <X />
                  </Button>
                </div>
              </CardHeader>

              <CardContent className="p-5 sm:p-6">
                <form className="flex flex-col gap-5" onSubmit={handleIncidenceSummarySubmit}>
                  {incidenceStatus && (
                    <Alert variant="destructive" className="rounded-2xl">
                      <AlertDescription>{incidenceStatus.message}</AlertDescription>
                    </Alert>
                  )}

                  <div className="flex flex-col gap-2">
                    <Label htmlFor="incidence-format">Formato</Label>
                    <select
                      id="incidence-format"
                      value={incidenceFormat}
                      onChange={(event) => setIncidenceFormat(event.target.value)}
                      className="h-10 rounded-xl border border-border/80 bg-background px-3 text-sm"
                    >
                      <option value="PCA">PCA</option>
                      <option value="PC_FPS">PC_FPS</option>
                      <option value="PC_UPRE">PC_UPRE</option>
                      <option value="PC_FNDR">PC_FNDR</option>
                      <option value="PC_OBRAS">PC_OBRAS</option>
                    </select>
                  </div>

                  <div className="flex justify-end gap-3">
                    <Button type="button" variant="outline" onClick={closeIncidenceSummary}>Cancelar</Button>
                    <Button type="submit">Generar PDF</Button>
                  </div>
                </form>
              </CardContent>
            </Card>
          </div>
        </div>,
        document.body,
      )}

      {generalBudgetOpen && createPortal(
        <div className="fixed inset-0 z-[80] flex justify-end bg-slate-950/20 backdrop-blur-[1px]">
          <div className="w-full max-w-xl overflow-y-auto border-l border-border/70 bg-background/96 p-4 shadow-[0_0_60px_rgba(15,23,42,0.16)] backdrop-blur xl:p-6">
            <Card className="border border-border/70 bg-white/92 shadow-[0_24px_90px_rgba(15,23,42,0.08)]">
              <CardHeader className="border-b border-border/70 bg-muted/20">
                <div className="flex items-start justify-between gap-4">
                  <div>
                    <CardTitle className="text-2xl tracking-[-0.04em]">Presupuesto general</CardTitle>
                    <CardDescription>{generalBudgetProject?.nombre_proyecto || "Selecciona el formato del reporte."}</CardDescription>
                  </div>

                  <Button variant="ghost" size="icon-sm" className="rounded-full" onClick={closeGeneralBudget}>
                    <X />
                  </Button>
                </div>
              </CardHeader>

              <CardContent className="p-5 sm:p-6">
                <form className="flex flex-col gap-5" onSubmit={handleGeneralBudgetSubmit}>
                  {generalBudgetStatus && (
                    <Alert variant="destructive" className="rounded-2xl">
                      <AlertDescription>{generalBudgetStatus.message}</AlertDescription>
                    </Alert>
                  )}

                  <div className="flex flex-col gap-2">
                    <Label htmlFor="general-budget-format">Formato</Label>
                    <select
                      id="general-budget-format"
                      value={generalBudgetFormat}
                      onChange={(event) => setGeneralBudgetFormat(event.target.value)}
                      className="h-10 rounded-xl border border-border/80 bg-background px-3 text-sm"
                    >
                      <option value="PCA">PCA</option>
                      <option value="PC_FPS">PC_FPS</option>
                      <option value="PC_UPRE">PC_UPRE</option>
                      <option value="PC_FNDR">PC_FNDR</option>
                      <option value="PC_OBRAS">PC_OBRAS</option>
                    </select>
                  </div>

                  <div className="flex justify-end gap-3">
                    <Button type="button" variant="outline" onClick={closeGeneralBudget}>Cancelar</Button>
                    <Button type="submit">Generar PDF</Button>
                  </div>
                </form>
              </CardContent>
            </Card>
          </div>
        </div>,
        document.body,
      )}

      {inputBreakdownOpen && createPortal(
        <div className="fixed inset-0 z-[80] flex justify-end bg-slate-950/20 backdrop-blur-[1px]">
          <div className="w-full max-w-xl overflow-y-auto border-l border-border/70 bg-background/96 p-4 shadow-[0_0_60px_rgba(15,23,42,0.16)] backdrop-blur xl:p-6">
            <Card className="border border-border/70 bg-white/92 shadow-[0_24px_90px_rgba(15,23,42,0.08)]">
              <CardHeader className="border-b border-border/70 bg-muted/20">
                <div className="flex items-start justify-between gap-4">
                  <div>
                    <CardTitle className="text-2xl tracking-[-0.04em]">Desglose de insumos del proyecto</CardTitle>
                    <CardDescription>{inputBreakdownProject?.nombre_proyecto || "Selecciona el tipo de desglose."}</CardDescription>
                  </div>

                  <Button variant="ghost" size="icon-sm" className="rounded-full" onClick={closeInputBreakdown}>
                    <X />
                  </Button>
                </div>
              </CardHeader>

              <CardContent className="p-5 sm:p-6">
                <form className="flex flex-col gap-5" onSubmit={handleInputBreakdownSubmit}>
                  {inputBreakdownStatus && (
                    <Alert variant="destructive" className="rounded-2xl">
                      <AlertDescription>{inputBreakdownStatus.message}</AlertDescription>
                    </Alert>
                  )}

                  <div className="flex flex-col gap-2">
                    <Label htmlFor="input-breakdown-type">Tipo</Label>
                    <select
                      id="input-breakdown-type"
                      value={inputBreakdownType}
                      onChange={(event) => setInputBreakdownType(event.target.value)}
                      className="h-10 rounded-xl border border-border/80 bg-background px-3 text-sm"
                    >
                      <option value="1">Material</option>
                      <option value="2">Mano de Obra</option>
                      <option value="3">Maquinaria y Herramientas</option>
                    </select>
                  </div>

                  <div className="flex justify-end gap-3">
                    <Button type="button" variant="outline" onClick={closeInputBreakdown}>Cancelar</Button>
                    <Button type="submit">Generar PDF</Button>
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
