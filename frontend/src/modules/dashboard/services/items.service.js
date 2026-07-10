import apiClient from "@/lib/api/client";
import { buildApiUrl } from "@/lib/utils/pdf";

export const itemsService = {
  list: async ({ page = 1, perPage = 10, search = "", order = "legacy", freshness = "", reviewDays = "", duplicates = false, status = "" } = {}) => {
    const response = await apiClient.get("/v1/items", {
      params: {
        page,
        per_page: perPage,
        order,
        ...(search ? { search } : {}),
        ...(freshness ? { freshness } : {}),
        ...(reviewDays ? { review_days: reviewDays } : {}),
        ...(duplicates ? { duplicates: 1 } : {}),
        ...(status ? { status } : {}),
      },
    });
    return response.data;
  },

  context: async () => {
    const response = await apiClient.get("/v1/items/context");
    return response.data;
  },

  maintenanceSummary: async () => {
    const response = await apiClient.get("/v1/items/maintenance-summary");
    return response.data;
  },

  review: async (itemId) => {
    const response = await apiClient.post(`/v1/items/${itemId}/review`);
    return response.data;
  },

  create: async (payload) => {
    const response = await apiClient.post("/v1/items", payload);
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

  deactivateImpact: async (itemId) => {
    const response = await apiClient.get(`/v1/items/${itemId}/deactivate-impact`);
    return response.data;
  },

  getById: async (itemId) => {
    const response = await apiClient.get(`/v1/items/${itemId}`);
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

  syncMaterials: async ({ itemId, payload }) => {
    const response = await apiClient.post(`/v1/items/${itemId}/materials/sync`, payload);
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

  syncLabor: async ({ itemId, payload }) => {
    const response = await apiClient.post(`/v1/items/${itemId}/labor/sync`, payload);
    return response.data;
  },

  updateLabor: async ({ itemId, itemInputId, payload }) => {
    const response = await apiClient.put(`/v1/items/${itemId}/labor/${itemInputId}`, payload);
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

  syncMachinery: async ({ itemId, payload }) => {
    const response = await apiClient.post(`/v1/items/${itemId}/machinery/sync`, payload);
    return response.data;
  },

  removeMachinery: async ({ itemId, itemInputId }) => {
    const response = await apiClient.delete(`/v1/items/${itemId}/machinery/${itemInputId}`);
    return response.data;
  },

  updateFiles: async ({ id, formData }) => {
    const response = await apiClient.post(`/v1/items/${id}/files`, formData, {
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

  downloadPriceRecalculationPdf: async ({ itemId, fecha, mode = "general" }) => {
    const response = await apiClient.get(`/v1/items/${itemId}/price-recalculation/pdf`, {
      params: { fecha, mode },
      responseType: "blob",
    });
    return response.data;
  },

  priceRecalculationPdfUrl: ({ itemId, fecha, mode = "general" }) => buildApiUrl(`/v1/items/${itemId}/price-recalculation/pdf`, { fecha, mode }),
  priceRecalculationXlsxUrl: ({ itemId, fecha, mode = "general" }) => buildApiUrl(`/v1/items/${itemId}/price-recalculation/xlsx`, { fecha, mode }),

  recalculateBreakdowns: async ({ itemId, payload }) => {
    const response = await apiClient.post(`/v1/items/${itemId}/breakdowns/recalculate`, payload, {
      responseType: "blob",
    });
    return response.data;
  },

  breakdownRecalculationPdfUrl: ({ itemId, fecha, type }) => buildApiUrl(`/v1/items/${itemId}/breakdowns/recalculate/pdf`, {
    fecha,
    tipo_desglose: type,
  }),
  breakdownRecalculationXlsxUrl: ({ itemId, fecha, type }) => buildApiUrl(`/v1/items/${itemId}/breakdowns/recalculate/xlsx`, {
    fecha,
    tipo_desglose: type,
  }),

  downloadLegacyUnitPriceAnalysisPdf: async (itemId, mode = "general") => {
    const response = await apiClient.get(`/v1/items/${itemId}/analisis-precios-unitarios/pdf`, {
      params: { mode },
      responseType: "blob",
    });
    return response.data;
  },

  legacyUnitPriceAnalysisPdfUrl: (itemId, mode = "general") => buildApiUrl(`/v1/items/${itemId}/analisis-precios-unitarios/pdf`, { mode }),
  legacyUnitPriceAnalysisXlsxUrl: (itemId, mode = "general") => buildApiUrl(`/v1/items/${itemId}/analisis-precios-unitarios/xlsx`, { mode }),

  downloadMaterialBreakdownPdf: async (itemId) => {
    const response = await apiClient.get(`/v1/items/${itemId}/materials/pdf`, {
      responseType: "blob",
    });
    return response.data;
  },

  materialBreakdownPdfUrl: (itemId) => buildApiUrl(`/v1/items/${itemId}/materials/pdf`),
  materialBreakdownXlsxUrl: (itemId) => buildApiUrl(`/v1/items/${itemId}/materials/xlsx`),

  downloadLaborBreakdownPdf: async (itemId) => {
    const response = await apiClient.get(`/v1/items/${itemId}/labor/pdf`, {
      responseType: "blob",
    });
    return response.data;
  },

  laborBreakdownPdfUrl: (itemId) => buildApiUrl(`/v1/items/${itemId}/labor/pdf`),
  laborBreakdownXlsxUrl: (itemId) => buildApiUrl(`/v1/items/${itemId}/labor/xlsx`),

  downloadMachineryBreakdownPdf: async (itemId) => {
    const response = await apiClient.get(`/v1/items/${itemId}/machinery/pdf`, {
      responseType: "blob",
    });
    return response.data;
  },

  machineryBreakdownPdfUrl: (itemId) => buildApiUrl(`/v1/items/${itemId}/machinery/pdf`),
  machineryBreakdownXlsxUrl: (itemId) => buildApiUrl(`/v1/items/${itemId}/machinery/xlsx`),

  signReport: async (itemId, reportKey, payload = {}) => {
    const response = await apiClient.post(`/v1/items/${itemId}/reports/${reportKey}/sign`, payload);
    return response.data;
  },

  signatureStatus: async (itemId, params = {}) => {
    const response = await apiClient.get(`/v1/items/${itemId}/signature-status`, { params });
    return response.data;
  },

  reportSignatures: async (itemId, reportKey, params = {}) => {
    const response = await apiClient.get(`/v1/items/${itemId}/reports/${reportKey}/signatures`, { params });
    return response.data;
  },

  latestSignedReportUrl: (itemId, reportKey, params = {}) => buildApiUrl(`/v1/items/${itemId}/reports/${reportKey}/signed/latest`, params),

  physicalSignatures: async (itemId, reportKey, params = {}) => {
    const response = await apiClient.get(`/v1/items/${itemId}/reports/${reportKey}/physical-signatures`, { params });
    return response.data;
  },

  markPhysicalSignature: async (itemId, reportKey, payload = {}) => {
    const response = await apiClient.post(`/v1/items/${itemId}/reports/${reportKey}/physical-signatures`, payload);
    return response.data;
  },

  updatePhysicalSignaturePositions: async (itemId, reportKey, payload = {}) => {
    const response = await apiClient.put(`/v1/items/${itemId}/reports/${reportKey}/physical-signatures/positions`, payload);
    return response.data;
  },

  physicalSignaturesPdfUrl: (itemId, reportKey, params = {}) => buildApiUrl(`/v1/items/${itemId}/reports/${reportKey}/physical-signatures/pdf`, params),
};
