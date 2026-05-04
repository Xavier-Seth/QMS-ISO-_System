<script setup>
import { ref } from "vue";
import { router } from "@inertiajs/vue3";
import { useNotifications } from "@/Composables/useNotifications.js";

const { notifications, unreadCount, loading, markRead, markAllRead } =
  useNotifications();

const isOpen = ref(false);

function toggle() {
  isOpen.value = !isOpen.value;
}

function close() {
  isOpen.value = false;
}

function handleClick(notification) {
  close();
  markRead(notification.id);
  const url = notification.data?.view_url ?? notification.data?.data?.view_url;
  if (url) {
    router.visit(url);
  }
}

function formatTime(dateStr) {
  if (!dateStr) {
    return "";
  }

  const diff = Math.floor((Date.now() - new Date(dateStr).getTime()) / 1000);

  if (diff < 60) {
    return "just now";
  }

  if (diff < 3600) {
    return `${Math.floor(diff / 60)}m ago`;
  }

  if (diff < 86400) {
    return `${Math.floor(diff / 3600)}h ago`;
  }

  return `${Math.floor(diff / 86400)}d ago`;
}

const typeBadgeClass = {
  ofi: "bg-blue-100 text-blue-700",
  car: "bg-orange-100 text-orange-700",
  dcr: "bg-purple-100 text-purple-700",
};
</script>

<template>
  <div class="relative">
    <!-- Bell button -->
    <button
      class="relative h-10 w-10 rounded-full bg-[#e7e7e7] flex items-center justify-center"
      type="button"
      @click="toggle"
    >
      <span
        v-if="unreadCount > 0"
        class="absolute top-2 right-2 h-2 w-2 rounded-full bg-red-500"
      />

      <svg
        class="w-5 h-5 text-[#1f2937]"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.8"
        stroke-linecap="round"
        stroke-linejoin="round"
      >
        <path d="M18 8a6 6 0 10-12 0c0 7-3 7-3 7h18s-3 0-3-7" />
        <path d="M13.73 21a2 2 0 01-3.46 0" />
      </svg>
    </button>

    <!-- Dropdown -->
    <div
      v-if="isOpen"
      class="absolute right-0 top-12 z-50 w-80 bg-white rounded-xl shadow-lg border border-slate-200 overflow-hidden"
    >
      <!-- Header -->
      <div class="flex items-center justify-between px-4 py-3 border-b border-slate-100">
        <span class="text-sm font-semibold text-slate-800">Notifications</span>

        <button
          v-if="unreadCount > 0"
          class="text-xs text-indigo-600 hover:text-indigo-800 font-medium"
          type="button"
          @click="markAllRead"
        >
          Mark all as read
        </button>
      </div>

      <!-- Loading -->
      <div v-if="loading" class="px-4 py-6 text-center text-sm text-slate-400">
        Loading...
      </div>

      <!-- Empty state -->
      <div
        v-else-if="notifications.length === 0"
        class="px-4 py-6 text-center text-sm text-slate-400"
      >
        No notifications yet.
      </div>

      <!-- List -->
      <ul v-else class="max-h-72 overflow-y-auto divide-y divide-slate-100">
        <li v-for="n in notifications" :key="n.id">
          <button
            type="button"
            class="flex w-full gap-3 px-4 py-3 text-left cursor-pointer hover:bg-slate-50 transition-colors"
            :class="{ 'bg-indigo-50/60': !n.read_at }"
            @click="handleClick(n)"
          >
            <!-- Type badge -->
            <span
              class="mt-0.5 shrink-0 rounded px-1.5 py-0.5 text-[10px] font-bold uppercase leading-none h-fit"
              :class="typeBadgeClass[(n.data?.type ?? n.data?.data?.type)] ?? 'bg-slate-100 text-slate-600'"
            >
              {{ (n.data?.type ?? n.data?.data?.type ?? "—").toUpperCase() }}
            </span>

            <!-- Message + time -->
            <div class="min-w-0">
              <p class="text-xs text-slate-700 leading-snug line-clamp-2">
                {{ n.data?.message ?? n.data?.data?.message ?? "New notification" }}
              </p>
              <p class="text-[10px] text-slate-400 mt-0.5">
                {{ formatTime(n.created_at) }}
              </p>
            </div>

            <!-- Unread dot -->
            <span
              v-if="!n.read_at"
              class="shrink-0 mt-1.5 h-2 w-2 rounded-full bg-indigo-500"
            />
          </button>
        </li>
      </ul>
    </div>

    <!-- Click-outside overlay -->
    <div
      v-if="isOpen"
      class="fixed inset-0 z-40"
      @click="close"
    />
  </div>
</template>
