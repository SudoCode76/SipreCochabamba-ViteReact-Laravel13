import { ArrowRight, ChevronDown, Loader2, LogOut, ShieldCheck, User } from "lucide-react";
import { Link, NavLink, Outlet, useLocation } from "react-router-dom";

import { navigationSections } from "@/app/navigation";
import { Button } from "@/components/ui/button";
import { useAuth } from "@/modules/auth/hooks/useAuth";
import { DropdownMenu, DropdownMenuContent, DropdownMenuGroup, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger } from "@/components/ui/dropdown-menu";
import { canAccessNavigationItem } from "@/lib/auth/permissions";
import { cn } from "@/lib/utils";

function isSectionActive(section, pathname) {
  if (section.path) {
    return pathname === section.path;
  }

  return section.children?.some((child) => pathname === child.path) ?? false;
}

export default function MainLayout() {
  const location = useLocation();
  const { user, logout, isLoggingOut } = useAuth();
  const visibleNavigationSections = navigationSections
    .map((section) => {
      if (!section.children) {
        return canAccessNavigationItem(user, section) ? section : null;
      }

      const children = section.children.filter((child) => canAccessNavigationItem(user, child));

      return children.length > 0 ? { ...section, children } : null;
    })
    .filter(Boolean);

  return (
    <div className="relative min-h-screen bg-background text-foreground">
      {isLoggingOut && (
        <div className="fixed inset-0 z-[100] flex items-center justify-center bg-background/75 backdrop-blur-sm">
          <div className="flex items-center gap-3 rounded-2xl border border-border/70 bg-background px-5 py-4 text-sm font-medium shadow-xl">
            <Loader2 className="size-5 animate-spin text-muted-foreground" />
            Cerrando sesión...
          </div>
        </div>
      )}

      <div className="pointer-events-none absolute inset-x-0 top-0 h-[460px] bg-[radial-gradient(circle_at_top,_rgba(15,23,42,0.07),_transparent_62%)]" />

      <header className="sticky top-0 z-50 px-4 pt-4 sm:px-6">
        <div className="mx-auto flex w-full max-w-7xl items-center justify-between gap-3 rounded-[28px] border border-border/70 bg-background/88 px-4 py-3 shadow-[0_18px_60px_rgba(15,23,42,0.08)] backdrop-blur-xl sm:px-5">
          <Link to="/dashboard" className="flex min-w-0 items-center gap-3">
            <div className="flex size-10 items-center justify-center rounded-2xl bg-foreground text-background shadow-sm">
              <ShieldCheck className="size-4" />
            </div>
            <div className="min-w-0">
              <p className="truncate text-sm font-semibold uppercase tracking-[0.22em] text-foreground">
                SIPRE
              </p>
              <p className="truncate text-xs text-muted-foreground">
                Sistema de Proyectos e Insumos
              </p>
            </div>
          </Link>

          <nav className="hidden items-center gap-2 lg:flex">
            {visibleNavigationSections.map((section) => {
              const active = isSectionActive(section, location.pathname);

              if (section.children) {
                return (
                  <DropdownMenu key={section.title}>
                    <DropdownMenuTrigger asChild>
                      <Button
                        variant={active ? "default" : "outline"}
                        size="sm"
                        className={cn(
                          "rounded-full border-border/70 px-3.5",
                          active
                            ? "bg-foreground text-background shadow-sm"
                            : "bg-background/80 text-muted-foreground hover:text-foreground",
                        )}
                      >
                        <section.icon data-icon="inline-start" />
                        {section.title}
                        <ChevronDown data-icon="inline-end" />
                      </Button>
                    </DropdownMenuTrigger>

                    <DropdownMenuContent className="w-72 rounded-3xl border border-border/70 bg-background/95 p-2 shadow-[0_24px_80px_rgba(15,23,42,0.14)] backdrop-blur-xl" align="center">
                      <DropdownMenuLabel className="px-3 py-2 text-[11px] uppercase tracking-[0.2em] text-muted-foreground">
                        {section.description}
                      </DropdownMenuLabel>
                      <DropdownMenuSeparator className="bg-border/70" />
                      <DropdownMenuGroup>
                        {section.children.map((child) => (
                          <DropdownMenuItem key={child.path} asChild className="rounded-2xl px-3 py-3">
                            <Link to={child.path} className="flex items-center gap-3">
                              <div className="flex size-9 items-center justify-center rounded-2xl border border-border/70 bg-muted/40">
                                <child.icon className="size-4 text-foreground" />
                              </div>
                              <div className="flex flex-1 flex-col gap-0.5">
                                <span className="font-medium text-foreground">{child.title}</span>
                              </div>
                              <ArrowRight className="size-4 text-muted-foreground" />
                            </Link>
                          </DropdownMenuItem>
                        ))}
                      </DropdownMenuGroup>
                    </DropdownMenuContent>
                  </DropdownMenu>
                );
              }

              return (
                <NavLink key={section.path} to={section.path}>
                  {({ isActive }) => (
                    <Button
                      variant={isActive ? "default" : "outline"}
                      size="sm"
                      className={cn(
                        "rounded-full border-border/70 px-3.5",
                        isActive
                          ? "bg-foreground text-background shadow-sm"
                          : "bg-background/80 text-muted-foreground hover:text-foreground",
                      )}
                    >
                      <section.icon data-icon="inline-start" />
                      {section.title}
                    </Button>
                  )}
                </NavLink>
              );
            })}
          </nav>

          <div className="flex items-center gap-3">
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="icon" className="size-10 rounded-full border border-border/70 bg-background/80 hover:bg-muted">
                  <User className="size-5" />
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end" className="w-56 rounded-2xl border-border/70 bg-background/95 p-2 shadow-xl backdrop-blur-xl">
                <DropdownMenuLabel className="px-2 py-2">
                  <p className="text-sm font-medium text-foreground">{user?.username || "Usuario"}</p>                </DropdownMenuLabel>
                <DropdownMenuSeparator className="bg-border/70" />
                <DropdownMenuItem asChild className="rounded-xl px-3 py-2 cursor-pointer">
                  <Link to="/perfil/password">
                    <User className="mr-2 size-4" />
                    <span>Cambiar contraseña</span>
                  </Link>
                </DropdownMenuItem>
                <DropdownMenuItem
                  className="rounded-xl px-3 py-2 text-destructive focus:bg-destructive/10 focus:text-destructive cursor-pointer mt-1"
                  onClick={logout}
                  disabled={isLoggingOut}
                >
                  <LogOut className="mr-2 size-4" />
                  <span>{isLoggingOut ? "Saliendo..." : "Cerrar sesión"}</span>
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </div>
        </div>
      </header>

      <main className="relative z-10 px-4 pb-10 pt-6 sm:px-6 sm:pb-12">
        <div className="mx-auto max-w-7xl">
          <Outlet />
        </div>
      </main>
    </div>
  );
}
