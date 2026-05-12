import apiClient from "@/lib/api/client";

export const manageInputRequestsService = {
  list: async ({ page = 1, perPage = 10, search = "" } = {}) => {
    const response = await apiClient.get("/v1/solicitudes-insumo/gestion", {
      params: {
        page,
        per_page: perPage,
        ...(search.trim() ? { search: search.trim() } : {}),
      },
    });

    return response.data;
  },

  getById: async (requestId) => {
    const response = await apiClient.get(`/v1/solicitudes-insumo/${requestId}`);
    return response.data;
  },

  manage: async (requestId, payload) => {
    const response = await apiClient.post(`/v1/solicitudes-insumo/${requestId}/gestion`, payload);
    return response.data;
  },

  revert: async (requestId, payload) => {
    const response = await apiClient.post(`/v1/solicitudes-insumo/${requestId}/revertir`, payload);
    return response.data;
  },

  searchUnitMeasures: async (query) => {
    const response = await apiClient.get("/v1/unidades-medida/search", {
      params: { q: query.trim() },
    });
    return response.data;
  },

  context: async () => {
    const response = await apiClient.get("/v1/input-requests/context");
    return response.data;
  },

  getQuotesHistory: async (requestId) => {
    const response = await apiClient.get(`/v1/input-requests/${requestId}/quotes/history`);
    return response.data;
  },

  getQuoteSummary: async (requestId) => {
    const response = await apiClient.get(`/v1/input-requests/${requestId}/quote-summary`);
    return response.data;
  },
};
