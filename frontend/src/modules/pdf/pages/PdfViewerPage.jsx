import { useEffect, useMemo, useState } from "react";
import { AlertCircle, CheckCircle2, ExternalLink, FileSignature, FileText, History, Loader2, RefreshCw, X } from "lucide-react";
import { useSearchParams } from "react-router-dom";

import { apiOrigin } from "@/lib/api/client";
import { buildPdfViewerUrl, openUrlInNewTab } from "@/lib/utils/pdf";
import { projectService } from "@/modules/projects/services/project.service";

const DEFAULT_ERROR_MESSAGE = "No se pudo generar el PDF.";
const SIGNATURE_SESSION_KEY = "sipre:ciudadania-digital:signature";

function parseMessageFromPayload(payload) {
  if (!payload || typeof payload !== "object") {
    return "";
  }

  if (typeof payload.message === "string") {
    return payload.message;
  }

  if (typeof payload.error === "string") {
    return payload.error;
  }

  return "";
}

async function readErrorMessage(response) {
  const contentType = response.headers.get("content-type") || "";

  if (response.status === 401) {
    return "Su sesión expiró. Inicie sesión nuevamente.";
  }

  if (response.status === 403) {
    return "No tiene permisos para generar este PDF.";
  }

  if (contentType.includes("application/json")) {
    try {
      const payload = await response.json();
      return parseMessageFromPayload(payload);
    } catch {
      return "";
    }
  }

  try {
    const text = await response.text();
    return text.replace(/<[^>]+>/g, " ").replace(/\s+/g, " ").trim();
  } catch {
    return "";
  }
}

function parseJsonParam(value) {
  if (!value) {
    return {};
  }

  try {
    const parsed = JSON.parse(value);
    return parsed && typeof parsed === "object" ? parsed : {};
  } catch {
    return {};
  }
}

function readApiError(error, fallback) {
  const payload = error?.response?.data;
  const firstFieldError = payload?.errors ? Object.values(payload.errors).flat().find(Boolean) : "";
  const traceId = payload?.trace_id ?? payload?.data?.trace_id;
  const message = firstFieldError || payload?.message || error?.message || fallback;

  if (traceId && !String(message).includes(traceId)) {
    return `${message} Código de seguimiento: ${traceId}`;
  }

  return message;
}

function formatSignatureDate(value) {
  if (!value) {
    return "-";
  }

  try {
    return new Intl.DateTimeFormat("es-BO", {
      dateStyle: "medium",
      timeStyle: "short",
    }).format(new Date(value));
  } catch {
    return String(value);
  }
}

function signatureStatusMeta(signatureStatus) {
  const latestSigned = signatureStatus?.latest_signed;
  const latestSignature = signatureStatus?.latest_signature;

  if (latestSigned?.has_signed_file) {
    return {
      label: "Firmado",
      className: "border-emerald-200 bg-emerald-50 text-emerald-700",
    };
  }

  if (latestSignature?.status && !["signed", "failed", "error", "cancelled"].includes(latestSignature.status)) {
    return {
      label: "Firma pendiente",
      className: "border-amber-200 bg-amber-50 text-amber-700",
    };
  }

  if (latestSignature?.status === "failed" || latestSignature?.status === "error") {
    return {
      label: "Firma con error",
      className: "border-rose-200 bg-rose-50 text-rose-700",
    };
  }

  return {
    label: "Sin firma",
    className: "border-slate-200 bg-slate-50 text-slate-600",
  };
}

function signerLabel(signature) {
  const validationRecords = Array.isArray(signature?.validation_records) ? signature.validation_records : [];
  const citizenshipUser = signature?.citizenship_user;
  const validationNames = validationRecords
    .map((record) => [
      record?.nombres,
      record?.primer_apellido,
      record?.segundo_apellido,
    ].filter(Boolean).join(" ").trim())
    .filter(Boolean);

  if (validationNames.length > 0) {
    return validationNames.join(", ");
  }

  const fullName = [
    citizenshipUser?.nombre,
  ].filter(Boolean).join(" ");

  return fullName || citizenshipUser?.nombre || signature?.user_name || "Sin firmante registrado";
}

