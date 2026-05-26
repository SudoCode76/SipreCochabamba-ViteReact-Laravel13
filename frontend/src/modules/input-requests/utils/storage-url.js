import { apiOrigin } from "@/lib/api/client";

const storageOrigin = import.meta.env.VITE_STORAGE_BASE_URL || apiOrigin;

export function storageUrl(pathOrUrl) {
  if (!pathOrUrl) {
    return null;
  }

  if (/^https?:\/\//i.test(pathOrUrl)) {
    return pathOrUrl;
  }

  const cleanPath = String(pathOrUrl).replace(/^\/+/, "");
  const storagePath = cleanPath.startsWith("storage/") ? cleanPath : `storage/${cleanPath}`;

  return `${storageOrigin.replace(/\/+$/, "")}/${storagePath}`;
}
