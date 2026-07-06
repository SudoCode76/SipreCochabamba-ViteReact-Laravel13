import { CheckCircle2, KeyRound, Loader2, LogOut } from "lucide-react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";

import { Button } from "@/components/ui/button";
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
      <div className="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-left text-sm text-amber-900">
        <div className="flex items-start gap-3">
          <KeyRound className="mt-0.5 size-4 shrink-0" />
          <div className="min-w-0 flex-1">
            <p className="font-semibold">Sesión de Ciudadanía Digital abierta</p>
            <p className="mt-1 text-xs leading-5 text-amber-800">
              Puede cerrarla manualmente antes de volver a intentar la firma.
            </p>
            <Button
              type="button"
              size="sm"
              variant="outline"
              onClick={closeSession}
              disabled={!canLogout || logoutMutation.isPending}
              className="mt-3 rounded-full border-amber-300 bg-white text-amber-900 hover:bg-amber-100"
            >
              {logoutMutation.isPending ? <Loader2 className="size-4 animate-spin" /> : <LogOut className="size-4" />}
              Cerrar sesión de Ciudadanía Digital
            </Button>
          </div>
        </div>
      </div>
    );
  }

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button
          variant="outline"
          size="sm"
          className={`h-10 rounded-full px-3 sm:min-w-48 sm:justify-start ${
            active
              ? "border-amber-200 bg-amber-50 text-amber-800 hover:bg-amber-100 hover:text-amber-900"
              : "border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 hover:text-emerald-900"
          }`}
          title={active ? "Sesión de Ciudadanía Digital abierta" : "Sesión de Ciudadanía Digital cerrada"}
        >
          {isFetching ? (
            <Loader2 className="size-4 animate-spin" />
          ) : active ? (
            <KeyRound className="size-4" />
          ) : (
            <CheckCircle2 className="size-4" />
          )}
          <span className="hidden text-xs font-semibold sm:inline">
            Ciudadanía: {active ? "abierta" : "cerrada"}
          </span>
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" className="w-80 rounded-2xl border-border/70 bg-background/95 p-2 shadow-xl backdrop-blur-xl">
        <DropdownMenuLabel className="px-3 py-2">
          <p className="text-sm font-semibold text-foreground">Ciudadanía Digital</p>
          <p className="mt-1 text-xs font-normal leading-5 text-muted-foreground">
            {active
              ? `Sesión abierta${signature?.code ? ` para ${signature.code}` : ""}.`
              : "No hay una sesión externa abierta."}
          </p>
        </DropdownMenuLabel>
        {active ? (
          <>
            <DropdownMenuSeparator className="bg-border/70" />
            <DropdownMenuItem
              className="rounded-xl px-3 py-2 text-amber-700 focus:bg-amber-50 focus:text-amber-900"
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
