import { Fragment, useMemo, useState } from "react";
import { useNavigate } from "react-router-dom";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { AlertTriangle, ChevronsUpDown, GitCompare, History, Loader2, Plus, Save, Trash2, X } from "lucide-react";

import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { openPdfViewer } from "@/lib/utils/pdf";
import { modulesService } from "@/modules/modules/services/modules.service";
import { getProjectApprovalLabel } from "../lib/project-status";
import { projectService } from "../services/project.service";

const formatOptions = [
  { value: "PC_OBRAS", label: "OBRAS PUBLICAS" },
  { value: "PC_FPS", label: "FPS" },
  { value: "PC_FNDR", label: "FNDR" },
  { value: "PC_UPRE", label: "UPRE" },
  { value: "PCA", label: "PREDETERMINADO" },
];

const emptyDraft = {
  itemId: "",
  moduleId: "",
  cantidad: "1",
  prioridad: "",
  precio: "",
};

function createRowKey() {
  return `tmp-${Date.now()}-${Math.random().toString(36).slice(2)}`;
}

function formatNumber(value, digits = 2) {
  const numericValue = Number(value || 0);

  return new Intl.NumberFormat("es-BO", {
    minimumFractionDigits: digits,
    maximumFractionDigits: digits,
  }).format(Number.isFinite(numericValue) ? numericValue : 0);
}

function formatDate(value) {
  if (!value) {
    return "-";
  }

  return new Date(value).toLocaleDateString("es-BO");
}

function versionOptionLabel(version) {
  const date = version.version_created_at ? formatDate(version.version_created_at) : version.fecha;

  return `Versión ${version.version_number || 1} · ${date || "-"} · ${getProjectApprovalLabel(version.aprobado)}`;
}

function versionBadgeClass(project) {
  if (project?.is_frozen) {
    return "border-slate-300 bg-slate-100 text-slate-700";
  }

  if (project?.aprobado === "AP") {
    return "border-sky-200 bg-sky-50 text-sky-700";
  }

  return "border-emerald-200 bg-emerald-50 text-emerald-700";
}

function compareRowClass(type) {
  if (type === "added") {
    return "bg-emerald-50";
  }

  if (type === "removed") {
    return "bg-rose-50";
  }

  if (type === "modified") {
    return "bg-amber-50";
  }

  return "";
}

function compareLabel(type) {
  if (type === "added") {
    return "Agregado";
  }

  if (type === "removed") {
    return "Quitado";
  }

  if (type === "modified") {
    return "Modificado";
  }

  return "Sin cambios";
}

function buildRow(detail, draft, module) {
  const cantidad = Number(draft.cantidad || 0);
  const prioridad = draft.prioridad === "" ? null : Number(draft.prioridad);
  const precio = Number(draft.precio || detail?.precio || 0);

  return {
    client_row_id: createRowKey(),
    id_proyecto_item: null,
    id_item: detail.id_item,
    id_modulo: module?.id_modulo ? Number(module.id_modulo) : null,
    modulo: module ? {
      id_modulo: module.id_modulo,
      nombre_modulo: module.nombre_modulo,
    } : null,
    item: detail.item,
    prioridad,
    cantidad,
    precio,
    grupo: detail.nombre_grupo ? {
      id_grupo: detail.id_grupo,
      nombre_grupo: detail.nombre_grupo,
    } : null,
    subgrupo: detail.descripcion ? {
      id_subgrupo: detail.id_subgrupo,
      descripcion: detail.descripcion,
    } : null,
    unidad: detail.nombre_unidad_medida ? {
      id_unidad_medida: detail.id_unidad_medida,
      nombre_unidad_medida: detail.nombre_unidad_medida,
      abreviatura: detail.abreviatura_unidad_medida ?? detail.nombre_unidad_medida,
    } : null,
    especificacion: detail.especificacion,
    especificacion_url: detail.especificacion_url,
  };
}

