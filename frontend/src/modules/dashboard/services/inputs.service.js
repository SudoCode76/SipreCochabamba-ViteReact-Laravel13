import apiClient from "@/lib/api/client";

export const inputsService = {
  list: async ({ page = 1, perPage = 10, description = "" } = {}) => {
    const response = await apiClient.get("/v1/dashboard/inputs", {
      params: {
        page,
        per_page: perPage,
        ...(description ? { description } : {}),
      },
    });
    return response.data;
  },
};
