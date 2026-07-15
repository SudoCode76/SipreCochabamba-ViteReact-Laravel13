import { forwardRef, useImperativeHandle, useMemo, useRef, useState } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { History, Loader2, Lock, MapPin, RefreshCw, Save } from "lucide-react";

import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Separator } from "@/components/ui/separator";
import { useToast } from "@/components/ui/toast";
import { getProjectApprovalLabel } from "../lib/project-status";
import { projectService } from "../services/project.service";
import ProjectSignatureAccessSection from "./ProjectSignatureAccessSection";
import { UpdatedProjectExitDialog } from "./UpdatedProjectExitDialog";

const emptyForm = {
  nombre_proyecto: "",
  ubicacion: "",
  latitud: "",
  longitud: "",
  distrito: "",
  zona: "",
  otb: "",
  fecha: "",
  responsable: "",
  solicitante: "",
  observaciones: "",
  estado: "AC",
  aprobado: "PD",
};

function MapPreview({ distrito, zona, otb, latitud, longitud }) {
  return (
    <div className="relative overflow-hidden rounded-3xl border border-border/80 bg-[radial-gradient(circle_at_top_left,_rgba(20,184,166,0.14),_transparent_32%),linear-gradient(135deg,rgba(248,250,252,1)_0%,rgba(241,245,249,1)_100%)]">
      <div className="absolute inset-0 bg-[linear-gradient(rgba(15,23,42,0.06)_1px,transparent_1px),linear-gradient(90deg,rgba(15,23,42,0.06)_1px,transparent_1px)] bg-[size:28px_28px] opacity-70" />
      <div className="absolute inset-x-0 top-1/4 h-px bg-teal-500/30" />
      <div className="absolute inset-y-0 left-1/3 w-px bg-sky-500/30" />
      <div className="absolute inset-y-0 right-1/4 w-px bg-teal-500/25" />
      <div className="absolute inset-x-0 bottom-1/3 h-px bg-sky-500/25" />

      <div className="relative flex h-[220px] items-center justify-center sm:h-[260px]">
        <div className="flex flex-col items-center gap-3 text-center">
          <div className="flex size-14 items-center justify-center rounded-full bg-foreground text-background shadow-lg shadow-slate-950/20">
            <MapPin className="size-6" />
          </div>
          <div className="rounded-2xl border border-border/80 bg-background/90 px-4 py-2 shadow-sm backdrop-blur">
            <p className="text-sm font-semibold text-foreground">Ubicación del proyecto</p>
            <p className="text-xs text-muted-foreground">
              {latitud || "-"} / {longitud || "-"}
            </p>
          </div>
        </div>
      </div>

      <div className="absolute left-4 top-4 flex flex-col overflow-hidden rounded-2xl border border-border/80 bg-background/95 shadow-sm">
        <button type="button" className="h-9 w-9 border-b border-border/80 text-xl text-muted-foreground">+</button>
        <button type="button" className="h-9 w-9 text-xl text-muted-foreground">-</button>
      </div>

      <div className="absolute bottom-4 right-4 rounded-full bg-foreground px-3 py-1.5 text-[11px] uppercase tracking-[0.18em] text-background shadow-sm">
        {distrito || "Distrito"} · {zona || "Zona"} · {otb || "OTB"}
      </div>
    </div>
  );
}

function ensureSelectedOption(options, value, label) {
  if (!value) {
    return options;
  }

  const normalizedValue = String(value);

  if (options.some((option) => String(option.id_usuario) === normalizedValue)) {
    return options;
  }

  return [
    {
      id_usuario: normalizedValue,
      funcionario: label || `Usuario ${normalizedValue}`,
    },
    ...options,
  ];
}

