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
  SearchCheck,
  Shield,
  UserRound,
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
import { useToast } from "@/components/ui/toast";
import { cn } from "@/lib/utils";
import { usersService } from "@/modules/users/services/users.service";

const statusClassMap = {
  AC: "bg-emerald-600 text-white",
  DC: "bg-slate-900 text-white",
};

const statusLabelMap = {
  AC: "ACTIVO",
  DC: "INACTIVO",
};

const initialForm = {
  funcionario: "",
  ci: "",
  username: "",
  password: "",
  password_confirmation: "",
  estado: "AC",
  role_id: "",
  unit_description: "",
};

function normalizeFormErrors(errors = {}) {
  const translated = { ...errors };

  if (translated.username?.[0]) {
    translated.username = ["Ya existe un usuario con ese nombre de usuario."];
  }

  if (translated.ci?.[0]) {
    translated.ci = ["Ya existe un usuario registrado con ese C.I."];
  }

  return {
    ...translated,
    username: translated.username ?? translated.usuario,
    ci: translated.ci ?? translated.documento,
  };
}

function validationMessage(error, fallback) {
  const errors = normalizeFormErrors(error.response?.data?.errors ?? {});

  if (errors.ci?.[0]) {
    return "Ya existe un usuario registrado con ese C.I.";
  }

  if (errors.username?.[0]) {
    return "Ya existe un usuario con ese nombre de usuario.";
  }

  return error.response?.data?.message || fallback;
}

function buildFormFromUser(user) {
  return {
    funcionario: user?.full_name ?? "",
    ci: user?.ci ?? "",
    username: user?.username ?? "",
    password: "",
    password_confirmation: "",
    estado: user?.status ?? "AC",
    role_id: user?.role?.id ? String(user.role.id) : "",
    unit_description: user?.unit?.description ?? "",
  };
}

