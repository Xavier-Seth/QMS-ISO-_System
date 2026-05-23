<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { Link, router } from '@inertiajs/vue3'
import { ref, watch } from 'vue'

const props = defineProps({
  records: {
    type: Object,
    required: true,
  },
  filters: {
    type: Object,
    required: true,
  },
  pendingCounts: {
    type: Object,
    default: () => ({ ofi: 0, car: 0, dcr: 0 }),
  },
})

/* ---------------------------
   Tab + workflow filter state
--------------------------- */
const activeTab = ref(props.filters?.tab ?? 'ofi')
const workflowStatus = ref(props.filters?.workflow_status ?? 'pending')
const q = ref(props.filters?.q ?? '')

let debounceTimer = null

watch(q, () => {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(applyFilters, 400)
})

const typeTabs = [
  { key: 'ofi', label: 'OFI' },
  { key: 'car', label: 'CAR' },
  { key: 'dcr', label: 'DCR' },
]

const workflowOptions = [
  { key: 'pending', label: 'Pending' },
  { key: 'approved', label: 'Approved' },
  { key: 'rejected', label: 'Returned' },
]

function switchTab(tabKey) {
  activeTab.value = tabKey
  q.value = ''
  applyFilters()
}

function setWorkflow(key) {
  workflowStatus.value = key
  applyFilters()
}

