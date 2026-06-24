import apiClient from "@/lib/api/client";

export const inputsService = {
  list: async ({ page = 1, perPage = 10, description = "", order = "legacy", categoryId = "", duplicates = false } = {}) => {
    const response = await apiClient.get("/v1/inputs", {
      params: {
        page,
        per_page: perPage,
        order,
        ...(description ? { description } : {}),
        ...(categoryId ? { category_id: categoryId } : {}),
        ...(duplicates ? { duplicates: 1 } : {}),
      },
    });
    return response.data;
  },
  requestDeleteAuthorization: async (id, payload = {}) => {
    const response = await apiClient.post(`/v1/inputs/${id}/delete-authorization-request`, payload);
    return response.data;
  },
  deleteImpact: async (id) => {
    const response = await apiClient.get(`/v1/inputs/${id}/delete-impact`);
    return response.data;
  },
  history: async (id) => {
    const response = await apiClient.get(`/v1/inputs/${id}/history`);
    return response.data;
  },
};
