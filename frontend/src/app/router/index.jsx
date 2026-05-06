import { createBrowserRouter, Navigate } from "react-router-dom";

import MainLayout from "@/app/layouts/MainLayout";
import ProtectedRoute from "./ProtectedRoute";
import ModulePlaceholder from "@/components/ModulePlaceholder";
import ChangePasswordPage from "@/modules/auth/pages/ChangePasswordPage";
import LoginPage from "@/modules/auth/pages/LoginPage";
import DashboardPage from "@/modules/dashboard/pages/DashboardPage";
import UsersPage from "@/modules/users/pages/UsersPage";

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
            element: placeholder("Items", "Composición, detalle técnico y flujo operativo de ítems.", "Operaciones", "slate"),
          },
          {
            path: "/analisis/fndr", // SE CORRIGIÓ: Se agregó el path que faltaba
            element: placeholder("Análisis FNDR", "Espacio reservado para revisión, cálculo y seguimiento del flujo FNDR.", "Operaciones", "emerald"),
          },
          {
            path: "/analisis/upre",
            element: placeholder("Análisis UPRE", "Pantalla preparada para evaluaciones y decisiones del circuito UPRE.", "Operaciones", "amber"),
          },
          {
            path: "/analisis/fps",
            element: placeholder("Análisis FPS", "Contenedor listo para controles, aprobación y trazabilidad de análisis FPS.", "Operaciones", "sky"),
          },
          {
            path: "/analisis/obras-publicas",
            element: placeholder("Análisis Obras Públicas", "Base visual lista para incorporar reglas, filtros y seguimiento de obras públicas.", "Operaciones", "violet"),
          },
          {
            path: "/analisis/proman",
            element: placeholder("Análisis Proman", "Interfaz pendiente de integrar con el flujo Proman sin cambiar el nuevo sistema visual.", "Operaciones", "rose"),
          },
          {
            path: "/insumos",
            element: placeholder("Insumos", "Módulo destinado al catálogo, historial y control operativo de insumos.", "Gestión", "emerald"),
          },
          {
            path: "/proyectos",
            element: placeholder("Proyectos", "Vista preparada para coordinación, resumen y detalle de proyectos institucionales.", "Gestión", "sky"),
          },
          {
            path: "/parametros",
            element: placeholder("Parámetros", "Aquí pueden entrar catálogos maestros, configuraciones y reglas base del sistema.", "Gestión", "amber"),
          },
          {
            path: "/administracion",
            element: placeholder("Administración", "Centro de control para gobierno del sistema, permisos y operación interna.", "Administración", "slate"),
          },
          {
            path: "/administracion/usuarios",
            element: <UsersPage />,
          },
          {
            path: "/administracion/funciones",
            element: placeholder("Gestión de Funciones", "Base visual prevista para funciones del sistema y acciones autorizables.", "Administración", "sky"),
          },
          {
            path: "/administracion/roles",
            element: placeholder("Gestión de Roles", "Módulo pensado para roles, matrices de acceso y cambios controlados.", "Administración", "violet"),
          },
          {
            path: "/administracion/autorizaciones",
            element: placeholder("Gestión de Autorizaciones", "Interfaz reservada para permisos específicos y revisiones sensibles.", "Administración", "amber"),
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
