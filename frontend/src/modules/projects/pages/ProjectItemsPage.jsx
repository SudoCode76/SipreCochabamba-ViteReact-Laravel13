import { useEffect, useRef } from "react";
import { useBeforeUnload, useBlocker, useNavigate, useParams } from "react-router-dom";
import { ArrowLeft } from "lucide-react";

import { Button } from "@/components/ui/button";
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import ProjectItemsForm from "../components/ProjectItemsForm";

export default function ProjectItemsPage() {
  const navigate = useNavigate();
  const { projectId } = useParams();
  const itemsFormRef = useRef(null);
  const allowNextNavigationRef = useRef(false);

  const navigateToProjects = () => {
    allowNextNavigationRef.current = true;
    navigate("/Proyecto");
  };

  const handleExit = () => {
    if (itemsFormRef.current?.requestExit) {
      itemsFormRef.current.requestExit();
      return;
    }

    navigateToProjects();
  };

  const blocker = useBlocker(({ currentLocation, nextLocation }) => {
    if (allowNextNavigationRef.current) {
      allowNextNavigationRef.current = false;
      return false;
    }

    return currentLocation.pathname !== nextLocation.pathname
      && Boolean(itemsFormRef.current?.shouldWarnBeforeUnload?.());
  });

  useEffect(() => {
    if (blocker.state !== "blocked") {
      return;
    }

    const resolveBlockedNavigation = async () => {
      const shouldProceed = await itemsFormRef.current?.requestExit?.({
        proceed: blocker.proceed,
      });

      if (!shouldProceed) {
        blocker.reset();
      }
    };

    void resolveBlockedNavigation();
  }, [blocker]);

  useBeforeUnload((event) => {
    if (!itemsFormRef.current?.shouldWarnBeforeUnload?.()) {
      return;
    }

    event.preventDefault();
    event.returnValue = "";
  });

  return (
    <div className="mx-auto w-full max-w-7xl animate-in fade-in duration-500">
        <Card className="border border-border/70 bg-white/92 shadow-[0_24px_90px_rgba(15,23,42,0.08)]">
          <CardHeader className="border-b border-border/70 bg-muted/20 px-4 py-3 sm:px-5">
            <div className="flex items-start justify-between gap-4">
              <div className="flex min-w-0 items-start gap-2">
                <Button variant="ghost" size="sm" onClick={handleExit} className="h-9 shrink-0 rounded-full px-2" aria-label="Volver a proyectos">
                  <ArrowLeft className="size-4" />
                  <span className="hidden sm:inline">Volver</span>
                </Button>
                <div>
                  <CardTitle className="text-xl tracking-[-0.04em]">Agregar Items al Proyecto</CardTitle>
                </div>
              </div>
            </div>
          </CardHeader>

          <CardContent className="p-3 sm:p-4">
            <ProjectItemsForm ref={itemsFormRef} projectId={projectId} onCancel={navigateToProjects} onSuccess={navigateToProjects} />
          </CardContent>
        </Card>
    </div>
  );
}
