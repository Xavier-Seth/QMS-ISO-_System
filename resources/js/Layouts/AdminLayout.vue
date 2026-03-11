<script setup>
import Sidebar from "@/Components/Sidebar.vue";

import GlobalLoadingOverlay from "@/Components/Feedback/GlobalLoadingOverlay.vue";
import ToastContainer from "@/Components/Feedback/ToastContainer.vue";
import ConfirmModal from "@/Components/Feedback/ConfirmModal.vue";
</script>

<template>
  <div class="layout-wrapper">
    <Sidebar />

    <main class="main-content">
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
  position: relative;
  margin-left: 280px;
  flex: 1;
  min-width: 0;
  height: 100vh;
  overflow-y: scroll;
  overflow-x: hidden;
  display: flex;
  flex-direction: column;
}
</style>