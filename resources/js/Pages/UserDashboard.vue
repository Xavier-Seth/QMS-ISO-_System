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
      ofi: { total: 0, draft: 0, pending: 0, approved: 0, rejected: 0 },
      dcr: { total: 0, draft: 0, pending: 0, approved: 0, rejected: 0 },
      car: { total: 0, draft: 0, pending: 0, approved: 0, rejected: 0 },
    }),
  },
  needs_attention: { type: Array, default: () => [] },
  my_drafts:       { type: Array, default: () => [] },
  pending_records: { type: Array, default: () => [] },
  recent_activity: { type: Array, default: () => [] },
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

function showRoute(type, id) {
  if (type === 'OFI') return `/ofi/records/${id}`
  if (type === 'DCR') return `/dcr/records/${id}`
  return `/car/records/${id}`
}

const totalPending = computed(
  () => props.summary.ofi.pending + props.summary.dcr.pending + props.summary.car.pending
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

    <div class="px-4 py-5 md:px-8 md:py-7 space-y-5 bg-[#f4f6f8] min-h-screen">

      <!-- Page heading -->
      <div>
        <h1 class="text-xl font-semibold text-slate-800 tracking-tight">My Dashboard</h1>
        <p class="text-sm text-slate-400 mt-0.5">Track and manage your OFI, DCR, and CAR submissions.</p>
      </div>

      <!-- ── Row 1: Summary Cards (always 3 columns) ── -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">

        <!-- OFI -->
        <div class="bg-white rounded-xl border border-slate-200 p-4 space-y-2.5">
          <div class="flex items-center justify-between">
            <span class="text-xs font-medium text-slate-400 uppercase tracking-widest">OFI</span>
            <span v-if="summary.ofi.pending > 0"
              class="inline-flex items-center gap-1 text-xs font-semibold px-2 py-0.5 rounded-full bg-amber-50 text-amber-600">
              {{ summary.ofi.pending }} pending
            </span>
          </div>
          <div class="text-2xl font-bold text-slate-800 tabular-nums">{{ summary.ofi.total }}</div>
          <div class="flex items-center gap-1.5 text-xs text-slate-400 flex-wrap">
            <span>{{ summary.ofi.draft }} draft</span>
            <span class="text-slate-300">·</span>
            <span class="text-emerald-600 font-medium">{{ summary.ofi.approved }} approved</span>
            <template v-if="summary.ofi.rejected > 0">
              <span class="text-slate-300">·</span>
              <span class="text-red-500 font-medium">{{ summary.ofi.rejected }} rejected</span>
            </template>
          </div>
        </div>

        <!-- DCR -->
        <div class="bg-white rounded-xl border border-slate-200 p-4 space-y-2.5">
          <div class="flex items-center justify-between">
            <span class="text-xs font-medium text-slate-400 uppercase tracking-widest">DCR</span>
            <span v-if="summary.dcr.pending > 0"
              class="inline-flex items-center gap-1 text-xs font-semibold px-2 py-0.5 rounded-full bg-amber-50 text-amber-600">
              {{ summary.dcr.pending }} pending
            </span>
          </div>
          <div class="text-2xl font-bold text-slate-800 tabular-nums">{{ summary.dcr.total }}</div>
          <div class="flex items-center gap-1.5 text-xs text-slate-400 flex-wrap">
            <span>{{ summary.dcr.draft }} draft</span>
            <span class="text-slate-300">·</span>
            <span class="text-emerald-600 font-medium">{{ summary.dcr.approved }} approved</span>
            <template v-if="summary.dcr.rejected > 0">
              <span class="text-slate-300">·</span>
              <span class="text-red-500 font-medium">{{ summary.dcr.rejected }} rejected</span>
            </template>
          </div>
        </div>

        <!-- CAR -->
        <div class="bg-white rounded-xl border border-slate-200 p-4 space-y-2.5">
          <div class="flex items-center justify-between">
            <span class="text-xs font-medium text-slate-400 uppercase tracking-widest">CAR</span>
            <span v-if="summary.car.pending > 0"
              class="inline-flex items-center gap-1 text-xs font-semibold px-2 py-0.5 rounded-full bg-amber-50 text-amber-600">
              {{ summary.car.pending }} pending
            </span>
          </div>
          <div class="text-2xl font-bold text-slate-800 tabular-nums">{{ summary.car.total }}</div>
          <div class="flex items-center gap-1.5 text-xs text-slate-400 flex-wrap">
            <span>{{ summary.car.draft }} draft</span>
            <span class="text-slate-300">·</span>
            <span class="text-emerald-600 font-medium">{{ summary.car.approved }} approved</span>
            <template v-if="summary.car.rejected > 0">
              <span class="text-slate-300">·</span>
              <span class="text-red-500 font-medium">{{ summary.car.rejected }} rejected</span>
            </template>
          </div>
        </div>

      </div>

      <!-- ── Quick Actions ── -->
      <div class="bg-white rounded-xl border border-slate-200 p-4">
        <h2 class="text-sm font-semibold text-slate-700 mb-3">Quick Actions</h2>
        <div class="flex flex-wrap gap-3">
          <Link
            href="/ofi-form"
            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-violet-50 text-violet-700 text-sm font-medium hover:bg-violet-100 transition-colors"
          >
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            New OFI
          </Link>
          <Link
            href="/dcr"
            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-sky-50 text-sky-700 text-sm font-medium hover:bg-sky-100 transition-colors"
          >
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            New DCR
          </Link>
          <Link
            href="/car"
            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-rose-50 text-rose-700 text-sm font-medium hover:bg-rose-100 transition-colors"
          >
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            New CAR
          </Link>
          <Link
            href="/my-records"
            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-slate-100 text-slate-600 text-sm font-medium hover:bg-slate-200 transition-colors"
          >
            View All My Records
          </Link>
        </div>
      </div>

      <!-- ── Row 3: Needs Attention + My Drafts (drafts column hidden when empty) ── -->
      <div :class="my_drafts.length > 0 ? 'grid grid-cols-1 md:grid-cols-2 gap-4' : ''">

        <!-- Needs Attention (Rejected) -->
        <div class="bg-white rounded-xl border border-slate-200 p-4">
          <div class="flex items-center gap-2 mb-3">
            <h2 class="text-sm font-semibold text-slate-700">Needs Attention</h2>
            <span v-if="needs_attention.length > 0"
              class="text-xs font-semibold px-1.5 py-0.5 rounded-full bg-red-50 text-red-600">
              {{ needs_attention.length }}
            </span>
          </div>

          <div v-if="needs_attention.length === 0" class="text-sm text-slate-400 py-3 text-center">
            No rejected records.
          </div>

          <ul v-else class="divide-y divide-slate-100">
            <li
              v-for="item in needs_attention"
              :key="item.type + item.id"
              class="py-2.5 flex items-start gap-3"
            >
              <span
                class="shrink-0 mt-0.5 text-[11px] font-bold px-2 py-0.5 rounded-md"
                :class="typeColor[item.type] ?? 'bg-slate-100 text-slate-500'"
              >
                {{ item.type }}
              </span>
              <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-slate-700 truncate">{{ item.record_no }}</p>
                <p
                  class="text-xs mt-0.5 truncate"
                  :class="item.rejection_reason ? 'text-red-500' : 'text-slate-400 italic'"
                >
                  {{ item.rejection_reason || 'No reason provided' }}
                </p>
                <p class="text-xs text-slate-400 mt-0.5">{{ item.updated_at }}</p>
              </div>
              <Link
                :href="showRoute(item.type, item.id)"
                class="shrink-0 text-xs font-medium text-indigo-500 hover:underline mt-0.5"
              >
                Edit
              </Link>
            </li>
          </ul>
        </div>

        <!-- My Drafts — hidden entirely when empty -->
        <div v-if="my_drafts.length > 0" class="bg-white rounded-xl border border-slate-200 p-4">
          <div class="flex items-center gap-2 mb-3">
            <h2 class="text-sm font-semibold text-slate-700">My Drafts</h2>
            <span class="text-xs font-semibold px-1.5 py-0.5 rounded-full bg-slate-100 text-slate-500">
              {{ my_drafts.length }}
            </span>
          </div>

          <ul class="divide-y divide-slate-100">
            <li
              v-for="item in my_drafts"
              :key="item.type + item.id"
              class="py-2.5 flex items-center gap-3"
            >
              <span
                class="shrink-0 text-[11px] font-bold px-2 py-0.5 rounded-md"
                :class="typeColor[item.type] ?? 'bg-slate-100 text-slate-500'"
              >
                {{ item.type }}
              </span>
              <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-slate-700 truncate">{{ item.record_no }}</p>
                <p class="text-xs text-slate-400 mt-0.5">{{ item.updated_at }}</p>
              </div>
              <Link
                :href="showRoute(item.type, item.id)"
                class="shrink-0 text-xs font-medium text-indigo-500 hover:underline"
              >
                Continue
              </Link>
            </li>
          </ul>
        </div>

      </div>

      <!-- ── Row 4: Pending + Recent Activity ── -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

        <!-- Pending Records -->
        <div class="bg-white rounded-xl border border-slate-200 p-4">
          <div class="flex items-center gap-2 mb-3">
            <h2 class="text-sm font-semibold text-slate-700">Pending Review</h2>
            <span v-if="pending_records.length > 0"
              class="text-xs font-semibold px-1.5 py-0.5 rounded-full bg-amber-50 text-amber-600">
              {{ pending_records.length }}
            </span>
          </div>

          <div v-if="pending_records.length === 0" class="text-sm text-slate-400 py-3 text-center">
            No records awaiting review.
          </div>

          <ul v-else class="divide-y divide-slate-100">
            <li
              v-for="item in pending_records"
              :key="item.type + item.id"
              class="py-2.5 flex items-center gap-3"
            >
              <span
                class="shrink-0 text-[11px] font-bold px-2 py-0.5 rounded-md"
                :class="typeColor[item.type] ?? 'bg-slate-100 text-slate-500'"
              >
                {{ item.type }}
              </span>
              <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-slate-700 truncate">{{ item.record_no }}</p>
                <p class="text-xs text-slate-400 mt-0.5">{{ item.updated_at }}</p>
              </div>
              <span class="shrink-0 inline-flex items-center gap-1.5 text-xs font-medium px-2 py-0.5 rounded-full bg-amber-50 text-amber-600">
                <span class="w-1.5 h-1.5 rounded-full bg-amber-400" />
                pending
              </span>
            </li>
          </ul>
        </div>

        <!-- Recent Activity -->
        <div class="bg-white rounded-xl border border-slate-200 p-4">
          <div class="flex items-center justify-between mb-3">
            <h2 class="text-sm font-semibold text-slate-700">Recent Activity</h2>
            <Link href="/my-records" class="text-xs text-indigo-500 hover:underline font-medium">
              View all
            </Link>
          </div>

          <div v-if="recent_activity.length === 0" class="text-sm text-slate-400 py-3 text-center">
            No recent activity.
          </div>

          <ul v-else class="divide-y divide-slate-100">
            <li
              v-for="(item, i) in recent_activity"
              :key="i"
              class="py-2.5 flex items-center gap-3"
            >
              <span
                class="shrink-0 text-[11px] font-bold px-2 py-0.5 rounded-md"
                :class="typeColor[item.type] ?? 'bg-slate-100 text-slate-500'"
              >
                {{ item.type }}
              </span>
              <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-slate-700 truncate">{{ item.record_no }}</p>
                <p class="text-xs text-slate-400 mt-0.5">{{ item.updated_at }}</p>
              </div>
              <span
                class="shrink-0 inline-flex items-center gap-1.5 text-xs font-medium px-2 py-0.5 rounded-full"
                :class="[getStatus(item.workflow_status).bg, getStatus(item.workflow_status).text]"
              >
                <span
                  class="w-1.5 h-1.5 rounded-full"
                  :class="getStatus(item.workflow_status).dot"
                />
                {{ item.workflow_status }}
              </span>
            </li>
          </ul>
        </div>

      </div>

      <!-- ── Pending alert banner ── -->
      <div
        v-if="totalPending > 0"
        class="flex items-center gap-4 rounded-xl border border-amber-200 bg-amber-50 px-5 py-4"
      >
        <svg class="w-5 h-5 text-amber-500 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
          <circle cx="12" cy="12" r="10"/>
          <line x1="12" y1="8" x2="12" y2="12"/>
          <line x1="12" y1="16" x2="12.01" y2="16"/>
        </svg>
        <p class="text-sm text-amber-700 font-medium">
          You have <strong>{{ totalPending }}</strong> record{{ totalPending !== 1 ? 's' : '' }} awaiting admin review.
        </p>
      </div>

    </div>
  </AdminLayoutWithHeader>
</template>
