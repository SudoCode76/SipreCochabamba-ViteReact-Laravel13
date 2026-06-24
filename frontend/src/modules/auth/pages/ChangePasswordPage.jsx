import { useState } from "react";
import { ArrowRight, KeyRound, Loader2, LockKeyhole, UserRound } from "lucide-react";
import { useMutation, useQuery } from "@tanstack/react-query";

import { authService } from "@/modules/auth/services/auth.service";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { useToast } from "@/components/ui/toast";
import { cn } from "@/lib/utils";

export default function ChangePasswordPage() {
  const toast = useToast();
  const [formData, setFormData] = useState({
    currentPassword: "",
    newPassword: "",
    confirmPassword: "",
  });
  const [status, setStatus] = useState({ type: "", message: "" });

  const { data: profile, isLoading: isProfileLoading, isError: isProfileError } = useQuery({
    queryKey: ["profile"],
    queryFn: authService.getProfile,
    retry: false,
  });

  const mutation = useMutation({
    mutationFn: authService.changePassword,
    onSuccess: () => {
      setStatus({ type: "success", message: "Contraseña actualizada correctamente." });
      toast.success("Contraseña actualizada correctamente.");
      setFormData({ currentPassword: "", newPassword: "", confirmPassword: "" });
    },
    onError: (error) => {
      const message = error.response?.data?.message || "Error al actualizar la contraseña.";
      setStatus({ type: "error", message });
    },
  });

  const handleChange = (event) => {
    setFormData({ ...formData, [event.target.id]: event.target.value });
  };

  const handleSubmit = (event) => {
    event.preventDefault();

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
      password_confirmation: confirmPassword,
    });
  };

  if (isProfileLoading) {
    return (
      <div className="flex h-[420px] items-center justify-center">
        <div className="flex items-center gap-3 rounded-full border border-border/70 bg-background/85 px-5 py-3 shadow-[0_18px_60px_rgba(15,23,42,0.08)] backdrop-blur">
          <Loader2 className="size-4 animate-spin text-foreground" />
          <span className="text-sm text-muted-foreground">Cargando perfil de seguridad</span>
        </div>
      </div>
    );
  }

  if (isProfileError) {
    return (
      <div className="flex justify-center py-10">
        <Alert variant="destructive" className="max-w-xl rounded-[24px] border-destructive/20 bg-destructive/5">
          <AlertDescription>
            No se pudo cargar la información del usuario. Por favor, inicia sesión nuevamente.
          </AlertDescription>
        </Alert>
      </div>
    );
  }

  const currentUser = profile?.data?.user?.full_name
    || profile?.data?.user?.username
    || profile?.name
    || profile?.username
    || "Cargando...";

  return (
    <div className="flex justify-center py-4 animate-in fade-in duration-500">
      <Card className="w-full max-w-4xl border border-border/70 bg-white/86 shadow-[0_24px_90px_rgba(15,23,42,0.08)] backdrop-blur">
        <CardHeader className="gap-4">
          <CardTitle className="text-3xl tracking-[-0.05em]">Cambiar contraseña</CardTitle>
        </CardHeader>

        <CardContent className="px-6 pb-6 sm:px-8 sm:pb-8">
          <form onSubmit={handleSubmit} className="flex flex-col gap-6">
            <div className="rounded-[28px] border border-border/70 bg-muted/35 p-5">
              <div className="flex flex-col gap-2">
                <Label htmlFor="username" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                  Usuario actual
                </Label>
                <div className="relative">
                  <UserRound className="absolute left-4 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                  <Input
                    id="username"
                    value={currentUser}
                    readOnly
                    className="h-12 rounded-2xl border-border/70 bg-background/90 pl-11 text-sm font-medium text-foreground"
                  />
                </div>
              </div>
            </div>

            <div className="grid gap-5">
              <div className="flex flex-col gap-1.5">
                <Label htmlFor="currentPassword" title="Contraseña actual" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                  Contraseña actual
                </Label>
                <div className="relative">
                  <LockKeyhole className="absolute left-4 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                  <Input
                    id="currentPassword"
                    type="password"
                    placeholder="Contraseña vigente"
                    className="h-12 rounded-2xl border-border/80 bg-background/90 pl-11 text-sm"
                    value={formData.currentPassword}
                    onChange={handleChange}
                    disabled={mutation.isPending}
                  />
                </div>
              </div>

              <div className="grid gap-5">
                <div className="flex flex-col gap-1.5">
                  <Label htmlFor="newPassword" title="Nueva contraseña" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                    Nueva contraseña
                  </Label>
                  <div className="relative">
                    <KeyRound className="absolute left-4 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                      id="newPassword"
                      type="password"
                      placeholder="Mínimo 6 caracteres"
                      className="h-12 rounded-2xl border-border/80 bg-background/90 pl-11 text-sm"
                      value={formData.newPassword}
                      onChange={handleChange}
                      disabled={mutation.isPending}
                    />
                  </div>
                </div>

                <div className="flex flex-col gap-1.5">
                  <Label htmlFor="confirmPassword" title="Repetir nueva contraseña" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                    Confirmar contraseña
                  </Label>
                  <div className="relative">
                    <KeyRound className="absolute left-4 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                      id="confirmPassword"
                      type="password"
                      placeholder="Repite la contraseña"
                      className="h-12 rounded-2xl border-border/80 bg-background/90 pl-11 text-sm"
                      value={formData.confirmPassword}
                      onChange={handleChange}
                      disabled={mutation.isPending}
                    />
                  </div>
                </div>
              </div>
            </div>

            {status.message && (
              <Alert
                variant={status.type === "error" ? "destructive" : "default"}
                className={cn(
                  "rounded-[22px] border-border/70 bg-background/85 py-3",
                  status.type === "success" && "border-emerald-200 bg-emerald-50 text-emerald-900",
                )}
              >
                <AlertDescription className="text-sm font-medium">
                  {status.message}
                </AlertDescription>
              </Alert>
            )}

            <div className="flex justify-end">
              <Button
                type="submit"
                className="h-12 rounded-full bg-foreground px-5 text-background hover:bg-foreground/90"
                disabled={mutation.isPending}
              >
                {mutation.isPending ? (
                  <Loader2 className="animate-spin" data-icon="inline-start" />
                ) : (
                  <KeyRound data-icon="inline-start" />
                )}
                <span>{mutation.isPending ? "Actualizando..." : "Actualizar contraseña"}</span>
                <ArrowRight data-icon="inline-end" />
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>
    </div>
  );
}
