import { useEffect, useMemo, useState } from "react";
import { FileSignature } from "lucide-react";
import { useSearchParams } from "react-router-dom";

import apiClient from "@/lib/api/client";
import { projectService } from "@/modules/projects/services/project.service";

const SIGNATURE_SESSION_KEY = "sipre:ciudadania-digital:signature";

const PHASE_CONFIG = {
  login: {
    endpoint: "/v1/citizenship/signature/login-callback",
    initialStatus: "Procesando inicio de sesión de Ciudadanía Digital...",
    redirectStatus: "Redirigiendo a la firma del documento...",
    successStatus: "Autenticación validada correctamente.",
    redirectField: "redirect_url",
  },
  approval: {
    endpoint: "/v1/citizenship/signature/approval-callback",
    initialStatus: "Procesando documento firmado...",
    redirectStatus: "Cerrando sesión de Ciudadanía Digital...",
    successStatus: "Documento firmado guardado correctamente.",
    redirectField: "logout_redirect_url",
  },
  logout: {
    endpoint: "/v1/citizenship/signature/logout-callback",
    initialStatus: "Cerrando sesión de Ciudadanía Digital...",
    redirectStatus: "",
    successStatus: "Firma finalizada y sesión de Ciudadanía Digital cerrada.",
    redirectField: "",
  },
};

function readApiError(error) {
  const payload = error?.response?.data;
  const firstFieldError = payload?.errors ? Object.values(payload.errors).flat().find(Boolean) : "";

  return firstFieldError || payload?.message || error?.message || "No se pudo continuar con la firma digital.";
}

function readPendingSignature() {
  const storages = [sessionStorage, localStorage];

  for (const storage of storages) {
    try {
      const raw = storage.getItem(SIGNATURE_SESSION_KEY);

      if (raw) {
        return JSON.parse(raw);
      }
    } catch {
      // Continue with the next storage fallback.
    }
  }

  return null;
}

function clearPendingSignature() {
  sessionStorage.removeItem(SIGNATURE_SESSION_KEY);
  localStorage.removeItem(SIGNATURE_SESSION_KEY);
}

function buildPdfViewerUrl(url, title = "PDF firmado") {
  const viewerUrl = new URL("/pdf-viewer", window.location.origin);
  viewerUrl.searchParams.set("url", url);
  viewerUrl.searchParams.set("title", title);

  return viewerUrl.toString();
}

export default function CitizenshipSignatureCallbackPage({ phase = "login" }) {
  const [searchParams] = useSearchParams();
  const config = PHASE_CONFIG[phase] || PHASE_CONFIG.login;
  const [status, setStatus] = useState(config.initialStatus);
  const [error, setError] = useState("");
  const [signedReportUrl, setSignedReportUrl] = useState("");

  const callbackPayload = useMemo(() => {
    const payload = {};

    searchParams.forEach((value, key) => {
      payload[key] = value;
    });

    if (!payload.signature) {
      const pendingSignature = readPendingSignature();

      if (pendingSignature?.id) {
        payload.signature = String(pendingSignature.id);
      }
    }

    return payload;
  }, [searchParams]);

  useEffect(() => {
    let cancelled = false;

    async function continueSignature() {
      try {
        setStatus(config.initialStatus);
        const response = await apiClient.post(config.endpoint, callbackPayload, {
          skipAuthRedirect: true,
        });
        const redirectUrl = config.redirectField ? response.data?.data?.[config.redirectField] : "";

        if (cancelled) {
          return;
        }

        if (redirectUrl) {
          setStatus(config.redirectStatus);
          window.location.href = redirectUrl;
          return;
        }

        const signature = response.data?.data?.signature;

        if (signature?.status === "signed" && signature?.project_id && signature?.report_key) {
          const signedUrl = projectService.latestSignedReportUrl(signature.project_id, signature.report_key, signature.parameters || {});
          setSignedReportUrl(buildPdfViewerUrl(signedUrl, "PDF firmado"));
        }

        if (phase === "logout" || (signature?.status === "signed" && !redirectUrl)) {
          clearPendingSignature();
        }

        setStatus(response.data?.message || config.successStatus);
      } catch (callbackError) {
        if (!cancelled) {
          setError(readApiError(callbackError));
          setStatus("");
        }
      }
    }

    void continueSignature();

    return () => {
      cancelled = true;
    };
  }, [callbackPayload, config.endpoint, config.initialStatus, config.redirectField, config.redirectStatus, config.successStatus, phase]);

  return (
    <main className="flex min-h-screen items-center justify-center bg-muted/30 p-6">
      <section className="w-full max-w-xl rounded-2xl border bg-background p-8 text-center shadow-sm">
        <div className="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-primary text-primary-foreground">
          <FileSignature className="h-7 w-7" aria-hidden="true" />
        </div>
        <h1 className="text-2xl font-semibold text-foreground">Firma digital</h1>
        {status ? (
          <p className="mt-3 text-sm text-muted-foreground">{status}</p>
        ) : null}
        {error ? (
          <div className="mt-5 rounded-xl border border-destructive/30 bg-destructive/5 p-4 text-sm text-destructive">
            {error}
          </div>
        ) : null}
        {signedReportUrl ? (
          <a
            className="mt-5 inline-flex rounded-full bg-primary px-5 py-2 text-sm font-semibold text-primary-foreground"
            href={signedReportUrl}
            target="_blank"
            rel="noreferrer"
          >
            Abrir PDF firmado
          </a>
        ) : null}
      </section>
    </main>
  );
}
