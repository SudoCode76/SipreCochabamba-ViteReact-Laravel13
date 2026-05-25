import { useState } from "react";
import { createPortal } from "react-dom";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { FolderKanban, Loader2, MoreHorizontal, Pencil, Plus, Trash2, X } from "lucide-react";

import { Alert, AlertDescription } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from "@/components/ui/dropdown-menu";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Separator } from "@/components/ui/separator";
import { subgroupsService } from "@/modules/groups/services/subgroups.service";
import { groupsService } from "@/modules/groups/services/groups.service";

const statusClass = {
  AC: "bg-emerald-600 text-white",
  DC: "bg-rose-600 text-white",
};

const getSubgroupId = (item) => item?.id_subgrupo ?? item?.id_sub_grupo ?? item?.id ?? null;
const getSubgroupCode = (item) => item?.codigo ?? item?.code ?? item?.codigo_sub_grupo ?? "";
const getSubgroupDescription = (item) => item?.descripcion ?? item?.description ?? item?.nombre_sub_grupo ?? item?.nombre ?? "";
const getGroupId = (item) => item?.id_grupo ?? item?.group_id ?? "";
const getGroupOptionId = (group) => group?.id_grupo ?? group?.id ?? "";
const getGroupOptionName = (group) => group?.nombre_grupo ?? group?.name ?? group?.description ?? "-";

