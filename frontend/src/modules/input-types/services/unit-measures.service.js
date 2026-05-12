import apiClient from "@/lib/api/client";

export const unitMeasuresService = {
  list: async () => {
    const response = await apiClient.get("/v1/unit-measures");
    return response.data;
  },
  create: async (payload) => {
    const response = await apiClient.post("/v1/unit-measures", payload);
    return response.data;
  },
  update: async ({ id, payload }) => {
    const response = await apiClient.put(`/v1/unit-measures/${id}`, payload);
    return response.data;
  },
  remove: async ({ id, authorization_code }) => {
    const response = await apiClient.delete(`/v1/unit-measures/${id}`, {
      data: { authorization_code },
    });
    return response.data;
  },
};
