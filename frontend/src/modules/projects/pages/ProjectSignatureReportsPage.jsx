import { useQuery } from "@tanstack/react-query";
import { AlertCircle, ArrowLeft, CheckCircle2, FileSignature, Loader2 } from "lucide-react";
import { useNavigate, useParams } from "react-router-dom";

import { Alert, AlertDescription } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { formatDateTime } from "@/lib/utils";
import { buildPdfViewerUrl } from "@/lib/utils/pdf";
import { projectService } from "../services/project.service";

const BREAKDOWNS = { 1: "Material", 2: "Mano de obra", 3: "Maquinaria y herramientas" };
const STATUS = {
  prepared: ["Preparado", "outline"],
  pending: ["Pendiente", "outline"],
  auth_pending: ["Autenticando", "outline"],
  sent: ["En firma", "outline"],
  signed: ["Firmado", "secondary"],
  error: ["Error", "destructive"],
  cancelled: ["Cancelado", "destructive"],
};

const REPORTS = {
  budget_by_group: (id) => projectService.budgetByGroupPdfUrl(id),
  budget_recalculation: (id, parameters) => projectService.budgetRecalculationPdfUrl(id, parameters.fecha),
  incidence_summary: (id, parameters) => projectService.incidenceSummaryPdfUrl(id, parameters.format),
  general_budget: (id, parameters) => projectService.generalBudgetPdfUrl(id, parameters.format),
  input_breakdown: (id, parameters) => projectService.inputBreakdownPdfUrl(id, parameters.type),
  inputs_report: (id) => projectService.inputsReportPdfUrl(id),
  grouped_inputs_report: (id) => projectService.groupedInputsReportPdfUrl(id),
  unit_prices: (id, parameters) => projectService.unitPricesPdfUrl(id, parameters.format),
  specifications: (id) => projectService.specificationsPdfUrl(id),
};

function parameterLabels(parameters) {
  return Object.entries(parameters ?? {}).map(([key, value]) => {
    if (key === "format") return `Formato: ${value}`;
    if (key === "type") return `Desglose: ${BREAKDOWNS[value] ?? value}`;
    if (key === "fecha") return `Fecha: ${value}`;
    return `${key}: ${value}`;
  });
}

function viewerPath(projectId, document) {
  if (!document.parameters_available || !REPORTS[document.report_key]) return null;

  const signature = {
    projectId,
    reportKey: document.report_key,
    parameters: document.parameters,
  };
  let pdfUrl;
  let options;

  if (document.has_signed_file) {
    pdfUrl = projectService.latestSignedReportUrl(projectId, document.report_key, document.parameters);
    options = { title: `${document.name} firmado`, signature, signedView: true };
  } else if (document.has_physical_activity) {
    pdfUrl = projectService.signaturePreviewPdfUrl(projectId, document.report_key, {
      ...document.parameters,
      page_scope: document.page_scope,
    });
    options = {
      title: "Previsualización de firmas",
      signature,
      adjustPhysicalSignatures: true,
      pageScope: document.page_scope,
    };
  } else {
    pdfUrl = REPORTS[document.report_key](projectId, document.parameters);
    options = { title: document.name, signature };
  }

  const url = new URL(buildPdfViewerUrl(pdfUrl, options));
  return `${url.pathname}${url.search}`;
}

function DocumentCard({ projectId, document }) {
  const navigate = useNavigate();
  const [statusLabel, statusVariant] = STATUS[document.latest_status] ?? [document.latest_status, "outline"];
  const path = viewerPath(projectId, document);
  const labels = parameterLabels(document.parameters);

  return (
    <Card className={document.current_user_needs_action ? "border-red-300 bg-red-50/30" : "border-border/70 bg-background/90"}>
      <CardHeader className="border-b border-border/60">
        <div className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <CardTitle>{document.name}</CardTitle>
            <CardDescription className="mt-1">{document.description}</CardDescription>
          </div>
          <div className="flex flex-wrap items-center gap-2">
            {document.current_user_needs_action && <Badge variant="destructive">Te falta firmar</Badge>}
            <Badge variant={statusVariant}>{statusLabel}</Badge>
          </div>
        </div>
      </CardHeader>
      <CardContent className="space-y-4 pt-5">
        <div className="flex flex-wrap gap-2">
          {document.has_physical_activity && <Badge variant="outline">Firma física</Badge>}
          {document.has_digital_activity && <Badge variant="outline">Firma digital</Badge>}
          {labels.map((label) => <Badge key={label} variant="secondary">{label}</Badge>)}
          {!document.parameters_available && <Badge variant="destructive">Parámetros no disponibles</Badge>}
        </div>
        <div className="grid gap-1 text-sm text-muted-foreground sm:grid-cols-2">
          <span><strong className="text-foreground">{document.signed_signers_count} de {document.required_signers_count}</strong> firmantes digitales</span>
          <span>{document.physical_signers_count} firmas físicas preparadas</span>
          <span className="sm:col-span-2">Última actividad: {document.last_activity_at ? formatDateTime(document.last_activity_at) : "Sin fecha"}</span>
        </div>
        <Button type="button" disabled={!path} onClick={() => path && navigate(path)} className="w-full">
          {document.has_signed_file ? "Ver PDF firmado" : document.has_physical_activity ? "Abrir previsualización" : "Abrir PDF y reintentar"}
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
  const summaryQuery = useQuery({
    queryKey: ["project-signature-status", projectId],
    queryFn: () => projectService.signatureStatus(projectId),
  });
  const project = projectQuery.data?.data?.project;
  const documents = summaryQuery.data?.data?.documents ?? [];
  const loading = projectQuery.isLoading || summaryQuery.isLoading;
  const failed = projectQuery.isError || summaryQuery.isError;

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
          <h1 className="text-2xl font-semibold tracking-tight">PDF con procesos de firma</h1>
          <p className="text-sm text-muted-foreground">{project?.nombre_proyecto ?? "Proyecto"}{project?.numero_version ? ` · Versión ${project.numero_version}` : ""}</p>
        </div>
      </div>

      {loading && <div className="flex items-center justify-center p-10 text-muted-foreground"><Loader2 className="mr-2 size-5 animate-spin" /> Cargando documentos...</div>}
      {failed && <Alert variant="destructive"><AlertCircle className="size-4" /><AlertDescription>No se pudieron cargar los documentos con firmas.</AlertDescription></Alert>}
      {!loading && !failed && documents.length === 0 && (
        <Alert><CheckCircle2 className="size-4" /><AlertDescription>Esta versión todavía no tiene PDF con procesos de firma.</AlertDescription></Alert>
      )}
      {!loading && !failed && documents.length > 0 && (
        <div className="grid gap-4 lg:grid-cols-2">
          {documents.map((document) => <DocumentCard key={`${document.report_key}:${document.parameters_hash}`} projectId={projectId} document={document} />)}
        </div>
      )}
    </div>
  );
}
