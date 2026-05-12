import apiClient from "@/lib/api/client";

export const calculationPercentagesService = {
  list: async () => {
    const response = await apiClient.get("/v1/calculation-percentages");
    return response.data;
  },
  create: async (payload) => {
    const response = await apiClient.post("/v1/calculation-percentages", payload);
    return response.data;
  },
  update: async ({ id, payload }) => {
    const response = await apiClient.put(`/v1/calculation-percentages/${id}`, payload);
    return response.data;
  },
  remove: async ({ id, autorizacion }) => {
    const response = await apiClient.delete(`/v1/calculation-percentages/${id}`, {
      data: { autorizacion },
    });
    return response.data;
  },
};
