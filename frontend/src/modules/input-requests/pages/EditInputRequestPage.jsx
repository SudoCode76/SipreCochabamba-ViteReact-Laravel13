import { useMemo, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";
import { useMutation, useQuery } from "@tanstack/react-query";
import { ArrowLeft, FileText, Loader2, MapPin, Upload } from "lucide-react";

import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { useToast } from "@/components/ui/toast";
import apiClient from "@/lib/api/client";
import { storageUrl } from "@/modules/input-requests/utils/storage-url";

function buildInitialForm(request) {
  return {
    descripcion: request?.descripcion ?? "",
    precio: request?.precio != null ? String(request.precio) : "",
    id_unidad_medida: request?.unidad_medida_id != null ? String(request.unidad_medida_id) : "",
    id_tipo: request?.tipo_id != null ? String(request.tipo_id) : "",
    ubicacion: request?.ubicacion ?? "",
    justificacion: request?.justificacion ?? "",
    usuario_solicitante: request?.usuario_solicitante != null ? String(request.usuario_solicitante) : "",
    estado_aprobacion: request?.approval_status ?? request?.estado_aprobacion ?? "PD",
  };
}

function QuoteCard({ title, file, replacementName, onChange }) {
  const fileUrl = storageUrl(file);

  return (
    <div className="flex flex-col items-center gap-4 rounded-[28px] border border-border/70 bg-background/80 px-6 py-7 text-center">
      <h3 className="text-lg font-semibold tracking-[-0.03em] text-foreground">{title}</h3>
      <div className="flex h-28 w-28 items-center justify-center rounded-full bg-emerald-100 text-emerald-700">
        <FileText className="size-12" />
      </div>
      <div className="space-y-2 text-sm">
        {fileUrl ? (
          <a href={fileUrl} target="_blank" rel="noreferrer" className="font-medium text-emerald-700 hover:underline">
            Ver archivo actual
          </a>
        ) : (
          <p className="text-muted-foreground">Sin archivo cargado</p>
        )}
        {onChange ? (
          <label className="inline-flex cursor-pointer items-center gap-2 rounded-full border border-border/80 px-4 py-2 text-sm hover:bg-muted/40">
            <Upload className="size-4" />
            Reemplazar archivo
            <input type="file" className="hidden" accept=".pdf,.doc,.docx,.xls,.xlsx" onChange={onChange} />
          </label>
        ) : null}
        {replacementName ? <p className="text-xs text-muted-foreground">Nuevo: {replacementName}</p> : null}
      </div>
    </div>
  );
}

export default function EditInputRequestPage() {
  const navigate = useNavigate();
  const toast = useToast();
  const { requestId } = useParams();
  const [draftFormData, setFormData] = useState(null);
  const [replaceQuotes, setReplaceQuotes] = useState(false);
  const [archivoValid, setArchivoValid] = useState(null);
  const [archivoPropuesto1, setArchivoPropuesto1] = useState(null);
  const [archivoPropuesto2, setArchivoPropuesto2] = useState(null);
  const [error, setError] = useState(null);

  const contextQuery = useQuery({
    queryKey: ["input-requests-context"],
    queryFn: async () => {
      const response = await apiClient.get("/v1/input-requests/context");
      return response.data;
    },
  });

  const detailQuery = useQuery({
    queryKey: ["input-request", requestId],
    queryFn: async () => {
      const response = await apiClient.get(`/v1/input-requests/${requestId}`);
      return response.data;
    },
    enabled: Boolean(requestId),
  });

  const request = detailQuery.data?.data?.request ?? null;
  const initialFormData = useMemo(() => (request ? buildInitialForm(request) : null), [request]);
  const formData = draftFormData ?? initialFormData;

  const types = contextQuery.data?.data?.types ?? [];
  const unitMeasures = contextQuery.data?.data?.unit_measures ?? [];

  const mutation = useMutation({
    mutationFn: async () => {
      const payload = new FormData();
      payload.append("descripcion", formData.descripcion);
      payload.append("precio", formData.precio);
      payload.append("unidad_medida", formData.id_unidad_medida);
      payload.append("tipo", formData.id_tipo);
      payload.append("ubicacion", formData.ubicacion);
      payload.append("justificacion", formData.justificacion);
      payload.append("usuario_solicitante", formData.usuario_solicitante);
      payload.append("estado_aprobacion", formData.estado_aprobacion);
      payload.append("adj", replaceQuotes ? "SI" : "NO");

      if (replaceQuotes && archivoValid) payload.append("valido", archivoValid);
      if (replaceQuotes && archivoPropuesto1) payload.append("propuesto_1", archivoPropuesto1);
      if (replaceQuotes && archivoPropuesto2) payload.append("propuesto_2", archivoPropuesto2);

      return apiClient.put(`/v1/input-requests/${requestId}`, payload);
    },
    onSuccess: () => {
      toast.success("Solicitud actualizada correctamente.");
      navigate("/Listar Solicitud de Insumo");
    },
    onError: (err) => {
      setError(err.response?.data?.message || "Error al actualizar la solicitud.");
    },
  });

  const handleChange = (field, value) => {
    setFormData((current) => ({ ...(current ?? formData), [field]: value }));
  };

  const handleSubmit = (event) => {
    event.preventDefault();
    setError(null);
    mutation.mutate();
  };

  if (contextQuery.isLoading || detailQuery.isLoading || !formData) {
    return (
      <div className="flex items-center justify-center p-8 text-muted-foreground">
        <Loader2 className="mr-2 size-4 animate-spin" /> Cargando solicitud...
      </div>
    );
  }

  if (contextQuery.isError || detailQuery.isError) {
    return (
      <Alert variant="destructive" className="rounded-2xl">
        <AlertDescription>
          {contextQuery.error?.response?.data?.message || detailQuery.error?.response?.data?.message || "No se pudo cargar la solicitud."}
        </AlertDescription>
      </Alert>
    );
  }

  return (
    <div className="flex flex-col gap-6 animate-in fade-in duration-500">
      <div className="flex items-center gap-4">
        <Button variant="ghost" size="sm" onClick={() => navigate("/Listar Solicitud de Insumo")} className="rounded-full">
          <ArrowLeft className="mr-2 h-4 w-4" />
          Volver
        </Button>
      </div>

      <Card className="mx-auto w-full max-w-6xl border border-border/70 bg-white/86 shadow-[0_24px_90px_rgba(15,23,42,0.08)]">
        <CardHeader className="gap-2 border-b border-border/70 bg-muted/25 pb-4">
          <div className="flex items-center gap-3">
            <div className="flex size-11 items-center justify-center rounded-2xl bg-sky-600 text-white">
              <FileText className="size-5" />
            </div>
            <CardTitle className="text-2xl tracking-[-0.04em]">Editar Solicitud</CardTitle>
          </div>
        </CardHeader>

        <CardContent className="space-y-8 p-6">
          {error ? (
            <Alert variant="destructive" className="rounded-2xl">
              <AlertDescription>{error}</AlertDescription>
            </Alert>
          ) : null}

          <form onSubmit={handleSubmit} className="space-y-8">
            <section className="space-y-3">
              <Label className="flex items-center gap-2 text-lg font-semibold text-foreground">
                <MapPin className="h-4 w-4" />
                Ubicación
              </Label>
              <div className="flex h-72 items-center justify-center rounded-[28px] border border-border/80 bg-muted/30 text-muted-foreground">
                Mapa interactivo - Seleccione la ubicación
              </div>
            </section>

            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
              <div className="space-y-2">
                <Label htmlFor="descripcion">Descripción</Label>
                <Input id="descripcion" value={formData.descripcion} onChange={(e) => handleChange("descripcion", e.target.value)} required />
              </div>
              <div className="space-y-2">
                <Label htmlFor="precio">Precio</Label>
                <Input id="precio" type="number" step="0.01" value={formData.precio} onChange={(e) => handleChange("precio", e.target.value)} required />
              </div>
              <div className="space-y-2 xl:col-span-2">
                <Label htmlFor="id_unidad_medida">Unidad de Medida</Label>
                <select
                  id="id_unidad_medida"
                  value={formData.id_unidad_medida}
                  onChange={(e) => handleChange("id_unidad_medida", e.target.value)}
                  className="h-10 w-full rounded-xl border border-border/80 px-3"
                  required
                >
                  <option value="">Seleccione una unidad</option>
                  {unitMeasures.map((unit) => (
                    <option key={unit.id_unidad_medida} value={unit.id_unidad_medida}>
                      {unit.descripcion} ({unit.abreviatura})
                    </option>
                  ))}
                </select>
              </div>
            </div>

            <div className="grid gap-4 md:grid-cols-2">
              <div className="space-y-2">
                <Label htmlFor="ubicacion">Ubicación</Label>
                <Input id="ubicacion" value={formData.ubicacion} onChange={(e) => handleChange("ubicacion", e.target.value)} required />
              </div>
              <div className="space-y-2">
                <Label htmlFor="id_tipo">Tipo de Insumo</Label>
                <select
                  id="id_tipo"
                  value={formData.id_tipo}
                  onChange={(e) => handleChange("id_tipo", e.target.value)}
                  className="h-10 w-full rounded-xl border border-border/80 px-3"
                  required
                >
                  <option value="">Seleccione un tipo</option>
                  {types.map((type) => (
                    <option key={type.id_tipo} value={type.id_tipo}>
                      {type.descripcion}
                    </option>
                  ))}
                </select>
              </div>
            </div>

            <div className="space-y-2">
              <Label htmlFor="justificacion">Justificación</Label>
              <textarea
                id="justificacion"
                value={formData.justificacion}
                onChange={(e) => handleChange("justificacion", e.target.value)}
                className="min-h-24 w-full rounded-xl border border-border/80 px-3 py-2"
                required
              />
            </div>

            <section className="space-y-4">
              <div className="flex items-center gap-3">
                <input
                  id="replaceQuotes"
                  type="checkbox"
                  checked={replaceQuotes}
                  onChange={(e) => setReplaceQuotes(e.target.checked)}
                  className="h-5 w-5 rounded border border-border/80"
                />
                <Label htmlFor="replaceQuotes" className="text-base font-semibold">
                  Cambiar cotización
                </Label>
              </div>

              <div className="grid gap-5 lg:grid-cols-3">
                <QuoteCard
                  title="Cotización Válida"
                  file={request.archivo}
                  replacementName={archivoValid?.name}
                  onChange={replaceQuotes ? (e) => setArchivoValid(e.target.files?.[0] ?? null) : null}
                />
                <QuoteCard
                  title="Cotización Propuesta 1"
                  file={request.archivo1}
                  replacementName={archivoPropuesto1?.name}
                  onChange={replaceQuotes ? (e) => setArchivoPropuesto1(e.target.files?.[0] ?? null) : null}
                />
                <QuoteCard
                  title="Cotización Propuesta 2"
                  file={request.archivo2}
                  replacementName={archivoPropuesto2?.name}
                  onChange={replaceQuotes ? (e) => setArchivoPropuesto2(e.target.files?.[0] ?? null) : null}
                />
              </div>
            </section>

            <div className="flex justify-center pt-2">
              <Button type="submit" className="min-w-48 bg-sky-600 hover:bg-sky-700" disabled={mutation.isPending}>
                {mutation.isPending ? (
                  <>
                    <Loader2 className="mr-2 size-4 animate-spin" />
                    Guardando Cambios
                  </>
                ) : (
                  "Guardar Cambios"
                )}
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>
    </div>
  );
}
