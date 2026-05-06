import apiClient from "@/lib/api/client";

export const itemsService = {
  list: async ({ page = 1, perPage = 10, search = "" } = {}) => {
    const response = await apiClient.get("/v1/items", {
      params: {
        page,
        per_page: perPage,
        ...(search ? { search } : {}),
      },
    });
    return response.data;
  },

  fndrList: async ({ page = 1, perPage = 10, search = "" } = {}) => {
    const response = await apiClient.get("/v1/items/fndr", {
      params: {
        page,
        per_page: perPage,
        ...(search ? { search } : {}),
      },
    });
    return response.data;
  },

  fpsList: async ({ page = 1, perPage = 10, search = "" } = {}) => {
    const response = await apiClient.get("/v1/items/fps", {
      params: {
        page,
        per_page: perPage,
        ...(search ? { search } : {}),
      },
    });
    return response.data;
  },

  obrasList: async ({ page = 1, perPage = 10, search = "" } = {}) => {
    const response = await apiClient.get("/v1/items/obras", {
      params: {
        page,
        per_page: perPage,
        ...(search ? { search } : {}),
      },
    });
    return response.data;
  },

  upreList: async ({ page = 1, perPage = 10, search = "" } = {}) => {
    const response = await apiClient.get("/v1/items/upre", {
      params: {
        page,
        per_page: perPage,
        ...(search ? { search } : {}),
      },
    });
    return response.data;
  },

  promanList: async ({ page = 1, perPage = 10, search = "" } = {}) => {
    const response = await apiClient.get("/v1/items/proman", {
      params: {
        page,
        per_page: perPage,
        ...(search ? { search } : {}),
      },
    });
    return response.data;
  },
};