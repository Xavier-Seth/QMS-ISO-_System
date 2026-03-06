<script setup>
import Sidebar from "@/Components/Sidebar.vue";
import Header from "@/Components/Header.vue";

defineProps({
  showSearch: { type: Boolean, default: false },
  searchValue: { type: String, default: "" },

  /* NEW */
  pageTitle: { type: String, default: "" },
  pageSubtitle: { type: String, default: "" },
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
        :pageTitle="pageTitle"
        :pageSubtitle="pageSubtitle"
        @search="(v) => emit('search', v)"
      />

      <!-- PAGE CONTENT -->
      <div class="page-content">
        <slot />
      </div>

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
  margin-left: 280px;
  flex: 1;
  min-width: 0;
  height: 100vh;
  overflow-y: auto;
  overflow-x: hidden;
  display: flex;
  flex-direction: column;
}

/* NEW */
.page-content {
  padding: 0 52px 40px 52px;
}
</style>
