import { Outlet, NavLink, useNavigate } from "react-router-dom";
import { 
  Home, 
  FileText, 
  Layers, 
  Box, 
  Settings, 
  Users, 
  User,
  ChevronDown,
  Key,
  Lock,
  List,
  LayoutGrid
} from "lucide-react";
import { cn } from "@/lib/utils";

import { useState } from "react";

export default function MainLayout() {
  const navigate = useNavigate();
  const [activeDropdown, setActiveDropdown] = useState(null);

  const menuItems = [
    { 
      name: "Página Principal", 
      path: "/dashboard", 
      icon: Home, 
      hasDropdown: true,
      children: [
        { name: "Item", path: "/items", icon: List },
        { name: "Análisis FNDR", path: "/analisis/fndr", icon: LayoutGrid },
        { name: "Análisis UPRE", path: "/analisis/upre", icon: LayoutGrid },
        { name: "Análisis FPS", path: "/analisis/fps", icon: LayoutGrid },
        { name: "Análisis Obras Públicas", path: "/analisis/obras-publicas", icon: LayoutGrid },
        { name: "Análisis Proman", path: "/analisis/proman", icon: LayoutGrid },
      ]
    },
    { name: "Ítem", path: "/items", icon: FileText, hasDropdown: true },
    { name: "Proyecto", path: "/proyectos", icon: Layers, hasDropdown: true },
    { name: "Insumo", path: "/insumos", icon: Box, hasDropdown: true },
    { name: "Parámetros", path: "/parametros", icon: Settings, hasDropdown: true },
    { 
      name: "Administración", 
      path: "/administracion", 
      icon: Users, 
      hasDropdown: true,
      children: [
        { name: "Usuarios", path: "/administracion/usuarios", icon: User },
        { name: "Funciones", path: "/administracion/funciones", icon: Settings },
        { name: "Roles", path: "/administracion/roles", icon: Layers },
        { name: "Autorizaciones", path: "/administracion/autorizaciones", icon: Box },
      ]
    },
    { 
      name: "Perfil de Usuario", 
      path: "/perfil", 
      icon: User, 
      hasDropdown: true,
      children: [
        { name: "Cambiar Contraseña", path: "/perfil/password", icon: Key },
        { name: "Cerrar Sesión", path: "/login", icon: Lock, action: () => navigate("/login") },
      ]
    },
  ];


  return (
    <div className="min-h-screen bg-slate-100 flex flex-col font-sans">
      
      {/* Horizontal Navbar */}
      <header className="bg-[#2c333d] text-white shadow-lg z-50">
        <div className="max-w-[1600px] mx-auto flex items-center justify-between h-20 px-6">
          
          {/* Left Side: User Info */}
          <div className="flex flex-col justify-center border-l-4 border-amber-500 pl-4 py-1">
            <h1 className="text-sm font-bold tracking-tight text-slate-300">SIPRE</h1>
            <p className="text-xs font-bold uppercase tracking-tight">GUSTAVO CLAURE FLORES</p>
            <p className="text-[9px] text-slate-400 font-medium mt-0.5">UNIDAD: DEPARTAMENTO DE SISTEMAS</p>
          </div>

          {/* Right Side: Navigation Items */}
          <nav className="flex h-full items-center">
            {menuItems.map((item) => (
              <div 
                key={item.path} 
                className="h-full relative group"
                onMouseEnter={() => item.children && setActiveDropdown(item.name)}
                onMouseLeave={() => setActiveDropdown(null)}
              >
                <NavLink
                  to={item.path}
                  className={({ isActive }) => cn(
                    "flex flex-col items-center justify-center h-full px-5 transition-all duration-200 group relative",
                    isActive 
                      ? "bg-[#5c9adb] text-white shadow-inner" 
                      : "text-slate-300 hover:text-white hover:bg-white/5"
                  )}
                >
                  <div className="flex flex-col items-center gap-1.5">
                    <div className="flex items-center gap-1">
                      <item.icon className={cn(
                        "h-5 w-5",
                        "text-[#4fd1c5] group-hover:text-white transition-colors"
                      )} />
                      {item.hasDropdown && <ChevronDown className="h-3 w-3 text-slate-500" />}
                    </div>
                    <span className="text-[10px] font-bold uppercase tracking-tight text-center max-w-[80px] leading-tight">
                      {item.name}
                    </span>
                  </div>
                </NavLink>

                {/* Dropdown Menu */}
                {item.children && activeDropdown === item.name && (
                  <div className="absolute top-full left-0 w-56 bg-white shadow-xl border border-slate-200 py-2 animate-in fade-in slide-in-from-top-2 duration-200 z-[100]">
                    {item.children.map((child) => (
                      <NavLink
                        key={child.path}
                        to={child.path}
                        onClick={(e) => {
                          if (child.action) {
                            e.preventDefault();
                            child.action();
                          }
                          setActiveDropdown(null);
                        }}
                        className="flex items-center gap-3 px-4 py-3 text-sm text-slate-600 hover:bg-slate-50 hover:text-[#5c9adb] transition-colors group/item"
                      >
                        <child.icon className="h-4 w-4 text-[#4fd1c5] group-hover/item:text-[#5c9adb]" />
                        <span className="font-medium">{child.name}</span>
                      </NavLink>
                    ))}
                  </div>
                )}

              </div>
            ))}
          </nav>
        </div>
      </header>


      {/* Main Content Area */}
      <main className="flex-1 overflow-y-auto p-8">
        <div className="max-w-7xl mx-auto">
          <Outlet />
        </div>
      </main>

      {/* Footer Branding */}
      <footer className="bg-white border-t border-slate-200 py-3 px-8 flex justify-between items-center text-[10px] text-slate-400 font-bold uppercase tracking-widest">
         <span>Gobierno Autónomo Municipal de Cochabamba</span>
         <span>SIPRE v2.0 - 2026</span>
      </footer>
    </div>
  );
}
