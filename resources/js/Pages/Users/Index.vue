<script setup>
import AdminLayout from "@/Layouts/AdminLayoutWithHeader.vue";
import { router } from "@inertiajs/vue3";
import { ref } from "vue";

const props = defineProps({
  users: Object,
  filters: Object,
});

const q = ref(props.filters?.q ?? "");

// ✅ only one open at a time
const expandedId = ref(null);
const toggleExpand = (id) => {
  expandedId.value = expandedId.value === id ? null : id;
};
const isExpanded = (id) => expandedId.value === id;

// search
const runSearch = () => {
  router.get("/users", { q: q.value }, { preserveState: true, replace: true, preserveScroll: true });
};
const onHeaderSearch = (value) => {
  q.value = value ?? "";
  runSearch();
};

// pagination
const goTo = (url) => {
  if (!url) return;
  router.get(url, {}, { preserveState: true, preserveScroll: true });
};
</script>

<template>
  <AdminLayout :showSearch="true" :searchValue="q" @search="onHeaderSearch">
    <div class="px-10 py-8">
      <!-- Table container -->
      <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <!-- Header -->
        <div class="bg-slate-900 text-white px-4 py-3">
          <div
            class="grid items-center"
            style="grid-template-columns: 44px 1.2fr 1fr 1fr 1.2fr 110px"
          >
            <div class="flex items-center justify-center">
              <input type="checkbox" class="accent-indigo-400" />
            </div>
            <div class="text-xs font-semibold tracking-wide">Name</div>
            <div class="text-xs font-semibold tracking-wide">Position</div>
            <div class="text-xs font-semibold tracking-wide">Department</div>
            <div class="text-xs font-semibold tracking-wide">Email</div>
            <div class="text-xs font-semibold tracking-wide text-center">
              Actions
            </div>
          </div>
        </div>

        <!-- Body -->
        <div v-if="users.data.length">
          <template v-for="u in users.data" :key="u.id">
            <!-- Main row -->
            <div
              class="px-4 py-2 border-b border-slate-100"
              :class="isExpanded(u.id) ? 'bg-slate-50' : 'bg-white'"
            >
              <div
                class="grid items-center"
                style="grid-template-columns: 44px 1.2fr 1fr 1fr 1.2fr 110px"
              >
                <div class="flex items-center justify-center gap-2">
                  <input type="checkbox" class="accent-indigo-500" />
                  <button
                    class="text-slate-500 hover:text-slate-800"
                    @click="toggleExpand(u.id)"
                    type="button"
                    title="Expand"
                  >
                    <svg
                      class="w-4 h-4 transition-transform"
                      :class="isExpanded(u.id) ? 'rotate-180' : ''"
                      viewBox="0 0 24 24"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="2"
                    >
                      <polyline points="6 9 12 15 18 9" />
                    </svg>
                  </button>
                </div>

                <div class="text-sm text-slate-800 font-medium">
                  {{ u.name }}
                </div>
                <div class="text-sm text-slate-600">{{ u.position ?? "-" }}</div>
                <div class="text-sm text-slate-600">
                  {{ u.department ?? "-" }}
                </div>
                <div class="text-sm text-slate-600">{{ u.email }}</div>

                <div class="flex items-center justify-center gap-4 text-slate-500">
                  <button class="hover:text-slate-800" type="button" title="Edit">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <path d="M12 20h9" />
                      <path d="M16.5 3.5a2.1 2.1 0 013 3L7 19l-4 1 1-4 12.5-12.5z" />
                    </svg>
                  </button>

                  <button class="hover:text-red-600" type="button" title="Delete">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <path d="M3 6h18" />
                      <path d="M8 6V4h8v2" />
                      <path d="M19 6l-1 14H6L5 6" />
                    </svg>
                  </button>
                </div>
              </div>

              <!-- Expanded details -->
              <div v-if="isExpanded(u.id)" class="mt-3">
                <div class="border border-indigo-300 rounded-lg bg-white px-6 py-4">
                  <div class="grid gap-10 md:grid-cols-2">
                    <div>
                      <div class="text-xs font-semibold text-slate-700 mb-2">
                        Office Location
                      </div>
                      <div class="flex items-start gap-2 text-sm text-slate-600">
                        <svg class="w-4 h-4 mt-[2px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                          <path d="M12 21s-6-5.3-6-10a6 6 0 1112 0c0 4.7-6 10-6 10z" />
                          <circle cx="12" cy="11" r="2" />
                        </svg>
                        <span>{{ u.office_location ?? "-" }}</span>
                      </div>
                    </div>

                    <div>
                      <div class="text-xs font-semibold text-slate-700 mb-2">
                        Account
                      </div>
                      <div class="text-sm text-slate-600">
                        <div><span class="text-slate-500">Username:</span> {{ u.username }}</div>
                        <div class="mt-1"><span class="text-slate-500">Role:</span> {{ u.role }}</div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </template>
        </div>

        <!-- Empty state -->
        <div v-else class="px-6 py-10 text-center text-sm text-slate-500">
          No users found.
        </div>

        <!-- Pagination -->
        <div class="px-6 py-4 flex items-center justify-between">
          <div class="text-xs text-slate-500">
            Showing {{ users.from ?? 0 }}–{{ users.to ?? 0 }} of {{ users.total ?? 0 }}
          </div>

          <div class="flex gap-2">
            <button
              class="px-3 py-1.5 text-sm rounded-lg border border-slate-200 bg-white text-slate-700 disabled:opacity-50"
              :disabled="!users.prev_page_url"
              @click="goTo(users.prev_page_url)"
              type="button"
            >
              Prev
            </button>

            <button
              class="px-3 py-1.5 text-sm rounded-lg border border-slate-200 bg-white text-slate-700 disabled:opacity-50"
              :disabled="!users.next_page_url"
              @click="goTo(users.next_page_url)"
              type="button"
            >
              Next
            </button>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>