import { useMemo, useState } from "react";
import { ChevronLeft, ChevronRight, Loader2, Package, Search, MoreHorizontal, Pencil, Package2, Users, Wrench, FileText, TrendingUp, RefreshCw, BarChart3, Hammer } from "lucide-react";
import { useQuery } from "@tanstack/react-query";

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
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
  DialogFooter,
} from "@/components/ui/dialog";
import { itemsService } from "@/modules/dashboard/services/items.service";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";

const statusLabel = {
  AC: "HABILITADO",
  DC: "INHABILITADO",
};

const statusClass = {
  AC: "bg-emerald-600 text-white",
  DC: "bg-rose-600 text-white",
};

export default function ItemsPage() {
  const [perPage, setPerPage] = useState(10);
  const [search, setSearch] = useState("");
  const [searchQuery, setSearchQuery] = useState("");
  const [page, setPage] = useState(1);
  const [editOpen, setEditOpen] = useState(false);
  const [editItem, setEditItem] = useState(null);

  const handleEdit = (item) => {
    setEditItem(item);
    setEditOpen(true);
  }

  const { data, isLoading, isError, error, isFetching } = useQuery({
    queryKey: ["items", { page, perPage, search }],
    queryFn: () => itemsService.list({ page, perPage, search }),
    placeholderData: (previousData) => previousData,
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

  const handleSearchSubmit = (event) => {
    event.preventDefault();
    setPage(1);
    setSearch(searchQuery.trim());
  };

  const handlePerPageChange = (event) => {
    setPerPage(Number(event.target.value));
    setPage(1);
  };

  return (
    <div className={`flex flex-col gap-6 animate-in fade-in duration-500 ${editOpen ? 'blur-sm' : ''}`}>
      <Card className="border border-border/70 bg-white/86 shadow-[0_24px_90px_rgba(15,23,42,0.08)] backdrop-blur">
        <CardHeader className="gap-4 border-b border-border/70 bg-muted/25">
          <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div className="flex items-center gap-3">
              <div className="flex size-11 items-center justify-center rounded-2xl bg-foreground text-background">
                <Package className="size-5" />
              </div>
              <div>
                <CardTitle className="text-2xl tracking-[-0.04em]">Items</CardTitle>
                <CardDescription>
                  Catálogo de items con búsqueda y paginación.
                </CardDescription>
              </div>
            </div>
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
                    {items.map((item, index) => (
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
                          {item.calculated_price !== null ? Number(item.calculated_price).toFixed(2) : "-"}
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
                              {item.available_actions?.edit && (
                                <DropdownMenuItem 
                                  className="flex items-center gap-2 rounded-xl px-3 py-2 cursor-pointer"
                                  onClick={() => handleEdit(item)}
                                >
                                  <Pencil className="h-4 w-4 text-muted-foreground" />
                                  <span>Editar</span>
                                </DropdownMenuItem>
                              )}
                              {item.available_actions?.materials && (
                                <DropdownMenuItem className="flex items-center gap-2 rounded-xl px-3 py-2 cursor-pointer">
                                  <Package2 className="h-4 w-4 text-muted-foreground" />
                                  <span>Materiales</span>
                                </DropdownMenuItem>
                              )}
                              {item.available_actions?.labor && (
                                <DropdownMenuItem className="flex items-center gap-2 rounded-xl px-3 py-2 cursor-pointer">
                                  <Users className="h-4 w-4 text-muted-foreground" />
                                  <span>Mano de Obra</span>
                                </DropdownMenuItem>
                              )}
                              {item.available_actions?.machinery && (
                                <DropdownMenuItem className="flex items-center gap-2 rounded-xl px-3 py-2 cursor-pointer">
                                  <Wrench className="h-4 w-4 text-muted-foreground" />
                                  <span>Maquinaria</span>
                                </DropdownMenuItem>
                              )}
                              {item.available_actions?.files && (
                                <DropdownMenuItem className="flex items-center gap-2 rounded-xl px-3 py-2 cursor-pointer">
                                  <FileText className="h-4 w-4 text-muted-foreground" />
                                  <span>Archivos</span>
                                </DropdownMenuItem>
                              )}
                              {(item.available_actions?.price_analysis || item.available_actions?.price_recalculation) && (
                                <>
                                  <DropdownMenuSeparator className="my-1 bg-border/50" />
                                  <DropdownMenuItem className="flex items-center gap-2 rounded-xl px-3 py-2 cursor-pointer">
                                    <TrendingUp className="h-4 w-4 text-muted-foreground" />
                                    <span>Análisis de Precio</span>
                                  </DropdownMenuItem>
                                  {item.available_actions?.price_recalculation && (
                                    <DropdownMenuItem className="flex items-center gap-2 rounded-xl px-3 py-2 cursor-pointer">
                                      <RefreshCw className="h-4 w-4 text-muted-foreground" />
                                      <span>Recalcular Precio</span>
                                    </DropdownMenuItem>
                                  )}
                                </>
                              )}
                              {(item.available_actions?.material_breakdown || item.available_actions?.labor_breakdown || item.available_actions?.tools_breakdown) && (
                                <>
                                  <DropdownMenuSeparator className="my-1 bg-border/50" />
                                  {item.available_actions?.material_breakdown && (
                                    <DropdownMenuItem className="flex items-center gap-2 rounded-xl px-3 py-2 cursor-pointer">
                                      <BarChart3 className="h-4 w-4 text-muted-foreground" />
                                      <span>Desglose Materiales</span>
                                    </DropdownMenuItem>
                                  )}
                                  {item.available_actions?.labor_breakdown && (
                                    <DropdownMenuItem className="flex items-center gap-2 rounded-xl px-3 py-2 cursor-pointer">
                                      <Users className="h-4 w-4 text-muted-foreground" />
                                      <span>Desglose M.O.</span>
                                    </DropdownMenuItem>
                                  )}
                                  {item.available_actions?.tools_breakdown && (
                                    <DropdownMenuItem className="flex items-center gap-2 rounded-xl px-3 py-2 cursor-pointer">
                                      <Hammer className="h-4 w-4 text-muted-foreground" />
                                      <span>Desglose Herramientas</span>
                                    </DropdownMenuItem>
                                  )}
                                  {item.available_actions?.breakdown_recalculation && (
                                    <DropdownMenuItem className="flex items-center gap-2 rounded-xl px-3 py-2 cursor-pointer">
                                      <RefreshCw className="h-4 w-4 text-muted-foreground" />
                                      <span>Recalcular Desglose</span>
                                    </DropdownMenuItem>
                                  )}
                                </>
                              )}
                            </DropdownMenuContent>
                          </DropdownMenu>
                        </td>
                      </tr>
                    ))}
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

      <Dialog open={editOpen} onOpenChange={setEditOpen}>
        <DialogContent className="max-w-lg rounded-2xl">
          <DialogHeader>
            <DialogTitle>Editar Item</DialogTitle>
            <DialogDescription>
              Código: {editItem?.codigo_item}
            </DialogDescription>
          </DialogHeader>

          {editItem && (
            <div className="space-y-4">
              <div className="space-y-2">
                <Label htmlFor="name">Nombre</Label>
                <Input id="name" defaultValue={editItem.name} className="w-full" />
              </div>
              <div className="space-y-2">
                <Label htmlFor="grupo">Grupo</Label>
                <Input id="grupo" defaultValue={editItem.group?.name} className="w-full" disabled />
              </div>
              <div className="space-y-2">
                <Label htmlFor="subgrupo">Subgrupo</Label>
                <Input id="subgrupo" defaultValue={editItem.subgroup?.description} className="w-full" disabled />
              </div>
              <div className="space-y-2">
                <Label htmlFor="precio">Precio</Label>
                <Input id="precio" type="number" defaultValue={editItem.calculated_price} className="w-full" />
              </div>
              <div className="space-y-2">
                <Label htmlFor="unidad">Unidad</Label>
                <Input id="unidad" defaultValue={editItem.unit_measure?.abbreviation} className="w-full" disabled />
              </div>
            </div>
          )}

          <DialogFooter className="mt-4">
            <Button type="submit" className="flex-1">
              Guardar
            </Button>
            <Button type="button" variant="outline" className="flex-1" onClick={() => setEditOpen(false)}>
              Cancelar
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}