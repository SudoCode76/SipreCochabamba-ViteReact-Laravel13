import { useEffect, useMemo, useRef, useState } from "react";
import { AlertCircle, AlertTriangle, CheckCircle2, ExternalLink, FileSignature, FileText, History, Loader2, RefreshCw, Save, X } from "lucide-react";
import { getDocument, GlobalWorkerOptions } from "pdfjs-dist";
import pdfWorkerUrl from "pdfjs-dist/build/pdf.worker.min.mjs?url";
import { useSearchParams } from "react-router-dom";

import { apiOrigin } from "@/lib/api/client";
import { buildPdfViewerUrl, openUrlInNewTab } from "@/lib/utils/pdf";
import { ThemeSwitcher } from "@/components/theme-switcher";
import { itemsService } from "@/modules/dashboard/services/items.service";
import { projectService } from "@/modules/projects/services/project.service";

const DEFAULT_ERROR_MESSAGE = "No se pudo generar el PDF.";
const SIGNATURE_SESSION_KEY = "sipre:ciudadania-digital:signature";

GlobalWorkerOptions.workerSrc = pdfWorkerUrl;

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

function apiAssetUrl(value) {
  try {
    const source = new URL(value, apiOrigin);

    return new URL(`${source.pathname}${source.search}`, apiOrigin).toString();
  } catch {
    return value;
  }
}

function physicalPositionsFromStatus(status) {
  const items = status?.items ?? [];

  if (!status?.page_assignment?.required) {
    return items.map((item) => ({
      ...item,
      page: Number(item.page ?? 1),
      x: Number(item.x ?? 10),
      y: Number(item.y ?? 10),
      width: Number(item.width ?? 56),
      height: Number(item.height ?? 28),
    }));
  }

  return items.flatMap((item) => (item.selected_pages ?? []).map((page) => {
    const position = item.page_positions?.[String(page)] ?? item;

    return {
      ...item,
      page: Number(page),
      x: Number(position.x ?? 10),
      y: Number(position.y ?? 10),
      width: Number(position.width ?? 56),
      height: Number(position.height ?? 28),
    };
  }));
}

function readMissingSignatureUsers(payload) {
  const missing = payload?.data?.missing_users ?? payload?.missing_users ?? [];

  if (missing.length > 0) {
    return missing;
  }

  return (payload?.errors?.signature_images ?? []).map((message, index) => {
    const [name, ...reason] = String(message).split(":");

    return {
      id: `signature-image-${index}`,
      full_name: name.trim(),
      reason: reason.join(":").trim() || String(message),
    };
  });
}

