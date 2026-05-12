import apiClient from "@/lib/api/client";

export const groupsService = {
  list: async () => {
    const response = await apiClient.get("/v1/groups");
    return response.data;
  },
  create: async (payload) => {
    const response = await apiClient.post("/v1/groups", payload);
    return response.data;
  },
  update: async ({ id, payload }) => {
    const response = await apiClient.put(`/v1/groups/${id}`, payload);
    return response.data;
  },
  remove: async ({ id, autorizacion }) => {
    const response = await apiClient.delete(`/v1/groups/${id}`, {
      data: { autorizacion },
    });
    return response.data;
  },
};
