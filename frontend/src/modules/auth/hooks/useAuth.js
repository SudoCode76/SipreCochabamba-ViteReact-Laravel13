import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useNavigate } from "react-router-dom";
import { clearItemsMaintenanceAlertDismissal } from "@/lib/items-maintenance-alert";
import { disconnectEcho } from "@/lib/realtime/echo";
import { authService } from "../services/auth.service";

export function useAuth() {
  const queryClient = useQueryClient();
  const navigate = useNavigate();

  const {
    data: user,
    isLoading,
    isError,
  } = useQuery({
    queryKey: ["auth-user"],
    queryFn: () => queryClient.getQueryData(["auth-user"]),
    enabled: false,
    initialData: () => queryClient.getQueryData(["auth-user"]),
  });

  const logoutMutation = useMutation({
    mutationFn: authService.logout,
    onMutate: async () => {
      const currentUser = queryClient.getQueryData(["auth-user"]);
      clearItemsMaintenanceAlertDismissal(currentUser);
      disconnectEcho();
      await queryClient.cancelQueries({ queryKey: ["auth-user"] });
      queryClient.removeQueries({ queryKey: ["auth-user"] });
    },
    onSettled: () => {
      queryClient.clear();
      navigate("/login", { replace: true });
    },
  });

  const logout = () => {
    logoutMutation.mutate();
  };

  return {
    user,
    isLoading,
    isError,
    logout,
    isLoggingOut: logoutMutation.isPending,
  };
}
