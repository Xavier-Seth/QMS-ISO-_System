<script setup>
import { watch } from "vue";
import { usePage } from "@inertiajs/vue3";

import Sidebar from "@/Components/Sidebar.vue";
import Header from "@/Components/Header.vue";

import GlobalLoadingOverlay from "@/Components/Feedback/GlobalLoadingOverlay.vue";
import ToastContainer from "@/Components/Feedback/ToastContainer.vue";
import ConfirmModal from "@/Components/Feedback/ConfirmModal.vue";

import { useToast } from "@/Composables/useToast";

import GlobalLoadingOverlay from "@/Components/Feedback/GlobalLoadingOverlay.vue";
import ToastContainer from "@/Components/Feedback/ToastContainer.vue";
import ConfirmModal from "@/Components/Feedback/ConfirmModal.vue";

import { useToast } from "@/Composables/useToast";

const props = defineProps({
  showSearch: { type: Boolean, default: false },
  searchValue: { type: String, default: "" },
});

const emit = defineEmits(["search"]);

const page = usePage();
const toast = useToast();

watch(
  () => page.props.flash,
  (flash) => {
    if (!flash) return;

    if (flash.success) {
      toast.success(flash.success);
    }

    if (flash.error) {
      toast.error(flash.error);
    }

    if (flash.info) {
      toast.info(flash.info);
    }
  },
  { deep: true, immediate: true }
);
</script>

<template>
  <div class="min-h-screen bg-slate-100 flex">
    <Sidebar />

    <!-- main area -->
    <main class="ml-[280px] flex-1 min-w-0 min-h-screen flex flex-col">
      <!-- header -->
      <Header
        :showSearch="props.showSearch"
        :searchValue="props.searchValue"
        @search="(v) => emit('search', v)"
      >
        <!-- ✅ forward your dashboard greeting to Header.vue slot="left" -->
        <template #left>
          <slot name="header-left" />
        </template>
      </Header>

      <!-- ✅ page content ALWAYS shows -->
      <div class="flex-1 min-h-0">
        <slot />
      </div>
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
  margin-left: 280px;
  flex: 1;
  min-width: 0;
  height: 100vh;
  overflow-y: auto;
  overflow-x: hidden;
  display: flex;
  flex-direction: column;
}
</style>