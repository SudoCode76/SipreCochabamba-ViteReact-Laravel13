/* eslint-disable react-refresh/only-export-components */
import { lazy, Suspense } from "react";
import { createBrowserRouter, Navigate } from "react-router-dom";

import MainLayout from "@/app/layouts/MainLayout";
import ProtectedRoute from "./ProtectedRoute";
import ModulePlaceholder from "@/components/ModulePlaceholder";
import LoginPage from "@/modules/auth/pages/LoginPage";

const loadAuditsPage = () => import("@/modules/audits/pages/AuditsPage");
const loadAuthorizationsPage = () => import("@/modules/authorizations/pages/AuthorizationsPage");
const loadCalculationPercentagesPage = () => import("@/modules/calculation-percentages/pages/CalculationPercentagesPage");
const loadChangePasswordPage = () => import("@/modules/auth/pages/ChangePasswordPage");
const loadCreateInputRequestPage = () => import("@/modules/input-requests/pages/CreateInputRequestPage");
const loadDashboardPage = () => import("@/modules/dashboard/pages/DashboardPage");
const loadEditInputRequestPage = () => import("@/modules/input-requests/pages/EditInputRequestPage");
const loadEditProjectPage = () => import("@/modules/projects/pages/EditProjectPage");
const loadFndrAnalysisPage = () => import("@/modules/analysis/fndr/pages/FndrAnalysisPage");
const loadFndrCalculationPercentagesPage = () => import("@/modules/calculation-percentages/pages/FndrCalculationPercentagesPage");
const loadFpsAnalysisPage = () => import("@/modules/analysis/fps/pages/FpsAnalysisPage");
const loadFpsCalculationPercentagesPage = () => import("@/modules/calculation-percentages/pages/FpsCalculationPercentagesPage");
const loadFunctionsPage = () => import("@/modules/functions/pages/FunctionsPage");
const loadGroupsPage = () => import("@/modules/groups/pages/GroupsPage");
const loadInputRequestsPage = () => import("@/modules/input-requests/pages/InputRequestsPage");
const loadInputsPage = () => import("@/modules/inputs/pages/InputsPage");
const loadInputCategoriesPage = () => import("@/modules/input-types/pages/InputCategoriesPage");
const loadInputTypesPage = () => import("@/modules/input-types/pages/InputTypesPage");
const loadItemsPage = () => import("@/modules/items/pages/ItemsPage");
const loadManageInputRequestsPage = () => import("@/modules/input-requests/pages/ManageInputRequestsPage");
const loadModulesPage = () => import("@/modules/modules/pages/ModulesPage");
const loadNewProjectPage = () => import("@/modules/projects/pages/NewProjectPage");
const loadObrasAnalysisPage = () => import("@/modules/analysis/obras/pages/ObrasAnalysisPage");
const loadObrasCalculationPercentagesPage = () => import("@/modules/calculation-percentages/pages/ObrasCalculationPercentagesPage");
const loadPriceRecalculationPage = () => import("@/modules/analysis/pages/PriceRecalculationPage");
const loadPdfViewerPage = () => import("@/modules/pdf/pages/PdfViewerPage");
const loadProjectItemsPage = () => import("@/modules/projects/pages/ProjectItemsPage");
const loadProjectTemplatesPage = () => import("@/modules/projects/pages/ProjectTemplatesPage");
const loadProjectsPage = () => import("@/modules/projects/pages/ProjectsPage");
const loadPromanAnalysisPage = () => import("@/modules/analysis/proman/pages/PromanAnalysisPage");
const loadPromanCalculationPercentagesPage = () => import("@/modules/calculation-percentages/pages/PromanCalculationPercentagesPage");
const loadRolesPage = () => import("@/modules/roles/pages/RolesPage");
const loadSubgroupsPage = () => import("@/modules/groups/pages/SubgroupsPage");
const loadUnitMeasuresPage = () => import("@/modules/input-types/pages/UnitMeasuresPage");
const loadUpreAnalysisPage = () => import("@/modules/analysis/upre/pages/UpreAnalysisPage");
const loadUpreCalculationPercentagesPage = () => import("@/modules/calculation-percentages/pages/UpreCalculationPercentagesPage");
const loadUsersPage = () => import("@/modules/users/pages/UsersPage");

