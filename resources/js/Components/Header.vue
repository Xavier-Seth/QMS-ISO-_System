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

const hasNotif = true;
</script>

<template>
  <header class="w-full bg-[#f4f6f8]">
    <div class="px-10 pt-6 pb-2">
      <div class="flex justify-between items-start gap-6">
        <!-- LEFT SIDE -->
        <div class="min-w-0">
          <div v-if="showSearch" class="flex items-center">
            <div class="relative w-[520px] max-w-[60vw]">
              <div class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">
                <svg
                  class="w-4 h-4"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                >
                  <circle cx="11" cy="11" r="7" />
                  <path d="M20 20l-3.5-3.5" />
                </svg>
              </div>

              <input
                v-model="q"
                type="text"
                placeholder="Search..."
                class="w-full rounded-full border border-slate-200 bg-white pl-10 pr-4 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-200"
                @keyup.enter="submit"
              />
            </div>
          </div>
        </div>

        <!-- RIGHT SIDE -->
        <div class="flex items-center gap-4">
          <!-- MAIL ICON -->
          <button
            class="relative h-10 w-10 rounded-full bg-[#e7e7e7] flex items-center justify-center"
            type="button"
          >
            <svg
              class="w-5 h-5 text-[#1f2937]"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="1.8"
              stroke-linecap="round"
              stroke-linejoin="round"
            >
              <path d="M4 7h16v10H4z" />
              <path d="M5 8l7 5 7-5" />
            </svg>
          </button>

          <!-- BELL ICON -->
          <button
            class="relative h-10 w-10 rounded-full bg-[#e7e7e7] flex items-center justify-center"
            type="button"
          >
            <span
              v-if="hasNotif"
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

          <!-- USER PROFILE -->
          <div class="flex items-center gap-3">
            <div class="h-10 w-10 rounded-full overflow-hidden bg-slate-200 flex items-center justify-center">
              <img
                v-if="me?.profile_photo"
                :src="me.profile_photo"
                alt="User profile"
                class="h-full w-full object-cover"
              />

              <span
                v-else
                class="text-slate-700 font-semibold"
              >
                {{ (me?.name?.[0] ?? "A").toUpperCase() }}
              </span>
            </div>

            <div class="text-right">
              <div class="text-xs text-slate-500 leading-none">
                Admin User
              </div>

              <div class="text-sm font-medium text-slate-800 leading-tight">
                {{ me?.name ?? "Admin" }}
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </header>
</template>