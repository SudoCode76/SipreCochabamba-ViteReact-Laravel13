import { useState, useEffect } from "react";
import { Key, Lock, User, ShieldCheck, Loader2 } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { cn } from "@/lib/utils";
import { useQuery, useMutation } from "@tanstack/react-query";
import { authService } from "@/modules/auth/services/auth.service";

export default function ChangePasswordPage() {
  const [formData, setFormData] = useState({
    currentPassword: "",
    newPassword: "",
    confirmPassword: "",
  });
  const [status, setStatus] = useState({ type: "", message: "" });

  // Obtener perfil del usuario desde la API
  const { data: profile, isLoading: isProfileLoading, isError: isProfileError } = useQuery({
    queryKey: ["profile"],
    queryFn: authService.getProfile,
    retry: false
  });

  // Mutación para cambiar contraseña
  const mutation = useMutation({
    mutationFn: authService.changePassword,
    onSuccess: () => {
      setStatus({ type: "success", message: "Contraseña actualizada correctamente." });
      setFormData({ currentPassword: "", newPassword: "", confirmPassword: "" });
    },
    onError: (error) => {
      const message = error.response?.data?.message || "Error al actualizar la contraseña.";
      setStatus({ type: "error", message });
    }
  });

  const handleChange = (e) => {
    setFormData({ ...formData, [e.target.id]: e.target.value });
  };

  const handleSubmit = (e) => {
    e.preventDefault();
    const { currentPassword, newPassword, confirmPassword } = formData;

    if (!currentPassword || !newPassword || !confirmPassword) {
      setStatus({ type: "error", message: "Por favor, completa todos los campos." });
      return;
    }

    if (newPassword !== confirmPassword) {
      setStatus({ type: "error", message: "Las nuevas contraseñas no coinciden." });
      return;
    }

    if (newPassword.length < 6) {
      setStatus({ type: "error", message: "La contraseña debe tener al menos 6 caracteres." });
      return;
    }

    setStatus({ type: "", message: "" });
    mutation.mutate({
      current_password: currentPassword,
      password: newPassword,
      password_confirmation: confirmPassword
    });
  };

  if (isProfileLoading) {
    return (
      <div className="flex h-[400px] items-center justify-center">
        <Loader2 className="h-8 w-8 animate-spin text-green-600" />
      </div>
    );
  }

  if (isProfileError) {
    return (
      <div className="flex justify-center py-10">
        <Alert variant="destructive" className="max-w-md">
          <AlertDescription>
            No se pudo cargar la información del usuario. Por favor, inicia sesión nuevamente.
          </AlertDescription>
        </Alert>
      </div>
    );
  }

  return (
    <div className="flex justify-center items-center py-10 animate-in fade-in duration-500">
      <Card className="w-full max-w-[500px] border-none shadow-lg">
        <CardContent className="pt-10 pb-10 px-8">
          <form onSubmit={handleSubmit} className="space-y-6">
            
            {/* Usuario Actual (Desde API) */}
            <div className="space-y-2">
              <Label htmlFor="username" className="text-xs font-bold text-slate-500 uppercase tracking-wider">
                Usuario Actual
              </Label>
              <div className="relative">
                <User className="absolute left-3 top-3 h-4 w-4 text-slate-400" />
                <Input
                  id="username"
                  value={profile?.name || profile?.username || "Cargando..."}
                  readOnly
                  className="pl-10 h-11 bg-slate-50 border-slate-200 text-slate-600 font-medium cursor-not-allowed"
                />
              </div>
            </div>

            <div className="h-px bg-slate-100 my-4" />

            {/* Contraseña Actual */}
            <div className="space-y-2">
              <Label htmlFor="currentPassword" title="Contraseña actual" className="text-xs font-bold text-slate-500 uppercase tracking-wider">
                Contraseña Actual
              </Label>
              <div className="relative">
                <Lock className="absolute left-3 top-3 h-4 w-4 text-slate-400" />
                <Input
                  id="currentPassword"
                  type="password"
                  placeholder="••••••••"
                  className="pl-10 h-11 focus:ring-green-500 focus:border-green-500 transition-all"
                  value={formData.currentPassword}
                  onChange={handleChange}
                  disabled={mutation.isPending}
                />
              </div>
            </div>

            {/* Nueva Contraseña */}
            <div className="space-y-2">
              <Label htmlFor="newPassword" title="Nueva contraseña" className="text-xs font-bold text-slate-500 uppercase tracking-wider">
                Nueva Contraseña
              </Label>
              <div className="relative">
                <Key className="absolute left-3 top-3 h-4 w-4 text-slate-400" />
                <Input
                  id="newPassword"
                  type="password"
                  placeholder="Mínimo 6 caracteres"
                  className="pl-10 h-11 focus:ring-green-500 focus:border-green-500 transition-all"
                  value={formData.newPassword}
                  onChange={handleChange}
                  disabled={mutation.isPending}
                />
              </div>
            </div>

            {/* Repetir Nueva Contraseña */}
            <div className="space-y-2">
              <Label htmlFor="confirmPassword" title="Repetir nueva contraseña" className="text-xs font-bold text-slate-500 uppercase tracking-wider">
                Repetir Nueva Contraseña
              </Label>
              <div className="relative">
                <Key className="absolute left-3 top-3 h-4 w-4 text-slate-400" />
                <Input
                  id="confirmPassword"
                  type="password"
                  placeholder="Confirma tu contraseña"
                  className="pl-10 h-11 focus:ring-green-500 focus:border-green-500 transition-all"
                  value={formData.confirmPassword}
                  onChange={handleChange}
                  disabled={mutation.isPending}
                />
              </div>
            </div>

            {status.message && (
              <Alert variant={status.type === "error" ? "destructive" : "default"} className={cn(
                "py-3",
                status.type === "success" && "border-green-200 bg-green-50 text-green-800"
              )}>
                <AlertDescription className="text-sm font-medium">
                  {status.message}
                </AlertDescription>
              </Alert>
            )}

            <div className="pt-2">
              <Button
                type="submit"
                className="w-full h-12 bg-[#00695c] hover:bg-[#004d40] text-white font-bold transition-all flex items-center justify-center gap-2 shadow-lg shadow-green-900/10"
                disabled={mutation.isPending}
              >
                {mutation.isPending ? (
                  <Loader2 className="h-4 w-4 animate-spin" />
                ) : (
                  <Key className="h-4 w-4" />
                )}
                <span>{mutation.isPending ? "Actualizando..." : "Cambiar Contraseña"}</span>
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>
    </div>
  );
}

