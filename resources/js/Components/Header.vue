<script setup>
import { computed, ref, watch } from "vue";
import { usePage } from "@inertiajs/vue3";
import NotificationDropdown from "@/Components/NotificationDropdown.vue";
import { useRoleFormatter } from "@/Composables/useRoleFormatter";

const props = defineProps({
  showSearch: { type: Boolean, default: false },
  searchValue: { type: String, default: "" },
  sidebarOpen: { type: Boolean, default: false },
});

const emit = defineEmits(["search", "toggle-sidebar"]);

const page = usePage();
const me = computed(() => page.props.auth?.user);
const { formatRole } = useRoleFormatter();

const q = ref(props.searchValue);

watch(
  () => props.searchValue,
  (v) => (q.value = v ?? "")
);

const submit = () => emit("search", q.value);

/* ===============================
   Display Name (remove middle)
================================ */
const displayName = computed(() => {
  const full = me.value?.name ?? "";

  const parts = full.trim().split(/\s+/);

  if (parts.length === 0) return "Admin";
  if (parts.length === 1) return parts[0];

  return `${parts[0]} ${parts[parts.length - 1]}`;
});
</script>

<template>
  <header class="w-full bg-[#f4f6f8]">
    <div class="px-4 pt-4 pb-2 lg:px-10 lg:pt-6">
      <div class="flex justify-between items-start gap-6">

        <!-- LEFT SIDE -->
        <div class="min-w-0 flex-1 flex items-center gap-4">
          <button
            type="button"
            class="lg:hidden flex items-center justify-center w-9 h-9 rounded-lg text-slate-500 hover:bg-slate-100 transition-colors shrink-0"
            aria-label="Toggle sidebar"
            :aria-expanded="sidebarOpen.toString()"
            @click="emit('toggle-sidebar')"
          >
            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="3" y1="6" x2="21" y2="6" />
              <line x1="3" y1="12" x2="21" y2="12" />
              <line x1="3" y1="18" x2="21" y2="18" />
            </svg>
          </button>
          <slot name="left" />
          <div v-if="showSearch" class="flex items-center min-w-0 flex-1">

            <div class="relative w-full max-w-[520px]">
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
        <div class="flex items-center gap-4 shrink-0">

          <!-- BELL ICON -->
          <NotificationDropdown />

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

            <div class="text-right hidden sm:block">
              <div class="text-xs text-slate-500 leading-none">
                {{ formatRole(me?.role) }}
              </div>

              <div class="text-sm font-medium text-slate-800 leading-tight">
                {{ displayName }}
              </div>
            </div>

          </div>

        </div>
      </div>
    </div>
  </header>
</template>