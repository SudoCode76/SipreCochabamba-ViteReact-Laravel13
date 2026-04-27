import { useState } from "react";
import { Key, Building } from "lucide-react";
import { Button } from "@/components/ui/button";
import {
  Card,
  CardContent,
  CardHeader,
} from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { cn } from "@/lib/utils";


import { useNavigate } from "react-router-dom";

export default function Login() {
  const navigate = useNavigate();
  const [username, setUsername] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState("");
  const [isLoading, setIsLoading] = useState(false);

  const handleSubmit = (e) => {
    e.preventDefault();
    if (!username || !password) {
      setError("Por favor, completa todos los campos.");
      return;
    }
    setError("");
    setIsLoading(true);

    // Simulación de carga
    setTimeout(() => {
      setIsLoading(false);
      navigate("/dashboard");
    }, 1000);
  };


  return (
    <div className="flex min-h-screen w-full flex-col items-center justify-center bg-[#2c333d] p-4 font-sans">

      {/* Header Institucional (Cochabamba) */}
      <div className="mb-8 flex flex-col items-center text-center text-white">
        <div className="mb-2 flex h-12 w-12 items-center justify-center rounded-full bg-white/10 p-2 shadow-inner">
          <Building className="h-8 w-8 text-amber-400" />
        </div>

        <h2 className="text-sm font-bold uppercase tracking-widest">Cochabamba</h2>
        <p className="text-[10px] opacity-80">Gobierno de la Ciudad</p>
      </div>

      <Card className="w-full max-w-[420px] border-none bg-white p-2 shadow-2xl transition-all duration-500">
        <CardHeader className="flex flex-col items-center pt-8 pb-6">
          {/* Logo SIPRE (Simulado) */}
          <div className="flex items-center gap-2">
            <div className="flex h-12 w-12 items-center justify-center rounded-full border-4 border-green-500 p-1">
              <div className="text-[10px] font-black text-green-600">BS</div>
            </div>
            <h1 className="text-5xl font-black tracking-tighter text-slate-800">
              SIPRE<span className="text-green-600">I</span>
            </h1>
          </div>
        </CardHeader>

        <CardContent className="px-8 pb-10">
          <form onSubmit={handleSubmit} className="space-y-5">
            <div className="space-y-1.5">
              <Label htmlFor="username" className="text-xs font-bold text-slate-600 uppercase">
                Usuario
              </Label>
              <Input
                id="username"
                className="h-11 rounded-none border-slate-300 bg-white transition-all focus:border-green-600 focus:ring-0"
                value={username}
                onChange={(e) => setUsername(e.target.value)}
                disabled={isLoading}
              />
            </div>

            <div className="space-y-1.5">
              <Label htmlFor="password" title="Contraseña" className="text-xs font-bold text-slate-600 uppercase">
                Contraseña
              </Label>
              <Input
                id="password"
                type="password"
                className="h-11 rounded-none border-slate-300 bg-white transition-all focus:border-green-600 focus:ring-0"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                disabled={isLoading}
              />
            </div>

            {error && (
              <Alert variant="destructive" className="py-2 animate-in fade-in duration-300">
                <AlertDescription className="text-xs font-medium">
                  {error}
                </AlertDescription>
              </Alert>
            )}

            <Button
              type="submit"
              className={cn(
                "w-full h-12 rounded-none bg-[#00695c] hover:bg-[#004d40] text-white font-bold transition-all flex items-center justify-center gap-2",
                isLoading && "opacity-80"
              )}
              disabled={isLoading}
            >
              <Key className="h-4 w-4 rotate-90" />
              <span>{isLoading ? "Iniciando..." : "Iniciar Sesión"}</span>
            </Button>
          </form>
        </CardContent>
      </Card>

      <div className="mt-8 text-center text-[10px] text-white/40 uppercase tracking-widest font-medium">
        Sistema de Gestión de Proyectos e Insumos
      </div>
    </div>
  );
}


