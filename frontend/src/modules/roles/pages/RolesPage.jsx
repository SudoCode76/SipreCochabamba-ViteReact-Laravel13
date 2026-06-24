import { useDeferredValue, useMemo, useState } from "react";
import { createPortal } from "react-dom";
import {
  ChevronLeft,
  ChevronRight,
  Filter,
  Loader2,
  MoreHorizontal,
  Pencil,
  Plus,
  RefreshCcw,
  Shield,
  ShieldCheck,
  Unplug,
  X,
} from "lucide-react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";

import { Alert, AlertDescription } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { ClearableSearchInput } from "@/components/ui/clearable-search-input";
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
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Separator } from "@/components/ui/separator";
import { cn } from "@/lib/utils";
import { rolesService } from "@/modules/roles/services/roles.service";

const initialRoleForm = {
  role: "",
  status: "ACTIVO",
};

function buildRoleForm(role) {
  return {
    role: role?.name ?? role?.nombre_rol ?? "",
    status: role?.status_label ?? (role?.status === "DC" ? "INACTIVO" : "ACTIVO"),
  };
}

export default function RolesPage() {
  const queryClient = useQueryClient();

  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(10);
  const [searchTerm, setSearchTerm] = useState("");
  const [statusFilter, setStatusFilter] = useState("");
  const [isRoleFormOpen, setIsRoleFormOpen] = useState(false);
  const [editingRoleId, setEditingRoleId] = useState(null);
  const [isDetailLoading, setIsDetailLoading] = useState(false);
  const [roleForm, setRoleForm] = useState(initialRoleForm);
  const [roleFormErrors, setRoleFormErrors] = useState({});
  const [feedback, setFeedback] = useState(null);

  const [selectedRoleId, setSelectedRoleId] = useState(null);
  const [isPermissionsOpen, setIsPermissionsOpen] = useState(false);
  const [permissionsPage, setPermissionsPage] = useState(1);
  const [permissionsPerPage, setPermissionsPerPage] = useState(10);
  const [permissionsSearch, setPermissionsSearch] = useState("");
  const [selectedFunctionLabel, setSelectedFunctionLabel] = useState("");
  const [selectedFunctionId, setSelectedFunctionId] = useState("");

  const deferredSearchTerm = useDeferredValue(searchTerm.trim());
  const deferredPermissionsSearch = useDeferredValue(permissionsSearch.trim());

  const rolesContextQuery = useQuery({
    queryKey: ["roles-context"],
    queryFn: rolesService.context,
  });

  const rolesQuery = useQuery({
    queryKey: ["roles", { page, perPage, deferredSearchTerm, statusFilter }],
    queryFn: () => rolesService.list({
      page,
      perPage,
      search: deferredSearchTerm,
      status: statusFilter,
    }),
    placeholderData: (previousData) => previousData,
  });

  const permissionsContextQuery = useQuery({
    queryKey: ["role-permissions-context", selectedRoleId],
    queryFn: () => rolesService.permissionsContext(selectedRoleId),
    enabled: selectedRoleId !== null && isPermissionsOpen,
  });

  const permissionsQuery = useQuery({
    queryKey: ["role-permissions", { selectedRoleId, permissionsPage, permissionsPerPage, deferredPermissionsSearch }],
    queryFn: () => rolesService.permissions({
      roleId: selectedRoleId,
      page: permissionsPage,
      perPage: permissionsPerPage,
      search: deferredPermissionsSearch,
    }),
    enabled: selectedRoleId !== null && isPermissionsOpen,
    placeholderData: (previousData) => previousData,
  });

  const roles = useMemo(() => rolesQuery.data?.data?.items ?? [], [rolesQuery.data]);
  const meta = rolesQuery.data?.data?.meta ?? { current_page: 1, per_page: perPage, total: 0, from: 0, to: 0, last_page: 1 };
  const statuses = rolesContextQuery.data?.data?.statuses ?? [];

  const permissionsRole = permissionsQuery.data?.data?.role || permissionsContextQuery.data?.data?.role || null;
  const permissions = permissionsQuery.data?.data?.permissions ?? [];
  const availableFunctions = permissionsQuery.data?.data?.available_functions ?? permissionsContextQuery.data?.data?.available_functions ?? [];
  const permissionsMeta = permissionsQuery.data?.data?.meta ?? { current_page: 1, per_page: permissionsPerPage, total: 0, from: 0, to: 0, last_page: 1 };

  const totalPages = Math.max(1, meta.last_page || Math.ceil((meta.total || 0) / (meta.per_page || perPage)));
  const visiblePages = useMemo(() => {
    const start = Math.max(1, meta.current_page - 2);
    const end = Math.min(totalPages, start + 4);
    const first = Math.max(1, end - 4);
    return Array.from({ length: end - first + 1 }, (_, index) => first + index);
  }, [meta.current_page, totalPages]);

  const permissionsTotalPages = Math.max(1, permissionsMeta.last_page || Math.ceil((permissionsMeta.total || 0) / (permissionsMeta.per_page || permissionsPerPage)));
  const visiblePermissionPages = useMemo(() => {
    const start = Math.max(1, permissionsMeta.current_page - 2);
    const end = Math.min(permissionsTotalPages, start + 4);
    const first = Math.max(1, end - 4);
    return Array.from({ length: end - first + 1 }, (_, index) => first + index);
  }, [permissionsMeta.current_page, permissionsTotalPages]);

  const resetRoleForm = () => {
    setRoleForm(initialRoleForm);
    setRoleFormErrors({});
    setEditingRoleId(null);
  };

  const closeRoleForm = () => {
    setIsRoleFormOpen(false);
    resetRoleForm();
  };

  const openCreateRole = () => {
    resetRoleForm();
    setIsRoleFormOpen(true);
  };

  const openEditRole = async (roleId) => {
    setRoleFormErrors({});
    setEditingRoleId(roleId);
    setIsRoleFormOpen(true);
    setIsDetailLoading(true);

    try {
      const detail = await queryClient.fetchQuery({
        queryKey: ["role-detail", roleId],
        queryFn: () => rolesService.getById(roleId),
      });

      const role = detail?.data?.role;
      if (role) {
        setRoleForm(buildRoleForm(role));
      }
    } catch (error) {
      setFeedback({
        type: "error",
        message: error.response?.data?.message || "No se pudo cargar el detalle del rol.",
      });
      closeRoleForm();
    } finally {
      setIsDetailLoading(false);
    }
  };

  const openPermissions = (roleId) => {
    setSelectedRoleId(roleId);
    setPermissionsPage(1);
    setPermissionsPerPage(10);
    setPermissionsSearch("");
    setSelectedFunctionLabel("");
    setSelectedFunctionId("");
    setIsPermissionsOpen(true);
  };

  const closePermissions = () => {
    setIsPermissionsOpen(false);
    setSelectedRoleId(null);
    setPermissionsSearch("");
    setSelectedFunctionLabel("");
    setSelectedFunctionId("");
  };

  const createRoleMutation = useMutation({
    mutationFn: rolesService.create,
    onSuccess: () => {
      setFeedback({ type: "success", message: "Rol creado correctamente." });
      closeRoleForm();
      queryClient.invalidateQueries({ queryKey: ["roles"] });
      queryClient.invalidateQueries({ queryKey: ["roles-context"] });
    },
    onError: (error) => {
      setRoleFormErrors(error.response?.data?.errors ?? {});
      setFeedback({
        type: "error",
        message: error.response?.status === 403
          ? "Acceso denegado para crear roles."
          : error.response?.data?.message || "No se pudo crear el rol.",
      });
    },
  });

  const updateRoleMutation = useMutation({
    mutationFn: ({ roleId, payload }) => rolesService.update(roleId, payload),
    onSuccess: () => {
      setFeedback({ type: "success", message: "Rol actualizado correctamente." });
      closeRoleForm();
      queryClient.invalidateQueries({ queryKey: ["roles"] });
      queryClient.invalidateQueries({ queryKey: ["role-detail"] });
    },
    onError: (error) => {
      setRoleFormErrors(error.response?.data?.errors ?? {});
      setFeedback({
        type: "error",
        message: error.response?.status === 403
          ? "Acceso denegado para editar roles."
          : error.response?.data?.message || "No se pudo actualizar el rol.",
      });
    },
  });

  const statusMutation = useMutation({
    mutationFn: ({ roleId, payload }) => rolesService.updateStatus(roleId, payload),
    onSuccess: () => {
      setFeedback({ type: "success", message: "Estado actualizado correctamente." });
      queryClient.invalidateQueries({ queryKey: ["roles"] });
    },
    onError: (error) => {
      setFeedback({
        type: "error",
        message: error.response?.status === 403
          ? "Acceso denegado para cambiar estado del rol."
          : error.response?.data?.message || "No se pudo actualizar el estado.",
      });
    },
  });

  const attachMutation = useMutation({
    mutationFn: ({ roleId, functionId }) => rolesService.attachFunction(roleId, functionId),
    onSuccess: () => {
      setFeedback({ type: "success", message: "Función asignada correctamente al rol." });
      setSelectedFunctionId("");
      setSelectedFunctionLabel("");
      queryClient.invalidateQueries({ queryKey: ["role-permissions-context", selectedRoleId] });
      queryClient.invalidateQueries({ queryKey: ["role-permissions"] });
    },
    onError: (error) => {
      setFeedback({
        type: "error",
        message: error.response?.data?.message || "No se pudo asignar la función.",
      });
    },
  });

  const detachMutation = useMutation({
    mutationFn: ({ roleId, functionId }) => rolesService.detachFunction(roleId, functionId),
    onSuccess: () => {
      setFeedback({ type: "success", message: "Función quitada correctamente del rol." });
      queryClient.invalidateQueries({ queryKey: ["role-permissions-context", selectedRoleId] });
      queryClient.invalidateQueries({ queryKey: ["role-permissions"] });
    },
    onError: (error) => {
      setFeedback({
        type: "error",
        message: error.response?.data?.message || "No se pudo quitar la función.",
      });
    },
  });

  const isSubmitting = createRoleMutation.isPending || updateRoleMutation.isPending;
  const isEditing = editingRoleId !== null;

  const handleRoleFormChange = (event) => {
    const { id, value } = event.target;
    setRoleForm((current) => ({ ...current, [id]: value }));
  };

  const handleSubmitRoleForm = (event) => {
    event.preventDefault();
    setFeedback(null);

    const nextErrors = {};
    if (!roleForm.role.trim()) nextErrors.role = ["El rol es obligatorio."];
    if (!roleForm.status) nextErrors.status = ["El estado es obligatorio."];

    setRoleFormErrors(nextErrors);

    if (Object.keys(nextErrors).length > 0) {
      return;
    }

    const payload = {
      role: roleForm.role.trim(),
      status: roleForm.status,
    };

    if (isEditing) {
      updateRoleMutation.mutate({ roleId: editingRoleId, payload });
      return;
    }

    createRoleMutation.mutate(payload);
  };

  const handleToggleStatus = (item) => {
    const nextStatus = item.status === "AC" ? "INACTIVO" : "ACTIVO";
    statusMutation.mutate({ roleId: item.id, payload: { status: nextStatus } });
  };

  const handleFunctionLabelChange = (event) => {
    const value = event.target.value;
    setSelectedFunctionLabel(value);

    const matched = availableFunctions.find((item) => item.label === value);
    setSelectedFunctionId(matched ? String(matched.id) : "");
  };

  const handleAttachFunction = () => {
    if (!selectedRoleId || !selectedFunctionId) {
      return;
    }

    attachMutation.mutate({ roleId: selectedRoleId, functionId: Number(selectedFunctionId) });
  };

  const handleDetachFunction = (functionId) => {
    if (!selectedRoleId) {
      return;
    }

    detachMutation.mutate({ roleId: selectedRoleId, functionId });
  };

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
                <CardTitle className="text-2xl tracking-[-0.04em]">Administración de roles</CardTitle>
              </div>
            </div>

            <div className="flex flex-wrap gap-2">
              <Button
                variant="outline"
                className="rounded-full border-border/70 bg-background/80"
                onClick={() => rolesQuery.refetch()}
                disabled={rolesQuery.isFetching}
              >
                <RefreshCcw data-icon="inline-start" className={cn(rolesQuery.isFetching && "animate-spin")} />
                Refrescar
              </Button>
              <Button className="rounded-full bg-foreground text-background hover:bg-foreground/90" onClick={openCreateRole}>
                <Plus data-icon="inline-start" />
                Registrar Rol
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

          <div className="grid gap-4 xl:grid-cols-[minmax(0,1fr)_minmax(220px,0.45fr)_minmax(140px,0.25fr)] xl:items-end">
            <div className="flex flex-col gap-2">
              <Label htmlFor="searchTerm" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                Buscador
              </Label>
              <ClearableSearchInput
                id="searchTerm"
                placeholder="Buscar rol"
                className="h-12 rounded-2xl border-border/80 bg-background/90"
                value={searchTerm}
                onChange={(event) => {
                  setSearchTerm(event.target.value);
                  setPage(1);
                }}
                onClear={() => {
                  setSearchTerm("");
                  setPage(1);
                }}
                isLoading={rolesQuery.isFetching && !rolesQuery.isLoading}
              />
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

            <div className="xl:col-span-3 flex flex-wrap items-center justify-between gap-3">
              <div className="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                <Filter className="size-3.5" /> Filtros administrativos
              </div>
              <span className="text-sm text-muted-foreground">
                La búsqueda se aplica automáticamente por nombre del rol.
              </span>
            </div>
          </div>

          {rolesQuery.isLoading && (
            <div className="flex items-center justify-center rounded-2xl border border-border/70 bg-background/70 p-8 text-muted-foreground">
              <Loader2 className="mr-2 size-4 animate-spin" /> Cargando roles...
            </div>
          )}

          {rolesQuery.isError && (
            <Alert variant="destructive" className="rounded-2xl">
              <AlertDescription>
                {rolesQuery.error?.response?.status === 403
                  ? "No tienes permisos para acceder a la administración de roles."
                  : rolesQuery.error?.response?.data?.message || "No se pudieron cargar los roles."}
              </AlertDescription>
            </Alert>
          )}

          {!rolesQuery.isLoading && !rolesQuery.isError && (
            <>
              <div className="hidden overflow-hidden rounded-[28px] border border-border/70 bg-background/90 lg:block">
                <div className="overflow-x-auto">
                  <table className="min-w-full border-collapse text-sm">
                    <thead>
                      <tr className="border-b border-border/70 bg-muted/30 text-left">
                        <th className="px-5 py-4 font-semibold text-foreground">N°</th>
                        <th className="px-5 py-4 font-semibold text-foreground">Rol</th>
                        <th className="px-5 py-4 font-semibold text-foreground">Estado</th>
                        <th className="px-5 py-4 font-semibold text-foreground text-right">Opciones</th>
                      </tr>
                    </thead>
                    <tbody>
                      {roles.map((item, index) => (
                        <tr key={item.id} className={index < roles.length - 1 ? "border-b border-border/60" : ""}>
                          <td className="px-5 py-4 align-top text-foreground">{(meta.from || 1) + index}</td>
                          <td className="px-5 py-4 align-top text-foreground font-medium">{item.name}</td>
                          <td className="px-5 py-4 align-top">
                            <Badge className={`rounded-full px-3 py-1 text-[11px] uppercase tracking-[0.18em] ${item.status === "AC" ? "bg-emerald-600 text-white" : "bg-slate-900 text-white"}`}>
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
                                  <DropdownMenuItem className="rounded-xl px-3 py-2 cursor-pointer" onClick={() => openEditRole(item.id)}>
                                    <Pencil className="mr-2 size-4" />
                                    Editar rol
                                  </DropdownMenuItem>
                                  {item.available_actions?.assign_functions && (
                                    <DropdownMenuItem className="rounded-xl px-3 py-2 cursor-pointer" onClick={() => openPermissions(item.id)}>
                                      <ShieldCheck className="mr-2 size-4" />
                                      Asignar funciones
                                    </DropdownMenuItem>
                                  )}
                                  {item.available_actions?.update_status && (
                                    <>
                                      <DropdownMenuSeparator className="bg-border/70" />
                                      <DropdownMenuItem className="rounded-xl px-3 py-2 cursor-pointer" onClick={() => handleToggleStatus(item)}>
                                        <Shield className="mr-2 size-4" />
                                        {item.status === "AC" ? "Desactivar" : "Activar"}
                                      </DropdownMenuItem>
                                    </>
                                  )}
                                </DropdownMenuContent>
                              </DropdownMenu>
                            </div>
                          </td>
                        </tr>
                      ))}
                      {roles.length === 0 && (
                        <tr>
                          <td colSpan={4} className="px-5 py-8 text-center text-muted-foreground">
                            No se encontraron roles para los filtros seleccionados.
                          </td>
                        </tr>
                      )}
                    </tbody>
                  </table>
                </div>
              </div>

              <div className="grid gap-4 lg:hidden">
                {roles.length === 0 ? (
                  <Card className="border border-border/70 bg-background/90">
                    <CardContent className="p-6 text-center text-muted-foreground">
                      No se encontraron roles para los filtros seleccionados.
                    </CardContent>
                  </Card>
                ) : roles.map((item) => (
                  <Card key={item.id} className="border border-border/70 bg-background/90">
                    <CardContent className="flex flex-col gap-4 p-5">
                      <div className="flex items-start justify-between gap-3">
                        <div>
                          <p className="font-medium text-foreground">{item.name}</p>
                        </div>
                        <Badge className={`rounded-full px-3 py-1 text-[11px] uppercase tracking-[0.18em] ${item.status === "AC" ? "bg-emerald-600 text-white" : "bg-slate-900 text-white"}`}>
                          {item.status_label}
                        </Badge>
                      </div>
                      <div className="flex gap-2">
                        <Button variant="outline" className="rounded-full border-border/70" onClick={() => openEditRole(item.id)}>
                          <Pencil data-icon="inline-start" />
                          Editar
                        </Button>
                        <Button variant="outline" className="rounded-full border-border/70" onClick={() => openPermissions(item.id)}>
                          <ShieldCheck data-icon="inline-start" />
                          Funciones
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
                    disabled={meta.current_page <= 1 || rolesQuery.isFetching}
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
                        disabled={rolesQuery.isFetching}
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
                    disabled={meta.current_page >= totalPages || rolesQuery.isFetching}
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

      {isRoleFormOpen && createPortal(
        <div className="fixed inset-0 z-[80] flex justify-end bg-slate-950/20 backdrop-blur-[1px]">
          <div className="w-full max-w-2xl overflow-y-auto border-l border-border/70 bg-background/96 p-4 shadow-[0_0_60px_rgba(15,23,42,0.16)] backdrop-blur xl:p-6">
            <Card className="border border-border/70 bg-white/92 shadow-[0_24px_90px_rgba(15,23,42,0.08)]">
              <CardHeader className="border-b border-border/70 bg-muted/20">
                <div className="flex items-start justify-between gap-4">
                  <div>
                    <CardTitle className="text-2xl tracking-[-0.04em]">
                      {isEditing ? "Editar rol" : "Registrar rol"}
                    </CardTitle>
                    <CardDescription>
                      {isEditing
                        ? "Actualiza el nombre y estado del rol seleccionado."
                        : "Registra un nuevo rol administrativo dentro del sistema."}
                    </CardDescription>
                  </div>

                  <Button variant="ghost" size="icon-sm" className="rounded-full" onClick={closeRoleForm}>
                    <X />
                  </Button>
                </div>
              </CardHeader>

              <CardContent className="p-5 sm:p-6">
                {isEditing && isDetailLoading ? (
                  <div className="flex items-center justify-center rounded-2xl border border-border/70 bg-background/70 p-8 text-muted-foreground">
                    <Loader2 className="mr-2 size-4 animate-spin" /> Cargando detalle del rol...
                  </div>
                ) : (
                  <form className="flex flex-col gap-5" onSubmit={handleSubmitRoleForm}>
                    <div className="grid gap-5 sm:grid-cols-2">
                      <div className="flex flex-col gap-2 sm:col-span-2">
                        <Label htmlFor="role" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                          Rol
                        </Label>
                        <Input id="role" value={roleForm.role} onChange={handleRoleFormChange} className="h-12 rounded-2xl border-border/80 bg-background/90" />
                        {roleFormErrors.role && <p className="text-sm text-destructive">{roleFormErrors.role[0]}</p>}
                        {roleFormErrors.nombre_rol && <p className="text-sm text-destructive">{roleFormErrors.nombre_rol[0]}</p>}
                      </div>

                      <div className="flex flex-col gap-2">
                        <Label htmlFor="status" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                          Estado
                        </Label>
                        <select id="status" value={roleForm.status} onChange={handleRoleFormChange} className="h-12 rounded-2xl border border-border/80 bg-background/90 px-4 text-sm text-foreground outline-none">
                          {statuses.map((status) => (
                            <option key={status.code} value={status.label}>{status.label}</option>
                          ))}
                        </select>
                        {roleFormErrors.status && <p className="text-sm text-destructive">{roleFormErrors.status[0]}</p>}
                        {roleFormErrors.estado && <p className="text-sm text-destructive">{roleFormErrors.estado[0]}</p>}
                      </div>
                    </div>

                    <Separator className="bg-border/70" />

                    <div className="flex flex-wrap justify-end gap-2">
                      <Button type="button" variant="outline" className="rounded-full border-border/70 bg-background/80" onClick={closeRoleForm}>
                        Cancelar
                      </Button>
                      <Button type="submit" className="rounded-full bg-foreground text-background hover:bg-foreground/90" disabled={isSubmitting}>
                        {isSubmitting && <Loader2 data-icon="inline-start" className="animate-spin" />}
                        {isEditing ? "Guardar cambios" : "Registrar rol"}
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

      {isPermissionsOpen && createPortal(
        <div className="fixed inset-0 z-[85] flex justify-end bg-slate-950/30 backdrop-blur-[2px]">
          <div className="w-full max-w-4xl overflow-y-auto border-l border-border/70 bg-background/96 p-4 shadow-[0_0_60px_rgba(15,23,42,0.16)] backdrop-blur xl:p-6">
            <Card className="border border-border/70 bg-white/92 shadow-[0_24px_90px_rgba(15,23,42,0.08)]">
              <CardHeader className="border-b border-border/70 bg-muted/20">
                <div className="flex items-start justify-between gap-4">
                  <div>
                    <CardTitle className="text-2xl tracking-[-0.04em]">Asignar funciones</CardTitle>
                    <CardDescription>
                      {permissionsRole ? `Rol seleccionado: ${permissionsRole.name}` : "Cargando contexto del rol..."}
                    </CardDescription>
                  </div>

                  <Button variant="ghost" size="icon-sm" className="rounded-full" onClick={closePermissions}>
                    <X />
                  </Button>
                </div>
              </CardHeader>

              <CardContent className="flex flex-col gap-6 p-5 sm:p-6">
                <div className="grid gap-4 xl:grid-cols-[minmax(0,1fr)_auto] xl:items-end">
                  <div className="flex flex-col gap-2">
                    <Label htmlFor="selectedFunctionLabel" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                      Función disponible
                    </Label>
                    <Input
                      id="selectedFunctionLabel"
                      value={selectedFunctionLabel}
                      onChange={handleFunctionLabelChange}
                      className="h-12 rounded-2xl border-border/80 bg-background/90"
                      list="available-role-functions"
                      placeholder="Selecciona o escribe una función disponible"
                    />
                    <datalist id="available-role-functions">
                      {availableFunctions.map((item) => (
                        <option key={item.id} value={item.label} />
                      ))}
                    </datalist>
                  </div>

                  <Button className="rounded-full bg-foreground text-background hover:bg-foreground/90" onClick={handleAttachFunction} disabled={!selectedFunctionId || attachMutation.isPending}>
                    {attachMutation.isPending ? <Loader2 data-icon="inline-start" className="animate-spin" /> : <Plus data-icon="inline-start" />}
                    Asignar
                  </Button>
                </div>

                <div className="grid gap-4 xl:grid-cols-[minmax(0,1fr)_140px] xl:items-end">
                  <div className="flex flex-col gap-2">
                    <Label htmlFor="permissionsSearch" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                      Buscar funciones asignadas
                    </Label>
                    <ClearableSearchInput
                      id="permissionsSearch"
                      value={permissionsSearch}
                      onChange={(event) => {
                        setPermissionsSearch(event.target.value);
                        setPermissionsPage(1);
                      }}
                      onClear={() => {
                        setPermissionsSearch("");
                        setPermissionsPage(1);
                      }}
                      className="h-12 rounded-2xl border-border/80 bg-background/90"
                      placeholder="Buscar por función, descripción o clase"
                      isLoading={permissionsQuery.isFetching && !permissionsQuery.isLoading}
                    />
                  </div>

                  <div className="flex flex-col gap-2">
                    <Label className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                      Mostrar
                    </Label>
                    <select
                      className="h-12 rounded-2xl border border-border/80 bg-background/90 px-4 text-sm text-foreground outline-none"
                      value={permissionsPerPage}
                      onChange={(event) => {
                        setPermissionsPerPage(Number(event.target.value));
                        setPermissionsPage(1);
                      }}
                    >
                      <option value={10}>10</option>
                      <option value={25}>25</option>
                      <option value={50}>50</option>
                    </select>
                  </div>
                </div>

                {permissionsQuery.isLoading || permissionsContextQuery.isLoading ? (
                  <div className="flex items-center justify-center rounded-2xl border border-border/70 bg-background/70 p-8 text-muted-foreground">
                    <Loader2 className="mr-2 size-4 animate-spin" /> Cargando permisos del rol...
                  </div>
                ) : permissionsQuery.isError ? (
                  <Alert variant="destructive" className="rounded-2xl">
                    <AlertDescription>
                      {permissionsQuery.error?.response?.data?.message || "No se pudieron cargar los permisos del rol."}
                    </AlertDescription>
                  </Alert>
                ) : (
                  <>
                    <div className="overflow-hidden rounded-[28px] border border-border/70 bg-background/90">
                      <div className="overflow-x-auto">
                        <table className="min-w-full border-collapse text-sm">
                          <thead>
                            <tr className="border-b border-border/70 bg-muted/30 text-left">
                              <th className="px-5 py-4 font-semibold text-foreground">N°</th>
                              <th className="px-5 py-4 font-semibold text-foreground">Descripción</th>
                              <th className="px-5 py-4 font-semibold text-foreground">Controlador</th>
                              <th className="px-5 py-4 font-semibold text-foreground">Nombre de la función</th>
                              <th className="px-5 py-4 font-semibold text-foreground">Estado</th>
                              <th className="px-5 py-4 font-semibold text-foreground text-right">Quitar</th>
                            </tr>
                          </thead>
                          <tbody>
                            {permissions.map((permission, index) => (
                              <tr key={permission.id} className={index < permissions.length - 1 ? "border-b border-border/60" : ""}>
                                <td className="px-5 py-4 align-top text-foreground">{(permissionsMeta.from || 1) + index}</td>
                                <td className="px-5 py-4 align-top text-foreground">{permission.description}</td>
                                <td className="px-5 py-4 align-top text-muted-foreground">{permission.function?.class}</td>
                                <td className="px-5 py-4 align-top text-muted-foreground">{permission.function?.name}</td>
                                <td className="px-5 py-4 align-top">
                                  <Badge className={`rounded-full px-3 py-1 text-[11px] uppercase tracking-[0.18em] ${permission.status === "AC" ? "bg-emerald-600 text-white" : "bg-slate-900 text-white"}`}>
                                    {permission.status}
                                  </Badge>
                                </td>
                                <td className="px-5 py-4 align-top text-right">
                                  <Button variant="outline" className="rounded-full border-border/70" onClick={() => handleDetachFunction(permission.function?.id)} disabled={detachMutation.isPending}>
                                    {detachMutation.isPending ? <Loader2 data-icon="inline-start" className="animate-spin" /> : <Unplug data-icon="inline-start" />}
                                    Quitar
                                  </Button>
                                </td>
                              </tr>
                            ))}
                            {permissions.length === 0 && (
                              <tr>
                                <td colSpan={6} className="px-5 py-8 text-center text-muted-foreground">
                                  No se encontraron funciones asignadas para los filtros seleccionados.
                                </td>
                              </tr>
                            )}
                          </tbody>
                        </table>
                      </div>
                    </div>

                    <div className="flex flex-col gap-4 px-1 text-sm text-muted-foreground lg:flex-row lg:items-center lg:justify-between">
                      <p>
                        Mostrando registros del {permissionsMeta.from || 0} al {permissionsMeta.to || 0} de un total de {permissionsMeta.total || 0} registros
                      </p>

                      <div className="flex items-center gap-2 self-end lg:self-auto">
                        <Button
                          variant="ghost"
                          size="sm"
                          className="rounded-full text-muted-foreground"
                          onClick={() => setPermissionsPage((current) => Math.max(1, current - 1))}
                          disabled={permissionsMeta.current_page <= 1 || permissionsQuery.isFetching}
                        >
                          <ChevronLeft data-icon="inline-start" />
                          Anterior
                        </Button>

                        <div className="flex items-center gap-1">
                          {visiblePermissionPages.map((pageNumber) => (
                            <Button
                              key={pageNumber}
                              variant={pageNumber === permissionsMeta.current_page ? "default" : "ghost"}
                              size="icon-sm"
                              className={pageNumber === permissionsMeta.current_page ? "rounded-full bg-foreground text-background" : "rounded-full text-muted-foreground"}
                              onClick={() => setPermissionsPage(pageNumber)}
                              disabled={permissionsQuery.isFetching}
                            >
                              {pageNumber}
                            </Button>
                          ))}
                        </div>

                        <Button
                          variant="ghost"
                          size="sm"
                          className="rounded-full text-muted-foreground"
                          onClick={() => setPermissionsPage((current) => Math.min(permissionsTotalPages, current + 1))}
                          disabled={permissionsMeta.current_page >= permissionsTotalPages || permissionsQuery.isFetching}
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
          </div>
        </div>,
        document.body,
      )}
    </div>
  );
}
