<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { computed, ref, watch, onBeforeUnmount } from 'vue'
import { router, Link, usePage, useForm } from '@inertiajs/vue3'
import { useToast } from '@/Composables/useToast'

const page = usePage()
const toast = useToast()

const props = defineProps({
  documentTypes: {
    type: Array,
    default: () => [],
  },
  seriesOptions: {
    type: Array,
    default: () => [],
  },
  filters: {
    type: Object,
    default: () => ({
      q: '',
      series: '',
      sort: 'code_asc',
      view: 'group',
      mode: '',
      status: 'All',
    }),
  },
})

const q = ref(props.filters.q ?? '')
const series = ref(props.filters.series ?? '')
const sort = ref(props.filters.sort ?? 'code_asc')
const view = ref(props.filters.view ?? 'group')
const status = ref(props.filters.status ?? 'All')
const isFiltering = ref(false)
const isRedirectingPerformance = ref(false)

const createModalOpen = ref(false)
const obsoleteModalOpen = ref(false)
const deleteModalOpen = ref(false)

const selectedRow = ref(null)
const openActionMenuId = ref(null)
const deletingType = ref(false)

const createForm = useForm({
  series_id: '',
  document_no: '',
  title: '',
  storage: '',
  status_note: '',
  requires_revision: false,
})

const obsoleteForm = useForm({
  status_note: '',
})

const mode = computed(() => {
  const url = page.url || ''
  return url.includes('mode=performance') ? 'performance' : ''
})

const showPerformanceTypeDropdown = computed(() => mode.value === 'performance')

const performanceOptions = [
  { label: 'IPCR', value: 'IPCR' },
  { label: 'DPCR', value: 'DPCR' },
  { label: 'UPCR', value: 'UPCR' },
]

const statusOptions = ['All', 'Active', 'Obsolete']

let debounceTimer = null

const selectedSeries = computed(() => {
  return props.seriesOptions.find((option) => String(option.id) === String(createForm.series_id)) || null
})

const fullCodePreview = computed(() => {
  if (!selectedSeries.value?.code_prefix || createForm.document_no === '' || createForm.document_no === null) {
    return '—'
  }

  const parsed = Number(createForm.document_no)

  if (!Number.isInteger(parsed) || parsed < 1 || parsed > 999) {
    return '—'
  }

  return `${selectedSeries.value.code_prefix}-${String(parsed).padStart(3, '0')}`
})

const deleteButtonText = computed(() => {
  if (!deletingType.value) return 'Delete Permanently'

  const count = Number(selectedRow.value?.documents_count || 0)

  if (count > 10) return 'Deleting files...'
  return 'Deleting...'
})

const performanceDocumentMap = computed(() => {
  const map = {}

  for (const row of props.documentTypes || []) {
    const seriesCode = (row?.series?.code_prefix || '').toUpperCase()
    if (['IPCR', 'DPCR', 'UPCR'].includes(seriesCode) && !map[seriesCode]) {
      map[seriesCode] = row
      continue
    }

    const code = (row?.code || '').toUpperCase()
    if (!map.IPCR && code.startsWith('IPCR')) map.IPCR = row
    if (!map.DPCR && code.startsWith('DPCR')) map.DPCR = row
    if (!map.UPCR && code.startsWith('UPCR')) map.UPCR = row
  }

  return map
})

function redirectToPerformanceDocument(selectedCode) {
  const code = String(selectedCode || '').toUpperCase()
  if (!code) return

  const row = performanceDocumentMap.value[code]

  if (!row?.id) {
    toast.error(`No ${code} document type was found. Create the ${code} document type first.`)
    return
  }

  isRedirectingPerformance.value = true

  router.get(`/documents/${row.id}`, {}, {
    preserveScroll: true,
    onFinish: () => {
      isRedirectingPerformance.value = false
    },
  })
}

function applyFilters() {
  if (showPerformanceTypeDropdown.value) {
    if (series.value) {
      redirectToPerformanceDocument(series.value)
      return
    }
  }

  isFiltering.value = true

  const params = {
    q: q.value || undefined,
    sort: sort.value || undefined,
    view: view.value || undefined,
    status: status.value || 'All',
  }

  if (showPerformanceTypeDropdown.value) {
    params.mode = 'performance'
    params.series = undefined
  } else {
    params.series = series.value || undefined
  }

  router.get('/documents', params, {
    preserveState: true,
    replace: true,
    preserveScroll: true,
    onFinish: () => {
      isFiltering.value = false
    },
  })
}

