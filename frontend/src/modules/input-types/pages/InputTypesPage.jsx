import { useMemo, useState } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { createPortal } from "react-dom";
import { FileText, ChevronLeft, ChevronRight, Loader2, MoreHorizontal, Pencil, Trash2, X, Plus } from "lucide-react";

import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Separator } from "@/components/ui/separator";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { inputTypesService } from "@/modules/input-types/services/input-types.service";

const statusClass = {
  AC: "bg-emerald-600 text-white",
  DC: "bg-rose-600 text-white",
};

export default function InputTypesPage() {
  const queryClient = useQueryClient();
  const [perPage, setPerPage] = useState(15);
  const [page, setPage] = useState(1);
  const [editOpen, setEditOpen] = useState(false);
  const [deleteOpen, setDeleteOpen] = useState(false);
  const [selectedItem, setSelectedItem] = useState(null);

  const { data, isLoading, isError, error, isFetching } = useQuery({
    queryKey: ["input-types", { page, perPage }],
    queryFn: () => inputTypesService.list({ page, perPage }),
    placeholderData: (previousData) => previousData,
  });

  const createMutation = useMutation({
    mutationFn: inputTypesService.create,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["input-types"] });
      closeEdit();
    },
    onError: (err) => {
      alert(err.response?.data?.message || "Error al crear el tipo de insumo.");
    },
  });

  const updateMutation = useMutation({
    mutationFn: inputTypesService.update,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["input-types"] });
      closeEdit();
    },
    onError: (err) => {
      alert(err.response?.data?.message || "Error al actualizar el tipo de insumo.");
    },
  });

  const deleteMutation = useMutation({
    mutationFn: inputTypesService.remove,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["input-types"] });
      closeDelete();
    },
    onError: (err) => {
      alert(err.response?.data?.message || "No se pudo eliminar el tipo de insumo.");
    },
  });

  const items = data?.data?.items ?? [];
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

  const handleEdit = (item) => {
    setSelectedItem(item);
    setEditOpen(true);
  };

  const handleNew = () => {
    setSelectedItem({ descripcion: "", estado: "AC" });
    setEditOpen(true);
  };

  const closeEdit = () => {
    setEditOpen(false);
    setSelectedItem(null);
  };

  const openDelete = (item) => {
    setSelectedItem(item);
    setDeleteOpen(true);
  };

  const closeDelete = () => {
    setDeleteOpen(false);
    setSelectedItem(null);
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    const formData = new FormData(e.currentTarget);
    const payload = {
      descripcion: String(formData.get("descripcion") || "").trim(),
      estado: String(formData.get("estado") || "AC"),
    };

    if (!payload.descripcion) {
      alert("La descripción es obligatoria.");
      return;
    }

    if (selectedItem?.id_tipo) {
      await updateMutation.mutateAsync({
        id: selectedItem.id_tipo,
        payload,
      });
      return;
    }

    await createMutation.mutateAsync(payload);
  };

  const handleDelete = async (e) => {
    e.preventDefault();
    const formData = new FormData(e.currentTarget);
    const authorization_code = String(formData.get("authorization_code") || "").trim();

    if (!authorization_code) {
      alert("El código de autorización es obligatorio.");
      return;
    }

    await deleteMutation.mutateAsync({
      id: selectedItem.id_tipo,
      authorization_code,
    });
  };

  const isNewItem = !selectedItem?.id_tipo;

  return (
    <>
      <div className={`flex flex-col gap-6 animate-in fade-in duration-500 ${editOpen || deleteOpen ? "blur-sm" : ""}`}>
        <Card className="border border-border/70 bg-white/86 shadow-[0_24px_90px_rgba(15,23,42,0.08)]">
          <CardHeader className="gap-4 border-b border-border/70 bg-muted/25">
            <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
              <div className="flex items-center gap-3">
                <div className="flex size-11 items-center justify-center rounded-2xl bg-amber-600 text-white">
                  <FileText className="size-5" />
                </div>
                <div>
                  <CardTitle className="text-2xl tracking-[-0.04em]">Tipo de Insumo</CardTitle>
                </div>
              </div>
              <Button
                className="bg-amber-600 hover:bg-amber-700"
                onClick={handleNew}
              >
                <Plus className="mr-2 h-4 w-4" />
                Nuevo
              </Button>
            </div>
          </CardHeader>

          <CardContent className="flex flex-col gap-6 p-5 sm:p-6">
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
                      onChange={(e) => { setPerPage(Number(e.target.value)); setPage(1); }}
                    >
                      <option value={10}>10</option>
                      <option value={15}>15</option>
                      <option value={25}>25</option>
                      <option value={50}>50</option>
                      <option value={100}>100</option>
                    </select>
                    <span className="pointer-events-none absolute inset-y-0 right-4 flex items-center text-muted-foreground">▾</span>
                  </div>
                </div>
              </div>
            </div>

            {isLoading && (
              <div className="flex items-center justify-center rounded-2xl border border-border/70 bg-background/70 p-8 text-muted-foreground">
                <Loader2 className="mr-2 size-4 animate-spin" /> Cargando...
              </div>
            )}

            {isError && (
              <Alert variant="destructive" className="rounded-2xl">
                <AlertDescription>
                  {error?.response?.data?.message || "Error al cargar los datos."}
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
                        <th className="px-5 py-4 font-semibold text-foreground">Descripción</th>
                        <th className="px-5 py-4 font-semibold text-foreground">Estado</th>
                        <th className="px-5 py-4 font-semibold text-foreground text-center">Opciones</th>
                      </tr>
                    </thead>
                    <tbody>
                      {items.map((item, index) => (
                        <tr key={item.id_tipo} className={index < items.length - 1 ? "border-b border-border/60" : ""}>
                          <td className="px-5 py-4 align-top text-foreground">
                            {index + 1}
                          </td>
                          <td className="px-5 py-4 align-top text-foreground">
                            {item.descripcion}
                          </td>
                          <td className="px-5 py-4 align-top">
                            <Badge className={`rounded-full px-3 py-1 text-[11px] uppercase tracking-[0.18em] ${statusClass[item.estado] || "bg-slate-500 text-white"}`}>
                              {item.status_label || (item.estado === "AC" ? "ACTIVO" : "INACTIVO")}
                            </Badge>
                          </td>
                          <td className="px-5 py-4 align-top text-center">
                            <DropdownMenu>
                              <DropdownMenuTrigger asChild>
                                <Button variant="ghost" size="sm" className="h-8 w-8 p-0">
                                  <MoreHorizontal className="h-4 w-4" />
                                </Button>
                              </DropdownMenuTrigger>
                              <DropdownMenuContent align="end" className="w-48 rounded-2xl border border-border/70 bg-background/95 p-1 shadow-lg">
                                {item.available_actions?.edit && (
                                  <DropdownMenuItem
                                    className="flex items-center gap-2 rounded-xl px-3 py-2 cursor-pointer"
                                    onClick={() => handleEdit(item)}
                                  >
                                    <Pencil className="h-4 w-4 text-muted-foreground" />
                                    <span>Editar</span>
                                  </DropdownMenuItem>
                                )}
                                {item.available_actions?.delete && (
                                  <DropdownMenuItem
                                    className="flex items-center gap-2 rounded-xl px-3 py-2 cursor-pointer text-rose-600 focus:text-rose-700"
                                    onClick={() => openDelete(item)}
                                  >
                                    <Trash2 className="h-4 w-4" />
                                    <span>Eliminar</span>
                                  </DropdownMenuItem>
                                )}
                              </DropdownMenuContent>
                            </DropdownMenu>
                          </td>
                        </tr>
                      ))}
                      {items.length === 0 && (
                        <tr>
                          <td colSpan={4} className="px-5 py-8 text-center text-muted-foreground">
                            No se encontraron tipos de insumo.
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
      </div>

      {editOpen && createPortal(
        <div className="fixed inset-0 z-[80] flex justify-end bg-slate-950/20 backdrop-blur-[1px]">
          <div className="w-full max-w-2xl overflow-y-auto border-l border-border/70 bg-background/96 p-4 shadow-[0_0_60px_rgba(15,23,42,0.16)] backdrop-blur xl:p-6">
            <Card className="border border-border/70 bg-white/92 shadow-[0_24px_90px_rgba(15,23,42,0.08)]">
              <CardHeader className="border-b border-border/70 bg-muted/20">
                <div className="flex items-start justify-between gap-4">
                  <div>
                    <CardTitle className="text-2xl tracking-[-0.04em]">
                      {isNewItem ? "Nuevo Tipo de Insumo" : "Editar Tipo de Insumo"}
                    </CardTitle>
                    <CardDescription>
                      {isNewItem ? "Crear un nuevo tipo de insumo" : `ID: ${selectedItem?.id_tipo}`}
                    </CardDescription>
                  </div>

                  <Button variant="ghost" size="icon-sm" className="rounded-full" onClick={closeEdit}>
                    <X />
                  </Button>
                </div>
              </CardHeader>

              <CardContent className="p-5 sm:p-6">
                {selectedItem && (
                  <form className="flex flex-col gap-5" onSubmit={handleSubmit}>
                    <div className="grid gap-5 sm:grid-cols-2">
                      <div className="flex flex-col gap-2 sm:col-span-2">
                        <Label htmlFor="descripcion" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                          Descripción
                        </Label>
                        <Input
                          id="descripcion"
                          name="descripcion"
                          defaultValue={selectedItem.descripcion}
                          className="h-12 rounded-2xl border-border/80 bg-background/90"
                          required
                        />
                      </div>

                      <div className="flex flex-col gap-2 sm:col-span-2">
                        <Label htmlFor="estado" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                          Estado
                        </Label>
                        <select
                          id="estado"
                          name="estado"
                          defaultValue={selectedItem.estado}
                          className="h-12 rounded-2xl border border-border/80 bg-background/90 px-4"
                        >
                          <option value="AC">ACTIVO</option>
                          <option value="DC">INACTIVO</option>
                        </select>
                      </div>
                    </div>

                    <Separator className="bg-border/70" />

                    <div className="flex flex-wrap justify-end gap-2">
                      <Button type="button" variant="outline" className="rounded-full border-border/70 bg-background/80" onClick={closeEdit}>
                        Cancelar
                      </Button>
                      <Button 
                        type="submit" 
                        className="rounded-full bg-foreground text-background hover:bg-foreground/90" 
                        disabled={updateMutation.isPending || createMutation.isPending}
                      >
                        {updateMutation.isPending || createMutation.isPending ? "Guardando..." : "Guardar cambios"}
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

      {deleteOpen && createPortal(
        <div className="fixed inset-0 z-[70] flex items-center justify-center bg-black/40 p-4 backdrop-blur-sm">
          <div className="w-full max-w-lg rounded-3xl border border-border/70 bg-background p-6 shadow-2xl">
            <div className="flex items-start justify-between gap-4">
              <div>
                <h3 className="text-lg font-semibold text-foreground">Eliminar tipo de insumo</h3>
                <p className="text-sm text-muted-foreground">Ingresa la autorizacion aprobada para eliminar <strong>{selectedItem?.descripcion}</strong>.</p>
              </div>
              <Button variant="ghost" size="icon-sm" className="rounded-full" onClick={closeDelete}>
                <X className="size-4" />
              </Button>
            </div>

            <form className="mt-6 space-y-4" onSubmit={handleDelete}>
              <div className="space-y-2">
                <Label htmlFor="authorization_code">Nro. autorizacion</Label>
                <Input id="authorization_code" name="authorization_code" placeholder="Ej. AUT-2026-001" required />
              </div>
              <div className="flex justify-end gap-2">
                <Button type="button" variant="outline" onClick={closeDelete}>Cancelar</Button>
                <Button type="submit" variant="destructive" disabled={deleteMutation.isPending}>
                  {deleteMutation.isPending && <Loader2 className="mr-2 size-4 animate-spin" />}
                  Eliminar
                </Button>
              </div>
            </form>
          </div>
        </div>,
        document.body,
      )}
    </>
  );
}
