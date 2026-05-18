import apiClient from "@/lib/api/client";

export const auditsService = {
  list: async ({ page = 1, perPage = 10, search = "", user = "", dateFrom = "", dateTo = "" } = {}) => {
    const response = await apiClient.get("/v1/audits", {
      params: {
        page,
        per_page: perPage,
        ...(search ? { search } : {}),
        ...(user ? { user } : {}),
        ...(dateFrom ? { date_from: dateFrom } : {}),
        ...(dateTo ? { date_to: dateTo } : {}),
      },
    });

    return response.data;
  },
};
