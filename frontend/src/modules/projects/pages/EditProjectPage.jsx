import { useNavigate, useParams } from "react-router-dom";
import { ArrowLeft, MapPin } from "lucide-react";

import { Button } from "@/components/ui/button";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import ProjectEditForm from "../components/ProjectEditForm";

export default function EditProjectPage() {
  const navigate = useNavigate();
  const { projectId } = useParams();

  return (
    <div className="flex flex-col gap-6 animate-in fade-in duration-500">
      <div className="flex items-center gap-4">
        <Button variant="ghost" size="sm" onClick={() => navigate("/Proyecto")} className="rounded-full">
          <ArrowLeft className="mr-2 h-4 w-4" />
          Volver
        </Button>
      </div>

      <div className="mx-auto w-full max-w-5xl">
        <Card className="border border-border/70 bg-white/92 shadow-[0_24px_90px_rgba(15,23,42,0.08)]">
          <CardHeader className="border-b border-border/70 bg-muted/20">
            <div className="flex items-start justify-between gap-4">
              <div>
                <CardTitle className="text-2xl tracking-[-0.04em]">Editar Proyecto</CardTitle>
                <CardDescription>
                  Actualiza la información base del proyecto seleccionado.
                </CardDescription>
              </div>

              <div className="flex size-11 items-center justify-center rounded-2xl bg-foreground text-background shadow-sm">
                <MapPin className="size-5" />
              </div>
            </div>
          </CardHeader>

          <CardContent className="p-5 sm:p-6">
            <ProjectEditForm projectId={projectId} onCancel={() => navigate("/Proyecto")} onSuccess={() => navigate("/Proyecto")} />
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
