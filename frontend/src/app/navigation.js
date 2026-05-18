import {
  Blocks,
  FolderKanban,
  KeyRound,
  LayoutDashboard,
  Package,
  ShieldCheck,
  SquareChartGantt,
  UsersRound,
  ClipboardClock,
} from "lucide-react";

export const navigationSections = [
  {
    title: "Inicio",
    path: "/dashboard",
    icon: LayoutDashboard,
  },
  {
    title: "Item",
    icon: Blocks,
    children: [
      { title: "Item", path: "/items", icon: SquareChartGantt, accent: "emerald" },
      { title: "Análisis FNDR", path: "/analisis/fndr", icon: SquareChartGantt, accent: "emerald" },
      { title: "Análisis UPRE", path: "/analisis/upre", icon: SquareChartGantt, accent: "amber" },
      { title: "Análisis FPS", path: "/analisis/fps", icon: SquareChartGantt, accent: "sky" },
      { title: "Análisis Obras Públicas", path: "/analisis/obras-publicas", icon: SquareChartGantt, accent: "violet" },
      { title: "Análisis Proman", path: "/analisis/proman", icon: SquareChartGantt, accent: "rose" },
    ],
  },
  {
    title: "Poyecto",
    icon: FolderKanban,
    children: [
      { title: "Nuevo proyecto", path: "/Nuevo proyecto", icon: Package, accent: "emerald" },
      { title: "Proyecto", path: "/Proyecto", icon: FolderKanban, accent: "sky" },
    ],
  },
  {
    title: "Insumo",
    icon: FolderKanban,
    children: [
      { title: "Insumo", path: "/Insumo", icon: Package, accent: "emerald" },
      { title: "Listar solicitud de insumo", path: "/Listar solicitud de Insumo", icon: Package, accent: "emerald" },
      { title: "Crear Solicitud Insumo", path: "/Crear Solicitud Insumo", icon: FolderKanban, accent: "sky" },
      { title: "Gestionar Solicitud", path: "/Gestionar Solicitud", icon: FolderKanban, accent: "sky" },

    ],
  },
  {
    title: "Parametros",
    icon: FolderKanban,
    children: [
      { title: "Tipo de Insumo", path: "/Tipo de Insumo", icon: Package, accent: "emerald" },
      { title: "Unidad de Medida", path: "/Unidad de Medida", icon: FolderKanban, accent: "sky" },
      { title: "Grupos", path: "/Grupos", icon: FolderKanban, accent: "sky" },
      { title: "Sub grupos", path: "/Sub grupos", icon: FolderKanban, accent: "sky" },
      { title: "% Calculo", path: "/parametros-calculo", icon: FolderKanban, accent: "sky" },
      { title: "% Calculo UPRE", path: "/parametros/upre", icon: FolderKanban, accent: "sky" },
      { title: "% Calculo FPS", path: "/parametros/fps", icon: FolderKanban, accent: "sky" },
      { title: "% Calculo FNDR", path: "/parametros/fndr", icon: FolderKanban, accent: "sky" },
      { title: "% OBRAS PUBLICAS", path: "/parametros/obras-publicas", icon: FolderKanban, accent: "sky" },
      { title: "% PROMAN", path: "/parametros/proman", icon: FolderKanban, accent: "sky" },
    ],
  },
  {
    title: "Administración",
    icon: ShieldCheck,
    children: [
      { title: "Usuarios", path: "/administracion/usuarios", icon: UsersRound, accent: "emerald" },
      { title: "Funciones", path: "/administracion/funciones", icon: Blocks, accent: "sky" },
      { title: "Roles", path: "/administracion/roles", icon: ShieldCheck, accent: "violet" },
      { title: "Autorizaciones", path: "/administracion/autorizaciones", icon: KeyRound, accent: "amber" },
      { title: "Auditoría", path: "/administracion/auditoria", icon: ClipboardClock, accent: "rose" },
    ],
  },
];

export const quickLinks = navigationSections.flatMap((section) =>
  section.children
    ? section.children.map((child) => ({ ...child, section: section.title }))
    : [{ title: section.title, path: section.path, icon: section.icon, accent: "slate", section: section.title }],
);
