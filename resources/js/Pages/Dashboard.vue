<script setup>
import AdminLayout from "@/Layouts/AdminLayoutWithHeader.vue";
import { computed } from "vue";

const props = defineProps({
  stats: { type: Array, default: () => [] },
  recentActivity: { type: Array, default: () => [] },
  pendingDocs: { type: Array, default: () => [] },
  authUser: {
    type: Object,
    default: () => ({ name: "User", role: "User" }),
  },
});

const greeting = computed(() => {
  const h = new Date().getHours();
  if (h < 12) return "Good morning";
  if (h < 18) return "Good afternoon";
  return "Good evening";
});

const trendTextClass = (trend) => {
  if (trend === "up") return "text-emerald-600";
  if (trend === "down") return "text-amber-600";
  return "text-slate-500";
};

const typeBadgeClass = (type) => {
  const t = String(type || "").toLowerCase();
  if (t === "dcr") return "bg-indigo-50 text-indigo-600";
  if (t === "ofi") return "bg-emerald-50 text-emerald-600";
  return "bg-slate-100 text-slate-600";
};

const actDotClass = (type) => {
  const t = String(type || "").toLowerCase();
  if (t === "dcr") return "bg-indigo-500";
  if (t === "ofi") return "bg-emerald-500";
  if (t === "approve") return "bg-blue-500";
  return "bg-slate-400";
};
</script>