function resolveAllowedPdfUrl(rawUrl) {
  if (!rawUrl) {
    return null;
  }

  try {
    const url = new URL(rawUrl, window.location.origin);

    if (url.origin !== apiOrigin) {
      return null;
    }

    return url.toString();
  } catch {
    return null;
  }
}

export default function PdfViewerPage() {
  const [searchParams] = useSearchParams();
  const pdfUrl = useMemo(() => resolveAllowedPdfUrl(searchParams.get("url")), [searchParams]);
  const title = searchParams.get("title") || "Documento PDF";
  const fallbackMessage = searchParams.get("message") || DEFAULT_ERROR_MESSAGE;
  const showChrome = searchParams.get("chrome") !== "0";
  const signatureProjectId = searchParams.get("sign_project");
  const signatureReportKey = searchParams.get("sign_report");
  const signatureParametersRaw = searchParams.get("sign_params") || "{}";
  const signatureParameters = useMemo(() => parseJsonParam(signatureParametersRaw), [signatureParametersRaw]);
  const hasSignatureContext = Boolean(signatureProjectId && signatureReportKey);
  const [blobUrl, setBlobUrl] = useState("");
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(true);
  const [reloadKey, setReloadKey] = useState(0);
  const [signatureStatus, setSignatureStatus] = useState(null);
  const [signatureLoading, setSignatureLoading] = useState(false);
  const [signatureError, setSignatureError] = useState("");
  const [signing, setSigning] = useState(false);
  const [historyOpen, setHistoryOpen] = useState(false);
  const [signatureHistory, setSignatureHistory] = useState([]);
  const [historyLoading, setHistoryLoading] = useState(false);
  const [historyError, setHistoryError] = useState("");

  const latestSigned = signatureStatus?.latest_signed;
  const hasSignedPdf = Boolean(latestSigned?.has_signed_file);
  const statusMeta = signatureStatusMeta(signatureStatus);

  const canSignPdf = Boolean(
    !loading
      && !error
      && blobUrl
      && hasSignatureContext
      && signatureStatus?.project_status_allows_signing
      && signatureStatus?.report?.is_enabled
      && signatureStatus?.report?.can_sign,
  );
  const signatureRestrictionMessage = useMemo(() => {
    if (!hasSignatureContext || signatureLoading || signatureError || !signatureStatus?.report) {
      return "";
    }

    if (!signatureStatus.report.is_enabled) {
      return "La firma digital no está habilitada para este reporte.";
    }

    if (!signatureStatus.project_status_allows_signing) {
      return "Este reporte solo puede firmarse cuando el proyecto está finalizado.";
    }

    if (!signatureStatus.report.can_sign) {
      return "Su rol no tiene permiso para firmar este reporte.";
    }

    return "";
  }, [hasSignatureContext, signatureError, signatureLoading, signatureStatus]);

  const openSignedPdf = (signature = latestSigned) => {
    if (!signature?.has_signed_file) {
      return;
    }

    const signedUrl = projectService.latestSignedReportUrl(
      signature.project_id || signatureProjectId,
      signature.report_key || signatureReportKey,
      signature.parameters || signatureParameters,
    );

    openUrlInNewTab(buildPdfViewerUrl(signedUrl, {
      title: "PDF firmado",
      signature: {
        projectId: signature.project_id || signatureProjectId,
        reportKey: signature.report_key || signatureReportKey,
        parameters: signature.parameters || signatureParameters,
      },
    }));
  };

  useEffect(() => {
    let cancelled = false;
    let currentBlobUrl = "";

    async function loadPdf() {
      setLoading(true);
      setError("");
      setBlobUrl("");

      if (!pdfUrl) {
        setError("La ruta del PDF no es válida.");
        setLoading(false);
        return;
      }

      try {
        const token = localStorage.getItem("token");
        const response = await fetch(pdfUrl, {
          credentials: "include",
          headers: {
            Accept: "application/pdf, application/json;q=0.9, text/plain;q=0.8",
            ...(token ? { Authorization: `Bearer ${token}` } : {}),
          },
        });

        if (!response.ok) {
          const backendMessage = await readErrorMessage(response);
          throw new Error(backendMessage || fallbackMessage);
        }

        const contentType = response.headers.get("content-type") || "";
        const disposition = response.headers.get("content-disposition") || "";

        if (
          contentType
          && !contentType.includes("application/pdf")
          && !contentType.includes("application/octet-stream")
          && !disposition.toLowerCase().includes(".pdf")
        ) {
          const backendMessage = await readErrorMessage(response);
          throw new Error(backendMessage || fallbackMessage);
        }

        const blob = await response.blob();

        if (!blob.size) {
          throw new Error(fallbackMessage);
        }

        currentBlobUrl = URL.createObjectURL(blob);

        if (!cancelled) {
          setBlobUrl(currentBlobUrl);
        }
      } catch (loadError) {
        if (!cancelled) {
          setError(loadError?.message || fallbackMessage);
        }
      } finally {
        if (!cancelled) {
          setLoading(false);
        }
      }
    }

    loadPdf();

    return () => {
      cancelled = true;

      if (currentBlobUrl) {
        URL.revokeObjectURL(currentBlobUrl);
      }
    };
  }, [fallbackMessage, pdfUrl, reloadKey]);

  useEffect(() => {
    let cancelled = false;

    async function loadSignatureStatus() {
      if (!hasSignatureContext) {
        setSignatureStatus(null);
        setSignatureError("");
        return;
      }

      setSignatureLoading(true);
      setSignatureError("");

      try {
        const response = await projectService.signatureStatus(signatureProjectId, {
          report_key: signatureReportKey,
          ...signatureParameters,
        });

        if (!cancelled) {
          setSignatureStatus(response?.data ?? null);
        }
      } catch (statusError) {
        if (!cancelled) {
          setSignatureStatus(null);
          setSignatureError(readApiError(statusError, "No se pudo validar si este PDF puede firmarse."));
        }
      } finally {
        if (!cancelled) {
          setSignatureLoading(false);
        }
      }
    }

    void loadSignatureStatus();

    return () => {
      cancelled = true;
    };
  }, [hasSignatureContext, signatureParameters, signatureProjectId, signatureReportKey]);

  const handleSignPdf = async () => {
    if (!canSignPdf || signing) {
      return;
    }

    setSigning(true);
    setSignatureError("");

    try {
      const response = await projectService.signReport(signatureProjectId, signatureReportKey, signatureParameters);
      const redirectUrl = response?.data?.redirect_url || response?.data?.signature?.redirect_url;
      const signature = response?.data?.signature;

      if (!redirectUrl) {
        throw new Error("Ciudadanía Digital no devolvió una URL de firma.");
      }

      if (signature?.id) {
        const signatureCode = signature.code || `code-${signature.id}`;
        const pendingSignature = JSON.stringify({
          id: signature.id,
          trace_id: signature.trace_id,
          code: signatureCode,
          project_id: signature.project_id,
          report_key: signature.report_key,
          parameters: signature.parameters || signatureParameters || {},
        });

        sessionStorage.setItem(SIGNATURE_SESSION_KEY, pendingSignature);
        localStorage.setItem(SIGNATURE_SESSION_KEY, pendingSignature);
      }

      window.location.assign(redirectUrl);
    } catch (signError) {
      setSignatureError(readApiError(signError, "No se pudo iniciar la firma digital."));
    } finally {
      setSigning(false);
    }
  };

  const handleOpenHistory = async () => {
    if (!hasSignatureContext) {
      return;
    }

    setHistoryOpen(true);
    setHistoryLoading(true);
    setHistoryError("");

    try {
      const response = await projectService.reportSignatures(signatureProjectId, signatureReportKey, signatureParameters);
      setSignatureHistory(response?.data?.items ?? []);
    } catch (historyLoadError) {
      setHistoryError(readApiError(historyLoadError, "No se pudo cargar el historial de firmas."));
      setSignatureHistory([]);
    } finally {
      setHistoryLoading(false);
    }
  };

  return (
    <main className="flex min-h-screen flex-col bg-slate-100 text-slate-950">
      {showChrome ? (
        <header className="flex items-center justify-between border-b border-slate-200 bg-white px-6 py-4 shadow-sm">
          <div className="flex items-center gap-3">
            <span className="flex h-10 w-10 items-center justify-center rounded-full bg-slate-950 text-white">
              <FileText className="h-5 w-5" />
            </span>
            <div>
              <h1 className="text-lg font-semibold">{title}</h1>
              <p className="text-sm text-slate-500">SIPRE</p>
            </div>
          </div>
          <div className="flex items-center gap-3">
            {!error && hasSignatureContext && !signatureLoading ? (
              <span className={`inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-semibold ${statusMeta.className}`}>
                {hasSignedPdf ? <CheckCircle2 className="h-3.5 w-3.5" /> : null}
                {statusMeta.label}
              </span>
            ) : null}
            {signatureLoading && !error ? (
              <span className="inline-flex items-center gap-2 text-sm text-slate-500">
                <Loader2 className="h-4 w-4 animate-spin" />
                Validando firma
              </span>
            ) : null}
            {!error && signatureError ? (
              <span className="max-w-sm text-right text-xs leading-5 text-red-600">{signatureError}</span>
            ) : null}
            {!error && signatureRestrictionMessage ? (
              <span className="max-w-sm text-right text-xs leading-5 text-slate-500">{signatureRestrictionMessage}</span>
            ) : null}
            {!error && hasSignedPdf ? (
              <button
                type="button"
                onClick={() => openSignedPdf()}
                className="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-medium text-emerald-700 shadow-sm transition hover:bg-emerald-100"
              >
                <ExternalLink className="h-4 w-4" />
                Abrir PDF firmado
              </button>
            ) : null}
            {!error && hasSignatureContext ? (
              <button
                type="button"
                onClick={handleOpenHistory}
                className="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50"
              >
                <History className="h-4 w-4" />
                Historial
              </button>
            ) : null}
            {canSignPdf ? (
              <button
                type="button"
                onClick={handleSignPdf}
                disabled={signing}
                className="inline-flex items-center gap-2 rounded-full bg-slate-950 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-70"
              >
                {signing ? <Loader2 className="h-4 w-4 animate-spin" /> : <FileSignature className="h-4 w-4" />}
                Firmar este PDF
              </button>
            ) : null}
            {error ? (
              <button
                type="button"
                onClick={() => setReloadKey((value) => value + 1)}
                className="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50"
              >
                <RefreshCw className="h-4 w-4" />
                Reintentar
              </button>
            ) : null}
          </div>
        </header>
      ) : null}

      <section className="flex min-h-0 flex-1">
        {loading ? (
          <div className="flex flex-1 flex-col items-center justify-center gap-4 p-8 text-center">
            <Loader2 className="h-10 w-10 animate-spin text-slate-500" />
            <div>
              <p className="text-lg font-semibold">Generando PDF...</p>
              <p className="mt-1 text-sm text-slate-500">Esto puede tardar unos segundos.</p>
            </div>
          </div>
        ) : null}

        {!loading && error ? (
          <div className="flex flex-1 items-center justify-center p-8">
            <div className="w-full max-w-xl rounded-2xl border border-red-100 bg-white p-6 text-center shadow-sm">
              <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-red-50 text-red-600">
                <AlertCircle className="h-6 w-6" />
              </div>
              <h2 className="mt-4 text-xl font-semibold">No se pudo abrir el PDF</h2>
              <p className="mt-2 text-sm leading-6 text-slate-600">{error}</p>
            </div>
          </div>
        ) : null}

        {!loading && !error && blobUrl ? (
          <iframe
            title={title}
            src={blobUrl}
            className={`${showChrome ? "h-[calc(100vh-73px)]" : "h-screen"} w-full border-0 bg-white`}
          />
        ) : null}
      </section>

      {historyOpen ? (
        <div className="fixed inset-0 z-[90] flex items-center justify-center bg-slate-950/30 p-4 backdrop-blur-sm">
          <div className="w-full max-w-3xl rounded-2xl border border-slate-200 bg-white shadow-2xl">
            <div className="flex items-start justify-between gap-4 border-b border-slate-200 p-5">
              <div>
                <h2 className="text-lg font-semibold">Historial de firmas</h2>
                <p className="mt-1 text-sm text-slate-500">Firmas del reporte y parámetros visualizados.</p>
              </div>
              <button
                type="button"
                onClick={() => setHistoryOpen(false)}
                className="rounded-full p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-900"
                aria-label="Cerrar historial"
              >
                <X className="h-4 w-4" />
              </button>
            </div>

            <div className="max-h-[60vh] overflow-y-auto p-5">
              {historyLoading ? (
                <div className="flex items-center justify-center gap-2 rounded-xl border border-dashed border-slate-200 p-8 text-sm text-slate-500">
                  <Loader2 className="h-4 w-4 animate-spin" />
                  Cargando historial...
                </div>
              ) : null}

              {!historyLoading && historyError ? (
                <div className="rounded-xl border border-red-100 bg-red-50 p-4 text-sm text-red-700">{historyError}</div>
              ) : null}

              {!historyLoading && !historyError && signatureHistory.length === 0 ? (
                <div className="rounded-xl border border-dashed border-slate-200 p-8 text-center text-sm text-slate-500">
                  No hay firmas registradas para estos parámetros.
                </div>
              ) : null}

              {!historyLoading && !historyError && signatureHistory.length > 0 ? (
                <div className="space-y-3">
                  {signatureHistory.map((signature) => (
                    <div key={signature.id} className="rounded-xl border border-slate-200 bg-slate-50/60 p-4">
                      <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                          <div className="flex flex-wrap items-center gap-2">
                            <span className={`rounded-full px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.16em] ${
                              signature.status === "signed"
                                ? "bg-emerald-100 text-emerald-700"
                                : signature.status === "failed" || signature.status === "error"
                                  ? "bg-rose-100 text-rose-700"
                                  : "bg-amber-100 text-amber-700"
                            }`}
                            >
                              {signature.status === "signed" ? "Firmado" : signature.status}
                            </span>
                            <span className="text-xs text-slate-500">{formatSignatureDate(signature.signed_at || signature.sent_at)}</span>
                          </div>
                          <div className="mt-2 text-sm font-medium text-slate-900">{signerLabel(signature)}</div>
                          <div className="mt-1 text-xs text-slate-500">SIPRE: {signature.user_name || "Sin usuario"} · Seguimiento: {signature.trace_id}</div>
                        </div>

                        {signature.has_signed_file ? (
                          <button
                            type="button"
                            onClick={() => openSignedPdf(signature)}
                            className="inline-flex shrink-0 items-center justify-center gap-2 rounded-full border border-emerald-200 bg-white px-3 py-2 text-sm font-medium text-emerald-700 transition hover:bg-emerald-50"
                          >
                            <ExternalLink className="h-4 w-4" />
                            Abrir firmado
                          </button>
                        ) : null}
                      </div>
                    </div>
                  ))}
                </div>
              ) : null}
            </div>
          </div>
        </div>
      ) : null}
    </main>
  );
}
