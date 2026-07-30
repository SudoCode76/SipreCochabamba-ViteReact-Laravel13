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

  mapProjects: async (params = {}) => {
    const response = await apiClient.get("/v1/projects/map", { params });
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

  versions: async (projectId) => {
    const response = await apiClient.get(`/v1/projects/${projectId}/versions`);
    return response.data;
  },

  createUpdatedVersion: async (projectId) => {
    const response = await apiClient.post(`/v1/projects/${projectId}/versions`);
    return response.data;
  },

  compareVersions: async (projectId, { base, target, format = "PCA" }) => {
    const response = await apiClient.get(`/v1/projects/${projectId}/versions/compare`, {
      params: { base, target, format },
    });
    return response.data;
  },

  finalizeVersion: async (projectId) => {
    const response = await apiClient.post(`/v1/projects/${projectId}/finalize`);
    return response.data;
  },

  synchronizeVersion: async (projectId) => {
    const response = await apiClient.post(`/v1/projects/${projectId}/synchronize`);
    return response.data;
  },

  excludeVersionInput: async (projectId, snapshotId) => {
    const response = await apiClient.post(`/v1/projects/${projectId}/input-snapshots/${snapshotId}/exclude`);
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

  reportWarnings: async (projectId) => {
    const response = await apiClient.get(`/v1/projects/${projectId}/report-warnings`);
    return response.data;
  },

  signatureAccess: async (projectId) => {
    const response = await apiClient.get(`/v1/projects/${projectId}/signature-access`);
    return response.data;
  },

  updateSignatureAccess: async (projectId, payload) => {
    const response = await apiClient.put(`/v1/projects/${projectId}/signature-access`, payload);
    return response.data;
  },

  signableReports: async () => {
    const response = await apiClient.get("/v1/signable-project-reports");
    return response.data;
  },

  updateSignableReport: async ({ reportKey, payload }) => {
    const response = await apiClient.patch(`/v1/signable-project-reports/${reportKey}`, payload);
    return response.data;
  },

  signReport: async (projectId, reportKey, payload = {}) => {
    const response = await apiClient.post(`/v1/projects/${projectId}/reports/${reportKey}/sign`, payload);
    return response.data;
  },

  signatureStatus: async (projectId, params = {}) => {
    const response = await apiClient.get(`/v1/projects/${projectId}/signature-status`, { params });
    return response.data;
  },

  reportSignatures: async (projectId, reportKey, params = {}) => {
    const response = await apiClient.get(`/v1/projects/${projectId}/reports/${reportKey}/signatures`, { params });
    return response.data;
  },

  latestSignedReportUrl: (projectId, reportKey, params = {}) => buildApiUrl(`/v1/projects/${projectId}/reports/${reportKey}/signed/latest`, params),

  physicalSignatures: async (projectId, reportKey, params = {}) => {
    const response = await apiClient.get(`/v1/projects/${projectId}/reports/${reportKey}/physical-signatures`, { params });
    return response.data;
  },

  markPhysicalSignature: async (projectId, reportKey, payload = {}) => {
    const response = await apiClient.post(`/v1/projects/${projectId}/reports/${reportKey}/physical-signatures`, payload);
    return response.data;
  },

  updatePhysicalSignaturePages: async (projectId, reportKey, pages) => {
    const response = await apiClient.put(`/v1/projects/${projectId}/reports/${reportKey}/physical-signatures/pages`, { pages });
    return response.data;
  },

  updatePhysicalSignaturePositions: async (projectId, reportKey, payload = {}) => {
    const response = await apiClient.put(`/v1/projects/${projectId}/reports/${reportKey}/physical-signatures/positions`, payload);
    return response.data;
  },

  prepareSignaturePreview: async (projectId, reportKey, payload = {}) => {
    const response = await apiClient.post(`/v1/projects/${projectId}/reports/${reportKey}/signature-preview`, payload);
    return response.data;
  },

  updateSignaturePreviewPositions: async (projectId, reportKey, payload = {}) => {
    const response = await apiClient.patch(`/v1/projects/${projectId}/reports/${reportKey}/signature-preview/positions`, payload);
    return response.data;
  },

  signaturePreviewPdfUrl: (projectId, reportKey, params = {}) => buildApiUrl(`/v1/projects/${projectId}/reports/${reportKey}/signature-preview/pdf`, params),

  cancelSignature: async (projectId, signatureId) => {
    const response = await apiClient.post(`/v1/projects/${projectId}/signatures/${signatureId}/cancel`);
    return response.data;
  },

  physicalSignaturesPdfUrl: (projectId, reportKey, params = {}) => buildApiUrl(`/v1/projects/${projectId}/reports/${reportKey}/physical-signatures/pdf`, params),

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
  budgetRecalculationXlsxUrl: (projectId, fecha) => buildApiUrl(`/v1/projects/${projectId}/budget-recalculation/xlsx`, { fecha }),

  downloadBudgetByGroupPdf: async (projectId) => {
    const response = await apiClient.get(`/v1/projects/${projectId}/budget-by-group/pdf`, {
      responseType: "blob",
    });
    return response.data;
  },

  budgetByGroupPdfUrl: (projectId) => buildApiUrl(`/v1/projects/${projectId}/budget-by-group/pdf`),
  budgetByGroupXlsxUrl: (projectId) => buildApiUrl(`/v1/projects/${projectId}/budget-by-group/xlsx`),

  downloadIncidenceSummaryPdf: async (projectId, format) => {
    const response = await apiClient.get(`/v1/projects/${projectId}/incidence-summary/pdf`, {
      params: { format },
      responseType: "blob",
    });
    return response.data;
  },

  incidenceSummaryPdfUrl: (projectId, format) => buildApiUrl(`/v1/projects/${projectId}/incidence-summary/pdf`, { format }),
  incidenceSummaryXlsxUrl: (projectId, format) => buildApiUrl(`/v1/projects/${projectId}/incidence-summary/xlsx`, { format }),

  downloadGeneralBudgetPdf: async (projectId, format) => {
    const response = await apiClient.get(`/v1/projects/${projectId}/general-budget/pdf`, {
      params: { format },
      responseType: "blob",
    });
    return response.data;
  },

  generalBudgetPdfUrl: (projectId, format) => buildApiUrl(`/v1/projects/${projectId}/general-budget/pdf`, { format }),
  generalBudgetXlsxUrl: (projectId, format) => buildApiUrl(`/v1/projects/${projectId}/general-budget/xlsx`, { format }),

  downloadInputBreakdownPdf: async (projectId, type) => {
    const response = await apiClient.get(`/v1/projects/${projectId}/input-breakdown/pdf`, {
      params: { type },
      responseType: "blob",
    });
    return response.data;
  },

  inputBreakdownPdfUrl: (projectId, type) => buildApiUrl(`/v1/projects/${projectId}/input-breakdown/pdf`, { type }),
  inputBreakdownXlsxUrl: (projectId, type) => buildApiUrl(`/v1/projects/${projectId}/input-breakdown/xlsx`, { type }),

  downloadInputsReportPdf: async (projectId) => {
    const response = await apiClient.get(`/v1/projects/${projectId}/inputs-report/pdf`, {
      responseType: "blob",
    });
    return response.data;
  },

  inputsReportPdfUrl: (projectId) => buildApiUrl(`/v1/projects/${projectId}/inputs-report/pdf`),
  inputsReportXlsxUrl: (projectId) => buildApiUrl(`/v1/projects/${projectId}/inputs-report/xlsx`),
  groupedInputsReportPdfUrl: (projectId) => buildApiUrl(`/v1/projects/${projectId}/grouped-inputs-report/pdf`),
  groupedInputsReportXlsxUrl: (projectId) => buildApiUrl(`/v1/projects/${projectId}/grouped-inputs-report/xlsx`),
  unitPricesPdfUrl: (projectId, format) => buildApiUrl(`/v1/projects/${projectId}/unit-prices/pdf`, { format }),
  specificationsPdfUrl: (projectId) => buildApiUrl(`/v1/projects/${projectId}/specifications/pdf`),

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
