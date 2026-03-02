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

const q = ref(props.searchValue);
watch(
  () => props.searchValue,
  (v) => (q.value = v ?? "")
);

const submit = () => emit("search", q.value);

// demo notif dot
const hasNotif = true;
</script>

<template>
  <header class="w-full bg-[#f4f6f8]">
    <div class="px-10 py-6">
      <div class="flex justify-between items-start gap-6">
        <!-- LEFT -->
        <div class="min-w-0">
          <!-- If showSearch, show fixed search -->
          <div v-if="showSearch" class="w-[420px] shrink-0">
            <div
              class="flex items-center gap-2 bg-white rounded-full px-4 py-2 shadow-sm border border-slate-200"
            >
              <svg
                class="w-4 h-4 text-slate-400"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
              >
                <circle cx="11" cy="11" r="7" />
                <path d="M21 21l-4.3-4.3" />
              </svg>

              <input
                v-model="q"
                @keyup.enter="submit"
                type="text"
                placeholder="Search..."
                class="w-full bg-transparent outline-none text-sm text-slate-700 placeholder:text-slate-400"
              />
            </div>
          </div>

          <!-- If NOT showSearch, render custom left content -->
          <div v-else>
            <slot name="left" />
          </div>
        </div>

        <!-- RIGHT: icons + profile -->
        <div class="flex items-start gap-6 shrink-0">
          <!-- icons -->
          <div class="flex items-center gap-3 pt-1">
            <button
              class="w-10 h-10 rounded-full bg-white border border-slate-200 shadow-sm grid place-items-center"
              type="button"
              title="Messages"
            >
              <svg
                class="w-4 h-4 text-slate-500"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
              >
                <path d="M4 4h16v16H4z" />
                <path d="M22 6l-10 7L2 6" />
              </svg>
            </button>

            <button
              class="w-10 h-10 rounded-full bg-white border border-slate-200 shadow-sm grid place-items-center relative"
              type="button"
              title="Notifications"
            >
              <span
                v-if="hasNotif"
                class="absolute top-2 right-2 w-2 h-2 rounded-full bg-red-500"
              ></span>

              <svg
                class="w-4 h-4 text-slate-500"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
              >
                <path
                  d="M18 8a6 6 0 10-12 0c0 7-3 7-3 7h18s-3 0-3-7"
                />
                <path d="M13.73 21a2 2 0 01-3.46 0" />
              </svg>
            </button>
          </div>

          <!-- profile (avatar above, name below) -->
          <div class="flex flex-col items-center">
            <div
              class="w-12 h-12 rounded-full bg-slate-200 grid place-items-center overflow-hidden"
              title="Logged in user"
            >
              <span class="text-slate-600 text-sm font-semibold">
                {{ (me?.name || "U").slice(0, 1).toUpperCase() }}
              </span>
            </div>

            <div class="mt-2 text-[11px] text-slate-700">
              {{ me?.name ?? "User" }}
            </div>
          </div>
        </div>
      </div>
    </div>
  </header>
</template>