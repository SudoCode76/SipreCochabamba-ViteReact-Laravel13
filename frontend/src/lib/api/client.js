
import axios from "axios";

const apiClient = axios.create({
  baseURL: import.meta.env.VITE_API_URL || "http://localhost:8000/api",
  headers: {
    Accept: "application/json",
  },
});

// Interceptor para añadir el token de autenticación (si existe)
apiClient.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem("token");

    if (config.data instanceof FormData) {
      delete config.headers["Content-Type"];
    }

    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  (error) => Promise.reject(error)
);

apiClient.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response && error.response.status === 401) {
      localStorage.removeItem("token");
      // Optionally redirect to login
      window.location.href = "/login";
    }
    if (error.response && error.response.status === 403) {
      error.message = error.response.data?.message || "No tiene permisos para realizar esta acción.";
    }
    return Promise.reject(error);
  }
);

export default apiClient;
