
import axios from "axios";

// const defaultApiBaseUrl = import.meta.env.DEV ? "https://sipregamcapidev.cochabamba.bo/api" : "/api";
const defaultApiBaseUrl =
  import.meta.env.VITE_API_URL || "/api";

export const apiBaseUrl = import.meta.env.VITE_API_URL || defaultApiBaseUrl;
export const apiOrigin = (() => {
  try {
    return new URL(apiBaseUrl, window.location.origin).origin;
  } catch {
    return window.location.origin;
  }
})();

const apiClient = axios.create({
  baseURL: apiBaseUrl,
  withCredentials: true,
  withXSRFToken: true,
  headers: {
    Accept: "application/json",
  },
});

apiClient.interceptors.request.use(
  (config) => {
    if (config.data instanceof FormData) {
      delete config.headers["Content-Type"];
    }

    return config;
  },
  (error) => Promise.reject(error)
);

apiClient.interceptors.response.use(
  (response) => response,
  (error) => {
    const shouldRedirectToLogin = error.response?.status === 401
      && !error.config?.skipAuthRedirect
      && window.location.pathname !== "/login";

    if (shouldRedirectToLogin) {
      window.dispatchEvent(new CustomEvent("auth:unauthorized"));
    }
    if (error.response && error.response.status === 403) {
      error.message = error.response.data?.message || "No tiene permisos para realizar esta acción.";
    }
    return Promise.reject(error);
  }
);

export default apiClient;
