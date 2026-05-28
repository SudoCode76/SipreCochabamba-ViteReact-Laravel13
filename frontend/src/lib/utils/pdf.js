import { apiBaseUrl } from "@/lib/api/client";

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
  const openedWindow = window.open(url, "_blank");

  if (openedWindow) {
    openedWindow.opener = null;
  }

  if (!openedWindow) {
    window.location.assign(url);
  }

  return openedWindow;
}

export function openPdfViewer(url, options = {}) {
  const viewerUrl = new URL("/pdf-viewer", window.location.origin);

  viewerUrl.searchParams.set("url", url);

  if (options.title) {
    viewerUrl.searchParams.set("title", options.title);
  }

  if (options.errorMessage) {
    viewerUrl.searchParams.set("message", options.errorMessage);
  }

  return openUrlInNewTab(viewerUrl.toString());
}

export function openPdfInNewTab(path, params = {}) {
  return openPdfViewer(buildApiUrl(path, params));
}
