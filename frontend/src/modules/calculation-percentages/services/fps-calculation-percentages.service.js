import apiClient from "@/lib/api/client";

export const fpsCalculationPercentagesService = {
  context: async () => {
    const response = await apiClient.get("/v1/calculation-percentages/fps/context");
    return response.data;
  },

  list: async ({ page = 1, perPage = 10, search = "", status = "" } = {}) => {
    const normalizedSearch = search.trim().toUpperCase();
    const normalizedStatus = status.trim().toUpperCase();

    const response = await apiClient.get("/v1/calculation-percentages/fps", {
      params: {
        page,
        per_page: perPage,
        ...(normalizedSearch ? { search: normalizedSearch } : {}),
        ...(normalizedStatus ? { status: normalizedStatus } : {}),
      },
    });

    return response.data;
  },

  getById: async (calculationPercentageId) => {
    const response = await apiClient.get(`/v1/calculation-percentages/fps/${calculationPercentageId}`);
    return response.data;
  },

  create: async (payload) => {
    const response = await apiClient.post("/v1/calculation-percentages/fps", payload);
    return response.data;
  },

  update: async (calculationPercentageId, payload) => {
    const response = await apiClient.put(`/v1/calculation-percentages/fps/${calculationPercentageId}`, payload);
    return response.data;
  },
};
