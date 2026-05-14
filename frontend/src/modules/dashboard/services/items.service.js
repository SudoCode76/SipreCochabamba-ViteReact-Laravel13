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

  update: async ({ id, payload }) => {
    const response = await apiClient.put(`/v1/items/${id}`, payload);
    return response.data;
  },

  compositionContext: async (itemId) => {
    const response = await apiClient.get(`/v1/items/${itemId}/composition/context`);
    return response.data;
  },

  materials: async (itemId) => {
    const response = await apiClient.get(`/v1/items/${itemId}/materials`);
    return response.data;
  },

  searchInputs: async ({ search = "", type = 1 } = {}) => {
    const response = await apiClient.get("/v1/search/inputs", {
      params: {
        ...(search ? { search } : {}),
        type,
      },
    });
    return response.data;
  },

  addMaterial: async ({ itemId, payload }) => {
    const response = await apiClient.post(`/v1/items/${itemId}/materials`, payload);
    return response.data;
  },

  removeMaterial: async ({ itemId, itemInputId }) => {
    const response = await apiClient.delete(`/v1/items/${itemId}/materials/${itemInputId}`);
    return response.data;
  },

  labor: async (itemId) => {
    const response = await apiClient.get(`/v1/items/${itemId}/labor`);
    return response.data;
  },

  addLabor: async ({ itemId, payload }) => {
    const response = await apiClient.post(`/v1/items/${itemId}/labor`, payload);
    return response.data;
  },

  removeLabor: async ({ itemId, itemInputId }) => {
    const response = await apiClient.delete(`/v1/items/${itemId}/labor/${itemInputId}`);
    return response.data;
  },

  machinery: async (itemId) => {
    const response = await apiClient.get(`/v1/items/${itemId}/machinery`);
    return response.data;
  },

  addMachinery: async ({ itemId, payload }) => {
    const response = await apiClient.post(`/v1/items/${itemId}/machinery`, payload);
    return response.data;
  },

  removeMachinery: async ({ itemId, itemInputId }) => {
    const response = await apiClient.delete(`/v1/items/${itemId}/machinery/${itemInputId}`);
    return response.data;
  },

  updateFiles: async ({ id, formData }) => {
    const response = await apiClient.post(`/v1/items/${id}?_method=PUT`, formData, {
      headers: {
        "Content-Type": "multipart/form-data",
      },
    });
    return response.data;
  },

  recalculatePrice: async ({ itemId, fecha }) => {
    const response = await apiClient.post(`/v1/items/${itemId}/price-recalculation`, { fecha });
    return response.data;
  },

  recalculateBreakdowns: async ({ itemId, payload }) => {
    const response = await apiClient.post(`/v1/items/${itemId}/breakdowns/recalculate`, payload);
    return response.data;
  },

  downloadLegacyUnitPriceAnalysisPdf: async (itemId) => {
    const response = await apiClient.get(`/v1/items/${itemId}/analisis-precios-unitarios/pdf`, {
      responseType: "blob",
    });
    return response.data;
  },
};
