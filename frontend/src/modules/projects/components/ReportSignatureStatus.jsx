import { useMemo, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { CheckCircle2, ExternalLink, History, Loader2, X } from "lucide-react";

import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { buildPdfViewerUrl, openUrlInNewTab } from "@/lib/utils/pdf";
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

export default function ReportSignatureStatus({ projectId, reportKey, parameters = {} }) {
  const [historyOpen, setHistoryOpen] = useState(false);
  const normalizedParameters = useMemo(() => compactParameters(parameters), [parameters]);
  const parametersKey = useMemo(() => JSON.stringify(normalizedParameters), [normalizedParameters]);
  const enabled = Boolean(projectId && reportKey);

  const statusQuery = useQuery({
    queryKey: ["project-report-signature-status", projectId, reportKey, parametersKey],
    queryFn: () => projectService.signatureStatus(projectId, { report_key: reportKey, ...normalizedParameters }),
    enabled,
    staleTime: 15_000,
  });

  const historyQuery = useQuery({
    queryKey: ["project-report-signatures", projectId, reportKey, parametersKey],
    queryFn: () => projectService.reportSignatures(projectId, reportKey, normalizedParameters),
    enabled: enabled && historyOpen,
    staleTime: 15_000,
  });

  if (!enabled) {
    return null;
  }

  const status = statusQuery.data?.data;
  const latestSigned = status?.latest_signed;
  const meta = statusMeta(status);
  const latestSigners = historyQuery.data?.data?.signers ?? latestSigned?.validation_records ?? [];

  const openSignedPdf = (signature = latestSigned) => {
    if (!signature?.has_signed_file) {
      return;
    }

    const url = projectService.latestSignedReportUrl(
      signature.project_id || projectId,
      signature.report_key || reportKey,
      signature.parameters || normalizedParameters,
    );

    openUrlInNewTab(buildPdfViewerUrl(url, {
      title: "PDF firmado",
      signature: {
        projectId: signature.project_id || projectId,
        reportKey: signature.report_key || reportKey,
        parameters: signature.parameters || normalizedParameters,
      },
    }));
  };

  return (
    <div className="rounded-2xl border border-border/70 bg-muted/20 p-3">
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
          <Button type="button" variant="outline" size="sm" className="rounded-full gap-2" onClick={() => setHistoryOpen(true)}>
            <History className="h-4 w-4" />
            Historial
          </Button>
        </div>
      </div>

      {statusQuery.isError ? (
        <p className="mt-2 text-xs text-rose-600">No se pudo consultar el estado de firma.</p>
      ) : null}

      <Dialog open={historyOpen} onOpenChange={setHistoryOpen}>
        <DialogContent className="max-w-3xl">
          <DialogHeader className="flex-row items-start justify-between gap-4">
            <div>
              <DialogTitle>Historial de firmas</DialogTitle>
              <p className="mt-1 text-sm text-muted-foreground">Firmas registradas para este reporte y sus parámetros.</p>
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
