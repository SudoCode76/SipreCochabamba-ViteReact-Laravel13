import { buildPdfViewerUrl, openUrlInNewTab } from "@/lib/utils/pdf";
import { projectService } from "@/modules/projects/services/project.service";

export function openNotificationAction(notification, navigate) {
  if (notification.action_kind === "profile") {
    navigate("/perfil");
    return;
  }

  if (notification.action_kind === "project" && notification.project_id) {
    navigate(`/Proyecto/${notification.project_id}/items`);
    return;
  }

  if (notification.action_kind === "signed_report"
    && notification.project_id
    && notification.report_key) {
    const parameters = notification.parameters || {};
    const url = projectService.latestSignedReportUrl(
      notification.project_id,
      notification.report_key,
      parameters,
    );

    openUrlInNewTab(buildPdfViewerUrl(url, {
      title: "PDF pendiente de firma",
      signature: {
        projectId: notification.project_id,
        reportKey: notification.report_key,
        parameters,
      },
    }));
  }
}

export function notificationActionLabel(notification) {
  if (notification.action_kind === "profile") return "Cargar firma";
  if (notification.action_kind === "project") return "Revisar proyecto";
  if (notification.action_kind === "signed_report") return "Abrir y firmar PDF";
  return "";
}
