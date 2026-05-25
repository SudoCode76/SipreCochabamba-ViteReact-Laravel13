const apiBaseUrl = import.meta.env.VITE_API_URL || "http://localhost:8000/api";

const apiOrigin = (() => {
  try {
    return new URL(apiBaseUrl).origin;
  } catch {
    return "http://localhost:8000";
  }
})();

export function storageUrl(pathOrUrl) {
  if (!pathOrUrl) {
    return null;
  }

  if (/^https?:\/\//i.test(pathOrUrl)) {
    return pathOrUrl;
  }

  const cleanPath = String(pathOrUrl).replace(/^\/+/, "");
  const storagePath = cleanPath.startsWith("storage/") ? cleanPath : `storage/${cleanPath}`;

  return `${apiOrigin}/${storagePath}`;
}
