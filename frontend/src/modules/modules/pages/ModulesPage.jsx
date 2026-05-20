import { useState } from "react";
import { createPortal } from "react-dom";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Boxes, Loader2, MoreHorizontal, Pencil, Plus, Trash2, X } from "lucide-react";

import { Alert, AlertDescription } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from "@/components/ui/dropdown-menu";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Separator } from "@/components/ui/separator";
import { modulesService } from "../services/modules.service";

const statusClass = {
  AC: "bg-emerald-600 text-white",
  DC: "bg-rose-600 text-white",
};

export default function ModulesPage() {
  const queryClient = useQueryClient();
  const [formOpen, setFormOpen] = useState(false);
  const [deleteOpen, setDeleteOpen] = useState(false);
  const [selectedModule, setSelectedModule] = useState(null);

  const { data, isLoading, isError, error } = useQuery({
    queryKey: ["modules"],
    queryFn: modulesService.list,
  });

  const createMutation = useMutation({
    mutationFn: modulesService.create,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["modules"] });
      closeForm();
    },
  });

  const updateMutation = useMutation({
    mutationFn: modulesService.update,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["modules"] });
      closeForm();
    },
  });

  const deleteMutation = useMutation({
    mutationFn: modulesService.remove,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["modules"] });
      closeDelete();
    },
  });

  const items = data?.data?.items ?? [];

  const openNew = () => {
    setSelectedModule({ nombre_modulo: "", estado: "AC" });
    setFormOpen(true);
  };

  const openEdit = (module) => {
    setSelectedModule(module);
    setFormOpen(true);
  };

  const closeForm = () => {
    setFormOpen(false);
    setSelectedModule(null);
  };

  const openDelete = (module) => {
    setSelectedModule(module);
    setDeleteOpen(true);
  };

  const closeDelete = () => {
    setDeleteOpen(false);
    setSelectedModule(null);
  };

  const handleSubmit = async (event) => {
    event.preventDefault();
    const formData = new FormData(event.currentTarget);
    const payload = {
      nombre_modulo: String(formData.get("nombre_modulo") || "").trim(),
      estado: String(formData.get("estado") || "AC"),
    };

    try {
      if (selectedModule?.id_modulo) {
        await updateMutation.mutateAsync({ id: selectedModule.id_modulo, payload });
        return;
      }

      await createMutation.mutateAsync(payload);
    } catch (mutationError) {
      alert(mutationError?.response?.data?.message || "No se pudo guardar el modulo.");
    }
  };

  const handleDelete = async () => {
    try {
      await deleteMutation.mutateAsync(selectedModule.id_modulo);
    } catch (mutationError) {
      alert(mutationError?.response?.data?.message || "No se pudo eliminar el modulo.");
    }
  };

  return (
    <>
      <div className={`flex flex-col gap-6 animate-in fade-in duration-500 ${formOpen || deleteOpen ? "blur-sm" : ""}`}>
        <Card className="border border-border/70 bg-white/86 shadow-[0_24px_90px_rgba(15,23,42,0.08)]">
          <CardHeader className="gap-4 border-b border-border/70 bg-muted/25">
            <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
              <div className="flex items-center gap-3">
                <div className="flex size-11 items-center justify-center rounded-2xl bg-sky-600 text-white">
                  <Boxes className="size-5" />
                </div>
                <CardTitle className="text-2xl tracking-[-0.04em]">Módulos</CardTitle>
              </div>
              <Button className="bg-sky-600 hover:bg-sky-700" onClick={openNew}>
                <Plus className="mr-2 h-4 w-4" />
                Nuevo
              </Button>
            </div>
          </CardHeader>

          <CardContent className="flex flex-col gap-6 p-5 sm:p-6">
            {isLoading && (
              <div className="flex items-center justify-center rounded-2xl border border-border/70 bg-background/70 p-8 text-muted-foreground">
                <Loader2 className="mr-2 size-4 animate-spin" /> Cargando módulos...
              </div>
            )}

            {isError && (
              <Alert variant="destructive" className="rounded-2xl">
                <AlertDescription>
                  {error?.response?.data?.message || "No se pudieron cargar los módulos."}
                </AlertDescription>
              </Alert>
            )}

            {!isLoading && !isError && (
              <div className="overflow-hidden rounded-[28px] border border-border/70 bg-background/90">
                <div className="overflow-x-auto max-h-[520px] overflow-y-auto">
                  <table className="min-w-full border-collapse text-sm">
                    <thead>
                      <tr className="border-b border-border/70 bg-muted/30 text-left">
                        <th className="px-5 py-4 font-semibold text-foreground">N°</th>
                        <th className="px-5 py-4 font-semibold text-foreground">Nombre</th>
                        <th className="px-5 py-4 font-semibold text-foreground">Estado</th>
                        <th className="px-5 py-4 font-semibold text-foreground text-center">Opciones</th>
                      </tr>
                    </thead>
                    <tbody>
                      {items.map((item, index) => {
                        const status = item.estado || "DC";
                        const statusLabelText = item.status_label || (status === "AC" ? "ACTIVO" : "INACTIVO");

                        return (
                          <tr key={item.id_modulo || index} className={index < items.length - 1 ? "border-b border-border/60" : ""}>
                            <td className="px-5 py-4 align-top text-foreground">{index + 1}</td>
                            <td className="px-5 py-4 align-top text-foreground">{item.nombre_modulo?.trim()}</td>
                            <td className="px-5 py-4 align-top">
                              <Badge className={`rounded-full px-3 py-1 text-[11px] uppercase tracking-[0.18em] ${statusClass[status] || "bg-slate-500 text-white"}`}>
                                {statusLabelText}
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
                                    <DropdownMenuItem className="flex items-center gap-2 rounded-xl px-3 py-2 cursor-pointer" onClick={() => openEdit(item)}>
                                      <Pencil className="h-4 w-4 text-muted-foreground" />
                                      <span>Editar</span>
                                    </DropdownMenuItem>
                                  )}
                                  {item.available_actions?.delete && (
                                    <DropdownMenuItem className="flex items-center gap-2 rounded-xl px-3 py-2 cursor-pointer text-destructive" onClick={() => openDelete(item)}>
                                      <Trash2 className="h-4 w-4" />
                                      <span>Eliminar</span>
                                    </DropdownMenuItem>
                                  )}
                                </DropdownMenuContent>
                              </DropdownMenu>
                            </td>
                          </tr>
                        );
                      })}
                      {items.length === 0 && (
                        <tr>
                          <td colSpan={4} className="px-5 py-8 text-center text-muted-foreground">
                            No se encontraron módulos.
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

      {formOpen && createPortal(
        <div className="fixed inset-0 z-[80] flex justify-end bg-slate-950/20 backdrop-blur-[1px]">
          <div className="w-full max-w-2xl overflow-y-auto border-l border-border/70 bg-background/96 p-4 shadow-[0_0_60px_rgba(15,23,42,0.16)] backdrop-blur xl:p-6">
            <Card className="border border-border/70 bg-white/92 shadow-[0_24px_90px_rgba(15,23,42,0.08)]">
              <CardHeader className="border-b border-border/70 bg-muted/20">
                <div className="flex items-start justify-between gap-4">
                  <CardTitle className="text-2xl tracking-[-0.04em]">
                    {selectedModule?.id_modulo ? "Editar módulo" : "Registrar nuevo módulo"}
                  </CardTitle>
                  <Button variant="ghost" size="icon-sm" className="rounded-full" onClick={closeForm}>
                    <X />
                  </Button>
                </div>
              </CardHeader>
              <CardContent className="p-5 sm:p-6">
                {selectedModule && (
                  <form className="flex flex-col gap-5" onSubmit={handleSubmit}>
                    <div className="grid gap-5">
                      <div className="flex flex-col gap-2">
                        <Label htmlFor="nombre_modulo" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">Nombre</Label>
                        <Input id="nombre_modulo" name="nombre_modulo" defaultValue={selectedModule.nombre_modulo?.trim() || ""} className="h-12 rounded-2xl border-border/80 bg-background/90" required />
                      </div>
                      <div className="flex flex-col gap-2">
                        <Label htmlFor="estado" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">Estado</Label>
                        <select id="estado" name="estado" defaultValue={selectedModule.estado || "AC"} className="h-12 rounded-2xl border border-border/80 bg-background/90 px-4">
                          <option value="AC">ACTIVO</option>
                          <option value="DC">INACTIVO</option>
                        </select>
                      </div>
                    </div>
                    <Separator className="bg-border/70" />
                    <div className="flex flex-wrap justify-end gap-2">
                      <Button type="button" variant="outline" className="rounded-full border-border/70 bg-background/80" onClick={closeForm}>Cancelar</Button>
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
                <h3 className="text-lg font-semibold text-foreground">Eliminar módulo</h3>
                <p className="text-sm text-muted-foreground">Se eliminará <strong>{selectedModule?.nombre_modulo?.trim()}</strong> si no tiene items activos asociados.</p>
              </div>
              <Button variant="ghost" size="icon-sm" className="rounded-full" onClick={closeDelete}>
                <X className="size-4" />
              </Button>
            </div>

            <div className="mt-6 flex justify-end gap-2">
              <Button type="button" variant="outline" onClick={closeDelete}>Cancelar</Button>
              <Button type="button" variant="destructive" disabled={deleteMutation.isPending} onClick={handleDelete}>
                {deleteMutation.isPending && <Loader2 className="mr-2 size-4 animate-spin" />}
                Eliminar
              </Button>
            </div>
          </div>
        </div>,
        document.body,
      )}
    </>
  );
}
