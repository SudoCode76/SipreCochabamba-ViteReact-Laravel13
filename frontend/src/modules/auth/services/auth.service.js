import apiClient from "@/lib/api/client";

export const authService = {
  login: async (credentials) => {
    const response = await apiClient.post("/v1/auth/login", {
      username: credentials.username,
      clave: credentials.password,
      device_name: "web",
    });
    return response.data;
  },

  getProfile: async () => {
    const response = await apiClient.get("/v1/auth/me");
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
