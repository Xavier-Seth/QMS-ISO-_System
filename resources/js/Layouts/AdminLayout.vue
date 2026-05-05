<script setup>
import { ref } from "vue";
import Sidebar from "@/Components/Sidebar.vue";

import GlobalLoadingOverlay from "@/Components/Feedback/GlobalLoadingOverlay.vue";
import ToastContainer from "@/Components/Feedback/ToastContainer.vue";
import ConfirmModal from "@/Components/Feedback/ConfirmModal.vue";

const sidebarOpen = ref(false);
</script>

<template>
  <div class="layout-wrapper">
    <Sidebar :open="sidebarOpen" @close="sidebarOpen = false" />

    <main class="main-content">
      <button
        type="button"
        class="lg:hidden fixed top-4 left-4 z-[101] flex items-center justify-center w-9 h-9 rounded-lg bg-white shadow text-slate-500 hover:bg-slate-50 transition-colors"
        aria-label="Toggle sidebar"
        :aria-expanded="sidebarOpen.toString()"
        @click="sidebarOpen = !sidebarOpen"
      >
        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="3" y1="6" x2="21" y2="6" />
          <line x1="3" y1="12" x2="21" y2="12" />
          <line x1="3" y1="18" x2="21" y2="18" />
        </svg>
      </button>
      <slot />
    </main>

    <ToastContainer />
    <ConfirmModal />
    <GlobalLoadingOverlay />
  </div>
</template>

<style scoped>
.layout-wrapper {
  display: flex;
  min-height: 100vh;
  background: #f1f5f9;
}

.main-content {
  margin-left: 0;
  flex: 1;
  min-width: 0;
  height: 100vh;
  overflow-y: scroll;
  overflow-x: hidden;
  display: flex;
  flex-direction: column;
}

@media (min-width: 1024px) {
  .main-content {
    margin-left: 280px;
  }
}
</style>