<template>
  <!-- showSearch=false so Header shows SLOT-LEFT instead of search -->
  <AdminLayout :showSearch="false">
    <!-- ✅ This is the aligned header-left content -->
    <template #header-left>
      <div class="pt-1">
        <p class="text-sm text-slate-500 mb-1">{{ greeting }} 👋</p>
        <h1 class="text-[28px] font-extrabold tracking-[-0.5px] text-slate-900 leading-tight">
          {{ props.authUser?.name ?? "User" }}
        </h1>
        <p class="text-[13px] text-slate-400 mt-1">
          {{ props.authUser?.role ?? "User" }} · Quality Management System
        </p>
      </div>
    </template>

    <!-- ✅ Page content -->
    <div class="px-10 py-8 bg-slate-100 min-h-screen flex flex-col gap-7">
      <!-- Stats -->
      <div class="grid grid-cols-4 gap-4 max-[1100px]:grid-cols-2 max-[560px]:grid-cols-1">
        <div
          v-for="stat in props.stats"
          :key="stat.label"
          class="relative bg-white border border-slate-200/70 rounded-2xl p-6 shadow-sm hover:shadow-md transition"
        >
          <!-- top accent bar -->
          <div
            class="absolute left-0 right-0 top-0 h-[3px] rounded-t-2xl opacity-70"
            :style="{ background: stat.color || '#94a3b8' }"
          ></div>

          <div class="flex items-start gap-4">
            <!-- Icon wrap -->
            <div
              class="w-11 h-11 rounded-xl flex items-center justify-center shrink-0"
              :style="{ background: stat.bg || '#f1f5f9' }"
            >
              <!-- docs -->
              <svg
                v-if="stat.icon === 'docs'"
                class="w-5 h-5"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
                :style="{ color: stat.color || '#64748b' }"
              >
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                <polyline points="14 2 14 8 20 8" />
                <line x1="16" y1="13" x2="8" y2="13" />
                <line x1="16" y1="17" x2="8" y2="17" />
              </svg>

              <!-- dcr -->
              <svg
                v-else-if="stat.icon === 'dcr'"
                class="w-5 h-5"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
                :style="{ color: stat.color || '#64748b' }"
              >
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                <polyline points="14 2 14 8 20 8" />
                <line x1="12" y1="18" x2="12" y2="12" />
                <line x1="9" y1="15" x2="15" y2="15" />
              </svg>

              <!-- ofi -->
              <svg
                v-else-if="stat.icon === 'ofi'"
                class="w-5 h-5"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
                :style="{ color: stat.color || '#64748b' }"
              >
                <circle cx="12" cy="12" r="10" />
                <line x1="12" y1="8" x2="12" y2="12" />
                <line x1="12" y1="16" x2="12.01" y2="16" />
              </svg>

              <!-- users -->
              <svg
                v-else-if="stat.icon === 'users'"
                class="w-5 h-5"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
                :style="{ color: stat.color || '#64748b' }"
              >
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                <circle cx="9" cy="7" r="4" />
                <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                <path d="M16 3.13a4 4 0 0 1 0 7.75" />
              </svg>
            </div>

            <!-- Text -->
            <div class="flex-1">
              <div class="text-[11px] uppercase tracking-wider text-slate-400 font-semibold">
                {{ stat.label }}
              </div>

              <div class="mt-2 text-3xl font-extrabold text-slate-900 leading-none">
                {{ stat.value }}
              </div>

              <div class="mt-2 text-xs flex items-center gap-1" :class="trendTextClass(stat.trend)">
                <svg
                  v-if="stat.trend === 'up'"
                  class="w-3.5 h-3.5"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2.5"
                >
                  <polyline points="18 15 12 9 6 15" />
                </svg>
                <svg
                  v-else-if="stat.trend === 'down'"
                  class="w-3.5 h-3.5"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2.5"
                >
                  <polyline points="6 9 12 15 18 9" />
                </svg>
                <span class="text-slate-500" v-if="!stat.trend">{{ stat.change }}</span>
                <span v-else>{{ stat.change }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Two Column -->
      <div class="grid grid-cols-[1fr_1.3fr] gap-4 max-[1100px]:grid-cols-1">
        <!-- Recent Activity -->
        <div class="bg-white rounded-2xl border border-slate-200/70 shadow-sm overflow-hidden">
          <div class="px-6 pt-5 pb-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="font-semibold text-slate-900">Recent Activity</h2>
            <span class="text-xs font-semibold text-emerald-600 bg-emerald-50 px-3 py-1 rounded-full flex items-center gap-2">
              <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full animate-pulse"></span>
              Live
            </span>
          </div>

          <ul class="py-2">
            <li
              v-for="(act, idx) in props.recentActivity"
              :key="`${act.doc}-${idx}`"
              class="px-6 py-3 border-b border-slate-50 hover:bg-slate-50 transition flex items-start gap-3"
            >
              <span class="w-2.5 h-2.5 rounded-full mt-1.5 shrink-0" :class="actDotClass(act.type)"></span>

              <div class="flex-1 min-w-0">
                <p class="text-sm text-slate-700 leading-snug">
                  <span class="font-semibold text-slate-900">{{ act.user }}</span>
                  {{ act.action }}
                </p>
                <p class="text-xs text-slate-400 mt-1 font-mono truncate">
                  {{ act.doc }}
                </p>
              </div>

              <div class="text-xs text-slate-400 whitespace-nowrap mt-1">
                {{ act.time }}
              </div>
            </li>

            <li v-if="props.recentActivity.length === 0" class="px-6 py-4 text-sm text-slate-400">
              No recent activity yet.
            </li>
          </ul>
        </div>

        <!-- Pending Documents -->
        <div class="bg-white rounded-2xl border border-slate-200/70 shadow-sm overflow-hidden">
          <div class="px-6 pt-5 pb-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="font-semibold text-slate-900">Pending Documents</h2>
            <a href="#" class="text-sm text-indigo-600 hover:underline">View all</a>
          </div>

          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="bg-slate-50 text-slate-400 text-[11px] uppercase tracking-wider">
                  <th class="text-left px-6 py-3 font-semibold">Document ID</th>
                  <th class="text-left px-6 py-3 font-semibold">Type</th>
                  <th class="text-left px-6 py-3 font-semibold">Department</th>
                  <th class="text-left px-6 py-3 font-semibold">Status</th>
                </tr>
              </thead>

              <tbody>
                <tr
                  v-for="doc in props.pendingDocs"
                  :key="doc.id"
                  class="border-t border-slate-50 hover:bg-slate-50 transition"
                >
                  <td class="px-6 py-3 font-mono text-slate-900">{{ doc.id }}</td>

                  <td class="px-6 py-3">
                    <span class="text-[11px] font-bold px-2.5 py-1 rounded-md" :class="typeBadgeClass(doc.type)">
                      {{ doc.type }}
                    </span>
                  </td>

                  <td class="px-6 py-3 text-slate-600">{{ doc.dept }}</td>

                  <td class="px-6 py-3">
                    <span class="text-[11px] px-3 py-1 rounded-full bg-amber-50 text-amber-600 font-semibold">
                      {{ doc.status }}
                    </span>
                  </td>
                </tr>

                <tr v-if="props.pendingDocs.length === 0">
                  <td colspan="4" class="px-6 py-6 text-slate-400 text-sm">
                    No pending documents yet.
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>