import apiClient from "@/lib/api/client";

export const projectService = {
  list: async ({ page = 1, perPage = 15, search = "" } = {}) => {
    const response = await apiClient.get("/v1/projects", {
      params: { page, per_page: perPage, search },
    });
    return response.data;
  },
};