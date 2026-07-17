import { useEffect, useMemo, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { ArrowLeft, FileSignature, Loader2 } from "lucide-react";
import { useNavigate, useParams } from "react-router-dom";

import { Alert, AlertDescription } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { buildPdfViewerUrl } from "@/lib/utils/pdf";
import { projectService } from "../services/project.service";

const FORMATS = ["PCA", "PC_FPS", "PC_UPRE", "PC_FNDR", "PC_OBRAS"];
const BREAKDOWNS = [
  [1, "Material"],
  [2, "Mano de obra"],
  [3, "Maquinaria y herramientas"],
];

const REPORTS = {
  budget_by_group: { pdf: (id) => projectService.budgetByGroupPdfUrl(id) },
  budget_recalculation: { parameter: "fecha", pdf: (id, parameters) => projectService.budgetRecalculationPdfUrl(id, parameters.fecha) },
  incidence_summary: { parameter: "format", defaultValue: "PCA", pdf: (id, parameters) => projectService.incidenceSummaryPdfUrl(id, parameters.format) },
  general_budget: { parameter: "format", defaultValue: "PCA", pdf: (id, parameters) => projectService.generalBudgetPdfUrl(id, parameters.format) },
  input_breakdown: { parameter: "type", defaultValue: 1, pdf: (id, parameters) => projectService.inputBreakdownPdfUrl(id, parameters.type) },
  inputs_report: { pdf: (id) => projectService.inputsReportPdfUrl(id) },
  grouped_inputs_report: { pdf: (id) => projectService.groupedInputsReportPdfUrl(id) },
  unit_prices: { parameter: "format", defaultValue: "PCA", pdf: (id, parameters) => projectService.unitPricesPdfUrl(id, parameters.format) },
  specifications: { pdf: (id) => projectService.specificationsPdfUrl(id) },
};

function viewerPath(projectId, report, parameters, signed = false) {
  const definition = REPORTS[report.report_key];
  const pdfUrl = signed
    ? projectService.latestSignedReportUrl(projectId, report.report_key, parameters)
    : definition.pdf(projectId, parameters);
  const url = new URL(buildPdfViewerUrl(pdfUrl, {
    title: report.name,
    signature: { projectId, reportKey: report.report_key, parameters },
  }));

  return `${url.pathname}${url.search}`;
}

function ReportCard({ projectId, report }) {
  const navigate = useNavigate();
  const definition = REPORTS[report.report_key];
  const [value, setValue] = useState(definition.defaultValue ?? "");
  const parameters = useMemo(
    () => definition.parameter && value !== "" ? { [definition.parameter]: value } : {},
    [definition.parameter, value],
  );
  const ready = !definition.parameter || value !== "";
  const statusQuery = useQuery({
    queryKey: ["project-signature-status", projectId, report.report_key, parameters],
    queryFn: () => projectService.signatureStatus(projectId, { report_key: report.report_key, ...parameters }),
    enabled: ready,
  });
  const status = statusQuery.data?.data;
  const pending = ["pending", "auth_pending", "sent"].includes(status?.current_user_signature_status);
  const state = status?.current_user_signed ? "Firmado" : pending ? "Firma en proceso" : "Pendiente";

  return (
    <Card className="border border-border/70 bg-background/90">
      <CardHeader className="border-b border-border/60">
        <div className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <CardTitle>{report.name}</CardTitle>
            <CardDescription className="mt-1">{report.description}</CardDescription>
          </div>
          <Badge variant={status?.current_user_signed ? "secondary" : pending ? "outline" : "default"}>{state}</Badge>
        </div>
      </CardHeader>
      <CardContent className="flex flex-col gap-4">
        {definition.parameter === "format" && (
          <label className="grid gap-2 text-sm font-medium">
            Formato
            <select className="h-10 rounded-md border border-input bg-background px-3" value={value} onChange={(event) => setValue(event.target.value)}>
              {FORMATS.map((format) => <option key={format} value={format}>{format}</option>)}
            </select>
          </label>
        )}
        {definition.parameter === "type" && (
          <label className="grid gap-2 text-sm font-medium">
            Desglose
            <select className="h-10 rounded-md border border-input bg-background px-3" value={value} onChange={(event) => setValue(Number(event.target.value))}>
              {BREAKDOWNS.map(([type, label]) => <option key={type} value={type}>{label}</option>)}
            </select>
          </label>
        )}
        {definition.parameter === "fecha" && (
          <label className="grid gap-2 text-sm font-medium">
            Fecha de recálculo
            <Input type="date" value={value} onChange={(event) => setValue(event.target.value)} />
          </label>
        )}

        <div className="flex min-h-6 items-center text-sm text-muted-foreground">
          {statusQuery.isFetching ? (
            <><Loader2 className="mr-2 size-4 animate-spin" /> Actualizando avance...</>
          ) : statusQuery.isError ? (
            <span className="text-destructive">No se pudo consultar el avance.</span>
          ) : ready ? (
            <span><strong className="text-foreground">{status?.signed_signers_count ?? 0} de {status?.required_signers_count ?? 0}</strong> firmantes</span>
          ) : (
            <span>Seleccione una fecha para consultar el avance.</span>
          )}
        </div>

        <Button
          type="button"
          disabled={!ready || statusQuery.isFetching || statusQuery.isError || pending}
          onClick={() => navigate(viewerPath(projectId, report, parameters, Boolean(status?.current_user_signed)))}
        >
          {status?.current_user_signed ? "Ver PDF firmado" : pending ? "Firma en proceso" : "Abrir PDF y firmar"}
        </Button>
      </CardContent>
    </Card>
  );
}

export default function ProjectSignatureReportsPage() {
  const { projectId } = useParams();
  const navigate = useNavigate();
  const projectQuery = useQuery({
    queryKey: ["project", projectId],
    queryFn: () => projectService.show(projectId),
  });
  const reportsQuery = useQuery({
    queryKey: ["signable-project-reports"],
    queryFn: projectService.signableReports,
  });
  const summaryQuery = useQuery({
    queryKey: ["project-signature-status", projectId],
    queryFn: () => projectService.signatureStatus(projectId),
  });
  const project = projectQuery.data?.data?.project;
  const summary = summaryQuery.data?.data;
  const reports = (reportsQuery.data?.data?.items ?? []).filter(
    (report) => report.scope === "project" && report.is_enabled && report.can_sign && REPORTS[report.report_key],
  );
  const loading = projectQuery.isLoading || reportsQuery.isLoading || summaryQuery.isLoading;
  const failed = projectQuery.isError || reportsQuery.isError || summaryQuery.isError;
  const allowed = summary?.signature_access?.allowed !== false;
  const directReport = reports.length === 1 && !REPORTS[reports[0].report_key].parameter ? reports[0] : null;

  useEffect(() => {
    if (!loading && !failed && allowed && summary?.project_is_finalized && directReport) {
      navigate(viewerPath(projectId, directReport, {}), { replace: true });
    }
  }, [allowed, directReport, failed, loading, navigate, projectId, summary?.project_is_finalized]);

  return (
    <div className="space-y-5">
      <div className="flex flex-wrap items-center gap-3">
        <Button type="button" variant="outline" size="icon" onClick={() => navigate(`/Proyecto/${projectId}/items`)} aria-label="Volver al proyecto">
          <ArrowLeft className="size-4" />
        </Button>
        <div className="flex size-11 items-center justify-center rounded-2xl bg-slate-900 text-white">
          <FileSignature className="size-5" />
        </div>
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">Firmas digitales del proyecto</h1>
          <p className="text-sm text-muted-foreground">{project?.nombre_proyecto ?? "Proyecto"}{project?.numero_version ? ` · Versión ${project.numero_version}` : ""}</p>
        </div>
      </div>

      {loading && <div className="flex items-center justify-center p-10 text-muted-foreground"><Loader2 className="mr-2 size-5 animate-spin" /> Cargando reportes...</div>}
      {failed && <Alert variant="destructive"><AlertDescription>No se pudieron cargar los reportes pendientes de firma.</AlertDescription></Alert>}
      {!loading && !failed && (!allowed || !summary?.project_is_finalized || reports.length === 0) && (
        <Alert><AlertDescription>{summary?.signature_access?.message || "No existen reportes habilitados que pueda firmar en esta versión."}</AlertDescription></Alert>
      )}
      {!loading && !failed && allowed && summary?.project_is_finalized && !directReport && reports.length > 0 && (
        <div className="grid gap-4 lg:grid-cols-2">
          {reports.map((report) => <ReportCard key={report.report_key} projectId={projectId} report={report} />)}
        </div>
      )}
    </div>
  );
}
