import apiClient from "@/lib/api/client";

export const notificationsService = {
  list: async ({ status = "all", page = 1, perPage = 10 } = {}) => {
    const response = await apiClient.get("/v1/notifications", {
      params: { status, page, per_page: perPage },
    });
    return response.data;
  },

  markRead: async (notificationId) => {
    const response = await apiClient.patch(`/v1/notifications/${notificationId}/read`);
    return response.data;
  },

  markAllRead: async () => {
    const response = await apiClient.patch("/v1/notifications/read-all");
    return response.data;
  },
};

export function markNotificationReadInCache(queryClient, notificationId) {
  const readAt = new Date().toISOString();

  queryClient.getQueriesData({ queryKey: ["notifications"] }).forEach(([queryKey, current]) => {
    const items = current?.data?.items ?? [];
    const notification = items.find((item) => item.id === notificationId);

    if (!notification || notification.read_at) return;

    const unreadOnly = queryKey[1]?.status === "unread";
    queryClient.setQueryData(queryKey, {
      ...current,
      data: {
        ...current.data,
        items: unreadOnly
          ? items.filter((item) => item.id !== notificationId)
          : items.map((item) => item.id === notificationId ? { ...item, read_at: readAt } : item),
        unread_count: Math.max(0, (current.data.unread_count ?? 0) - 1),
        meta: unreadOnly && current.data.meta ? {
          ...current.data.meta,
          total: Math.max(0, current.data.meta.total - 1),
        } : current.data.meta,
      },
    });
  });
}

export function markAllNotificationsReadInCache(queryClient) {
  const readAt = new Date().toISOString();

  queryClient.getQueriesData({ queryKey: ["notifications"] }).forEach(([queryKey, current]) => {
    if (!current?.data) return;

    const unreadOnly = queryKey[1]?.status === "unread";
    queryClient.setQueryData(queryKey, {
      ...current,
      data: {
        ...current.data,
        items: unreadOnly
          ? []
          : (current.data.items ?? []).map((item) => ({ ...item, read_at: item.read_at || readAt })),
        unread_count: 0,
        meta: unreadOnly && current.data.meta ? {
          ...current.data.meta,
          current_page: 1,
          last_page: 1,
          total: 0,
        } : current.data.meta,
      },
    });
  });
}
