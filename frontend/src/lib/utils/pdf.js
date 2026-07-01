import apiClient, { apiBaseUrl } from "@/lib/api/client";

export function buildApiUrl(path, params = {}) {
  const base = apiBaseUrl.endsWith("/") ? apiBaseUrl : `${apiBaseUrl}/`;
  const url = new URL(String(path).replace(/^\/+/, ""), new URL(base, window.location.origin));

  Object.entries(params).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== "") {
      url.searchParams.set(key, value);
    }
  });

  return url.toString();
}

export function openUrlInNewTab(url) {
  const openedWindow = window.open("about:blank", "_blank");

  if (openedWindow) {
    openedWindow.opener = null;
    openedWindow.location.href = url;
  }

  if (!openedWindow) {
    window.location.assign(url);
  }

  return openedWindow;
}

export function buildPdfViewerUrl(url, options = {}) {
  const viewerUrl = new URL("/pdf-viewer", window.location.origin);
  const signature = options.signature;

  viewerUrl.searchParams.set("url", url);

  if (options.title) {
    viewerUrl.searchParams.set("title", options.title);
  }

  if (options.errorMessage) {
    viewerUrl.searchParams.set("message", options.errorMessage);
  }

  if (options.chrome === false) {
    viewerUrl.searchParams.set("chrome", "0");
  }

  if (signature?.projectId && signature?.reportKey) {
    viewerUrl.searchParams.set("sign_project", String(signature.projectId));
    viewerUrl.searchParams.set("sign_report", signature.reportKey);

    if (signature.parameters && Object.keys(signature.parameters).length > 0) {
      viewerUrl.searchParams.set("sign_params", JSON.stringify(signature.parameters));
    }
  }

  return viewerUrl.toString();
}

export function openPdfViewer(url, options = {}) {
  return openUrlInNewTab(buildPdfViewerUrl(url, options));
}

export function openPdfInNewTab(path, params = {}) {
  return openPdfViewer(buildApiUrl(path, params));
}

function filenameFromContentDisposition(contentDisposition) {
  const match = contentDisposition?.match(/filename\*=UTF-8''([^;]+)|filename="?([^"]+)"?/i);
  const filename = match?.[1] || match?.[2];

  return filename ? decodeURIComponent(filename) : "reporte.xlsx";
}

export async function downloadUrl(url) {
  const response = await apiClient.get(url, {
    responseType: "blob",
    headers: {
      Accept: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/json",
    },
  });
  const blobUrl = URL.createObjectURL(response.data);
  const link = document.createElement("a");
  link.href = blobUrl;
  link.download = filenameFromContentDisposition(response.headers["content-disposition"]);
  link.rel = "noopener noreferrer";
  link.style.display = "none";
  document.body.appendChild(link);
  link.click();
  link.remove();
  URL.revokeObjectURL(blobUrl);
}