watch(q, () => {
  if (showPerformanceTypeDropdown.value) return

  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(applyFilters, 220)
})

watch([sort, view], () => {
  if (showPerformanceTypeDropdown.value) return
  applyFilters()
})

watch(series, (newValue, oldValue) => {
  if (showPerformanceTypeDropdown.value) {
    if (!newValue) return
    if (newValue === oldValue) return
    redirectToPerformanceDocument(newValue)
    return
  }

  applyFilters()
})

watch(status, () => {
  if (showPerformanceTypeDropdown.value) return
  applyFilters()
})

watch(
  () => props.filters,
  (newFilters) => {
    q.value = newFilters?.q ?? ''
    series.value = newFilters?.series ?? ''
    sort.value = newFilters?.sort ?? 'code_asc'
    view.value = newFilters?.view ?? 'group'
    status.value = newFilters?.status ?? 'All'
  },
  { immediate: true, deep: true }
)

watch(
  () => page.url,
  (url) => {
    const isPerformance = (url || '').includes('mode=performance')

    if (isPerformance && !['IPCR', 'DPCR', 'UPCR'].includes(series.value)) {
      series.value = ''
    }
  },
  { immediate: true }
)

const normalized = computed(() => {
  let rows = [...props.documentTypes]

  const byCode = (a, b) => (a.code || '').localeCompare(b.code || '')
  const byName = (a, b) => (a.name || '').localeCompare(b.name || '')
  const byUploads = (a, b) => (b.documents_count || 0) - (a.documents_count || 0)
  const byLatest = (a, b) => {
    const da = a.latest_upload_at ? new Date(a.latest_upload_at).getTime() : 0
    const db = b.latest_upload_at ? new Date(b.latest_upload_at).getTime() : 0
    return db - da
  }

  if (sort.value === 'code_asc') rows.sort(byCode)
  if (sort.value === 'name_asc') rows.sort(byName)
  if (sort.value === 'uploads_desc') rows.sort(byUploads)
  if (sort.value === 'latest_desc') rows.sort(byLatest)

  return rows
})

const grouped = computed(() => {
  if (!showPerformanceTypeDropdown.value && series.value === 'R-QMS') {
    const groups = {
      'R-QMS-001 to R-QMS-061': [],
      'R-QMS-100 to R-QMS-112': [],
      Other: [],
    }

    for (const r of normalized.value) {
      const m = (r.code || '').match(/R-QMS-(\d+)/i)
      const n = m ? parseInt(m[1], 10) : null

      if (n !== null && n >= 1 && n <= 61) groups['R-QMS-001 to R-QMS-061'].push(r)
      else if (n !== null && n >= 100 && n <= 112) groups['R-QMS-100 to R-QMS-112'].push(r)
      else groups.Other.push(r)
    }

    return groups
  }

  return { 'All Items': normalized.value }
})

function uploadCountClass(count) {
  if (!count) return 'bg-rose-50 text-rose-700 ring-rose-200'
  return 'bg-emerald-50 text-emerald-700 ring-emerald-200'
}

function typeStatusClass(rowStatus) {
  if ((rowStatus || '').toLowerCase() === 'obsolete') {
    return 'bg-amber-50 text-amber-700 ring-amber-200'
  }

  return 'bg-emerald-50 text-emerald-700 ring-emerald-200'
}

function formatDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString()
}

function resetCreateForm() {
  createForm.reset()
  createForm.clearErrors()
  createForm.series_id = ''
  createForm.document_no = ''
  createForm.title = ''
  createForm.storage = ''
  createForm.status_note = ''
  createForm.requires_revision = false
}

function openCreateModal() {
  resetCreateForm()
  createModalOpen.value = true
}

