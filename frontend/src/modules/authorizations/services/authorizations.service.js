import apiClient from "@/lib/api/client";

export const authorizationsService = {
  context: async () => {
    const response = await apiClient.get("/v1/authorizations/context");
    return response.data;
  },

  list: async ({ page = 1, perPage = 10, search = "", status = "", module = "" } = {}) => {
    const response = await apiClient.get("/v1/authorizations", {
      params: {
        page,
        per_page: perPage,
        ...(search ? { search } : {}),
        ...(status ? { status } : {}),
        ...(module ? { module } : {}),
      },
    });
    return response.data;
  },

  getById: async (authorizationId) => {
    const response = await apiClient.get(`/v1/authorizations/${authorizationId}`);
    return response.data;
  },

  updateStatus: async (authorizationId, payload) => {
    const response = await apiClient.patch(`/v1/authorizations/${authorizationId}/status`, payload);
    return response.data;
  },
};
