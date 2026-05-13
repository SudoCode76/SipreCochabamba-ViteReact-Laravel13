import apiClient from "@/lib/api/client";

export const projectService = {
  list: async ({ page = 1, perPage = 15, search = "" } = {}) => {
    const response = await apiClient.get("/v1/projects", {
      params: { page, per_page: perPage, search },
    });
    return response.data;
  },

  context: async () => {
    const response = await apiClient.get("/v1/projects/context");
    return response.data;
  },

  show: async (projectId) => {
    const response = await apiClient.get(`/v1/projects/${projectId}`);
    return response.data;
  },

  update: async (projectId, payload) => {
    const response = await apiClient.put(`/v1/projects/${projectId}`, payload);
    return response.data;
  },

  items: async (projectId, format = "PCA") => {
    const response = await apiClient.get(`/v1/projects/${projectId}/items`, {
      params: { format },
    });
    return response.data;
  },

  syncItems: async (projectId, payload) => {
    const response = await apiClient.post(`/v1/projects/${projectId}/items/sync`, payload);
    return response.data;
  },

  searchItems: async (search = "") => {
    const response = await apiClient.get("/v1/search/items", {
      params: search ? { search } : {},
    });
    return response.data;
  },

  itemIncidencePrice: async (itemId, format = "PCA") => {
    const response = await apiClient.get(`/v1/projects/items/${itemId}/incidence-price`, {
      params: { format },
    });
    return response.data;
  },
};