const AuditsPage = lazy(loadAuditsPage);
const AuthorizationsPage = lazy(loadAuthorizationsPage);
const CalculationPercentagesPage = lazy(loadCalculationPercentagesPage);
const ChangePasswordPage = lazy(loadChangePasswordPage);
const CreateInputRequestPage = lazy(loadCreateInputRequestPage);
const DashboardPage = lazy(loadDashboardPage);
const EditInputRequestPage = lazy(loadEditInputRequestPage);
const EditProjectPage = lazy(loadEditProjectPage);
const FndrAnalysisPage = lazy(loadFndrAnalysisPage);
const FndrCalculationPercentagesPage = lazy(loadFndrCalculationPercentagesPage);
const FpsAnalysisPage = lazy(loadFpsAnalysisPage);
const FpsCalculationPercentagesPage = lazy(loadFpsCalculationPercentagesPage);
const FunctionsPage = lazy(loadFunctionsPage);
const GroupsPage = lazy(loadGroupsPage);
const InputRequestsPage = lazy(loadInputRequestsPage);
const InputsPage = lazy(loadInputsPage);
const InputCategoriesPage = lazy(loadInputCategoriesPage);
const InputTypesPage = lazy(loadInputTypesPage);
const ItemsPage = lazy(loadItemsPage);
const ManageInputRequestsPage = lazy(loadManageInputRequestsPage);
const ModulesPage = lazy(loadModulesPage);
const NewProjectPage = lazy(loadNewProjectPage);
const ObrasAnalysisPage = lazy(loadObrasAnalysisPage);
const ObrasCalculationPercentagesPage = lazy(loadObrasCalculationPercentagesPage);
const PriceRecalculationPage = lazy(loadPriceRecalculationPage);
const PdfViewerPage = lazy(loadPdfViewerPage);
const ProjectItemsPage = lazy(loadProjectItemsPage);
const ProjectTemplatesPage = lazy(loadProjectTemplatesPage);
const ProjectsPage = lazy(loadProjectsPage);
const PromanAnalysisPage = lazy(loadPromanAnalysisPage);
const PromanCalculationPercentagesPage = lazy(loadPromanCalculationPercentagesPage);
const RolesPage = lazy(loadRolesPage);
const SubgroupsPage = lazy(loadSubgroupsPage);
const UnitMeasuresPage = lazy(loadUnitMeasuresPage);
const UpreAnalysisPage = lazy(loadUpreAnalysisPage);
const UpreCalculationPercentagesPage = lazy(loadUpreCalculationPercentagesPage);
const UsersPage = lazy(loadUsersPage);

export function prefetchFrequentRoutes() {
  void Promise.allSettled([
    loadDashboardPage(),
    loadProjectsPage(),
    loadItemsPage(),
    loadInputsPage(),
  ]);
}

function routeElement(element) {
  return (
    <Suspense fallback={<div className="p-6 text-sm text-muted-foreground">Cargando...</div>}>
      {element}
    </Suspense>
  );
}

function placeholder(title, description, section, accent) {
  return (
    <ModulePlaceholder
      title={title}
      description={description}
      section={section}
      accent={accent}
    />
  );
}

