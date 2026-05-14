import apiClient from "@/lib/api/client";

export const inputsService = {
  list: async ({ page = 1, perPage = 10, description = "" } = {}) => {
    const response = await apiClient.get("/v1/inputs", {
      params: {
        page,
        per_page: perPage,
        ...(description ? { description } : {}),
      },
    });
    return response.data;
  },
  requestDeleteAuthorization: async (id, payload = {}) => {
    const response = await apiClient.post(`/v1/inputs/${id}/delete-authorization-request`, payload);
    return response.data;
  },
};
