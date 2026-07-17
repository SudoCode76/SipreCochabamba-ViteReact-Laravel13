import { useEffect, useRef } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Bell, CheckCheck, FileSignature, Loader2, PackageCheck } from "lucide-react";
import { Link, useNavigate } from "react-router-dom";

import { Button } from "@/components/ui/button";
import { useToast } from "@/components/ui/toast";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { getEcho } from "@/lib/realtime/echo";
import { openNotificationAction } from "@/modules/notifications/lib/notification-action";
import {
  markAllNotificationsReadInCache,
  markNotificationReadInCache,
  notificationsService,
} from "@/modules/notifications/services/notifications.service";

const notificationQueryKey = ["notifications", { status: "all", page: 1, perPage: 8 }];

function relativeTime(value) {
  if (!value) return "";

  const seconds = Math.round((new Date(value).getTime() - Date.now()) / 1000);
  const formatter = new Intl.RelativeTimeFormat("es", { numeric: "auto" });

  if (Math.abs(seconds) < 60) return formatter.format(seconds, "second");
  const minutes = Math.round(seconds / 60);
  if (Math.abs(minutes) < 60) return formatter.format(minutes, "minute");
  const hours = Math.round(minutes / 60);
  if (Math.abs(hours) < 24) return formatter.format(hours, "hour");
  return formatter.format(Math.round(hours / 24), "day");
}

