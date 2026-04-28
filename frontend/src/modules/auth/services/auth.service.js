import apiClient from "@/lib/api/client";

export const authService = {
  getProfile: async () => {
    const response = await apiClient.get("/me");
    return response.data;
  },

  changePassword: async (passwordData) => {
    const response = await apiClient.put("/user/password", passwordData);
    return response.data;
  },
};
