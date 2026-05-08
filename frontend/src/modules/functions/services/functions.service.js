import apiClient from "@/lib/api/client";

export const functionsService = {
  context: async () => {
    const response = await apiClient.get("/v1/functions/context");
    return response.data;
  },

  permissionsMatrix: async () => {
    const response = await apiClient.get("/v1/permissions/matrix");
    return response.data;
  },

  list: async ({ page = 1, perPage = 10, search = "", className = "", status = "" } = {}) => {
    const response = await apiClient.get("/v1/functions", {
      params: {
        page,
        per_page: perPage,
        ...(search ? { search } : {}),
        ...(className ? { class: className } : {}),
        ...(status ? { status } : {}),
      },
    });
    return response.data;
  },

  getById: async (functionId) => {
    const response = await apiClient.get(`/v1/functions/${functionId}`);
    return response.data;
  },

  create: async (payload) => {
    const response = await apiClient.post("/v1/functions", payload);
    return response.data;
  },

  update: async (functionId, payload) => {
    const response = await apiClient.put(`/v1/functions/${functionId}`, payload);
    return response.data;
  },

  updateStatus: async (functionId, payload) => {
    const response = await apiClient.patch(`/v1/functions/${functionId}/status`, payload);
    return response.data;
  },
};