export default function SubgroupsPage() {
  const queryClient = useQueryClient();
  const [formOpen, setFormOpen] = useState(false);
  const [deleteOpen, setDeleteOpen] = useState(false);
  const [selectedItem, setSelectedItem] = useState(null);

  const { data, isLoading, isError, error } = useQuery({
    queryKey: ["subgroups"],
    queryFn: subgroupsService.list,
  });

  const { data: groupsData } = useQuery({
    queryKey: ["groups"],
    queryFn: groupsService.list,
  });

  const createMutation = useMutation({
    mutationFn: subgroupsService.create,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["subgroups"] });
      closeForm();
    },
  });

  const updateMutation = useMutation({
    mutationFn: subgroupsService.update,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["subgroups"] });
      closeForm();
    },
  });

  const deleteMutation = useMutation({
    mutationFn: subgroupsService.remove,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["subgroups"] });
      closeDelete();
    },
  });

  const items = data?.data?.items ?? (Array.isArray(data?.data) ? data.data : []);
  const groups = groupsData?.data?.items ?? (Array.isArray(groupsData?.data) ? groupsData.data : []);

  const openNew = () => {
    setSelectedItem({ codigo: "", descripcion: "", estado: "AC", id_grupo: "" });
    setFormOpen(true);
  };

  const openEdit = (item) => {
    setSelectedItem(item);
    setFormOpen(true);
  };

  const closeForm = () => {
    setFormOpen(false);
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

  const handleSubmit = async (event) => {
    event.preventDefault();
    const formData = new FormData(event.currentTarget);
    const payload = {
      codigo: String(formData.get("codigo_subgrupo") || "").trim(),
      descripcion: String(formData.get("nombre_subgrupo") || "").trim(),
      estado: String(formData.get("estado") || "AC"),
      id_grupo: Number(formData.get("id_grupo")),
    };

    try {
      const itemId = getSubgroupId(selectedItem);
      if (itemId) {
        await updateMutation.mutateAsync({ id: itemId, payload });
        return;
      }

      await createMutation.mutateAsync(payload);
    } catch (mutationError) {
      alert(mutationError?.response?.data?.message || "No se pudo guardar el subgrupo.");
    }
  };

  const handleDelete = async (event) => {
    event.preventDefault();
    const formData = new FormData(event.currentTarget);
    const autorizacion = String(formData.get("autorizacion") || "").trim();

    try {
      const itemId = getSubgroupId(selectedItem);
      await deleteMutation.mutateAsync({ id: itemId, autorizacion });
    } catch (mutationError) {
      alert(mutationError?.response?.data?.message || "No se pudo eliminar el subgrupo.");
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
                  <FolderKanban className="size-5" />
                </div>
                <CardTitle className="text-2xl tracking-[-0.04em]">Sub grupos</CardTitle>
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
                <Loader2 className="mr-2 size-4 animate-spin" /> Cargando subgrupos...
              </div>
            )}

            {isError && (
              <Alert variant="destructive" className="rounded-2xl">
                <AlertDescription>
                  {error?.response?.data?.message || "No se pudieron cargar los subgrupos."}
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
                        <th className="px-5 py-4 font-semibold text-foreground">Codigo</th>
                        <th className="px-5 py-4 font-semibold text-foreground">Descripción</th>
                        <th className="px-5 py-4 font-semibold text-foreground">Grupo</th>
                        <th className="px-5 py-4 font-semibold text-foreground">Estado</th>
                        <th className="px-5 py-4 font-semibold text-foreground text-center">Opciones</th>
                      </tr>
                    </thead>
                    <tbody>
                      {items.map((item, index) => {
                        const code = getSubgroupCode(item) || "-";
                        const description = getSubgroupDescription(item) || "-";
                        const status = item.status || item.estado || "DC";
                        const statusLabelText = item.status_label || (status === "AC" ? "ACTIVO" : "INACTIVO");
                        
                        // Resolve group name
                        const groupId = getGroupId(item);
                        const groupName = item.nombre_grupo || item.group?.name || item.grupo?.nombre_grupo ||
                                          getGroupOptionName(groups.find((g) => String(getGroupOptionId(g)) === String(groupId)));

                        return (
                          <tr key={getSubgroupId(item) || index} className={index < items.length - 1 ? "border-b border-border/60" : ""}>
                            <td className="px-5 py-4 align-top text-foreground">{index + 1}</td>
                            <td className="px-5 py-4 align-top text-foreground font-medium">{code}</td>
                            <td className="px-5 py-4 align-top text-foreground">{description}</td>
                            <td className="px-5 py-4 align-top text-muted-foreground">{groupName}</td>
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
                                  <DropdownMenuItem className="flex items-center gap-2 rounded-xl px-3 py-2 cursor-pointer" onClick={() => openEdit(item)}>
                                    <Pencil className="h-4 w-4 text-muted-foreground" />
                                    <span>Editar</span>
                                  </DropdownMenuItem>
                                  <DropdownMenuItem className="flex items-center gap-2 rounded-xl px-3 py-2 cursor-pointer text-destructive" onClick={() => openDelete(item)}>
                                    <Trash2 className="h-4 w-4" />
                                    <span>Eliminar</span>
                                  </DropdownMenuItem>
                                </DropdownMenuContent>
                              </DropdownMenu>
                            </td>
                          </tr>
                        );
                      })}
                      {items.length === 0 && (
                        <tr>
                          <td colSpan={5} className="px-5 py-8 text-center text-muted-foreground">
                            No se encontraron subgrupos.
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
                    {getSubgroupId(selectedItem) ? "Editar subgrupo" : "Registrar nuevo subgrupo"}
                  </CardTitle>
                  <Button variant="ghost" size="icon-sm" className="rounded-full" onClick={closeForm}>
                    <X />
                  </Button>
                </div>
              </CardHeader>
              <CardContent className="p-5 sm:p-6">
                {selectedItem && (
                  <form className="flex flex-col gap-5" onSubmit={handleSubmit}>
                    <div className="grid gap-5 sm:grid-cols-2">
                      <div className="flex flex-col gap-2">
                        <Label htmlFor="id_grupo" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">Grupo</Label>
                        <select 
                          id="id_grupo" 
                          name="id_grupo" 
                          defaultValue={getGroupId(selectedItem)} 
                          className="h-12 rounded-2xl border border-border/80 bg-background/90 px-4" 
                          required
                        >
                          <option value="">Seleccionar grupo</option>
                          {groups.map(g => (
                            <option key={getGroupOptionId(g)} value={getGroupOptionId(g)}>{getGroupOptionName(g)}</option>
                          ))}
                        </select>
                      </div>
                      <div className="flex flex-col gap-2">
                        <Label htmlFor="codigo_subgrupo" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">Codigo</Label>
                        <Input 
                          id="codigo_subgrupo" 
                          name="codigo_subgrupo" 
                          defaultValue={getSubgroupCode(selectedItem)} 
                          className="h-12 rounded-2xl border-border/80 bg-background/90" 
                          required 
                        />
                      </div>
                      <div className="flex flex-col gap-2 sm:col-span-2">
                        <Label htmlFor="nombre_subgrupo" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">Descripción</Label>
                        <Input 
                          id="nombre_subgrupo" 
                          name="nombre_subgrupo" 
                          defaultValue={getSubgroupDescription(selectedItem)} 
                          className="h-12 rounded-2xl border-border/80 bg-background/90" 
                          required 
                        />
                      </div>
                      <div className="flex flex-col gap-2 sm:col-span-2">
                        <Label htmlFor="estado" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">Estado</Label>
                        <select id="estado" name="estado" defaultValue={selectedItem.status || selectedItem.estado || "AC"} className="h-12 rounded-2xl border border-border/80 bg-background/90 px-4">
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
                <h3 className="text-lg font-semibold text-foreground">Eliminar subgrupo</h3>
                <p className="text-sm text-muted-foreground">Ingresa la autorizacion aprobada para eliminar <strong>{getSubgroupDescription(selectedItem)}</strong>.</p>
              </div>
              <Button variant="ghost" size="icon-sm" className="rounded-full" onClick={closeDelete}>
                <X className="size-4" />
              </Button>
            </div>

            <form className="mt-6 space-y-4" onSubmit={handleDelete}>
              <div className="space-y-2">
                <Label htmlFor="autorizacion">Nro. autorizacion</Label>
                <Input id="autorizacion" name="autorizacion" placeholder="Ej. AUT-2026-001" required />
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
