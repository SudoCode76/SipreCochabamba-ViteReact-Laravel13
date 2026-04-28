import {
  Blocks,
  CircleUserRound,
  FolderKanban,
  KeyRound,
  LayoutDashboard,
  Package,
  ShieldCheck,
  SlidersHorizontal,
  SquareChartGantt,
  UsersRound,
} from "lucide-react";

export const navigationSections = [
  {
    title: "Inicio",
    path: "/dashboard",
    icon: LayoutDashboard,
    description: "Resumen operativo y accesos rápidos.",
  },
  {
    title: "Operaciones",
    icon: Blocks,
    description: "Flujos principales de análisis y composición.",
    children: [
      { title: "Items", path: "/items", icon: SquareChartGantt, accent: "slate" },
      { title: "Análisis FNDR", path: "/analisis/fndr", icon: SquareChartGantt, accent: "emerald" },
      { title: "Análisis UPRE", path: "/analisis/upre", icon: SquareChartGantt, accent: "amber" },
      { title: "Análisis FPS", path: "/analisis/fps", icon: SquareChartGantt, accent: "sky" },
      { title: "Análisis Obras Públicas", path: "/analisis/obras-publicas", icon: SquareChartGantt, accent: "violet" },
      { title: "Análisis Proman", path: "/analisis/proman", icon: SquareChartGantt, accent: "rose" },
    ],
  },
  {
    title: "Gestión",
    icon: FolderKanban,
    description: "Catálogos, proyectos e insumos del sistema.",
    children: [
      { title: "Insumos", path: "/insumos", icon: Package, accent: "emerald" },
      { title: "Proyectos", path: "/proyectos", icon: FolderKanban, accent: "sky" },
      { title: "Parámetros", path: "/parametros", icon: SlidersHorizontal, accent: "amber" },
    ],
  },
  {
    title: "Administración",
    icon: ShieldCheck,
    description: "Usuarios, permisos y control del sistema.",
    children: [
      { title: "Panel", path: "/administracion", icon: ShieldCheck, accent: "slate" },
      { title: "Usuarios", path: "/administracion/usuarios", icon: UsersRound, accent: "emerald" },
      { title: "Funciones", path: "/administracion/funciones", icon: Blocks, accent: "sky" },
      { title: "Roles", path: "/administracion/roles", icon: ShieldCheck, accent: "violet" },
      { title: "Autorizaciones", path: "/administracion/autorizaciones", icon: KeyRound, accent: "amber" },
    ],
  },
  {
    title: "Perfil",
    icon: CircleUserRound,
    description: "Preferencias personales y seguridad.",
    children: [
      { title: "Perfil", path: "/perfil", icon: CircleUserRound, accent: "slate" },
      { title: "Cambiar contraseña", path: "/perfil/password", icon: KeyRound, accent: "amber" },
    ],
  },
];

export const quickLinks = navigationSections.flatMap((section) =>
  section.children
    ? section.children.map((child) => ({ ...child, section: section.title }))
    : [{ title: section.title, path: section.path, icon: section.icon, accent: "slate", section: section.title }],
);
