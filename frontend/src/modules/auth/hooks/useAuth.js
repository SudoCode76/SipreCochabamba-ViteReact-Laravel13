import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useNavigate } from "react-router-dom";
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
