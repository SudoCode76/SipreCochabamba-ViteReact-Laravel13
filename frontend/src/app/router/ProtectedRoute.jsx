import { Navigate, Outlet } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";
import { Loader2 } from "lucide-react";
import { authService } from "@/modules/auth/services/auth.service";

const AUTH_QUERY_OPTIONS = {
  staleTime: Number.POSITIVE_INFINITY,
  gcTime: 60 * 60 * 1000,
};

export default function ProtectedRoute() {
  const { isLoading, isError } = useQuery({
    queryKey: ["auth-user"],
    queryFn: async () => {
      const data = await authService.getProfile();
      return data?.data?.user || data?.user || data;
    },
    retry: false,
    refetchOnWindowFocus: false,
    ...AUTH_QUERY_OPTIONS,
  });

  if (isLoading) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-background text-muted-foreground">
        <Loader2 className="size-5 animate-spin" />
      </div>
    );
  }

  if (isError) {
    return <Navigate to="/login" replace />;
  }

  return <Outlet />;
}
