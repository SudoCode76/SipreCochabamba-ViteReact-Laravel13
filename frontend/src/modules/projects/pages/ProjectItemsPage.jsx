import { useEffect, useRef } from "react";
import { useBeforeUnload, useBlocker, useNavigate, useParams } from "react-router-dom";
import { ArrowLeft, ListPlus } from "lucide-react";

import { Button } from "@/components/ui/button";
import {
  Card,
  CardContent,
  CardDescription,
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
    <div className="flex flex-col gap-6 animate-in fade-in duration-500">
      <div className="flex items-center gap-4">
        <Button variant="ghost" size="sm" onClick={handleExit} className="rounded-full">
          <ArrowLeft className="mr-2 h-4 w-4" />
          Volver
        </Button>
      </div>

      <div className="mx-auto w-full max-w-7xl">
        <Card className="border border-border/70 bg-white/92 shadow-[0_24px_90px_rgba(15,23,42,0.08)]">
          <CardHeader className="border-b border-border/70 bg-muted/20">
            <div className="flex items-start justify-between gap-4">
              <div>
                <CardTitle className="text-2xl tracking-[-0.04em]">Agregar Items al Proyecto</CardTitle>
                <CardDescription>
                  Selecciona, edita y ordena los items del proyecto.
                </CardDescription>
              </div>

              <div className="flex size-11 items-center justify-center rounded-2xl bg-foreground text-background shadow-sm">
                <ListPlus className="size-5" />
              </div>
            </div>
          </CardHeader>

          <CardContent className="p-5 sm:p-6">
            <ProjectItemsForm ref={itemsFormRef} projectId={projectId} onCancel={navigateToProjects} onSuccess={navigateToProjects} />
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
