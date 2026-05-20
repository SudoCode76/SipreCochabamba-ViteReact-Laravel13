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
    queryFn: async () => {
      const data = await authService.getProfile();
      return data?.data?.user || data?.user || data;
    },
    retry: false, // Don't retry if fetching the user fails (e.g. 401)
    refetchOnWindowFocus: false,
  });

  const logoutMutation = useMutation({
    mutationFn: authService.logout,
    onSettled: () => {
      // Regardless of success or failure, we clear the local state
      localStorage.removeItem("token");
      queryClient.clear();
      navigate("/login");
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
