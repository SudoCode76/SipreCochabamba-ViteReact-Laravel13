import { useState } from "react";
import { createPortal } from "react-dom";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { FileText, Loader2, Pencil, Plus, X } from "lucide-react";

import { Alert, AlertDescription } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Separator } from "@/components/ui/separator";
import { useToast } from "@/components/ui/toast";
import { inputCategoriesService } from "@/modules/input-types/services/input-categories.service";

const statusClass = {
  AC: "bg-emerald-600 text-white",
  DC: "bg-rose-600 text-white",
};

export default function InputCategoriesPage() {
  const queryClient = useQueryClient();
  const toast = useToast();
  const [editOpen, setEditOpen] = useState(false);
  const [selectedItem, setSelectedItem] = useState(null);

  const { data, isLoading, isError, error, isFetching } = useQuery({
    queryKey: ["input-categories"],
    queryFn: inputCategoriesService.list,
    placeholderData: (previousData) => previousData,
  });

  const createMutation = useMutation({
    mutationFn: inputCategoriesService.create,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["input-categories"] });
      queryClient.invalidateQueries({ queryKey: ["inputs-context"] });
      toast.success("Categoría creada correctamente.");
      closeEdit();
    },
    onError: (err) => {
      alert(err.response?.data?.message || "Error al crear la categoría de insumo.");
    },
  });

  const updateMutation = useMutation({
    mutationFn: inputCategoriesService.update,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["input-categories"] });
      queryClient.invalidateQueries({ queryKey: ["inputs-context"] });
      toast.success("Categoría actualizada correctamente.");
      closeEdit();
    },
    onError: (err) => {
      alert(err.response?.data?.message || "Error al actualizar la categoría de insumo.");
    },
  });

  const items = data?.data?.items ?? [];
  const isNewItem = !selectedItem?.id_categoria;

  const handleNew = () => {
    setSelectedItem({ descripcion: "", estado: "AC" });
    setEditOpen(true);
  };

  const handleEdit = (item) => {
    setSelectedItem(item);
    setEditOpen(true);
  };

  const closeEdit = () => {
    setEditOpen(false);
    setSelectedItem(null);
  };

  const handleSubmit = async (event) => {
    event.preventDefault();
    const formData = new FormData(event.currentTarget);
    const payload = {
      descripcion: String(formData.get("descripcion") || "").trim(),
      estado: String(formData.get("estado") || "AC"),
    };

    if (!payload.descripcion) {
      alert("La descripción es obligatoria.");
      return;
    }

    if (selectedItem?.id_categoria) {
      await updateMutation.mutateAsync({
        id: selectedItem.id_categoria,
        payload,
      });
      return;
    }

    await createMutation.mutateAsync(payload);
  };

  return (
    <>
      <div className={`flex flex-col gap-6 animate-in fade-in duration-500 ${editOpen ? "blur-sm" : ""}`}>
        <Card className="border border-border/70 bg-white/86 shadow-[0_24px_90px_rgba(15,23,42,0.08)]">
          <CardHeader className="gap-4 border-b border-border/70 bg-muted/25">
            <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
              <div className="flex items-center gap-3">
                <div className="flex size-11 items-center justify-center rounded-2xl bg-emerald-600 text-white">
                  <FileText className="size-5" />
                </div>
                <div>
                  <CardTitle className="text-2xl tracking-[-0.04em]">Categorías de Insumo</CardTitle>
                  <CardDescription>Clasificación adicional para organizar insumos.</CardDescription>
                </div>
              </div>
              <Button className="bg-emerald-600 hover:bg-emerald-700" onClick={handleNew}>
                <Plus className="mr-2 h-4 w-4" />
                Nueva
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
                  {error?.response?.data?.message || "Error al cargar las categorías."}
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
                        <tr key={item.id_categoria} className={index < items.length - 1 ? "border-b border-border/60" : ""}>
                          <td className="px-5 py-4 align-top text-foreground">{index + 1}</td>
                          <td className="px-5 py-4 align-top text-foreground">{item.descripcion}</td>
                          <td className="px-5 py-4 align-top">
                            <Badge className={`rounded-full px-3 py-1 text-[11px] uppercase tracking-[0.18em] ${statusClass[item.estado] || "bg-slate-500 text-white"}`}>
                              {item.status_label || (item.estado === "AC" ? "ACTIVO" : "INACTIVO")}
                            </Badge>
                          </td>
                          <td className="px-5 py-4 align-top text-center">
                            {item.available_actions?.edit && (
                              <Button
                                variant="ghost"
                                size="sm"
                                className="h-8 gap-1 px-2 text-muted-foreground hover:text-foreground"
                                onClick={() => handleEdit(item)}
                              >
                                <Pencil className="h-4 w-4" />
                                <span>Editar</span>
                              </Button>
                            )}
                          </td>
                        </tr>
                      ))}
                      {items.length === 0 && (
                        <tr>
                          <td colSpan={4} className="px-5 py-8 text-center text-muted-foreground">
                            No se encontraron categorías de insumo.
                          </td>
                        </tr>
                      )}
                    </tbody>
                  </table>
                </div>

                <div className="px-5 py-4 text-sm text-muted-foreground">
                  {isFetching ? "Actualizando..." : `${items.length} categoría(s) registradas`}
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
                      {isNewItem ? "Nueva Categoría de Insumo" : "Editar Categoría de Insumo"}
                    </CardTitle>
                    <CardDescription>
                      {isNewItem ? "Crear una nueva categoría" : `ID: ${selectedItem?.id_categoria}`}
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
                          Nombre Categoría
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
    </>
  );
}
