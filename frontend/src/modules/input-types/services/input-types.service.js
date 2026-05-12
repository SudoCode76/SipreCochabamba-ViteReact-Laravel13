import apiClient from "@/lib/api/client";

export const inputTypesService = {
  list: async ({ page = 1, perPage = 15 } = {}) => {
    const response = await apiClient.get("/v1/input-types", {
      params: { page, per_page: perPage },
    });
    return response.data;
  },
  create: async (payload) => {
    const response = await apiClient.post("/v1/input-types", payload);
    return response.data;
  },
  update: async ({ id, payload }) => {
    const response = await apiClient.put(`/v1/input-types/${id}`, payload);
    return response.data;
  },
  remove: async ({ id, authorization_code }) => {
    const response = await apiClient.delete(`/v1/input-types/${id}`, {
      data: { authorization_code },
    });
    return response.data;
  },
};