export const router = createBrowserRouter([
  {
    path: "/",
    element: <Navigate to="/dashboard" replace />,
  },
  {
    path: "/login",
    element: <LoginPage />,
  },
  {
    element: <ProtectedRoute />,
    children: [
      {
        path: "/pdf-viewer",
        element: routeElement(<PdfViewerPage />),
      },
      {
        element: <MainLayout />,
        children: [
          {
            path: "/dashboard",
            element: routeElement(<DashboardPage />),
          },
          {
            path: "/items",
            element: routeElement(<ItemsPage />),
          },
          {
            path: "/analisis/fndr",
            element: routeElement(<FndrAnalysisPage />),
          },
          {
            path: "/analisis/upre",
            element: routeElement(<UpreAnalysisPage />),
          },
          {
            path: "/analisis/fps",
            element: routeElement(<FpsAnalysisPage />),
          },
          {
            path: "/analisis/obras-publicas",
            element: routeElement(<ObrasAnalysisPage />),
          },
          {
            path: "/analisis/proman",
            element: routeElement(<PromanAnalysisPage />),
          },
          {
            path: "/items/:itemId/recalculate",
            element: routeElement(<PriceRecalculationPage />),
          },
          {
            path: "/Insumo",
            element: routeElement(<InputsPage />),
          },
          {
            path: "/Listar Solicitud de Insumo",
            element: routeElement(<InputRequestsPage />),
          },
          {
            path: "/Crear Solicitud Insumo",
            element: routeElement(<CreateInputRequestPage />),
          },
          {
            path: "/Listar Solicitud de Insumo/:requestId/editar",
            element: routeElement(<EditInputRequestPage />),
          },
          {
            path: "/Gestionar Solicitud",
            element: routeElement(<ManageInputRequestsPage />),
          },
          {
            path: "/Proyecto",
            element: routeElement(<ProjectsPage />),
          },
          {
            path: "/Nuevo proyecto",
            element: routeElement(<NewProjectPage />),
          },
          {
            path: "/Proyecto/:projectId/editar",
            element: routeElement(<EditProjectPage />),
          },
          {
            path: "/Proyecto/:projectId/items",
            element: routeElement(<ProjectItemsPage />),
          },
          {
            path: "/parametros",
            element: placeholder("Parámetros", "Aquí pueden entrar catálogos maestros, configuraciones y reglas base del sistema.", "Gestión", "amber"),
          },
          {
            path: "/parametros/fps",
            element: routeElement(<FpsCalculationPercentagesPage />),
          },
          {
            path: "/parametros/upre",
            element: routeElement(<UpreCalculationPercentagesPage />),
          },
          {
            path: "/parametros/fndr",
            element: routeElement(<FndrCalculationPercentagesPage />),
          },
          {
            path: "/Grupos",
            element: routeElement(<GroupsPage />),
          },
          {
            path: "/%25%20Calculo",
            element: routeElement(<CalculationPercentagesPage />),
          },
          {
            path: "/%2520Calculo",
            element: routeElement(<CalculationPercentagesPage />),
          },
          {
            path: "/% Calculo",
            element: routeElement(<CalculationPercentagesPage />),
          },
          {
            path: "/Calculo",
            element: routeElement(<CalculationPercentagesPage />),
          },
          {
            path: "/calculo",
            element: routeElement(<CalculationPercentagesPage />),
          },
          {
            path: "/Sub grupos",
            element: routeElement(<SubgroupsPage />),
          },
          {
            path: "/Modulos",
            element: routeElement(<ModulesPage />),
          },
          {
            path: "/Planillas Proyecto",
            element: routeElement(<ProjectTemplatesPage />),
          },
          {
            path: "/parametros-calculo",
            element: routeElement(<CalculationPercentagesPage />),
          },
          {
            path: "/parametros/proman",
            element: routeElement(<PromanCalculationPercentagesPage />),
          },
          {
            path: "/parametros/obras-publicas",
            element: routeElement(<ObrasCalculationPercentagesPage />),
          },
          {
            path: "/Parametros Porcentajes Proman",
            element: routeElement(<PromanCalculationPercentagesPage />),
          },
          {
            path: "/%25%20Calculo%20UPRE",
            element: routeElement(<UpreCalculationPercentagesPage />),
          },
          {
            path: "/% Calculo UPRE",
            element: routeElement(<UpreCalculationPercentagesPage />),
          },
          {
            path: "/Parametros Porcentajes Upre",
            element: routeElement(<UpreCalculationPercentagesPage />),
          },
          {
            path: "/%25%20Calculo%20FPS",
            element: routeElement(<FpsCalculationPercentagesPage />),
          },
          {
            path: "/% Calculo FPS",
            element: routeElement(<FpsCalculationPercentagesPage />),
          },
          {
            path: "/Parametros Porcentajes Fps",
            element: routeElement(<FpsCalculationPercentagesPage />),
          },
          {
            path: "/%25%20Calculo%20FNDR",
            element: routeElement(<FndrCalculationPercentagesPage />),
          },
          {
            path: "/% Calculo FNDR",
            element: routeElement(<FndrCalculationPercentagesPage />),
          },
          {
            path: "/Parametros Porcentajes Fndr",
            element: routeElement(<FndrCalculationPercentagesPage />),
          },
          {
            path: "/%25%20OBRAS%20PUBLICAS",
            element: routeElement(<ObrasCalculationPercentagesPage />),
          },
          {
            path: "/% OBRAS PUBLICAS",
            element: routeElement(<ObrasCalculationPercentagesPage />),
          },
          {
            path: "/Parametros Porcentajes Obras Publicas",
            element: routeElement(<ObrasCalculationPercentagesPage />),
          },
          {
            path: "/Tipo de Insumo",
            element: routeElement(<InputTypesPage />),
          },
          {
            path: "/Categoria de Insumo",
            element: routeElement(<InputCategoriesPage />),
          },
          {
            path: "/Unidad de Medida",
            element: routeElement(<UnitMeasuresPage />),
          },
          {
            path: "/administracion",
            element: placeholder("Administración", "Centro de control para gobierno del sistema, permisos y operación interna.", "Administración", "slate"),
          },
          {
            path: "/usuarios",
            element: placeholder("Gestión de Usuarios", "Pantalla lista para listado, filtros y administración del personal del sistema.", "Administración", "emerald"),
          },
          {
            path: "/funciones",
            element: placeholder("Gestión de Funciones", "Base visual prevista para funciones del sistema y acciones autorizables.", "Administración", "sky"),
          },
          {
            path: "/roles",
            element: placeholder("Gestión de Roles", "Módulo pensado para roles, matrices de acceso y cambios controlados.", "Administración", "violet"),
          },
          {
            path: "/autorizaciones",
            element: placeholder("Gestión de Autorizaciones", "Interfaz reservada para permisos específicos y revisiones sensibles.", "Administración", "amber"),
          },
          {
            path: "/administracion/usuarios",
            element: routeElement(<UsersPage />),
          },
          {
            path: "/administracion/funciones",
            element: routeElement(<FunctionsPage />),
          },
          {
            path: "/administracion/roles",
            element: routeElement(<RolesPage />),
          },
          {
            path: "/administracion/autorizaciones",
            element: routeElement(<AuthorizationsPage />),
          },
          {
            path: "/administracion/auditoria",
            element: routeElement(<AuditsPage />),
          },
          {
            path: "/perfil",
            element: placeholder("Perfil de usuario", "Espacio listo para datos personales, preferencias y actividad reciente.", "Perfil", "slate"),
          },
          {
            path: "/perfil/password",
            element: routeElement(<ChangePasswordPage />),
          },
        ],
      },
    ],
  },
]);
