export const PROJECT_APPROVAL_LABELS = {
  PD: "DESARROLLO",
  RV: "FINALIZADO",
  AP: "ACTUALIZADO",
};

export function getProjectApprovalLabel(status) {
  return PROJECT_APPROVAL_LABELS[status] || status || "-";
}