function signatureStatusMeta(signatureStatus) {
  const latestSigned = signatureStatus?.latest_signed;
  const latestSignature = signatureStatus?.latest_signature;

  if (signatureStatus?.is_signed_stale) {
    return {
      label: "Firma anterior disponible",
      className: "border-amber-200 bg-amber-50 text-amber-700",
    };
  }

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

function signerName(record) {
  return [
    record?.nombres,
    record?.primer_apellido,
    record?.segundo_apellido,
  ].filter(Boolean).join(" ").trim() || "Firmante sin nombre";
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

function boxesOverlap(a, b) {
  return a.x < b.x + b.width
    && a.x + a.width > b.x
    && a.y < b.y + b.height
    && a.y + a.height > b.y;
}

function clamp(value, min, max) {
  return Math.min(Math.max(value, min), max);
}

const MIN_PHYSICAL_SIGNATURE_WIDTH = 20;
const MIN_PHYSICAL_SIGNATURE_HEIGHT = 12;

export default function PdfViewerPage() {
  const [searchParams] = useSearchParams();
  const pdfUrl = useMemo(() => resolveAllowedPdfUrl(searchParams.get("url")), [searchParams]);
  const title = searchParams.get("title") || "Documento PDF";
  const isPhysicalSignedPdfView = title === "PDF con firmas físicas";
  const isAdjustPhysicalMode = searchParams.get("adjust_physical") === "1";
  const fallbackMessage = searchParams.get("message") || DEFAULT_ERROR_MESSAGE;
  const showChrome = searchParams.get("chrome") !== "0";
  const signatureSubject = searchParams.get("sign_subject") || (searchParams.get("sign_item") ? "item" : "project");
  const signatureProjectId = searchParams.get("sign_project");
  const signatureItemId = searchParams.get("sign_item");
  const signatureSubjectId = signatureSubject === "item" ? signatureItemId : signatureProjectId;
  const signatureService = signatureSubject === "item" ? itemsService : projectService;
  const signatureReportKey = searchParams.get("sign_report");
  const signatureParametersRaw = searchParams.get("sign_params") || "{}";
  const signatureParameters = useMemo(() => parseJsonParam(signatureParametersRaw), [signatureParametersRaw]);
  const requestedPageScope = searchParams.get("page_scope") === "all" ? "all" : "last";
  const hasSignatureContext = Boolean(signatureSubjectId && signatureReportKey);
  const [blobUrl, setBlobUrl] = useState("");
  const [physicalPdfData, setPhysicalPdfData] = useState(null);
  const [physicalPdfDocument, setPhysicalPdfDocument] = useState(null);
  const [physicalPdfLoading, setPhysicalPdfLoading] = useState(false);
  const [physicalPdfError, setPhysicalPdfError] = useState("");
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(true);
  const [reloadKey, setReloadKey] = useState(0);
  const [signatureStatus, setSignatureStatus] = useState(null);
  const [signatureLoading, setSignatureLoading] = useState(false);
  const [signatureChecked, setSignatureChecked] = useState(!hasSignatureContext);
  const [acceptedStaleNoticeKey, setAcceptedStaleNoticeKey] = useState("");
  const [signatureError, setSignatureError] = useState("");
  const [missingSignatureUsers, setMissingSignatureUsers] = useState([]);
  const [signing, setSigning] = useState(false);
  const [signAllPagesDialogOpen, setSignAllPagesDialogOpen] = useState(false);
  const [historyOpen, setHistoryOpen] = useState(false);
  const [latestSigners, setLatestSigners] = useState([]);
  const [historyLoading, setHistoryLoading] = useState(false);
  const [historyError, setHistoryError] = useState("");
  const [physicalStatus, setPhysicalStatus] = useState(null);
  const [physicalLoading, setPhysicalLoading] = useState(false);
  const [physicalError, setPhysicalError] = useState("");
  const [physicalPositions, setPhysicalPositions] = useState([]);
  const [physicalPositionsDirty, setPhysicalPositionsDirty] = useState(false);
  const [selectedPhysicalPage, setSelectedPhysicalPage] = useState(1);
  const [savingPhysicalPositions, setSavingPhysicalPositions] = useState(false);
  const [physicalPositionMessage, setPhysicalPositionMessage] = useState("");
  const [assignmentPages, setAssignmentPages] = useState([]);
  const [savingAssignment, setSavingAssignment] = useState(false);
  const [assignmentMessage, setAssignmentMessage] = useState("");
  const physicalCanvasRef = useRef(null);
  const physicalPdfCanvasRef = useRef(null);
  const dragRef = useRef(null);

  const latestSigned = signatureStatus?.latest_signed;
  const hasSignedPdf = Boolean(latestSigned?.has_signed_file);
  const isSignedStale = Boolean(signatureStatus?.is_signed_stale);
  const pendingSignature = physicalStatus?.pending_signature
    || (["pending", "auth_pending", "sent"].includes(signatureStatus?.latest_signature?.status)
      ? signatureStatus.latest_signature
      : null);
  const currentUserSignature = physicalStatus?.current_user_signature || signatureStatus?.current_user_signature;
  const cancelableSignature = currentUserSignature || (pendingSignature?.can_cancel ? pendingSignature : null);
  const projectPendingSignature = signatureSubject === "project" ? pendingSignature : null;
  const staleNoticeKey = `${pdfUrl || ""}|${reloadKey}|${signatureSubject || ""}|${signatureSubjectId || ""}|${signatureReportKey || ""}|${signatureParametersRaw}`;
  const staleNoticeAccepted = acceptedStaleNoticeKey === staleNoticeKey;
  const canRenderPdf = Boolean(
    !loading
      && !error
      && blobUrl
      && (!hasSignatureContext || signatureChecked)
      && (!isSignedStale || staleNoticeAccepted),
  );
  const statusMeta = signatureStatusMeta(signatureStatus);

  const showSignatureToolbar = Boolean(hasSignatureContext && !isPhysicalSignedPdfView && !isAdjustPhysicalMode);
  const canSignPdf = Boolean(
    showSignatureToolbar
      && !error
      && signatureStatus?.project_status_allows_signing
      && signatureStatus?.report?.is_enabled
      && signatureStatus?.report?.can_sign
      && !signatureStatus?.current_user_signed
      && !projectPendingSignature,
  );
  const signatureRestrictionMessage = useMemo(() => {
    if (!showSignatureToolbar || signatureLoading || signatureError || !signatureStatus?.report) {
      return "";
    }

    if (signatureStatus.signature_access?.allowed === false) {
      return signatureStatus.signature_access.message || "No está autorizado para firmar documentos de este proyecto.";
    }

    if (signatureStatus.current_user_signed) {
      return "Ya firmó digitalmente esta variante del reporte.";
    }

    if (projectPendingSignature) {
      const userName = projectPendingSignature.user_name || "otro firmante";

      if (currentUserSignature) {
        return "Su solicitud de firma quedó pendiente. Cancélela para volver a iniciar.";
      }

      return projectPendingSignature.can_cancel
        ? `Hay una firma pendiente de ${userName}. Puede cancelarla para desbloquear el documento.`
        : `Hay una firma pendiente de ${userName}. Espere a que finalice o solicite al administrador cancelarla.`;
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
  }, [currentUserSignature, projectPendingSignature, showSignatureToolbar, signatureError, signatureLoading, signatureStatus]);

  const applyPhysicalStatus = (status) => {
    const pageSizes = status?.page_sizes ?? [];

    setPhysicalStatus(status);
    setPhysicalPositionsDirty(false);
    setPhysicalPositions(physicalPositionsFromStatus(status));
    setPhysicalPositionMessage(status?.has_overlapping_signatures
      ? "Las firmas físicas no pueden superponerse. Acomódelas y guarde las posiciones antes de enviar."
      : "");
    setAssignmentPages((status?.page_assignment?.current_user_pages ?? []).map(Number));

    if (pageSizes.length > 0) {
      setSelectedPhysicalPage((page) => pageSizes.some((size) => Number(size.page) === Number(page)) ? page : Number(pageSizes.at(-1).page));
    }
  };

  const openSignedPdf = (signature = latestSigned) => {
    if (!signature?.has_signed_file) {
      return;
    }

    const signedSubject = signature.subject || signatureSubject;
    const signedSubjectId = signedSubject === "item"
      ? (signature.item_id || signatureItemId)
      : (signature.project_id || signatureProjectId);
    const signedService = signedSubject === "item" ? itemsService : projectService;

    const signedUrl = signedService.latestSignedReportUrl(
      signedSubjectId,
      signature.report_key || signatureReportKey,
      signature.parameters || signatureParameters,
    );

    openUrlInNewTab(buildPdfViewerUrl(signedUrl, {
      title: "PDF firmado",
      signature: {
        subject: signedSubject,
        ...(signedSubject === "item"
          ? { itemId: signedSubjectId }
          : { projectId: signedSubjectId }),
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
      setPhysicalPdfData(null);
      setPhysicalPdfDocument(null);
      setPhysicalPdfError("");
      setPhysicalPdfLoading(isAdjustPhysicalMode);

      if (!pdfUrl) {
        setError("La ruta del PDF no es válida.");
        setLoading(false);
        setPhysicalPdfLoading(false);
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
        const pdfData = isAdjustPhysicalMode ? await blob.arrayBuffer() : null;

        if (!cancelled) {
          setBlobUrl(currentBlobUrl);
          setPhysicalPdfData(pdfData);
        }
      } catch (loadError) {
        if (!cancelled) {
          setError(loadError?.message || fallbackMessage);
          setPhysicalPdfLoading(false);
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
  }, [fallbackMessage, isAdjustPhysicalMode, pdfUrl, reloadKey]);

  useEffect(() => {
    if (!isAdjustPhysicalMode || !physicalPdfData) {
      return undefined;
    }

    let cancelled = false;
    const loadingTask = getDocument({ data: new Uint8Array(physicalPdfData.slice(0)) });

    void loadingTask.promise
      .then((document) => {
        if (cancelled) {
          void document.destroy();
          return;
        }

        setPhysicalPdfDocument(document);
      })
      .catch(() => {
        if (!cancelled) {
          setPhysicalPdfError("No se pudo preparar el PDF para ajustar firmas.");
          setPhysicalPdfLoading(false);
        }
      });

    return () => {
      cancelled = true;
      void loadingTask.destroy();
    };
  }, [isAdjustPhysicalMode, physicalPdfData]);

  const currentPhysicalPageSize = useMemo(
    () => (physicalStatus?.page_sizes ?? []).find((page) => Number(page.page) === Number(selectedPhysicalPage)),
    [physicalStatus, selectedPhysicalPage],
  );
  const requiresPageAssignment = Boolean(physicalStatus?.page_assignment?.required);
  const currentUserPages = physicalStatus?.page_assignment?.current_user_pages ?? assignmentPages;
  const currentPageCanEdit = !requiresPageAssignment || currentUserPages.includes(Number(selectedPhysicalPage));
  const currentPagePositions = useMemo(
    () => physicalPositions.filter((position) => Number(position.page) === Number(selectedPhysicalPage)),
    [physicalPositions, selectedPhysicalPage],
  );
  const pageMap = physicalStatus?.page_map?.modules ?? [];
  const physicalPageIsTarget = physicalStatus?.page_scope === "all"
    || Number(selectedPhysicalPage) === Number(physicalStatus?.signature_page);
  const physicalPageOffsetY = useMemo(() => {
    if (!currentPhysicalPageSize || !physicalPageIsTarget) {
      return 0;
    }

    const targetPages = (physicalStatus?.page_sizes ?? []).filter((page) => (
      physicalStatus?.page_scope === "all" || Number(page.page) === Number(physicalStatus?.signature_page)
    ));
    const minimumHeight = Math.min(...targetPages.map((page) => Number(page.height)));

    return Math.max(0, Number(currentPhysicalPageSize.height) - minimumHeight);
  }, [currentPhysicalPageSize, physicalPageIsTarget, physicalStatus]);

  useEffect(() => {
    const canvas = physicalPdfCanvasRef.current;

    if (!isAdjustPhysicalMode || !physicalPdfDocument || !currentPhysicalPageSize || !canvas) {
      return undefined;
    }

    let cancelled = false;
    let renderTask = null;

    void physicalPdfDocument.getPage(selectedPhysicalPage)
      .then((page) => {
        if (cancelled) {
          return null;
        }

        const scale = Math.min(window.devicePixelRatio || 1, 2);
        const viewport = page.getViewport({ scale });

        canvas.width = Math.floor(viewport.width);
        canvas.height = Math.floor(viewport.height);
        renderTask = page.render({ canvas, viewport });

        return renderTask.promise;
      })
      .catch((renderError) => {
        if (!cancelled && renderError?.name !== "RenderingCancelledException") {
          setPhysicalPdfError("No se pudo mostrar la página seleccionada.");
        }
      })
      .finally(() => {
        if (!cancelled) {
          setPhysicalPdfLoading(false);
        }
      });

    return () => {
      cancelled = true;
      renderTask?.cancel();
    };
  }, [currentPhysicalPageSize, isAdjustPhysicalMode, physicalPdfDocument, selectedPhysicalPage]);

  useEffect(() => {
    let cancelled = false;

    async function loadSignatureStatus() {
      setSignatureChecked(false);

      if (!hasSignatureContext) {
        setSignatureStatus(null);
        setSignatureError("");
        setSignatureChecked(true);
        return;
      }

      setSignatureLoading(true);
      setSignatureError("");

      try {
        const response = await signatureService.signatureStatus(signatureSubjectId, {
          report_key: signatureReportKey,
          ...signatureParameters,
        });

        if (!cancelled) {
          setSignatureStatus(response?.data ?? null);
        }
      } catch (statusError) {
        if (!cancelled) {
          setSignatureStatus(null);
          setSignatureError(readMissingSignatureUsers(statusError?.response?.data).length > 0
            ? ""
            : readApiError(statusError, "No se pudo validar si este PDF puede firmarse."));
        }
      } finally {
        if (!cancelled) {
          setSignatureLoading(false);
          setSignatureChecked(true);
        }
      }
    }

    void loadSignatureStatus();

    return () => {
      cancelled = true;
    };
  }, [hasSignatureContext, signatureParameters, signatureReportKey, signatureService, signatureSubjectId]);

  useEffect(() => {
    let cancelled = false;

    async function loadPhysicalStatus() {
      if (!isAdjustPhysicalMode && (!showSignatureToolbar || !hasSignedPdf || isSignedStale)) {
        setPhysicalStatus(null);
        setPhysicalError("");
        return;
      }

      setPhysicalLoading(true);
      setPhysicalError("");

      try {
        const response = await signatureService.physicalSignatures(signatureSubjectId, signatureReportKey, signatureParameters);

        if (!cancelled) {
          applyPhysicalStatus(response?.data ?? null);
        }
      } catch (loadError) {
        if (!cancelled) {
          setPhysicalStatus(null);
          setPhysicalError(readApiError(loadError, "No se pudo cargar las firmas físicas."));
        }
      } finally {
        if (!cancelled) {
          setPhysicalLoading(false);
        }
      }
    }

    void loadPhysicalStatus();

    return () => {
      cancelled = true;
    };
  }, [isAdjustPhysicalMode, showSignatureToolbar, hasSignedPdf, isSignedStale, signatureParameters, signatureReportKey, signatureService, signatureSubjectId]);

  const submitSignPdf = async (pageScope, layoutHash = "") => {
    if (!(isAdjustPhysicalMode ? physicalStatus?.can_send : canSignPdf) || signing) {
      return;
    }

    setSignAllPagesDialogOpen(false);
    setSigning(true);
    setSignatureError("");

    try {
      const response = await signatureService.signReport(signatureSubjectId, signatureReportKey, {
        ...signatureParameters,
        sign_all_pages: pageScope === "all",
        ...(signatureSubject === "project" ? {
          page_scope: pageScope,
          ...(layoutHash ? { layout_hash: layoutHash } : {}),
        } : {}),
      });
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
          subject: signature.subject || signatureSubject,
          project_id: signature.project_id,
          item_id: signature.item_id,
          report_key: signature.report_key,
          parameters: signature.parameters || signatureParameters || {},
        });

        sessionStorage.setItem(SIGNATURE_SESSION_KEY, pendingSignature);
        localStorage.setItem(SIGNATURE_SESSION_KEY, pendingSignature);
      }

      window.location.assign(redirectUrl);
    } catch (signError) {
      const missing = readMissingSignatureUsers(signError?.response?.data);

      if (missing.length > 0) {
        setSignatureError("");
        setMissingSignatureUsers(missing);
      } else {
        setSignatureError(readApiError(signError, "No se pudo iniciar la firma digital."));
      }
    } finally {
      setSigning(false);
    }
  };

  const beginSignaturePreview = async (pageScope) => {
    if (signatureSubject !== "project" || signing) {
      void submitSignPdf(pageScope);
      return;
    }

    setSignAllPagesDialogOpen(false);
    setSigning(true);
    setSignatureError("");

    try {
      const response = await projectService.prepareSignaturePreview(
        signatureProjectId,
        signatureReportKey,
        { ...signatureParameters, page_scope: pageScope },
      );
      const preview = response?.data ?? response;

      if (!preview?.ready) {
        const missing = readMissingSignatureUsers(preview);

        if (missing.length > 0) {
          setSignatureError("");
          setMissingSignatureUsers(missing);
          return;
        }

        throw new Error("La previsualización de firmas no está lista.");
      }

      const previewUrl = projectService.signaturePreviewPdfUrl(signatureProjectId, signatureReportKey, signatureParameters);
      window.location.assign(buildPdfViewerUrl(previewUrl, {
        title: "Previsualización de firmas",
        adjustPhysicalSignatures: true,
        pageScope,
        layoutHash: preview.layout_hash,
        signature: {
          projectId: signatureProjectId,
          reportKey: signatureReportKey,
          parameters: signatureParameters,
        },
      }));
    } catch (previewError) {
      const missing = readMissingSignatureUsers(previewError?.response?.data);

      if (missing.length > 0) {
        setSignatureError("");
        setMissingSignatureUsers(missing);
        return;
      }

      setSignatureError(readApiError(previewError, "No se pudo preparar la previsualización de firmas."));
    } finally {
      setSigning(false);
    }
  };

  const handleSignPdf = () => {
    if (!canSignPdf || signing) {
      return;
    }

    if (!latestSigned?.has_signed_file || isSignedStale || !signatureStatus?.is_current_pdf_signed) {
      setSignAllPagesDialogOpen(true);
      return;
    }

    void submitSignPdf(latestSigned.page_scope || "last", latestSigned.layout_hash || "");
  };

  const handleOpenHistory = async () => {
    if (!hasSignatureContext) {
      return;
    }

    setHistoryOpen(true);
    setHistoryLoading(true);
    setHistoryError("");

    try {
      const response = await signatureService.reportSignatures(signatureSubjectId, signatureReportKey, signatureParameters);
      setLatestSigners(response?.data?.signers ?? []);
    } catch (historyLoadError) {
      setHistoryError(readApiError(historyLoadError, "No se pudo cargar el historial de firmas."));
      setLatestSigners([]);
    } finally {
      setHistoryLoading(false);
    }
  };

  const handleCancelSignature = async () => {
    if (!cancelableSignature?.id || signatureSubject !== "project") {
      return;
    }

    setSignatureError("");
    try {
      await projectService.cancelSignature(signatureProjectId, cancelableSignature.id);
      sessionStorage.removeItem(SIGNATURE_SESSION_KEY);
      localStorage.removeItem(SIGNATURE_SESSION_KEY);
      window.location.reload();
    } catch (cancelError) {
      setSignatureError(readApiError(cancelError, "No se pudo cancelar la firma pendiente."));
    }
  };

  const sharedPageBounds = useMemo(() => {
    const pageSizes = (physicalStatus?.page_sizes ?? []).filter((page) => (
      physicalStatus?.page_scope === "all" || Number(page.page) === Number(physicalStatus?.signature_page)
    ));

    if (pageSizes.length === 0) {
      return null;
    }

    return {
      x: 0,
      y: 0,
      width: Math.min(...pageSizes.map((page) => Number(page.width))),
      height: Math.min(...pageSizes.map((page) => Number(page.height))),
    };
  }, [physicalStatus]);

  const hasPhysicalOverlap = (positions) => positions.some((position, index) => (
    positions.slice(index + 1).some((next) => Number(position.page) === Number(next.page) && boxesOverlap(position, next))
  ));

  const updatePhysicalPosition = (id, page, updater) => {
    setPhysicalPositions((positions) => {
      const current = positions.find((position) => position.id === id && Number(position.page) === Number(page));

      if (!current) {
        return positions;
      }

      const candidate = updater(current);

      if (physicalStatus?.digital_zone && boxesOverlap(candidate, physicalStatus.digital_zone)) {
        setPhysicalPositionMessage("Las firmas físicas no pueden entrar en la zona de Ciudadanía Digital.");
        return positions;
      }

      if (positions.some((position) => (
        Number(position.page) === Number(page) && position.id !== id && boxesOverlap(candidate, position)
      ))) {
        setPhysicalPositionMessage("Las firmas físicas no pueden superponerse.");
        return positions;
      }

      setPhysicalPositionsDirty(true);
      setPhysicalPositionMessage("Hay cambios sin guardar.");

      return positions.map((position) => (
        position.id === id && Number(position.page) === Number(page) ? candidate : position
      ));
    });
  };

  const handlePhysicalPointerDown = (event, position) => {
    if (!physicalStatus?.can_adjust || !currentPageCanEdit || !physicalPageIsTarget || !currentPhysicalPageSize || !physicalCanvasRef.current) {
      return;
    }

    const rect = physicalCanvasRef.current.getBoundingClientRect();
    const pointerX = ((event.clientX - rect.left) / rect.width) * currentPhysicalPageSize.width;
    const pointerY = (((event.clientY - rect.top) / rect.height) * currentPhysicalPageSize.height) - physicalPageOffsetY;

    dragRef.current = {
      mode: "move",
      id: position.id,
      page: position.page,
      offsetX: pointerX - position.x,
      offsetY: pointerY - position.y,
      previous: position,
    };
    event.currentTarget.setPointerCapture(event.pointerId);
  };

  const handlePhysicalPointerMove = (event) => {
    const drag = dragRef.current;

    if (!drag || !currentPhysicalPageSize || !sharedPageBounds || !physicalCanvasRef.current) {
      return;
    }

    const rect = physicalCanvasRef.current.getBoundingClientRect();
    const pointerX = ((event.clientX - rect.left) / rect.width) * currentPhysicalPageSize.width;
    const pointerY = (((event.clientY - rect.top) / rect.height) * currentPhysicalPageSize.height) - physicalPageOffsetY;

    updatePhysicalPosition(drag.id, drag.page, (position) => {
      if (drag.mode === "resize") {
        return {
          ...position,
          width: Number(clamp(pointerX - position.x, MIN_PHYSICAL_SIGNATURE_WIDTH, sharedPageBounds.width - position.x).toFixed(2)),
          height: Number(clamp(pointerY - position.y, MIN_PHYSICAL_SIGNATURE_HEIGHT, sharedPageBounds.height - position.y).toFixed(2)),
        };
      }

      return {
        ...position,
        x: Number(clamp(pointerX - drag.offsetX, 0, sharedPageBounds.width - position.width).toFixed(2)),
        y: Number(clamp(pointerY - drag.offsetY, 0, sharedPageBounds.height - position.height).toFixed(2)),
      };
    });
  };

  const handlePhysicalResizePointerDown = (event, position) => {
    event.stopPropagation();

    if (!physicalStatus?.can_adjust || !currentPageCanEdit || !physicalPageIsTarget || !physicalCanvasRef.current) {
      return;
    }

    dragRef.current = {
      mode: "resize",
      id: position.id,
      page: position.page,
      previous: position,
    };
    event.currentTarget.setPointerCapture(event.pointerId);
  };

  const handlePhysicalPointerUp = () => {
    const drag = dragRef.current;

    if (!drag) {
      return;
    }

    setPhysicalPositions((positions) => {
      if (!hasPhysicalOverlap(positions)) {
        return positions;
      }

      setPhysicalPositionMessage("Las firmas físicas no pueden superponerse.");
      return positions.map((position) => (
        position.id === drag.id && Number(position.page) === Number(drag.page) ? drag.previous : position
      ));
    });
    dragRef.current = null;
  };

  const handleSavePhysicalPositions = async () => {
    if (!physicalStatus?.can_adjust) {
      setPhysicalError("Su rol no tiene permiso para ajustar firmas físicas.");
      return;
    }

    if (hasPhysicalOverlap(physicalPositions)) {
      setPhysicalPositionMessage("Las firmas físicas no pueden superponerse.");
      return;
    }

    setSavingPhysicalPositions(true);
    setPhysicalPositionMessage("");
    setPhysicalError("");

    try {
      const updatePositions = signatureSubject === "project"
        ? projectService.updateSignaturePreviewPositions
        : signatureService.updatePhysicalSignaturePositions;
      const response = await updatePositions(signatureSubjectId, signatureReportKey, {
        ...signatureParameters,
        ...(signatureSubject === "project" ? { layout_hash: physicalStatus.layout_hash } : {}),
        positions: physicalPositions
          .filter((position) => !requiresPageAssignment || currentUserPages.includes(Number(position.page)))
          .map(({ id, page, x, y, width, height }) => ({ id, page, x, y, width, height })),
      });
      applyPhysicalStatus(response?.data ?? null);
      setPhysicalPositionMessage("Posiciones guardadas.");
    } catch (saveError) {
      setPhysicalError(readApiError(saveError, "No se pudo guardar las posiciones."));
    } finally {
      setSavingPhysicalPositions(false);
    }
  };

  const toggleAssignmentPages = (pages) => {
    const normalized = [...new Set(pages.map(Number))].sort((a, b) => a - b);

    setAssignmentPages((current) => {
      const selected = normalized.every((page) => current.includes(page));

      return selected
        ? current.filter((page) => !normalized.includes(page))
        : [...new Set([...current, ...normalized])].sort((a, b) => a - b);
    });
    setAssignmentMessage("");
  };

  const handleSaveAssignmentPages = async () => {
    if (!requiresPageAssignment || assignmentPages.length === 0) {
      setAssignmentMessage("Seleccione al menos una página.");
      return;
    }

    setSavingAssignment(true);
    setAssignmentMessage("");

    try {
      const response = await projectService.updatePhysicalSignaturePages(
        signatureProjectId,
        signatureReportKey,
        assignmentPages,
      );
      applyPhysicalStatus(response?.data ?? null);
      setSelectedPhysicalPage(Number(assignmentPages[0]));
      setAssignmentMessage("Páginas confirmadas.");
    } catch (saveError) {
      setAssignmentMessage(readApiError(saveError, "No se pudieron guardar las páginas."));
    } finally {
      setSavingAssignment(false);
    }
  };

  return (
    <main className="theme-shell flex min-h-screen flex-col bg-background text-foreground">
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
            <ThemeSwitcher />
            {!error && showSignatureToolbar && !signatureLoading ? (
              <span className={`inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-semibold ${statusMeta.className}`}>
                {hasSignedPdf ? <CheckCircle2 className="h-3.5 w-3.5" /> : null}
                {statusMeta.label}
              </span>
            ) : null}
            {showSignatureToolbar && signatureLoading && !error ? (
              <span className="inline-flex items-center gap-2 text-sm text-slate-500">
                <Loader2 className="h-4 w-4 animate-spin" />
                Validando firma
              </span>
            ) : null}
            {!error && showSignatureToolbar && signatureError ? (
              <span className="max-w-sm text-right text-xs leading-5 text-red-600">{signatureError}</span>
            ) : null}
            {!error && isAdjustPhysicalMode && physicalError ? (
              <span className="max-w-sm text-right text-xs leading-5 text-red-600">{physicalError}</span>
            ) : null}
            {!error && signatureRestrictionMessage ? (
              <span className="max-w-sm text-right text-xs leading-5 text-slate-500">{signatureRestrictionMessage}</span>
            ) : null}
            {!error && isAdjustPhysicalMode && physicalLoading ? (
              <span className="inline-flex items-center gap-2 text-sm text-slate-500">
                <Loader2 className="h-4 w-4 animate-spin" />
                Cargando firmas
              </span>
            ) : null}
            {!error && isAdjustPhysicalMode ? (
              <button
                type="button"
                onClick={handleSavePhysicalPositions}
                disabled={!physicalStatus?.can_adjust || !physicalPositionsDirty || savingPhysicalPositions || physicalPositions.length === 0}
                className="inline-flex items-center gap-2 rounded-full bg-slate-950 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-70"
              >
                {savingPhysicalPositions ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}
                Guardar posiciones
              </button>
            ) : null}
            {!error && isAdjustPhysicalMode ? (
              <button
                type="button"
                onClick={() => void submitSignPdf(physicalStatus?.page_scope || requestedPageScope, physicalStatus?.layout_hash || "")}
                disabled={!physicalStatus?.can_send || physicalPositionsDirty || signing || savingPhysicalPositions}
                title={physicalPositionsDirty ? "Guarde las posiciones antes de enviar a Ciudadanía Digital." : undefined}
                className="inline-flex items-center gap-2 rounded-full bg-emerald-700 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-emerald-600 disabled:cursor-not-allowed disabled:opacity-70"
              >
                {signing ? <Loader2 className="h-4 w-4 animate-spin" /> : <FileSignature className="h-4 w-4" />}
                Enviar a Ciudadanía Digital
              </button>
            ) : null}
            {!error && cancelableSignature && signatureSubject === "project" ? (
              <button
                type="button"
                onClick={handleCancelSignature}
                className="inline-flex items-center gap-2 rounded-full border border-amber-300 bg-amber-50 px-4 py-2 text-sm font-medium text-amber-800 transition hover:bg-amber-100"
              >
                <X className="h-4 w-4" />
                Cancelar intento
              </button>
            ) : null}
            {!error && showSignatureToolbar ? (
              <button
                type="button"
                onClick={handleOpenHistory}
                className="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50"
              >
                <History className="h-4 w-4" />
                Ver firmantes
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

        {!loading && !error && blobUrl && hasSignatureContext && !signatureChecked ? (
          <div className="flex flex-1 flex-col items-center justify-center gap-4 p-8 text-center">
            <Loader2 className="h-10 w-10 animate-spin text-slate-500" />
            <div>
              <p className="text-lg font-semibold">Validando firma...</p>
              <p className="mt-1 text-sm text-slate-500">Revisando si existe un PDF firmado anterior.</p>
            </div>
          </div>
        ) : null}

        {canRenderPdf && isAdjustPhysicalMode ? (
          <div className="flex flex-1 flex-col overflow-hidden bg-slate-200">
            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-300 bg-white px-4 py-3">
              <div>
                <div className="flex items-center gap-2">
                  <span className="text-sm font-medium text-slate-700">Página</span>
                  <select
                    value={selectedPhysicalPage}
                    onChange={(event) => {
                      setPhysicalPdfError("");
                      setPhysicalPdfLoading(true);
                      setSelectedPhysicalPage(Number(event.target.value));
                    }}
                    className="h-9 rounded-lg border border-slate-200 bg-white px-3 text-sm"
                  >
                    {(physicalStatus?.page_sizes ?? []).map((page) => (
                      <option key={page.page} value={page.page}>{page.page}</option>
                    ))}
                  </select>
                </div>
                <p className="mt-2 text-xs text-slate-500">
                  Si mueve o redimensiona una firma, debe guardar las posiciones antes de enviar.
                </p>
              </div>
              {physicalPositionMessage ? (
                <span className={`text-sm ${physicalPositionMessage.includes("guardadas")
                  ? "text-emerald-700"
                  : physicalPositionMessage.includes("sin guardar") ? "text-amber-700" : "text-red-600"}`}>
                  {physicalPositionMessage}
                </span>
              ) : null}
            </div>

            <div className="flex min-h-0 flex-1 overflow-hidden">
              {requiresPageAssignment ? (
                <aside className="flex w-80 shrink-0 flex-col overflow-hidden border-r border-slate-300 bg-white p-4">
                  <div className="rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm">
                    <p className="font-semibold text-slate-800">Asignación de páginas</p>
                    <p className="mt-1 text-xs text-slate-600">
                      {physicalStatus?.page_assignment?.confirmed_signers ?? 0} de {physicalStatus?.page_assignment?.total_signers ?? 0} firmantes confirmaron.
                    </p>
                    <p className={`mt-1 text-xs ${(physicalStatus?.page_assignment?.uncovered_pages ?? []).length > 0 ? "text-amber-700" : "text-emerald-700"}`}>
                      {(physicalStatus?.page_assignment?.uncovered_pages ?? []).length > 0
                        ? `Sin cobertura: ${physicalStatus.page_assignment.uncovered_pages.join(", ")}`
                        : "Todas las páginas tienen cobertura."}
                    </p>
                    <ul className="mt-3 space-y-1 border-t border-slate-200 pt-2">
                      {(physicalStatus?.items ?? []).map((signer) => (
                        <li key={signer.id} className="flex items-center justify-between gap-2 text-xs">
                          <span className="truncate text-slate-700">{signer.user_name}</span>
                          <span className={signer.pages_confirmed_at ? "text-emerald-700" : "text-amber-700"}>
                            {signer.pages_confirmed_at ? "Confirmado" : "Pendiente"}
                          </span>
                        </li>
                      ))}
                    </ul>
                  </div>

                  <p className="mt-3 text-xs text-amber-700">
                    {physicalStatus?.page_assignment?.current_user_confirmed
                      ? "Puede cambiar su selección y volver a confirmarla."
                      : "Confirme su selección para mostrar y acomodar las firmas."}
                  </p>
                  <button
                    type="button"
                    onClick={() => void handleSaveAssignmentPages()}
                    disabled={savingAssignment || assignmentPages.length === 0}
                    className="mt-2 inline-flex w-full items-center justify-center gap-2 rounded-lg bg-slate-950 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
                  >
                    {savingAssignment ? <Loader2 className="h-4 w-4 animate-spin" /> : null}
                    Confirmar páginas y mostrar firmas
                  </button>
                  {assignmentMessage ? (
                    <p className={`mt-2 text-xs ${assignmentMessage.includes("confirmadas") ? "text-emerald-700" : "text-red-600"}`}>
                      {assignmentMessage}
                    </p>
                  ) : null}

                  <div className="mt-4 min-h-0 flex-1 space-y-4 overflow-y-auto pr-1">
                    {pageMap.map((module) => (
                      <section key={module.module_id} className="rounded-xl border border-slate-200 p-3">
                        <label className="flex cursor-pointer items-start gap-2 text-sm font-semibold text-slate-800">
                          <input
                            type="checkbox"
                            checked={(module.pages ?? []).every((page) => assignmentPages.includes(Number(page)))}
                            onChange={() => toggleAssignmentPages(module.pages ?? [])}
                            className="mt-0.5 h-4 w-4 rounded border-slate-300"
                          />
                          <span>{module.name}</span>
                        </label>

                        <div className="mt-3 space-y-3 border-l border-slate-200 pl-3">
                          {(module.items ?? []).map((item) => (
                            <div key={item.project_item_id}>
                              <label className="flex cursor-pointer items-start gap-2 text-xs font-medium text-slate-700">
                                <input
                                  type="checkbox"
                                  checked={(item.pages ?? []).every((page) => assignmentPages.includes(Number(page)))}
                                  onChange={() => toggleAssignmentPages(item.pages ?? [])}
                                  className="mt-0.5 h-4 w-4 rounded border-slate-300"
                                />
                                <span>{item.name} · hojas {item.start_page}–{item.end_page}</span>
                              </label>
                              <div className="mt-2 flex flex-wrap gap-1.5">
                                {(item.pages ?? []).map((page) => {
                                  const selected = assignmentPages.includes(Number(page));

                                  return (
                                    <button
                                      key={page}
                                      type="button"
                                      aria-pressed={selected}
                                      onClick={() => toggleAssignmentPages([page])}
                                      className={`h-7 min-w-7 rounded-md px-2 text-xs font-medium ${selected
                                        ? "bg-slate-950 text-white"
                                        : "border border-slate-200 bg-white text-slate-600 hover:bg-slate-50"}`}
                                    >
                                      {page}
                                    </button>
                                  );
                                })}
                              </div>
                            </div>
                          ))}
                        </div>
                      </section>
                    ))}
                  </div>
                </aside>
              ) : null}

              <div className="min-h-0 flex-1 overflow-auto p-6">
              {!physicalPdfError && requiresPageAssignment && !currentPageCanEdit ? (
                <p className="mx-auto mb-3 max-w-[920px] rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-center text-xs font-medium text-amber-800">
                  Esta hoja está en modo lectura porque no forma parte de su asignación confirmada.
                </p>
              ) : null}
              {currentPhysicalPageSize ? (
                <div
                  ref={physicalCanvasRef}
                  className="theme-fixed-light relative mx-auto bg-white shadow-2xl"
                  style={{
                    width: "min(100%, 920px)",
                    aspectRatio: `${currentPhysicalPageSize.width} / ${currentPhysicalPageSize.height}`,
                  }}
                >
                  <canvas
                    ref={physicalPdfCanvasRef}
                    role="img"
                    aria-label={`Página ${selectedPhysicalPage} del PDF firmado`}
                    className="pointer-events-none absolute inset-0 z-0 h-full w-full"
                  />
                  {physicalPdfLoading ? (
                    <div className="theme-fixed-light pointer-events-none absolute inset-0 z-20 flex items-center justify-center bg-white/70 text-sm text-slate-600">
                      <Loader2 className="mr-2 h-5 w-5 animate-spin" />
                      Cargando página...
                    </div>
                  ) : null}
                  {physicalPdfError ? (
                    <div className="theme-fixed-light absolute inset-0 z-20 flex items-center justify-center bg-white p-6 text-center text-sm text-red-600">
                      {physicalPdfError}
                    </div>
                  ) : null}
                  {!physicalPdfError && physicalPageIsTarget && physicalStatus?.digital_zone ? (
                    <div
                      className="pointer-events-none absolute z-[5] flex items-center justify-center border-2 border-dashed border-rose-400 bg-rose-100/55 text-xs font-semibold uppercase tracking-[0.16em] text-rose-700"
                      style={{
                        left: `${(physicalStatus.digital_zone.x / currentPhysicalPageSize.width) * 100}%`,
                        top: `${((physicalStatus.digital_zone.y + physicalPageOffsetY) / currentPhysicalPageSize.height) * 100}%`,
                        width: `${(physicalStatus.digital_zone.width / currentPhysicalPageSize.width) * 100}%`,
                        height: `${(physicalStatus.digital_zone.height / currentPhysicalPageSize.height) * 100}%`,
                      }}
                    >
                      Zona reservada para Ciudadanía Digital
                    </div>
                  ) : null}
                  {!physicalPdfError && physicalPageIsTarget && currentPagePositions.map((position) => (
                    <div
                      key={`${position.id}-${position.page}`}
                      role="button"
                      tabIndex={0}
                      onPointerDown={(event) => handlePhysicalPointerDown(event, position)}
                      onPointerMove={handlePhysicalPointerMove}
                      onPointerUp={handlePhysicalPointerUp}
                      onPointerCancel={handlePhysicalPointerUp}
                      className={`theme-fixed-light absolute z-10 touch-none overflow-hidden border-2 border-emerald-500 bg-white/80 shadow-lg ${currentPageCanEdit ? "cursor-move" : "cursor-default"}`}
                      style={{
                        left: `${(position.x / currentPhysicalPageSize.width) * 100}%`,
                        top: `${((position.y + physicalPageOffsetY) / currentPhysicalPageSize.height) * 100}%`,
                        width: `${(position.width / currentPhysicalPageSize.width) * 100}%`,
                        height: `${(position.height / currentPhysicalPageSize.height) * 100}%`,
                      }}
                      title={position.user_name || "Firma física"}
                    >
                      <div className="flex h-full w-full flex-col">
                        <div className="min-h-0 flex-1">
                          {position.signature_image_url ? (
                            <img
                              src={apiAssetUrl(position.signature_image_url)}
                              alt=""
                              className="h-full w-full object-contain"
                              draggable="false"
                            />
                          ) : (
                            <span className="text-xs text-slate-600">{position.user_name || "Firma"}</span>
                          )}
                        </div>
                        <div className="truncate px-0.5 text-center text-[8px] leading-none text-slate-900">
                          {position.user_name || "Usuario"}
                        </div>
                      </div>
                      {currentPageCanEdit ? (
                        <span
                          role="button"
                          tabIndex={0}
                          aria-label="Cambiar tamaño"
                          onPointerDown={(event) => handlePhysicalResizePointerDown(event, position)}
                          onPointerMove={handlePhysicalPointerMove}
                          onPointerUp={handlePhysicalPointerUp}
                          onPointerCancel={handlePhysicalPointerUp}
                          className="absolute bottom-0 right-0 h-4 w-4 cursor-nwse-resize rounded-tl bg-emerald-500 shadow"
                        />
                      ) : null}
                    </div>
                  ))}
                  {!physicalPdfError && !physicalPageIsTarget ? (
                    <div className="theme-fixed-light pointer-events-none absolute inset-0 z-10 flex items-center justify-center bg-white/45 p-6 text-center text-sm font-medium text-slate-600">
                      Las firmas se aplicarán únicamente en la última página.
                    </div>
                  ) : null}
                </div>
              ) : (
                <div className="rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500">
                  No hay firmas físicas para ajustar.
                </div>
              )}
              </div>
            </div>
          </div>
        ) : null}

        {canRenderPdf && !isAdjustPhysicalMode ? (
          <iframe
            title={title}
            src={blobUrl}
            className={`${showChrome ? "h-[calc(100vh-73px)]" : "h-screen"} theme-fixed-light w-full border-0 bg-white`}
          />
        ) : null}
      </section>

      {signAllPagesDialogOpen ? (
        <div className="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/40 p-4 backdrop-blur-sm">
          <div className="w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-6 text-center shadow-2xl">
            <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-slate-950 text-white">
              <FileSignature className="h-6 w-6" />
            </div>
            <h2 className="mt-4 text-xl font-semibold">Firma digital</h2>
            <p className="mt-2 text-sm leading-6 text-slate-600">
              ¿Quieres que las firmas físicas y la firma de Ciudadanía Digital aparezcan en todas las hojas?
            </p>
            <div className="mt-6 flex flex-col justify-center gap-3 sm:flex-row">
              <button
                type="button"
                onClick={() => void beginSignaturePreview("all")}
                className="inline-flex items-center justify-center rounded-full bg-slate-950 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-800"
              >
                Todas las hojas
              </button>
              <button
                type="button"
                onClick={() => void beginSignaturePreview("last")}
                className="inline-flex items-center justify-center rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
              >
                Solo la última hoja
              </button>
              <button
                type="button"
                onClick={() => setSignAllPagesDialogOpen(false)}
                className="inline-flex items-center justify-center rounded-full px-4 py-2 text-sm font-medium text-slate-500 transition hover:bg-slate-100 hover:text-slate-900"
              >
                Cancelar
              </button>
            </div>
          </div>
        </div>
      ) : null}

      {missingSignatureUsers.length > 0 ? (
        <div className="fixed inset-0 z-[110] flex items-center justify-center bg-slate-950/45 p-4 backdrop-blur-sm">
          <div
            role="dialog"
            aria-modal="true"
            aria-labelledby="missing-signatures-title"
            className="w-full max-w-lg rounded-2xl border border-red-200 bg-white p-6 shadow-2xl"
          >
            <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-red-50 text-red-600">
              <AlertTriangle className="h-6 w-6" />
            </div>
            <h2 id="missing-signatures-title" className="mt-4 text-center text-xl font-semibold">
              No se puede enviar a Ciudadanía Digital
            </h2>
            <p className="mt-2 text-center text-sm leading-6 text-slate-600">
              Todos los firmantes seleccionados deben tener una imagen de firma cargada en su perfil.
            </p>
            <ul className="mt-5 space-y-2">
              {missingSignatureUsers.map((user) => (
                <li key={user.id ?? user.user_id ?? user.full_name} className="rounded-xl border border-red-100 bg-red-50 px-4 py-3">
                  <p className="font-medium text-red-900">{user.full_name}</p>
                  <p className="mt-1 text-sm text-red-700">{user.reason}</p>
                </li>
              ))}
            </ul>
            <button
              type="button"
              autoFocus
              onClick={() => setMissingSignatureUsers([])}
              className="mt-6 inline-flex w-full items-center justify-center rounded-full bg-slate-950 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-800"
            >
              Entendido
            </button>
          </div>
        </div>
      ) : null}

      {!loading && !error && blobUrl && signatureChecked && isSignedStale && !staleNoticeAccepted ? (
        <div className="fixed inset-0 z-[95] flex items-center justify-center bg-slate-950/40 p-4 backdrop-blur-sm">
          <div className="w-full max-w-xl rounded-2xl border border-amber-200 bg-white p-6 text-center shadow-2xl">
            <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-amber-50 text-amber-600">
              <AlertTriangle className="h-6 w-6" />
            </div>
            <h2 className="mt-4 text-xl font-semibold">Firma anterior disponible</h2>
            <p className="mt-2 text-sm leading-6 text-slate-600">
              El proyecto fue modificado y existe un reporte anterior firmado. Se mostrará el reporte actual.
            </p>
            <div className="mt-6 flex flex-col justify-center gap-3 sm:flex-row">
              <button
                type="button"
                onClick={() => setAcceptedStaleNoticeKey(staleNoticeKey)}
                className="inline-flex items-center justify-center rounded-full bg-slate-950 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-800"
              >
                Continuar y ver PDF actual
              </button>
              {hasSignedPdf ? (
                <button
                  type="button"
                  onClick={() => openSignedPdf()}
                  className="inline-flex items-center justify-center gap-2 rounded-full border border-amber-200 bg-amber-50 px-4 py-2 text-sm font-medium text-amber-800 transition hover:bg-amber-100"
                >
                  <ExternalLink className="h-4 w-4" />
                  Abrir PDF firmado anterior
                </button>
              ) : null}
            </div>
          </div>
        </div>
      ) : null}

      {historyOpen ? (
        <div className="fixed inset-0 z-[90] flex items-center justify-center bg-slate-950/30 p-4 backdrop-blur-sm">
          <div className="w-full max-w-3xl rounded-2xl border border-slate-200 bg-white shadow-2xl">
            <div className="flex items-start justify-between gap-4 border-b border-slate-200 p-5">
              <div>
                <h2 className="text-lg font-semibold">Firmantes del PDF</h2>
                <p className="mt-1 text-sm text-slate-500">Identidades certificadas en el último PDF firmado.</p>
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

              {!historyLoading && !historyError && latestSigners.length === 0 ? (
                <div className="rounded-xl border border-dashed border-slate-200 p-8 text-center text-sm text-slate-500">
                  No hay firmantes validados para estos parámetros.
                </div>
              ) : null}

              {!historyLoading && !historyError && latestSigners.length > 0 ? (
                <div className="space-y-3">
                  <div className="rounded-xl border border-emerald-100 bg-emerald-50/70 p-4">
                    <h3 className="text-sm font-semibold text-emerald-900">Firmantes del PDF</h3>
                    <div className="mt-3 space-y-2">
                      {latestSigners.map((signer, index) => (
                        <div key={`${signer?.nro_documento || index}-${signer?.fecha_verificacion || index}`} className="rounded-lg bg-white/80 px-3 py-2 text-sm">
                          <div className="font-medium text-slate-900">{signerName(signer)}</div>
                          <div className="mt-0.5 text-xs text-slate-500">
                            Doc.: {signer?.nro_documento || "-"} · Sol.: {signer?.fecha_solicitud || "-"} · Verif.: {signer?.fecha_verificacion || "-"}
                          </div>
                        </div>
                      ))}
                    </div>
                  </div>
                </div>
              ) : null}

            </div>
          </div>
        </div>
      ) : null}
    </main>
  );
}
