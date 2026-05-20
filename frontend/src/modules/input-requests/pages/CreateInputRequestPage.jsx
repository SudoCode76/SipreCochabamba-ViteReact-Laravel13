import { useState } from "react";
import { useNavigate } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";
import { FileText, Loader2, ArrowLeft, MapPin, Upload } from "lucide-react";

import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { authService } from "@/modules/auth/services/auth.service";
import apiClient from "@/lib/api/client";

function getCurrentUserId(profile) {
  return profile?.data?.user?.id
    || profile?.data?.user?.id_usuario
    || profile?.data?.user?.usuario_id
    || profile?.user?.id
    || profile?.user?.id_usuario
    || profile?.id
    || profile?.id_usuario
    || null;
}

export default function CreateInputRequestPage() {
  const navigate = useNavigate();
  const [formData, setFormData] = useState({
    descripcion: "",
    precio: "",
    id_unidad_medida: "",
    id_tipo: "",
    ubicacion: "",
    justificacion: "",
    distrito: "",
    zona: "",
    otb: "",
  });
  const [error, setError] = useState(null);
  const [saving, setSaving] = useState(false);
  const [archivoValid, setArchivoValid] = useState(null);
  const [archivoPropuesto1, setArchivoPropuesto1] = useState(null);
  const [archivoPropuesto2, setArchivoPropuesto2] = useState(null);

  const { data: contextData, isLoading: contextLoading } = useQuery({
    queryKey: ["input-requests-context"],
    queryFn: async () => {
      const response = await apiClient.get("/v1/input-requests/context");
      return response.data;
    },
  });

  const profileQuery = useQuery({
    queryKey: ["input-requests-create-profile"],
    queryFn: authService.getProfile,
    retry: false,
  });

  const types = contextData?.data?.types ?? [];
  const unitMeasures = contextData?.data?.unit_measures ?? [];
  const currentUserId = getCurrentUserId(profileQuery.data);

  const handleChange = (field, value) => {
    setFormData((prev) => ({ ...prev, [field]: value }));
  };

  const handleFileChange = (e, setFile) => {
    if (e.target.files && e.target.files[0]) {
      setFile(e.target.files[0]);
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError(null);
    setSaving(true);

    try {
      if (!currentUserId) {
        throw new Error("No se pudo identificar al usuario actual.");
      }

      const payload = {
        descripcion: formData.descripcion,
        precio: formData.precio,
        unidad_medida: formData.id_unidad_medida,
        tipo: formData.id_tipo,
        ubicacion: formData.ubicacion,
        justificacion: formData.justificacion,
        usuario_solicitante: String(currentUserId),
      };

      const hasFiles = Boolean(archivoValid || archivoPropuesto1 || archivoPropuesto2);

      if (hasFiles) {
        const formDataToSend = new FormData();

        Object.entries(payload).forEach(([key, value]) => {
          formDataToSend.append(key, value);
        });

        if (archivoValid) formDataToSend.append("valido", archivoValid);
        if (archivoPropuesto1) formDataToSend.append("propuesto_1", archivoPropuesto1);
        if (archivoPropuesto2) formDataToSend.append("propuesto_2", archivoPropuesto2);

        await apiClient.post("/v1/input-requests", formDataToSend);
      } else {
        await apiClient.post("/v1/input-requests", payload);
      }

      navigate("/Listar Solicitud de Insumo");
    } catch (err) {
      const firstFieldError = err.response?.data?.errors
        ? Object.values(err.response.data.errors).flat().find(Boolean)
        : null;
      setError(firstFieldError || err.response?.data?.message || err.message || "Error al crear la solicitud");
    } finally {
      setSaving(false);
    }
  };

  if (contextLoading || profileQuery.isLoading) {
    return (
      <div className="flex items-center justify-center p-8">
        <Loader2 className="animate-spin" />
      </div>
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

      <Card className="border border-border/70 bg-white/86 shadow-[0_24px_90px_rgba(15,23,42,0.08)] max-w-4xl mx-auto w-full">
        <CardHeader className="gap-2 border-b border-border/70 bg-muted/25 pb-4">
          <div className="flex items-center gap-3">
            <div className="flex size-11 items-center justify-center rounded-2xl bg-sky-600 text-white">
              <FileText className="size-5" />
            </div>
            <CardTitle className="text-xl tracking-[-0.04em]">Crear Solicitud de Insumo</CardTitle>
          </div>
        </CardHeader>

        <CardContent className="p-6">
          {error && (
            <div className="mb-4 p-3 rounded-xl bg-red-50 text-red-600 text-sm">
              {error}
            </div>
          )}

          <form onSubmit={handleSubmit} className="space-y-6">
            <div className="space-y-2">
              <Label className="flex items-center gap-2">
                <MapPin className="h-4 w-4" />
                Ubicación en el Mapa
              </Label>
              <div className="h-48 rounded-xl border border-border/80 bg-muted/30 flex items-center justify-center">
                <p className="text-muted-foreground text-sm">Mapa interactivo - Seleccione la ubicación</p>
              </div>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div className="space-y-2">
                <Label htmlFor="distrito">Distrito</Label>
                <Input
                  id="distrito"
                  value={formData.distrito}
                  onChange={(e) => handleChange("distrito", e.target.value)}
                  placeholder="Distrito"
                />
              </div>
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
              <Label htmlFor="ubicacion">Ubicación</Label>
              <Input
                id="ubicacion"
                value={formData.ubicacion}
                onChange={(e) => handleChange("ubicacion", e.target.value)}
                placeholder="Ingrese la ubicación específica"
              />
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label htmlFor="descripcion">Descripción *</Label>
                <Input
                  id="descripcion"
                  value={formData.descripcion}
                  onChange={(e) => handleChange("descripcion", e.target.value)}
                  placeholder="Ingrese la descripción del insumo"
                  required
                />
              </div>

              <div className="space-y-2">
                <Label htmlFor="precio">Precio *</Label>
                <Input
                  id="precio"
                  type="number"
                  step="0.01"
                  value={formData.precio}
                  onChange={(e) => {
                    const val = e.target.value;
                    if (val.length <= 5) handleChange("precio", val);
                  }}
                  placeholder="0.00"
                  required
                />
              </div>
            </div>

            <div className="space-y-2">
              <Label htmlFor="id_unidad_medida">Unidad de Medida *</Label>
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

            <div className="space-y-2">
              <Label htmlFor="id_tipo">Tipo *</Label>
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

            <div className="space-y-2">
              <Label htmlFor="justificacion">Justificación *</Label>
              <textarea
                id="justificacion"
                value={formData.justificacion}
                onChange={(e) => handleChange("justificacion", e.target.value)}
                placeholder="Ingrese la justificación"
                className="w-full h-24 rounded-xl border border-border/80 px-3 py-2"
                required
              />
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label>Cotización Válida</Label>
                <div className="flex items-center gap-2">
                  <label className="flex items-center gap-2 px-4 py-2 rounded-xl border border-border/80 cursor-pointer hover:bg-muted/30">
                    <Upload className="h-4 w-4" />
                    <span className="text-sm">Seleccionar archivo</span>
                    <input
                      type="file"
                      className="hidden"
                      accept=".pdf,.doc,.docx,.xls,.xlsx"
                      onChange={(e) => handleFileChange(e, setArchivoValid)}
                    />
                  </label>
                </div>
                {archivoValid && <span className="text-sm text-muted-foreground">{archivoValid.name}</span>}
              </div>

              <div className="space-y-2">
                <Label>Cotización Propuesta 1</Label>
                <div className="flex items-center gap-2">
                  <label className="flex items-center gap-2 px-4 py-2 rounded-xl border border-border/80 cursor-pointer hover:bg-muted/30">
                    <Upload className="h-4 w-4" />
                    <span className="text-sm">Seleccionar archivo</span>
                    <input
                      type="file"
                      className="hidden"
                      accept=".pdf,.doc,.docx,.xls,.xlsx"
                      onChange={(e) => handleFileChange(e, setArchivoPropuesto1)}
                    />
                  </label>
                </div>
                {archivoPropuesto1 && <span className="text-sm text-muted-foreground">{archivoPropuesto1.name}</span>}
              </div>

              <div className="space-y-2 md:col-span-2">
                <Label>Cotización Propuesta 2</Label>
                <div className="flex items-center gap-2">
                  <label className="flex items-center gap-2 px-4 py-2 rounded-xl border border-border/80 cursor-pointer hover:bg-muted/30">
                    <Upload className="h-4 w-4" />
                    <span className="text-sm">Seleccionar archivo</span>
                    <input
                      type="file"
                      className="hidden"
                      accept=".pdf,.doc,.docx,.xls,.xlsx"
                      onChange={(e) => handleFileChange(e, setArchivoPropuesto2)}
                    />
                  </label>
                </div>
                {archivoPropuesto2 && <span className="text-sm text-muted-foreground">{archivoPropuesto2.name}</span>}
              </div>
            </div>

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
                onClick={() => navigate("/Listar Solicitud de Insumo")}
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
