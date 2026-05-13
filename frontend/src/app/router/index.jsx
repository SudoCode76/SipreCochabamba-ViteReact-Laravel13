import { createBrowserRouter, Navigate, redirect } from "react-router-dom";

import MainLayout from "@/app/layouts/MainLayout";
import ProtectedRoute from "./ProtectedRoute";
import ModulePlaceholder from "@/components/ModulePlaceholder";
import ChangePasswordPage from "@/modules/auth/pages/ChangePasswordPage";
import AuthorizationsPage from "@/modules/authorizations/pages/AuthorizationsPage";
import FpsCalculationPercentagesPage from "@/modules/calculation-percentages/pages/FpsCalculationPercentagesPage";
import LoginPage from "@/modules/auth/pages/LoginPage";
import FndrCalculationPercentagesPage from "@/modules/calculation-percentages/pages/FndrCalculationPercentagesPage";
import ObrasCalculationPercentagesPage from "@/modules/calculation-percentages/pages/ObrasCalculationPercentagesPage";
import PromanCalculationPercentagesPage from "@/modules/calculation-percentages/pages/PromanCalculationPercentagesPage";
import UpreCalculationPercentagesPage from "@/modules/calculation-percentages/pages/UpreCalculationPercentagesPage";
import DashboardPage from "@/modules/dashboard/pages/DashboardPage";
import ItemsPage from "@/modules/items/pages/ItemsPage";
import FndrAnalysisPage from "@/modules/analysis/fndr/pages/FndrAnalysisPage";
import UpreAnalysisPage from "@/modules/analysis/upre/pages/UpreAnalysisPage";
import FpsAnalysisPage from "@/modules/analysis/fps/pages/FpsAnalysisPage";
import ObrasAnalysisPage from "@/modules/analysis/obras/pages/ObrasAnalysisPage";
import PromanAnalysisPage from "@/modules/analysis/proman/pages/PromanAnalysisPage";
import PriceRecalculationPage from "@/modules/analysis/pages/PriceRecalculationPage";
import ProjectsPage from "@/modules/projects/pages/ProjectsPage";
import NewProjectPage from "@/modules/projects/pages/NewProjectPage";
import EditProjectPage from "@/modules/projects/pages/EditProjectPage";
import InputsPage from "@/modules/inputs/pages/InputsPage";
import InputRequestsPage from "@/modules/input-requests/pages/InputRequestsPage";
import CreateInputRequestPage from "@/modules/input-requests/pages/CreateInputRequestPage";
import ManageInputRequestsPage from "@/modules/input-requests/pages/ManageInputRequestsPage";
import InputTypesPage from "@/modules/input-types/pages/InputTypesPage";
import UnitMeasuresPage from "@/modules/input-types/pages/UnitMeasuresPage";
import FunctionsPage from "@/modules/functions/pages/FunctionsPage";
import RolesPage from "@/modules/roles/pages/RolesPage";
import UsersPage from "@/modules/users/pages/UsersPage";
import GroupsPage from "@/modules/groups/pages/GroupsPage";
import SubgroupsPage from "@/modules/groups/pages/SubgroupsPage";
import CalculationPercentagesPage from "@/modules/calculation-percentages/pages/CalculationPercentagesPage";

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
        element: <MainLayout />,
        children: [
          {
            path: "/dashboard",
            element: <DashboardPage />,
          },
          {
            path: "/items",
            element: <ItemsPage />,
          },
          {
            path: "/analisis/fndr",
            element: <FndrAnalysisPage />,
          },
          {
            path: "/analisis/upre",
            element: <UpreAnalysisPage />,
          },
          {
            path: "/analisis/fps",
            element: <FpsAnalysisPage />,
          },
          {
            path: "/analisis/obras-publicas",
            element: <ObrasAnalysisPage />,
          },
          {
            path: "/analisis/proman",
            element: <PromanAnalysisPage />,
          },
          {
            path: "/items/:itemId/recalculate",
            element: <PriceRecalculationPage />,
          },
          {
            path: "/Insumo",
            element: <InputsPage />,
          },
          {
            path: "/Listar Solicitud de Insumo",
            element: <InputRequestsPage />,
          },
          {
            path: "/Crear Solicitud Insumo",
            element: <CreateInputRequestPage />,
          },
          {
            path: "/Gestionar Solicitud",
            element: <ManageInputRequestsPage />,
          },
          {
            path: "/Proyecto",
            element: <ProjectsPage />,
          },
          {
            path: "/Nuevo proyecto",
            element: <NewProjectPage />,
          },
          {
            path: "/Proyecto/:projectId/editar",
            element: <EditProjectPage />,
          },
          {
            path: "/parametros",
            element: placeholder("Parámetros", "Aquí pueden entrar catálogos maestros, configuraciones y reglas base del sistema.", "Gestión", "amber"),
          },
          {
            path: "/parametros/fps",
            element: <FpsCalculationPercentagesPage />,
          },
          {
            path: "/parametros/upre",
            element: <UpreCalculationPercentagesPage />,
          },
          {
            path: "/parametros/fndr",
            element: <FndrCalculationPercentagesPage />,
          },
          {
            path: "/Grupos",
            element: <GroupsPage />,
          },
          {
            path: "/%25%20Calculo",
            element: <CalculationPercentagesPage />,
          },
          {
            path: "/%2520Calculo",
            element: <CalculationPercentagesPage />,
          },
          {
            path: "/% Calculo",
            element: <CalculationPercentagesPage />,
          },
          {
            path: "/Calculo",
            element: <CalculationPercentagesPage />,
          },
          {
            path: "/calculo",
            element: <CalculationPercentagesPage />,
          },
          {
            path: "/Sub grupos",
            element: <SubgroupsPage />,
          },
          {
            path: "/parametros-calculo",
            element: <CalculationPercentagesPage />,
          },
          {
            path: "/parametros/proman",
            element: <PromanCalculationPercentagesPage />,
          },
          {
            path: "/parametros/obras-publicas",
            element: <ObrasCalculationPercentagesPage />,
          },
          {
            path: "/Parametros Porcentajes Proman",
            element: <PromanCalculationPercentagesPage />,
          },
          {
            path: "/%25%20Calculo%20UPRE",
            element: <UpreCalculationPercentagesPage />,
          },
          {
            path: "/% Calculo UPRE",
            element: <UpreCalculationPercentagesPage />,
          },
          {
            path: "/Parametros Porcentajes Upre",
            element: <UpreCalculationPercentagesPage />,
          },
          {
            path: "/%25%20Calculo%20FPS",
            element: <FpsCalculationPercentagesPage />,
          },
          {
            path: "/% Calculo FPS",
            element: <FpsCalculationPercentagesPage />,
          },
          {
            path: "/Parametros Porcentajes Fps",
            element: <FpsCalculationPercentagesPage />,
          },
          {
            path: "/%25%20Calculo%20FNDR",
            element: <FndrCalculationPercentagesPage />,
          },
          {
            path: "/% Calculo FNDR",
            element: <FndrCalculationPercentagesPage />,
          },
          {
            path: "/Parametros Porcentajes Fndr",
            element: <FndrCalculationPercentagesPage />,
          },
          {
            path: "/%25%20OBRAS%20PUBLICAS",
            element: <ObrasCalculationPercentagesPage />,
          },
          {
            path: "/% OBRAS PUBLICAS",
            element: <ObrasCalculationPercentagesPage />,
          },
          {
            path: "/Parametros Porcentajes Obras Publicas",
            element: <ObrasCalculationPercentagesPage />,
          },
          {
            path: "/Tipo de Insumo",
            element: <InputTypesPage />,
          },
          {
            path: "/Unidad de Medida",
            element: <UnitMeasuresPage />,
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
            element: <UsersPage />,
          },
          {
            path: "/administracion/funciones",
            element: <FunctionsPage />,
          },
          {
            path: "/administracion/roles",
            element: <RolesPage />,
          },
          {
            path: "/administracion/autorizaciones",
            element: <AuthorizationsPage />,
          },
          {
            path: "/perfil",
            element: placeholder("Perfil de usuario", "Espacio listo para datos personales, preferencias y actividad reciente.", "Perfil", "slate"),
          },
          {
            path: "/perfil/password",
            element: <ChangePasswordPage />,
          },
        ],
      },
    ],
  },
]);