function submitCreate() {
  createForm.post('/documents/types', {
    preserveScroll: true,
    onSuccess: () => {
      const code = fullCodePreview.value !== '—' ? fullCodePreview.value : 'Document type'
      createModalOpen.value = false
      resetCreateForm()
      toast.success(`${code} created successfully.`)
    },
    onError: (errors) => {
      if (errors.document_no) {
        if (errors.document_no.toLowerCase().includes('already exists')) {
          toast.error(`Cannot create document type. ${fullCodePreview.value} already exists.`)
          return
        }

        toast.error(errors.document_no)
        return
      }

      if (errors.series_id) {
        toast.error(errors.series_id)
        return
      }

      if (errors.title) {
        toast.error(errors.title)
        return
      }

      if (errors.storage) {
        toast.error(errors.storage)
        return
      }

      toast.error('Failed to create document type. Please try again.')
    },
  })
}

function openObsoleteModal(row) {
  closeActionMenu()
  selectedRow.value = row
  obsoleteForm.reset()
  obsoleteForm.clearErrors()
  obsoleteForm.status_note = row.status_note || ''
  obsoleteModalOpen.value = true
}

function submitObsolete() {
  if (!selectedRow.value) return

  obsoleteForm.patch(`/documents/types/${selectedRow.value.id}/obsolete`, {
    preserveScroll: true,
    onSuccess: () => {
      toast.success(`${selectedRow.value.code} marked as obsolete.`)
      obsoleteModalOpen.value = false
      selectedRow.value = null
      obsoleteForm.reset()
      obsoleteForm.clearErrors()
    },
    onError: () => {
      toast.error('Failed to mark document type as obsolete.')
    },
  })
}

function openDeleteModal(row) {
  closeActionMenu()
  selectedRow.value = row
  deleteModalOpen.value = true
}

function closeDeleteModal() {
  if (deletingType.value) return
  deleteModalOpen.value = false
}

function submitDelete() {
  if (!selectedRow.value || deletingType.value) return

  const row = selectedRow.value
  deletingType.value = true

  if ((row.documents_count || 0) > 10) {
    toast.info(`Deleting ${row.code}. This may take a moment because it has many files.`)
  }

  router.delete(`/documents/types/${row.id}`, {
    preserveScroll: true,
    onSuccess: () => {
      toast.success(`${row.code} deleted successfully.`)
      deleteModalOpen.value = false
      selectedRow.value = null
    },
    onError: (errors) => {
      const firstError =
        errors?.delete ||
        errors?.document_type ||
        errors?.message ||
        Object.values(errors || {})[0] ||
        'Failed to delete document type. Please try again.'

      toast.error(Array.isArray(firstError) ? firstError[0] : firstError)
    },
    onFinish: () => {
      deletingType.value = false
    },
  })
}

function toggleActionMenu(rowId) {
  openActionMenuId.value = openActionMenuId.value === rowId ? null : rowId
}

function closeActionMenu() {
  openActionMenuId.value = null
}

function handleGlobalClick(event) {
  const target = event.target
  if (!(target instanceof Element)) return

  if (!target.closest('[data-action-menu-root]')) {
    closeActionMenu()
  }
}

watch([createModalOpen, obsoleteModalOpen, deleteModalOpen], () => {
  if (createModalOpen.value || obsoleteModalOpen.value || deleteModalOpen.value) {
    closeActionMenu()
  }
})

if (typeof window !== 'undefined') {
  window.addEventListener('click', handleGlobalClick)
}

onBeforeUnmount(() => {
  if (typeof window !== 'undefined') {
    window.removeEventListener('click', handleGlobalClick)
  }
})
</script>

