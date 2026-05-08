import { useState, useMemo } from "react";
import { useQuery } from "@tanstack/react-query";
import { Package, Search, ChevronLeft, ChevronRight } from "lucide-react";

import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { projectService } from "../services/project.service";

const statusClass = {
  AC: "bg-emerald-600 text-white",
  DC: "bg-rose-600 text-white",
};

const approvalClass = {
  PD: "bg-amber-500 text-white",
  RV: "bg-blue-500 text-white",
  AP: "bg-emerald-600 text-white",
};

export default function ProjectsPage() {
  const [perPage, setPerPage] = useState(15);
  const [search, setSearch] = useState("");
  const [searchQuery, setSearchQuery] = useState("");
  const [page, setPage] = useState(1);

  const { data, isLoading, isError, error, isFetching } = useQuery({
    queryKey: ["projects", { page, perPage, search }],
    queryFn: () => projectService.list({ page, perPage, search }),
    placeholderData: (previousData) => previousData,
  });

  const projects = data?.data?.items ?? [];
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
    <div className="flex flex-col gap-6 animate-in fade-in duration-500">
      <Card className="border border-border/70 bg-white/86 shadow-[0_24px_90px_rgba(15,23,42,0.08)]">
        <CardHeader className="gap-4 border-b border-border/70 bg-muted/25">
          <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div className="flex items-center gap-3">
              <div className="flex size-11 items-center justify-center rounded-2xl bg-sky-600 text-white">
                <Package className="size-5" />
              </div>
              <div>
                <CardTitle className="text-2xl tracking-[-0.04em]">Proyectos</CardTitle>
              </div>
            </div>
          </div>
        </CardHeader>

        <CardContent className="flex flex-col gap-6 p-5 sm:p-6">
          <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div className="flex items-end gap-3">
              <div className="flex flex-col gap-2">
                <label className="text-xs font-medium text-muted-foreground">Buscar</label>
                <form onSubmit={handleSearchSubmit} className="flex gap-2">
                  <Input
                    placeholder="Buscar proyecto..."
                    className="h-9 w-64 rounded-xl border-border/80"
                    value={searchQuery}
                    onChange={(e) => setSearchQuery(e.target.value)}
                  />
                  <Button type="submit" variant="ghost" size="sm" className="h-9 rounded-xl">
                    <Search className="size-4" />
                  </Button>
                </form>
              </div>
            </div>

            <div className="flex items-center gap-3">
              <label className="text-xs font-medium text-muted-foreground">Mostrar</label>
              <select
                value={perPage}
                onChange={handlePerPageChange}
                className="h-9 rounded-xl border border-border/80 px-3 text-sm"
              >
                <option value={10}>10</option>
                <option value={15}>15</option>
                <option value={25}>25</option>
                <option value={50}>50</option>
              </select>
            </div>
          </div>

          {isLoading && (
            <div className="flex items-center justify-center rounded-2xl border border-border/70 bg-background/70 p-8 text-muted-foreground">
              Cargando proyectos...
            </div>
          )}

          {isError && (
            <div className="rounded-2xl border border-destructive/50 bg-destructive/10 p-4 text-destructive">
              {error?.response?.data?.message || "Error al cargar proyectos"}
            </div>
          )}

          {!isLoading && !isError && (
            <div className="overflow-hidden rounded-[28px] border border-border/70 bg-background/90">
              <div className="overflow-x-auto max-h-[500px] overflow-y-auto">
                <table className="min-w-full border-collapse text-sm">
                  <thead>
                    <tr className="border-b border-border/70 bg-muted/30 text-left">
                      <th className="px-5 py-4 font-semibold text-foreground">N°</th>
                      <th className="px-5 py-4 font-semibold text-foreground">Proyecto</th>
                      <th className="px-5 py-4 font-semibold text-foreground">Ubicación</th>
                      <th className="px-5 py-4 font-semibold text-foreground">Fecha</th>
                      <th className="px-5 py-4 font-semibold text-foreground">Responsable</th>
                      <th className="px-5 py-4 font-semibold text-foreground">Solicitante</th>
                      <th className="px-5 py-4 font-semibold text-foreground">Condición</th>
                      <th className="px-5 py-4 font-semibold text-foreground">Observación</th>
                      <th className="px-5 py-4 font-semibold text-foreground">Estado</th>
                      <th className="px-5 py-4 font-semibold text-foreground text-center">Opciones</th>
                    </tr>
                  </thead>
                  <tbody>
                    {projects.map((project, index) => (
                      <tr key={project.id_proyecto} className={index < projects.length - 1 ? "border-b border-border/60" : ""}>
                        <td className="px-5 py-4 align-top text-foreground">
                          {index + 1}
                        </td>
                        <td className="px-5 py-4 align-top text-foreground max-w-[250px]">
                          <div className="truncate">{project.nombre_proyecto}</div>
                        </td>
                        <td className="px-5 py-4 align-top text-muted-foreground max-w-[180px]">
                          <div className="truncate">{project.ubicacion}</div>
                        </td>
                        <td className="px-5 py-4 align-top text-muted-foreground">
                          {project.fecha}
                        </td>
                        <td className="px-5 py-4 align-top text-foreground">
                          {project.responsable_nombre || project.responsable}
                        </td>
                        <td className="px-5 py-4 align-top text-foreground">
                          {project.solicitante_nombre || project.solicitante}
                        </td>
                        <td className="px-5 py-4 align-top">
                          <Badge className={`rounded-full px-3 py-1 text-[11px] uppercase tracking-[0.18em] ${approvalClass[project.aprobado] || "bg-slate-500 text-white"}`}>
                            {project.aprobado === "PD" ? "PENDIENTE" : project.aprobado === "RV" ? "REVISADO" : "APROBADO"}
                          </Badge>
                        </td>
                        <td className="px-5 py-4 align-top text-muted-foreground max-w-[150px]">
                          <div className="truncate">{project.observaciones || "-"}</div>
                        </td>
                        <td className="px-5 py-4 align-top">
                          <Badge className={`rounded-full px-3 py-1 text-[11px] uppercase tracking-[0.18em] ${statusClass[project.estado] || "bg-slate-500 text-white"}`}>
                            {project.estado === "AC" ? "ACTIVO" : "INACTIVO"}
                          </Badge>
                        </td>
                        <td className="px-5 py-4 align-top text-center">
                          <Button variant="ghost" size="sm" className="h-8 w-8 p-0 rounded-full">
                            •
                          </Button>
                        </td>
                      </tr>
                    ))}
                    {projects.length === 0 && (
                      <tr>
                        <td colSpan={10} className="px-5 py-8 text-center text-muted-foreground">
                          No se encontraron proyectos.
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
  );
}