export default function ProjectItemsForm({ projectId, projectName, onCancel, onSuccess }) {
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const [format, setFormat] = useState("PCA");
  const [draft, setDraft] = useState(emptyDraft);
  const [rows, setRows] = useState([]);
  const [rowsDirty, setRowsDirty] = useState(false);
  const [search, setSearch] = useState("");
  const [itemComboboxOpen, setItemComboboxOpen] = useState(false);
  const [compareOpen, setCompareOpen] = useState(false);
  const [compareBaseId, setCompareBaseId] = useState("");
  const [compareTargetId, setCompareTargetId] = useState("");
  const [showUnchanged, setShowUnchanged] = useState(false);
  const [error, setError] = useState(null);

  const { data: projectData } = useQuery({
    queryKey: ["project", projectId],
    queryFn: () => projectService.show(projectId),
    enabled: Boolean(projectId),
  });

  const { data: versionsData, isLoading: versionsLoading } = useQuery({
    queryKey: ["project-versions", projectId],
    queryFn: () => projectService.versions(projectId),
    enabled: Boolean(projectId),
  });

  const { data: itemsData, isLoading } = useQuery({
    queryKey: ["project-items", projectId, format],
    queryFn: () => projectService.items(projectId, format),
    enabled: Boolean(projectId),
  });

  const { data: warningData } = useQuery({
    queryKey: ["project-report-warnings", projectId],
    queryFn: () => projectService.reportWarnings(projectId),
    enabled: Boolean(projectId),
  });

  const { data: modulesData } = useQuery({
    queryKey: ["project-modules"],
    queryFn: modulesService.activeForProjects,
  });

  const { data: searchData, isFetching: isSearching } = useQuery({
    queryKey: ["project-item-search", search],
    queryFn: () => projectService.searchItems(search.trim()),
  });

  const { data: selectedItemData, isFetching: isLoadingItem } = useQuery({
    queryKey: ["project-item-detail", draft.itemId, format],
    queryFn: () => projectService.itemIncidencePrice(draft.itemId, format),
    enabled: Boolean(draft.itemId),
  });

  const { data: comparisonData, isFetching: isComparing, isError: compareFailed } = useQuery({
    queryKey: ["project-version-compare", projectId, compareBaseId, compareTargetId, format],
    queryFn: () => projectService.compareVersions(projectId, {
      base: compareBaseId,
      target: compareTargetId,
      format,
    }),
    enabled: compareOpen && Boolean(compareBaseId) && Boolean(compareTargetId) && compareBaseId !== compareTargetId,
  });

  const queryRows = useMemo(() => {
    const items = itemsData?.data?.items;

    if (!items) {
      return [];
    }

    return items.map((item) => ({
      ...item,
      client_row_id: item.id_proyecto_item ? `project-item-${item.id_proyecto_item}` : createRowKey(),
    }));
  }, [itemsData]);

  const moduleOptions = useMemo(
    () => (modulesData?.data?.items ?? []).filter((module) => module.estado === "AC"),
    [modulesData],
  );
  const generalModule = moduleOptions.find((module) => String(module.nombre_modulo || "").trim().toLowerCase() === "general") ?? moduleOptions[0] ?? null;

  const syncMutation = useMutation({
    mutationFn: (payload) => projectService.syncItems(projectId, payload),
    onSuccess: (response) => {
      const updatedProject = response?.data?.project;

      queryClient.invalidateQueries({ queryKey: ["project-items", projectId] });
      queryClient.invalidateQueries({ queryKey: ["project-report-warnings", projectId] });
      setRows([]);
      setRowsDirty(false);
      queryClient.setQueryData(["project", projectId], (current) => {
        if (!updatedProject) {
          return current;
        }

        if (!current?.data) {
          return { success: true, data: { project: updatedProject } };
        }

        return {
          ...current,
          data: {
            ...current.data,
            project: updatedProject,
          },
        };
      });

      queryClient.setQueriesData({ queryKey: ["projects"] }, (currentData) => {
        if (!currentData?.data?.items || !updatedProject) {
          return currentData;
        }

        return {
          ...currentData,
          data: {
            ...currentData.data,
            items: currentData.data.items.map((item) => (
              item.id_proyecto === updatedProject.id_proyecto
                ? { ...item, ...updatedProject }
                : item
            )),
          },
        };
      });

      onSuccess?.();
    },
    onError: (mutationError) => {
      const fieldErrors = mutationError.response?.data?.errors;
      const firstFieldError = fieldErrors ? Object.values(fieldErrors).flat().find(Boolean) : null;
      setError(firstFieldError || mutationError.response?.data?.message || "No se pudieron guardar los items del proyecto.");
    },
  });

  const excludeInputMutation = useMutation({
    mutationFn: (snapshotId) => projectService.excludeVersionInput(projectId, snapshotId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["project-items", projectId] });
      queryClient.invalidateQueries({ queryKey: ["project-report-warnings", projectId] });
      queryClient.invalidateQueries({ queryKey: ["project", projectId] });
      queryClient.invalidateQueries({ queryKey: ["projects"] });
    },
    onError: (mutationError) => {
      setError(mutationError.response?.data?.message || "No se pudo excluir el insumo de esta versión.");
    },
  });

  const itemOptions = searchData?.data?.items ?? [];
  const selectedDetail = selectedItemData?.data?.item ?? null;
  const currentProject = projectData?.data?.project;
  const versions = versionsData?.data?.items ?? [];
  const isReadOnly = Boolean(currentProject && (!currentProject.is_current_version || currentProject.is_frozen));
  const displayProjectName = currentProject?.nombre_proyecto || projectName || "-";
  const selectedOption = itemOptions.find((option) => Number(option.id) === Number(draft.itemId));
  const effectiveModuleId = draft.moduleId || (generalModule ? String(generalModule.id_modulo) : "");
  const selectedModule = moduleOptions.find((module) => Number(module.id_modulo) === Number(effectiveModuleId)) ?? generalModule;
  const effectiveRows = rowsDirty ? rows : queryRows;
  const reportWarningsSummary = warningData?.data?.summary;
  const hasSavedRows = queryRows.length > 0;
  const effectivePrice = draft.precio || (selectedDetail?.precio != null ? String(selectedDetail.precio) : "");
  const canCompareVersions = versions.length > 1;
  const currentVersionOption = versions.find((version) => Number(version.id_proyecto) === Number(projectId)) ?? currentProject;
  const comparison = comparisonData?.data;
  const comparisonRows = (comparison?.items ?? []).filter((item) => showUnchanged || item.change_type !== "unchanged");

  const total = useMemo(
    () => effectiveRows.reduce((acc, row) => acc + (Number(row.cantidad || 0) * Number(row.precio || 0)), 0),
    [effectiveRows],
  );

  const groupedRows = useMemo(() => {
    const groups = new Map();

    effectiveRows.forEach((row) => {
      const moduleId = row.id_modulo ?? "sin-modulo";
      const moduleName = row.modulo?.nombre_modulo ?? "Sin módulo";

      if (!groups.has(moduleId)) {
        groups.set(moduleId, {
          id: moduleId,
          name: moduleName,
          rows: [],
          subtotal: 0,
        });
      }

      const group = groups.get(moduleId);
      group.rows.push(row);
      group.subtotal += Number(row.cantidad || 0) * Number(row.precio || 0);
    });

    return Array.from(groups.values());
  }, [effectiveRows]);

  const handleDraftChange = (field, value) => {
    setError(null);
    setDraft((current) => ({ ...current, [field]: value }));
  };

  const handleItemSearchChange = (value) => {
    setSearch(value);
    setItemComboboxOpen(true);

    if (draft.itemId) {
      handleDraftChange("itemId", "");
    }
  };

  const handleItemSelect = (option) => {
    setSearch(option.text);
    setItemComboboxOpen(false);
    handleDraftChange("itemId", String(option.id));
  };

  const handleAdd = () => {
    if (isReadOnly) {
      return;
    }

    if (!selectedDetail) {
      setError("Selecciona un item para agregar al proyecto.");
      return;
    }

    if (!(Number(draft.cantidad) > 0)) {
      setError("La cantidad debe ser mayor a 0.");
      return;
    }

    if (!(Number(effectivePrice) >= 0)) {
      setError("El precio debe ser mayor o igual a 0.");
      return;
    }

    const nextRow = buildRow(selectedDetail, {
      ...draft,
      moduleId: effectiveModuleId,
      precio: effectivePrice,
    }, selectedModule);

    setRows([...effectiveRows, nextRow]);
    setRowsDirty(true);

    setDraft(emptyDraft);
    setSearch("");
    setItemComboboxOpen(false);
    setError(null);
  };

  const handleRemove = (rowKey) => {
    if (isReadOnly) {
      return;
    }

    setRows(effectiveRows.filter((row) => (row.id_proyecto_item ?? row.client_row_id) !== rowKey));
    setRowsDirty(true);
  };

  const handleViewSpecification = (row) => {
    if (!row.especificacion_url) {
      setError("No existe el PDF de especificacion para este item.");
      return;
    }

    setError(null);
    window.open(row.especificacion_url, "_blank", "noopener,noreferrer");
  };

  const runWithReportWarning = (action) => {
    if (!reportWarningsSummary?.has_warnings) {
      action();
      return;
    }

    const confirmed = window.confirm("Este proyecto tiene items o insumos desactivados. El reporte se generara con los datos registrados del proyecto. ¿Desea continuar?");

    if (confirmed) {
      action();
    }
  };

  const handlePrintUnitPrices = () => {
    setError(null);
    runWithReportWarning(() => {
      openPdfViewer(projectService.unitPricesPdfUrl(projectId, format), {
        chrome: false,
        errorMessage: "No se pudo generar el PDF de precios unitarios.",
      });
    });
  };

  const handlePrintSpecifications = () => {
    setError(null);
    runWithReportWarning(() => {
      openPdfViewer(projectService.specificationsPdfUrl(projectId), {
        title: "Especificaciones del proyecto",
        errorMessage: "No se pudo generar el PDF de especificaciones del proyecto.",
      });
    });
  };

  const handleVersionChange = (value) => {
    if (rowsDirty) {
      const confirmed = window.confirm("Hay cambios sin guardar. Si cambias de versión se perderán los cambios temporales de esta pantalla. ¿Deseas continuar?");

      if (!confirmed) {
        return;
      }
    }

    navigate(`/Proyecto/${value}/items`);
  };

  const handleOpenComparison = () => {
    if (!currentProject || versions.length < 2) {
      return;
    }

    const currentId = String(currentProject.id_proyecto);
    const previousVersion = versions.find((version) => Number(version.version_number) < Number(currentProject.version_number || 1))
      ?? versions.find((version) => Number(version.id_proyecto) !== Number(currentProject.id_proyecto));

    setCompareBaseId(currentId);
    setCompareTargetId(previousVersion ? String(previousVersion.id_proyecto) : "");
    setCompareOpen(true);
  };

  const handleOrder = () => {
    if (isReadOnly) {
      return;
    }

    setRows([...effectiveRows].sort((a, b) => {
      const left = a.prioridad ?? Number.MAX_SAFE_INTEGER;
      const right = b.prioridad ?? Number.MAX_SAFE_INTEGER;

      if (left !== right) {
        return left - right;
      }

      return String(a.item || "").localeCompare(String(b.item || ""));
    }));
    setRowsDirty(true);
  };

  const handleSubmit = async (event) => {
    event.preventDefault();

    if (isReadOnly) {
      return;
    }

    if (effectiveRows.length === 0) {
      setError("Agrega al menos un item antes de guardar.");
      return;
    }

    setError(null);
    await syncMutation.mutateAsync({
      items: effectiveRows.map((row, index) => ({
        id_item: row.id_item,
        id_proyecto_item: row.id_proyecto_item ?? undefined,
        id_modulo: row.id_modulo ?? (generalModule?.id_modulo ?? undefined),
        precio: Number(row.precio || 0),
        cantidad: Number(row.cantidad || 0),
        prioridad: row.prioridad ?? index + 1,
      })),
      format,
    });
  };

  if (isLoading || versionsLoading) {
    return (
      <div className="flex items-center justify-center p-10 text-muted-foreground">
        <Loader2 className="size-5 animate-spin" />
      </div>
    );
  }

  return (
    <form className="flex flex-col gap-6" onSubmit={handleSubmit}>
      {error && (
        <div className="rounded-xl border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive">
          {error}
        </div>
      )}

      <div className="grid gap-4 rounded-2xl border border-border/70 bg-muted/30 p-4 lg:grid-cols-[1fr_auto] lg:items-end">
        <div className="grid gap-4 md:grid-cols-[minmax(0,1fr)_auto] md:items-end">
          <div className="flex flex-col gap-2">
            <Label htmlFor="project-version" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">
              Versión del proyecto
            </Label>
            <select
              id="project-version"
              value={projectId}
              onChange={(event) => handleVersionChange(event.target.value)}
              className="h-12 w-full rounded-2xl border border-border/80 bg-background px-3 text-sm"
            >
              {versions.map((version) => (
                <option key={version.id_proyecto} value={version.id_proyecto}>
                  {versionOptionLabel(version)}
                </option>
              ))}
            </select>
          </div>

          {currentVersionOption && (
            <div className={`inline-flex h-12 items-center gap-2 rounded-full border px-4 text-sm font-semibold ${versionBadgeClass(currentVersionOption)}`}>
              <History className="size-4" />
              {currentVersionOption.is_current_version ? "Vigente" : "Histórica"}
              {currentVersionOption.is_frozen ? " · Congelada" : ""}
            </div>
          )}
        </div>

        <div className="flex flex-col items-start gap-2 lg:items-end">
          <Button
            type="button"
            variant="outline"
            className="rounded-full"
            onClick={handleOpenComparison}
            disabled={!canCompareVersions}
          >
            <GitCompare className="mr-2 size-4" />
            Comparar versiones
          </Button>
          {!canCompareVersions && (
            <span className="text-xs text-muted-foreground">Solo existe una versión.</span>
          )}
        </div>
      </div>

      {compareOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/45 p-4">
          <div className="flex max-h-[88vh] w-full max-w-6xl flex-col overflow-hidden rounded-3xl border border-border/80 bg-white shadow-2xl">
            <div className="flex items-start justify-between gap-4 border-b border-border/70 px-5 py-4">
              <div>
                <h2 className="text-xl font-semibold tracking-[-0.03em]">Comparar versiones</h2>
                <p className="text-sm text-muted-foreground">Diferencias de ítems, cantidades, precios y subtotales.</p>
              </div>
              <Button type="button" variant="ghost" size="icon" className="rounded-full" onClick={() => setCompareOpen(false)}>
                <X className="size-5" />
              </Button>
            </div>

            <div className="flex flex-col gap-4 overflow-y-auto p-5">
              <div className="grid gap-4 md:grid-cols-[1fr_1fr_auto] md:items-end">
                <div className="flex flex-col gap-2">
                  <Label htmlFor="compare-base" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">Base</Label>
                  <select
                    id="compare-base"
                    value={compareBaseId}
                    onChange={(event) => setCompareBaseId(event.target.value)}
                    className="h-12 rounded-2xl border border-border/80 bg-background px-3 text-sm"
                  >
                    {versions.map((version) => (
                      <option key={version.id_proyecto} value={version.id_proyecto}>
                        {versionOptionLabel(version)}
                      </option>
                    ))}
                  </select>
                </div>

                <div className="flex flex-col gap-2">
                  <Label htmlFor="compare-target" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">Comparar con</Label>
                  <select
                    id="compare-target"
                    value={compareTargetId}
                    onChange={(event) => setCompareTargetId(event.target.value)}
                    className="h-12 rounded-2xl border border-border/80 bg-background px-3 text-sm"
                  >
                    {versions.map((version) => (
                      <option key={version.id_proyecto} value={version.id_proyecto}>
                        {versionOptionLabel(version)}
                      </option>
                    ))}
                  </select>
                </div>

                <label className="flex h-12 items-center gap-2 rounded-2xl border border-border/80 px-4 text-sm text-muted-foreground">
                  <input
                    type="checkbox"
                    checked={showUnchanged}
                    onChange={(event) => setShowUnchanged(event.target.checked)}
                    className="size-4"
                  />
                  Ver sin cambios
                </label>
              </div>

              {compareBaseId === compareTargetId && (
                <div className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                  Selecciona dos versiones distintas para comparar.
                </div>
              )}

              {isComparing && (
                <div className="flex items-center justify-center gap-2 rounded-xl border border-border/70 px-4 py-8 text-muted-foreground">
                  <Loader2 className="size-5 animate-spin" />
                  Comparando versiones...
                </div>
              )}

              {compareFailed && (
                <div className="rounded-xl border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive">
                  No se pudo comparar las versiones seleccionadas.
                </div>
              )}

              {comparison && compareBaseId !== compareTargetId && !isComparing && (
                <>
                  <div className="grid gap-3 md:grid-cols-4">
                    <div className="rounded-2xl border border-border/70 bg-background p-4">
                      <span className="text-xs font-semibold uppercase tracking-[0.16em] text-muted-foreground">Total base</span>
                      <p className="mt-2 text-xl font-semibold">Bs {formatNumber(comparison.summary.base_total, 2)}</p>
                    </div>
                    <div className="rounded-2xl border border-border/70 bg-background p-4">
                      <span className="text-xs font-semibold uppercase tracking-[0.16em] text-muted-foreground">Total comparación</span>
                      <p className="mt-2 text-xl font-semibold">Bs {formatNumber(comparison.summary.target_total, 2)}</p>
                    </div>
                    <div className="rounded-2xl border border-border/70 bg-background p-4">
                      <span className="text-xs font-semibold uppercase tracking-[0.16em] text-muted-foreground">Diferencia</span>
                      <p className="mt-2 text-xl font-semibold">Bs {formatNumber(comparison.summary.difference, 2)}</p>
                    </div>
                    <div className="rounded-2xl border border-border/70 bg-background p-4">
                      <span className="text-xs font-semibold uppercase tracking-[0.16em] text-muted-foreground">Variación</span>
                      <p className="mt-2 text-xl font-semibold">
                        {comparison.summary.difference_percent == null ? "-" : `${formatNumber(comparison.summary.difference_percent, 2)}%`}
                      </p>
                    </div>
                  </div>

                  <div className="overflow-hidden rounded-2xl border border-border/70">
                    <div className="overflow-x-auto">
                      <table className="min-w-full text-sm">
                        <thead className="bg-slate-100 text-slate-900">
                          <tr>
                            <th className="px-3 py-3 text-left">Cambio</th>
                            <th className="px-3 py-3 text-left">Item</th>
                            <th className="px-3 py-3 text-left">Módulo</th>
                            <th className="px-3 py-3 text-right">Cant. base</th>
                            <th className="px-3 py-3 text-right">Cant. comp.</th>
                            <th className="px-3 py-3 text-right">Precio base</th>
                            <th className="px-3 py-3 text-right">Precio comp.</th>
                            <th className="px-3 py-3 text-right">Dif. subtotal</th>
                          </tr>
                        </thead>
                        <tbody>
                          {comparisonRows.length === 0 ? (
                            <tr>
                              <td colSpan={8} className="px-4 py-8 text-center text-muted-foreground">No hay diferencias para mostrar.</td>
                            </tr>
                          ) : comparisonRows.map((row) => (
                            <tr key={row.key} className={`border-t border-border/60 ${compareRowClass(row.change_type)}`}>
                              <td className="px-3 py-3 font-semibold">{compareLabel(row.change_type)}</td>
                              <td className="px-3 py-3">{row.item}</td>
                              <td className="px-3 py-3">{row.target?.modulo ?? row.base?.modulo ?? "-"}</td>
                              <td className="px-3 py-3 text-right">{row.base ? formatNumber(row.base.cantidad, 2) : "-"}</td>
                              <td className="px-3 py-3 text-right">{row.target ? formatNumber(row.target.cantidad, 2) : "-"}</td>
                              <td className="px-3 py-3 text-right">{row.base ? formatNumber(row.base.precio, 2) : "-"}</td>
                              <td className="px-3 py-3 text-right">{row.target ? formatNumber(row.target.precio, 2) : "-"}</td>
                              <td className="px-3 py-3 text-right">{formatNumber(row.diff.subtotal, 2)}</td>
                            </tr>
                          ))}
                        </tbody>
                      </table>
                    </div>
                  </div>
                </>
              )}
            </div>
          </div>
        </div>
      )}

      {reportWarningsSummary?.has_warnings && (
        <div className="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
          <div className="flex items-start gap-2">
            <AlertTriangle className="mt-0.5 size-4 shrink-0" />
            <div>
              <p className="font-semibold">Este proyecto tiene elementos desactivados.</p>
              <p>
                Hay {reportWarningsSummary.items_count} item(s) y {reportWarningsSummary.inputs_count} insumo(s) que ya no estan activos. Los reportes usaran los datos registrados del proyecto.
              </p>
            </div>
          </div>
        </div>
      )}

      {isReadOnly && (
        <div className="rounded-xl border border-slate-300 bg-slate-100 px-4 py-3 text-sm text-slate-800">
          <p className="font-semibold">Versión {currentProject?.version_number} congelada</p>
          <p>Los ítems son de solo lectura. Las acciones de PDF y especificaciones continúan disponibles.</p>
        </div>
      )}

      <div className="grid gap-4 lg:grid-cols-[1.35fr_0.65fr]">
        <div className="rounded-2xl border border-border/70 bg-background/70 px-4 py-3">
          <span className="text-xs font-semibold uppercase tracking-[0.18em] text-muted-foreground">Proyecto</span>
          <p className="mt-2 text-base font-semibold text-foreground">{displayProjectName}</p>
        </div>

        <div className="rounded-2xl border border-slate-300 bg-slate-500 px-4 py-3 text-center text-white shadow-sm">
          <span className="text-xs font-semibold uppercase tracking-[0.18em] text-slate-100">Precio actual proyecto</span>
          <p className="mt-2 text-2xl font-semibold">Bs {formatNumber(total)}</p>
        </div>
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        <div className="flex flex-col gap-2">
          <Label className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">Grupo</Label>
          <Input value={selectedDetail?.nombre_grupo ?? ""} className="h-12 rounded-2xl border-border/80 bg-background/90" disabled readOnly />
        </div>

        <div className="flex flex-col gap-2">
          <Label className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">Subgrupo</Label>
          <Input value={selectedDetail?.descripcion ?? ""} className="h-12 rounded-2xl border-border/80 bg-background/90" disabled readOnly />
        </div>
      </div>

      <div className="grid gap-4 lg:grid-cols-[0.75fr_1.3fr_0.55fr_0.55fr_0.55fr_0.55fr]">
        <div className="flex flex-col gap-2">
          <Label htmlFor="modulo" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">Módulo</Label>
          <select
            id="modulo"
            value={effectiveModuleId}
            onChange={(event) => handleDraftChange("moduleId", event.target.value)}
            className="h-12 rounded-2xl border border-border/80 bg-background/90 px-4"
            disabled={isReadOnly}
          >
            {moduleOptions.map((module) => (
              <option key={module.id_modulo} value={module.id_modulo}>
                {module.nombre_modulo}
              </option>
            ))}
          </select>
        </div>

        <div className="flex flex-col gap-2">
          <Label htmlFor="project-item-search" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">Item</Label>
          <div className="relative">
            <Input
              id="project-item-search"
              role="combobox"
              aria-expanded={itemComboboxOpen}
              aria-controls="project-item-options"
              value={search || selectedOption?.text || ""}
              onChange={(event) => handleItemSearchChange(event.target.value)}
              onFocus={() => setItemComboboxOpen(true)}
              placeholder="Buscar item..."
              className="h-12 rounded-2xl border-border/80 bg-background/90 pr-11"
              disabled={isReadOnly}
            />
            <Button
              type="button"
              variant="ghost"
              size="icon-sm"
              className="absolute right-2 top-1/2 -translate-y-1/2 rounded-full text-muted-foreground"
              onClick={() => setItemComboboxOpen((current) => !current)}
              disabled={isReadOnly}
            >
              <ChevronsUpDown className="size-4" />
            </Button>

            {itemComboboxOpen && (
              <div
                id="project-item-options"
                className="absolute z-30 mt-2 max-h-64 w-full overflow-y-auto rounded-2xl border border-border/80 bg-white p-1 shadow-xl"
              >
                {isSearching && (
                  <div className="px-3 py-2 text-sm text-muted-foreground">Buscando items...</div>
                )}

                {!isSearching && itemOptions.length === 0 && (
                  <div className="px-3 py-2 text-sm text-muted-foreground">No se encontraron items.</div>
                )}

                {!isSearching && itemOptions.map((option) => (
                  <button
                    key={option.id}
                    type="button"
                    className={`w-full rounded-xl px-3 py-2 text-left text-sm hover:bg-muted ${Number(option.id) === Number(draft.itemId) ? "bg-muted font-semibold" : ""}`}
                    onMouseDown={(event) => event.preventDefault()}
                    onClick={() => handleItemSelect(option)}
                  >
                    {option.text}
                  </button>
                ))}
              </div>
            )}
          </div>
        </div>

        <div className="flex flex-col gap-2">
          <Label htmlFor="precio" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">Precio</Label>
          <Input
            id="precio"
            type="number"
            min="0"
            step="0.0001"
            value={effectivePrice}
            className="h-12 rounded-2xl border-border/80 bg-muted/40 text-muted-foreground"
            disabled
            readOnly
          />
        </div>

        <div className="flex flex-col gap-2">
          <Label htmlFor="cantidad" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">Cantidad</Label>
          <Input id="cantidad" type="number" min="0" step="0.01" value={draft.cantidad} onChange={(event) => handleDraftChange("cantidad", event.target.value)} className="h-12 rounded-2xl border-border/80 bg-background/90" disabled={isReadOnly} />
        </div>

        <div className="flex flex-col gap-2">
          <Label htmlFor="prioridad" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">Prioridad</Label>
          <Input id="prioridad" type="number" min="1" step="1" value={draft.prioridad} onChange={(event) => handleDraftChange("prioridad", event.target.value)} className="h-12 rounded-2xl border-border/80 bg-background/90" disabled={isReadOnly} />
        </div>

        <div className="flex flex-col gap-2">
          <Label className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">Unidad de medida</Label>
          <Input value={selectedDetail?.abreviatura_unidad_medida ?? ""} className="h-12 rounded-2xl border-border/80 bg-background/90" disabled readOnly />
          {isLoadingItem && <span className="text-xs text-muted-foreground">Cargando precio actual...</span>}
        </div>
      </div>

      <div className="flex flex-col gap-4 rounded-2xl border border-border/70 bg-background/70 p-4 xl:flex-row xl:items-center xl:justify-between">
        <div className="flex flex-wrap gap-4">
          {formatOptions.map((option) => (
            <label key={option.value} className="flex items-center gap-2 text-sm text-muted-foreground">
              <input
                type="radio"
                name="project-format"
                value={option.value}
                checked={format === option.value}
                onChange={(event) => setFormat(event.target.value)}
                className="size-4"
              />
              <span>{option.label}</span>
            </label>
          ))}
        </div>

        <div className="flex flex-wrap gap-3">
          <Button type="button" variant="outline" className="rounded-full border-emerald-200 bg-emerald-50 text-emerald-700" onClick={handlePrintUnitPrices} disabled={!hasSavedRows}>
            Imprimir Precios Unitarios
          </Button>
          <Button type="button" variant="outline" className="rounded-full border-slate-300 bg-slate-100 text-slate-700" onClick={handleOrder} disabled={isReadOnly}>
            Ordenar
          </Button>
          <Button type="button" variant="outline" className="rounded-full border-emerald-200 bg-emerald-50 text-emerald-700" onClick={handlePrintSpecifications} disabled={!hasSavedRows}>
            Imprimir Todas Las Especificaciones
          </Button>
        </div>
        {rowsDirty && (
          <p className="text-sm font-medium text-amber-700">
            Guarda los ítems antes de imprimir para incluir los cambios.
          </p>
        )}
      </div>

      <div className="overflow-hidden rounded-2xl border border-border/70 bg-white shadow-sm">
        <div className="flex items-center justify-between bg-teal-900 px-4 py-3 text-white">
          <span className="text-sm font-semibold uppercase tracking-[0.18em]">Items agregados</span>
          <Button type="button" className="rounded-full bg-teal-500 text-white hover:bg-teal-400" onClick={handleAdd} disabled={isReadOnly}>
            <Plus className="mr-2 size-4" />
            Agregar
          </Button>
        </div>

        <div className="overflow-x-auto">
          <table className="min-w-full text-sm">
            <thead className="bg-slate-100 text-slate-900">
              <tr>
                <th className="px-3 py-3 text-left font-semibold">Prioridad</th>
                <th className="px-3 py-3 text-left font-semibold">Grupo</th>
                <th className="px-3 py-3 text-left font-semibold">Subgrupo</th>
                <th className="px-3 py-3 text-left font-semibold">Item</th>
                <th className="px-3 py-3 text-left font-semibold">Unidad</th>
                <th className="px-3 py-3 text-right font-semibold">Cantidad</th>
                <th className="px-3 py-3 text-right font-semibold">Unitario</th>
                <th className="px-3 py-3 text-right font-semibold">Parcial</th>
                <th className="px-3 py-3 text-left font-semibold">Ver especificacion</th>
                <th className="px-3 py-3 text-center font-semibold">Orden</th>
                <th className="px-3 py-3 text-center font-semibold">Quitar</th>
              </tr>
            </thead>
            <tbody>
              {effectiveRows.length === 0 ? (
                <tr>
                  <td colSpan={11} className="px-4 py-8 text-center text-muted-foreground">No hay items agregados al proyecto.</td>
                </tr>
              ) : groupedRows.map((group) => (
                <Fragment key={`module-${group.id}`}>
                  <tr className="border-t border-border/60 bg-teal-800 text-white">
                    <td colSpan={7} className="px-3 py-3 font-semibold uppercase tracking-[0.12em]">{group.name}</td>
                    <td className="px-3 py-3 text-right font-semibold">{formatNumber(group.subtotal, 2)}</td>
                    <td colSpan={3} className="px-3 py-3" />
                  </tr>
                  {group.rows.map((row, index) => {
                    const partial = Number(row.cantidad || 0) * Number(row.precio || 0);
                    const rowKey = row.id_proyecto_item ?? row.client_row_id;
                    const inputWarnings = row.warnings?.inputs ?? [];
                    const rowHasWarnings = Boolean(row.has_warnings);

                    return (
                      <tr key={rowKey} className={`border-t border-border/60 ${rowHasWarnings ? "bg-rose-50" : ""}`}>
                        <td className="px-3 py-3">{row.prioridad ?? index + 1}</td>
                        <td className="px-3 py-3">{row.grupo?.nombre_grupo ?? "-"}</td>
                        <td className="px-3 py-3">{row.subgrupo?.descripcion ?? "-"}</td>
                        <td className="px-3 py-3">
                          <div className="flex flex-col gap-1">
                            <span className={rowHasWarnings ? "font-semibold text-rose-700" : ""}>{row.item}</span>
                            {row.warnings?.item && (
                              <span className="inline-flex w-fit items-center rounded-full bg-rose-600 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-[0.12em] text-white">
                                Item {row.warnings.item.status_label}
                              </span>
                            )}
                            {inputWarnings.length > 0 && (
                              <div className="flex flex-col gap-1">
                                <span className="text-xs font-medium text-rose-700">
                                  {inputWarnings.length} insumo(s) desactivado(s) o eliminado(s)
                                </span>
                                {!isReadOnly && inputWarnings.map((warning) => (
                                  <button
                                    key={warning.id_snapshot}
                                    type="button"
                                    className="w-fit text-left text-xs font-semibold text-rose-700 underline underline-offset-2"
                                    onClick={() => excludeInputMutation.mutate(warning.id_snapshot)}
                                    disabled={excludeInputMutation.isPending}
                                  >
                                    Excluir {warning.description} de esta versión
                                  </button>
                                ))}
                              </div>
                            )}
                          </div>
                        </td>
                        <td className="px-3 py-3">{row.unidad?.abreviatura ?? row.unidad?.nombre_unidad_medida ?? "-"}</td>
                        <td className="px-3 py-3 text-right">{formatNumber(row.cantidad)}</td>
                        <td className="px-3 py-3 text-right">{formatNumber(row.precio, 2)}</td>
                        <td className="px-3 py-3 text-right">{formatNumber(partial, 2)}</td>
                        <td className="px-3 py-3">
                          <Button type="button" variant="outline" className="rounded-full" onClick={() => handleViewSpecification(row)}>Ver</Button>
                        </td>
                        <td className="px-3 py-3 text-center">{row.prioridad ?? index + 1}</td>
                        <td className="px-3 py-3 text-center">
                          <Button type="button" variant="ghost" className="rounded-full text-destructive hover:text-destructive" onClick={() => handleRemove(rowKey)} disabled={isReadOnly}>
                            <Trash2 className="size-4" />
                          </Button>
                        </td>
                      </tr>
                    );
                  })}
                </Fragment>
              ))}
            </tbody>
            <tfoot>
              <tr className="bg-slate-400/70 font-semibold text-slate-950">
                <td colSpan={7} className="px-3 py-4 text-left">Total</td>
                <td className="px-3 py-4 text-right">{formatNumber(total, 2)}</td>
                <td colSpan={3} className="px-3 py-4" />
              </tr>
            </tfoot>
          </table>
        </div>
      </div>

      <div className="flex justify-center gap-3">
        <Button type="button" variant="outline" className="rounded-full border-border/70 bg-background/80" onClick={onCancel}>
          Cancelar
        </Button>
        {!isReadOnly && (
        <Button type="submit" className="rounded-full bg-teal-600 text-white hover:bg-teal-500" disabled={syncMutation.isPending}>
          {syncMutation.isPending ? (
            <>
              <Loader2 className="mr-2 size-4 animate-spin" />
              Guardando...
            </>
          ) : (
            <>
              <Save className="mr-2 size-4" />
              Guardar
            </>
          )}
        </Button>
        )}
      </div>
    </form>
  );
}
