import apiClient from "@/lib/api/client";

export const modulesService = {
  list: async () => {
    const response = await apiClient.get("/v1/modules");
    return response.data;
  },
  activeForProjects: async () => {
    const response = await apiClient.get("/v1/project-modules");
    return response.data;
  },
  context: async () => {
    const response = await apiClient.get("/v1/modules/context");
    return response.data;
  },
  create: async (payload) => {
    const response = await apiClient.post("/v1/modules", payload);
    return response.data;
  },
  update: async ({ id, payload }) => {
    const response = await apiClient.put(`/v1/modules/${id}`, payload);
    return response.data;
  },
  remove: async (id) => {
    const response = await apiClient.delete(`/v1/modules/${id}`);
    return response.data;
  },
};
