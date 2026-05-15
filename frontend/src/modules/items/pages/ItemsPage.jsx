import { useMemo, useState } from "react";
import { createPortal } from "react-dom";
import { ChevronLeft, ChevronRight, Loader2, Package, Search, MoreHorizontal, Pencil, Package2, Users, Wrench, FileText, TrendingUp, RefreshCw, BarChart3, Hammer, Trash2, X, Plus } from "lucide-react";
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
  const [search, setSearch] = useState("");
  const [searchQuery, setSearchQuery] = useState("");
  const [page, setPage] = useState(1);
  const [reportLoadingItemId, setReportLoadingItemId] = useState(null);
  const [reportFeedback, setReportFeedback] = useState(null);
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
  const [materialsOpen, setMaterialsOpen] = useState(false);
  const [materialsItem, setMaterialsItem] = useState(null);
  const [materialSearch, setMaterialSearch] = useState("");
  const [laborOpen, setLaborOpen] = useState(false);
  const [laborItem, setLaborItem] = useState(null);
  const [laborSearch, setLaborSearch] = useState("");
  const [machineryOpen, setMachineryOpen] = useState(false);
  const [machineryItem, setMachineryItem] = useState(null);
  const [machinerySearch, setMachinerySearch] = useState("");
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
  };

  const openMaterials = (item) => {
    setMaterialsItem(item);
    setMaterialsOpen(true);
    setMaterialSearch("");
  };

  const closeMaterials = () => {
    setMaterialsOpen(false);
    setMaterialsItem(null);
    setMaterialSearch("");
  };

  const openLabor = (item) => {
    setLaborItem(item);
    setLaborOpen(true);
    setLaborSearch("");
  };

  const closeLabor = () => {
    setLaborOpen(false);
    setLaborItem(null);
    setLaborSearch("");
  };

  const openMachinery = (item) => {
    setMachineryItem(item);
    setMachineryOpen(true);
    setMachinerySearch("");
  };

  const closeMachinery = () => {
    setMachineryOpen(false);
    setMachineryItem(null);
    setMachinerySearch("");
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
    queryKey: ["items", { page, perPage, search }],
    queryFn: () => itemsService.list({ page, perPage, search }),
    placeholderData: (previousData) => previousData,
  });

  const { data: contextData, isLoading: contextLoading } = useQuery({
    queryKey: ["items-context"],
    queryFn: itemsService.context,
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

  const addMaterialMutation = useMutation({
    mutationFn: itemsService.addMaterial,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["item-materials", materialsItem?.id_item] });
      queryClient.invalidateQueries({ queryKey: ["items"] });
    },
  });

  const removeMaterialMutation = useMutation({
    mutationFn: itemsService.removeMaterial,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["item-materials", materialsItem?.id_item] });
      queryClient.invalidateQueries({ queryKey: ["items"] });
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

  const addLaborMutation = useMutation({
    mutationFn: itemsService.addLabor,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["item-labor", laborItem?.id_item] });
      queryClient.invalidateQueries({ queryKey: ["items"] });
    },
  });

  const removeLaborMutation = useMutation({
    mutationFn: itemsService.removeLabor,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["item-labor", laborItem?.id_item] });
      queryClient.invalidateQueries({ queryKey: ["items"] });
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

  const addMachineryMutation = useMutation({
    mutationFn: itemsService.addMachinery,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["item-machinery", machineryItem?.id_item] });
      queryClient.invalidateQueries({ queryKey: ["items"] });
    },
  });

  const removeMachineryMutation = useMutation({
    mutationFn: itemsService.removeMachinery,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["item-machinery", machineryItem?.id_item] });
      queryClient.invalidateQueries({ queryKey: ["items"] });
    },
  });

  const updateFilesMutation = useMutation({
    mutationFn: itemsService.updateFiles,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["items"] });
      closeFiles();
    },
  });

  const recalculateMutation = useMutation({
    mutationFn: itemsService.recalculatePrice,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["items"] });
      closeRecalculate();
    },
  });

  const breakdownMutation = useMutation({
    mutationFn: itemsService.recalculateBreakdowns,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["items"] });
      closeBreakdown();
    },
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

  const handleOpenUnitPriceAnalysisPdf = async (item) => {
    if (!item?.id_item) return;

    setReportFeedback(null);
    setReportLoadingItemId(item.id_item);

    const reportWindow = window.open("", "_blank");

    if (reportWindow) {
      reportWindow.document.title = "Generando PDF";
      reportWindow.document.body.innerHTML = "<p style=\"font-family: Arial, sans-serif; padding: 24px;\">Generando analisis de precios unitarios...</p>";
    }

    try {
      const pdfResponse = await itemsService.downloadLegacyUnitPriceAnalysisPdf(item.id_item);
      const pdfBlob = pdfResponse instanceof Blob
        ? pdfResponse
        : new Blob([pdfResponse], { type: "application/pdf" });

      if (pdfBlob.size === 0) {
        throw new Error("El PDF se recibio vacio.");
      }

      const contentType = String(pdfBlob.type || "").toLowerCase();
      if (contentType && !contentType.includes("pdf")) {
        throw new Error("La respuesta no corresponde a un PDF valido.");
      }

      const blobUrl = URL.createObjectURL(pdfBlob);

      if (reportWindow) {
        reportWindow.location.replace(blobUrl);
      } else {
        const fallbackWindow = window.open(blobUrl, "_blank");
        if (!fallbackWindow) {
          window.location.href = blobUrl;
        }
      }

      window.setTimeout(() => URL.revokeObjectURL(blobUrl), 60000);
    } catch (mutationError) {
      if (reportWindow) {
        reportWindow.close();
      }

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

    const reportWindow = window.open("", "_blank");

    if (reportWindow) {
      reportWindow.document.title = "Generando PDF";
      reportWindow.document.body.innerHTML = "<p style=\"font-family: Arial, sans-serif; padding: 24px;\">Generando desglose de materiales...</p>";
    }

    try {
      const pdfResponse = await itemsService.downloadMaterialBreakdownPdf(item.id_item);
      const pdfBlob = pdfResponse instanceof Blob
        ? pdfResponse
        : new Blob([pdfResponse], { type: "application/pdf" });

      if (pdfBlob.size === 0) {
        throw new Error("El PDF se recibio vacio.");
      }

      const contentType = String(pdfBlob.type || "").toLowerCase();
      if (contentType && !contentType.includes("pdf")) {
        throw new Error("La respuesta no corresponde a un PDF valido.");
      }

      const blobUrl = URL.createObjectURL(pdfBlob);

      if (reportWindow) {
        reportWindow.location.replace(blobUrl);
      } else {
        const fallbackWindow = window.open(blobUrl, "_blank");
        if (!fallbackWindow) {
          window.location.href = blobUrl;
        }
      }

      window.setTimeout(() => URL.revokeObjectURL(blobUrl), 60000);
    } catch (mutationError) {
      if (reportWindow) {
        reportWindow.close();
      }

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

    const reportWindow = window.open("", "_blank");

    if (reportWindow) {
      reportWindow.document.title = "Generando PDF";
      reportWindow.document.body.innerHTML = "<p style=\"font-family: Arial, sans-serif; padding: 24px;\">Generando desglose de mano de obra...</p>";
    }

    try {
      const pdfResponse = await itemsService.downloadLaborBreakdownPdf(item.id_item);
      const pdfBlob = pdfResponse instanceof Blob
        ? pdfResponse
        : new Blob([pdfResponse], { type: "application/pdf" });

      if (pdfBlob.size === 0) {
        throw new Error("El PDF se recibio vacio.");
      }

      const contentType = String(pdfBlob.type || "").toLowerCase();
      if (contentType && !contentType.includes("pdf")) {
        throw new Error("La respuesta no corresponde a un PDF valido.");
      }

      const blobUrl = URL.createObjectURL(pdfBlob);

      if (reportWindow) {
        reportWindow.location.replace(blobUrl);
      } else {
        const fallbackWindow = window.open(blobUrl, "_blank");
        if (!fallbackWindow) {
          window.location.href = blobUrl;
        }
      }

      window.setTimeout(() => URL.revokeObjectURL(blobUrl), 60000);
    } catch (mutationError) {
      if (reportWindow) {
        reportWindow.close();
      }

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

    const reportWindow = window.open("", "_blank");

    if (reportWindow) {
      reportWindow.document.title = "Generando PDF";
      reportWindow.document.body.innerHTML = "<p style=\"font-family: Arial, sans-serif; padding: 24px;\">Generando desglose de herramientas...</p>";
    }

    try {
      const pdfResponse = await itemsService.downloadMachineryBreakdownPdf(item.id_item);
      const pdfBlob = pdfResponse instanceof Blob
        ? pdfResponse
        : new Blob([pdfResponse], { type: "application/pdf" });

      if (pdfBlob.size === 0) {
        throw new Error("El PDF se recibio vacio.");
      }

      const contentType = String(pdfBlob.type || "").toLowerCase();
      if (contentType && !contentType.includes("pdf")) {
        throw new Error("La respuesta no corresponde a un PDF valido.");
      }

      const blobUrl = URL.createObjectURL(pdfBlob);

      if (reportWindow) {
        reportWindow.location.replace(blobUrl);
      } else {
        const fallbackWindow = window.open(blobUrl, "_blank");
        if (!fallbackWindow) {
          window.location.href = blobUrl;
        }
      }

      window.setTimeout(() => URL.revokeObjectURL(blobUrl), 60000);
    } catch (mutationError) {
      if (reportWindow) {
        reportWindow.close();
      }

      setReportFeedback({
        type: "error",
        message: mutationError?.response?.data?.message || mutationError?.message || "No se pudo abrir el desglose de herramientas.",
      });
    } finally {
      setReportLoadingItemId(null);
    }
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

  const handleAddMaterial = async (event) => {
    event.preventDefault();
    if (!materialsItem?.id_item) return;

    const formData = new FormData(event.currentTarget);
    const idInsumo = Number(formData.get("id_insumo") || 0);
    const cantidad = Number(formData.get("cantidad") || 0);

    if (!idInsumo || !cantidad || cantidad <= 0) {
      alert("Selecciona un material y registra una cantidad mayor a 0.");
      return;
    }

    try {
      await addMaterialMutation.mutateAsync({
        itemId: materialsItem.id_item,
        payload: { id_insumo: idInsumo, cantidad },
      });
      event.currentTarget.reset();
      setMaterialSearch("");
    } catch (mutationError) {
      alert(mutationError?.response?.data?.message || "No se pudo agregar el material.");
    }
  };

  const handleRemoveMaterial = async (itemInputId) => {
    if (!materialsItem?.id_item) return;

    try {
      await removeMaterialMutation.mutateAsync({
        itemId: materialsItem.id_item,
        itemInputId,
      });
    } catch (mutationError) {
      alert(mutationError?.response?.data?.message || "No se pudo quitar el material.");
    }
  };

  const handleAddLabor = async (event) => {
    event.preventDefault();
    if (!laborItem?.id_item) return;

    const formData = new FormData(event.currentTarget);
    const idInsumo = Number(formData.get("id_insumo") || 0);
    const cantidad = Number(formData.get("cantidad") || 0);

    if (!idInsumo || !cantidad || cantidad <= 0) {
      alert("Selecciona mano de obra y registra una cantidad mayor a 0.");
      return;
    }

    try {
      await addLaborMutation.mutateAsync({
        itemId: laborItem.id_item,
        payload: { id_insumo: idInsumo, cantidad },
      });
      event.currentTarget.reset();
      setLaborSearch("");
    } catch (mutationError) {
      alert(mutationError?.response?.data?.message || "No se pudo agregar la mano de obra.");
    }
  };

  const handleRemoveLabor = async (itemInputId) => {
    if (!laborItem?.id_item) return;

    try {
      await removeLaborMutation.mutateAsync({
        itemId: laborItem.id_item,
        itemInputId,
      });
    } catch (mutationError) {
      alert(mutationError?.response?.data?.message || "No se pudo quitar la mano de obra.");
    }
  };

  const handleAddMachinery = async (event) => {
    event.preventDefault();
    if (!machineryItem?.id_item) return;

    const formData = new FormData(event.currentTarget);
    const idInsumo = Number(formData.get("id_insumo") || 0);
    const cantidad = Number(formData.get("cantidad") || 0);

    if (!idInsumo || !cantidad || cantidad <= 0) {
      alert("Selecciona maquinaria y registra una cantidad mayor a 0.");
      return;
    }

    try {
      await addMachineryMutation.mutateAsync({
        itemId: machineryItem.id_item,
        payload: { id_insumo: idInsumo, cantidad },
      });
      event.currentTarget.reset();
      setMachinerySearch("");
    } catch (mutationError) {
      alert(mutationError?.response?.data?.message || "No se pudo agregar maquinaria.");
    }
  };

  const handleRemoveMachinery = async (itemInputId) => {
    if (!machineryItem?.id_item) return;

    try {
      await removeMachineryMutation.mutateAsync({
        itemId: machineryItem.id_item,
        itemInputId,
      });
    } catch (mutationError) {
      alert(mutationError?.response?.data?.message || "No se pudo quitar maquinaria.");
    }
  };

  const handleFilesSubmit = async (event) => {
    event.preventDefault();
    if (!filesItem?.id_item) return;

    const formData = new FormData(event.currentTarget);
    const payload = new FormData();
    payload.append("item", filesItem.name || "");

    const specification = formData.get("specification");
    const sheet = formData.get("sheet");
    const specificationFile = formData.get("specification_file");
    const sheetFile = formData.get("sheet_file");

    if (typeof specification === "string" && specification.trim()) {
      payload.append("specification", specification.trim());
    }

    if (typeof sheet === "string" && sheet.trim()) {
      payload.append("sheet", sheet.trim());
    }

    if (specificationFile instanceof File && specificationFile.size > 0) {
      payload.append("specification_file", specificationFile);
    }

    if (sheetFile instanceof File && sheetFile.size > 0) {
      payload.append("sheet_file", sheetFile);
    }

    try {
      await updateFilesMutation.mutateAsync({
        id: filesItem.id_item,
        formData: payload,
      });
    } catch (mutationError) {
      alert(mutationError?.response?.data?.message || "No se pudieron guardar los archivos del item.");
    }
  };

  const handleRecalculateSubmit = async (event) => {
    event.preventDefault();
    if (!recalculateItem?.id_item || !recalculateDate) return;

    try {
      await recalculateMutation.mutateAsync({
        itemId: recalculateItem.id_item,
        fecha: recalculateDate,
      });
    } catch (mutationError) {
      alert(mutationError?.response?.data?.message || "No se pudo recalcular el precio del item.");
    }
  };

  const handleBreakdownSubmit = async (event) => {
    event.preventDefault();
    if (!breakdownItem?.id_item || !breakdownDate || !breakdownType) return;

    setReportFeedback(null);
    setReportLoadingItemId(breakdownItem.id_item);

    const reportWindow = window.open("", "_blank");

    if (reportWindow) {
      reportWindow.document.title = "Generando PDF";
      reportWindow.document.body.innerHTML = "<p style=\"font-family: Arial, sans-serif; padding: 24px;\">Generando desglose historico...</p>";
    }

    try {
      const pdfResponse = await breakdownMutation.mutateAsync({
        itemId: breakdownItem.id_item,
        payload: {
          fecha: breakdownDate,
          tipo_desglose: breakdownType,
        },
      });
      const pdfBlob = pdfResponse instanceof Blob
        ? pdfResponse
        : new Blob([pdfResponse], { type: "application/pdf" });

      if (pdfBlob.size === 0) {
        throw new Error("El PDF se recibio vacio.");
      }

      const contentType = String(pdfBlob.type || "").toLowerCase();
      if (contentType && !contentType.includes("pdf")) {
        throw new Error("La respuesta no corresponde a un PDF valido.");
      }

      const blobUrl = URL.createObjectURL(pdfBlob);

      if (reportWindow) {
        reportWindow.location.replace(blobUrl);
      } else {
        const fallbackWindow = window.open(blobUrl, "_blank");
        if (!fallbackWindow) {
          window.location.href = blobUrl;
        }
      }

      window.setTimeout(() => URL.revokeObjectURL(blobUrl), 60000);
    } catch (mutationError) {
      if (reportWindow) {
        reportWindow.close();
      }

      setReportFeedback({
        type: "error",
        message: mutationError?.response?.data?.message || mutationError?.message || "No se pudo abrir el desglose historico.",
      });
    } finally {
      setReportLoadingItemId(null);
    }
  };

  const materials = materialsData?.data?.items ?? [];
  const materialOptions = inputOptionsData?.data?.items ?? [];
  const materialContext = materialsContextData?.data ?? null;
  const materialsTotal = materials.reduce((acc, material) => acc + Number(material.parcial || 0), 0);
  const laborItems = laborData?.data?.items ?? [];
  const laborOptions = laborOptionsData?.data?.items ?? [];
  const laborContext = laborContextData?.data ?? null;
  const laborTotal = laborItems.reduce((acc, labor) => acc + Number(labor.parcial || 0), 0);
  const machineryItems = machineryData?.data?.items ?? [];
  const machineryOptions = machineryOptionsData?.data?.items ?? [];
  const machineryContext = machineryContextData?.data ?? null;
  const machineryTotal = machineryItems.reduce((acc, machinery) => acc + Number(machinery.parcial || 0), 0);
  const specificationUrl = filesItem?.specification ? `/storage/${filesItem.specification}` : null;
  const sheetUrl = filesItem?.sheet ? `/storage/${filesItem.sheet}` : null;

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
                                  <DropdownMenuItem className="flex items-center gap-2 rounded-xl px-3 py-2 cursor-pointer" onClick={() => handleOpenUnitPriceAnalysisPdf(item)} disabled={reportLoadingItemId === item.id_item}>
                                    <TrendingUp className="h-4 w-4 text-muted-foreground" />
                                    <span>{reportLoadingItemId === item.id_item ? "Generando PDF..." : "Análisis de precios unitarios"}</span>
                                  </DropdownMenuItem>
                                  <DropdownMenuItem className="flex items-center gap-2 rounded-xl px-3 py-2 cursor-pointer" onClick={() => openRecalculate(item)}>
                                    <RefreshCw className="h-4 w-4 text-muted-foreground" />
                                    <span>Recalcular Precio</span>
                                  </DropdownMenuItem>

                                  <DropdownMenuSeparator className="my-1 bg-border/50" />
                                  <DropdownMenuItem
                                    className="flex items-center gap-2 rounded-xl px-3 py-2 cursor-pointer"
                                    onClick={() => handleOpenMaterialBreakdownPdf(item)}
                                  >
                                    <BarChart3 className="h-4 w-4 text-muted-foreground" />
                                    <span>Desglose Materiales</span>
                                  </DropdownMenuItem>
                                  <DropdownMenuItem
                                    className="flex items-center gap-2 rounded-xl px-3 py-2 cursor-pointer"
                                    onClick={() => handleOpenLaborBreakdownPdf(item)}
                                    disabled={reportLoadingItemId === item.id_item}
                                  >
                                    <Users className="h-4 w-4 text-muted-foreground" />
                                    <span>{reportLoadingItemId === item.id_item ? "Generando PDF..." : "Desglose M.O."}</span>
                                  </DropdownMenuItem>
                                  <DropdownMenuItem
                                    className="flex items-center gap-2 rounded-xl px-3 py-2 cursor-pointer"
                                    onClick={() => handleOpenMachineryBreakdownPdf(item)}
                                    disabled={reportLoadingItemId === item.id_item}
                                  >
                                    <Hammer className="h-4 w-4 text-muted-foreground" />
                                    <span>{reportLoadingItemId === item.id_item ? "Generando PDF..." : "Desglose Herramientas"}</span>
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

      {filesOpen && createPortal(
        <div className="fixed inset-0 z-[80] flex justify-end bg-slate-950/20 backdrop-blur-[1px]">
          <div className="w-full max-w-3xl overflow-y-auto border-l border-border/70 bg-background/96 p-4 shadow-[0_0_60px_rgba(15,23,42,0.16)] backdrop-blur xl:p-6">
            <Card className="border border-border/70 bg-white/92 shadow-[0_24px_90px_rgba(15,23,42,0.08)]">
              <CardHeader className="border-b border-border/70 bg-muted/20">
                <div className="flex items-start justify-between gap-4">
                  <div>
                    <CardTitle className="text-2xl tracking-[-0.04em]">Adjuntar Archivos al Item</CardTitle>
                    <CardDescription>
                      {filesItem?.name ?? "Carga especificaciones y ficha tecnica del item."}
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
                    <Label htmlFor="specification" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">Descripcion</Label>
                    <Input id="specification" name="specification" defaultValue="" className="h-12 rounded-2xl border-border/80 bg-background/90" />
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

                  <Button type="submit" className="h-12 rounded-full bg-emerald-600 text-white hover:bg-emerald-700" disabled={updateFilesMutation.isPending}>
                    {updateFilesMutation.isPending ? (
                      <>
                        <Loader2 className="mr-2 size-4 animate-spin" />
                        Guardando...
                      </>
                    ) : "Agregar"}
                  </Button>
                </form>
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

                  <Button type="submit" className="h-12 rounded-full bg-emerald-600 text-white hover:bg-emerald-700" disabled={recalculateMutation.isPending}>
                    {recalculateMutation.isPending ? (
                      <>
                        <Loader2 className="mr-2 size-4 animate-spin" />
                        Recalculando...
                      </>
                    ) : "Recalcular"}
                  </Button>
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

                  <Button type="submit" className="h-12 rounded-full bg-emerald-600 text-white hover:bg-emerald-700" disabled={breakdownMutation.isPending}>
                    {breakdownMutation.isPending ? (
                      <>
                        <Loader2 className="mr-2 size-4 animate-spin" />
                        Recalculando...
                      </>
                    ) : "Recalcular Desgloses"}
                  </Button>
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
                    <Label htmlFor="id_insumo" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">Material</Label>
                    <select id="id_insumo" name="id_insumo" className="h-12 rounded-2xl border border-border/80 bg-background/90 px-4 text-sm text-foreground outline-none transition focus:border-foreground/20" required>
                      <option value="">--- Seleccionar ---</option>
                      {materialOptions.map((option) => (
                        <option key={option.id} value={option.id}>{option.text}</option>
                      ))}
                    </select>
                    <Input placeholder="Buscar material" className="h-11 rounded-2xl border-border/80 bg-background/90" value={materialSearch} onChange={(event) => setMaterialSearch(event.target.value)} />
                  </div>

                  <div className="flex flex-col gap-2">
                    <Label htmlFor="cantidad" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">Cantidad</Label>
                    <Input id="cantidad" name="cantidad" type="number" min="1" step="1" className="h-12 rounded-2xl border-border/80 bg-background/90" required />
                  </div>

                  <Button type="submit" className="h-12 rounded-full bg-emerald-600 px-6 text-white hover:bg-emerald-700" disabled={addMaterialMutation.isPending}>
                    {addMaterialMutation.isPending ? <Loader2 className="size-4 animate-spin" /> : "Agregar"}
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
                        {materials.map((material, index) => (
                          <tr key={material.id_item_insumo || index} className={index < materials.length - 1 ? "border-b border-border/60" : ""}>
                            <td className="px-4 py-3">{material.descripcion ?? "-"}</td>
                            <td className="px-4 py-3">{material.unidad ?? "-"}</td>
                            <td className="px-4 py-3">{Math.round(Number(material.cantidad || 0))}</td>
                            <td className="px-4 py-3">{Number(material.precio_unitario || 0).toFixed(2)}</td>
                            <td className="px-4 py-3">{Number(material.parcial || 0).toFixed(2)}</td>
                            <td className="px-4 py-3 text-center">
                              <Button type="button" className="h-9 rounded-md bg-red-600 px-3 text-white hover:bg-red-700" onClick={() => handleRemoveMaterial(material.id_item_insumo)} disabled={removeMaterialMutation.isPending}>
                                <Trash2 className="size-4" />
                              </Button>
                            </td>
                          </tr>
                        ))}
                        {materials.length === 0 && !materialsLoading && (
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
                    <Label htmlFor="labor_id_insumo" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">Mano de Obra del Item</Label>
                    <select id="labor_id_insumo" name="id_insumo" className="h-12 rounded-2xl border border-border/80 bg-background/90 px-4 text-sm text-foreground outline-none transition focus:border-foreground/20" required>
                      <option value="">--- Seleccionar ---</option>
                      {laborOptions.map((option) => (
                        <option key={option.id} value={option.id}>{option.text}</option>
                      ))}
                    </select>
                    <Input placeholder="Buscar mano de obra" className="h-11 rounded-2xl border-border/80 bg-background/90" value={laborSearch} onChange={(event) => setLaborSearch(event.target.value)} />
                  </div>

                  <div className="flex flex-col gap-2">
                    <Label htmlFor="labor_cantidad" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">Cantidad</Label>
                    <Input id="labor_cantidad" name="cantidad" type="number" min="1" step="1" className="h-12 rounded-2xl border-border/80 bg-background/90" required />
                  </div>

                  <Button type="submit" className="h-12 rounded-full bg-emerald-600 px-6 text-white hover:bg-emerald-700" disabled={addLaborMutation.isPending}>
                    {addLaborMutation.isPending ? <Loader2 className="size-4 animate-spin" /> : "Agregar"}
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
                        {laborItems.map((labor, index) => (
                          <tr key={labor.id_item_insumo || index} className={index < laborItems.length - 1 ? "border-b border-border/60" : ""}>
                            <td className="px-4 py-3">{labor.descripcion ?? "-"}</td>
                            <td className="px-4 py-3">{labor.unidad ?? "-"}</td>
                            <td className="px-4 py-3">{Math.round(Number(labor.cantidad || 0))}</td>
                            <td className="px-4 py-3">{Number(labor.precio_unitario || 0).toFixed(2)}</td>
                            <td className="px-4 py-3">{Number(labor.parcial || 0).toFixed(2)}</td>
                            <td className="px-4 py-3 text-center">
                              <Button type="button" className="h-9 rounded-md bg-red-600 px-3 text-white hover:bg-red-700" onClick={() => handleRemoveLabor(labor.id_item_insumo)} disabled={removeLaborMutation.isPending}>
                                <Trash2 className="size-4" />
                              </Button>
                            </td>
                          </tr>
                        ))}
                        {laborItems.length === 0 && !laborLoading && (
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
                    <Label htmlFor="machinery_id_insumo" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">Equipos, Maquinaria, Herramientas de Item</Label>
                    <select id="machinery_id_insumo" name="id_insumo" className="h-12 rounded-2xl border border-border/80 bg-background/90 px-4 text-sm text-foreground outline-none transition focus:border-foreground/20" required>
                      <option value="">--- Seleccionar ---</option>
                      {machineryOptions.map((option) => (
                        <option key={option.id} value={option.id}>{option.text}</option>
                      ))}
                    </select>
                    <Input placeholder="Buscar maquinaria" className="h-11 rounded-2xl border-border/80 bg-background/90" value={machinerySearch} onChange={(event) => setMachinerySearch(event.target.value)} />
                  </div>

                  <div className="flex flex-col gap-2">
                    <Label htmlFor="machinery_cantidad" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">Cantidad</Label>
                    <Input id="machinery_cantidad" name="cantidad" type="number" min="0.0001" step="0.0001" className="h-12 rounded-2xl border-border/80 bg-background/90" required />
                  </div>

                  <Button type="submit" className="h-12 rounded-full bg-emerald-600 px-6 text-white hover:bg-emerald-700" disabled={addMachineryMutation.isPending}>
                    {addMachineryMutation.isPending ? <Loader2 className="size-4 animate-spin" /> : "Agregar"}
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
                        {machineryItems.map((machinery, index) => (
                          <tr key={machinery.id_item_insumo || index} className={index < machineryItems.length - 1 ? "border-b border-border/60" : ""}>
                            <td className="px-4 py-3">{machinery.descripcion ?? "-"}</td>
                            <td className="px-4 py-3">{machinery.unidad ?? "-"}</td>
                            <td className="px-4 py-3">{Number(machinery.cantidad || 0).toFixed(2)}</td>
                            <td className="px-4 py-3">{Number(machinery.precio_unitario || 0).toFixed(2)}</td>
                            <td className="px-4 py-3">{Number(machinery.parcial || 0).toFixed(2)}</td>
                            <td className="px-4 py-3 text-center">
                              <Button type="button" className="h-9 rounded-md bg-red-600 px-3 text-white hover:bg-red-700" onClick={() => handleRemoveMachinery(machinery.id_item_insumo)} disabled={removeMachineryMutation.isPending}>
                                <Trash2 className="size-4" />
                              </Button>
                            </td>
                          </tr>
                        ))}
                        {machineryItems.length === 0 && !machineryLoading && (
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
