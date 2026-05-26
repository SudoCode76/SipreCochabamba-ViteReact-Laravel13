import apiClient, { apiOrigin } from "@/lib/api/client";

const csrfClient = async () => {
  await apiClient.get(`${apiOrigin}/sanctum/csrf-cookie`, {
    baseURL: undefined,
  });
};

export const authService = {
  login: async (credentials) => {
    await csrfClient();

    const response = await apiClient.post("/v1/auth/login", {
      username: credentials.username,
      clave: credentials.password,
      device_name: "web",
    });
    return response.data;
  },

  getProfile: async (config = {}) => {
    const response = await apiClient.get("/v1/auth/me", config);
    return response.data;
  },

  changePassword: async (passwordData) => {
    const response = await apiClient.post("/v1/auth/change-password", passwordData);
    return response.data;
  },

  logout: async () => {
    const response = await apiClient.post("/v1/auth/logout");
    return response.data;
  },
};
