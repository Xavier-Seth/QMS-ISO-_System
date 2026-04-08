<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { Link, router } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'

const props = defineProps({
  records: Object,
  filters: Object,
  counts: Object,
})

const workflowStatus = ref(props.filters?.workflow_status ?? 'all')
const type = ref(props.filters?.type ?? 'all')

watch([workflowStatus, type], () => {
  router.get('/my-records', {
    workflow_status: workflowStatus.value,
    type: type.value,
  }, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  })
})

const tabs = computed(() => {
  const source = props.counts?.[type.value] ?? props.counts?.all ?? {}

  return [
    { key: 'all', label: 'All', count: source.all ?? 0 },
    { key: 'pending', label: 'Pending', count: source.pending ?? 0 },
    { key: 'approved', label: 'Approved', count: source.approved ?? 0 },
    { key: 'rejected', label: 'Rejected', count: source.rejected ?? 0 },
  ]
})

function formatDate(value) {
  if (!value) return '—'
  return new Date(value).toLocaleString()
}

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

function typeBadgeClass(type) {
  if (type === 'car') return 'bg-blue-50 text-blue-700 ring-blue-200'
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
            Track your submitted OFI, CAR and future records.
          </p>
        </div>

        <!-- Tabs + Type Filter -->
        <div class="border-t border-slate-200 px-6 py-4">
          <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">

            <!-- Tabs -->
            <div class="flex flex-wrap gap-2">
              <button
                v-for="tab in tabs"
                :key="tab.key"
                type="button"
                @click="workflowStatus = tab.key"
                class="inline-flex items-center gap-2 rounded-xl border px-4 py-2 text-sm transition"
                :class="workflowStatus === tab.key
                  ? 'border-slate-900 bg-slate-900 text-white'
                  : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'"
              >
                <span>{{ tab.label }}</span>
                <span
                  class="rounded-full px-2 py-0.5 text-xs"
                  :class="workflowStatus === tab.key
                    ? 'bg-white/15 text-white'
                    : 'bg-slate-100 text-slate-600'"
                >
                  {{ tab.count }}
                </span>
              </button>
            </div>

            <!-- Type Filter -->
            <div class="flex items-center gap-2">
              <label class="text-sm font-medium text-slate-600">Type</label>

              <select
                v-model="type"
                class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm text-slate-700"
              >
                <option value="all">All</option>
                <option value="ofi">OFI</option>
                <option value="car">CAR</option>
              </select>
            </div>

          </div>
        </div>
      </div>

      <!-- Table -->
      <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="border-b border-slate-200 bg-slate-50">
              <tr class="text-left">
                <th class="px-5 py-3 font-semibold text-slate-700">Type</th>
                <th class="px-5 py-3 font-semibold text-slate-700">Record No.</th>
                <th class="px-5 py-3 font-semibold text-slate-700">Subject</th>
                <th class="px-5 py-3 font-semibold text-slate-700">Workflow</th>
                <th class="px-5 py-3 font-semibold text-slate-700">Resolution</th>
                <th class="px-5 py-3 font-semibold text-slate-700">Date Submitted</th>
                <th class="px-5 py-3 font-semibold text-slate-700">Remarks</th>
                <th class="px-5 py-3 text-right font-semibold text-slate-700">Actions</th>
              </tr>
            </thead>

            <tbody>
              <tr
                v-for="record in records.data"
                :key="record.id"
                class="border-b border-slate-100 hover:bg-slate-50"
              >
                <!-- TYPE -->
                <td class="px-5 py-4">
                  <span
                    class="rounded-full px-2.5 py-1 text-xs font-semibold ring-1"
                    :class="typeBadgeClass(record.type)"
                  >
                    {{ record.type_label }}
                  </span>
                </td>

                <!-- RECORD -->
                <td class="px-5 py-4 font-medium text-slate-900">
                  {{ record.record_no || '—' }}
                </td>

                <!-- SUBJECT -->
                <td class="px-5 py-4 text-slate-600">
                  {{ record.subject || '—' }}
                </td>

                <!-- WORKFLOW -->
                <td class="px-5 py-4">
                  <span
                    class="rounded-full px-2 py-1 text-xs ring-1"
                    :class="workflowBadgeClass(record.workflow_status)"
                  >
                    {{ record.workflow_status || 'draft' }}
                  </span>
                </td>

                <!-- RESOLUTION -->
                <td class="px-5 py-4">
                  <span
                    class="rounded-full px-2 py-1 text-xs ring-1"
                    :class="resolutionBadgeClass(record.resolution_status)"
                  >
                    {{ record.resolution_status || 'open' }}
                  </span>
                </td>

                <!-- DATE -->
                <td class="px-5 py-4 text-slate-600">
                  {{ formatDate(record.created_at) }}
                </td>

                <!-- REMARKS -->
                <td class="px-5 py-4 text-slate-600">
                  {{ record.remarks || '—' }}
                </td>

                <!-- ACTION -->
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

              <tr v-if="!records.data.length">
                <td colspan="8" class="px-5 py-8 text-center text-sm text-slate-500">
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
              :class="link.active
                ? 'border-slate-900 bg-slate-900 text-white'
                : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-100'"
            >
              <span v-html="link.label" />
            </Link>
          </template>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>