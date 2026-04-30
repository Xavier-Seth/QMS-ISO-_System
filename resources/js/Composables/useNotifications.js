import { onMounted, onUnmounted, ref } from "vue";
import { usePage } from "@inertiajs/vue3";
import axios from "axios";

export function useNotifications() {
  const page = usePage();
  const userId = page.props.auth?.user?.id;

  const notifications = ref([]);
  const unreadCount = ref(page.props.notifications_unread_count ?? 0);
  const loading = ref(false);

  async function fetchNotifications() {
    if (!userId) {
      return;
    }

    loading.value = true;

    try {
      const response = await axios.get("/notifications");
      notifications.value = response.data.data ?? [];
    } catch {
      // silently ignore
    } finally {
      loading.value = false;
    }
  }

  async function markRead(id) {
    try {
      await axios.post(`/notifications/${id}/read`);

      const item = notifications.value.find((n) => n.id === id);

      if (item && !item.read_at) {
        item.read_at = new Date().toISOString();
        unreadCount.value = Math.max(0, unreadCount.value - 1);
      }
    } catch {
      // silently ignore
    }
  }

  async function markAllRead() {
    try {
      await axios.post("/notifications/read-all");

      notifications.value.forEach((n) => {
        if (!n.read_at) {
          n.read_at = new Date().toISOString();
        }
      });

      unreadCount.value = 0;
    } catch {
      // silently ignore
    }
  }

  function handleIncomingNotification(notification) {
    notifications.value.unshift({
      id: notification.id,
      type: notification.type,
      data: notification,
      read_at: null,
      created_at: new Date().toISOString(),
    });

    unreadCount.value += 1;
  }

  onMounted(() => {
    if (!userId) {
      return;
    }

    fetchNotifications();

    window.Echo?.private(`user.${userId}`).notification(
      handleIncomingNotification
    );
  });

  onUnmounted(() => {
    if (!userId) {
      return;
    }

    window.Echo?.leave(`user.${userId}`);
  });

  return {
    notifications,
    unreadCount,
    loading,
    markRead,
    markAllRead,
    fetchNotifications,
  };
}
