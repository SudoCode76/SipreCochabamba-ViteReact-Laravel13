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
      { title: "Item", path: "/items", icon: SquareChartGantt, accent: "emerald", permission: { className: "ITEMS", functions: ["INDEX", "ITEMS"] } },
      { title: "Análisis FNDR", path: "/analisis/fndr", icon: SquareChartGantt, accent: "emerald", permission: { className: "ITEMS", functions: ["FNDR"] } },
      { title: "Análisis UPRE", path: "/analisis/upre", icon: SquareChartGantt, accent: "amber", permission: { className: "ITEMS", functions: ["UPRE"] } },
      { title: "Análisis FPS", path: "/analisis/fps", icon: SquareChartGantt, accent: "sky", permission: { className: "ITEMS", functions: ["FPS"] } },
      { title: "Análisis Obras Públicas", path: "/analisis/obras-publicas", icon: SquareChartGantt, accent: "violet", permission: { className: "ITEMS", functions: ["OBRAS_PUBLICAS"] } },
      { title: "Análisis Proman", path: "/analisis/proman", icon: SquareChartGantt, accent: "rose", permission: { className: "ITEMS", functions: ["PROMAN"] } },
    ],
  },
  {
    title: "Poyecto",
    icon: FolderKanban,
    children: [
      { title: "Nuevo proyecto", path: "/Nuevo proyecto", icon: Package, accent: "emerald", permission: { className: "PROYECTO", functions: ["REGISTRAR_PROYECTO", "NUEVO_PROYECTO"] } },
      { title: "Proyecto", path: "/Proyecto", icon: FolderKanban, accent: "sky", permission: { className: "PROYECTO", functions: ["INDEX", "PROYECTO"] } },
    ],
  },
  {
    title: "Insumo",
    icon: FolderKanban,
    children: [
      { title: "Insumo", path: "/Insumo", icon: Package, accent: "emerald", permission: { className: "INSUMO", functions: ["INSUMO", "INPUTS", "INPUTS_ADMIN", "LISTA_INSUMO"] } },
      { title: "Listar solicitud de insumo", path: "/Listar solicitud de Insumo", icon: Package, accent: "emerald", permission: { className: "INSUMO", functions: ["SOLICITUD", "INPUT_QUOTES", "SOLICITUD_INSUMO", "LISTAR_SOLICITUD_INSUMO"] } },
      { title: "Crear Solicitud Insumo", path: "/Crear Solicitud Insumo", icon: FolderKanban, accent: "sky", permission: { className: "INSUMO", functions: ["NUEVA_SOLICITUD", "INPUT_QUOTES", "CREAR_SOLICITUD_INSUMO"] } },
      { title: "Gestionar Solicitud", path: "/Gestionar Solicitud", icon: FolderKanban, accent: "sky", permission: { className: "INSUMO", functions: ["SOLICITUD", "INPUTS_ADMIN", "GESTIONAR_SOLICITUD_INSUMO"] } },

    ],
  },
  {
    title: "Parametros",
    icon: FolderKanban,
    children: [
      { title: "Tipo de Insumo", path: "/Tipo de Insumo", icon: Package, accent: "emerald", permission: { className: "PARAMETROS", functions: ["TIPOINSUMO", "TIPO_INSUMO", "INPUT_TYPES"] } },
      { title: "Unidad de Medida", path: "/Unidad de Medida", icon: FolderKanban, accent: "sky", permission: { className: "PARAMETROS", functions: ["UNIDAD_MEDIDA", "UNIDAD"] } },
      { title: "Grupos", path: "/Grupos", icon: FolderKanban, accent: "sky", permission: { className: "PARAMETROS", functions: ["GRUPOS", "GRUPO"] } },
      { title: "Sub grupos", path: "/Sub grupos", icon: FolderKanban, accent: "sky", permission: { className: "PARAMETROS", functions: ["SUBGRUPOS", "SUB_GRUPOS", "SUB_GRUPO"] } },
      { title: "% Calculo", path: "/parametros-calculo", icon: FolderKanban, accent: "sky", permission: { className: "PARAMETROS", functions: ["PORCENTAJE_CALCULO", "PARAMETROS_CALCULO"] } },
      { title: "% Calculo UPRE", path: "/parametros/upre", icon: FolderKanban, accent: "sky", permission: { className: "ITEMS", functions: ["UPRE"] } },
      { title: "% Calculo FPS", path: "/parametros/fps", icon: FolderKanban, accent: "sky", permission: { className: "ITEMS", functions: ["FPS"] } },
      { title: "% Calculo FNDR", path: "/parametros/fndr", icon: FolderKanban, accent: "sky", permission: { className: "ITEMS", functions: ["FNDR"] } },
      { title: "% OBRAS PUBLICAS", path: "/parametros/obras-publicas", icon: FolderKanban, accent: "sky", permission: { className: "ITEMS", functions: ["OBRAS_PUBLICAS"] } },
      { title: "% PROMAN", path: "/parametros/proman", icon: FolderKanban, accent: "sky", permission: { className: "ITEMS", functions: ["PROMAN"] } },
    ],
  },
  {
    title: "Administración",
    icon: ShieldCheck,
    children: [
      { title: "Usuarios", path: "/administracion/usuarios", icon: UsersRound, accent: "emerald", permission: { className: "ADMINISTRADOR", functions: ["USUARIOS"] } },
      { title: "Funciones", path: "/administracion/funciones", icon: Blocks, accent: "sky", permission: { className: "ADMINISTRADOR", functions: ["FUNCIONES"] } },
      { title: "Roles", path: "/administracion/roles", icon: ShieldCheck, accent: "violet", permission: { className: "ADMINISTRADOR", functions: ["ROLES"] } },
      { title: "Autorizaciones", path: "/administracion/autorizaciones", icon: KeyRound, accent: "amber", permission: { className: "ADMINISTRADOR", functions: ["AUTORIZACION", "AUTORIZACIONES"] } },
      { title: "Auditoría", path: "/administracion/auditoria", icon: ClipboardClock, accent: "rose", permission: { className: "ADMINISTRADOR", functions: ["AUDITORIA"] } },
    ],
  },
];

export const quickLinks = navigationSections.flatMap((section) =>
  section.children
    ? section.children.map((child) => ({ ...child, section: section.title }))
    : [{ title: section.title, path: section.path, icon: section.icon, accent: "slate", section: section.title }],
);
