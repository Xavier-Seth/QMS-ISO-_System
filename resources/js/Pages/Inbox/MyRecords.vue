<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { Link, router } from '@inertiajs/vue3'
import { onUnmounted, ref, watch } from 'vue'

const props = defineProps({
  records: Object,
  filters: Object,
  returnedCounts: {
    type: Object,
    default: () => ({ ofi: 0, car: 0, dcr: 0 }),
  },
})

/* ---------------------------
   Tab + workflow filter state
--------------------------- */
const activeTab = ref(props.filters?.tab ?? 'ofi')
const workflowStatus = ref(props.filters?.workflow_status ?? 'all')
const q = ref(props.filters?.q ?? '')

let debounceTimer = null

watch(q, () => {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(applyFilters, 400)
})

onUnmounted(() => clearTimeout(debounceTimer))

const typeTabs = [
  { key: 'ofi', label: 'OFI' },
  { key: 'car', label: 'CAR' },
  { key: 'dcr', label: 'DCR' },
]

const workflowOptions = [
  { key: 'all', label: 'All' },
  { key: 'pending', label: 'Pending' },
  { key: 'approved', label: 'Approved' },
  { key: 'rejected', label: 'Returned' },
]

function switchTab(tabKey) {
  clearTimeout(debounceTimer)
  activeTab.value = tabKey
  q.value = ''
  applyFilters()
}

function setWorkflow(key) {
  clearTimeout(debounceTimer)
  workflowStatus.value = key
  applyFilters()
}

function applyFilters() {
  router.get(
    '/my-records',
    {
      tab: activeTab.value,
      workflow_status: workflowStatus.value,
      q: q.value || undefined,
    },
    {
      preserveScroll: true,
      replace: true,
    }
  )
}

/* ---------------------------
   Helpers
--------------------------- */
function workflowBadgeClass(status) {
  if (status === 'approved') return 'bg-emerald-50 text-emerald-700 ring-emerald-200'
  if (status === 'rejected') return 'bg-rose-50 text-rose-700 ring-rose-200'
  if (status === 'pending') return 'bg-amber-50 text-amber-700 ring-amber-200'
  return 'bg-slate-100 text-slate-700 ring-slate-200'
}

function resolutionBadgeClass(status) {
  if (status === 'closed') return 'bg-emerald-50 text-emerald-700 ring-emerald-200'
  if (status === 'ongoing') return 'bg-blue-50 text-blue-700 ring-blue-200'
  if (status === 'open') return 'bg-sky-50 text-sky-700 ring-sky-200'
  return 'bg-slate-100 text-slate-700 ring-slate-200'
}
</script>