export default function UsersPage() {
  const queryClient = useQueryClient();
  const toast = useToast();

  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(10);
  const [searchTerm, setSearchTerm] = useState("");
  const [statusFilter, setStatusFilter] = useState("");
  const [roleFilter, setRoleFilter] = useState("");
  const [unitFilter, setUnitFilter] = useState("");
  const [isFormOpen, setIsFormOpen] = useState(false);
  const [editingUserId, setEditingUserId] = useState(null);
  const [isDetailLoading, setIsDetailLoading] = useState(false);
  const [unitSearch, setUnitSearch] = useState("");
  const [isUnitMenuOpen, setIsUnitMenuOpen] = useState(false);
  const [form, setForm] = useState(initialForm);
  const [formErrors, setFormErrors] = useState({});
  const [feedback, setFeedback] = useState(null);
  const [lookupFeedback, setLookupFeedback] = useState(null);
  const [lastLookupCi, setLastLookupCi] = useState("");

  const deferredSearchTerm = useDeferredValue(searchTerm.trim());

  const usersQuery = useQuery({
    queryKey: ["users", { page, perPage, deferredSearchTerm, statusFilter, roleFilter, unitFilter }],
    queryFn: () => usersService.list({
      page,
      perPage,
      search: deferredSearchTerm,
      status: statusFilter,
      roleId: roleFilter,
      unitId: unitFilter,
    }),
    placeholderData: (previousData) => previousData,
  });

  const rolesQuery = useQuery({
    queryKey: ["roles-catalog"],
    queryFn: usersService.listRoles,
  });

  const unitsQuery = useQuery({
    queryKey: ["units-catalog", unitSearch],
    queryFn: () => usersService.listUnits(unitSearch),
    enabled: isFormOpen,
  });

  const users = useMemo(() => usersQuery.data?.data?.items ?? [], [usersQuery.data]);
  const meta = usersQuery.data?.data?.meta ?? { current_page: 1, per_page: perPage, total: 0 };
  const roles = rolesQuery.data?.data?.items ?? [];
  const units = unitsQuery.data?.data?.items ?? [];

  const totalPages = Math.max(1, Math.ceil((meta.total || 0) / (meta.per_page || perPage)));
  const startRecord = meta.total === 0 ? 0 : (meta.current_page - 1) * meta.per_page + 1;
  const endRecord = Math.min(meta.current_page * meta.per_page, meta.total);

  const visiblePages = useMemo(() => {
    const start = Math.max(1, meta.current_page - 2);
    const end = Math.min(totalPages, start + 4);
    const first = Math.max(1, end - 4);
    return Array.from({ length: end - first + 1 }, (_, index) => first + index);
  }, [meta.current_page, totalPages]);

  const resetForm = () => {
    setForm(initialForm);
    setFormErrors({});
    setEditingUserId(null);
    setLookupFeedback(null);
    setLastLookupCi("");
    setUnitSearch("");
    setIsUnitMenuOpen(false);
  };

  const closeForm = () => {
    setIsFormOpen(false);
    resetForm();
  };

  const openCreate = () => {
    resetForm();
    setIsFormOpen(true);
  };

  const loadUserForEdit = async (userId) => {
    setFeedback(null);
    setFormErrors({});
    setLookupFeedback(null);
    setEditingUserId(userId);
    setIsFormOpen(true);
    setIsDetailLoading(true);

    try {
      const detail = await queryClient.fetchQuery({
        queryKey: ["user-detail", userId],
        queryFn: () => usersService.getById(userId),
      });

      const detailUser = detail?.data?.user;
      if (detailUser) {
        setForm(buildFormFromUser(detailUser));
        setUnitSearch(detailUser.unit?.description || "");
      }
    } catch (error) {
      setFeedback({
        type: "error",
        message: error.response?.data?.message || "No se pudo cargar el detalle del usuario.",
      });
      closeForm();
    } finally {
      setIsDetailLoading(false);
    }
  };

  const openEdit = async (userId) => {
    await loadUserForEdit(userId);
  };

  const lookupMutation = useMutation({
    mutationFn: usersService.findExistingByCi,
    onSuccess: (data, ci) => {
      setLastLookupCi(ci);

      const existingUser = data?.data?.items?.[0];

      if (!existingUser) {
        setLookupFeedback({
          type: "default",
          message: "No existe un usuario local con ese C.I. Puedes continuar con el registro manual.",
        });
        return;
      }

      setLookupFeedback({
        type: "success",
        message: `Se cargó el usuario existente ${existingUser.full_name} para edición directa.`,
      });
      void loadUserForEdit(existingUser.id);
    },
    onError: (error) => {
      setLookupFeedback({
        type: "error",
        message: error.response?.data?.message || "No se pudo consultar el usuario por C.I.",
      });
    },
  });

  const createMutation = useMutation({
    mutationFn: usersService.create,
    onSuccess: () => {
      setFeedback({ type: "success", message: "Usuario creado correctamente." });
      toast.success("Usuario creado correctamente.");
      closeForm();
      queryClient.invalidateQueries({ queryKey: ["users"] });
    },
    onError: (error) => {
      setFormErrors(normalizeFormErrors(error.response?.data?.errors ?? {}));
      setFeedback({
        type: "error",
        message: error.response?.status === 403
          ? "Acceso denegado para crear usuarios."
          : validationMessage(error, "No se pudo crear el usuario."),
      });
    },
  });

  const updateMutation = useMutation({
    mutationFn: ({ userId, payload }) => usersService.update(userId, payload),
    onSuccess: () => {
      setFeedback({ type: "success", message: "Usuario actualizado correctamente." });
      toast.success("Usuario actualizado correctamente.");
      closeForm();
      queryClient.invalidateQueries({ queryKey: ["users"] });
      queryClient.invalidateQueries({ queryKey: ["user-detail"] });
    },
    onError: (error) => {
      setFormErrors(normalizeFormErrors(error.response?.data?.errors ?? {}));
      setFeedback({
        type: "error",
        message: error.response?.status === 403
          ? "Acceso denegado para editar usuarios."
          : validationMessage(error, "No se pudo actualizar el usuario."),
      });
    },
  });

  const statusMutation = useMutation({
    mutationFn: ({ userId, status }) => usersService.updateStatus(userId, status),
    onSuccess: () => {
      setFeedback({ type: "success", message: "Estado actualizado correctamente." });
      toast.success("Estado actualizado correctamente.");
      queryClient.invalidateQueries({ queryKey: ["users"] });
    },
    onError: (error) => {
      setFeedback({
        type: "error",
        message: error.response?.status === 403
          ? "Acceso denegado para cambiar estado del usuario."
          : error.response?.data?.message || "No se pudo actualizar el estado.",
      });
    },
  });

  const isSubmitting = createMutation.isPending || updateMutation.isPending;
  const isEditing = editingUserId !== null;

  const handlePerPageChange = (event) => {
    setPerPage(Number(event.target.value));
    setPage(1);
  };

  const handleFormChange = (event) => {
    const { id, value } = event.target;
    setForm((current) => ({ ...current, [id]: value }));

    if (id === "ci") {
      setLookupFeedback(null);
      setLastLookupCi("");
    }

    if (id === "unit_description") {
      setUnitSearch(value);
      setIsUnitMenuOpen(true);
    }
  };

  const handleUnitSelect = (unit) => {
    setForm((current) => ({
      ...current,
      unit_description: unit.descripcion,
    }));
    setUnitSearch(unit.descripcion);
    setIsUnitMenuOpen(false);
  };

  const validateForm = () => {
    const nextErrors = {};

    if (!form.ci.trim()) nextErrors.ci = ["El C.I. es obligatorio."];
    if (!form.username.trim()) nextErrors.username = ["El usuario es obligatorio."];
    if (!form.role_id) nextErrors.role_id = ["El rol es obligatorio."];
    if (!form.estado) nextErrors.estado = ["El estado es obligatorio."];

    if (isEditing) {
      if (!form.funcionario.trim()) nextErrors.funcionario = ["El nombre completo es obligatorio."];
      if (!form.unit_description.trim()) nextErrors.unit_description = ["La unidad es obligatoria."];
    }

    if (!isEditing) {
      if (!form.password) nextErrors.password = ["La contraseña es obligatoria."];
      if (!form.password_confirmation) nextErrors.password_confirmation = ["La confirmación es obligatoria."];
      if (form.password && form.password_confirmation && form.password !== form.password_confirmation) {
        nextErrors.password_confirmation = ["La confirmación no coincide."];
      }
    }

    setFormErrors(nextErrors);
    return Object.keys(nextErrors).length === 0;
  };

  const lookupByCiIfNeeded = async () => {
    const trimmedCi = form.ci.trim();

    if (!trimmedCi || trimmedCi.length < 5 || trimmedCi === lastLookupCi) {
      return null;
    }

    try {
      const data = await lookupMutation.mutateAsync(trimmedCi);
      return data?.data?.items?.[0] ?? null;
    } catch {
      return null;
    }
  };

  const findExistingUserByCiForSubmit = async () => {
    const trimmedCi = form.ci.trim();

    if (!trimmedCi || trimmedCi.length < 5) {
      return null;
    }

    try {
      const data = await usersService.findExistingByCi(trimmedCi);
      return data?.data?.items?.[0] ?? null;
    } catch {
      return null;
    }
  };

  const handleSubmitForm = (event) => {
    event.preventDefault();
    setFeedback(null);
    setLookupFeedback(null);

    if (!validateForm()) {
      return;
    }

    const submit = async () => {
      const existingUser = !isEditing ? await findExistingUserByCiForSubmit() : null;

      if (!isEditing && existingUser) {
        setFormErrors((current) => ({
          ...current,
          ci: ["Ya existe un usuario registrado con ese C.I."],
        }));
        setLookupFeedback({
          type: "error",
          message: `Ya existe un usuario con ese C.I.: ${existingUser.full_name}. Abre ese registro para editarlo en lugar de crear uno nuevo.`,
        });
        return;
      }

      const resolvedFullName = form.funcionario.trim();
      const resolvedUnitDescription = form.unit_description.trim();

      if (!resolvedFullName || !resolvedUnitDescription) {
        setFormErrors((current) => ({
          ...current,
          ...(!resolvedFullName ? { funcionario: ["No se pudo resolver el nombre completo. Completa el campo manualmente."] } : {}),
          ...(!resolvedUnitDescription ? { unit_description: ["No se pudo resolver la unidad. Completa el campo manualmente."] } : {}),
        }));
        return;
      }

      const payload = {
        funcionario: resolvedFullName,
        ci: form.ci.trim(),
        username: form.username.trim(),
        estado: form.estado,
        role_id: Number(form.role_id),
        unidad: {
          descripcion: resolvedUnitDescription,
        },
      };

      if (isEditing) {
        updateMutation.mutate({ userId: editingUserId, payload });
        return;
      }

      createMutation.mutate({
        ...payload,
        password: form.password,
        password_confirmation: form.password_confirmation,
      });
    };

    void submit();
  };

  const handleToggleStatus = (user) => {
    const nextStatus = user.status === "AC" ? "DC" : "AC";
    statusMutation.mutate({ userId: user.id, status: nextStatus });
  };

  return (
    <div className="flex flex-col gap-6 animate-in fade-in duration-500">
      <Card className="border border-border/70 bg-white/86 shadow-[0_24px_90px_rgba(15,23,42,0.08)] backdrop-blur">
        <CardHeader className="gap-4 border-b border-border/70 bg-muted/25">
          <div className="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
            <div className="flex items-center gap-3">
              <div className="flex size-11 items-center justify-center rounded-2xl bg-foreground text-background">
                <UserRound className="size-5" />
              </div>
              <div>
                <CardTitle className="text-2xl tracking-[-0.04em]">Administración de usuarios</CardTitle>
              </div>
            </div>

            <div className="flex flex-wrap gap-2">
              <Button
                variant="outline"
                className="rounded-full border-border/70 bg-background/80"
                onClick={() => usersQuery.refetch()}
                disabled={usersQuery.isFetching}
              >
                <RefreshCcw data-icon="inline-start" className={cn(usersQuery.isFetching && "animate-spin")} />
                Refrescar
              </Button>
              <Button className="rounded-full bg-foreground text-background hover:bg-foreground/90" onClick={openCreate}>
                <Plus data-icon="inline-start" />
                Nuevo
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

          <div className="grid gap-4 xl:grid-cols-[minmax(0,1.1fr)_repeat(3,minmax(0,0.5fr))] xl:items-end">
            <div className="flex flex-col gap-2">
              <Label htmlFor="searchName" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                Búsqueda global
              </Label>
              <ClearableSearchInput
                id="searchName"
                placeholder="Buscar por nombre, C.I., usuario, rol o unidad"
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
                isLoading={usersQuery.isFetching && !usersQuery.isLoading}
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
                <option value="AC">Activos</option>
                <option value="DC">Inactivos</option>
              </select>
            </div>

            <div className="flex flex-col gap-2">
              <Label htmlFor="roleFilter" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                Rol
              </Label>
              <select
                id="roleFilter"
                className="h-12 rounded-2xl border border-border/80 bg-background/90 px-4 text-sm text-foreground outline-none"
                value={roleFilter}
                onChange={(event) => {
                  setRoleFilter(event.target.value);
                  setPage(1);
                }}
              >
                <option value="">Todos</option>
                {roles.map((role) => (
                  <option key={role.id} value={role.id}>{role.name}</option>
                ))}
              </select>
            </div>

            <div className="flex flex-col gap-2">
              <Label htmlFor="unitFilter" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                Unidad
              </Label>
              <select
                id="unitFilter"
                className="h-12 rounded-2xl border border-border/80 bg-background/90 px-4 text-sm text-foreground outline-none"
                value={unitFilter}
                onChange={(event) => {
                  setUnitFilter(event.target.value);
                  setPage(1);
                }}
              >
                <option value="">Todas</option>
                {units.map((unit) => (
                  <option key={unit.id_unidad} value={unit.id_unidad}>{unit.descripcion}</option>
                ))}
              </select>
            </div>

            <div className="xl:col-span-4 flex flex-wrap items-center justify-between gap-3">
              <div className="flex items-center gap-3">
                <div className="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                  <Filter className="size-3.5" />
                  Mostrar
                </div>
                <select
                  className="h-11 min-w-28 rounded-2xl border border-border/80 bg-background/90 px-4 text-sm text-foreground outline-none"
                  value={perPage}
                  onChange={handlePerPageChange}
                >
                  <option value={10}>10</option>
                  <option value={25}>25</option>
                  <option value={50}>50</option>
                </select>
              </div>
            </div>
          </div>

          {usersQuery.isLoading && (
            <div className="flex items-center justify-center rounded-2xl border border-border/70 bg-background/70 p-8 text-muted-foreground">
              <Loader2 className="mr-2 size-4 animate-spin" /> Cargando usuarios...
            </div>
          )}

          {usersQuery.isError && (
            <Alert variant="destructive" className="rounded-2xl">
              <AlertDescription>
                {usersQuery.error?.response?.status === 403
                  ? "No tienes permisos para acceder a la administración de usuarios."
                  : usersQuery.error?.response?.data?.message || "No se pudieron cargar los usuarios."}
              </AlertDescription>
            </Alert>
          )}

          {!usersQuery.isLoading && !usersQuery.isError && (
            <>
              <div className="hidden overflow-hidden rounded-[28px] border border-border/70 bg-background/90 lg:block">
                <div className="overflow-x-auto">
                  <table className="min-w-full border-collapse text-sm">
                    <thead>
                      <tr className="border-b border-border/70 bg-muted/30 text-left">
                        <th className="px-5 py-4 font-semibold text-foreground">Nro</th>
                        <th className="px-5 py-4 font-semibold text-foreground">C.I.</th>
                        <th className="px-5 py-4 font-semibold text-foreground">Nombre completo</th>
                        <th className="px-5 py-4 font-semibold text-foreground">Unidad</th>
                        <th className="px-5 py-4 font-semibold text-foreground">Usuario</th>
                        <th className="px-5 py-4 font-semibold text-foreground">Rol</th>
                        <th className="px-5 py-4 font-semibold text-foreground">Estado</th>
                        <th className="px-5 py-4 font-semibold text-foreground text-right">Opciones</th>
                      </tr>
                    </thead>
                    <tbody>
                      {users.map((user, index) => (
                        <tr key={user.id} className={index < users.length - 1 ? "border-b border-border/60" : ""}>
                          <td className="px-5 py-4 align-top text-foreground">{startRecord + index}</td>
                          <td className="px-5 py-4 align-top text-muted-foreground">{user.ci || "-"}</td>
                          <td className="px-5 py-4 align-top text-foreground">
                            <div className="font-medium">{user.full_name}</div>
                          </td>
                          <td className="px-5 py-4 align-top text-muted-foreground">{user.unit?.description || "Sin unidad"}</td>
                          <td className="px-5 py-4 align-top text-muted-foreground">{user.username}</td>
                          <td className="px-5 py-4 align-top text-muted-foreground">{user.role?.name || "Sin rol"}</td>
                          <td className="px-5 py-4 align-top">
                            <Badge className={`rounded-full px-3 py-1 text-[11px] uppercase tracking-[0.18em] ${statusClassMap[user.status] || "bg-slate-500 text-white"}`}>
                              {user.status_label || statusLabelMap[user.status] || user.status}
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
                                <DropdownMenuContent align="end" className="w-52 rounded-2xl border-border/70 bg-background/95 p-2 shadow-xl backdrop-blur-xl">
                                  <DropdownMenuItem className="rounded-xl px-3 py-2 cursor-pointer" onClick={() => openEdit(user.id)}>
                                    <Pencil className="mr-2 size-4" />
                                    Editar usuario
                                  </DropdownMenuItem>
                                  {user.available_actions?.change_status && (
                                    <>
                                      <DropdownMenuSeparator className="bg-border/70" />
                                      <DropdownMenuItem className="rounded-xl px-3 py-2 cursor-pointer" onClick={() => handleToggleStatus(user)}>
                                        <Shield className="mr-2 size-4" />
                                        {user.status === "AC" ? "Desactivar" : "Activar"}
                                      </DropdownMenuItem>
                                    </>
                                  )}
                                </DropdownMenuContent>
                              </DropdownMenu>
                            </div>
                          </td>
                        </tr>
                      ))}
                      {users.length === 0 && (
                        <tr>
                          <td colSpan={8} className="px-5 py-8 text-center text-muted-foreground">
                            No se encontraron usuarios para los filtros seleccionados.
                          </td>
                        </tr>
                      )}
                    </tbody>
                  </table>
                </div>
              </div>

              <div className="grid gap-4 lg:hidden">
                {users.length === 0 ? (
                  <Card className="border border-border/70 bg-background/90">
                    <CardContent className="p-6 text-center text-muted-foreground">
                      No se encontraron usuarios para los filtros seleccionados.
                    </CardContent>
                  </Card>
                ) : users.map((user) => (
                  <Card key={user.id} className="border border-border/70 bg-background/90">
                    <CardContent className="flex flex-col gap-4 p-5">
                      <div className="flex items-start justify-between gap-3">
                        <div>
                          <p className="font-medium text-foreground">{user.full_name}</p>
                          <p className="text-sm text-muted-foreground">{user.username}</p>
                        </div>
                        <Badge className={`rounded-full px-3 py-1 text-[11px] uppercase tracking-[0.18em] ${statusClassMap[user.status] || "bg-slate-500 text-white"}`}>
                          {user.status_label || statusLabelMap[user.status] || user.status}
                        </Badge>
                      </div>
                      <div className="grid gap-2 text-sm text-muted-foreground">
                        <p><span className="font-medium text-foreground">C.I.:</span> {user.ci || "-"}</p>
                        <p><span className="font-medium text-foreground">Unidad:</span> {user.unit?.description || "Sin unidad"}</p>
                        <p><span className="font-medium text-foreground">Rol:</span> {user.role?.name || "Sin rol"}</p>
                      </div>
                      <div className="flex gap-2">
                        <Button variant="outline" className="rounded-full border-border/70" onClick={() => openEdit(user.id)}>
                          <Pencil data-icon="inline-start" />
                          Editar
                        </Button>
                        {user.available_actions?.change_status && (
                          <Button variant="outline" className="rounded-full border-border/70" onClick={() => handleToggleStatus(user)}>
                            <Shield data-icon="inline-start" />
                            {user.status === "AC" ? "Desactivar" : "Activar"}
                          </Button>
                        )}
                      </div>
                    </CardContent>
                  </Card>
                ))}
              </div>

              <div className="flex flex-col gap-4 px-1 text-sm text-muted-foreground lg:flex-row lg:items-center lg:justify-between">
                <p>Mostrando registros del {startRecord} al {endRecord} de un total de {meta.total} registros</p>

                <div className="flex items-center gap-2 self-end lg:self-auto">
                  <Button
                    variant="ghost"
                    size="sm"
                    className="rounded-full text-muted-foreground"
                    onClick={() => setPage((current) => Math.max(1, current - 1))}
                    disabled={meta.current_page <= 1 || usersQuery.isFetching}
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
                        disabled={usersQuery.isFetching}
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
                    disabled={meta.current_page >= totalPages || usersQuery.isFetching}
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

      {isFormOpen && createPortal(
        <div className="fixed inset-0 z-[80] flex justify-end bg-slate-950/20 backdrop-blur-[1px]">
          <div className="w-full max-w-2xl overflow-y-auto border-l border-border/70 bg-background/96 p-4 shadow-[0_0_60px_rgba(15,23,42,0.16)] backdrop-blur xl:p-6">
            <Card className="border border-border/70 bg-white/92 shadow-[0_24px_90px_rgba(15,23,42,0.08)]">
              <CardHeader className="border-b border-border/70 bg-muted/20">
                <div className="flex items-start justify-between gap-4">
                  <div>
                    <CardTitle className="text-2xl tracking-[-0.04em]">
                      {isEditing ? "Editar usuario" : "Nuevo usuario"}
                    </CardTitle>
                    <CardDescription>
                      {isEditing
                        ? "Actualiza la información administrativa del usuario seleccionado."
                        : "Busca primero al funcionario por C.I. y luego completa o corrige manualmente los datos."}
                    </CardDescription>
                  </div>

                  <Button variant="ghost" size="icon-sm" className="rounded-full" onClick={closeForm}>
                    <X />
                  </Button>
                </div>
              </CardHeader>

              <CardContent className="p-5 sm:p-6">
                {isEditing && isDetailLoading ? (
                  <div className="flex items-center justify-center rounded-2xl border border-border/70 bg-background/70 p-8 text-muted-foreground">
                    <Loader2 className="mr-2 size-4 animate-spin" /> Cargando detalle del usuario...
                  </div>
                ) : (
                  <form className="flex flex-col gap-5" onSubmit={handleSubmitForm}>
                    {feedback?.type === "error" && (
                      <Alert variant="destructive" className="rounded-2xl">
                        <AlertDescription>{feedback.message}</AlertDescription>
                      </Alert>
                    )}

                    <div className="grid gap-5 sm:grid-cols-2">
                      <div className="flex flex-col gap-2">
                        <Label htmlFor="ci" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                          C.I.
                        </Label>
                        <div className="flex gap-2">
                          <Input
                            id="ci"
                            value={form.ci}
                            onChange={handleFormChange}
                            className="h-12 rounded-2xl border-border/80 bg-background/90"
                          />
                          <Button type="button" variant="outline" className="h-12 rounded-full border-border/70 bg-background/80 px-4" onClick={() => void lookupByCiIfNeeded()} disabled={lookupMutation.isPending}>
                            {lookupMutation.isPending ? <Loader2 data-icon="inline-start" className="animate-spin" /> : <SearchCheck data-icon="inline-start" />}
                            Buscar
                          </Button>
                        </div>
                        {formErrors.ci && <p className="text-sm text-destructive">{formErrors.ci[0]}</p>}
                      </div>

                      {lookupFeedback && (
                        <div className="sm:col-span-2">
                          <Alert
                            variant={lookupFeedback.type === "error" ? "destructive" : "default"}
                            className={cn(
                              "rounded-2xl border-border/70",
                              lookupFeedback.type === "success" && "border-emerald-200 bg-emerald-50 text-emerald-900",
                            )}
                          >
                            <AlertDescription>{lookupFeedback.message}</AlertDescription>
                          </Alert>
                        </div>
                      )}

                      <div className="flex flex-col gap-2 sm:col-span-2">
                        <Label htmlFor="funcionario" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                          Nombre completo
                        </Label>
                        <Input id="funcionario" value={form.funcionario} onChange={handleFormChange} className="h-12 rounded-2xl border-border/80 bg-background/90" />
                        {formErrors.funcionario && <p className="text-sm text-destructive">{formErrors.funcionario[0]}</p>}
                      </div>

                      <div className="flex flex-col gap-2">
                        <Label htmlFor="username" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                          Usuario
                        </Label>
                        <Input id="username" value={form.username} onChange={handleFormChange} className="h-12 rounded-2xl border-border/80 bg-background/90" />
                        {formErrors.username && <p className="text-sm text-destructive">{formErrors.username[0]}</p>}
                      </div>

                      <div className="flex flex-col gap-2">
                        <Label htmlFor="role_id" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                          Rol
                        </Label>
                        <select id="role_id" value={form.role_id} onChange={handleFormChange} className="h-12 rounded-2xl border border-border/80 bg-background/90 px-4 text-sm text-foreground outline-none">
                          <option value="">Selecciona un rol</option>
                          {roles.map((role) => (
                            <option key={role.id} value={role.id}>{role.name}</option>
                          ))}
                        </select>
                        {formErrors.role_id && <p className="text-sm text-destructive">{formErrors.role_id[0]}</p>}
                      </div>

                      {!isEditing && (
                        <>
                          <div className="flex flex-col gap-2">
                            <Label htmlFor="password" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                              Contraseña
                            </Label>
                            <Input id="password" type="password" value={form.password} onChange={handleFormChange} className="h-12 rounded-2xl border-border/80 bg-background/90" />
                            {formErrors.password && <p className="text-sm text-destructive">{formErrors.password[0]}</p>}
                          </div>

                          <div className="flex flex-col gap-2">
                            <Label htmlFor="password_confirmation" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                              Confirmación
                            </Label>
                            <Input id="password_confirmation" type="password" value={form.password_confirmation} onChange={handleFormChange} className="h-12 rounded-2xl border-border/80 bg-background/90" />
                            {formErrors.password_confirmation && <p className="text-sm text-destructive">{formErrors.password_confirmation[0]}</p>}
                          </div>
                        </>
                      )}

                      <div className="flex flex-col gap-2">
                        <Label htmlFor="estado" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                          Estado
                        </Label>
                        <select id="estado" value={form.estado} onChange={handleFormChange} className="h-12 rounded-2xl border border-border/80 bg-background/90 px-4 text-sm text-foreground outline-none">
                          <option value="AC">Activo</option>
                          <option value="DC">Inactivo</option>
                        </select>
                        {formErrors.estado && <p className="text-sm text-destructive">{formErrors.estado[0]}</p>}
                      </div>

                      <div className="flex flex-col gap-2 sm:col-span-2">
                        <Label htmlFor="unit_description" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                          Unidad
                        </Label>
                        <Input
                          id="unit_description"
                          value={form.unit_description}
                          onChange={handleFormChange}
                          onFocus={() => setIsUnitMenuOpen(true)}
                          placeholder="Descripción de unidad administrativa"
                          className="h-12 rounded-2xl border-border/80 bg-background/90"
                        />
                        {isUnitMenuOpen && (
                          <div className="mt-2 max-h-52 overflow-y-auto rounded-2xl border border-border/70 bg-background/98 p-2 shadow-[0_18px_50px_rgba(15,23,42,0.12)] backdrop-blur">
                            {unitsQuery.isLoading ? (
                              <div className="flex items-center gap-2 px-3 py-3 text-sm text-muted-foreground">
                                <Loader2 className="size-4 animate-spin" /> Buscando unidades...
                              </div>
                            ) : units.length > 0 ? (
                              units.map((unit) => (
                                <button
                                  key={unit.id_unidad}
                                  type="button"
                                  className="flex w-full items-center rounded-xl px-3 py-2 text-left text-sm text-foreground hover:bg-muted/50"
                                  onClick={() => handleUnitSelect(unit)}
                                >
                                  {unit.descripcion}
                                </button>
                              ))
                            ) : (
                              <div className="px-3 py-3 text-sm text-muted-foreground">
                                No se encontraron unidades.
                              </div>
                            )}
                          </div>
                        )}
                        {formErrors.unit_description && <p className="text-sm text-destructive">{formErrors.unit_description[0]}</p>}
                        <p className="text-sm text-muted-foreground">
                          Usa el botón de búsqueda para consultar por C.I. Si ese C.I. ya existe en el sistema, se cargará automáticamente para edición.
                        </p>
                      </div>
                    </div>

                    <Separator className="bg-border/70" />

                    <div className="flex flex-wrap justify-end gap-2">
                      <Button type="button" variant="outline" className="rounded-full border-border/70 bg-background/80" onClick={closeForm}>
                        Cancelar
                      </Button>
                      <Button type="submit" className="rounded-full bg-foreground text-background hover:bg-foreground/90" disabled={isSubmitting}>
                        {isSubmitting && <Loader2 data-icon="inline-start" className="animate-spin" />}
                        {isEditing ? "Guardar cambios" : "Crear usuario"}
                      </Button>
                    </div>
                  </form>
                )}
              </CardContent>
            </Card>
          </div>
        </div>
      , document.body)}
    </div>
  );
}
