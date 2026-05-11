import apiClient from "@/lib/api/client";

export const fndrCalculationPercentagesService = {
  context: async () => {
    const response = await apiClient.get("/v1/calculation-percentages/fndr/context");
    return response.data;
  },

  list: async ({ page = 1, perPage = 10, search = "", status = "" } = {}) => {
    const normalizedSearch = search.trim().toUpperCase();

    const response = await apiClient.get("/v1/calculation-percentages/fndr", {
      params: {
        page,
        per_page: perPage,
        ...(normalizedSearch ? { search: normalizedSearch } : {}),
        ...(status ? { status } : {}),
      },
    });
    return response.data;
  },

  getById: async (calculationPercentageId) => {
    const response = await apiClient.get(`/v1/calculation-percentages/fndr/${calculationPercentageId}`);
    return response.data;
  },

  create: async (payload) => {
    const response = await apiClient.post("/v1/calculation-percentages/fndr", payload);
    return response.data;
  },

  update: async (calculationPercentageId, payload) => {
    const response = await apiClient.put(`/v1/calculation-percentages/fndr/${calculationPercentageId}`, payload);
    return response.data;
  },
};
