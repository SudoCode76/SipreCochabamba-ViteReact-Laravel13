import apiClient from "@/lib/api/client";

export const subgroupsService = {
  list: async () => {
    const response = await apiClient.get("/v1/subgroups");
    return response.data;
  },
  create: async (payload) => {
    const response = await apiClient.post("/v1/subgroups", payload);
    return response.data;
  },
  update: async ({ id, payload }) => {
    const response = await apiClient.put(`/v1/subgroups/${id}`, payload);
    return response.data;
  },
  remove: async ({ id, autorizacion }) => {
    const response = await apiClient.delete(`/v1/subgroups/${id}`, {
      data: { autorizacion },
    });
    return response.data;
  },
};
