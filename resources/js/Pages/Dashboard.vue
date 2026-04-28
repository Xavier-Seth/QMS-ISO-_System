<script setup>
import AdminLayoutWithHeader from '@/Layouts/AdminLayoutWithHeader.vue'
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'

const user = usePage().props.auth.user

const greeting = computed(() => {
  const h = new Date().getHours()
  if (h >= 5 && h < 12) return 'Good morning 👋'
  if (h >= 12 && h < 18) return 'Good afternoon 👋'
  return 'Good evening 👋'
})

const props = defineProps({
  summary: {
    type: Object,
    default: () => ({
      total_document_types: 0,
      active_document_types: 0,
      obsolete_document_types: 0,
      total_uploads: 0,
      total_ofi: 0,
      total_dcr: 0,
      total_car: 0,
      pending_ofi: 0,
      pending_dcr: 0,
      pending_car: 0,
    }),
  },
  needs_revision: { type: Array, default: () => [] },
  recent_uploads: { type: Array, default: () => [] },
  series_distribution: { type: Array, default: () => [] },
  recent_activity: { type: Array, default: () => [] },
  yearly_stats: { type: Array, default: () => [] },
})

const totalPending = computed(
  () => props.summary.pending_ofi + props.summary.pending_dcr + props.summary.pending_car
)

const activePercent = computed(() => {
  const total = props.summary.total_document_types
  return total > 0
    ? Math.round((props.summary.active_document_types / total) * 100)
    : 0
})

const statusColor = {
  approved: { text: 'text-emerald-600', bg: 'bg-emerald-50', dot: 'bg-emerald-500' },
  pending:  { text: 'text-amber-600',   bg: 'bg-amber-50',   dot: 'bg-amber-400'  },
  rejected: { text: 'text-red-600',     bg: 'bg-red-50',     dot: 'bg-red-400'    },
  draft:    { text: 'text-slate-500',   bg: 'bg-slate-100',  dot: 'bg-slate-400'  },
}

const typeColor = {
  OFI: 'bg-violet-100 text-violet-700',
  DCR: 'bg-sky-100 text-sky-700',
  CAR: 'bg-rose-100 text-rose-700',
}

function getStatus(status) {
  return statusColor[status] ?? statusColor.draft
}

const maxYearlyTotal = computed(() =>
  props.yearly_stats.reduce((m, r) => Math.max(m, r.grand_total), 1)
)
</script>