<template>
  <AdminLayout>
    <div class="space-y-6 p-6">
      <!-- Header -->
      <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="bg-gradient-to-r from-slate-900 to-slate-800 px-6 py-5">
          <h1 class="text-xl font-semibold text-white">My Records</h1>
          <p class="mt-1 text-sm text-slate-300">
            Track your submitted OFI, CAR, and DCR records.
          </p>
        </div>

        <!-- Tab bar -->
        <div class="flex items-center border-b border-slate-200 px-4">
          <button
            v-for="tab in typeTabs"
            :key="tab.key"
            type="button"
            class="relative px-5 py-3.5 text-sm font-medium transition"
            :class="
              activeTab === tab.key
                ? 'text-slate-900 after:absolute after:bottom-0 after:left-0 after:right-0 after:h-0.5 after:bg-slate-900'
                : 'text-slate-500 hover:text-slate-700'
            "
            @click="switchTab(tab.key)"
          >
            {{ tab.label }}
            <span
              v-if="returnedCounts[tab.key] > 0"
              class="absolute right-2 top-2 h-2 w-2 rounded-full bg-rose-500"
              title="Has records returned for revision"
            ></span>
          </button>
        </div>

        <!-- Workflow sub-filter + search -->
        <div class="flex flex-wrap items-center justify-between gap-3 px-6 py-3">
          <div class="flex flex-wrap items-center gap-2">
            <button
              v-for="opt in workflowOptions"
              :key="opt.key"
              type="button"
              class="inline-flex items-center rounded-xl border px-4 py-1.5 text-sm transition"
              :class="
                workflowStatus === opt.key
                  ? 'border-slate-900 bg-slate-900 text-white'
                  : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'
              "
              @click="setWorkflow(opt.key)"
            >
              {{ opt.label }}
            </button>
          </div>

          <input
            v-model="q"
            type="text"
            placeholder="Search by record no., name, department…"
            aria-label="Search records"
            class="w-full max-w-sm rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-300"
          />
        </div>
      </div>

      <!-- Table -->
      <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="border-b border-slate-200 bg-slate-50">
              <tr class="text-left">
                <th class="px-5 py-3 font-semibold text-slate-700">Record No.</th>
                <th class="px-5 py-3 font-semibold text-slate-700">Subject</th>
                <th class="px-5 py-3 font-semibold text-slate-700">Workflow</th>
                <th class="px-5 py-3 font-semibold text-slate-700">Resolution</th>
                <th class="px-5 py-3 font-semibold text-slate-700">Date Submitted</th>
                <th class="px-5 py-3 text-right font-semibold text-slate-700">Actions</th>
              </tr>
            </thead>

            <tbody>
              <template
                v-for="record in records.data"
                :key="`${record.type}-${record.id}`"
              >
                <tr class="border-b border-slate-100 hover:bg-slate-50">
                  <td class="px-5 py-4 font-medium text-slate-900">
                    {{ record.record_no || '—' }}
                  </td>

                  <td class="px-5 py-4 text-slate-600">
                    {{ record.subject || '—' }}
                  </td>

                  <td class="px-5 py-4">
                    <span
                      class="rounded-full px-2 py-1 text-xs ring-1"
                      :class="workflowBadgeClass(record.workflow_status)"
                    >
                      {{ record.workflow_status === 'rejected' ? 'Returned for Revision' : (record.workflow_status || 'draft') }}
                    </span>
                  </td>

                  <td class="px-5 py-4">
                    <span
                      class="rounded-full px-2 py-1 text-xs ring-1"
                      :class="resolutionBadgeClass(record.resolution_status)"
                    >
                      {{ record.resolution_status || 'open' }}
                    </span>
                  </td>

                  <td class="px-5 py-4 text-slate-600">
                    {{ record.date_submitted || '—' }}
                  </td>

                  <td class="px-5 py-4">
                    <div class="flex justify-end gap-2">
                      <Link
                        :href="record.view_url"
                        class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs text-slate-700 hover:bg-slate-50"
                      >
                        {{ record.workflow_status === 'rejected' ? 'Edit / Resubmit' : 'View' }}
                      </Link>
                    </div>
                  </td>
                </tr>

                <tr
                  v-if="record.workflow_status === 'rejected'"
                  class="border-b border-amber-100 bg-amber-50"
                >
                  <td colspan="6" class="px-5 py-3">
                    <div class="flex items-start gap-2 text-sm text-amber-800">
                      <span class="mt-0.5 shrink-0 font-semibold">Reason for return:</span>
                      <span>{{ record.remarks }}</span>
                    </div>
                  </td>
                </tr>
              </template>

              <tr v-if="!records.data.length">
                <td colspan="6" class="px-5 py-8 text-center text-sm text-slate-500">
                  No records found.
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div class="flex flex-wrap items-center gap-2 border-t border-slate-200 bg-slate-50 px-6 py-4">
          <template v-for="(link, index) in records.links" :key="index">
            <Link
              v-if="link.url"
              :href="link.url"
              preserve-scroll
              preserve-state
              class="inline-flex items-center rounded-lg border px-3 py-1.5 text-sm"
              :class="
                link.active
                  ? 'border-slate-900 bg-slate-900 text-white'
                  : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-100'
              "
            >
              <span v-html="link.label" />
            </Link>

            <span
              v-else
              class="inline-flex cursor-not-allowed items-center rounded-lg border border-slate-200 bg-slate-100 px-3 py-1.5 text-sm text-slate-400"
            >
              <span v-html="link.label" />
            </span>
          </template>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
