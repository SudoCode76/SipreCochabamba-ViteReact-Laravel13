import apiClient from "@/lib/api/client";

export const inputCategoriesService = {
  list: async () => {
    const response = await apiClient.get("/v1/input-categories");
    return response.data;
  },
  create: async (payload) => {
    const response = await apiClient.post("/v1/input-categories", payload);
    return response.data;
  },
  update: async ({ id, payload }) => {
    const response = await apiClient.put(`/v1/input-categories/${id}`, payload);
    return response.data;
  },
};
