import { useMemo, useState } from "react";
import { createPortal } from "react-dom";
import { ChevronLeft, ChevronRight, Loader2, Package, Search, MoreHorizontal, Pencil, Package2, Users, Wrench, FileText, TrendingUp, RefreshCw, BarChart3, Hammer, Trash2, X, Plus, FileDown } from "lucide-react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";

import { Badge } from "@/components/ui/badge";
import { Alert, AlertDescription } from "@/components/ui/alert";
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
import { downloadUrl, openPdfViewer } from "@/lib/utils/pdf";
import { itemsService } from "@/modules/dashboard/services/items.service";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";

const statusClass = {
  AC: "bg-emerald-600 text-white",
  DC: "bg-rose-600 text-white",
};

const collectValidationMessages = (error, fallback) => {
  const errors = error?.response?.data?.errors;

  if (errors && typeof errors === "object") {
    return Object.values(errors).flat().filter(Boolean);
  }

  return [error?.response?.data?.message || error?.message || fallback];
};

export default function ItemsPage() {
  const queryClient = useQueryClient();
  const [perPage, setPerPage] = useState(10);
  const [order, setOrder] = useState("legacy");
  const [search, setSearch] = useState("");
  const [searchQuery, setSearchQuery] = useState("");
  const [page, setPage] = useState(1);
  const [reportLoadingItemId, setReportLoadingItemId] = useState(null);
  const [reportFeedback, setReportFeedback] = useState(null);
  const [exportChoice, setExportChoice] = useState(null);
  const [createOpen, setCreateOpen] = useState(false);
  const [createGroupId, setCreateGroupId] = useState("");
  const [createUnitId, setCreateUnitId] = useState("");
  const [unitSearch, setUnitSearch] = useState("");
  const [unitComboboxOpen, setUnitComboboxOpen] = useState(false);
  const [createErrors, setCreateErrors] = useState([]);
  const [editOpen, setEditOpen] = useState(false);
  const [editItem, setEditItem] = useState(null);
  const [editGroupId, setEditGroupId] = useState("");
  const [editSubgroupId, setEditSubgroupId] = useState("");
  const [editUnitId, setEditUnitId] = useState("");
  const [editUnitSearch, setEditUnitSearch] = useState("");
  const [editUnitComboboxOpen, setEditUnitComboboxOpen] = useState(false);
  const [editErrors, setEditErrors] = useState([]);
  const [deactivateImpactOpen, setDeactivateImpactOpen] = useState(false);
  const [pendingDeactivatePayload, setPendingDeactivatePayload] = useState(null);
  const [materialsOpen, setMaterialsOpen] = useState(false);
  const [materialsItem, setMaterialsItem] = useState(null);
  const [materialSearch, setMaterialSearch] = useState("");
  const [selectedMaterialId, setSelectedMaterialId] = useState("");
  const [materialComboboxOpen, setMaterialComboboxOpen] = useState(false);
  const [draftMaterials, setDraftMaterials] = useState([]);
  const [materialDraftDirty, setMaterialDraftDirty] = useState(false);
  const [deletedMaterialInputIds, setDeletedMaterialInputIds] = useState([]);
  const [laborOpen, setLaborOpen] = useState(false);
  const [laborItem, setLaborItem] = useState(null);
  const [laborSearch, setLaborSearch] = useState("");
  const [selectedLaborId, setSelectedLaborId] = useState("");
  const [laborComboboxOpen, setLaborComboboxOpen] = useState(false);
  const [draftLabor, setDraftLabor] = useState([]);
  const [laborDraftDirty, setLaborDraftDirty] = useState(false);
  const [deletedLaborInputIds, setDeletedLaborInputIds] = useState([]);
  const [machineryOpen, setMachineryOpen] = useState(false);
  const [machineryItem, setMachineryItem] = useState(null);
  const [machinerySearch, setMachinerySearch] = useState("");
  const [selectedMachineryId, setSelectedMachineryId] = useState("");
  const [machineryComboboxOpen, setMachineryComboboxOpen] = useState(false);
  const [draftMachinery, setDraftMachinery] = useState([]);
  const [machineryDraftDirty, setMachineryDraftDirty] = useState(false);
  const [deletedMachineryInputIds, setDeletedMachineryInputIds] = useState([]);
  const [filesOpen, setFilesOpen] = useState(false);
  const [filesItem, setFilesItem] = useState(null);
  const [recalculateOpen, setRecalculateOpen] = useState(false);
  const [recalculateItem, setRecalculateItem] = useState(null);
  const [recalculateDate, setRecalculateDate] = useState("");
  const [breakdownOpen, setBreakdownOpen] = useState(false);
  const [breakdownItem, setBreakdownItem] = useState(null);
  const [breakdownDate, setBreakdownDate] = useState("");
  const [breakdownType, setBreakdownType] = useState("");

  const openCreate = () => {
    setCreateOpen(true);
    setCreateGroupId("");
    setCreateUnitId("");
    setUnitSearch("");
    setUnitComboboxOpen(false);
    setCreateErrors([]);
  };

  const closeCreate = () => {
    setCreateOpen(false);
    setCreateGroupId("");
    setCreateUnitId("");
    setUnitSearch("");
    setUnitComboboxOpen(false);
    setCreateErrors([]);
  };

  const handleEdit = (item) => {
    const unitLabel = item.unit_measure
      ? `${item.unit_measure.description ?? ""}${item.unit_measure.abbreviation ? ` (${item.unit_measure.abbreviation})` : ""}`
      : "";

    setEditItem(item);
    setEditGroupId(item.group?.id ? String(item.group.id) : "");
    setEditSubgroupId(item.subgroup?.id ? String(item.subgroup.id) : "");
    setEditUnitId(item.unit_measure?.id ? String(item.unit_measure.id) : "");
    setEditUnitSearch(unitLabel.trim());
    setEditUnitComboboxOpen(false);
    setEditErrors([]);
    setEditOpen(true);
  };

  const closeEdit = () => {
    setEditOpen(false);
    setEditItem(null);
    setEditGroupId("");
    setEditSubgroupId("");
    setEditUnitId("");
    setEditUnitSearch("");
    setEditUnitComboboxOpen(false);
    setEditErrors([]);
    setDeactivateImpactOpen(false);
    setPendingDeactivatePayload(null);
  };

  const openMaterials = (item) => {
    setMaterialsItem(item);
    setMaterialsOpen(true);
    setMaterialSearch("");
    setSelectedMaterialId("");
    setMaterialComboboxOpen(false);
    setDraftMaterials([]);
    setMaterialDraftDirty(false);
    setDeletedMaterialInputIds([]);
  };

  const closeMaterials = () => {
    setMaterialsOpen(false);
    setMaterialsItem(null);
    setMaterialSearch("");
    setSelectedMaterialId("");
    setMaterialComboboxOpen(false);
    setDraftMaterials([]);
    setMaterialDraftDirty(false);
    setDeletedMaterialInputIds([]);
  };

  const openLabor = (item) => {
    setLaborItem(item);
    setLaborOpen(true);
    setLaborSearch("");
    setSelectedLaborId("");
    setLaborComboboxOpen(false);
    setDraftLabor([]);
    setLaborDraftDirty(false);
    setDeletedLaborInputIds([]);
  };

  const closeLabor = () => {
    setLaborOpen(false);
    setLaborItem(null);
    setLaborSearch("");
    setSelectedLaborId("");
    setLaborComboboxOpen(false);
    setDraftLabor([]);
    setLaborDraftDirty(false);
    setDeletedLaborInputIds([]);
  };

  const openMachinery = (item) => {
    setMachineryItem(item);
    setMachineryOpen(true);
    setMachinerySearch("");
    setSelectedMachineryId("");
    setMachineryComboboxOpen(false);
    setDraftMachinery([]);
    setMachineryDraftDirty(false);
    setDeletedMachineryInputIds([]);
  };

  const closeMachinery = () => {
    setMachineryOpen(false);
    setMachineryItem(null);
    setMachinerySearch("");
    setSelectedMachineryId("");
    setMachineryComboboxOpen(false);
    setDraftMachinery([]);
    setMachineryDraftDirty(false);
    setDeletedMachineryInputIds([]);
  };

  const openFiles = (item) => {
    setFilesItem(item);
    setFilesOpen(true);
  };

  const closeFiles = () => {
    setFilesOpen(false);
    setFilesItem(null);
  };

  const openRecalculate = (item) => {
    setRecalculateItem(item);
    setRecalculateDate("");
    setRecalculateOpen(true);
  };

  const closeRecalculate = () => {
    setRecalculateOpen(false);
    setRecalculateItem(null);
    setRecalculateDate("");
  };

  const openBreakdown = (item) => {
    setBreakdownItem(item);
    setBreakdownDate("");
    setBreakdownType("");
    setBreakdownOpen(true);
  };

  const closeBreakdown = () => {
    setBreakdownOpen(false);
    setBreakdownItem(null);
    setBreakdownDate("");
    setBreakdownType("");
  };

  const { data, isLoading, isError, error, isFetching } = useQuery({
    queryKey: ["items", { page, perPage, search, order }],
    queryFn: () => itemsService.list({ page, perPage, search, order }),
    placeholderData: (previousData) => previousData,
  });

  const { data: contextData, isLoading: contextLoading } = useQuery({
    queryKey: ["items-context"],
    queryFn: itemsService.context,
  });

  const {
    data: deactivateImpactData,
    isLoading: deactivateImpactLoading,
    isError: deactivateImpactIsError,
  } = useQuery({
    queryKey: ["item-deactivate-impact", editItem?.id_item],
    queryFn: () => itemsService.deactivateImpact(editItem.id_item),
    enabled: deactivateImpactOpen && Boolean(editItem?.id_item),
    retry: false,
  });

  const createMutation = useMutation({
    mutationFn: itemsService.create,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["items"] });
      closeCreate();
    },
  });

  const updateMutation = useMutation({
    mutationFn: itemsService.update,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["items"] });
      closeEdit();
    },
  });

  const { data: materialsContextData } = useQuery({
    queryKey: ["item-composition-context", materialsItem?.id_item],
    queryFn: () => itemsService.compositionContext(materialsItem.id_item),
    enabled: materialsOpen && Boolean(materialsItem?.id_item),
  });

  const { data: materialsData, isLoading: materialsLoading } = useQuery({
    queryKey: ["item-materials", materialsItem?.id_item],
    queryFn: () => itemsService.materials(materialsItem.id_item),
    enabled: materialsOpen && Boolean(materialsItem?.id_item),
  });

  const { data: inputOptionsData, isLoading: inputOptionsLoading } = useQuery({
    queryKey: ["material-input-options", materialSearch],
    queryFn: () => itemsService.searchInputs({ search: materialSearch, type: 1 }),
    enabled: materialsOpen,
  });

  const syncMaterialsMutation = useMutation({
    mutationFn: itemsService.syncMaterials,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["item-materials", materialsItem?.id_item] });
      queryClient.invalidateQueries({ queryKey: ["items"] });
      closeMaterials();
    },
  });

  const { data: laborContextData } = useQuery({
    queryKey: ["item-labor-context", laborItem?.id_item],
    queryFn: () => itemsService.compositionContext(laborItem.id_item),
    enabled: laborOpen && Boolean(laborItem?.id_item),
  });

  const { data: laborData, isLoading: laborLoading } = useQuery({
    queryKey: ["item-labor", laborItem?.id_item],
    queryFn: () => itemsService.labor(laborItem.id_item),
    enabled: laborOpen && Boolean(laborItem?.id_item),
  });

  const { data: laborOptionsData, isLoading: laborOptionsLoading } = useQuery({
    queryKey: ["labor-input-options", laborSearch],
    queryFn: () => itemsService.searchInputs({ search: laborSearch, type: 2 }),
    enabled: laborOpen,
  });

  const syncLaborMutation = useMutation({
    mutationFn: itemsService.syncLabor,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["item-labor", laborItem?.id_item] });
      queryClient.invalidateQueries({ queryKey: ["items"] });
      closeLabor();
    },
  });

  const { data: machineryContextData } = useQuery({
    queryKey: ["item-machinery-context", machineryItem?.id_item],
    queryFn: () => itemsService.compositionContext(machineryItem.id_item),
    enabled: machineryOpen && Boolean(machineryItem?.id_item),
  });

  const { data: machineryData, isLoading: machineryLoading } = useQuery({
    queryKey: ["item-machinery", machineryItem?.id_item],
    queryFn: () => itemsService.machinery(machineryItem.id_item),
    enabled: machineryOpen && Boolean(machineryItem?.id_item),
  });

  const { data: machineryOptionsData, isLoading: machineryOptionsLoading } = useQuery({
    queryKey: ["machinery-input-options", machinerySearch],
    queryFn: () => itemsService.searchInputs({ search: machinerySearch, type: 3 }),
    enabled: machineryOpen,
  });

  const syncMachineryMutation = useMutation({
    mutationFn: itemsService.syncMachinery,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["item-machinery", machineryItem?.id_item] });
      queryClient.invalidateQueries({ queryKey: ["items"] });
      closeMachinery();
    },
  });

  const updateFilesMutation = useMutation({
    mutationFn: itemsService.updateFiles,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["items"] });
      closeFiles();
    },
  });

  const { data: filesData, isLoading: filesLoading, isError: filesError } = useQuery({
    queryKey: ["item-files", filesItem?.id_item],
    queryFn: () => itemsService.getById(filesItem.id_item),
    enabled: filesOpen && Boolean(filesItem?.id_item),
  });

  const items = data?.data?.items ?? [];
  const context = contextData?.data ?? {};
  const groups = context.groups ?? [];
  const subgroupsByGroup = context.subgroups_by_group ?? {};
  const statuses = context.statuses ?? [];
  const unitMeasures = context.unit_measures ?? [];
  const permissions = context.permissions ?? {};
  const createSubgroups = createGroupId ? (subgroupsByGroup[createGroupId] ?? []) : [];
  const editSubgroups = editGroupId ? (subgroupsByGroup[editGroupId] ?? []) : [];
  const normalizedUnitSearch = unitSearch.trim().toLowerCase();
  const filteredUnitMeasures = unitMeasures
    .filter((unit) => {
      if (!normalizedUnitSearch) return true;

      return `${unit.description ?? ""} ${unit.abbreviation ?? ""}`.toLowerCase().includes(normalizedUnitSearch);
    })
    .slice(0, 30);
  const selectedUnitMeasure = unitMeasures.find((unit) => String(unit.id) === String(createUnitId));
  const normalizedEditUnitSearch = editUnitSearch.trim().toLowerCase();
  const filteredEditUnitMeasures = unitMeasures
    .filter((unit) => {
      if (!normalizedEditUnitSearch) return true;

      return `${unit.description ?? ""} ${unit.abbreviation ?? ""}`.toLowerCase().includes(normalizedEditUnitSearch);
    })
    .slice(0, 30);
  const selectedEditUnitMeasure = unitMeasures.find((unit) => String(unit.id) === String(editUnitId));
  const deactivateImpact = deactivateImpactData?.data ?? null;
  const impactedProjects = deactivateImpact?.projects ?? [];
  const impactedInputs = deactivateImpact?.inputs ?? [];
  const deactivateImpactSummary = deactivateImpact?.summary ?? { projects_count: 0, inputs_count: 0 };
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

  const handleSearchSubmit = (event) => {
    event.preventDefault();
    setPage(1);
    setSearch(searchQuery.trim());
  };

  const handlePerPageChange = (event) => {
    setPerPage(Number(event.target.value));
    setPage(1);
  };

  const handleOrderChange = (event) => {
    setOrder(event.target.value);
    setPage(1);
  };

  const closeExportChoice = () => setExportChoice(null);

  const openExportChoice = (item, report) => {
    if (!item?.id_item) return;
    setReportFeedback(null);
    setExportChoice({ item, report });
  };

  const handleOpenUnitPriceAnalysisPdf = async (item) => {
    if (!item?.id_item) return;

    setReportFeedback(null);
    setReportLoadingItemId(item.id_item);

    try {
      openPdfViewer(itemsService.legacyUnitPriceAnalysisPdfUrl(item.id_item), {
        title: "Análisis de precio unitario",
        errorMessage: "No se pudo generar el análisis de precio unitario.",
      });
    } catch (mutationError) {
      setReportFeedback({
        type: "error",
        message: mutationError?.response?.data?.message || mutationError?.message || "No se pudo abrir el analisis de precios unitarios.",
      });
    } finally {
      setReportLoadingItemId(null);
    }
  };

  const handleOpenMaterialBreakdownPdf = async (item) => {
    if (!item?.id_item) return;

    setReportFeedback(null);
    setReportLoadingItemId(item.id_item);

    try {
      openPdfViewer(itemsService.materialBreakdownPdfUrl(item.id_item), {
        title: "Desglose de materiales",
        errorMessage: "No se pudo generar el desglose de materiales.",
      });
    } catch (mutationError) {
      setReportFeedback({
        type: "error",
        message: mutationError?.response?.data?.message || mutationError?.message || "No se pudo abrir el desglose de materiales.",
      });
    } finally {
      setReportLoadingItemId(null);
    }
  };

  const handleOpenLaborBreakdownPdf = async (item) => {
    if (!item?.id_item) return;

    setReportFeedback(null);
    setReportLoadingItemId(item.id_item);

    try {
      openPdfViewer(itemsService.laborBreakdownPdfUrl(item.id_item), {
        title: "Desglose de mano de obra",
        errorMessage: "No se pudo generar el desglose de mano de obra.",
      });
    } catch (mutationError) {
      setReportFeedback({
        type: "error",
        message: mutationError?.response?.data?.message || mutationError?.message || "No se pudo abrir el desglose de mano de obra.",
      });
    } finally {
      setReportLoadingItemId(null);
    }
  };

  const handleOpenMachineryBreakdownPdf = async (item) => {
    if (!item?.id_item) return;

    setReportFeedback(null);
    setReportLoadingItemId(item.id_item);

    try {
      openPdfViewer(itemsService.machineryBreakdownPdfUrl(item.id_item), {
        title: "Desglose de herramientas",
        errorMessage: "No se pudo generar el desglose de herramientas.",
      });
    } catch (mutationError) {
      setReportFeedback({
        type: "error",
        message: mutationError?.response?.data?.message || mutationError?.message || "No se pudo abrir el desglose de herramientas.",
      });
    } finally {
      setReportLoadingItemId(null);
    }
  };

  const handleDownloadUnitPriceAnalysisXlsx = (item) => {
    if (!item?.id_item) return;
    setReportFeedback(null);
    downloadUrl(itemsService.legacyUnitPriceAnalysisXlsxUrl(item.id_item));
  };

  const handleDownloadMaterialBreakdownXlsx = (item) => {
    if (!item?.id_item) return;
    setReportFeedback(null);
    downloadUrl(itemsService.materialBreakdownXlsxUrl(item.id_item));
  };

  const handleDownloadLaborBreakdownXlsx = (item) => {
    if (!item?.id_item) return;
    setReportFeedback(null);
    downloadUrl(itemsService.laborBreakdownXlsxUrl(item.id_item));
  };

  const handleDownloadMachineryBreakdownXlsx = (item) => {
    if (!item?.id_item) return;
    setReportFeedback(null);
    downloadUrl(itemsService.machineryBreakdownXlsxUrl(item.id_item));
  };

  const handleExportChoicePdf = () => {
    if (!exportChoice?.item?.id_item) return;

    const { item, report } = exportChoice;

    if (report === "unit-price") {
      handleOpenUnitPriceAnalysisPdf(item);
    }

    if (report === "materials") {
      handleOpenMaterialBreakdownPdf(item);
    }

    if (report === "labor") {
      handleOpenLaborBreakdownPdf(item);
    }

    if (report === "machinery") {
      handleOpenMachineryBreakdownPdf(item);
    }

    closeExportChoice();
  };

  const handleExportChoiceXlsx = () => {
    if (!exportChoice?.item?.id_item) return;

    const { item, report } = exportChoice;

    if (report === "unit-price") {
      handleDownloadUnitPriceAnalysisXlsx(item);
    }

    if (report === "materials") {
      handleDownloadMaterialBreakdownXlsx(item);
    }

    if (report === "labor") {
      handleDownloadLaborBreakdownXlsx(item);
    }

    if (report === "machinery") {
      handleDownloadMachineryBreakdownXlsx(item);
    }

    closeExportChoice();
  };

  const handleUnitSearchChange = (event) => {
    const value = event.target.value;
    setUnitSearch(value);
    setUnitComboboxOpen(true);

    const exactMatch = unitMeasures.find((unit) => {
      const label = `${unit.description ?? ""}${unit.abbreviation ? ` (${unit.abbreviation})` : ""}`;
      return label.toLowerCase() === value.trim().toLowerCase();
    });

    setCreateUnitId(exactMatch ? String(exactMatch.id) : "");
  };

  const handleSelectUnitMeasure = (unit) => {
    setCreateUnitId(String(unit.id));
    setUnitSearch(`${unit.description ?? ""}${unit.abbreviation ? ` (${unit.abbreviation})` : ""}`);
    setUnitComboboxOpen(false);
  };

  const handleEditGroupChange = (event) => {
    const groupId = event.target.value;
    const availableSubgroups = groupId ? (subgroupsByGroup[groupId] ?? []) : [];
    const currentSubgroupIsValid = availableSubgroups.some((subgroup) => String(subgroup.id) === String(editSubgroupId));

    setEditGroupId(groupId);

    if (!currentSubgroupIsValid) {
      setEditSubgroupId("");
    }
  };

  const handleEditUnitSearchChange = (event) => {
    const value = event.target.value;
    setEditUnitSearch(value);
    setEditUnitComboboxOpen(true);

    const exactMatch = unitMeasures.find((unit) => {
      const label = `${unit.description ?? ""}${unit.abbreviation ? ` (${unit.abbreviation})` : ""}`;
      return label.toLowerCase() === value.trim().toLowerCase();
    });

    setEditUnitId(exactMatch ? String(exactMatch.id) : "");
  };

  const handleSelectEditUnitMeasure = (unit) => {
    setEditUnitId(String(unit.id));
    setEditUnitSearch(`${unit.description ?? ""}${unit.abbreviation ? ` (${unit.abbreviation})` : ""}`);
    setEditUnitComboboxOpen(false);
  };

  const handleMaterialSearchChange = (event) => {
    const value = event.target.value;
    setMaterialSearch(value);
    setMaterialComboboxOpen(true);

    const exactMatch = materialOptions.find((option) => option.text.toLowerCase() === value.trim().toLowerCase());
    setSelectedMaterialId(exactMatch ? String(exactMatch.id) : "");
  };

  const handleSelectMaterial = (material) => {
    setSelectedMaterialId(String(material.id));
    setMaterialSearch(material.text);
    setMaterialComboboxOpen(false);
  };

  const handleLaborSearchChange = (event) => {
    const value = event.target.value;
    setLaborSearch(value);
    setLaborComboboxOpen(true);

    const exactMatch = laborOptions.find((option) => option.text.toLowerCase() === value.trim().toLowerCase());
    setSelectedLaborId(exactMatch ? String(exactMatch.id) : "");
  };

  const handleSelectLabor = (labor) => {
    setSelectedLaborId(String(labor.id));
    setLaborSearch(labor.text);
    setLaborComboboxOpen(false);
  };

  const handleMachinerySearchChange = (event) => {
    const value = event.target.value;
    setMachinerySearch(value);
    setMachineryComboboxOpen(true);

    const exactMatch = machineryOptions.find((option) => option.text.toLowerCase() === value.trim().toLowerCase());
    setSelectedMachineryId(exactMatch ? String(exactMatch.id) : "");
  };

  const handleSelectMachinery = (machinery) => {
    setSelectedMachineryId(String(machinery.id));
    setMachinerySearch(machinery.text);
    setMachineryComboboxOpen(false);
  };

  const handleCreateSubmit = async (event) => {
    event.preventDefault();

    if (!createUnitId) {
      setCreateErrors(["Selecciona una unidad de medida valida."]);
      return;
    }

    const formData = new FormData(event.currentTarget);
    const payload = {
      group_id: Number(formData.get("group_id") || 0),
      subgroup_id: Number(formData.get("subgroup_id") || 0),
      item: String(formData.get("item") || "").trim(),
      unit_measure_id: Number(createUnitId),
      status: String(formData.get("status") || "").trim(),
    };

    try {
      setCreateErrors([]);
      await createMutation.mutateAsync(payload);
      setPage(1);
    } catch (mutationError) {
      setCreateErrors(collectValidationMessages(mutationError, "No se pudo crear el item."));
    }
  };

  const handleEditSubmit = async (event) => {
    event.preventDefault();
    if (!editItem?.id_item) return;

    if (!editUnitId) {
      setEditErrors(["Selecciona una unidad de medida valida."]);
      return;
    }

    const formData = new FormData(event.currentTarget);
    const payload = {
      group_id: Number(formData.get("group_id") || 0),
      subgroup_id: Number(formData.get("subgroup_id") || 0),
      item: String(formData.get("item") || "").trim(),
      unit_measure_id: Number(editUnitId),
      status: String(formData.get("status") || "").trim(),
    };

    const currentStatus = String(editItem.status ?? editItem.estado ?? "").trim().toUpperCase();
    const nextStatus = String(payload.status ?? "").trim().toUpperCase();

    if (currentStatus === "AC" && nextStatus === "DC") {
      setEditErrors([]);
      setPendingDeactivatePayload({
        id: editItem.id_item,
        payload,
      });
      setDeactivateImpactOpen(true);
      return;
    }

    try {
      setEditErrors([]);
      await updateMutation.mutateAsync({
        id: editItem.id_item,
        payload,
      });
    } catch (mutationError) {
      setEditErrors(collectValidationMessages(mutationError, "No se pudo actualizar el item."));
    }
  };

  const closeDeactivateImpact = () => {
    setDeactivateImpactOpen(false);
    setPendingDeactivatePayload(null);
  };

  const confirmDeactivateItem = async () => {
    if (!pendingDeactivatePayload) return;

    try {
      setEditErrors([]);
      await updateMutation.mutateAsync(pendingDeactivatePayload);
      closeDeactivateImpact();
    } catch (mutationError) {
      setEditErrors(collectValidationMessages(mutationError, "No se pudo desactivar el item."));
      closeDeactivateImpact();
    }
  };

  const handleAddMaterial = (event) => {
    event.preventDefault();
    if (!materialsItem?.id_item) return;

    const formData = new FormData(event.currentTarget);
    const idInsumo = Number(selectedMaterialId || 0);
    const cantidad = Number(formData.get("cantidad") || 0);

    if (!idInsumo || !cantidad || cantidad <= 0) {
      alert("Selecciona un material y registra una cantidad mayor a 0.");
      return;
    }

    const selectedOption = materialOptions.find((option) => Number(option.id) === idInsumo);

    const existingIndex = materialRows.findIndex((row) => Number(row.id_insumo) === idInsumo);
    const existingRow = existingIndex >= 0 ? materialRows[existingIndex] : null;
    const unitPrice = Number(selectedOption?.precio ?? existingRow?.precio_unitario ?? 0);
    const nextRow = {
      id_item_insumo: existingRow?.id_item_insumo,
      id_insumo: idInsumo,
      descripcion: selectedOption?.text ?? existingRow?.descripcion ?? "",
      unidad: selectedOption?.unidad ?? existingRow?.unidad ?? "",
      cantidad,
      precio_unitario: unitPrice,
      parcial: cantidad * unitPrice,
      persisted: Boolean(existingRow?.persisted),
    };
    const nextRows = existingIndex >= 0
      ? materialRows.map((row, index) => (index === existingIndex ? nextRow : row))
      : [...materialRows, nextRow];

    setDraftMaterials(nextRows);
    setMaterialDraftDirty(true);
    setDeletedMaterialInputIds((currentIds) => currentIds.filter((inputId) => Number(inputId) !== idInsumo));
    event.currentTarget.reset();
    setMaterialSearch("");
    setSelectedMaterialId("");
    setMaterialComboboxOpen(false);
  };

  const handleRemoveMaterial = (material) => {
    setDraftMaterials(materialRows.filter((row) => Number(row.id_insumo) !== Number(material.id_insumo)));
    setMaterialDraftDirty(true);

    if (material.persisted) {
      setDeletedMaterialInputIds((currentIds) => Array.from(new Set([...currentIds, Number(material.id_insumo)])));
    }
  };

  const handleMaterialQuantityChange = (material, nextQuantity) => {
    const quantity = nextQuantity === "" ? "" : Number(nextQuantity);

    setDraftMaterials(materialRows.map((row) => {
      if (Number(row.id_insumo) !== Number(material.id_insumo)) {
        return row;
      }

      return {
        ...row,
        cantidad: quantity,
        parcial: Number(quantity || 0) * Number(row.precio_unitario || 0),
      };
    }));
    setMaterialDraftDirty(true);
  };

  const handleSaveMaterials = async () => {
    if (!materialsItem?.id_item) return;
    if (materialRows.some((material) => !Number(material.cantidad) || Number(material.cantidad) <= 0)) {
      alert("Todas las cantidades deben ser mayores a 0.");
      return;
    }

    try {
      await syncMaterialsMutation.mutateAsync({
        itemId: materialsItem.id_item,
        payload: {
          items: materialRows.map((material) => ({
            id_insumo: Number(material.id_insumo),
            cantidad: Number(material.cantidad),
          })),
          deleted_input_ids: deletedMaterialInputIds,
        },
      });
    } catch (mutationError) {
      alert(mutationError?.response?.data?.message || "No se pudieron guardar los materiales.");
    }
  };

  const handleAddLabor = (event) => {
    event.preventDefault();
    if (!laborItem?.id_item) return;

    const formData = new FormData(event.currentTarget);
    const idInsumo = Number(selectedLaborId || 0);
    const cantidad = Number(formData.get("cantidad") || 0);

    if (!idInsumo || !cantidad || cantidad <= 0) {
      alert("Selecciona mano de obra y registra una cantidad mayor a 0.");
      return;
    }

    const selectedOption = laborOptions.find((option) => Number(option.id) === idInsumo);
    const existingIndex = laborRows.findIndex((row) => Number(row.id_insumo) === idInsumo);
    const existingRow = existingIndex >= 0 ? laborRows[existingIndex] : null;
    const unitPrice = Number(selectedOption?.precio ?? existingRow?.precio_unitario ?? 0);
    const nextRow = {
      id_item_insumo: existingRow?.id_item_insumo,
      id_insumo: idInsumo,
      descripcion: selectedOption?.text ?? existingRow?.descripcion ?? "",
      unidad: selectedOption?.unidad ?? existingRow?.unidad ?? "",
      cantidad,
      precio_unitario: unitPrice,
      parcial: cantidad * unitPrice,
      persisted: Boolean(existingRow?.persisted),
    };
    const nextRows = existingIndex >= 0
      ? laborRows.map((row, index) => (index === existingIndex ? nextRow : row))
      : [...laborRows, nextRow];

    setDraftLabor(nextRows);
    setLaborDraftDirty(true);
    setDeletedLaborInputIds((currentIds) => currentIds.filter((inputId) => Number(inputId) !== idInsumo));
    event.currentTarget.reset();
    setLaborSearch("");
    setSelectedLaborId("");
    setLaborComboboxOpen(false);
  };

  const handleRemoveLabor = (labor) => {
    setDraftLabor(laborRows.filter((row) => Number(row.id_insumo) !== Number(labor.id_insumo)));
    setLaborDraftDirty(true);

    if (labor.persisted) {
      setDeletedLaborInputIds((currentIds) => Array.from(new Set([...currentIds, Number(labor.id_insumo)])));
    }
  };

  const handleLaborQuantityChange = (labor, nextQuantity) => {
    const quantity = nextQuantity === "" ? "" : Number(nextQuantity);

    setDraftLabor(laborRows.map((row) => (
      Number(row.id_insumo) === Number(labor.id_insumo)
        ? { ...row, cantidad: quantity, parcial: Number(quantity || 0) * Number(row.precio_unitario || 0) }
        : row
    )));
    setLaborDraftDirty(true);
  };

  const handleSaveLabor = async () => {
    if (!laborItem?.id_item) return;
    if (laborRows.some((labor) => !Number(labor.cantidad) || Number(labor.cantidad) <= 0)) {
      alert("Todas las cantidades deben ser mayores a 0.");
      return;
    }

    try {
      await syncLaborMutation.mutateAsync({
        itemId: laborItem.id_item,
        payload: {
          items: laborRows.map((labor) => ({
            id_insumo: Number(labor.id_insumo),
            cantidad: Number(labor.cantidad),
          })),
          deleted_input_ids: deletedLaborInputIds,
        },
      });
    } catch (mutationError) {
      alert(mutationError?.response?.data?.message || "No se pudo guardar la mano de obra.");
    }
  };

  const handleAddMachinery = (event) => {
    event.preventDefault();
    if (!machineryItem?.id_item) return;

    const formData = new FormData(event.currentTarget);
    const idInsumo = Number(selectedMachineryId || 0);
    const cantidad = Number(formData.get("cantidad") || 0);

    if (!idInsumo || !cantidad || cantidad <= 0) {
      alert("Selecciona maquinaria y registra una cantidad mayor a 0.");
      return;
    }

    const selectedOption = machineryOptions.find((option) => Number(option.id) === idInsumo);
    const existingIndex = machineryRows.findIndex((row) => Number(row.id_insumo) === idInsumo);
    const existingRow = existingIndex >= 0 ? machineryRows[existingIndex] : null;
    const unitPrice = Number(selectedOption?.precio ?? existingRow?.precio_unitario ?? 0);
    const nextRow = {
      id_item_insumo: existingRow?.id_item_insumo,
      id_insumo: idInsumo,
      descripcion: selectedOption?.text ?? existingRow?.descripcion ?? "",
      unidad: selectedOption?.unidad ?? existingRow?.unidad ?? "",
      cantidad,
      precio_unitario: unitPrice,
      parcial: cantidad * unitPrice,
      persisted: Boolean(existingRow?.persisted),
    };
    const nextRows = existingIndex >= 0
      ? machineryRows.map((row, index) => (index === existingIndex ? nextRow : row))
      : [...machineryRows, nextRow];

    setDraftMachinery(nextRows);
    setMachineryDraftDirty(true);
    setDeletedMachineryInputIds((currentIds) => currentIds.filter((inputId) => Number(inputId) !== idInsumo));
    event.currentTarget.reset();
    setMachinerySearch("");
    setSelectedMachineryId("");
    setMachineryComboboxOpen(false);
  };

  const handleRemoveMachinery = (machinery) => {
    setDraftMachinery(machineryRows.filter((row) => Number(row.id_insumo) !== Number(machinery.id_insumo)));
    setMachineryDraftDirty(true);

    if (machinery.persisted) {
      setDeletedMachineryInputIds((currentIds) => Array.from(new Set([...currentIds, Number(machinery.id_insumo)])));
    }
  };

  const handleMachineryQuantityChange = (machinery, nextQuantity) => {
    const quantity = nextQuantity === "" ? "" : Number(nextQuantity);

    setDraftMachinery(machineryRows.map((row) => (
      Number(row.id_insumo) === Number(machinery.id_insumo)
        ? { ...row, cantidad: quantity, parcial: Number(quantity || 0) * Number(row.precio_unitario || 0) }
        : row
    )));
    setMachineryDraftDirty(true);
  };

  const handleSaveMachinery = async () => {
    if (!machineryItem?.id_item) return;
    if (machineryRows.some((machinery) => !Number(machinery.cantidad) || Number(machinery.cantidad) <= 0)) {
      alert("Todas las cantidades deben ser mayores a 0.");
      return;
    }

    try {
      await syncMachineryMutation.mutateAsync({
        itemId: machineryItem.id_item,
        payload: {
          items: machineryRows.map((machinery) => ({
            id_insumo: Number(machinery.id_insumo),
            cantidad: Number(machinery.cantidad),
          })),
          deleted_input_ids: deletedMachineryInputIds,
        },
      });
    } catch (mutationError) {
      alert(mutationError?.response?.data?.message || "No se pudo guardar maquinaria.");
    }
  };

  const handleFilesSubmit = async (event) => {
    event.preventDefault();
    const currentFilesItem = filesData?.data?.item;
    if (!currentFilesItem?.id_item) return;

    const formData = new FormData(event.currentTarget);
    const payload = new FormData();
    payload.append("item", currentFilesItem.name || "");

    const specificationFile = formData.get("specification_file");
    const sheetFile = formData.get("sheet_file");

    if (specificationFile instanceof File && specificationFile.size > 0) {
      payload.append("specification_file", specificationFile);
    }

    if (sheetFile instanceof File && sheetFile.size > 0) {
      payload.append("sheet_file", sheetFile);
    }

    try {
      await updateFilesMutation.mutateAsync({
        id: currentFilesItem.id_item,
        formData: payload,
      });
    } catch (mutationError) {
      alert(collectValidationMessages(mutationError, "No se pudieron guardar los archivos del item.").join("\n"));
    }
  };

  const handleRecalculateSubmit = async (event) => {
    event.preventDefault();
    if (!recalculateItem?.id_item || !recalculateDate) return;

    setReportFeedback(null);
    setReportLoadingItemId(recalculateItem.id_item);

    try {
      openPdfViewer(itemsService.priceRecalculationPdfUrl({
        itemId: recalculateItem.id_item,
        fecha: recalculateDate,
        mode: "general",
      }), {
        title: "Recalcular precio item",
        errorMessage: "No se pudo generar el recálculo de precio del item.",
      });
      closeRecalculate();
    } catch (mutationError) {
      setReportFeedback({
        type: "error",
        message: mutationError?.response?.data?.message || mutationError?.message || "No se pudo abrir el recálculo de precio del item.",
      });
    } finally {
      setReportLoadingItemId(null);
    }
  };

  const handleRecalculateXlsx = () => {
    if (!recalculateItem?.id_item || !recalculateDate) return;
    downloadUrl(itemsService.priceRecalculationXlsxUrl({
      itemId: recalculateItem.id_item,
      fecha: recalculateDate,
      mode: "general",
    }));
    closeRecalculate();
  };

  const handleBreakdownSubmit = async (event) => {
    event.preventDefault();
    if (!breakdownItem?.id_item || !breakdownDate || !breakdownType) return;

    setReportFeedback(null);
    setReportLoadingItemId(breakdownItem.id_item);

    try {
      openPdfViewer(itemsService.breakdownRecalculationPdfUrl({
        itemId: breakdownItem.id_item,
        fecha: breakdownDate,
        type: breakdownType,
      }), {
        title: "Desglose histórico",
        errorMessage: "No se pudo generar el desglose histórico.",
      });
      closeBreakdown();
    } catch (mutationError) {
      setReportFeedback({
        type: "error",
        message: mutationError?.response?.data?.message || mutationError?.message || "No se pudo abrir el desglose historico.",
      });
    } finally {
      setReportLoadingItemId(null);
    }
  };

  const handleBreakdownXlsx = () => {
    if (!breakdownItem?.id_item || !breakdownDate || !breakdownType) return;
    downloadUrl(itemsService.breakdownRecalculationXlsxUrl({
      itemId: breakdownItem.id_item,
      fecha: breakdownDate,
      type: breakdownType,
    }));
    closeBreakdown();
  };

  const materialOptions = inputOptionsData?.data?.items ?? [];
  const materialContext = materialsContextData?.data ?? null;
  const initialMaterialRows = useMemo(() => {
    const currentMaterials = materialsData?.data?.items ?? [];

    return currentMaterials.map((material) => ({
      id_item_insumo: material.id_item_insumo,
      id_insumo: material.id_insumo,
      descripcion: material.descripcion,
      unidad: material.unidad,
      cantidad: Number(material.cantidad || 0),
      precio_unitario: Number(material.precio_unitario || 0),
      parcial: Number(material.cantidad || 0) * Number(material.precio_unitario || 0),
      persisted: true,
    }));
  }, [materialsData]);
  const materialRows = materialDraftDirty ? draftMaterials : initialMaterialRows;
  const materialsTotal = materialRows.reduce((acc, material) => acc + Number(material.parcial || 0), 0);
  const laborOptions = laborOptionsData?.data?.items ?? [];
  const laborContext = laborContextData?.data ?? null;
  const initialLaborRows = useMemo(() => {
    const currentLabor = laborData?.data?.items ?? [];

    return currentLabor.map((labor) => ({
      id_item_insumo: labor.id_item_insumo,
      id_insumo: labor.id_insumo,
      descripcion: labor.descripcion,
      unidad: labor.unidad,
      cantidad: Number(labor.cantidad || 0),
      precio_unitario: Number(labor.precio_unitario || 0),
      parcial: Number(labor.cantidad || 0) * Number(labor.precio_unitario || 0),
      persisted: true,
    }));
  }, [laborData]);
  const laborRows = laborDraftDirty ? draftLabor : initialLaborRows;
  const laborTotal = laborRows.reduce((acc, labor) => acc + Number(labor.parcial || 0), 0);
  const machineryOptions = machineryOptionsData?.data?.items ?? [];
  const machineryContext = machineryContextData?.data ?? null;
  const initialMachineryRows = useMemo(() => {
    const currentMachinery = machineryData?.data?.items ?? [];

    return currentMachinery.map((machinery) => ({
      id_item_insumo: machinery.id_item_insumo,
      id_insumo: machinery.id_insumo,
      descripcion: machinery.descripcion,
      unidad: machinery.unidad,
      cantidad: Number(machinery.cantidad || 0),
      precio_unitario: Number(machinery.precio_unitario || 0),
      parcial: Number(machinery.cantidad || 0) * Number(machinery.precio_unitario || 0),
      persisted: true,
    }));
  }, [machineryData]);
  const machineryRows = machineryDraftDirty ? draftMachinery : initialMachineryRows;
  const machineryTotal = machineryRows.reduce((acc, machinery) => acc + Number(machinery.parcial || 0), 0);
  const currentFilesItem = filesData?.data?.item ?? null;
  const specificationUrl = currentFilesItem?.specification_url ?? null;
  const sheetUrl = currentFilesItem?.sheet_url ?? null;

  return (
    <div className="flex flex-col gap-6 animate-in fade-in duration-500">
      <Card className="border border-border/70 bg-white/86 shadow-[0_24px_90px_rgba(15,23,42,0.08)] backdrop-blur">
        <CardHeader className="gap-4 border-b border-border/70 bg-muted/25">
          <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div className="flex items-center gap-3">
              <div className="flex size-11 items-center justify-center rounded-2xl bg-foreground text-background">
                <Package className="size-5" />
              </div>
              <div>
                <CardTitle className="text-2xl tracking-[-0.04em]">Items</CardTitle>
              </div>
            </div>
            {permissions.can_create && (
              <Button
                type="button"
                className="h-11 rounded-full bg-foreground px-5 text-background hover:bg-foreground/90"
                onClick={openCreate}
                disabled={contextLoading}
              >
                <Plus className="mr-2 size-4" />
                Nuevo
              </Button>
            )}
          </div>
        </CardHeader>

        <CardContent className="flex flex-col gap-6 p-5 sm:p-6">
          {reportFeedback && (
            <Alert variant="destructive" className="rounded-2xl">
              <AlertDescription>{reportFeedback.message}</AlertDescription>
            </Alert>
          )}

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
                    <option value={25}>25</option>
                    <option value={50}>50</option>
                    <option value={100}>100</option>
                  </select>
                  <span className="pointer-events-none absolute inset-y-0 right-4 flex items-center text-muted-foreground">▾</span>
                </div>
              </div>

              <div className="flex flex-col gap-2">
                <span className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                  Orden
                </span>
                <div className="relative">
                  <select
                    className="h-12 min-w-36 appearance-none rounded-2xl border border-border/80 bg-background/90 px-4 pr-10 text-sm text-foreground outline-none transition focus:border-foreground/20"
                    value={order}
                    onChange={handleOrderChange}
                  >
                    <option value="legacy">Predeterminado</option>
                    <option value="recent">Recientes</option>
                    <option value="oldest">Antiguos</option>
                  </select>
                  <span className="pointer-events-none absolute inset-y-0 right-4 flex items-center text-muted-foreground">▾</span>
                </div>
              </div>
            </div>

            <form className="flex w-full max-w-sm flex-col gap-2" onSubmit={handleSearchSubmit}>
              <span className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                Buscar
              </span>
              <div className="relative">
                <Search className="pointer-events-none absolute left-4 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                  placeholder="Buscar item"
                  className="h-12 rounded-2xl border-border/80 bg-background/90 pl-11"
                  value={searchQuery}
                  onChange={(event) => setSearchQuery(event.target.value)}
                />
              </div>
            </form>
          </div>

          {isLoading && (
            <div className="flex items-center justify-center rounded-2xl border border-border/70 bg-background/70 p-8 text-muted-foreground">
              <Loader2 className="mr-2 size-4 animate-spin" /> Cargando items...
            </div>
          )}

          {isError && (
            <Alert variant="destructive" className="rounded-2xl">
              <AlertDescription>
                {error?.response?.data?.message || "No se pudieron cargar los items."}
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
                      <th className="px-5 py-4 font-semibold text-foreground">Grupo</th>
                      <th className="px-5 py-4 font-semibold text-foreground">Subgrupo</th>
                      <th className="px-5 py-4 font-semibold text-foreground">Item</th>
                      <th className="px-5 py-4 font-semibold text-foreground">Precio</th>
                      <th className="px-5 py-4 font-semibold text-foreground">Unidad</th>
                      <th className="px-5 py-4 font-semibold text-foreground">Estado</th>
                      <th className="px-5 py-4 font-semibold text-foreground text-center">Opciones</th>
                    </tr>
                  </thead>
                  <tbody>
                    {items.map((item, index) => {
                      const isItemEnabled = String(item.status ?? item.estado ?? "").trim().toUpperCase() === "AC";

                      return (
                        <tr key={item.id_item} className={index < items.length - 1 ? "border-b border-border/60" : ""}>
                        <td className="px-5 py-4 align-top text-foreground">
                          {index + 1}
                        </td>
                        <td className="px-5 py-4 align-top text-muted-foreground">
                          {item.group?.name ?? "-"}
                        </td>
                        <td className="px-5 py-4 align-top text-muted-foreground">
                          {item.subgroup?.description ?? "-"}
                        </td>
                        <td className="px-5 py-4 align-top text-foreground">
                          <div className="max-w-[260px] leading-7">{item.name ?? "-"}</div>
                        </td>
                        <td className="px-5 py-4 align-top text-foreground">
                          {item.calculated_price_label ?? item.precio_calculado ?? (item.calculated_price !== null ? Number(item.calculated_price).toFixed(2) : "-")}
                        </td>
                        <td className="px-5 py-4 align-top text-muted-foreground">
                          {item.unit_measure?.abbreviation ?? "-"}
                        </td>
                        <td className="px-5 py-4 align-top">
                          <Badge className={`rounded-full px-3 py-1 text-[11px] uppercase tracking-[0.18em] ${statusClass[item.status] || "bg-slate-500 text-white"}`}>
                            {item.status_label ?? "-"}
                          </Badge>
                        </td>
                        <td className="px-5 py-4 align-top">
                          <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                              <Button variant="ghost" size="sm" className="h-8 w-8 p-0">
                                <MoreHorizontal className="h-4 w-4" />
                              </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-48 rounded-2xl border border-border/70 bg-background/95 p-1 shadow-lg">
                              <DropdownMenuItem
                                className="flex items-center gap-2 rounded-xl px-3 py-2 cursor-pointer"
                                onClick={() => handleEdit(item)}
                              >
                                <Pencil className="h-4 w-4 text-muted-foreground" />
                                <span>Editar</span>
                              </DropdownMenuItem>
                              {isItemEnabled && (
                                <>
                                  <DropdownMenuItem className="flex items-center gap-2 rounded-xl px-3 py-2 cursor-pointer" onClick={() => openMaterials(item)}>
                                    <Package2 className="h-4 w-4 text-muted-foreground" />
                                    <span>Materiales</span>
                                  </DropdownMenuItem>
                                  <DropdownMenuItem className="flex items-center gap-2 rounded-xl px-3 py-2 cursor-pointer" onClick={() => openLabor(item)}>
                                    <Users className="h-4 w-4 text-muted-foreground" />
                                    <span>Mano de Obra</span>
                                  </DropdownMenuItem>
                                  <DropdownMenuItem className="flex items-center gap-2 rounded-xl px-3 py-2 cursor-pointer" onClick={() => openMachinery(item)}>
                                    <Wrench className="h-4 w-4 text-muted-foreground" />
                                    <span>Maquinaria</span>
                                  </DropdownMenuItem>
                                  <DropdownMenuItem className="flex items-center gap-2 rounded-xl px-3 py-2 cursor-pointer" onClick={() => openFiles(item)}>
                                    <FileText className="h-4 w-4 text-muted-foreground" />
                                    <span>Archivos</span>
                                  </DropdownMenuItem>

                                  <DropdownMenuSeparator className="my-1 bg-border/50" />
                                  <DropdownMenuItem className="flex items-center gap-2 rounded-xl px-3 py-2 cursor-pointer" onClick={() => openExportChoice(item, "unit-price")} disabled={reportLoadingItemId === item.id_item}>
                                    <TrendingUp className="h-4 w-4 text-muted-foreground" />
                                    <span>{reportLoadingItemId === item.id_item ? "Generando..." : "Análisis de precios unitarios"}</span>
                                  </DropdownMenuItem>
                                  <DropdownMenuItem className="flex items-center gap-2 rounded-xl px-3 py-2 cursor-pointer" onClick={() => openRecalculate(item)}>
                                    <RefreshCw className="h-4 w-4 text-muted-foreground" />
                                    <span>Recalcular Precio</span>
                                  </DropdownMenuItem>

                                  <DropdownMenuSeparator className="my-1 bg-border/50" />
                                  <DropdownMenuItem
                                    className="flex items-center gap-2 rounded-xl px-3 py-2 cursor-pointer"
                                    onClick={() => openExportChoice(item, "materials")}
                                  >
                                    <BarChart3 className="h-4 w-4 text-muted-foreground" />
                                    <span>Desglose Materiales</span>
                                  </DropdownMenuItem>
                                  <DropdownMenuItem
                                    className="flex items-center gap-2 rounded-xl px-3 py-2 cursor-pointer"
                                    onClick={() => openExportChoice(item, "labor")}
                                    disabled={reportLoadingItemId === item.id_item}
                                  >
                                    <Users className="h-4 w-4 text-muted-foreground" />
                                    <span>{reportLoadingItemId === item.id_item ? "Generando..." : "Desglose M.O."}</span>
                                  </DropdownMenuItem>
                                  <DropdownMenuItem
                                    className="flex items-center gap-2 rounded-xl px-3 py-2 cursor-pointer"
                                    onClick={() => openExportChoice(item, "machinery")}
                                    disabled={reportLoadingItemId === item.id_item}
                                  >
                                    <Hammer className="h-4 w-4 text-muted-foreground" />
                                    <span>{reportLoadingItemId === item.id_item ? "Generando..." : "Desglose Herramientas"}</span>
                                  </DropdownMenuItem>
                                  <DropdownMenuItem className="flex items-center gap-2 rounded-xl px-3 py-2 cursor-pointer" onClick={() => openBreakdown(item)}>
                                    <RefreshCw className="h-4 w-4 text-muted-foreground" />
                                    <span>Recalcular Desglose</span>
                                  </DropdownMenuItem>
                                </>
                              )}
                            </DropdownMenuContent>
                          </DropdownMenu>
                        </td>
                        </tr>
                      );
                    })}
                    {items.length === 0 && (
                      <tr>
                        <td colSpan={8} className="px-5 py-8 text-center text-muted-foreground">
                          No se encontraron items para los filtros seleccionados.
                        </td>
                      </tr>
                    )}
                  </tbody>
                </table>
              </div>

              <Separator className="bg-border/70" />

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

      {createOpen && createPortal(
        <div className="fixed inset-0 z-[80] flex justify-end bg-slate-950/20 backdrop-blur-[1px]">
          <div className="w-full max-w-2xl overflow-y-auto border-l border-border/70 bg-background/96 p-4 shadow-[0_0_60px_rgba(15,23,42,0.16)] backdrop-blur xl:p-6">
            <Card className="border border-border/70 bg-white/92 shadow-[0_24px_90px_rgba(15,23,42,0.08)]">
              <CardHeader className="border-b border-border/70 bg-muted/20">
                <div className="flex items-start justify-between gap-4">
                  <div>
                    <CardTitle className="text-2xl tracking-[-0.04em]">Nuevo Item</CardTitle>
                    <CardDescription>Registra la informacion base del item.</CardDescription>
                  </div>

                  <Button variant="ghost" size="icon-sm" className="rounded-full" onClick={closeCreate}>
                    <X />
                  </Button>
                </div>
              </CardHeader>

              <CardContent className="p-5 sm:p-6">
                <form className="flex flex-col gap-5" onSubmit={handleCreateSubmit}>
                  {createErrors.length > 0 && (
                    <Alert variant="destructive" className="rounded-2xl">
                      <AlertDescription>
                        <ul className="list-disc space-y-1 pl-4">
                          {createErrors.map((message) => (
                            <li key={message}>{message}</li>
                          ))}
                        </ul>
                      </AlertDescription>
                    </Alert>
                  )}

                  <div className="grid gap-5 sm:grid-cols-2">
                    <div className="flex flex-col gap-2">
                      <Label htmlFor="create_group" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                        Grupo
                      </Label>
                      <select
                        id="create_group"
                        name="group_id"
                        value={createGroupId}
                        onChange={(event) => setCreateGroupId(event.target.value)}
                        className="h-12 rounded-2xl border border-border/80 bg-background/90 px-4 text-sm text-foreground outline-none transition focus:border-foreground/20"
                        required
                      >
                        <option value="">Seleccionar</option>
                        {groups.map((group) => (
                          <option key={group.id} value={group.id}>{group.name}</option>
                        ))}
                      </select>
                    </div>

                    <div className="flex flex-col gap-2">
                      <Label htmlFor="create_subgroup" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                        Subgrupo
                      </Label>
                      <select
                        id="create_subgroup"
                        name="subgroup_id"
                        className="h-12 rounded-2xl border border-border/80 bg-background/90 px-4 text-sm text-foreground outline-none transition focus:border-foreground/20"
                        disabled={!createGroupId}
                        required
                      >
                        <option value="">Seleccionar</option>
                        {createSubgroups.map((subgroup) => (
                          <option key={subgroup.id} value={subgroup.id}>{subgroup.description}</option>
                        ))}
                      </select>
                    </div>

                    <div className="flex flex-col gap-2 sm:col-span-2">
                      <Label htmlFor="create_item" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                        Descripcion del item
                      </Label>
                      <Input id="create_item" name="item" className="h-12 rounded-2xl border-border/80 bg-background/90" required />
                    </div>

                    <div className="flex flex-col gap-2 sm:col-span-2">
                      <Label htmlFor="create_unit_combobox" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                        Unidad de medida
                      </Label>
                      <div className="relative">
                        <Input
                          id="create_unit_combobox"
                          placeholder="Buscar y seleccionar unidad"
                          value={unitSearch}
                          onChange={handleUnitSearchChange}
                          onFocus={() => setUnitComboboxOpen(true)}
                          onBlur={() => window.setTimeout(() => setUnitComboboxOpen(false), 120)}
                          className="h-12 rounded-2xl border-border/80 bg-background/90 pr-12"
                          autoComplete="off"
                          required
                        />
                        <input type="hidden" name="unit_measure_id" value={createUnitId} readOnly />
                        <span className="pointer-events-none absolute inset-y-0 right-4 flex items-center text-muted-foreground">▾</span>

                        {unitComboboxOpen && (
                          <div className="absolute left-0 right-0 top-[calc(100%+0.35rem)] z-20 max-h-56 overflow-y-auto rounded-2xl border border-border/80 bg-background p-1 shadow-lg">
                            {filteredUnitMeasures.map((unit) => {
                              const label = `${unit.description ?? ""}${unit.abbreviation ? ` (${unit.abbreviation})` : ""}`;
                              const isSelected = String(unit.id) === String(createUnitId);

                              return (
                                <button
                                  key={unit.id}
                                  type="button"
                                  className={`flex w-full items-center justify-between rounded-xl px-3 py-2 text-left text-sm transition hover:bg-muted ${isSelected ? "bg-muted font-medium text-foreground" : "text-muted-foreground"}`}
                                  onMouseDown={(event) => {
                                    event.preventDefault();
                                    handleSelectUnitMeasure(unit);
                                  }}
                                >
                                  <span>{label}</span>
                                  {isSelected && <span className="text-xs uppercase tracking-[0.18em] text-emerald-700">Seleccionado</span>}
                                </button>
                              );
                            })}

                            {filteredUnitMeasures.length === 0 && (
                              <div className="px-3 py-3 text-sm text-muted-foreground">
                                No se encontraron unidades.
                              </div>
                            )}
                          </div>
                        )}
                      </div>
                      {selectedUnitMeasure && (
                        <p className="text-xs text-muted-foreground">
                          Seleccionado: {selectedUnitMeasure.description}{selectedUnitMeasure.abbreviation ? ` (${selectedUnitMeasure.abbreviation})` : ""}
                        </p>
                      )}
                    </div>

                    <div className="flex flex-col gap-2">
                      <Label htmlFor="create_status" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                        Estado
                      </Label>
                      <select
                        id="create_status"
                        name="status"
                        defaultValue="AC"
                        className="h-12 rounded-2xl border border-border/80 bg-background/90 px-4 text-sm text-foreground outline-none transition focus:border-foreground/20"
                        required
                      >
                        {(statuses.length > 0 ? statuses : [{ code: "AC", label: "ACTIVO" }, { code: "DC", label: "INACTIVO" }]).map((status) => (
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
                      ) : "Guardar"}
                    </Button>
                  </div>
                </form>
              </CardContent>
            </Card>
          </div>
        </div>,
        document.body,
      )}

      {editOpen && createPortal(
        <div className="fixed inset-0 z-[80] flex justify-end bg-slate-950/20 backdrop-blur-[1px]">
          <div className="w-full max-w-2xl overflow-y-auto border-l border-border/70 bg-background/96 p-4 shadow-[0_0_60px_rgba(15,23,42,0.16)] backdrop-blur xl:p-6">
            <Card className="border border-border/70 bg-white/92 shadow-[0_24px_90px_rgba(15,23,42,0.08)]">
              <CardHeader className="border-b border-border/70 bg-muted/20">
                <div className="flex items-start justify-between gap-4">
                  <div>
                    <CardTitle className="text-2xl tracking-[-0.04em]">Editar Item</CardTitle>
                    <CardDescription>
                      {editItem?.codigo_item ? `Código: ${editItem.codigo_item}` : "Actualiza la información base del item seleccionado."}
                    </CardDescription>
                  </div>

                  <Button variant="ghost" size="icon-sm" className="rounded-full" onClick={closeEdit}>
                    <X />
                  </Button>
                </div>
              </CardHeader>

              <CardContent className="p-5 sm:p-6">
                {editItem && (
                  <form className="flex flex-col gap-5" onSubmit={handleEditSubmit}>
                    {editErrors.length > 0 && (
                      <Alert variant="destructive" className="rounded-2xl">
                        <AlertDescription>
                          <ul className="list-disc space-y-1 pl-4">
                            {editErrors.map((message) => (
                              <li key={message}>{message}</li>
                            ))}
                          </ul>
                        </AlertDescription>
                      </Alert>
                    )}

                    <div className="grid gap-5 sm:grid-cols-2">
                      <div className="flex flex-col gap-2">
                        <Label htmlFor="edit_group" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                          Grupo
                        </Label>
                        <select
                          id="edit_group"
                          name="group_id"
                          value={editGroupId}
                          onChange={handleEditGroupChange}
                          className="h-12 rounded-2xl border border-border/80 bg-background/90 px-4 text-sm text-foreground outline-none transition focus:border-foreground/20"
                          required
                        >
                          <option value="">Seleccionar</option>
                          {groups.map((group) => (
                            <option key={group.id} value={group.id}>{group.name}</option>
                          ))}
                        </select>
                      </div>

                      <div className="flex flex-col gap-2">
                        <Label htmlFor="edit_subgroup" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                          Subgrupo
                        </Label>
                        <select
                          id="edit_subgroup"
                          name="subgroup_id"
                          value={editSubgroupId}
                          onChange={(event) => setEditSubgroupId(event.target.value)}
                          className="h-12 rounded-2xl border border-border/80 bg-background/90 px-4 text-sm text-foreground outline-none transition focus:border-foreground/20"
                          disabled={!editGroupId}
                          required
                        >
                          <option value="">Seleccionar</option>
                          {editSubgroups.map((subgroup) => (
                            <option key={subgroup.id} value={subgroup.id}>{subgroup.description}</option>
                          ))}
                        </select>
                      </div>

                      <div className="flex flex-col gap-2 sm:col-span-2">
                        <Label htmlFor="edit_name" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                          Descripcion del item
                        </Label>
                        <Input id="edit_name" name="item" defaultValue={editItem.name} className="h-12 rounded-2xl border-border/80 bg-background/90" required />
                      </div>

                      <div className="flex flex-col gap-2 sm:col-span-2">
                        <Label htmlFor="edit_unit_combobox" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                          Unidad de medida
                        </Label>
                        <div className="relative">
                          <Input
                            id="edit_unit_combobox"
                            placeholder="Buscar y seleccionar unidad"
                            value={editUnitSearch}
                            onChange={handleEditUnitSearchChange}
                            onFocus={() => setEditUnitComboboxOpen(true)}
                            onBlur={() => window.setTimeout(() => setEditUnitComboboxOpen(false), 120)}
                            className="h-12 rounded-2xl border-border/80 bg-background/90 pr-12"
                            autoComplete="off"
                            role="combobox"
                            aria-expanded={editUnitComboboxOpen}
                            aria-controls="edit_unit_options"
                            required
                          />
                          <input type="hidden" name="unit_measure_id" value={editUnitId} readOnly />
                          <button
                            type="button"
                            className="absolute inset-y-0 right-3 flex items-center px-1 text-muted-foreground"
                            onMouseDown={(event) => {
                              event.preventDefault();
                              setEditUnitComboboxOpen((current) => !current);
                            }}
                            aria-label="Mostrar unidades de medida"
                          >
                            ▾
                          </button>

                          {editUnitComboboxOpen && (
                            <div id="edit_unit_options" className="absolute left-0 right-0 top-[calc(100%+0.35rem)] z-20 max-h-56 overflow-y-auto rounded-2xl border border-border/80 bg-background p-1 shadow-lg">
                              {filteredEditUnitMeasures.map((unit) => {
                                const label = `${unit.description ?? ""}${unit.abbreviation ? ` (${unit.abbreviation})` : ""}`;
                                const isSelected = String(unit.id) === String(editUnitId);

                                return (
                                  <button
                                    key={unit.id}
                                    type="button"
                                    className={`flex w-full items-center justify-between rounded-xl px-3 py-2 text-left text-sm transition hover:bg-muted ${isSelected ? "bg-muted font-medium text-foreground" : "text-muted-foreground"}`}
                                    onMouseDown={(event) => {
                                      event.preventDefault();
                                      handleSelectEditUnitMeasure(unit);
                                    }}
                                  >
                                    <span>{label}</span>
                                    {isSelected && <span className="text-xs uppercase tracking-[0.18em] text-emerald-700">Seleccionado</span>}
                                  </button>
                                );
                              })}

                              {filteredEditUnitMeasures.length === 0 && (
                                <div className="px-3 py-3 text-sm text-muted-foreground">
                                  No se encontraron unidades.
                                </div>
                              )}
                            </div>
                          )}
                        </div>
                        {selectedEditUnitMeasure && (
                          <p className="text-xs text-muted-foreground">
                            Seleccionado: {selectedEditUnitMeasure.description}{selectedEditUnitMeasure.abbreviation ? ` (${selectedEditUnitMeasure.abbreviation})` : ""}
                          </p>
                        )}
                      </div>

                      <div className="flex flex-col gap-2">
                        <Label htmlFor="edit_status" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                          Estado
                        </Label>
                        <select
                          id="edit_status"
                          name="status"
                          defaultValue={editItem.status ?? "AC"}
                          className="h-12 rounded-2xl border border-border/80 bg-background/90 px-4 text-sm text-foreground outline-none transition focus:border-foreground/20"
                          required
                        >
                          {(statuses.length > 0 ? statuses : [{ code: "AC", label: "ACTIVO" }, { code: "DC", label: "INACTIVO" }]).map((status) => (
                            <option key={status.code} value={status.code}>{status.label}</option>
                          ))}
                        </select>
                      </div>
                    </div>

                    <Separator className="bg-border/70" />

                    <div className="flex flex-wrap justify-end gap-2">
                      <Button type="button" variant="outline" className="rounded-full border-border/70 bg-background/80" onClick={closeEdit}>
                        Cancelar
                      </Button>
                      <Button type="submit" className="rounded-full bg-foreground text-background hover:bg-foreground/90" disabled={updateMutation.isPending}>
                        {updateMutation.isPending ? (
                          <>
                            <Loader2 className="mr-2 size-4 animate-spin" />
                            Guardando...
                          </>
                        ) : "Guardar cambios"}
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

      {deactivateImpactOpen && createPortal(
        <div className="fixed inset-0 z-[90] flex items-center justify-center bg-slate-950/30 p-4 backdrop-blur-[2px]">
          <Card className="w-full max-w-3xl border border-border/70 bg-white/95 shadow-[0_24px_90px_rgba(15,23,42,0.18)]">
            <CardHeader className="border-b border-border/70 bg-muted/20">
              <div className="flex items-start justify-between gap-4">
                <div>
                  <CardTitle className="text-2xl tracking-[-0.04em]">Desactivar Item</CardTitle>
                  <CardDescription>
                    Revisa en qué proyectos se usa y qué insumos componen este item antes de desactivarlo.
                  </CardDescription>
                </div>
                <Button variant="ghost" size="icon-sm" className="rounded-full" onClick={closeDeactivateImpact}>
                  <X />
                </Button>
              </div>
            </CardHeader>

            <CardContent className="flex max-h-[78vh] flex-col gap-5 overflow-y-auto p-5 sm:p-6">
              <div className="rounded-2xl border border-border/70 bg-muted/20 p-4">
                {deactivateImpactLoading ? (
                  <div className="flex items-center gap-2 text-sm text-muted-foreground">
                    <Loader2 className="size-4 animate-spin" />
                    Cargando impacto de desactivación...
                  </div>
                ) : deactivateImpactIsError ? (
                  <Alert variant="destructive" className="rounded-2xl">
                    <AlertDescription>No se pudo cargar el impacto de desactivación del ítem.</AlertDescription>
                  </Alert>
                ) : (
                  <div className="flex flex-col gap-4">
                    <p className="text-sm text-muted-foreground">
                      Este ítem está usado en <strong className="text-foreground">{deactivateImpactSummary.projects_count}</strong> proyecto(s)
                      {" "}y tiene <strong className="text-foreground">{deactivateImpactSummary.inputs_count}</strong> insumo(s) activo(s).
                    </p>

                    {deactivateImpactSummary.projects_count === 0 && deactivateImpactSummary.inputs_count === 0 && (
                      <p className="rounded-xl border border-border/70 bg-background/80 p-3 text-sm text-muted-foreground">
                        No se encontraron proyectos ni insumos afectados.
                      </p>
                    )}

                    <div className="grid gap-4 lg:grid-cols-2">
                      <div className="rounded-2xl border border-border/70 bg-background/90 p-4">
                        <h3 className="text-sm font-semibold text-foreground">Proyectos afectados</h3>
                        <div className="mt-3 max-h-64 overflow-y-auto">
                          {impactedProjects.length > 0 ? (
                            <div className="flex flex-col divide-y divide-border/70">
                              {impactedProjects.map((project) => (
                                <div key={`${project.id_proyecto}-${project.module}-${project.quantity}`} className="py-3 first:pt-0 last:pb-0">
                                  <p className="text-sm font-medium leading-6 text-foreground">{project.name}</p>
                                  <div className="mt-1 flex flex-wrap gap-2 text-xs text-muted-foreground">
                                    <span>Estado: {project.approval_label ?? project.approval_status ?? "-"}</span>
                                    <span>Cantidad: {Number(project.quantity || 0).toLocaleString("es-BO")}</span>
                                    <span>Módulo: {project.module ?? "General"}</span>
                                  </div>
                                </div>
                              ))}
                            </div>
                          ) : (
                            <p className="text-sm text-muted-foreground">No hay proyectos activos usando este ítem.</p>
                          )}
                        </div>
                      </div>

                      <div className="rounded-2xl border border-border/70 bg-background/90 p-4">
                        <h3 className="text-sm font-semibold text-foreground">Insumos del ítem</h3>
                        <div className="mt-3 max-h-64 overflow-y-auto">
                          {impactedInputs.length > 0 ? (
                            <div className="flex flex-col divide-y divide-border/70">
                              {impactedInputs.map((input) => (
                                <div key={input.id_insumo} className="py-3 first:pt-0 last:pb-0">
                                  <p className="text-sm font-medium leading-6 text-foreground">{input.description}</p>
                                  <div className="mt-1 grid gap-1 text-xs text-muted-foreground sm:grid-cols-2">
                                    <span>Tipo: {input.type ?? "-"}</span>
                                    <span>Unidad: {input.unit ?? "-"}</span>
                                    <span>Cantidad: {Number(input.quantity || 0).toLocaleString("es-BO")}</span>
                                    <span>Parcial: {Number(input.partial || 0).toLocaleString("es-BO", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                                  </div>
                                </div>
                              ))}
                            </div>
                          ) : (
                            <p className="text-sm text-muted-foreground">No hay insumos activos en este ítem.</p>
                          )}
                        </div>
                      </div>
                    </div>
                  </div>
                )}
              </div>

              <div className="flex flex-wrap justify-end gap-2">
                <Button type="button" variant="outline" className="rounded-full border-border/70 bg-background/80" onClick={closeDeactivateImpact}>
                  Cancelar
                </Button>
                <Button
                  type="button"
                  className="rounded-full bg-rose-600 text-white hover:bg-rose-700"
                  disabled={deactivateImpactLoading || deactivateImpactIsError || updateMutation.isPending}
                  onClick={confirmDeactivateItem}
                >
                  {updateMutation.isPending ? (
                    <>
                      <Loader2 className="mr-2 size-4 animate-spin" />
                      Desactivando...
                    </>
                  ) : "Desactivar de todos modos"}
                </Button>
              </div>
            </CardContent>
          </Card>
        </div>,
        document.body,
      )}

      {filesOpen && createPortal(
        <div className="fixed inset-0 z-[80] flex justify-end bg-slate-950/20 backdrop-blur-[1px]">
          <div className="w-full max-w-3xl overflow-y-auto border-l border-border/70 bg-background/96 p-4 shadow-[0_0_60px_rgba(15,23,42,0.16)] backdrop-blur xl:p-6">
            <Card className="border border-border/70 bg-white/92 shadow-[0_24px_90px_rgba(15,23,42,0.08)]">
              <CardHeader className="border-b border-border/70 bg-muted/20">
                <div className="flex items-start justify-between gap-4">
                  <div>
                    <CardTitle className="text-2xl tracking-[-0.04em]">Adjuntar Archivos al Item</CardTitle>
                    <CardDescription>
                      {currentFilesItem?.name ?? filesItem?.name ?? "Carga especificaciones y ficha tecnica del item."}
                    </CardDescription>
                  </div>
                  <Button variant="ghost" size="icon-sm" className="rounded-full" onClick={closeFiles}>
                    <X />
                  </Button>
                </div>
              </CardHeader>

              <CardContent className="p-5 sm:p-6">
                <form className="flex flex-col gap-6" onSubmit={handleFilesSubmit}>
                  <div className="flex flex-col gap-2">
                    <Label htmlFor="files_item_name" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">Item</Label>
                    <Input id="files_item_name" value={currentFilesItem?.name ?? ""} className="h-12 rounded-2xl border-border/80 bg-muted/20" readOnly />
                  </div>

                  <div className="grid gap-5 sm:grid-cols-2">
                    <div className="flex flex-col gap-2">
                      <Label htmlFor="specification_file" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">Especificaciones Tecnicas</Label>
                      <Input id="specification_file" name="specification_file" type="file" className="h-12 rounded-2xl border-border/80 bg-background/90 file:mr-4 file:rounded-full file:border-0 file:bg-muted file:px-4 file:py-2" />
                    </div>
                    <div className="flex flex-col gap-2">
                      <span className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">Especificaciones Tecnicas Cargada</span>
                      {specificationUrl ? (
                        <a href={specificationUrl} target="_blank" rel="noreferrer" className="text-sm text-teal-700 hover:underline">Ver especificaciones tecnicas</a>
                      ) : (
                        <span className="text-sm text-muted-foreground">Sin archivo cargado</span>
                      )}
                    </div>

                    <div className="flex flex-col gap-2">
                      <Label htmlFor="sheet_file" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">Ficha Tecnica</Label>
                      <Input id="sheet_file" name="sheet_file" type="file" className="h-12 rounded-2xl border-border/80 bg-background/90 file:mr-4 file:rounded-full file:border-0 file:bg-muted file:px-4 file:py-2" />
                    </div>
                    <div className="flex flex-col gap-2">
                      <span className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">Ficha Tecnica Cargada</span>
                      {sheetUrl ? (
                        <a href={sheetUrl} target="_blank" rel="noreferrer" className="text-sm text-teal-700 hover:underline">Ver ficha tecnica</a>
                      ) : (
                        <span className="text-sm text-muted-foreground">Sin archivo cargado</span>
                      )}
                    </div>
                  </div>

                  {filesError && (
                    <Alert variant="destructive">
                      <AlertDescription>No se pudieron cargar los datos actuales del item.</AlertDescription>
                    </Alert>
                  )}

                  <Button type="submit" className="h-12 rounded-full bg-emerald-600 text-white hover:bg-emerald-700" disabled={updateFilesMutation.isPending || filesLoading || !currentFilesItem}>
                    {updateFilesMutation.isPending ? (
                      <>
                        <Loader2 className="mr-2 size-4 animate-spin" />
                        Guardando...
                      </>
                    ) : "Agregar"}
                  </Button>

                  {filesLoading && (
                    <div className="flex items-center text-sm text-muted-foreground">
                      <Loader2 className="mr-2 size-4 animate-spin" /> Cargando datos del item...
                    </div>
                  )}
                </form>
              </CardContent>
            </Card>
          </div>
        </div>,
        document.body,
      )}

      {exportChoice && createPortal(
        <div className="fixed inset-0 z-[90] flex items-center justify-center bg-slate-950/30 p-4 backdrop-blur-[1px]">
          <Card className="w-full max-w-md border border-border/70 bg-white shadow-[0_24px_90px_rgba(15,23,42,0.18)]">
            <CardHeader className="border-b border-border/70 bg-muted/20">
              <div className="flex items-start justify-between gap-4">
                <div>
                  <CardTitle className="text-2xl tracking-[-0.04em]">Formato del reporte</CardTitle>
                  <CardDescription>
                    {exportChoice.report === "unit-price" && "Análisis de precios unitarios"}
                    {exportChoice.report === "materials" && "Desglose de materiales"}
                    {exportChoice.report === "labor" && "Desglose de mano de obra"}
                    {exportChoice.report === "machinery" && "Desglose de herramientas"}
                  </CardDescription>
                </div>
                <Button variant="ghost" size="icon-sm" className="rounded-full" onClick={closeExportChoice}>
                  <X />
                </Button>
              </div>
            </CardHeader>

            <CardContent className="space-y-5 p-5 sm:p-6">
              <div className="rounded-2xl border border-border/70 bg-muted/20 p-4">
                <p className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">Item</p>
                <p className="mt-2 text-sm font-semibold text-foreground">{exportChoice.item?.name ?? exportChoice.item?.item ?? "-"}</p>
              </div>

              <div className="grid gap-3 sm:grid-cols-2">
                <Button type="button" className="h-12 rounded-full bg-emerald-600 text-white hover:bg-emerald-700" onClick={handleExportChoicePdf}>
                  <FileText className="mr-2 size-4" />
                  PDF
                </Button>
                <Button type="button" variant="outline" className="h-12 rounded-full" onClick={handleExportChoiceXlsx}>
                  <FileDown className="mr-2 size-4" />
                  XLSX
                </Button>
              </div>
            </CardContent>
          </Card>
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
                    <CardTitle className="text-2xl tracking-[-0.04em]">Recalcular precio Item</CardTitle>
                    <CardDescription>Recalcular precios unitarios por periodos de tiempo.</CardDescription>
                  </div>
                  <Button variant="ghost" size="icon-sm" className="rounded-full" onClick={closeRecalculate}>
                    <X />
                  </Button>
                </div>
              </CardHeader>

              <CardContent className="p-5 sm:p-6">
                <form className="flex flex-col gap-5" onSubmit={handleRecalculateSubmit}>
                  <div className="flex flex-col gap-2">
                    <Label htmlFor="recalculate_item" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">Item</Label>
                    <Input id="recalculate_item" value={recalculateItem?.name ?? ""} className="h-12 rounded-2xl border-border/80 bg-background/90" disabled />
                  </div>

                  <div className="flex flex-col gap-2">
                    <Label htmlFor="recalculate_date" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">Seleccione Fecha de Impresion/calculo</Label>
                    <Input id="recalculate_date" type="date" value={recalculateDate} onChange={(event) => setRecalculateDate(event.target.value)} className="h-12 rounded-2xl border-border/80 bg-background/90" required />
                  </div>

                  <div className="flex justify-end gap-3">
                    <Button type="button" variant="outline" className="h-12 min-w-36 rounded-full" onClick={handleRecalculateXlsx} disabled={reportLoadingItemId === recalculateItem?.id_item || !recalculateDate}>
                      Exportar XLSX
                    </Button>
                    <Button type="submit" className="h-12 min-w-36 rounded-full bg-emerald-600 text-white hover:bg-emerald-700" disabled={reportLoadingItemId === recalculateItem?.id_item || !recalculateDate}>
                      {reportLoadingItemId === recalculateItem?.id_item ? (
                        <>
                          <Loader2 className="mr-2 size-4 animate-spin" />
                          Generando...
                        </>
                      ) : "Recalcular PDF"}
                    </Button>
                  </div>
                </form>
              </CardContent>
            </Card>
          </div>
        </div>,
        document.body,
      )}

      {breakdownOpen && createPortal(
        <div className="fixed inset-0 z-[80] flex justify-end bg-slate-950/20 backdrop-blur-[1px]">
          <div className="w-full max-w-3xl overflow-y-auto border-l border-border/70 bg-background/96 p-4 shadow-[0_0_60px_rgba(15,23,42,0.16)] backdrop-blur xl:p-6">
            <Card className="border border-border/70 bg-white/92 shadow-[0_24px_90px_rgba(15,23,42,0.08)]">
              <CardHeader className="border-b border-border/70 bg-muted/20">
                <div className="flex items-start justify-between gap-4">
                  <div>
                    <CardTitle className="text-2xl tracking-[-0.04em]">Recalcular Desgloses</CardTitle>
                    <CardDescription>Recalcular hoja de desglose segun tipo de insumo.</CardDescription>
                  </div>
                  <Button variant="ghost" size="icon-sm" className="rounded-full" onClick={closeBreakdown}>
                    <X />
                  </Button>
                </div>
              </CardHeader>

              <CardContent className="p-5 sm:p-6">
                <form className="flex flex-col gap-5" onSubmit={handleBreakdownSubmit}>
                  <div className="flex flex-col gap-2">
                    <Label htmlFor="breakdown_item" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">Item</Label>
                    <Input id="breakdown_item" value={breakdownItem?.name ?? ""} className="h-12 rounded-2xl border-border/80 bg-background/90" disabled />
                  </div>

                  <div className="flex flex-col gap-2">
                    <Label htmlFor="breakdown_type" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">Tipo de Insumo Desglose</Label>
                    <select id="breakdown_type" value={breakdownType} onChange={(event) => setBreakdownType(event.target.value)} className="h-12 rounded-2xl border border-border/80 bg-background/90 px-4 text-sm text-foreground outline-none transition focus:border-foreground/20" required>
                      <option value="">Seleccionar</option>
                      <option value="materiales">Materiales</option>
                      <option value="mano_obra">Mano de Obra</option>
                      <option value="maquinaria">Maquinaria</option>
                    </select>
                  </div>

                  <div className="flex flex-col gap-2">
                    <Label htmlFor="breakdown_date" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">Seleccione Fecha de Impresion/calculo</Label>
                    <Input id="breakdown_date" type="date" value={breakdownDate} onChange={(event) => setBreakdownDate(event.target.value)} className="h-12 rounded-2xl border-border/80 bg-background/90" required />
                  </div>

                  <div className="flex justify-end gap-3">
                    <Button type="button" variant="outline" className="h-12 w-44 rounded-full" onClick={handleBreakdownXlsx} disabled={!breakdownDate || !breakdownType}>
                      Exportar XLSX
                    </Button>
                    <Button type="submit" className="h-12 w-44 rounded-full bg-emerald-600 text-white hover:bg-emerald-700">
                      Recalcular Desgloses
                    </Button>
                  </div>
                </form>
              </CardContent>
            </Card>
          </div>
        </div>,
        document.body,
      )}

      {materialsOpen && createPortal(
        <div className="fixed inset-0 z-[80] flex justify-end bg-slate-950/20 backdrop-blur-[1px]">
          <div className="w-full max-w-4xl overflow-y-auto border-l border-border/70 bg-background/96 p-4 shadow-[0_0_60px_rgba(15,23,42,0.16)] backdrop-blur xl:p-6">
            <Card className="border border-border/70 bg-white/92 shadow-[0_24px_90px_rgba(15,23,42,0.08)]">
              <CardHeader className="border-b border-border/70 bg-muted/20">
                <div className="flex items-start justify-between gap-4">
                  <div>
                    <CardTitle className="text-2xl tracking-[-0.04em]">Agregar Material al Item</CardTitle>
                    <CardDescription>
                      {materialContext?.item?.name ?? materialsItem?.name ?? "Gestiona los materiales del item seleccionado."}
                    </CardDescription>
                  </div>

                  <Button variant="ghost" size="icon-sm" className="rounded-full" onClick={closeMaterials}>
                    <X />
                  </Button>
                </div>
              </CardHeader>

              <CardContent className="flex flex-col gap-6 p-5 sm:p-6">
                <div className="grid gap-4 lg:grid-cols-2">
                  <div className="rounded-2xl border border-border/70 bg-background/80 p-4">
                    <p className="text-xs font-semibold uppercase tracking-[0.2em] text-muted-foreground">Item</p>
                    <p className="mt-2 text-sm font-medium text-foreground">{materialContext?.item?.name ?? "-"}</p>
                    <p className="mt-3 text-xs font-semibold uppercase tracking-[0.2em] text-muted-foreground">Unidad de Medida</p>
                    <p className="mt-2 text-sm text-muted-foreground">{materialContext?.item?.unit_measure?.abbreviation ?? "-"}</p>
                  </div>

                  <div className="rounded-2xl border border-slate-500/40 bg-slate-600 px-5 py-4 text-white">
                    <p className="text-xs font-semibold uppercase tracking-[0.2em] text-slate-100">Precio Actual Item</p>
                    <p className="mt-2 text-2xl font-semibold">{Number(materialsTotal || 0).toFixed(2)} Bs.</p>
                  </div>
                </div>

                <form className="grid gap-4 lg:grid-cols-[1fr_180px_auto] lg:items-end" onSubmit={handleAddMaterial}>
                  <div className="flex flex-col gap-2">
                    <Label htmlFor="material_combobox" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">Material</Label>
                    <div className="relative">
                      <Input
                        id="material_combobox"
                        placeholder="Buscar y seleccionar material"
                        className="h-12 rounded-2xl border-border/80 bg-background/90 pr-12"
                        value={materialSearch}
                        onChange={handleMaterialSearchChange}
                        onFocus={() => setMaterialComboboxOpen(true)}
                        onBlur={() => window.setTimeout(() => setMaterialComboboxOpen(false), 120)}
                        autoComplete="off"
                        required
                      />
                      <input type="hidden" name="id_insumo" value={selectedMaterialId} readOnly />
                      <span className="pointer-events-none absolute inset-y-0 right-4 flex items-center text-muted-foreground">▾</span>

                      {materialComboboxOpen && (
                        <div className="absolute left-0 right-0 top-[calc(100%+0.35rem)] z-20 max-h-56 overflow-y-auto rounded-2xl border border-border/80 bg-background p-1 shadow-lg">
                          {materialOptions.map((material) => {
                            const isSelected = String(material.id) === String(selectedMaterialId);

                            return (
                              <button
                                key={material.id}
                                type="button"
                                className={`flex w-full items-center justify-between rounded-xl px-3 py-2 text-left text-sm transition hover:bg-muted ${isSelected ? "bg-muted font-medium text-foreground" : "text-muted-foreground"}`}
                                onMouseDown={(event) => {
                                  event.preventDefault();
                                  handleSelectMaterial(material);
                                }}
                              >
                                <span>{material.text}</span>
                                {isSelected && <span className="text-xs uppercase tracking-[0.18em] text-emerald-700">Seleccionado</span>}
                              </button>
                            );
                          })}

                          {materialOptions.length === 0 && (
                            <div className="px-3 py-3 text-sm text-muted-foreground">
                              No se encontraron materiales.
                            </div>
                          )}
                        </div>
                      )}
                    </div>
                  </div>

                  <div className="flex flex-col gap-2">
                    <Label htmlFor="cantidad" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">Cantidad</Label>
                    <Input id="cantidad" name="cantidad" type="number" min="0.0001" step="0.0001" className="h-12 rounded-2xl border-border/80 bg-background/90" required />
                  </div>

                  <Button type="submit" className="h-12 rounded-full bg-emerald-600 px-6 text-white hover:bg-emerald-700">
                    Agregar
                  </Button>
                </form>

                <div className="overflow-hidden rounded-[24px] border border-border/70 bg-background/90">
                  <div className="overflow-x-auto">
                    <table className="min-w-full border-collapse text-sm">
                      <thead>
                        <tr className="border-b border-border/70 bg-muted/30 text-left">
                          <th className="px-4 py-3 font-semibold text-foreground">Insumo</th>
                          <th className="px-4 py-3 font-semibold text-foreground">Unidad</th>
                          <th className="px-4 py-3 font-semibold text-foreground">Cantidad</th>
                          <th className="px-4 py-3 font-semibold text-foreground">Unitario</th>
                          <th className="px-4 py-3 font-semibold text-foreground">Parcial</th>
                          <th className="px-4 py-3 font-semibold text-foreground text-center">Quitar</th>
                        </tr>
                      </thead>
                      <tbody>
                        {materialRows.map((material, index) => (
                          <tr key={material.id_item_insumo || `new-${material.id_insumo}`} className={index < materialRows.length - 1 ? "border-b border-border/60" : ""}>
                            <td className="px-4 py-3">{material.descripcion ?? "-"}</td>
                            <td className="px-4 py-3">{material.unidad ?? "-"}</td>
                            <td className="px-4 py-3">
                              <Input
                                type="number"
                                min="0.0001"
                                step="0.0001"
                                value={material.cantidad}
                                onChange={(event) => handleMaterialQuantityChange(material, event.target.value)}
                                className="h-10 min-w-28 rounded-xl border-border/80 bg-background/90"
                              />
                            </td>
                            <td className="px-4 py-3">{Number(material.precio_unitario || 0).toFixed(2)}</td>
                            <td className="px-4 py-3">{Number(material.parcial || 0).toFixed(2)}</td>
                            <td className="px-4 py-3 text-center">
                              <Button type="button" className="h-9 rounded-md bg-red-600 px-3 text-white hover:bg-red-700" onClick={() => handleRemoveMaterial(material)}>
                                <Trash2 className="size-4" />
                              </Button>
                            </td>
                          </tr>
                        ))}
                        {materialRows.length === 0 && !materialsLoading && (
                          <tr>
                            <td colSpan={6} className="px-4 py-8 text-center text-muted-foreground">No hay materiales registrados.</td>
                          </tr>
                        )}
                      </tbody>
                    </table>
                  </div>
                </div>

                <div className="rounded-2xl border border-border/70 bg-muted/20 p-4">
                  <p className="text-3xl font-semibold tracking-[-0.03em] text-foreground">Total</p>
                  <p className="mt-1 text-lg font-medium text-muted-foreground">{materialsTotal.toFixed(2)} Bs.</p>
                </div>

                <div className="flex flex-col gap-3 border-t border-border/70 pt-4 sm:flex-row sm:justify-end">
                  <Button type="button" variant="outline" className="rounded-full" onClick={closeMaterials}>
                    Cancelar
                  </Button>
                  <Button type="button" className="rounded-full bg-foreground text-background hover:bg-foreground/90" onClick={handleSaveMaterials} disabled={syncMaterialsMutation.isPending || materialsLoading}>
                    {syncMaterialsMutation.isPending ? <Loader2 className="mr-2 size-4 animate-spin" /> : null}
                    Guardar materiales
                  </Button>
                </div>

                {(inputOptionsLoading || materialsLoading) && (
                  <div className="flex items-center text-sm text-muted-foreground">
                    <Loader2 className="mr-2 size-4 animate-spin" /> Cargando datos...
                  </div>
                )}
              </CardContent>
            </Card>
          </div>
        </div>,
        document.body,
      )}

      {laborOpen && createPortal(
        <div className="fixed inset-0 z-[80] flex justify-end bg-slate-950/20 backdrop-blur-[1px]">
          <div className="w-full max-w-4xl overflow-y-auto border-l border-border/70 bg-background/96 p-4 shadow-[0_0_60px_rgba(15,23,42,0.16)] backdrop-blur xl:p-6">
            <Card className="border border-border/70 bg-white/92 shadow-[0_24px_90px_rgba(15,23,42,0.08)]">
              <CardHeader className="border-b border-border/70 bg-muted/20">
                <div className="flex items-start justify-between gap-4">
                  <div>
                    <CardTitle className="text-2xl tracking-[-0.04em]">Mano de Obra del Item</CardTitle>
                    <CardDescription>
                      {laborContext?.item?.name ?? laborItem?.name ?? "Gestiona la mano de obra del item seleccionado."}
                    </CardDescription>
                  </div>

                  <Button variant="ghost" size="icon-sm" className="rounded-full" onClick={closeLabor}>
                    <X />
                  </Button>
                </div>
              </CardHeader>

              <CardContent className="flex flex-col gap-6 p-5 sm:p-6">
                <div className="grid gap-4 lg:grid-cols-2">
                  <div className="rounded-2xl border border-border/70 bg-background/80 p-4">
                    <p className="text-xs font-semibold uppercase tracking-[0.2em] text-muted-foreground">Item</p>
                    <p className="mt-2 text-sm font-medium text-foreground">{laborContext?.item?.name ?? "-"}</p>
                    <p className="mt-3 text-xs font-semibold uppercase tracking-[0.2em] text-muted-foreground">Unidad de Medida</p>
                    <p className="mt-2 text-sm text-muted-foreground">{laborContext?.item?.unit_measure?.abbreviation ?? "-"}</p>
                  </div>

                  <div className="rounded-2xl border border-slate-500/40 bg-slate-600 px-5 py-4 text-white">
                    <p className="text-xs font-semibold uppercase tracking-[0.2em] text-slate-100">Precio Actual Item</p>
                    <p className="mt-2 text-2xl font-semibold">{Number(laborTotal || 0).toFixed(2)} Bs.</p>
                  </div>
                </div>

                <form className="grid gap-4 lg:grid-cols-[1fr_180px_auto] lg:items-end" onSubmit={handleAddLabor}>
                  <div className="flex flex-col gap-2">
                    <Label htmlFor="labor_combobox" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">Mano de Obra del Item</Label>
                    <div className="relative">
                      <Input
                        id="labor_combobox"
                        placeholder="Buscar y seleccionar mano de obra"
                        className="h-12 rounded-2xl border-border/80 bg-background/90 pr-12"
                        value={laborSearch}
                        onChange={handleLaborSearchChange}
                        onFocus={() => setLaborComboboxOpen(true)}
                        onBlur={() => window.setTimeout(() => setLaborComboboxOpen(false), 120)}
                        autoComplete="off"
                        required
                      />
                      <input type="hidden" name="id_insumo" value={selectedLaborId} readOnly />
                      <span className="pointer-events-none absolute inset-y-0 right-4 flex items-center text-muted-foreground">▾</span>

                      {laborComboboxOpen && (
                        <div className="absolute left-0 right-0 top-[calc(100%+0.35rem)] z-20 max-h-56 overflow-y-auto rounded-2xl border border-border/80 bg-background p-1 shadow-lg">
                          {laborOptions.map((labor) => {
                            const isSelected = String(labor.id) === String(selectedLaborId);

                            return (
                              <button
                                key={labor.id}
                                type="button"
                                className={`flex w-full items-center justify-between rounded-xl px-3 py-2 text-left text-sm transition hover:bg-muted ${isSelected ? "bg-muted font-medium text-foreground" : "text-muted-foreground"}`}
                                onMouseDown={(event) => {
                                  event.preventDefault();
                                  handleSelectLabor(labor);
                                }}
                              >
                                <span>{labor.text}</span>
                                {isSelected && <span className="text-xs uppercase tracking-[0.18em] text-emerald-700">Seleccionado</span>}
                              </button>
                            );
                          })}

                          {laborOptions.length === 0 && (
                            <div className="px-3 py-3 text-sm text-muted-foreground">
                              No se encontró mano de obra.
                            </div>
                          )}
                        </div>
                      )}
                    </div>
                  </div>

                  <div className="flex flex-col gap-2">
                    <Label htmlFor="labor_cantidad" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">Cantidad</Label>
                    <Input id="labor_cantidad" name="cantidad" type="number" min="0.0001" step="0.0001" className="h-12 rounded-2xl border-border/80 bg-background/90" required />
                  </div>

                  <Button type="submit" className="h-12 rounded-full bg-emerald-600 px-6 text-white hover:bg-emerald-700">
                    Agregar
                  </Button>
                </form>

                <div className="overflow-hidden rounded-[24px] border border-border/70 bg-background/90">
                  <div className="overflow-x-auto">
                    <table className="min-w-full border-collapse text-sm">
                      <thead>
                        <tr className="border-b border-border/70 bg-muted/30 text-left">
                          <th className="px-4 py-3 font-semibold text-foreground">Insumo</th>
                          <th className="px-4 py-3 font-semibold text-foreground">Unidad</th>
                          <th className="px-4 py-3 font-semibold text-foreground">Cantidad</th>
                          <th className="px-4 py-3 font-semibold text-foreground">Unitario</th>
                          <th className="px-4 py-3 font-semibold text-foreground">Parcial</th>
                          <th className="px-4 py-3 font-semibold text-foreground text-center">Quitar</th>
                        </tr>
                      </thead>
                      <tbody>
                        {laborRows.map((labor, index) => (
                          <tr key={labor.id_item_insumo || `new-labor-${labor.id_insumo}`} className={index < laborRows.length - 1 ? "border-b border-border/60" : ""}>
                            <td className="px-4 py-3">{labor.descripcion ?? "-"}</td>
                            <td className="px-4 py-3">{labor.unidad ?? "-"}</td>
                            <td className="px-4 py-3">
                              <Input
                                type="number"
                                min="0.0001"
                                step="0.0001"
                                value={labor.cantidad}
                                onChange={(event) => handleLaborQuantityChange(labor, event.target.value)}
                                className="h-10 min-w-28 rounded-xl border-border/80 bg-background/90"
                              />
                            </td>
                            <td className="px-4 py-3">{Number(labor.precio_unitario || 0).toFixed(2)}</td>
                            <td className="px-4 py-3">{Number(labor.parcial || 0).toFixed(2)}</td>
                            <td className="px-4 py-3 text-center">
                              <Button type="button" className="h-9 rounded-md bg-red-600 px-3 text-white hover:bg-red-700" onClick={() => handleRemoveLabor(labor)}>
                                <Trash2 className="size-4" />
                              </Button>
                            </td>
                          </tr>
                        ))}
                        {laborRows.length === 0 && !laborLoading && (
                          <tr>
                            <td colSpan={6} className="px-4 py-8 text-center text-muted-foreground">No hay mano de obra registrada.</td>
                          </tr>
                        )}
                      </tbody>
                    </table>
                  </div>
                </div>

                <div className="rounded-2xl border border-border/70 bg-muted/20 p-4">
                  <p className="text-3xl font-semibold tracking-[-0.03em] text-foreground">Total</p>
                  <p className="mt-1 text-lg font-medium text-muted-foreground">{laborTotal.toFixed(2)} Bs.</p>
                </div>

                <div className="flex flex-col gap-3 border-t border-border/70 pt-4 sm:flex-row sm:justify-end">
                  <Button type="button" variant="outline" className="rounded-full" onClick={closeLabor}>
                    Cancelar
                  </Button>
                  <Button type="button" className="rounded-full bg-foreground text-background hover:bg-foreground/90" onClick={handleSaveLabor} disabled={syncLaborMutation.isPending || laborLoading}>
                    {syncLaborMutation.isPending ? <Loader2 className="mr-2 size-4 animate-spin" /> : null}
                    Guardar mano de obra
                  </Button>
                </div>

                {(laborOptionsLoading || laborLoading) && (
                  <div className="flex items-center text-sm text-muted-foreground">
                    <Loader2 className="mr-2 size-4 animate-spin" /> Cargando datos...
                  </div>
                )}
              </CardContent>
            </Card>
          </div>
        </div>,
        document.body,
      )}

      {machineryOpen && createPortal(
        <div className="fixed inset-0 z-[80] flex justify-end bg-slate-950/20 backdrop-blur-[1px]">
          <div className="w-full max-w-4xl overflow-y-auto border-l border-border/70 bg-background/96 p-4 shadow-[0_0_60px_rgba(15,23,42,0.16)] backdrop-blur xl:p-6">
            <Card className="border border-border/70 bg-white/92 shadow-[0_24px_90px_rgba(15,23,42,0.08)]">
              <CardHeader className="border-b border-border/70 bg-muted/20">
                <div className="flex items-start justify-between gap-4">
                  <div>
                    <CardTitle className="text-2xl tracking-[-0.04em]">Equipos, Maquinaria, Herramientas de Item</CardTitle>
                    <CardDescription>
                      {machineryContext?.item?.name ?? machineryItem?.name ?? "Gestiona maquinaria del item seleccionado."}
                    </CardDescription>
                  </div>

                  <Button variant="ghost" size="icon-sm" className="rounded-full" onClick={closeMachinery}>
                    <X />
                  </Button>
                </div>
              </CardHeader>

              <CardContent className="flex flex-col gap-6 p-5 sm:p-6">
                <div className="grid gap-4 lg:grid-cols-2">
                  <div className="rounded-2xl border border-border/70 bg-background/80 p-4">
                    <p className="text-xs font-semibold uppercase tracking-[0.2em] text-muted-foreground">Item</p>
                    <p className="mt-2 text-sm font-medium text-foreground">{machineryContext?.item?.name ?? "-"}</p>
                    <p className="mt-3 text-xs font-semibold uppercase tracking-[0.2em] text-muted-foreground">Unidad de Medida</p>
                    <p className="mt-2 text-sm text-muted-foreground">{machineryContext?.item?.unit_measure?.abbreviation ?? "-"}</p>
                  </div>

                  <div className="rounded-2xl border border-slate-500/40 bg-slate-600 px-5 py-4 text-white">
                    <p className="text-xs font-semibold uppercase tracking-[0.2em] text-slate-100">Precio Actual Item</p>
                    <p className="mt-2 text-2xl font-semibold">{Number(machineryTotal || 0).toFixed(2)} Bs.</p>
                  </div>
                </div>

                <form className="grid gap-4 lg:grid-cols-[1fr_180px_auto] lg:items-end" onSubmit={handleAddMachinery}>
                  <div className="flex flex-col gap-2">
                    <Label htmlFor="machinery_combobox" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">Equipos, Maquinaria, Herramientas de Item</Label>
                    <div className="relative">
                      <Input
                        id="machinery_combobox"
                        placeholder="Buscar y seleccionar maquinaria"
                        className="h-12 rounded-2xl border-border/80 bg-background/90 pr-12"
                        value={machinerySearch}
                        onChange={handleMachinerySearchChange}
                        onFocus={() => setMachineryComboboxOpen(true)}
                        onBlur={() => window.setTimeout(() => setMachineryComboboxOpen(false), 120)}
                        autoComplete="off"
                        required
                      />
                      <input type="hidden" name="id_insumo" value={selectedMachineryId} readOnly />
                      <span className="pointer-events-none absolute inset-y-0 right-4 flex items-center text-muted-foreground">▾</span>

                      {machineryComboboxOpen && (
                        <div className="absolute left-0 right-0 top-[calc(100%+0.35rem)] z-20 max-h-56 overflow-y-auto rounded-2xl border border-border/80 bg-background p-1 shadow-lg">
                          {machineryOptions.map((machinery) => {
                            const isSelected = String(machinery.id) === String(selectedMachineryId);

                            return (
                              <button
                                key={machinery.id}
                                type="button"
                                className={`flex w-full items-center justify-between rounded-xl px-3 py-2 text-left text-sm transition hover:bg-muted ${isSelected ? "bg-muted font-medium text-foreground" : "text-muted-foreground"}`}
                                onMouseDown={(event) => {
                                  event.preventDefault();
                                  handleSelectMachinery(machinery);
                                }}
                              >
                                <span>{machinery.text}</span>
                                {isSelected && <span className="text-xs uppercase tracking-[0.18em] text-emerald-700">Seleccionado</span>}
                              </button>
                            );
                          })}

                          {machineryOptions.length === 0 && (
                            <div className="px-3 py-3 text-sm text-muted-foreground">
                              No se encontró maquinaria.
                            </div>
                          )}
                        </div>
                      )}
                    </div>
                  </div>

                  <div className="flex flex-col gap-2">
                    <Label htmlFor="machinery_cantidad" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">Cantidad</Label>
                    <Input id="machinery_cantidad" name="cantidad" type="number" min="0.0001" step="0.0001" className="h-12 rounded-2xl border-border/80 bg-background/90" required />
                  </div>

                  <Button type="submit" className="h-12 rounded-full bg-emerald-600 px-6 text-white hover:bg-emerald-700">
                    Agregar
                  </Button>
                </form>

                <div className="overflow-hidden rounded-[24px] border border-border/70 bg-background/90">
                  <div className="overflow-x-auto">
                    <table className="min-w-full border-collapse text-sm">
                      <thead>
                        <tr className="border-b border-border/70 bg-muted/30 text-left">
                          <th className="px-4 py-3 font-semibold text-foreground">Insumo</th>
                          <th className="px-4 py-3 font-semibold text-foreground">Unidad</th>
                          <th className="px-4 py-3 font-semibold text-foreground">Cantidad</th>
                          <th className="px-4 py-3 font-semibold text-foreground">Unitario</th>
                          <th className="px-4 py-3 font-semibold text-foreground">Parcial</th>
                          <th className="px-4 py-3 font-semibold text-foreground text-center">Quitar</th>
                        </tr>
                      </thead>
                      <tbody>
                        {machineryRows.map((machinery, index) => (
                          <tr key={machinery.id_item_insumo || `new-machinery-${machinery.id_insumo}`} className={index < machineryRows.length - 1 ? "border-b border-border/60" : ""}>
                            <td className="px-4 py-3">{machinery.descripcion ?? "-"}</td>
                            <td className="px-4 py-3">{machinery.unidad ?? "-"}</td>
                            <td className="px-4 py-3">
                              <Input
                                type="number"
                                min="0.0001"
                                step="0.0001"
                                value={machinery.cantidad}
                                onChange={(event) => handleMachineryQuantityChange(machinery, event.target.value)}
                                className="h-10 min-w-28 rounded-xl border-border/80 bg-background/90"
                              />
                            </td>
                            <td className="px-4 py-3">{Number(machinery.precio_unitario || 0).toFixed(2)}</td>
                            <td className="px-4 py-3">{Number(machinery.parcial || 0).toFixed(2)}</td>
                            <td className="px-4 py-3 text-center">
                              <Button type="button" className="h-9 rounded-md bg-red-600 px-3 text-white hover:bg-red-700" onClick={() => handleRemoveMachinery(machinery)}>
                                <Trash2 className="size-4" />
                              </Button>
                            </td>
                          </tr>
                        ))}
                        {machineryRows.length === 0 && !machineryLoading && (
                          <tr>
                            <td colSpan={6} className="px-4 py-8 text-center text-muted-foreground">No hay maquinaria registrada.</td>
                          </tr>
                        )}
                      </tbody>
                    </table>
                  </div>
                </div>

                <div className="rounded-2xl border border-border/70 bg-muted/20 p-4">
                  <p className="text-3xl font-semibold tracking-[-0.03em] text-foreground">Total</p>
                  <p className="mt-1 text-lg font-medium text-muted-foreground">{machineryTotal.toFixed(2)} Bs.</p>
                </div>

                <div className="flex flex-col gap-3 border-t border-border/70 pt-4 sm:flex-row sm:justify-end">
                  <Button type="button" variant="outline" className="rounded-full" onClick={closeMachinery}>
                    Cancelar
                  </Button>
                  <Button type="button" className="rounded-full bg-foreground text-background hover:bg-foreground/90" onClick={handleSaveMachinery} disabled={syncMachineryMutation.isPending || machineryLoading}>
                    {syncMachineryMutation.isPending ? <Loader2 className="mr-2 size-4 animate-spin" /> : null}
                    Guardar maquinaria
                  </Button>
                </div>

                {(machineryOptionsLoading || machineryLoading) && (
                  <div className="flex items-center text-sm text-muted-foreground">
                    <Loader2 className="mr-2 size-4 animate-spin" /> Cargando datos...
                  </div>
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
