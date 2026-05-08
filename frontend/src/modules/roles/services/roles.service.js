import apiClient from "@/lib/api/client";

export const rolesService = {
  context: async () => {
    const response = await apiClient.get("/v1/roles/context");
    return response.data;
  },

  list: async ({ page = 1, perPage = 10, search = "", status = "" } = {}) => {
    const response = await apiClient.get("/v1/roles", {
      params: {
        page,
        per_page: perPage,
        ...(search ? { search } : {}),
        ...(status ? { status } : {}),
      },
    });
    return response.data;
  },

  getById: async (roleId) => {
    const response = await apiClient.get(`/v1/roles/${roleId}`);
    return response.data;
  },

  create: async (payload) => {
    const response = await apiClient.post("/v1/roles", payload);
    return response.data;
  },

  update: async (roleId, payload) => {
    const response = await apiClient.put(`/v1/roles/${roleId}`, payload);
    return response.data;
  },

  updateStatus: async (roleId, payload) => {
    const response = await apiClient.patch(`/v1/roles/${roleId}/status`, payload);
    return response.data;
  },

  permissionsContext: async (roleId) => {
    const response = await apiClient.get(`/v1/roles/${roleId}/permissions/context`);
    return response.data;
  },

  permissions: async ({ roleId, page = 1, perPage = 10, search = "" }) => {
    const response = await apiClient.get(`/v1/roles/${roleId}/permissions`, {
      params: {
        page,
        per_page: perPage,
        ...(search ? { search } : {}),
      },
    });
    return response.data;
  },

  attachFunction: async (roleId, functionId) => {
    const response = await apiClient.post(`/v1/roles/${roleId}/permissions/attach`, {
      function_id: functionId,
    });
    return response.data;
  },

  detachFunction: async (roleId, functionId) => {
    const response = await apiClient.post(`/v1/roles/${roleId}/permissions/detach`, {
      function_id: functionId,
    });
    return response.data;
  },
};
