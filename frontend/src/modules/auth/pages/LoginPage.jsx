import { useState } from "react";
import { ArrowRight } from "lucide-react";
import { useNavigate } from "react-router-dom";
import { useQueryClient } from "@tanstack/react-query";
import { authService } from "../services/auth.service";

import { Alert, AlertDescription } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { ThemeSwitcher } from "@/components/theme-switcher";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { cn } from "@/lib/utils";

function prefetchFrequentRoutes() {
  void Promise.allSettled([
    import("@/modules/dashboard/pages/DashboardPage"),
    import("@/modules/projects/pages/ProjectsPage"),
    import("@/modules/items/pages/ItemsPage"),
    import("@/modules/inputs/pages/InputsPage"),
  ]);
}

export default function LoginPage() {
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const [username, setUsername] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState("");
  const [isLoading, setIsLoading] = useState(false);

  const handleSubmit = async (event) => {
    event.preventDefault();

    if (!username || !password) {
      setError("Por favor, completa todos los campos.");
      return;
    }

    setError("");
    setIsLoading(true);

    try {
      const data = await authService.login({ username, password });
      
      if (data.success === false) {
        // Handle explicit success: false from backend (like the Bruno screenshot)
        let errorMsg = data.message || "Credenciales inválidas.";
        if (data.errors && data.errors.username) {
          errorMsg = data.errors.username[0];
        }
        setError(errorMsg);
        setIsLoading(false);
        return;
      }

      queryClient.setQueryData(["auth-user"], data?.data?.user || data?.user || data);
      prefetchFrequentRoutes();
      navigate("/dashboard", { replace: true });
    } catch (err) {
      // Handle axios errors
      if (err.response && err.response.data) {
        const errorData = err.response.data;
        let errorMsg = errorData.message || "Error al iniciar sesión.";
        if (errorData.errors && errorData.errors.username) {
          errorMsg = errorData.errors.username[0];
        }
        setError(errorMsg);
      } else {
        setError("Error de conexión al servidor.");
      }
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <div className="relative flex min-h-screen items-center justify-center overflow-hidden bg-background px-4 py-10">
      <ThemeSwitcher className="absolute right-4 top-4 z-20 sm:right-6 sm:top-6" />

      <div className="relative z-10 w-full max-w-[620px]">
        <Card className="border border-border/70 bg-card/88 shadow-xl backdrop-blur">
          <CardHeader className="gap-5 px-8 pt-8 text-center sm:px-10 sm:pt-10">
            <div className="flex justify-center">
              <img
                src="/img/logo-dark.png"
                alt="SIPRE"
                width={780}
                height={300}
                className="theme-fixed-light h-auto w-full max-w-[280px] rounded-2xl bg-white p-3 object-contain"
              />
            </div>
            <div>
              <CardTitle className="text-3xl tracking-[-0.05em] text-foreground">Iniciar sesión</CardTitle>
              <CardDescription className="mx-auto mt-2 max-w-sm text-sm leading-6">
                Accede al sistema con una interfaz más clara y enfocada en productividad.
              </CardDescription>
            </div>
          </CardHeader>

          <CardContent className="px-8 pb-8 sm:px-10 sm:pb-10">
            <form onSubmit={handleSubmit} className="flex flex-col gap-5">
              <div className="flex flex-col gap-1.5">
                <Label htmlFor="username" className="text-[11px] font-semibold uppercase tracking-[0.2em] text-muted-foreground">
                  Usuario
                </Label>
                <Input
                  id="username"
                  className="h-12 rounded-2xl border-border/80 bg-background/90 px-4 text-sm shadow-none transition-all focus-visible:ring-1 focus-visible:ring-foreground/20 focus-visible:ring-offset-0"
                  value={username}
                  onChange={(event) => setUsername(event.target.value)}
                  disabled={isLoading}
                  placeholder="Tu usuario institucional"
                />
              </div>

              <div className="flex flex-col gap-1.5">
                <Label htmlFor="password" title="Contraseña" className="text-[11px] font-semibold uppercase tracking-[0.2em] text-muted-foreground">
                  Contraseña
                </Label>
                <Input
                  id="password"
                  type="password"
                  className="h-12 rounded-2xl border-border/80 bg-background/90 px-4 text-sm shadow-none transition-all focus-visible:ring-1 focus-visible:ring-foreground/20 focus-visible:ring-offset-0"
                  value={password}
                  onChange={(event) => setPassword(event.target.value)}
                  disabled={isLoading}
                  placeholder="Ingresa tu contraseña"
                />
              </div>

              {error && (
                <Alert variant="destructive" className="rounded-2xl border-destructive/20 bg-destructive/5 py-3 animate-in fade-in duration-300">
                  <AlertDescription className="text-sm font-medium">
                    {error}
                  </AlertDescription>
                </Alert>
              )}

              <Button
                type="submit"
                className={cn(
                  "mt-1 h-12 rounded-full bg-foreground text-background transition-all hover:bg-foreground/90",
                  isLoading && "opacity-85",
                )}
                disabled={isLoading}
              >
                <span>{isLoading ? "Ingresando..." : "Entrar al sistema"}</span>
                <ArrowRight data-icon="inline-end" />
              </Button>
            </form>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
