import { RouterProvider } from "react-router-dom";
import { useEffect } from "react";

import { router } from "@/app/router";

function App() {
  useEffect(() => {
    const handleUnauthorized = () => {
      if (window.location.pathname !== "/login") {
        void router.navigate("/login", { replace: true });
      }
    };

    window.addEventListener("auth:unauthorized", handleUnauthorized);

    return () => {
      window.removeEventListener("auth:unauthorized", handleUnauthorized);
    };
  }, []);

  return (
    <RouterProvider router={router} />
  )
}

export default App
