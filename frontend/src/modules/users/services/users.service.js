import apiClient from "@/lib/api/client";

export const usersService = {
  list: async ({ page = 1, perPage = 10, search = "", status = "", roleId = "", unitId = "", ci = "", username = "", name = "" } = {}) => {
    const response = await apiClient.get("/v1/users", {
      params: {
        page,
        per_page: perPage,
        ...(search ? { search } : {}),
        ...(name ? { name } : {}),
        ...(ci ? { ci } : {}),
        ...(username ? { username } : {}),
        ...(status ? { status } : {}),
        ...(roleId ? { role_id: roleId } : {}),
        ...(unitId ? { unit_id: unitId } : {}),
      },
    });

    return response.data;
  },

  getById: async (userId) => {
    const response = await apiClient.get(`/v1/users/${userId}`);
    return response.data;
  },

  create: async (payload) => {
    const response = await apiClient.post("/v1/users", payload);
    return response.data;
  },

  update: async (userId, payload) => {
    const response = await apiClient.put(`/v1/users/${userId}`, payload);
    return response.data;
  },

  updateStatus: async (userId, status) => {
    const response = await apiClient.patch(`/v1/users/${userId}/status`, { estado: status });
    return response.data;
  },

  findExistingByCi: async (ci) => {
    const response = await apiClient.get("/v1/users", {
      params: {
        ci,
        per_page: 1,
      },
    });

    return response.data;
  },

  listRoles: async () => {
    const response = await apiClient.get("/v1/roles");
    return response.data;
  },

  listUnits: async (search = "") => {
    const response = await apiClient.get("/v1/units", {
      params: {
        ...(search ? { search } : {}),
      },
    });
    return response.data;
  },
};
