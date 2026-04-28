import { useState } from "react";
import { ArrowRight, KeyRound } from "lucide-react";
import { useNavigate } from "react-router-dom";

import { Alert, AlertDescription } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
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

export default function LoginPage() {
  const navigate = useNavigate();
  const [username, setUsername] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState("");
  const [isLoading, setIsLoading] = useState(false);

  const handleSubmit = (event) => {
    event.preventDefault();

    if (!username || !password) {
      setError("Por favor, completa todos los campos.");
      return;
    }

    setError("");
    setIsLoading(true);

    setTimeout(() => {
      setIsLoading(false);
      navigate("/dashboard");
    }, 1000);
  };

  return (
    <div className="relative flex min-h-screen items-center justify-center overflow-hidden bg-background px-4 py-10">
      <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top,_rgba(15,23,42,0.1),_transparent_55%)]" />

      <div className="relative z-10 w-full max-w-[620px]">
        <Card className="border border-border/70 bg-white/88 shadow-[0_30px_120px_rgba(15,23,42,0.1)] backdrop-blur">
          <CardHeader className="gap-4 px-8 pt-8 sm:px-10 sm:pt-10">
            <div className="flex items-center justify-between gap-4">
              <div>
                <CardTitle className="text-3xl tracking-[-0.05em] text-foreground">Iniciar sesión</CardTitle>
                <CardDescription className="mt-2 max-w-sm text-sm leading-6">
                  Accede al sistema con una interfaz más clara y enfocada en productividad.
                </CardDescription>
              </div>
              <div className="flex size-12 items-center justify-center rounded-[18px] bg-foreground text-background">
                <KeyRound className="size-5" />
              </div>
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