const ProjectEditForm = forwardRef(function ProjectEditForm({ projectId, onCancel, onSuccess }, ref) {
  const queryClient = useQueryClient();
  const toast = useToast();
  const [selectedProjectId, setSelectedProjectId] = useState(projectId);
  const [draftFormData, setFormData] = useState(null);
  const [signatureAccessDraft, setSignatureAccessDraft] = useState(null);
  const [error, setError] = useState(null);
  const [saving, setSaving] = useState(false);
  const [versioning, setVersioning] = useState(false);
  const [finalizingVersion, setFinalizingVersion] = useState(false);
  const [updatedExitDialogOpen, setUpdatedExitDialogOpen] = useState(false);
  const updatedExitResolverRef = useRef(null);

  const { data: contextData, isLoading: contextLoading } = useQuery({
    queryKey: ["project-context"],
    queryFn: projectService.context,
  });

  const { data: versionsData, isLoading: versionsLoading } = useQuery({
    queryKey: ["project-versions", projectId],
    queryFn: () => projectService.versions(projectId),
    enabled: Boolean(projectId),
  });

  const { data: projectData, isLoading: projectLoading, isError: projectError } = useQuery({
    queryKey: ["project", selectedProjectId],
    queryFn: () => projectService.show(selectedProjectId),
    enabled: Boolean(selectedProjectId),
  });

  const { data: signatureAccessData } = useQuery({
    queryKey: ["project-signature-access", selectedProjectId],
    queryFn: () => projectService.signatureAccess(selectedProjectId),
    enabled: Boolean(selectedProjectId),
  });

  const project = projectData?.data?.project;
  const versions = versionsData?.data?.items ?? [];
  const isReadOnly = Boolean(project && (!project.is_current_version || project.is_frozen));
  const canManageSignatureAccess = Boolean(signatureAccessData?.data?.can_manage);
  const hasPreviousVersion = versions.length > 1 && Number(project?.version_number || 1) > 1;
  const shouldAskBeforeLeavingUpdatedVersion = Boolean(
    project?.aprobado === "AP"
      && project?.is_current_version
      && !project?.is_frozen
      && hasPreviousVersion,
  );

  const initialFormData = useMemo(() => {
    if (!project) {
      return emptyForm;
    }

    return {
      nombre_proyecto: project.nombre_proyecto ?? "",
      ubicacion: project.ubicacion ?? "",
      latitud: project.latitud ?? "",
      longitud: project.longitud ?? "",
      distrito: project.distrito ?? "",
      zona: project.zona ?? "",
      otb: project.otb ?? "",
      fecha: project.fecha ?? "",
      responsable: String(project.responsable ?? ""),
      solicitante: String(project.solicitante ?? ""),
      observaciones: project.observaciones ?? "",
      estado: project.estado ?? "AC",
      aprobado: project.aprobado ?? "PD",
    };
  }, [project]);
  const formData = draftFormData ?? initialFormData;

  const responsibleOptions = ensureSelectedOption(
    contextData?.data?.responsible_options ?? [],
    formData.responsable,
    project?.nombre_responsable,
  );
  const requesterOptions = ensureSelectedOption(
    contextData?.data?.requester_options ?? [],
    formData.solicitante,
    project?.solicitante_nombre,
  );
  const statuses = contextData?.data?.statuses ?? [];
  const conditions = (contextData?.data?.conditions ?? []).filter((condition) => {
    if (project?.aprobado === "PD") {
      return ["PD", "RV"].includes(condition.code);
    }

    if (project?.aprobado === "AP") {
      return ["AP", "RV"].includes(condition.code);
    }

    return condition.code === "RV";
  });

  const handleChange = (field, value) => {
    setFormData((prev) => ({ ...(prev ?? formData), [field]: value }));
  };

  const handleSubmit = async (event) => {
    event.preventDefault();
    setError(null);
    setSaving(true);

    try {
      if (formData.aprobado === "RV") {
        const confirmed = window.confirm("Al finalizar, esta versión quedará congelada y ya no podrá editarse. ¿Desea continuar?");

        if (!confirmed) {
          setSaving(false);
          return;
        }
      }

      const currentSignatureDraft = signatureAccessDraft?.projectId === selectedProjectId
        ? signatureAccessDraft
        : null;

      if (currentSignatureDraft) {
        const signaturePayload = {
          mode: currentSignatureDraft.mode,
          user_ids: currentSignatureDraft.user_ids,
        };

        await projectService.updateSignatureAccess(selectedProjectId, signaturePayload);

        await queryClient.invalidateQueries({ queryKey: ["project-signature-access", selectedProjectId] });
        await queryClient.invalidateQueries({ queryKey: ["project-report-signature-status"] });
        await queryClient.invalidateQueries({ queryKey: ["project-report-physical-signatures"] });
        setSignatureAccessDraft(null);
      }

      const response = isReadOnly
        ? null
        : await projectService.update(selectedProjectId, {
            ...formData,
            observaciones: formData.observaciones?.trim() || null,
          });

      const updatedProject = response?.data?.project;

      queryClient.setQueriesData({ queryKey: ["projects"] }, (currentData) => {
        if (!currentData?.data?.items || !updatedProject) {
          return currentData;
        }

        return {
          ...currentData,
          data: {
            ...currentData.data,
            items: currentData.data.items.map((item) => (
              item.id_proyecto === updatedProject.id_proyecto
                ? {
                    ...item,
                    ...updatedProject,
                    responsable_nombre: updatedProject.nombre_responsable ?? item.responsable_nombre,
                  }
                : item
            )),
          },
        };
      });

      if (response) {
        queryClient.setQueryData(["project", selectedProjectId], response);
        queryClient.invalidateQueries({ queryKey: ["project-versions", projectId] });
      }
      toast.success("Cambios guardados correctamente.");
      onSuccess?.();
    } catch (err) {
      const fieldErrors = err.response?.data?.errors;
      const firstFieldError = fieldErrors ? Object.values(fieldErrors).flat().find(Boolean) : null;
      setError(firstFieldError || err.response?.data?.message || "Error al actualizar el proyecto");
    } finally {
      setSaving(false);
    }
  };

  const handleCreateUpdatedVersion = async () => {
    const confirmed = window.confirm("Se creará una nueva versión ACTUALIZADA desde esta versión FINALIZADA. La anterior permanecerá congelada. ¿Desea continuar?");

    if (!confirmed) {
      return;
    }

    setError(null);
    setVersioning(true);

    try {
      const response = await projectService.createUpdatedVersion(selectedProjectId);
      const nextProject = response?.data?.project;

      await queryClient.invalidateQueries({ queryKey: ["project-versions", projectId] });
      queryClient.invalidateQueries({ queryKey: ["projects"] });

      if (nextProject?.id_proyecto) {
        queryClient.setQueryData(["project", nextProject.id_proyecto], response);
        setSelectedProjectId(nextProject.id_proyecto);
      }
      toast.success("Versión actualizada creada correctamente.");
    } catch (err) {
      const fieldErrors = err.response?.data?.errors;
      const firstFieldError = fieldErrors ? Object.values(fieldErrors).flat().find(Boolean) : null;
      setError(firstFieldError || err.response?.data?.message || "No se pudo crear la nueva versión.");
    } finally {
      setVersioning(false);
    }
  };

  const handleFinalizeUpdatedVersion = async () => {
    setError(null);
    setFinalizingVersion(true);

    try {
      const response = await projectService.finalizeVersion(selectedProjectId);

      queryClient.setQueryData(["project", selectedProjectId], response);
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ["project", selectedProjectId] }),
        queryClient.invalidateQueries({ queryKey: ["project-versions", projectId] }),
        queryClient.invalidateQueries({ queryKey: ["projects"] }),
      ]);
      setFormData(null);
      toast.success("Versión finalizada correctamente.");
      onSuccess?.();
      return true;
    } catch (err) {
      const fieldErrors = err.response?.data?.errors;
      const firstFieldError = fieldErrors ? Object.values(fieldErrors).flat().find(Boolean) : null;
      setError(firstFieldError || err.response?.data?.message || "No se pudo finalizar la versión.");
      return false;
    } finally {
      setFinalizingVersion(false);
    }
  };

  const requestUpdatedExitDecision = () => new Promise((resolve) => {
    updatedExitResolverRef.current = resolve;
    setUpdatedExitDialogOpen(true);
  });

  const closeUpdatedExitDialog = (result = false) => {
    setUpdatedExitDialogOpen(false);
    updatedExitResolverRef.current?.(result);
    updatedExitResolverRef.current = null;
  };

  const handleKeepUpdatedVersionActive = () => {
    onCancel?.();
    closeUpdatedExitDialog(true);
  };

  const handleFinalizeFromUpdatedExit = async () => {
    const finalized = await handleFinalizeUpdatedVersion();

    if (finalized) {
      closeUpdatedExitDialog(true);
    }
  };

  const handleCancel = async () => {
    if (!shouldAskBeforeLeavingUpdatedVersion) {
      onCancel?.();
      return;
    }

    return requestUpdatedExitDecision();
  };

  useImperativeHandle(ref, () => ({
    requestExit: handleCancel,
  }));

  if (contextLoading || projectLoading || versionsLoading) {
    return (
      <div className="flex items-center justify-center p-8">
        <Loader2 className="size-5 animate-spin" />
      </div>
    );
  }

  if (projectError) {
    return (
      <div className="rounded-2xl border border-destructive/50 bg-destructive/10 p-4 text-destructive">
        No se pudo cargar el proyecto.
      </div>
    );
  }

  return (
    <>
    <form onSubmit={handleSubmit} className="flex flex-col gap-5">
      {error && (
        <div className="rounded-xl border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive">
          {error}
        </div>
      )}

      <div className="grid gap-4 rounded-2xl border border-border/70 bg-muted/30 p-4 sm:grid-cols-[1fr_auto] sm:items-end">
        <div className="flex flex-col gap-2">
          <Label htmlFor="project-version" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">
            Versión del proyecto
          </Label>
          <select
            id="project-version"
            value={selectedProjectId}
            onChange={(event) => {
              setSelectedProjectId(Number(event.target.value));
              setFormData(null);
              setError(null);
            }}
            className="h-12 w-full rounded-2xl border border-border/80 bg-background px-3 text-sm"
          >
            {versions.map((version) => (
              <option key={version.id_proyecto} value={version.id_proyecto}>
                Versión {version.version_number} · {version.version_created_at ? new Date(version.version_created_at).toLocaleDateString("es-BO") : version.fecha} · {getProjectApprovalLabel(version.aprobado)}
              </option>
            ))}
          </select>
        </div>
        <div className="flex items-center gap-2 text-sm text-muted-foreground">
          <History className="size-4" />
          {versions.length} versión(es)
        </div>
        <a
          href={`/Proyecto/${selectedProjectId}/items`}
          className="w-fit text-sm font-semibold text-sky-700 underline underline-offset-4 sm:col-span-2"
        >
          Ver ítems y reportes de esta versión
        </a>
      </div>

      {isReadOnly && (
        <div className="flex items-start gap-3 rounded-xl border border-slate-300 bg-slate-100 px-4 py-3 text-sm text-slate-800">
          <Lock className="mt-0.5 size-4 shrink-0" />
          <div>
            <p className="font-semibold">Versión congelada de solo lectura</p>
            <p>Puede consultarla y generar reportes, pero sus datos e ítems ya no pueden modificarse.</p>
          </div>
        </div>
      )}

      <fieldset disabled={isReadOnly} className="flex flex-col gap-5 disabled:opacity-75">
      <section className="flex flex-col gap-5">
        <span className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">
          Ubicación
        </span>

        <MapPreview
          distrito={formData.distrito}
          zona={formData.zona}
          otb={formData.otb}
          latitud={formData.latitud}
          longitud={formData.longitud}
        />

        <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
          <div className="flex flex-col gap-2">
            <Label htmlFor="distrito" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">Distrito</Label>
            <Input id="distrito" value={formData.distrito} onChange={(e) => handleChange("distrito", e.target.value)} className="h-12 rounded-2xl border-border/80 bg-background/90" />
          </div>
          <div className="flex flex-col gap-2">
            <Label htmlFor="zona" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">Zona</Label>
            <Input id="zona" value={formData.zona} onChange={(e) => handleChange("zona", e.target.value)} className="h-12 rounded-2xl border-border/80 bg-background/90" />
          </div>
          <div className="flex flex-col gap-2 sm:col-span-2 lg:col-span-1">
            <Label htmlFor="otb" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">OTB</Label>
            <Input id="otb" value={formData.otb} onChange={(e) => handleChange("otb", e.target.value)} className="h-12 rounded-2xl border-border/80 bg-background/90" />
          </div>
        </div>

        <div className="flex flex-col gap-2">
          <Label htmlFor="ubicacion" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">Especificación</Label>
          <Input id="ubicacion" value={formData.ubicacion} onChange={(e) => handleChange("ubicacion", e.target.value)} className="h-12 rounded-2xl border-border/80 bg-background/90" />
        </div>

        <div className="grid gap-5 sm:grid-cols-2">
          <div className="flex flex-col gap-2">
            <Label htmlFor="latitud" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">Latitud</Label>
            <Input id="latitud" value={formData.latitud} onChange={(e) => handleChange("latitud", e.target.value)} className="h-12 rounded-2xl border-border/80 bg-background/90" />
          </div>
          <div className="flex flex-col gap-2">
            <Label htmlFor="longitud" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">Longitud</Label>
            <Input id="longitud" value={formData.longitud} onChange={(e) => handleChange("longitud", e.target.value)} className="h-12 rounded-2xl border-border/80 bg-background/90" />
          </div>
        </div>
      </section>

      <Separator className="bg-border/70" />

      <div className="grid gap-5 sm:grid-cols-2">
        <div className="flex flex-col gap-2 sm:col-span-2">
          <Label htmlFor="nombre_proyecto" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">Proyecto</Label>
          <Input id="nombre_proyecto" value={formData.nombre_proyecto} onChange={(e) => handleChange("nombre_proyecto", e.target.value)} className="h-12 rounded-2xl border-border/80 bg-background/90" required />
        </div>
        <div className="flex flex-col gap-2">
          <Label htmlFor="fecha" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">Fecha</Label>
          <Input id="fecha" type="date" value={formData.fecha} onChange={(e) => handleChange("fecha", e.target.value)} className="h-12 rounded-2xl border-border/80 bg-background/90" required />
        </div>
        <div className="flex flex-col gap-2">
          <Label htmlFor="responsable" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">Responsable</Label>
          <select id="responsable" value={formData.responsable} onChange={(e) => handleChange("responsable", e.target.value)} className="h-12 w-full rounded-2xl border border-border/80 bg-background/90 px-3 text-sm" required>
            <option value="">SELECCIONAR</option>
            {responsibleOptions.map((option) => (
              <option key={option.id_usuario} value={option.id_usuario}>{option.funcionario}</option>
            ))}
          </select>
        </div>
        <div className="flex flex-col gap-2">
          <Label htmlFor="solicitante" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">Solicitante</Label>
          <select id="solicitante" value={formData.solicitante} onChange={(e) => handleChange("solicitante", e.target.value)} className="h-12 w-full rounded-2xl border border-border/80 bg-background/90 px-3 text-sm" required>
            <option value="">SELECCIONAR</option>
            {requesterOptions.map((option) => (
              <option key={option.id_usuario} value={option.id_usuario}>{option.funcionario}</option>
            ))}
          </select>
        </div>
        <div className="flex flex-col gap-2">
          <Label htmlFor="aprobado" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">Condición</Label>
          <select id="aprobado" value={formData.aprobado} onChange={(e) => handleChange("aprobado", e.target.value)} className="h-12 w-full rounded-2xl border border-border/80 bg-background/90 px-3 text-sm" required>
            {conditions.map((condition) => (
              <option key={condition.code} value={condition.code}>{condition.label}</option>
            ))}
          </select>
        </div>
        <div className="flex flex-col gap-2">
          <Label htmlFor="estado" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">Estado</Label>
          <select id="estado" value={formData.estado} onChange={(e) => handleChange("estado", e.target.value)} className="h-12 w-full rounded-2xl border border-border/80 bg-background/90 px-3 text-sm" required>
            {statuses.map((status) => (
              <option key={status.code} value={status.code}>{status.label}</option>
            ))}
          </select>
        </div>
      </div>

      <div className="flex flex-col gap-2">
        <Label htmlFor="observaciones" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">Observación</Label>
        <textarea id="observaciones" value={formData.observaciones} onChange={(e) => handleChange("observaciones", e.target.value)} className="min-h-28 w-full rounded-2xl border border-border/80 bg-background/90 px-3 py-3 text-sm" />
      </div>
      </fieldset>

      <ProjectSignatureAccessSection
        projectId={selectedProjectId}
        people={contextData?.data?.people ?? []}
        onDraftChange={(value) => setSignatureAccessDraft({ projectId: selectedProjectId, ...value })}
      />

      <Separator className="bg-border/70" />

      <div className="flex flex-wrap justify-end gap-2">
        <Button type="button" variant="outline" className="rounded-full border-border/70 bg-background/80" onClick={handleCancel} disabled={finalizingVersion}>
          Cancelar
        </Button>
        {project?.is_current_version && project?.is_frozen && (
          <Button type="button" className="rounded-full bg-sky-700 text-white hover:bg-sky-600" onClick={handleCreateUpdatedVersion} disabled={versioning}>
            {versioning ? <Loader2 className="mr-2 size-4 animate-spin" /> : <RefreshCw className="mr-2 size-4" />}
            Crear versión actualizada
          </Button>
        )}
        {(!isReadOnly || canManageSignatureAccess) && (
        <Button type="submit" className="rounded-full bg-foreground text-background hover:bg-foreground/90" disabled={saving}>
          {saving ? (
            <>
              <Loader2 className="mr-2 size-4 animate-spin" />
              Guardando...
            </>
          ) : (
            <>
              <Save className="mr-2 size-4" />
              Guardar cambios
            </>
          )}
        </Button>
        )}
      </div>
    </form>
    <UpdatedProjectExitDialog
      open={updatedExitDialogOpen}
      isFinalizing={finalizingVersion}
      onCancel={() => closeUpdatedExitDialog(false)}
      onFinalize={handleFinalizeFromUpdatedExit}
      onKeepActive={handleKeepUpdatedVersionActive}
    />
    </>
  );
});

export default ProjectEditForm;
