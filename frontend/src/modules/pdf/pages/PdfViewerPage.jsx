import { useEffect, useMemo, useState } from "react";
import { AlertCircle, FileText, Loader2, RefreshCw } from "lucide-react";
import { useSearchParams } from "react-router-dom";

import { apiOrigin } from "@/lib/api/client";

const DEFAULT_ERROR_MESSAGE = "No se pudo generar el PDF.";

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
  const [blobUrl, setBlobUrl] = useState("");
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(true);
  const [reloadKey, setReloadKey] = useState(0);

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
    </main>
  );
}
