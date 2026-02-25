<script setup>
import Sidebar from '@/Components/Sidebar.vue'
</script>

<template>
  <div class="layout-wrapper">
    <Sidebar />
    <main class="main-content">
      <slot />
    </main>
  </div>
</template>

<style scoped>
.layout-wrapper {
  display: flex;
  min-height: 100vh;
  background: #f1f5f9;
}

.main-content {
  margin-left: 280px;   /* offset for fixed sidebar */
  flex: 1;
  min-width: 0;
  height: 100vh;
  overflow-y: auto;     /* main content area scrolls */
  overflow-x: hidden;
  display: flex;
  flex-direction: column;
}
</style>