import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Bell, CheckCheck, ChevronLeft, ChevronRight, FileSignature, Loader2 } from "lucide-react";
import { useNavigate } from "react-router-dom";

import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { formatDateTime } from "@/lib/utils";
import { notificationActionLabel, openNotificationAction } from "@/modules/notifications/lib/notification-action";
import {
  markAllNotificationsReadInCache,
  markNotificationReadInCache,
  notificationsService,
} from "@/modules/notifications/services/notifications.service";

export default function NotificationsPage() {
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const [status, setStatus] = useState("all");
  const [page, setPage] = useState(1);
  const query = useQuery({
    queryKey: ["notifications", { status, page, perPage: 10 }],
    queryFn: () => notificationsService.list({ status, page, perPage: 10 }),
    refetchOnWindowFocus: true,
  });
  const items = query.data?.data?.items ?? [];
  const unreadCount = query.data?.data?.unread_count ?? 0;
  const meta = query.data?.data?.meta ?? { current_page: 1, last_page: 1, total: 0 };
  const markReadMutation = useMutation({
    mutationFn: notificationsService.markRead,
    onMutate: (notificationId) => markNotificationReadInCache(queryClient, notificationId),
    onSettled: () => queryClient.invalidateQueries({ queryKey: ["notifications"] }),
  });
  const markAllMutation = useMutation({
    mutationFn: notificationsService.markAllRead,
    onMutate: () => markAllNotificationsReadInCache(queryClient),
    onSettled: () => queryClient.invalidateQueries({ queryKey: ["notifications"] }),
  });

  const openNotification = (notification) => {
    if (!notification.read_at) {
      markReadMutation.mutate(notification.id);
    }
    openNotificationAction(notification, navigate);
  };

  return (
    <Card className="border border-border/70 bg-white/86 shadow-[0_24px_90px_rgba(15,23,42,0.08)]">
      <CardHeader className="border-b border-border/70 bg-muted/25">
        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div className="flex items-center gap-3">
            <span className="flex size-11 items-center justify-center rounded-2xl bg-foreground text-background">
              <Bell className="size-5" />
            </span>
            <div>
              <CardTitle className="text-2xl tracking-[-0.04em]">Notificaciones</CardTitle>
              <p className="mt-1 text-sm text-muted-foreground">{unreadCount} notificación{unreadCount === 1 ? "" : "es"} sin ver</p>
            </div>
          </div>
          {unreadCount > 0 ? (
            <Button type="button" variant="outline" className="rounded-full" disabled={markAllMutation.isPending} onClick={() => markAllMutation.mutate()}>
              {markAllMutation.isPending ? <Loader2 className="size-4 animate-spin" /> : <CheckCheck className="size-4" />}
              Marcar todas como vistas
            </Button>
          ) : null}
        </div>
      </CardHeader>

      <CardContent className="space-y-5 p-5 sm:p-6">
        <div className="flex gap-2">
          {[{ value: "all", label: "Todas" }, { value: "unread", label: "No vistas" }].map((option) => (
            <Button
              key={option.value}
              type="button"
              size="sm"
              variant={status === option.value ? "default" : "outline"}
              className="rounded-full"
              onClick={() => {
                setStatus(option.value);
                setPage(1);
              }}
            >
              {option.label}
            </Button>
          ))}
        </div>

        {query.isLoading ? (
          <div className="flex items-center justify-center rounded-2xl border border-dashed border-border p-10 text-sm text-muted-foreground">
            <Loader2 className="mr-2 size-4 animate-spin" /> Cargando notificaciones...
          </div>
        ) : null}

        {query.isError ? (
          <div className="rounded-2xl border border-destructive/30 bg-destructive/5 p-4 text-sm text-destructive">
            No se pudieron cargar las notificaciones.
          </div>
        ) : null}

        {!query.isLoading && !query.isError && items.length === 0 ? (
          <div className="rounded-2xl border border-dashed border-border p-10 text-center text-sm text-muted-foreground">
            No hay notificaciones para este filtro.
          </div>
        ) : null}

        <div className="space-y-3">
          {items.map((notification) => {
            const actionLabel = notificationActionLabel(notification);

            return (
              <article key={notification.id} className={`rounded-2xl border p-4 ${notification.read_at ? "border-border/70 bg-background" : "border-sky-200 bg-sky-50/60"}`}>
                <div className="flex items-start gap-3">
                  <span className={`flex size-10 shrink-0 items-center justify-center rounded-full ${notification.read_at ? "bg-muted text-muted-foreground" : "bg-sky-100 text-sky-700"}`}>
                    <FileSignature className="size-5" />
                  </span>
                  <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-start justify-between gap-2">
                      <div>
                        <h2 className="font-semibold text-foreground">{notification.title}</h2>
                        <p className="mt-1 text-sm leading-6 text-muted-foreground">{notification.message}</p>
                      </div>
                      {!notification.read_at ? <Badge className="rounded-full bg-sky-600 text-white">NUEVA</Badge> : null}
                    </div>
                    <div className="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
                      {notification.project_name ? <span>{notification.project_name} · versión {notification.version_number}</span> : null}
                      {notification.report_name ? <span>{notification.report_name}</span> : null}
                      <span>{formatDateTime(notification.created_at)}</span>
                    </div>
                    <div className="mt-4 flex flex-wrap gap-2">
                      {actionLabel ? (
                        <Button type="button" size="sm" className="rounded-full" onClick={() => openNotification(notification)}>
                          {actionLabel}
                        </Button>
                      ) : null}
                      {!notification.read_at ? (
                        <Button type="button" size="sm" variant="outline" className="rounded-full" onClick={() => markReadMutation.mutate(notification.id)}>
                          Marcar como vista
                        </Button>
                      ) : null}
                    </div>
                  </div>
                </div>
              </article>
            );
          })}
        </div>

        {meta.last_page > 1 ? (
          <div className="flex items-center justify-end gap-2">
            <Button type="button" variant="outline" size="sm" className="rounded-full" disabled={meta.current_page <= 1 || query.isFetching} onClick={() => setPage((value) => Math.max(1, value - 1))}>
              <ChevronLeft className="size-4" /> Anterior
            </Button>
            <span className="text-sm text-muted-foreground">Página {meta.current_page} de {meta.last_page}</span>
            <Button type="button" variant="outline" size="sm" className="rounded-full" disabled={meta.current_page >= meta.last_page || query.isFetching} onClick={() => setPage((value) => Math.min(meta.last_page, value + 1))}>
              Siguiente <ChevronRight className="size-4" />
            </Button>
          </div>
        ) : null}
      </CardContent>
    </Card>
  );
}