<template>
  <AdminLayoutWithHeader>
    <template #headerLeft>
      <div>
        <p class="text-xs text-slate-400 leading-none">{{ greeting }},</p>
        <p class="text-sm font-semibold text-slate-700 leading-tight mt-0.5">{{ user?.name }}</p>
      </div>
    </template>

    <div class="px-8 py-7 space-y-6 bg-[#f4f6f8] min-h-screen">

      <!-- Page heading -->
      <div>
        <h1 class="text-xl font-semibold text-slate-800 tracking-tight">System Overview</h1>
        <p class="text-sm text-slate-400 mt-0.5">A snapshot of your QMS document system.</p>
      </div>

      <!-- ── Row 1: Summary Cards ── -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">

        <!-- Documents -->
        <div class="bg-white rounded-xl border border-slate-200 p-5 space-y-3">
          <div class="flex items-center justify-between">
            <span class="text-xs font-medium text-slate-400 uppercase tracking-widest">Document Types</span>
            <span class="inline-flex items-center gap-1.5 text-xs font-medium px-2 py-0.5 rounded-full bg-slate-100 text-slate-500">
              {{ summary.total_uploads }} uploads
            </span>
          </div>
          <div class="text-3xl font-bold text-slate-800 tabular-nums">
            {{ summary.total_document_types }}
          </div>
          <!-- Active / Obsolete bar -->
          <div class="space-y-1.5">
            <div class="flex justify-between text-xs text-slate-400">
              <span>{{ summary.active_document_types }} active</span>
              <span>{{ summary.obsolete_document_types }} obsolete</span>
            </div>
            <div class="h-1.5 rounded-full bg-slate-100 overflow-hidden">
              <div
                class="h-full bg-indigo-500 rounded-full transition-all duration-500"
                :style="{ width: activePercent + '%' }"
              />
            </div>
          </div>
        </div>

        <!-- OFI -->
        <div class="bg-white rounded-xl border border-slate-200 p-5 space-y-3">
          <div class="flex items-center justify-between">
            <span class="text-xs font-medium text-slate-400 uppercase tracking-widest">OFI Records</span>
            <span v-if="summary.pending_ofi > 0"
              class="inline-flex items-center gap-1 text-xs font-semibold px-2 py-0.5 rounded-full bg-amber-50 text-amber-600">
              {{ summary.pending_ofi }} pending
            </span>
          </div>
          <div class="text-3xl font-bold text-slate-800 tabular-nums">{{ summary.total_ofi }}</div>
          <p class="text-xs text-slate-400">Opportunity for Improvement forms</p>
        </div>

        <!-- DCR -->
        <div class="bg-white rounded-xl border border-slate-200 p-5 space-y-3">
          <div class="flex items-center justify-between">
            <span class="text-xs font-medium text-slate-400 uppercase tracking-widest">DCR Records</span>
            <span v-if="summary.pending_dcr > 0"
              class="inline-flex items-center gap-1 text-xs font-semibold px-2 py-0.5 rounded-full bg-amber-50 text-amber-600">
              {{ summary.pending_dcr }} pending
            </span>
          </div>
          <div class="text-3xl font-bold text-slate-800 tabular-nums">{{ summary.total_dcr }}</div>
          <p class="text-xs text-slate-400">Document Change Requests</p>
        </div>

        <!-- CAR -->
        <div class="bg-white rounded-xl border border-slate-200 p-5 space-y-3">
          <div class="flex items-center justify-between">
            <span class="text-xs font-medium text-slate-400 uppercase tracking-widest">CAR Records</span>
            <span v-if="summary.pending_car > 0"
              class="inline-flex items-center gap-1 text-xs font-semibold px-2 py-0.5 rounded-full bg-amber-50 text-amber-600">
              {{ summary.pending_car }} pending
            </span>
          </div>
          <div class="text-3xl font-bold text-slate-800 tabular-nums">{{ summary.total_car }}</div>
          <p class="text-xs text-slate-400">Corrective Action Requests</p>
        </div>

      </div>

      <!-- ── Row 2: Recent Uploads + Recent Activity ── -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

        <!-- Recent Uploads -->
        <div class="bg-white rounded-xl border border-slate-200 p-5">
          <div class="flex items-center justify-between mb-4">
            <h2 class="text-sm font-semibold text-slate-700">Recently Uploaded</h2>
            <Link href="/documents" class="text-xs text-indigo-500 hover:underline font-medium">
              Browse
            </Link>
          </div>

          <div v-if="recent_uploads.length === 0" class="text-sm text-slate-400 py-4 text-center">
            No uploads yet.
          </div>

          <ul v-else class="divide-y divide-slate-100">
            <li
              v-for="upload in recent_uploads"
              :key="upload.id"
              class="py-3 flex items-start gap-3"
            >
              <!-- File icon -->
              <div class="mt-0.5 shrink-0 w-8 h-8 rounded-lg bg-indigo-50 flex items-center justify-center">
                <svg class="w-4 h-4 text-indigo-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                  <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                  <polyline points="14 2 14 8 20 8"/>
                </svg>
              </div>

              <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-slate-700 truncate">
                  {{ upload.file_name }}
                </p>
                <p class="text-xs text-slate-400 mt-0.5 truncate">
                  {{ upload.doc_code }} · {{ upload.uploader }}
                  <span v-if="upload.revision"> · Rev. {{ upload.revision }}</span>
                </p>
              </div>

              <span class="text-xs text-slate-400 shrink-0 mt-0.5">{{ upload.uploaded_at }}</span>
            </li>
          </ul>
        </div>

        <!-- Recent Form Activity -->
        <div class="bg-white rounded-xl border border-slate-200 p-5">
          <div class="flex items-center justify-between mb-4">
            <h2 class="text-sm font-semibold text-slate-700">Recent Form Activity</h2>
            <Link href="/inbox" class="text-xs text-indigo-500 hover:underline font-medium">
              View inbox
            </Link>
          </div>

          <div v-if="recent_activity.length === 0" class="text-sm text-slate-400 py-4 text-center">
            No recent activity.
          </div>

          <ul v-else class="divide-y divide-slate-100">
            <li
              v-for="(item, i) in recent_activity"
              :key="i"
              class="py-3 flex items-center gap-3"
            >
              <!-- Type badge -->
              <span
                class="shrink-0 text-[11px] font-bold px-2 py-0.5 rounded-md"
                :class="typeColor[item.type] ?? 'bg-slate-100 text-slate-500'"
              >
                {{ item.type }}
              </span>

              <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-slate-700 truncate">{{ item.record_no }}</p>
                <p class="text-xs text-slate-400 truncate">{{ item.actor }}</p>
              </div>

              <div class="shrink-0 text-right">
                <span
                  class="inline-flex items-center gap-1.5 text-xs font-medium px-2 py-0.5 rounded-full"
                  :class="[getStatus(item.workflow_status).bg, getStatus(item.workflow_status).text]"
                >
                  <span
                    class="w-1.5 h-1.5 rounded-full"
                    :class="getStatus(item.workflow_status).dot"
                  />
                  {{ item.workflow_status }}
                </span>
                <p class="text-[11px] text-slate-400 mt-0.5">{{ item.updated_at }}</p>
              </div>
            </li>
          </ul>
        </div>

      </div>

      <!-- ── Row 4: Yearly Statistics ── -->
      <div v-if="yearly_stats.length > 0" class="bg-white rounded-xl border border-slate-200 p-5">

        <div class="flex items-center justify-between mb-5">
          <div>
            <h2 class="text-sm font-semibold text-slate-700">Yearly Record Statistics</h2>
            <p class="text-xs text-slate-400 mt-0.5">OFI, DCR, and CAR records created per year — approved counts shown as closed</p>
          </div>
          <span class="text-xs text-slate-400 font-medium">{{ yearly_stats.length }} year{{ yearly_stats.length !== 1 ? 's' : '' }}</span>
        </div>

        <!-- Visual bar rows -->
        <div class="space-y-4 mb-6">
          <div
            v-for="row in [...yearly_stats].reverse()"
            :key="row.year"
            class="flex items-center gap-4"
          >
            <!-- Year label -->
            <span class="w-10 shrink-0 text-xs font-bold text-slate-600 tabular-nums">{{ row.year }}</span>

            <!-- Stacked bar -->
            <div class="flex-1 flex h-5 rounded-md overflow-hidden bg-slate-100 gap-px">
              <!-- OFI portion -->
              <div
                class="bg-violet-400 flex items-center justify-center transition-all duration-500"
                :style="{ width: (row.ofi_total / maxYearlyTotal * 100) + '%' }"
                :title="`OFI: ${row.ofi_total} total, ${row.ofi_closed} approved`"
              />
              <!-- DCR portion -->
              <div
                class="bg-sky-400 flex items-center justify-center transition-all duration-500"
                :style="{ width: (row.dcr_total / maxYearlyTotal * 100) + '%' }"
                :title="`DCR: ${row.dcr_total} total, ${row.dcr_closed} approved`"
              />
              <!-- CAR portion -->
              <div
                class="bg-rose-400 flex items-center justify-center transition-all duration-500"
                :style="{ width: (row.car_total / maxYearlyTotal * 100) + '%' }"
                :title="`CAR: ${row.car_total} total, ${row.car_closed} approved`"
              />
            </div>

            <!-- Total + close rate -->
            <div class="shrink-0 text-right w-28">
              <span class="text-sm font-semibold text-slate-700 tabular-nums">{{ row.grand_total }}</span>
              <span class="text-xs text-slate-400"> records</span>
              <div class="text-xs text-emerald-600 font-medium tabular-nums">
                {{ row.close_rate }}% approved
              </div>
            </div>
          </div>
        </div>

        <!-- Legend -->
        <div class="flex items-center gap-4 border-t border-slate-100 pt-4">
          <span class="flex items-center gap-1.5 text-xs text-slate-500">
            <span class="w-2.5 h-2.5 rounded-sm bg-violet-400 shrink-0"></span> OFI
          </span>
          <span class="flex items-center gap-1.5 text-xs text-slate-500">
            <span class="w-2.5 h-2.5 rounded-sm bg-sky-400 shrink-0"></span> DCR
          </span>
          <span class="flex items-center gap-1.5 text-xs text-slate-500">
            <span class="w-2.5 h-2.5 rounded-sm bg-rose-400 shrink-0"></span> CAR
          </span>
          <span class="ml-auto text-xs text-slate-400">"Approved" counts as closed</span>
        </div>

        <!-- Detailed table (collapsible feel — always shown, clean) -->
        <div class="mt-5 overflow-x-auto">
          <table class="w-full text-xs text-left border-collapse">
            <thead>
              <tr class="border-b border-slate-100">
                <th class="pb-2 pr-4 font-semibold text-slate-500 w-14">Year</th>
                <th class="pb-2 px-3 font-semibold text-violet-500 text-right">OFI Total</th>
                <th class="pb-2 px-3 font-semibold text-violet-400 text-right">OFI Closed</th>
                <th class="pb-2 px-3 font-semibold text-sky-500 text-right">DCR Total</th>
                <th class="pb-2 px-3 font-semibold text-sky-400 text-right">DCR Closed</th>
                <th class="pb-2 px-3 font-semibold text-rose-500 text-right">CAR Total</th>
                <th class="pb-2 px-3 font-semibold text-rose-400 text-right">CAR Closed</th>
                <th class="pb-2 pl-3 font-semibold text-slate-500 text-right">Grand Total</th>
                <th class="pb-2 pl-3 font-semibold text-emerald-600 text-right">Approved</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
              <tr
                v-for="row in [...yearly_stats].reverse()"
                :key="'t' + row.year"
                class="hover:bg-slate-50 transition-colors"
              >
                <td class="py-2 pr-4 font-bold text-slate-700 tabular-nums">{{ row.year }}</td>
                <td class="py-2 px-3 text-right tabular-nums text-slate-600">{{ row.ofi_total }}</td>
                <td class="py-2 px-3 text-right tabular-nums text-slate-400">{{ row.ofi_closed }}</td>
                <td class="py-2 px-3 text-right tabular-nums text-slate-600">{{ row.dcr_total }}</td>
                <td class="py-2 px-3 text-right tabular-nums text-slate-400">{{ row.dcr_closed }}</td>
                <td class="py-2 px-3 text-right tabular-nums text-slate-600">{{ row.car_total }}</td>
                <td class="py-2 px-3 text-right tabular-nums text-slate-400">{{ row.car_closed }}</td>
                <td class="py-2 pl-3 text-right tabular-nums font-semibold text-slate-700">{{ row.grand_total }}</td>
                <td class="py-2 pl-3 text-right tabular-nums font-semibold text-emerald-600">
                  {{ row.grand_closed }}
                  <span class="font-normal text-slate-400">({{ row.close_rate }}%)</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

      </div>

      <!-- ── Pending alert banner (only shown if any pending) ── -->
      <div
        v-if="totalPending > 0"
        class="flex items-center justify-between gap-4 rounded-xl border border-amber-200 bg-amber-50 px-5 py-4"
      >
        <div class="flex items-center gap-3">
          <svg class="w-5 h-5 text-amber-500 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
            <circle cx="12" cy="12" r="10"/>
            <line x1="12" y1="8" x2="12" y2="12"/>
            <line x1="12" y1="16" x2="12.01" y2="16"/>
          </svg>
          <p class="text-sm text-amber-700 font-medium">
            You have <strong>{{ totalPending }}</strong> record{{ totalPending !== 1 ? 's' : '' }} awaiting review in your inbox.
          </p>
        </div>
        <Link href="/inbox" class="shrink-0 text-sm font-semibold text-amber-700 hover:text-amber-900 underline underline-offset-2">
          Go to Inbox
        </Link>
      </div>

    </div>
  </AdminLayoutWithHeader>
</template>