function applyFilters() {
  router.get(
    '/inbox',
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
   Reject Modal
--------------------------- */
const showRejectModal = ref(false)
const rejectRecord = ref(null)
const rejectReason = ref('')

function openRejectModal(record) {
  rejectRecord.value = record
  rejectReason.value = ''
  showRejectModal.value = true
}

function closeRejectModal() {
  showRejectModal.value = false
  rejectRecord.value = null
  rejectReason.value = ''
}

function submitReject() {
  if (!rejectRecord.value) return

  if (!rejectReason.value.trim()) {
    alert('Rejection reason is required.')
    return
  }

  router.post(
    rejectRecord.value.reject_url,
    { rejection_reason: rejectReason.value.trim() },
    {
      preserveScroll: true,
      onSuccess: () => closeRejectModal(),
      onError: (errors) => alert(errors.rejection_reason || 'Failed to reject record.'),
    }
  )
}

/* ---------------------------
   Actions
--------------------------- */
function approveRecord(record) {
  if (record.workflow_status !== 'pending') return
  router.post(record.approve_url, {}, { preserveScroll: true })
}

/* ---------------------------
   Helpers
--------------------------- */
function workflowBadgeClass(status) {
  if (status === 'approved') return 'bg-emerald-50 text-emerald-700 ring-emerald-200'
  if (status === 'rejected') return 'bg-rose-50 text-rose-700 ring-rose-200'
  return 'bg-amber-50 text-amber-700 ring-amber-200'
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
    <!-- Reject Modal -->
    <div
      v-if="showRejectModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4"
      @click.self="closeRejectModal"
    >
      <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl">
        <h3 class="text-lg font-semibold text-slate-900">
          Return {{ rejectRecord?.type_label }} Record for Revision
        </h3>

        <p class="mt-1 text-sm text-slate-500">
          Please provide a reason for returning this record for revision.
        </p>

        <textarea
          v-model="rejectReason"
          rows="4"
          class="mt-4 w-full rounded-xl border border-slate-200 px-4 py-2 text-sm outline-none focus:ring-2 focus:ring-slate-200"
          placeholder="Describe what needs to be corrected before resubmitting..."
        ></textarea>

        <div class="mt-6 flex justify-end gap-2">
          <button
            type="button"
            class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
            @click="closeRejectModal"
          >
            Cancel
          </button>

          <button
            type="button"
            class="rounded-xl bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700"
            @click="submitReject"
          >
            Return for Revision
          </button>
        </div>
      </div>
    </div>

    <div class="w-full space-y-6 p-6">
      <!-- Header -->
      <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="bg-gradient-to-r from-slate-900 to-slate-800 px-6 py-5">
          <h1 class="text-xl font-semibold text-white">Admin Inbox</h1>
          <p class="mt-1 text-sm text-slate-300">
            Review submitted OFI, CAR, and DCR records from departments and users.
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
              v-if="pendingCounts[tab.key] > 0"
              class="absolute right-2 top-2 h-2 w-2 rounded-full bg-rose-500"
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
            class="w-full max-w-sm rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-300"
          />
        </div>
      </div>

      <!-- Table -->
      <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="border-b border-slate-200 bg-slate-50">
              <tr class="text-left">
                <th class="whitespace-nowrap px-5 py-3 font-semibold text-slate-700">Record No.</th>
                <th class="px-5 py-3 font-semibold text-slate-700">Subject / Target</th>
                <th class="px-5 py-3 font-semibold text-slate-700">Submitted By</th>
                <th class="whitespace-nowrap px-5 py-3 font-semibold text-slate-700">Department</th>
                <th class="whitespace-nowrap px-5 py-3 font-semibold text-slate-700">Workflow</th>
                <th class="whitespace-nowrap px-5 py-3 font-semibold text-slate-700">Resolution</th>
                <th class="whitespace-nowrap px-5 py-3 font-semibold text-slate-700">Date Submitted</th>
                <th class="whitespace-nowrap px-5 py-3 text-right font-semibold text-slate-700">Actions</th>
              </tr>
            </thead>

            <tbody>
              <tr
                v-for="record in records.data"
                :key="`${record.type}-${record.id}`"
                class="border-b border-slate-100 hover:bg-slate-50"
              >
                <td class="whitespace-nowrap px-5 py-4 font-medium text-slate-900">
                  {{ record.record_no || '—' }}
                </td>

                <td class="px-5 py-4 text-slate-600">
                  <div class="max-w-[140px] truncate md:max-w-[220px]" :title="record.subject || '—'">
                    {{ record.subject || '—' }}
                  </div>
                </td>

                <td class="px-5 py-4 text-slate-600">
                  <div class="max-w-[100px] leading-5 md:max-w-[170px]">
                    {{ record.submitted_by || '—' }}
                  </div>
                </td>

                <td class="whitespace-nowrap px-5 py-4 text-slate-600">
                  {{ record.department || '—' }}
                </td>

                <td class="whitespace-nowrap px-5 py-4">
                  <span
                    class="rounded-full px-2 py-1 text-xs ring-1"
                    :class="workflowBadgeClass(record.workflow_status)"
                  >
                    {{ record.workflow_status }}
                  </span>
                </td>

                <td class="whitespace-nowrap px-5 py-4">
                  <span
                    class="rounded-full px-2 py-1 text-xs ring-1"
                    :class="resolutionBadgeClass(record.resolution_status)"
                  >
                    {{ record.resolution_status || '—' }}
                  </span>
                </td>

                <td class="whitespace-nowrap px-5 py-4 text-slate-600">
                  {{ record.date_submitted || '—' }}
                </td>

                <td class="px-5 py-4">
                  <div class="flex justify-end gap-2 whitespace-nowrap">
                    <Link
                      :href="record.view_url"
                      class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs text-slate-700 hover:bg-slate-50"
                    >
                      View
                    </Link>

                    <template v-if="record.workflow_status === 'pending'">
                      <button
                        type="button"
                        class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs text-white hover:bg-emerald-500"
                        @click="approveRecord(record)"
                      >
                        Approve
                      </button>

                      <button
                        type="button"
                        class="rounded-lg bg-amber-600 px-3 py-1.5 text-xs text-white hover:bg-amber-500"
                        @click="openRejectModal(record)"
                      >
                        Return
                      </button>
                    </template>
                  </div>
                </td>
              </tr>

              <tr v-if="!records.data.length">
                <td colspan="8" class="px-5 py-8 text-center text-sm text-slate-500">
                  No records found for this filter.
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div class="flex flex-wrap items-center gap-2 border-t border-slate-200 bg-slate-50 px-6 py-4">
          <template v-for="(link, index) in records.links" :key="`${link.label}-${index}`">
            <Link
              v-if="link.url"
              :href="link.url"
              preserve-scroll
              preserve-state
              class="inline-flex items-center rounded-lg border px-3 py-1.5 text-sm transition"
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
