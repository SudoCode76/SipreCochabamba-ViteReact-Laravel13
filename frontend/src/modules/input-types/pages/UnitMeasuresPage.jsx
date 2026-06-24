import { useState } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { createPortal } from "react-dom";
import { FileText, Loader2, MoreHorizontal, Pencil, Trash2, X, Plus } from "lucide-react";

import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Separator } from "@/components/ui/separator";
import { useToast } from "@/components/ui/toast";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { unitMeasuresService } from "@/modules/input-types/services/unit-measures.service";

const statusClass = {
  AC: "bg-emerald-600 text-white",
  DC: "bg-rose-600 text-white",
};

export default function UnitMeasuresPage() {
  const queryClient = useQueryClient();
  const toast = useToast();
  const [editOpen, setEditOpen] = useState(false);
  const [deleteOpen, setDeleteOpen] = useState(false);
  const [selectedItem, setSelectedItem] = useState(null);

  const { data, isLoading, isError, error } = useQuery({
    queryKey: ["unit-measures"],
    queryFn: unitMeasuresService.list,
  });

  const createMutation = useMutation({
    mutationFn: unitMeasuresService.create,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["unit-measures"] });
      toast.success("Unidad de medida creada correctamente.");
      closeEdit();
    },
    onError: (err) => {
      alert(err.response?.data?.message || "Error al crear la unidad de medida.");
    },
  });

  const updateMutation = useMutation({
    mutationFn: unitMeasuresService.update,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["unit-measures"] });
      toast.success("Unidad de medida actualizada correctamente.");
      closeEdit();
    },
    onError: (err) => {
      alert(err.response?.data?.message || "Error al actualizar la unidad de medida.");
    },
  });

  const deleteMutation = useMutation({
    mutationFn: unitMeasuresService.remove,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["unit-measures"] });
      toast.success("Unidad de medida eliminada correctamente.");
      closeDelete();
    },
    onError: (err) => {
      alert(err.response?.data?.message || "No se pudo eliminar la unidad de medida.");
    },
  });

  const items = data?.data?.items ?? [];
  const isNewItem = !selectedItem?.id_unidad_medida;

  const handleEdit = (item) => {
    setSelectedItem(item);
    setEditOpen(true);
  };

  const handleNew = () => {
    setSelectedItem({ descripcion: "", abreviatura: "", estado: "AC" });
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
      descripcion: String(formData.get("Descripcion") || "").trim(),
      abreviatura: String(formData.get("abreviatura") || "").trim(),
      estado: String(formData.get("estado") || "AC"),
    };

    if (!payload.descripcion || !payload.abreviatura) {
      alert("Descripción y abreviatura son obligatorias.");
      return;
    }

    if (selectedItem?.id_unidad_medida) {
      await updateMutation.mutateAsync({
        id: selectedItem.id_unidad_medida,
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
      id: selectedItem.id_unidad_medida,
      authorization_code,
    });
  };

  return (
    <>
      <div className={`flex flex-col gap-6 animate-in fade-in duration-500 ${editOpen || deleteOpen ? "blur-sm" : ""}`}>
        <Card className="border border-border/70 bg-white/86 shadow-[0_24px_90px_rgba(15,23,42,0.08)]">
          <CardHeader className="gap-4 border-b border-border/70 bg-muted/25">
            <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
              <div className="flex items-center gap-3">
                <div className="flex size-11 items-center justify-center rounded-2xl bg-sky-600 text-white">
                  <FileText className="size-5" />
                </div>
                <CardTitle className="text-2xl tracking-[-0.04em]">Unidades de Medida</CardTitle>
              </div>
              <Button className="bg-sky-600 hover:bg-sky-700" onClick={handleNew}>
                <Plus className="mr-2 h-4 w-4" />
                Registrar nueva medida
              </Button>
            </div>
          </CardHeader>

          <CardContent className="flex flex-col gap-6 p-5 sm:p-6">
            {isLoading && (
              <div className="flex items-center justify-center rounded-2xl border border-border/70 bg-background/70 p-8 text-muted-foreground">
                <Loader2 className="mr-2 size-4 animate-spin" /> Cargando...
              </div>
            )}

            {isError && (
              <Alert variant="destructive" className="rounded-2xl">
                <AlertDescription>
                  {error?.response?.data?.message || "Error al cargar unidades de medida."}
                </AlertDescription>
              </Alert>
            )}

            {!isLoading && !isError && (
              <div className="overflow-hidden rounded-[28px] border border-border/70 bg-background/90">
                <div className="overflow-x-auto max-h-[560px] overflow-y-auto">
                  <table className="min-w-full border-collapse text-sm">
                    <thead>
                      <tr className="border-b border-border/70 bg-muted/30 text-left">
                        <th className="px-5 py-4 font-semibold text-foreground">N°</th>
                        <th className="px-5 py-4 font-semibold text-foreground">Descripción</th>
                        <th className="px-5 py-4 font-semibold text-foreground">Abreviatura</th>
                        <th className="px-5 py-4 font-semibold text-foreground">Estado</th>
                        <th className="px-5 py-4 font-semibold text-foreground text-center">Opciones</th>
                      </tr>
                    </thead>
                    <tbody>
                      {items.map((item, index) => (
                        <tr key={item.id_unidad_medida} className={index < items.length - 1 ? "border-b border-border/60" : ""}>
                          <td className="px-5 py-4 align-top text-foreground">{index + 1}</td>
                          <td className="px-5 py-4 align-top text-foreground">{item.descripcion}</td>
                          <td className="px-5 py-4 align-top text-foreground">{item.abreviatura}</td>
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
                              <DropdownMenuContent align="end" className="w-44 rounded-2xl border border-border/70 bg-background/95 p-1 shadow-lg">
                                {item.available_actions?.edit && (
                                  <DropdownMenuItem className="flex items-center gap-2 rounded-xl px-3 py-2 cursor-pointer" onClick={() => handleEdit(item)}>
                                    <Pencil className="h-4 w-4 text-muted-foreground" />
                                    <span>Editar</span>
                                  </DropdownMenuItem>
                                )}
                                {item.available_actions?.delete && (
                                  <DropdownMenuItem className="flex items-center gap-2 rounded-xl px-3 py-2 cursor-pointer text-rose-600 focus:text-rose-700" onClick={() => openDelete(item)}>
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
                          <td colSpan={5} className="px-5 py-8 text-center text-muted-foreground">
                            No se encontraron unidades de medida.
                          </td>
                        </tr>
                      )}
                    </tbody>
                  </table>
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
                  <CardTitle className="text-2xl tracking-[-0.04em]">
                    {isNewItem ? "Registrar nueva medida" : "Editar Unidad de Medida"}
                  </CardTitle>
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
                        <Label htmlFor="descripcion" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">Nombre Unidad Medida </Label>
                        <Input id="descripcion" name="descripcion" defaultValue={selectedItem.descripcion} className="h-12 rounded-2xl border-border/80 bg-background/90" required />
                      </div>
                      <div className="flex flex-col gap-2 sm:col-span-2">
                        <Label htmlFor="abreviatura" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">Abreviatura</Label>
                        <Input id="abreviatura" name="abreviatura" defaultValue={selectedItem.abreviatura} className="h-12 rounded-2xl border-border/80 bg-background/90" required />
                      </div>
                      <div className="flex flex-col gap-2 sm:col-span-2">
                        <Label htmlFor="estado" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">Estado</Label>
                        <select id="estado" name="estado" defaultValue={selectedItem.estado || "AC"} className="h-12 rounded-2xl border border-border/80 bg-background/90 px-4">
                          <option value="AC">ACTIVO</option>
                          <option value="DC">INACTIVO</option>
                        </select>
                      </div>
                    </div>
                    <Separator className="bg-border/70" />
                    <div className="flex flex-wrap justify-end gap-2">
                      <Button type="button" variant="outline" className="rounded-full border-border/70 bg-background/80" onClick={closeEdit}>Cancelar</Button>
                      <Button type="submit" className="rounded-full bg-foreground text-background hover:bg-foreground/90" disabled={createMutation.isPending || updateMutation.isPending}>
                        {createMutation.isPending || updateMutation.isPending ? "Guardando..." : "Guardar cambios"}
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
                <h3 className="text-lg font-semibold text-foreground">Eliminar unidad de medida</h3>
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
