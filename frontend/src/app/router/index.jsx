import { createBrowserRouter, Navigate } from "react-router-dom";
import LoginPage from "@/modules/auth/pages/LoginPage";
import ChangePasswordPage from "@/modules/auth/pages/ChangePasswordPage";
import DashboardPage from "@/modules/dashboard/pages/DashboardPage";
import MainLayout from "@/app/layouts/MainLayout";


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
    element: <MainLayout />,
    children: [
      {
        path: "/dashboard",
        element: <DashboardPage />,
      },
      // Otros módulos se agregarán aquí
      {
        path: "/items",
        element: <div className="p-8 text-center"><h1 className="text-2xl font-bold">Items (En construcción)</h1></div>,
      },
      {
        path: "/analisis/fndr",
        element: <div className="p-8 text-center"><h1 className="text-2xl font-bold">Análisis FNDR (En construcción)</h1></div>,
      },
      {
        path: "/analisis/upre",
        element: <div className="p-8 text-center"><h1 className="text-2xl font-bold">Análisis UPRE (En construcción)</h1></div>,
      },
      {
        path: "/analisis/fps",
        element: <div className="p-8 text-center"><h1 className="text-2xl font-bold">Análisis FPS (En construcción)</h1></div>,
      },
      {
        path: "/analisis/obras-publicas",
        element: <div className="p-8 text-center"><h1 className="text-2xl font-bold">Análisis Obras Públicas (En construcción)</h1></div>,
      },
      {
        path: "/analisis/proman",
        element: <div className="p-8 text-center"><h1 className="text-2xl font-bold">Análisis Proman (En construcción)</h1></div>,
      },

      {
        path: "/insumos",
        element: <div className="p-8 text-center"><h1 className="text-2xl font-bold">Insumos (En construcción)</h1></div>,
      },
      {
        path: "/proyectos",
        element: <div className="p-8 text-center"><h1 className="text-2xl font-bold">Proyectos (En construcción)</h1></div>,
      },
      {
        path: "/parametros",
        element: <div className="p-8 text-center"><h1 className="text-2xl font-bold">Parámetros (En construcción)</h1></div>,
      },
      {
        path: "/administracion",
        element: <div className="p-8 text-center"><h1 className="text-2xl font-bold">Administración (En construcción)</h1></div>,
      },
      {
        path: "/administracion/usuarios",
        element: <div className="p-8 text-center"><h1 className="text-2xl font-bold">Gestión de Usuarios (En construcción)</h1></div>,
      },
      {
        path: "/administracion/funciones",
        element: <div className="p-8 text-center"><h1 className="text-2xl font-bold">Gestión de Funciones (En construcción)</h1></div>,
      },
      {
        path: "/administracion/roles",
        element: <div className="p-8 text-center"><h1 className="text-2xl font-bold">Gestión de Roles</h1></div>,
      },
      {
        path: "/administracion/autorizaciones",
        element: <div className="p-8 text-center"><h1 className="text-2xl font-bold">Gestión de Autorizaciones</h1></div>,
      },

      {
        path: "/perfil",
        element: <div className="p-8 text-center"><h1 className="text-2xl font-bold">Perfil de usuario</h1></div>,
      },
      {
        path: "/perfil/password",
        element: <ChangePasswordPage />,
      },



    ],
  },
]);
