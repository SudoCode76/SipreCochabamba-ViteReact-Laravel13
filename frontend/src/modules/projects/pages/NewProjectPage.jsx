import { lazy, Suspense, useState } from "react";
import { useNavigate } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";
import { ArrowLeft, Loader2, Package, MapPin, Copy } from "lucide-react";

import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { useToast } from "@/components/ui/toast";
import ProjectSignatureAccessSection from "../components/ProjectSignatureAccessSection";
import { normalizeProjectCoordinates } from "../lib/project-coordinates";
import { projectService } from "../services/project.service";

const ProjectLocationMap = lazy(() => import("../components/ProjectLocationMap"));

const fieldLabels = {
  nombre_proyecto: "El nombre del proyecto",
  ubicacion: "La especificación de ubicación",
  latitud: "La latitud",
  longitud: "La longitud",
  distrito: "El distrito",
  zona: "La zona",
  otb: "La OTB",
  fecha: "La fecha",
  responsable: "El responsable",
  solicitante: "El solicitante",
  aprobado: "La condición",
  estado: "El estado",
};

export default function NewProjectPage() {
  const navigate = useNavigate();
  const toast = useToast();
  const [formData, setFormData] = useState({
    nombre_proyecto: "",
    ubicacion: "",
    latitud: "-17.389500",
    longitud: "-66.156800",
    distrito: "",
    zona: "",
    subdistrito: "",
    otb: "",
    fecha: new Date().toISOString().split("T")[0],
    responsable: "",
    solicitante: "",
    observaciones: "",
    estado: "AC",
    aprobado: "PD",
  });
  const [creationMode, setCreationMode] = useState("blank");
  const [selectedTemplateId, setSelectedTemplateId] = useState("");
  const [signatureAccess, setSignatureAccess] = useState({ mode: "selected", user_ids: [] });
  const [error, setError] = useState(null);
  const [saving, setSaving] = useState(false);

  const { data, isLoading } = useQuery({
    queryKey: ["project-context"],
    queryFn: projectService.context,
  });

  const { data: templatesData, isFetching: templatesFetching } = useQuery({
    queryKey: ["project-templates-active"],
    queryFn: () => projectService.templates({ perPage: 100, status: "AC" }),
    enabled: creationMode === "template",
    retry: false,
  });

  const responsibleOptions = data?.data?.responsible_options ?? [];
  const requesterOptions = data?.data?.requester_options ?? [];
  const statuses = data?.data?.statuses ?? [];
  const conditions = (data?.data?.conditions ?? []).filter((condition) => condition.code === "PD");
  const templates = templatesData?.data?.items ?? [];
  const people = data?.data?.people ?? [];
  const creatorId = Number(data?.data?.metadata?.creator_user_id || 0);

  const handleChange = (field, value) => {
    document.getElementById(field)?.setCustomValidity("");
    setError(null);
    setFormData((prev) => ({ ...prev, [field]: value }));
  };

  const handleModeChange = (mode) => {
    setCreationMode(mode);
    setSelectedTemplateId("");
    setError(null);
  };

  const handleTemplateChange = (templateId) => {
    setSelectedTemplateId(templateId);
    setError(null);

    const template = templates.find((item) => String(item.id_proyecto) === String(templateId));
    if (!template) {
      return;
    }

    const coordinates = normalizeProjectCoordinates(
      template.latitud,
      template.longitud,
      template.coordinate_system,
    );

    if (!coordinates) {
      setError("La planilla no tiene una ubicación válida. Seleccione el punto manualmente en el mapa.");
    }

    setFormData((current) => ({
      ...current,
      nombre_proyecto: "",
      ubicacion: template.ubicacion || "",
      latitud: coordinates ? coordinates.lat.toFixed(6) : "",
      longitud: coordinates ? coordinates.lng.toFixed(6) : "",
      distrito: template.distrito || "",
      zona: template.zona || "",
      subdistrito: template.subdistrito || "",
      otb: template.otb || "",
      responsable: template.responsable || "",
      solicitante: template.solicitante ? String(template.solicitante) : "",
      observaciones: template.observaciones || "",
      estado: "AC",
      aprobado: "PD",
    }));
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError(null);
    setSaving(true);

    try {
      const payload = {
        ...formData,
        latitud: formData.latitud?.trim() || null,
        longitud: formData.longitud?.trim() || null,
        observaciones: formData.observaciones?.trim() || null,
        signature_access: {
          mode: signatureAccess.mode,
          user_ids: [...new Set((signatureAccess.user_ids.length ? signatureAccess.user_ids : [creatorId]).map(Number).filter(Boolean))],
        },
      };
      delete payload.subdistrito;

      if (creationMode === "template") {
        if (!selectedTemplateId) {
          setError("Seleccione una planilla para crear el proyecto.");
          setSaving(false);
          return;
        }

        await projectService.createFromTemplate(selectedTemplateId, payload);
      } else {
        await projectService.create(payload);
      }

      toast.success("Proyecto creado correctamente.");
      navigate("/Proyecto");
    } catch (err) {
      const fieldErrors = err.response?.data?.errors;
      const [field, messages] = Object.entries(fieldErrors ?? {})[0] ?? [];
      const rawMessage = (Array.isArray(messages) ? messages[0] : messages) || err.response?.data?.message;
      const label = fieldLabels[field] || field?.replaceAll("_", " ") || "El campo";
      const message = rawMessage === "validation.required"
        ? `${label} es obligatorio.`
        : rawMessage?.startsWith("validation.")
          ? `Revise el valor de ${label.toLowerCase()}.`
          : rawMessage || "Error al crear el proyecto";

      setError(message);

      requestAnimationFrame(() => {
        const input = field ? document.getElementById(field) : null;
        if (!input) {
          return;
        }

        input.setCustomValidity(message);
        input.reportValidity();
      });
    } finally {
      setSaving(false);
    }
  };

  if (isLoading) {
    return (
      <div className="flex items-center justify-center p-8">
        <Loader2 className="animate-spin" />
      </div>
    );
  }

  return (
    <div className="flex flex-col gap-6 animate-in fade-in duration-500">
      <div className="flex items-center gap-4">
        <Button variant="ghost" size="sm" onClick={() => navigate("/Proyecto")} className="rounded-full">
          <ArrowLeft className="mr-2 h-4 w-4" />
          Volver
        </Button>
      </div>

      <Card className="border border-border/70 bg-white/86 shadow-[0_24px_90px_rgba(15,23,42,0.08)] max-w-4xl mx-auto w-full">
        <CardHeader className="gap-2 border-b border-border/70 bg-muted/25 pb-4">
          <div className="flex items-center gap-3">
            <div className="flex size-11 items-center justify-center rounded-2xl bg-sky-600 text-white">
              <Package className="size-5" />
            </div>
            <CardTitle className="text-xl tracking-[-0.04em]">Nuevo Proyecto</CardTitle>
          </div>
        </CardHeader>

        <CardContent className="p-6">
          {error && (
            <div className="mb-4 p-3 rounded-xl bg-red-50 text-red-600 text-sm">
              {error}
            </div>
          )}

          <form onSubmit={handleSubmit} className="space-y-6">
            <div className="rounded-2xl border border-border/70 bg-muted/20 p-4">
              <Label className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">Forma de creación</Label>
              <div className="mt-3 grid gap-3 md:grid-cols-2">
                <button
                  type="button"
                  className={`flex items-center gap-3 rounded-2xl border px-4 py-3 text-left transition ${creationMode === "blank" ? "border-sky-500 bg-sky-50 text-sky-900" : "border-border/70 bg-background"}`}
                  onClick={() => handleModeChange("blank")}
                >
                  <Package className="h-5 w-5" />
                  <span className="font-medium">Crear desde cero</span>
                </button>
                <button
                  type="button"
                  className={`flex items-center gap-3 rounded-2xl border px-4 py-3 text-left transition ${creationMode === "template" ? "border-sky-500 bg-sky-50 text-sky-900" : "border-border/70 bg-background"}`}
                  onClick={() => handleModeChange("template")}
                >
                  <Copy className="h-5 w-5" />
                  <span className="font-medium">Usar planilla</span>
                </button>
              </div>

              {creationMode === "template" && (
                <div className="mt-4 space-y-2">
                  <Label htmlFor="project-template">Planilla</Label>
                  <select
                    id="project-template"
                    value={selectedTemplateId}
                    onChange={(event) => handleTemplateChange(event.target.value)}
                    className="h-10 w-full rounded-xl border border-border/80 bg-background px-3"
                  >
                    <option value="">{templatesFetching ? "Cargando planillas..." : "Seleccione una planilla"}</option>
                    {templates.map((template) => (
                      <option key={template.id_proyecto} value={template.id_proyecto}>
                        {template.nombre_proyecto}
                      </option>
                    ))}
                  </select>
                </div>
              )}
            </div>

            <div className="space-y-2">
              <Label className="flex items-center gap-2">
                <MapPin className="h-4 w-4" />
                Ubicación en el Mapa
              </Label>
              <Suspense
                fallback={(
                  <div className="flex h-[360px] items-center justify-center rounded-xl border border-border/80 bg-muted/30 text-sm text-muted-foreground">
                    <Loader2 className="mr-2 size-4 animate-spin" />
                    Cargando mapa
                  </div>
                )}
              >
                <ProjectLocationMap
                  value={formData}
                  onChange={(changes) => setFormData((current) => ({ ...current, ...changes }))}
                  showProjects
                />
              </Suspense>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div className="space-y-2">
                <Label htmlFor="latitud">Latitud</Label>
                <Input
                  id="latitud"
                  value={formData.latitud}
                  onChange={(e) => handleChange("latitud", e.target.value)}
                  placeholder="-17.389500"
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="longitud">Longitud</Label>
                <Input
                  id="longitud"
                  value={formData.longitud}
                  onChange={(e) => handleChange("longitud", e.target.value)}
                  placeholder="-66.156800"
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="distrito">Distrito</Label>
                <Input
                  id="distrito"
                  value={formData.distrito}
                  onChange={(e) => handleChange("distrito", e.target.value)}
                  placeholder="Distrito"
                />
              </div>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div className="space-y-2">
                <Label htmlFor="zona">Zona</Label>
                <Input
                  id="zona"
                  value={formData.zona}
                  onChange={(e) => handleChange("zona", e.target.value)}
                  placeholder="Zona"
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="subdistrito">Sub Distrito</Label>
                <Input
                  id="subdistrito"
                  value={formData.subdistrito}
                  onChange={(e) => handleChange("subdistrito", e.target.value)}
                  placeholder="Sub Distrito"
                  readOnly
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="otb">OTB</Label>
                <Input
                  id="otb"
                  value={formData.otb}
                  onChange={(e) => handleChange("otb", e.target.value)}
                  placeholder="OTB"
                />
              </div>
            </div>

            <div className="space-y-2">
              <Label htmlFor="ubicacion">Especificación de Ubicación</Label>
              <Input
                id="ubicacion"
                value={formData.ubicacion}
                onChange={(e) => handleChange("ubicacion", e.target.value)}
                placeholder="Ingrese la ubicación específica"
              />
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label htmlFor="nombre_proyecto">Proyecto *</Label>
                <Input
                  id="nombre_proyecto"
                  value={formData.nombre_proyecto}
                  onChange={(e) => handleChange("nombre_proyecto", e.target.value)}
                  placeholder="Nombre del proyecto"
                  required
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="fecha">Fecha *</Label>
                <Input
                  id="fecha"
                  type="date"
                  value={formData.fecha}
                  onChange={(e) => handleChange("fecha", e.target.value)}
                  required
                />
              </div>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label htmlFor="responsable">Responsable *</Label>
                <select
                  id="responsable"
                  value={formData.responsable}
                  onChange={(e) => handleChange("responsable", e.target.value)}
                  className="h-10 w-full rounded-xl border border-border/80 px-3"
                  required
                >
                  <option value="">Seleccione un responsable</option>
                  {responsibleOptions.map((opt) => (
                    <option key={opt.id_usuario} value={opt.id_usuario}>
                      {opt.funcionario}
                    </option>
                  ))}
                </select>
              </div>

              <div className="space-y-2">
                <Label htmlFor="solicitante">Solicitante *</Label>
                <select
                  id="solicitante"
                  value={formData.solicitante}
                  onChange={(e) => handleChange("solicitante", e.target.value)}
                  className="h-10 w-full rounded-xl border border-border/80 px-3"
                  required
                >
                  <option value="">Seleccione un solicitante</option>
                  {requesterOptions.map((opt) => (
                    <option key={opt.id_usuario} value={opt.id_usuario}>
                      {opt.funcionario}
                    </option>
                  ))}
                </select>
              </div>
            </div>

            <div className="space-y-2">
              <Label htmlFor="observaciones">Observación</Label>
              <textarea
                id="observaciones"
                value={formData.observaciones}
                onChange={(e) => handleChange("observaciones", e.target.value)}
                placeholder="Ingrese observaciones (opcional)"
                className="w-full h-24 rounded-xl border border-border/80 px-3 py-2"
              />
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label htmlFor="aprobado">Condición *</Label>
                <select
                  id="aprobado"
                  value={formData.aprobado}
                  onChange={(e) => handleChange("aprobado", e.target.value)}
                  className="h-10 w-full rounded-xl border border-border/80 px-3"
                  required
                >
                  {conditions.map((condition) => (
                    <option key={condition.code} value={condition.code}>
                      {condition.label}
                    </option>
                  ))}
                </select>
              </div>

              <div className="space-y-2">
                <Label htmlFor="estado">Estado *</Label>
                <select
                  id="estado"
                  value={formData.estado}
                  onChange={(e) => handleChange("estado", e.target.value)}
                  className="h-10 w-full rounded-xl border border-border/80 px-3"
                  required
                >
                  {statuses.map((status) => (
                    <option key={status.code} value={status.code}>
                      {status.label}
                    </option>
                  ))}
                </select>
              </div>
            </div>

            <ProjectSignatureAccessSection
              people={people}
              creatorId={creatorId}
              draftValue={signatureAccess}
              onDraftChange={setSignatureAccess}
            />

            <div className="flex gap-3 pt-4">
              <Button
                type="submit"
                className="flex-1 bg-sky-600 hover:bg-sky-700"
                disabled={saving}
              >
                {saving ? (
                  <>
                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                    Guardando
                  </>
                ) : (
                  "Guardar"
                )}
              </Button>
              <Button
                type="button"
                variant="outline"
                className="flex-1"
                onClick={() => navigate("/Proyecto")}
                disabled={saving}
              >
                Cancelar
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>
    </div>
  );
}
