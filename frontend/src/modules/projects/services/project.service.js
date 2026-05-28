import apiClient from "@/lib/api/client";
import { buildApiUrl } from "@/lib/utils/pdf";

export const projectService = {
  list: async ({ page = 1, perPage = 15, search = "", order = "legacy" } = {}) => {
    const response = await apiClient.get("/v1/projects", {
      params: { page, per_page: perPage, search, order },
    });
    return response.data;
  },

  context: async () => {
    const response = await apiClient.get("/v1/projects/context");
    return response.data;
  },

  create: async (payload) => {
    const response = await apiClient.post("/v1/projects", payload);
    return response.data;
  },

  show: async (projectId) => {
    const response = await apiClient.get(`/v1/projects/${projectId}`);
    return response.data;
  },

  history: async (projectId, { page = 1, perPage = 10, action = "", user = "", dateFrom = "", dateTo = "" } = {}) => {
    const response = await apiClient.get(`/v1/projects/${projectId}/history`, {
      params: {
        page,
        per_page: perPage,
        action: action || undefined,
        user: user || undefined,
        date_from: dateFrom || undefined,
        date_to: dateTo || undefined,
      },
    });
    return response.data;
  },

  update: async (projectId, payload) => {
    const response = await apiClient.put(`/v1/projects/${projectId}`, payload);
    return response.data;
  },

  templates: async ({ page = 1, perPage = 15, search = "", status = "" } = {}) => {
    const response = await apiClient.get("/v1/project-templates", {
      params: {
        page,
        per_page: perPage,
        search: search || undefined,
        status: status || undefined,
      },
    });
    return response.data;
  },

  showTemplate: async (templateId) => {
    const response = await apiClient.get(`/v1/project-templates/${templateId}`);
    return response.data;
  },

  createTemplateFromProject: async (projectId, payload) => {
    const response = await apiClient.post(`/v1/projects/${projectId}/template`, payload);
    return response.data;
  },

  updateTemplate: async ({ id, payload }) => {
    const templateId = id;
    const response = await apiClient.put(`/v1/project-templates/${templateId}`, payload);
    return response.data;
  },

  createFromTemplate: async (templateId, payload) => {
    const response = await apiClient.post(`/v1/project-templates/${templateId}/create-project`, payload);
    return response.data;
  },

  budgetRecalculation: async (projectId, payload) => {
    const response = await apiClient.post(`/v1/projects/${projectId}/budget-recalculation`, payload);
    return response.data;
  },

  downloadBudgetRecalculationPdf: async (projectId, fecha) => {
    const response = await apiClient.get(`/v1/projects/${projectId}/budget-recalculation/pdf`, {
      params: { fecha },
      responseType: "blob",
    });
    return response.data;
  },

  budgetRecalculationPdfUrl: (projectId, fecha) => buildApiUrl(`/v1/projects/${projectId}/budget-recalculation/pdf`, { fecha }),

  downloadBudgetByGroupPdf: async (projectId) => {
    const response = await apiClient.get(`/v1/projects/${projectId}/budget-by-group/pdf`, {
      responseType: "blob",
    });
    return response.data;
  },

  budgetByGroupPdfUrl: (projectId) => buildApiUrl(`/v1/projects/${projectId}/budget-by-group/pdf`),

  downloadIncidenceSummaryPdf: async (projectId, format) => {
    const response = await apiClient.get(`/v1/projects/${projectId}/incidence-summary/pdf`, {
      params: { format },
      responseType: "blob",
    });
    return response.data;
  },

  incidenceSummaryPdfUrl: (projectId, format) => buildApiUrl(`/v1/projects/${projectId}/incidence-summary/pdf`, { format }),

  downloadGeneralBudgetPdf: async (projectId, format) => {
    const response = await apiClient.get(`/v1/projects/${projectId}/general-budget/pdf`, {
      params: { format },
      responseType: "blob",
    });
    return response.data;
  },

  generalBudgetPdfUrl: (projectId, format) => buildApiUrl(`/v1/projects/${projectId}/general-budget/pdf`, { format }),

  downloadInputBreakdownPdf: async (projectId, type) => {
    const response = await apiClient.get(`/v1/projects/${projectId}/input-breakdown/pdf`, {
      params: { type },
      responseType: "blob",
    });
    return response.data;
  },

  inputBreakdownPdfUrl: (projectId, type) => buildApiUrl(`/v1/projects/${projectId}/input-breakdown/pdf`, { type }),

  downloadInputsReportPdf: async (projectId) => {
    const response = await apiClient.get(`/v1/projects/${projectId}/inputs-report/pdf`, {
      responseType: "blob",
    });
    return response.data;
  },

  inputsReportPdfUrl: (projectId) => buildApiUrl(`/v1/projects/${projectId}/inputs-report/pdf`),

  items: async (projectId, format = "PCA") => {
    const response = await apiClient.get(`/v1/projects/${projectId}/items`, {
      params: { format },
    });
    return response.data;
  },

  syncItems: async (projectId, payload) => {
    const response = await apiClient.post(`/v1/projects/${projectId}/items/sync`, payload);
    return response.data;
  },

  searchItems: async (search = "") => {
    const response = await apiClient.get("/v1/search/items", {
      params: search ? { search } : {},
    });
    return response.data;
  },

  itemIncidencePrice: async (itemId, format = "PCA") => {
    const response = await apiClient.get(`/v1/projects/items/${itemId}/incidence-price`, {
      params: { format },
    });
    return response.data;
  },
};