<template>
  <AdminLayout>
    <div class="space-y-6 p-6">
      <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="bg-gradient-to-r from-slate-900 to-slate-800 px-4 py-2">
          <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <h1 class="text-base font-semibold text-white sm:text-xl">Documents</h1>
              <p class="mt-1 text-sm text-slate-300">
                Find documents by code or name. Admins can create, obsolete, and permanently delete document types here.
              </p>
            </div>

            <button
              type="button"
              @click="openCreateModal"
              class="inline-flex items-center justify-center rounded-xl bg-white px-4 py-2 text-sm font-medium text-slate-900 transition hover:bg-slate-100"
            >
              + New Document Type
            </button>
          </div>
        </div>

        <div class="px-4 py-4">
          <div class="grid grid-cols-1 gap-3 md:grid-cols-12">
            <div :class="showPerformanceTypeDropdown ? 'md:col-span-5' : 'md:col-span-6'">
              <label class="text-xs font-medium text-slate-600">Search</label>
              <div class="relative mt-1">
                <input
                  v-model="q"
                  type="text"
                  placeholder="Search: R-QMS-001, Filing Chart, OFI, DCR..."
                  :disabled="showPerformanceTypeDropdown"
                  class="w-full rounded-xl border border-slate-200 px-4 py-1.5 pr-10 transition focus:outline-none focus:ring-2 focus:ring-slate-300 disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-400"
                />
                <div class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400">
                  <svg
                    v-if="!isFiltering && !isRedirectingPerformance"
                    xmlns="http://www.w3.org/2000/svg"
                    class="h-5 w-5 transition"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M21 21l-4.35-4.35m0 0A7.5 7.5 0 104.5 4.5a7.5 7.5 0 0012.15 12.15z"
                    />
                  </svg>

                  <svg
                    v-else
                    xmlns="http://www.w3.org/2000/svg"
                    class="h-5 w-5 animate-spin"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M12 4v2m0 12v2m8-8h-2M6 12H4m14.364-6.364l-1.414 1.414M7.05 16.95l-1.414 1.414m0-12.728L7.05 7.05m10.9 9.9l1.414 1.414"
                    />
                  </svg>
                </div>
              </div>
            </div>

            <div v-if="showPerformanceTypeDropdown" class="md:col-span-2">
              <label class="text-xs font-medium text-slate-600">File Type</label>
              <select
                v-model="series"
                class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-1.5 transition focus:outline-none focus:ring-2 focus:ring-slate-300"
              >
                <option value="">Select</option>
                <option
                  v-for="option in performanceOptions"
                  :key="option.value"
                  :value="option.value"
                >
                  {{ option.label }}
                </option>
              </select>
            </div>

            <div class="md:col-span-3">
              <label class="text-xs font-medium text-slate-600">Document Type Status</label>
              <div class="mt-1 flex rounded-xl border border-slate-200 bg-slate-50 p-1">
                <button
                  v-for="option in statusOptions"
                  :key="option"
                  type="button"
                  @click="status = option"
                  :disabled="showPerformanceTypeDropdown"
                  class="flex-1 rounded-lg px-3 py-1.5 text-sm transition disabled:cursor-not-allowed disabled:opacity-50"
                  :class="
                    status === option
                      ? 'bg-white text-slate-900 shadow-sm'
                      : 'text-slate-600 hover:text-slate-900'
                  "
                >
                  {{ option }}
                </button>
              </div>
            </div>

            <div :class="showPerformanceTypeDropdown ? 'md:col-span-2' : 'md:col-span-3'">
              <label class="text-xs font-medium text-slate-600">Sort</label>
              <select
                v-model="sort"
                :disabled="showPerformanceTypeDropdown"
                class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-1.5 transition focus:outline-none focus:ring-2 focus:ring-slate-300 disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-400"
              >
                <option value="code_asc">Code (A–Z)</option>
                <option value="name_asc">Name (A–Z)</option>
                <option value="uploads_desc">Most uploads</option>
                <option value="latest_desc">Latest updated</option>
              </select>
            </div>
          </div>

          <div class="mt-4 flex items-center justify-between">
            <div class="text-xs text-slate-500">
              <template v-if="showPerformanceTypeDropdown">
                Select IPCR, DPCR, or UPCR to open it directly.
              </template>
              <template v-else>
                Showing
                <span class="font-semibold text-slate-700">{{ normalized.length }}</span>
                document type(s)
              </template>
            </div>

            <div class="inline-flex rounded-xl border border-slate-200 bg-slate-50 p-1">
              <button
                type="button"
                @click="view = 'group'"
                class="rounded-lg px-3 py-1.5 text-sm transition-all duration-200"
                :class="view === 'group'
                  ? 'bg-white text-slate-900 shadow-sm'
                  : 'text-slate-600 hover:text-slate-900'"
              >
                Browse
              </button>

              <button
                type="button"
                @click="view = 'table'"
                class="rounded-lg px-3 py-1.5 text-sm transition-all duration-200"
                :class="view === 'table'
                  ? 'bg-white text-slate-900 shadow-sm'
                  : 'text-slate-600 hover:text-slate-900'"
              >
                Table
              </button>
            </div>
          </div>
        </div>
      </div>

      <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0 translate-y-1"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100 translate-y-0"
        leave-to-class="opacity-0 -translate-y-1"
      >
        <div
          v-if="showPerformanceTypeDropdown"
          class="rounded-2xl border border-slate-200 bg-white p-10 text-center"
        >
          <div class="font-semibold text-slate-900">
            <template v-if="isRedirectingPerformance">
              Opening selected performance form...
            </template>
            <template v-else>
              Select a performance form
            </template>
          </div>

          <div class="mt-1 text-sm text-slate-600">
            <template v-if="isRedirectingPerformance">
              Redirecting to the upload page.
            </template>
            <template v-else>
              Choose IPCR, DPCR, or UPCR from the dropdown above.
            </template>
          </div>
        </div>

        <div
          v-else-if="!normalized.length"
          class="rounded-2xl border border-slate-200 bg-white p-10 text-center"
        >
          <div class="font-semibold text-slate-900">No results</div>
          <div class="mt-1 text-sm text-slate-600">Try a different keyword or reset filters.</div>
        </div>
      </Transition>

      <Transition
        mode="out-in"
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0 translate-y-1"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100 translate-y-0"
        leave-to-class="opacity-0 -translate-y-1"
      >
        <div
          v-if="!showPerformanceTypeDropdown && view === 'group' && normalized.length"
          key="group-view"
          class="space-y-6"
        >
          <div
            v-for="(items, groupName) in grouped"
            :key="groupName"
            v-show="items.length"
          >
            <div class="mb-3 flex items-center justify-between">
              <h2 class="text-sm font-semibold text-slate-800">{{ groupName }}</h2>
              <span class="text-xs text-slate-500">{{ items.length }} items</span>
            </div>

            <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
              <div
                v-for="row in items"
                :key="row.id"
                class="rounded-2xl border border-slate-200 bg-white p-4 transition-all duration-200 hover:-translate-y-[1px] hover:border-slate-300 hover:shadow-sm"
              >
                <div class="flex items-start justify-between gap-3">
                  <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                      <span class="rounded-md bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-900">
                        {{ row.code }}
                      </span>

                      <span
                        class="rounded-full px-2 py-1 text-xs ring-1"
                        :class="typeStatusClass(row.status)"
                      >
                        {{ row.status || 'Active' }}
                      </span>
                    </div>

                    <div class="mt-2 truncate font-semibold text-slate-900">
                      {{ row.name }}
                    </div>

                    <div class="mt-1 text-xs text-slate-500">
                      Latest:
                      <span class="text-slate-700">{{ formatDate(row.latest_upload_at) }}</span>
                    </div>

                    <div v-if="row.status_note" class="mt-1 line-clamp-2 text-xs text-slate-500">
                      Note: {{ row.status_note }}
                    </div>
                  </div>

                  <span
                    class="whitespace-nowrap rounded-full px-2 py-1 text-xs ring-1 transition"
                    :class="uploadCountClass(row.documents_count)"
                  >
                    {{ row.documents_count || 0 }} upload(s)
                  </span>
                </div>

                <div class="mt-4 flex items-center gap-2">
                  <Link
                    :href="`/documents/${row.id}`"
                    class="flex-1 rounded-xl bg-slate-900 px-3 py-2 text-center text-sm text-white transition hover:bg-slate-800"
                  >
                    Open
                  </Link>

                  <div class="relative" data-action-menu-root>
                    <button
                      type="button"
                      @click.stop="toggleActionMenu(row.id)"
                      class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-50 hover:text-slate-900"
                      title="Actions"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M10 3a1.75 1.75 0 110 3.5A1.75 1.75 0 0110 3zm0 5.25a1.75 1.75 0 110 3.5 1.75 1.75 0 010-3.5zm0 5.25a1.75 1.75 0 110 3.5A1.75 1.75 0 0110 13.5z" />
                      </svg>
                    </button>

                    <div
                      v-if="openActionMenuId === row.id"
                      class="absolute right-0 z-30 mt-2 w-52 overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-lg"
                    >
                      <Link
                        :href="`/documents/${row.id}`"
                        class="block px-4 py-2 text-sm text-slate-700 transition hover:bg-slate-50"
                        @click="closeActionMenu"
                      >
                        Open
                      </Link>

                      <button
                        v-if="(row.status || '').toLowerCase() !== 'obsolete'"
                        type="button"
                        class="block w-full px-4 py-2 text-left text-sm text-amber-700 transition hover:bg-amber-50"
                        @click="openObsoleteModal(row)"
                      >
                        Mark as Obsolete
                      </button>

                      <button
                        type="button"
                        class="block w-full px-4 py-2 text-left text-sm text-rose-700 transition hover:bg-rose-50"
                        @click="openDeleteModal(row)"
                      >
                        Delete Permanently
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div
          v-else-if="!showPerformanceTypeDropdown && view === 'table' && normalized.length"
          key="table-view"
          class="overflow-hidden rounded-2xl border border-slate-200 bg-white"
        >
          <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
              <thead class="border-b border-slate-200 bg-slate-50">
                <tr class="text-left">
                  <th class="px-4 py-3 font-semibold text-slate-700">Code</th>
                  <th class="px-4 py-3 font-semibold text-slate-700">File Name</th>
                  <th class="px-4 py-3 font-semibold text-slate-700">Status</th>
                  <th class="px-4 py-3 font-semibold text-slate-700">Uploads</th>
                  <th class="px-4 py-3 font-semibold text-slate-700">Latest</th>
                  <th class="px-4 py-3 text-right font-semibold text-slate-700">Action</th>
                </tr>
              </thead>

              <tbody>
                <tr
                  v-for="row in normalized"
                  :key="row.id"
                  class="border-b border-slate-100 transition-colors duration-200 hover:bg-slate-50"
                >
                  <td class="whitespace-nowrap px-4 py-3">
                    <span class="rounded-md bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-900">
                      {{ row.code }}
                    </span>
                  </td>

                  <td class="px-4 py-3 font-medium text-slate-900">
                    <div>{{ row.name }}</div>
                    <div v-if="row.status_note" class="mt-1 text-xs text-slate-500">
                      {{ row.status_note }}
                    </div>
                  </td>

                  <td class="px-4 py-3">
                    <span class="rounded-full px-2 py-1 text-xs ring-1" :class="typeStatusClass(row.status)">
                      {{ row.status || 'Active' }}
                    </span>
                  </td>

                  <td class="px-4 py-3">
                    <span class="rounded-full px-2 py-1 text-xs ring-1" :class="uploadCountClass(row.documents_count)">
                      {{ row.documents_count || 0 }}
                    </span>
                  </td>

                  <td class="px-4 py-3 text-slate-700">
                    {{ formatDate(row.latest_upload_at) }}
                  </td>

                  <td class="px-4 py-3 text-right">
                    <div class="inline-flex items-center gap-2">
                      <Link
                        :href="`/documents/${row.id}`"
                        class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-3 py-2 text-xs text-white transition hover:bg-slate-800"
                      >
                        Open
                      </Link>

                      <div class="relative" data-action-menu-root>
                        <button
                          type="button"
                          @click.stop="toggleActionMenu(row.id)"
                          class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-50 hover:text-slate-900"
                          title="Actions"
                        >
                          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M10 3a1.75 1.75 0 110 3.5A1.75 1.75 0 0110 3zm0 5.25a1.75 1.75 0 110 3.5 1.75 1.75 0 010-3.5zm0 5.25a1.75 1.75 0 110 3.5A1.75 1.75 0 0110 13.5z" />
                          </svg>
                        </button>

                        <div
                          v-if="openActionMenuId === row.id"
                          class="absolute right-0 z-30 mt-2 w-52 overflow-hidden rounded-xl border border-slate-200 bg-white py-1 text-left shadow-lg"
                        >
                          <Link
                            :href="`/documents/${row.id}`"
                            class="block px-4 py-2 text-sm text-slate-700 transition hover:bg-slate-50"
                            @click="closeActionMenu"
                          >
                            Open
                          </Link>

                          <button
                            v-if="(row.status || '').toLowerCase() !== 'obsolete'"
                            type="button"
                            class="block w-full px-4 py-2 text-left text-sm text-amber-700 transition hover:bg-amber-50"
                            @click="openObsoleteModal(row)"
                          >
                            Mark as Obsolete
                          </button>

                          <button
                            type="button"
                            class="block w-full px-4 py-2 text-left text-sm text-rose-700 transition hover:bg-rose-50"
                            @click="openDeleteModal(row)"
                          >
                            Delete Permanently
                          </button>
                        </div>
                      </div>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="px-4 py-3 text-xs text-slate-500">
            Tip: Search “OFI” or “DCR” to jump directly to those document types.
          </div>
        </div>
      </Transition>

      <div
        v-if="createModalOpen"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4"
      >
        <div class="flex max-h-[90vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl bg-white shadow-xl">
          <div class="shrink-0 border-b border-slate-200 px-6 py-4">
            <h2 class="text-lg font-semibold text-slate-900">Create Document Type</h2>
            <p class="mt-1 text-sm text-slate-500">
              Add a new document type using a structured document code.
            </p>
          </div>

          <div class="flex-1 overflow-y-auto px-6 py-5">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
              <div>
                <label class="text-sm font-medium text-slate-700">Series</label>
                <select
                  v-model="createForm.series_id"
                  class="mt-1 w-full rounded-xl border px-3 py-2 focus:outline-none focus:ring-2 focus:ring-slate-300"
                  :class="createForm.errors.series_id ? 'border-rose-300' : 'border-slate-200'"
                >
                  <option value="">Select series</option>
                  <option
                    v-for="option in seriesOptions"
                    :key="option.id"
                    :value="option.id"
                  >
                    {{ option.code_prefix }} — {{ option.name }}
                  </option>
                </select>
                <p v-if="createForm.errors.series_id" class="mt-1 text-xs text-rose-600">
                  {{ createForm.errors.series_id }}
                </p>
              </div>

              <div>
                <label class="text-sm font-medium text-slate-700">Document Number</label>
                <input
                  v-model="createForm.document_no"
                  type="number"
                  min="1"
                  max="999"
                  placeholder="e.g. 234"
                  class="mt-1 w-full rounded-xl border px-3 py-2 focus:outline-none focus:ring-2 focus:ring-slate-300"
                  :class="createForm.errors.document_no ? 'border-rose-300' : 'border-slate-200'"
                />
                <p class="mt-1 text-xs text-slate-500">The system will generate a zero-padded 3-digit code.</p>
                <p v-if="createForm.errors.document_no" class="mt-1 text-xs text-rose-600">
                  {{ createForm.errors.document_no }}
                </p>
              </div>

              <div class="md:col-span-2">
                <label class="text-sm font-medium text-slate-700">Full Code Preview</label>
                <div class="mt-1 rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-800">
                  {{ fullCodePreview }}
                </div>
              </div>

              <div class="md:col-span-2">
                <label class="text-sm font-medium text-slate-700">Title</label>
                <input
                  v-model="createForm.title"
                  type="text"
                  placeholder="Document title"
                  class="mt-1 w-full rounded-xl border px-3 py-2 focus:outline-none focus:ring-2 focus:ring-slate-300"
                  :class="createForm.errors.title ? 'border-rose-300' : 'border-slate-200'"
                />
                <p v-if="createForm.errors.title" class="mt-1 text-xs text-rose-600">
                  {{ createForm.errors.title }}
                </p>
              </div>

              <div class="md:col-span-2">
                <label class="text-sm font-medium text-slate-700">Storage / File Type</label>
                <input
                  v-model="createForm.storage"
                  type="text"
                  placeholder="e.g. Physical, Electronic / PDF / DOCX / Folder / Mixed"
                  class="mt-1 w-full rounded-xl border px-3 py-2 focus:outline-none focus:ring-2 focus:ring-slate-300"
                  :class="createForm.errors.storage ? 'border-rose-300' : 'border-slate-200'"
                />
                <p v-if="createForm.errors.storage" class="mt-1 text-xs text-rose-600">
                  {{ createForm.errors.storage }}
                </p>
              </div>

              <div class="md:col-span-2">
                <label class="inline-flex items-center gap-2 text-sm font-medium text-slate-700">
                  <input
                    v-model="createForm.requires_revision"
                    type="checkbox"
                    class="rounded border-slate-300 text-slate-900 focus:ring-slate-400"
                  />
                  Requires revision control
                </label>
              </div>

              <div class="md:col-span-2">
                <label class="text-sm font-medium text-slate-700">Status Note (optional)</label>
                <textarea
                  v-model="createForm.status_note"
                  rows="3"
                  class="mt-1 w-full rounded-xl border px-3 py-2 focus:outline-none focus:ring-2 focus:ring-slate-300"
                  :class="createForm.errors.status_note ? 'border-rose-300' : 'border-slate-200'"
                  placeholder="Optional note"
                />
                <p v-if="createForm.errors.status_note" class="mt-1 text-xs text-rose-600">
                  {{ createForm.errors.status_note }}
                </p>
              </div>
            </div>
          </div>

          <div class="shrink-0 flex justify-end gap-2 border-t border-slate-200 bg-white px-6 py-4">
            <button
              type="button"
              @click="createModalOpen = false"
              class="rounded-xl border border-slate-200 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50"
            >
              Cancel
            </button>
            <button
              type="button"
              @click="submitCreate"
              :disabled="createForm.processing"
              class="rounded-xl bg-slate-900 px-4 py-2 text-sm text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
            >
              {{ createForm.processing ? 'Creating...' : 'Create' }}
            </button>
          </div>
        </div>
      </div>

      <div
        v-if="obsoleteModalOpen"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4"
      >
        <div class="w-full max-w-xl rounded-2xl bg-white shadow-xl">
          <div class="border-b border-slate-200 px-6 py-4">
            <h2 class="text-lg font-semibold text-slate-900">Mark as Obsolete</h2>
            <p class="mt-1 text-sm text-slate-500">
              This document type stays in the system for history, but it can no longer receive new uploads.
            </p>
          </div>

          <div class="px-6 py-5">
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
              <div class="font-semibold">{{ selectedRow?.code }} — {{ selectedRow?.name }}</div>
              <div class="mt-1">Current uploads: {{ selectedRow?.documents_count || 0 }}</div>
            </div>

            <div class="mt-4">
              <label class="text-sm font-medium text-slate-700">Reason / Note</label>
              <textarea
                v-model="obsoleteForm.status_note"
                rows="4"
                class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-slate-300"
                placeholder="Why is this document type now obsolete?"
              />
              <p v-if="obsoleteForm.errors.status_note" class="mt-1 text-xs text-rose-600">
                {{ obsoleteForm.errors.status_note }}
              </p>
            </div>
          </div>

          <div class="flex justify-end gap-2 border-t border-slate-200 px-6 py-4">
            <button
              type="button"
              @click="obsoleteModalOpen = false"
              class="rounded-xl border border-slate-200 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50"
            >
              Cancel
            </button>
            <button
              type="button"
              @click="submitObsolete"
              :disabled="obsoleteForm.processing"
              class="rounded-xl bg-amber-600 px-4 py-2 text-sm text-white hover:bg-amber-700 disabled:cursor-not-allowed disabled:opacity-60"
            >
              {{ obsoleteForm.processing ? 'Saving...' : 'Confirm Obsolete' }}
            </button>
          </div>
        </div>
      </div>

      <div
        v-if="deleteModalOpen"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4"
      >
        <div class="w-full max-w-xl rounded-2xl bg-white shadow-xl">
          <div class="border-b border-slate-200 px-6 py-4">
            <h2 class="text-lg font-semibold text-rose-700">Delete Permanently</h2>
            <p class="mt-1 text-sm text-slate-500">This action cannot be undone.</p>
          </div>

          <div class="px-6 py-5">
            <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
              <div class="font-semibold">{{ selectedRow?.code }} — {{ selectedRow?.name }}</div>
              <div class="mt-2">
                This will permanently remove the document type and related stored content.
              </div>
              <div v-if="(selectedRow?.documents_count || 0) > 0" class="mt-3 font-semibold">
                Warning: this document type has {{ selectedRow?.documents_count }} upload(s). Their stored files will also be deleted.
              </div>
            </div>
          </div>

          <div class="flex justify-end gap-2 border-t border-slate-200 px-6 py-4">
            <button
              type="button"
              @click="closeDeleteModal"
              :disabled="deletingType"
              class="rounded-xl border border-slate-200 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
            >
              Cancel
            </button>
            <button
              type="button"
              @click="submitDelete"
              :disabled="deletingType"
              class="rounded-xl bg-rose-600 px-4 py-2 text-sm text-white hover:bg-rose-700 disabled:cursor-not-allowed disabled:opacity-60"
            >
              {{ deleteButtonText }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>