export function NotificationBell({
  user,
  canViewItemAlerts,
  outdatedItemsCount,
  itemMaintenanceFetching,
}) {
  const navigate = useNavigate();
  const toast = useToast();
  const queryClient = useQueryClient();
  const knownIds = useRef(new Set());
  const latestCreatedAt = useRef(0);
  const initialized = useRef(false);
  const notificationsQuery = useQuery({
    queryKey: notificationQueryKey,
    queryFn: () => notificationsService.list({ perPage: 8 }),
    enabled: Boolean(user?.id),
    staleTime: 30_000,
    refetchOnWindowFocus: true,
  });
  const items = notificationsQuery.data?.data?.items ?? [];
  const unreadCount = notificationsQuery.data?.data?.unread_count ?? 0;
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

  useEffect(() => {
    knownIds.current = new Set();
    latestCreatedAt.current = 0;
    initialized.current = false;
  }, [user?.id]);

  useEffect(() => {
    if (!notificationsQuery.data || initialized.current) return;

    const currentItems = notificationsQuery.data.data?.items ?? [];
    currentItems.forEach((notification) => knownIds.current.add(notification.id));
    latestCreatedAt.current = Math.max(0, ...currentItems.map((notification) => Date.parse(notification.created_at) || 0));
    initialized.current = true;
  }, [notificationsQuery.data]);

  useEffect(() => {
    if (!user?.id) return undefined;

    const echo = getEcho();
    if (!echo) return undefined;

    const refresh = async (showToast) => {
      try {
        const response = await notificationsService.list({ perPage: 8 });
        const nextItems = response.data?.items ?? [];
        const previousLatest = latestCreatedAt.current;
        const incoming = initialized.current && showToast
          ? nextItems.find((notification) => !notification.read_at
            && !knownIds.current.has(notification.id)
            && (Date.parse(notification.created_at) || 0) >= previousLatest)
          : null;

        nextItems.forEach((notification) => knownIds.current.add(notification.id));
        latestCreatedAt.current = Math.max(previousLatest, ...nextItems.map((notification) => Date.parse(notification.created_at) || 0));
        initialized.current = true;
        queryClient.setQueryData(notificationQueryKey, response);
        queryClient.invalidateQueries({ queryKey: ["notifications"] });

        if (incoming) {
          toast.info(`${incoming.title}: ${incoming.message}`, {
            duration: 7000,
            onClick: () => {
              markNotificationReadInCache(queryClient, incoming.id);
              notificationsService.markRead(incoming.id)
                .finally(() => queryClient.invalidateQueries({ queryKey: ["notifications"] }));
              openNotificationAction(incoming, navigate);
            },
          });
        }
      } catch {
        queryClient.invalidateQueries({ queryKey: ["notifications"] });
      }
    };
    const onChanged = () => void refresh(true);
    const onFocus = () => void refresh(false);
    const channelName = `users.${user.id}`;
    let channel = null;
    let disposed = false;

    const unsubscribe = () => {
      if (!channel) return;

      channel.stopListening(".notifications.changed", onChanged);
      echo.leave(channelName);
      channel = null;
    };
    const subscribe = () => {
      if (disposed || channel || echo.connectionStatus() !== "connected") return;

      channel = echo.private(channelName).listen(".notifications.changed", onChanged);
      void refresh(false);
    };
    const onConnectionChange = (status) => {
      if (status === "connected") {
        subscribe();
        return;
      }

      if (status === "disconnected" || status === "failed") {
        unsubscribe();
      }
    };
    const stopConnectionListener = echo.connector.onConnectionChange(onConnectionChange);

    onConnectionChange(echo.connectionStatus());
    window.addEventListener("focus", onFocus);

    return () => {
      disposed = true;
      stopConnectionListener();
      window.removeEventListener("focus", onFocus);
      unsubscribe();
    };
  }, [navigate, queryClient, toast, user?.id]);

  const openNotification = (notification) => {
    if (!notification.read_at) {
      markReadMutation.mutate(notification.id);
    }
    openNotificationAction(notification, navigate);
  };

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button
          variant="ghost"
          size="icon"
          className="relative size-10 rounded-full border border-border/70 bg-background/80 hover:bg-muted"
          title="Notificaciones"
        >
          {notificationsQuery.isFetching || itemMaintenanceFetching
            ? <Loader2 className="size-5 animate-spin" />
            : <Bell className="size-5" />}
          {unreadCount > 0 ? (
            <span className="absolute -right-1 -top-1 flex min-h-5 min-w-5 items-center justify-center rounded-full bg-rose-600 px-1 text-[10px] font-bold text-white">
              {unreadCount > 99 ? "99+" : unreadCount}
            </span>
          ) : null}
        </Button>
      </DropdownMenuTrigger>

      <DropdownMenuContent align="end" className="w-[calc(100vw-2rem)] max-w-96 rounded-2xl border-border/70 bg-background/95 p-2 shadow-xl backdrop-blur-xl">
        <DropdownMenuLabel className="flex items-center justify-between gap-3 px-3 py-2">
          <div>
            <p className="text-sm font-semibold text-foreground">Notificaciones</p>
            <p className="mt-1 text-xs font-normal text-muted-foreground">{unreadCount} sin ver</p>
          </div>
          {unreadCount > 0 ? (
            <Button
              type="button"
              variant="ghost"
              size="sm"
              className="rounded-full text-xs"
              disabled={markAllMutation.isPending}
              onClick={() => markAllMutation.mutate()}
            >
              <CheckCheck className="size-4" />
              Marcar todas
            </Button>
          ) : null}
        </DropdownMenuLabel>
        <DropdownMenuSeparator className="bg-border/70" />

        <div className="max-h-80 overflow-y-auto">
          {items.length === 0 ? (
            <div className="px-3 py-6 text-center text-sm text-muted-foreground">No tienes notificaciones de firma.</div>
          ) : items.map((notification) => (
            <DropdownMenuItem
              key={notification.id}
              className="mb-1 cursor-pointer items-start gap-3 rounded-xl px-3 py-3"
              onSelect={() => openNotification(notification)}
            >
              <span className={`mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full ${notification.read_at ? "bg-muted text-muted-foreground" : "bg-sky-100 text-sky-700"}`}>
                <FileSignature className="size-4" />
              </span>
              <span className="min-w-0 flex-1">
                <span className="block text-sm font-semibold text-foreground">{notification.title}</span>
                <span className="mt-1 block line-clamp-2 text-xs leading-5 text-muted-foreground">{notification.message}</span>
                <span className="mt-1 block text-[11px] text-muted-foreground">{relativeTime(notification.created_at)}</span>
              </span>
              {!notification.read_at ? <span className="mt-2 size-2 shrink-0 rounded-full bg-sky-600" /> : null}
            </DropdownMenuItem>
          ))}
        </div>

        <DropdownMenuItem asChild className="rounded-xl px-3 py-2">
          <Link to="/notificaciones" className="justify-center font-medium">Ver todas las notificaciones</Link>
        </DropdownMenuItem>

        {canViewItemAlerts ? (
          <>
            <DropdownMenuSeparator className="bg-border/70" />
            <DropdownMenuLabel className="px-3 py-2">
              <p className="flex items-center gap-2 text-sm font-semibold text-foreground">
                <PackageCheck className="size-4" /> Mantenimiento de ítems
              </p>
              <p className="mt-1 text-xs font-normal text-muted-foreground">
                {outdatedItemsCount > 0
                  ? `${outdatedItemsCount} ítem${outdatedItemsCount === 1 ? "" : "s"} requiere${outdatedItemsCount === 1 ? "" : "n"} revisión.`
                  : "No hay ítems pendientes de revisión."}
              </p>
            </DropdownMenuLabel>
            {outdatedItemsCount > 0 ? (
              <DropdownMenuItem asChild className="rounded-xl px-3 py-2">
                <Link to="/items" state={{ freshness: "outdated" }}>Ver ítems pendientes</Link>
              </DropdownMenuItem>
            ) : null}
          </>
        ) : null}
      </DropdownMenuContent>
    </DropdownMenu>
  );
}
