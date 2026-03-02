<script setup>
import Sidebar from "@/Components/Sidebar.vue";
import Header from "@/Components/Header.vue";

const props = defineProps({
  showSearch: { type: Boolean, default: false },
  searchValue: { type: String, default: "" },
});

const emit = defineEmits(["search"]);
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
  </div>
</template>