<script setup>
import Sidebar from "@/Components/Sidebar.vue";
import Header from "@/Components/Header.vue";

defineProps({
  showSearch: { type: Boolean, default: false },
  searchValue: { type: String, default: "" },
});

const emit = defineEmits(["search"]);
</script>

<template>
  <div class="layout-wrapper">
    <Sidebar />

    <main class="main-content">
      <Header
        :showSearch="showSearch"
        :searchValue="searchValue"
        @search="(v) => emit('search', v)"
      />

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
  margin-left: 280px; /* fixed sidebar */
  flex: 1;
  min-width: 0; /* important: prevents flex children stretching weirdly */
  height: 100vh;
  overflow-y: auto;
  overflow-x: hidden;
  display: flex;
  flex-direction: column;
}
</style>