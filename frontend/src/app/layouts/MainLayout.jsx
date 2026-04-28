import { ArrowRight, ChevronDown, ShieldCheck } from "lucide-react";
import { Link, NavLink, Outlet, useLocation } from "react-router-dom";

import { navigationSections } from "@/app/navigation";
import { Button } from "@/components/ui/button";
import { DropdownMenu, DropdownMenuContent, DropdownMenuGroup, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger } from "@/components/ui/dropdown-menu";
import { cn } from "@/lib/utils";

function isSectionActive(section, pathname) {
  if (section.path) {
    return pathname === section.path;
  }

  return section.children?.some((child) => pathname === child.path) ?? false;
}

export default function MainLayout() {
  const location = useLocation();

  return (
    <div className="relative min-h-screen bg-background text-foreground">
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
            {navigationSections.map((section) => {
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
                                <span className="text-xs text-muted-foreground">{section.title}</span>
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
