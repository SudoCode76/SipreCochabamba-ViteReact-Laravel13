import { Fragment, useMemo, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ChevronsUpDown, Loader2, Plus, Save, Trash2 } from "lucide-react";

import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { modulesService } from "@/modules/modules/services/modules.service";
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
  const queryClient = useQueryClient();
  const [format, setFormat] = useState("PCA");
  const [draft, setDraft] = useState(emptyDraft);
  const [rows, setRows] = useState([]);
  const [rowsDirty, setRowsDirty] = useState(false);
  const [search, setSearch] = useState("");
  const [itemComboboxOpen, setItemComboboxOpen] = useState(false);
  const [error, setError] = useState(null);

  const { data: projectData } = useQuery({
    queryKey: ["project", projectId],
    queryFn: () => projectService.show(projectId),
    enabled: Boolean(projectId),
  });

  const { data: itemsData, isLoading } = useQuery({
    queryKey: ["project-items", projectId, format],
    queryFn: () => projectService.items(projectId, format),
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

  const itemOptions = searchData?.data?.items ?? [];
  const selectedDetail = selectedItemData?.data?.item ?? null;
  const currentProject = projectData?.data?.project;
  const displayProjectName = currentProject?.nombre_proyecto || projectName || "-";
  const selectedOption = itemOptions.find((option) => Number(option.id) === Number(draft.itemId));
  const effectiveModuleId = draft.moduleId || (generalModule ? String(generalModule.id_modulo) : "");
  const selectedModule = moduleOptions.find((module) => Number(module.id_modulo) === Number(effectiveModuleId)) ?? generalModule;
  const effectiveRows = rowsDirty ? rows : queryRows;
  const effectivePrice = draft.precio || (selectedDetail?.precio != null ? String(selectedDetail.precio) : "");

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

  const handleOrder = () => {
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

  if (isLoading) {
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
            />
            <Button
              type="button"
              variant="ghost"
              size="icon-sm"
              className="absolute right-2 top-1/2 -translate-y-1/2 rounded-full text-muted-foreground"
              onClick={() => setItemComboboxOpen((current) => !current)}
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
          <Input id="cantidad" type="number" min="0" step="0.01" value={draft.cantidad} onChange={(event) => handleDraftChange("cantidad", event.target.value)} className="h-12 rounded-2xl border-border/80 bg-background/90" />
        </div>

        <div className="flex flex-col gap-2">
          <Label htmlFor="prioridad" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">Prioridad</Label>
          <Input id="prioridad" type="number" min="1" step="1" value={draft.prioridad} onChange={(event) => handleDraftChange("prioridad", event.target.value)} className="h-12 rounded-2xl border-border/80 bg-background/90" />
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
          <Button type="button" variant="outline" className="rounded-full border-emerald-200 bg-emerald-50 text-emerald-700" disabled>
            Imprimir Precios Unitarios
          </Button>
          <Button type="button" variant="outline" className="rounded-full border-slate-300 bg-slate-100 text-slate-700" onClick={handleOrder}>
            Ordenar
          </Button>
          <Button type="button" variant="outline" className="rounded-full border-emerald-200 bg-emerald-50 text-emerald-700" disabled>
            Imprimir Todas Las Especificaciones
          </Button>
        </div>
      </div>

      <div className="overflow-hidden rounded-2xl border border-border/70 bg-white shadow-sm">
        <div className="flex items-center justify-between bg-teal-900 px-4 py-3 text-white">
          <span className="text-sm font-semibold uppercase tracking-[0.18em]">Items agregados</span>
          <Button type="button" className="rounded-full bg-teal-500 text-white hover:bg-teal-400" onClick={handleAdd}>
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

                    return (
                      <tr key={rowKey} className="border-t border-border/60">
                        <td className="px-3 py-3">{row.prioridad ?? index + 1}</td>
                        <td className="px-3 py-3">{row.grupo?.nombre_grupo ?? "-"}</td>
                        <td className="px-3 py-3">{row.subgrupo?.descripcion ?? "-"}</td>
                        <td className="px-3 py-3">{row.item}</td>
                        <td className="px-3 py-3">{row.unidad?.abreviatura ?? row.unidad?.nombre_unidad_medida ?? "-"}</td>
                        <td className="px-3 py-3 text-right">{formatNumber(row.cantidad)}</td>
                        <td className="px-3 py-3 text-right">{formatNumber(row.precio, 2)}</td>
                        <td className="px-3 py-3 text-right">{formatNumber(partial, 2)}</td>
                        <td className="px-3 py-3">
                          <Button type="button" variant="outline" className="rounded-full" onClick={() => handleViewSpecification(row)}>Ver</Button>
                        </td>
                        <td className="px-3 py-3 text-center">{row.prioridad ?? index + 1}</td>
                        <td className="px-3 py-3 text-center">
                          <Button type="button" variant="ghost" className="rounded-full text-destructive hover:text-destructive" onClick={() => handleRemove(rowKey)}>
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
      </div>
    </form>
  );
}
