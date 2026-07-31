import { useMemo, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { CheckCircle2, ExternalLink, FileSignature, History, Loader2, Move, X } from "lucide-react";

import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { buildPdfViewerUrl, openUrlInNewTab } from "@/lib/utils/pdf";
import { itemsService } from "@/modules/dashboard/services/items.service";
import { projectService } from "@/modules/projects/services/project.service";

function compactParameters(parameters = {}) {
  return Object.fromEntries(
    Object.entries(parameters).filter(([, value]) => value !== undefined && value !== null && value !== ""),
  );
}

function statusMeta(status) {
  const latestSigned = status?.latest_signed;
  const latestSignature = status?.latest_signature;

  if (status?.is_signed_stale) {
    return { label: "Firma anterior disponible", className: "bg-amber-100 text-amber-700" };
  }

  if (latestSigned?.has_signed_file) {
    return { label: "Firmado", className: "bg-emerald-100 text-emerald-700" };
  }

  if (latestSignature?.status && !["signed", "failed", "error", "cancelled"].includes(latestSignature.status)) {
    return { label: "Firma pendiente", className: "bg-amber-100 text-amber-700" };
  }

  if (latestSignature?.status === "failed" || latestSignature?.status === "error") {
    return { label: "Firma con error", className: "bg-rose-100 text-rose-700" };
  }

  return { label: "Sin firma", className: "bg-slate-100 text-slate-600" };
}

function signerName(record) {
  return [
    record?.nombres,
    record?.primer_apellido,
    record?.segundo_apellido,
  ].filter(Boolean).join(" ").trim() || "Firmante sin nombre";
}

function readApiError(error, fallback) {
  return error?.response?.data?.message || fallback;
}

export default function ReportSignatureStatus({ subject = "project", projectId, itemId, reportKey, parameters = {} }) {
  const [historyOpen, setHistoryOpen] = useState(false);
  const [physicalOpen, setPhysicalOpen] = useState(false);
  const [physicalError, setPhysicalError] = useState("");
  const [markingPhysical, setMarkingPhysical] = useState(false);
  const isItemSubject = subject === "item";
  const subjectId = isItemSubject ? itemId : projectId;
  const signatureService = isItemSubject ? itemsService : projectService;
  const queryScope = isItemSubject ? "item" : "project";
  const normalizedParameters = useMemo(() => compactParameters(parameters), [parameters]);
  const parametersKey = useMemo(() => JSON.stringify(normalizedParameters), [normalizedParameters]);
  const enabled = Boolean(subjectId && reportKey);

  const buildSignatureOptions = (targetSubject = subject, targetId = subjectId, targetReportKey = reportKey, targetParameters = normalizedParameters) => {
    if (targetSubject === "item") {
      return {
        subject: "item",
        itemId: targetId,
        reportKey: targetReportKey,
        parameters: targetParameters,
      };
    }

    return {
      projectId: targetId,
      reportKey: targetReportKey,
      parameters: targetParameters,
    };
  };

  const statusQuery = useQuery({
    queryKey: [`${queryScope}-report-signature-status`, subjectId, reportKey, parametersKey],
    queryFn: () => signatureService.signatureStatus(subjectId, { report_key: reportKey, ...normalizedParameters }),
    enabled,
    staleTime: 15_000,
  });

  const historyQuery = useQuery({
    queryKey: [`${queryScope}-report-signatures`, subjectId, reportKey, parametersKey],
    queryFn: () => signatureService.reportSignatures(subjectId, reportKey, normalizedParameters),
    enabled: enabled && historyOpen,
    staleTime: 15_000,
  });

  const status = statusQuery.data?.data;
  const latestSigned = status?.latest_signed;
  const canUsePhysicalSignatures = Boolean(isItemSubject && latestSigned?.has_signed_file && !status?.is_signed_stale);

  const physicalQuery = useQuery({
    queryKey: [`${queryScope}-report-physical-signatures`, subjectId, reportKey, parametersKey],
    queryFn: () => signatureService.physicalSignatures(subjectId, reportKey, normalizedParameters),
    enabled: enabled && canUsePhysicalSignatures,
    staleTime: 15_000,
  });

  if (!enabled) {
    return null;
  }

  const meta = statusMeta(status);
  const latestSigners = historyQuery.data?.data?.signers ?? latestSigned?.validation_records ?? [];
  const physicalStatus = physicalQuery.data?.data;
  const signatureAccess = status?.signature_access;
  const physicalSignatures = physicalStatus?.items ?? [];
  const physicalSignatureCount = physicalStatus?.count ?? 0;

  const openSignedPdf = (signature = latestSigned) => {
    if (!signature?.has_signed_file) {
      return;
    }

    const signatureSubject = signature.subject || (signature.item_id ? "item" : subject);
    const signedSubjectId = signatureSubject === "item"
      ? (signature.item_id || signature.subject_id || itemId || subjectId)
      : (signature.project_id || signature.subject_id || projectId || subjectId);
    const signedService = signatureSubject === "item" ? itemsService : projectService;
    const url = signedService.latestSignedReportUrl(
      signedSubjectId,
      signature.report_key || reportKey,
      signature.parameters || normalizedParameters,
    );

    openUrlInNewTab(buildPdfViewerUrl(url, {
      title: "PDF firmado",
      signature: buildSignatureOptions(
        signatureSubject,
        signedSubjectId,
        signature.report_key || reportKey,
        signature.parameters || normalizedParameters,
      ),
    }));
  };

  const openPhysicalSignedPdf = () => {
    const url = signatureService.physicalSignaturesPdfUrl(subjectId, reportKey, normalizedParameters);

    openUrlInNewTab(buildPdfViewerUrl(url, {
      title: "PDF con firmas físicas",
      signature: buildSignatureOptions(),
    }));
  };

  const openAdjustPhysicalSignatures = () => {
    if (!latestSigned?.has_signed_file) {
      return;
    }

    const url = signatureService.latestSignedReportUrl(subjectId, reportKey, normalizedParameters);

    openUrlInNewTab(buildPdfViewerUrl(url, {
      title: "Ajustar firmas físicas",
      adjustPhysicalSignatures: true,
      signature: buildSignatureOptions(),
    }));
  };

  const handleMarkPhysicalSignature = async () => {
    if (!physicalStatus?.can_mark || markingPhysical) {
      return;
    }

    setPhysicalError("");
    setMarkingPhysical(true);

    try {
      await signatureService.markPhysicalSignature(subjectId, reportKey, normalizedParameters);
      await physicalQuery.refetch();
    } catch (error) {
      setPhysicalError(readApiError(error, "No se pudo marcar la firma física."));
    } finally {
      setMarkingPhysical(false);
    }
  };

  return (
    <div className="rounded-2xl border border-border/70 bg-muted/20 p-3">
      <div className="flex flex-col gap-3">
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div className="flex items-center gap-2">
            {statusQuery.isFetching ? <Loader2 className="h-4 w-4 animate-spin text-muted-foreground" /> : <CheckCircle2 className="h-4 w-4 text-muted-foreground" />}
            <span className="text-sm font-medium text-foreground">Firma digital</span>
            <Badge className={`rounded-full px-2.5 py-1 text-[11px] uppercase tracking-[0.16em] ${meta.className}`}>
              {meta.label}
            </Badge>
          </div>

          <div className="flex flex-wrap gap-2">
            {latestSigned?.has_signed_file ? (
              <Button type="button" variant="outline" size="sm" className="rounded-full gap-2" onClick={() => openSignedPdf()}>
                <ExternalLink className="h-4 w-4" />
                Ver PDF firmado
              </Button>
            ) : null}
            {canUsePhysicalSignatures && physicalSignatureCount > 0 ? (
              <Button type="button" variant="outline" size="sm" className="rounded-full gap-2" onClick={openPhysicalSignedPdf}>
                <ExternalLink className="h-4 w-4" />
                PDF con firmas físicas
              </Button>
            ) : null}
            {canUsePhysicalSignatures && physicalStatus?.can_adjust && physicalSignatureCount > 0 ? (
              <Button type="button" variant="outline" size="sm" className="rounded-full gap-2" onClick={openAdjustPhysicalSignatures}>
                <Move className="h-4 w-4" />
                Ajustar firmas
              </Button>
            ) : null}
            {canUsePhysicalSignatures ? (
              <Button type="button" variant="outline" size="sm" className="rounded-full gap-2" onClick={() => setPhysicalOpen((open) => !open)}>
                {physicalQuery.isFetching ? <Loader2 className="h-4 w-4 animate-spin" /> : <FileSignature className="h-4 w-4" />}
                Firmas físicas
                <Badge className="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] text-slate-700">{physicalSignatureCount}</Badge>
              </Button>
            ) : null}
            <Button type="button" variant="outline" size="sm" className="rounded-full gap-2" onClick={() => setHistoryOpen(true)}>
              <History className="h-4 w-4" />
              Ver firmantes
            </Button>
          </div>
        </div>

        {status?.is_signed_stale ? (
          <p className="rounded-xl bg-amber-50 px-3 py-2 text-xs text-amber-700">
            Este reporte cambió desde la última firma. Se generará el PDF actual sin firmas.
          </p>
        ) : null}

        {signatureAccess?.allowed === false ? (
          <p className="rounded-xl bg-amber-50 px-3 py-2 text-xs text-amber-700">
            {signatureAccess.message || "No está autorizado para firmar documentos de este proyecto."}
          </p>
        ) : null}

        {canUsePhysicalSignatures && physicalOpen ? (
          <div className="rounded-2xl border border-border bg-background p-3 shadow-sm">
            <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
              <div>
                <div className="text-sm font-semibold text-foreground">Firmas físicas</div>
                <div className="text-xs text-muted-foreground">{physicalSignatureCount} usuario(s) marcaron este documento.</div>
              </div>
              {physicalStatus?.already_marked ? (
                <Badge className="w-fit rounded-full bg-emerald-100 text-emerald-700">Marcado</Badge>
              ) : (
                <Badge className="w-fit rounded-full bg-amber-100 text-amber-700">Pendiente</Badge>
              )}
            </div>

            <div className="mt-3 max-h-40 space-y-2 overflow-y-auto">
              {physicalQuery.isFetching ? (
                <div className="flex items-center gap-2 rounded-xl border border-dashed border-border p-3 text-xs text-muted-foreground">
                  <Loader2 className="h-3.5 w-3.5 animate-spin" />
                  Cargando firmas...
                </div>
              ) : null}
              {!physicalQuery.isFetching && physicalSignatures.length === 0 ? (
                <div className="rounded-xl border border-dashed border-border p-3 text-xs text-muted-foreground">
                  Aún no hay firmas físicas marcadas.
                </div>
              ) : null}
              {!physicalQuery.isFetching && physicalSignatures.map((signature) => (
                <div key={signature.id} className="rounded-xl bg-muted/50 px-3 py-2 text-xs">
                  <div className="font-medium text-foreground">{signature.user_name || "Usuario"}</div>
                  <div className="text-muted-foreground">Marcado: {signature.marked_at || "-"}</div>
                </div>
              ))}
            </div>

            {physicalStatus?.needs_signature_image ? (
              <p className="mt-3 rounded-xl bg-amber-50 px-3 py-2 text-xs text-amber-700">
                Carga tu firma en Perfil para marcar este documento.
              </p>
            ) : null}
            {physicalStatus?.signature_access?.allowed === false ? (
              <p className="mt-3 rounded-xl bg-amber-50 px-3 py-2 text-xs text-amber-700">
                {physicalStatus.signature_access.message}
              </p>
            ) : null}
            {physicalError ? (
              <p className="mt-3 rounded-xl bg-rose-50 px-3 py-2 text-xs text-rose-700">{physicalError}</p>
            ) : null}

            <div className="mt-3 flex flex-col gap-2 sm:flex-row">
              <Button
                type="button"
                size="sm"
                className="rounded-full"
                disabled={!physicalStatus?.can_mark || markingPhysical}
                onClick={handleMarkPhysicalSignature}
              >
                {markingPhysical ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <FileSignature className="mr-2 h-4 w-4" />}
                {physicalStatus?.already_marked ? "Actualizar firma física" : "Marcar firma física"}
              </Button>
            </div>
          </div>
        ) : null}
      </div>

      {statusQuery.isError ? (
        <p className="mt-2 text-xs text-rose-600">No se pudo consultar el estado de firma.</p>
      ) : null}

      <Dialog open={historyOpen} onOpenChange={setHistoryOpen}>
        <DialogContent className="max-w-3xl">
          <DialogHeader className="flex-row items-start justify-between gap-4">
            <div>
              <DialogTitle>Firmantes del PDF</DialogTitle>
              <p className="mt-1 text-sm text-muted-foreground">Identidades certificadas en el último PDF firmado.</p>
            </div>
            <Button type="button" variant="ghost" size="icon-sm" className="rounded-full" onClick={() => setHistoryOpen(false)}>
              <X className="h-4 w-4" />
            </Button>
          </DialogHeader>

          <div className="max-h-[55vh] overflow-y-auto">
            {historyQuery.isLoading || historyQuery.isFetching ? (
              <div className="flex items-center justify-center gap-2 rounded-xl border border-dashed border-border/80 p-8 text-sm text-muted-foreground">
                <Loader2 className="h-4 w-4 animate-spin" />
                Cargando historial...
              </div>
            ) : null}

            {!historyQuery.isFetching && historyQuery.isError ? (
              <div className="rounded-xl border border-rose-100 bg-rose-50 p-4 text-sm text-rose-700">
                No se pudo cargar el historial de firmas.
              </div>
            ) : null}

            {!historyQuery.isFetching && !historyQuery.isError && latestSigners.length === 0 ? (
              <div className="rounded-xl border border-dashed border-border/80 p-8 text-center text-sm text-muted-foreground">
                No hay firmantes validados para estos parámetros.
              </div>
            ) : null}

            {!historyQuery.isFetching && !historyQuery.isError && latestSigners.length > 0 ? (
              <div className="space-y-3">
                <div className="rounded-xl border border-emerald-100 bg-emerald-50/70 p-4">
                  <h3 className="text-sm font-semibold text-emerald-900">Firmantes del PDF</h3>
                  <div className="mt-3 space-y-2">
                    {latestSigners.map((signer, index) => (
                      <div key={`${signer?.nro_documento || index}-${signer?.fecha_verificacion || index}`} className="rounded-lg bg-white/80 px-3 py-2 text-sm">
                        <div className="font-medium text-foreground">{signerName(signer)}</div>
                        <div className="mt-0.5 text-xs text-muted-foreground">
                          Doc.: {signer?.nro_documento || "-"} · Sol.: {signer?.fecha_solicitud || "-"} · Verif.: {signer?.fecha_verificacion || "-"}
                        </div>
                      </div>
                    ))}
                  </div>
                </div>
              </div>
            ) : null}
          </div>
        </DialogContent>
      </Dialog>
    </div>
  );
}
