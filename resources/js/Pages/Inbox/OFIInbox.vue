<script setup>
import AdminLayoutWithHeader from '@/Layouts/AdminLayout.vue'
import { Link, router } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'

const props = defineProps({
  records: Object,
  filters: Object,
  counts: Object,
})

const workflowStatus = ref(props.filters?.workflow_status ?? 'pending')

watch(workflowStatus, (value) => {
  router.get(route('ofi.inbox'), {
    workflow_status: value,
  }, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  })
})

const tabs = computed(() => [
  { key: 'pending', label: 'Pending', count: props.counts?.pending ?? 0 },
  { key: 'approved', label: 'Approved', count: props.counts?.approved ?? 0 },
  { key: 'rejected', label: 'Rejected', count: props.counts?.rejected ?? 0 },
])

function approve(id) {
  router.post(route('ofi.inbox.approve', id), {}, {
    preserveScroll: true,
  })
}

function reject(id) {
  router.post(route('ofi.inbox.reject', id), {}, {
    preserveScroll: true,
  })
}

function formatDate(value) {
  if (!value) return '—'
  return new Date(value).toLocaleString()
}

function workflowBadgeClass(status) {
  if (status === 'approved') return 'bg-emerald-50 text-emerald-700 ring-emerald-200'
  if (status === 'rejected') return 'bg-rose-50 text-rose-700 ring-rose-200'
  return 'bg-amber-50 text-amber-700 ring-amber-200'
}

function resolutionBadgeClass(status) {
  if (status === 'closed') return 'bg-emerald-50 text-emerald-700 ring-emerald-200'
  if (status === 'ongoing') return 'bg-blue-50 text-blue-700 ring-blue-200'
  return 'bg-slate-100 text-slate-700 ring-slate-200'
}
</script>

<template>
  <AdminLayoutWithHeader>
    <div class="space-y-6 p-6">
      <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="bg-gradient-to-r from-slate-900 to-slate-800 px-6 py-5">
          <h1 class="text-xl font-semibold text-white">OFI Inbox</h1>
          <p class="mt-1 text-sm text-slate-300">
            Review submitted Opportunities for Improvement.
          </p>
        </div>

        <div class="border-t border-slate-200 px-6 py-4">
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
                :class="workflowStatus === tab.key ? 'bg-white/15 text-white' : 'bg-slate-100 text-slate-600'"
              >
                {{ tab.count }}
              </span>
            </button>
          </div>
        </div>
      </div>

      <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead class="border-b border-slate-200 bg-slate-50">
              <tr class="text-left">
                <th class="px-5 py-3 font-semibold text-slate-700">OFI No.</th>
                <th class="px-5 py-3 font-semibold text-slate-700">Ref No.</th>
                <th class="px-5 py-3 font-semibold text-slate-700">To</th>
                <th class="px-5 py-3 font-semibold text-slate-700">Created By</th>
                <th class="px-5 py-3 font-semibold text-slate-700">Workflow</th>
                <th class="px-5 py-3 font-semibold text-slate-700">Resolution</th>
                <th class="px-5 py-3 font-semibold text-slate-700">Date Submitted</th>
                <th class="px-5 py-3 text-right font-semibold text-slate-700">Actions</th>
              </tr>
            </thead>

            <tbody>
              <tr
                v-for="record in records.data"
                :key="record.id"
                class="border-b border-slate-100 hover:bg-slate-50"
              >
                <td class="px-5 py-4 font-medium text-slate-900">{{ record.ofi_no || '—' }}</td>
                <td class="px-5 py-4 text-slate-600">{{ record.ref_no || '—' }}</td>
                <td class="px-5 py-4 text-slate-600">{{ record.to || '—' }}</td>
                <td class="px-5 py-4 text-slate-600">{{ record.created_by_name }}</td>
                <td class="px-5 py-4">
                  <span
                    class="rounded-full px-2 py-1 text-xs ring-1"
                    :class="workflowBadgeClass(record.workflow_status)"
                  >
                    {{ record.workflow_status }}
                  </span>
                </td>
                <td class="px-5 py-4">
                  <span
                    class="rounded-full px-2 py-1 text-xs ring-1"
                    :class="resolutionBadgeClass(record.resolution_status)"
                  >
                    {{ record.resolution_status }}
                  </span>
                </td>
                <td class="px-5 py-4 text-slate-600">{{ formatDate(record.created_at) }}</td>
                <td class="px-5 py-4">
                  <div class="flex justify-end gap-2">
                    <Link
                      :href="`/ofi-form?record=${record.id}`"
                      class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs text-slate-700 hover:bg-slate-50"
                    >
                      View
                    </Link>

                    <template v-if="record.workflow_status === 'pending'">
                      <button
                        type="button"
                        @click="approve(record.id)"
                        class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs text-white hover:bg-emerald-500"
                      >
                        Approve
                      </button>

                      <button
                        type="button"
                        @click="reject(record.id)"
                        class="rounded-lg bg-rose-600 px-3 py-1.5 text-xs text-white hover:bg-rose-500"
                      >
                        Reject
                      </button>
                    </template>
                  </div>
                </td>
              </tr>

              <tr v-if="!records.data.length">
                <td colspan="8" class="px-5 py-8 text-center text-sm text-slate-500">
                  No OFI records found for this status.
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="flex flex-wrap items-center gap-2 border-t border-slate-200 bg-slate-50 px-6 py-4">
          <template v-for="(link, index) in records.links" :key="`${link.label}-${index}`">
            <Link
              v-if="link.url"
              :href="link.url"
              preserve-scroll
              preserve-state
              class="inline-flex items-center rounded-lg border px-3 py-1.5 text-sm transition"
              :class="link.active
                ? 'border-slate-900 bg-slate-900 text-white'
                : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-100'"
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
  </AdminLayoutWithHeader>
</template>