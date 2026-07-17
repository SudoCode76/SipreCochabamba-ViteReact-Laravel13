import { CheckCircle2, KeyRound, Loader2, LogOut } from "lucide-react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";

import { Button } from "@/components/ui/button";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger } from "@/components/ui/dropdown-menu";
import { useToast } from "@/components/ui/toast";
import { citizenshipService, citizenshipSessionKey } from "@/modules/pdf/services/citizenship.service";

function readApiError(error) {
  const payload = error?.response?.data;
  const firstFieldError = payload?.errors ? Object.values(payload.errors).flat().find(Boolean) : "";

  return firstFieldError || payload?.message || error?.message || "No se pudo cerrar la sesión de Ciudadanía Digital.";
}

function isMissingTokenLogoutError(error) {
  return error?.response?.status === 422 && readApiError(error).includes("token válido");
}

export function CitizenshipSessionIndicator({ compact = false }) {
  const toast = useToast();
  const queryClient = useQueryClient();
  const { data, isFetching } = useQuery({
    queryKey: citizenshipSessionKey,
    queryFn: citizenshipService.session,
    staleTime: 30 * 1000,
    refetchOnWindowFocus: true,
  });
  const session = data?.data;
  const active = Boolean(session?.active);
  const canLogout = Boolean(session?.can_logout);
  const signature = session?.signature;
  const logoutMutation = useMutation({
    mutationFn: citizenshipService.logout,
    onSuccess: (response) => {
      const logoutRedirectUrl = response?.data?.logout_redirect_url;

      if (logoutRedirectUrl) {
        queryClient.setQueryData(citizenshipSessionKey, response);
        window.location.assign(logoutRedirectUrl);
        return;
      }

      void queryClient.invalidateQueries({ queryKey: citizenshipSessionKey });
    },
    onError: (error) => {
      if (isMissingTokenLogoutError(error)) {
        queryClient.setQueryData(citizenshipSessionKey, {
          success: true,
          data: {
            active: false,
            can_logout: false,
            logout_redirect_url: null,
            signature: null,
          },
        });
        toast.success("La sesión ya estaba cerrada.");
        return;
      }

      toast.error(readApiError(error));
    },
  });

  const closeSession = () => {
    if (!canLogout || logoutMutation.isPending) {
      return;
    }

    logoutMutation.mutate();
  };

  if (compact && !active) {
    return null;
  }

  if (compact) {
    return (
      <Alert className="mt-5 text-left">
        <KeyRound />
        <AlertTitle>Sesión de Ciudadanía Digital abierta</AlertTitle>
        <AlertDescription>
          <p>Puede cerrarla manualmente antes de volver a intentar la firma.</p>
          <Button
            type="button"
            size="sm"
            variant="outline"
            onClick={closeSession}
            disabled={!canLogout || logoutMutation.isPending}
            className="mt-3"
          >
            {logoutMutation.isPending ? <Loader2 className="size-4 animate-spin" /> : <LogOut className="size-4" />}
            Cerrar sesión
          </Button>
        </AlertDescription>
      </Alert>
    );
  }

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button
          variant="outline"
          size="sm"
          className="h-9"
          title={active ? "Sesión de Ciudadanía Digital abierta" : "Sesión de Ciudadanía Digital cerrada"}
        >
          {isFetching ? (
            <Loader2 className="size-4 animate-spin" />
          ) : active ? (
            <KeyRound className="size-4" />
          ) : (
            <CheckCircle2 className="size-4" />
          )}
          <span className="hidden sm:inline">Ciudadanía Digital</span>
          <Badge variant={active ? "secondary" : "outline"} className="hidden sm:inline-flex">
            {active ? "Abierta" : "Cerrada"}
          </Badge>
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" className="w-[calc(100vw-2rem)] max-w-80">
        <DropdownMenuLabel>
          <p className="text-sm font-medium text-foreground">Ciudadanía Digital</p>
          <p className="text-xs font-normal leading-5 text-muted-foreground">
            {active
              ? `Sesión abierta${signature?.code ? ` para ${signature.code}` : ""}.`
              : "No hay una sesión externa abierta."}
          </p>
        </DropdownMenuLabel>
        {active ? (
          <>
            <DropdownMenuSeparator />
            <DropdownMenuItem
              variant="destructive"
              onClick={closeSession}
              disabled={!canLogout || logoutMutation.isPending}
            >
              {logoutMutation.isPending ? <Loader2 className="mr-2 size-4 animate-spin" /> : <LogOut className="mr-2 size-4" />}
              <span>{canLogout ? "Cerrar sesión de Ciudadanía Digital" : "No se puede cerrar automáticamente"}</span>
            </DropdownMenuItem>
          </>
        ) : null}
      </DropdownMenuContent>
    </DropdownMenu>
  );
}
