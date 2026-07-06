import apiClient from "@/lib/api/client";

export const citizenshipSessionKey = ["citizenship-session"];

export const citizenshipService = {
  session: async () => {
    const response = await apiClient.get("/v1/citizenship/session");
    return response.data;
  },

  logout: async () => {
    const response = await apiClient.post("/v1/citizenship/session/logout");
    return response.data;
  },
};
