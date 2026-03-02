<script setup>
import { computed, ref, watch } from "vue";
import { usePage } from "@inertiajs/vue3";

const props = defineProps({
  showSearch: { type: Boolean, default: false },
  searchValue: { type: String, default: "" },
});

const emit = defineEmits(["search"]);

const page = usePage();
const me = computed(() => page.props.auth?.user);

// local input state (so it feels instant)
const q = ref(props.searchValue);

watch(
  () => props.searchValue,
  (v) => (q.value = v ?? "")
);

const submit = () => emit("search", q.value);

// optional notif red dot (wire later)
const hasNotif = true;
</script>

<template>
  <header class="sticky top-0 z-40 bg-slate-50/90 backdrop-blur border-b border-slate-200">
    <div class="h-16 px-10 flex items-center justify-between">
      <!-- LEFT: Search (optional) -->
      <div v-if="showSearch" class="w-[420px]">
        <div class="flex items-center gap-2 bg-white rounded-full px-4 py-2 shadow-sm border border-slate-200">
          <svg class="w-4 h-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="7" />
            <path d="M21 21l-4.3-4.3" />
          </svg>

          <input
            v-model="q"
            @keyup.enter="submit"
            type="text"
            placeholder="Search..."
            class="w-full outline-none text-sm text-slate-700 placeholder:text-slate-400"
          />
        </div>
      </div>

      <!-- If no search, keep left side empty to push right content -->
      <div v-else></div>

      <!-- RIGHT: icons + profile -->
      <div class="flex items-center gap-4">
        <!-- Mail -->
        <button
          class="w-9 h-9 rounded-full bg-white border border-slate-200 shadow-sm grid place-items-center"
          type="button"
          title="Messages"
        >
          <svg class="w-4 h-4 text-slate-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M4 4h16v16H4z" />
            <path d="M22 6l-10 7L2 6" />
          </svg>
        </button>

        <!-- Bell -->
        <button
          class="w-9 h-9 rounded-full bg-white border border-slate-200 shadow-sm grid place-items-center relative"
          type="button"
          title="Notifications"
        >
          <span v-if="hasNotif" class="absolute top-2 right-2 w-2 h-2 rounded-full bg-red-500"></span>
          <svg class="w-4 h-4 text-slate-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M18 8a6 6 0 10-12 0c0 7-3 7-3 7h18s-3 0-3-7" />
            <path d="M13.73 21a2 2 0 01-3.46 0" />
          </svg>
        </button>

        <!-- Profile -->
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-full overflow-hidden bg-slate-200 grid place-items-center">
            <span class="text-slate-700 text-sm font-semibold">
              {{ (me?.name || "U").slice(0, 1).toUpperCase() }}
            </span>
          </div>

          <div class="leading-tight text-right">
            <div class="text-xs text-slate-500">{{ me?.role }}</div>
            <div class="text-sm font-semibold text-slate-900">{{ me?.name }}</div>
          </div>
        </div>
      </div>
    </div>
  </header>
</template>