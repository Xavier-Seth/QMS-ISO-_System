<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { Link, router } from '@inertiajs/vue3'
import { ref, computed, watch } from 'vue'
import { useLoadingOverlay } from '@/Composables/useLoadingOverlay'
import { useToast } from '@/Composables/useToast'

const props = defineProps({
  documentType: Object,
  documents: Object,
  filters: Object,
  stats: Object,
  performanceView: {
    type: Object,
    default: () => ({
      enabled: false,
      record_type_groups: [],
      selected_record_type: null,
      selected_year: null,
      selected_period: null,
      selected_period_name: null,
      selected_files: [],
    }),
  },
})

const loading = useLoadingOverlay()
const toast = useToast()

const requiresRevision = computed(() => {
  if (props.documentType && typeof props.documentType.requires_revision === 'boolean') {
    return props.documentType.requires_revision
  }

  const code = (props.documentType?.code || '').toUpperCase()
  return code.startsWith('F-QMS')
})

const isPerformanceForm = computed(() => {
  if (typeof props.documentType?.is_performance_form === 'boolean') {
    return props.documentType.is_performance_form
  }

  const seriesCode = (props.documentType?.series?.code_prefix || '').toUpperCase()
  return ['IPCR', 'DPCR', 'UPCR'].includes(seriesCode)
})

const isObsoleteType = computed(() => {
  if (typeof props.documentType?.is_obsolete === 'boolean') {
    return props.documentType.is_obsolete
  }

  return (props.documentType?.status || '').toLowerCase() === 'obsolete'
})

const canUpload = computed(() => {
  if (typeof props.documentType?.can_upload === 'boolean') {
    return props.documentType.can_upload
  }

  return !isObsoleteType.value
})

const search = ref(props.filters?.q ?? '')
const statusFilter = ref(props.filters?.status ?? 'All')
const sort = ref(props.filters?.sort ?? 'latest')
const perPage = ref(String(props.filters?.per_page ?? 10))
const dateFrom = ref(props.filters?.date_from ?? '')
const dateTo = ref(props.filters?.date_to ?? '')
const selectedRecordType = ref(
  props.filters?.record_type ?? props.performanceView?.selected_record_type ?? null
)
const selectedYear = ref(props.filters?.year ?? props.performanceView?.selected_year ?? null)
const selectedPeriod = ref(props.filters?.period ?? props.performanceView?.selected_period ?? null)

const searchPlaceholder = computed(() => {
  if (isPerformanceForm.value) {
    return 'Search file, remarks, uploader...'
  }

  return requiresRevision.value
    ? 'Search file, revision, remarks, uploader...'
    : 'Search file, remarks, uploader...'
})

const performanceRecordTypeGroups = computed(() => props.performanceView?.record_type_groups ?? [])
const performanceSelectedFiles = computed(() => props.performanceView?.selected_files ?? [])
const performanceSelectedPeriodName = computed(() => props.performanceView?.selected_period_name ?? '')

const performanceSelectedRecordTypeName = computed(() => {
  const selected = performanceRecordTypeGroups.value.find(
    (group) => group.record_type === selectedRecordType.value
  )

  return selected?.record_type_name ?? formatPerformanceRecordType(selectedRecordType.value)
})

const selectedRecordTypeGroup = computed(() => {
  return performanceRecordTypeGroups.value.find(
    (group) => group.record_type === selectedRecordType.value
  ) ?? null
})

const performanceYearGroups = computed(() => {
  return selectedRecordTypeGroup.value?.years ?? []
})

const hasPerformanceUploads = computed(() => performanceRecordTypeGroups.value.length > 0)

const shouldShowPerformanceFirstUpload = computed(() => {
  return isPerformanceForm.value && !hasPerformanceUploads.value && canUpload.value
})

watch(requiresRevision, (val) => {
  if (!val) statusFilter.value = 'All'
})

function reloadDocuments(extra = {}) {
  router.get(
    `/documents/${props.documentType.id}`,
    {
      q: search.value || undefined,
      status: !isPerformanceForm.value && requiresRevision.value ? statusFilter.value : undefined,
      sort: sort.value || 'latest',
      per_page: !isPerformanceForm.value ? perPage.value || 10 : undefined,
      date_from: dateFrom.value || undefined,
      date_to: dateTo.value || undefined,
      record_type: isPerformanceForm.value ? selectedRecordType.value || undefined : undefined,
      year: isPerformanceForm.value ? selectedYear.value || undefined : undefined,
      period: isPerformanceForm.value ? selectedPeriod.value || undefined : undefined,
      ...extra,
    },
    {
      preserveState: true,
      preserveScroll: true,
      replace: true,
    }
  )
}

let searchTimeout = null

watch(search, () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    reloadDocuments({ page: 1 })
  }, 300)
})

watch(statusFilter, () => {
  if (!requiresRevision.value || isPerformanceForm.value) return
  reloadDocuments({ page: 1 })
})

watch(sort, () => reloadDocuments({ page: 1 }))

watch(perPage, () => {
  if (!isPerformanceForm.value) {
    reloadDocuments({ page: 1 })
  }
})

watch(dateFrom, () => reloadDocuments({ page: 1 }))
watch(dateTo, () => reloadDocuments({ page: 1 }))

const showUploadModal = ref(false)
const uploading = ref(false)
const uploadError = ref('')
const fileInput = ref(null)

const currentYear = new Date().getFullYear()

const yearOptions = computed(() => {
  const startYear = currentYear + 1
  const endYear = 2020

  const generatedYears = []
  for (let year = startYear; year >= endYear; year -= 1) {
    generatedYears.push(year)
  }

  const existingYears = performanceRecordTypeGroups.value
    .flatMap((group) => group.years || [])
    .map((yearGroup) => Number(yearGroup.year))
    .filter(Boolean)

  return [...new Set([...generatedYears, ...existingYears])].sort((a, b) => b - a)
})

const periodOptions = [
  { value: 'JAN_JUN', label: 'January – June' },
  { value: 'JUL_DEC', label: 'July – December' },
]

const performanceRecordTypeOptions = [
  { value: 'TARGET', label: 'Target' },
  { value: 'ACCOMPLISHMENT', label: 'Accomplishment' },
]

const uploadForm = ref({
  files: [],
  revision: '',
  performance_record_type:
    props.filters?.record_type ?? props.performanceView?.selected_record_type ?? 'TARGET',
  year: props.filters?.year ?? props.performanceView?.selected_year ?? currentYear,
  period: props.filters?.period ?? props.performanceView?.selected_period ?? 'JAN_JUN',
})

function resetUploadForm() {
  uploadError.value = ''
  uploadForm.value = {
    files: [],
    revision: '',
    performance_record_type:
      selectedRecordType.value ||
      props.performanceView?.selected_record_type ||
      'TARGET',
    year: selectedYear.value ?? props.performanceView?.selected_year ?? currentYear,
    period: selectedPeriod.value ?? props.performanceView?.selected_period ?? 'JAN_JUN',
  }

  if (fileInput.value) {
    fileInput.value.value = ''
  }
}

function openUpload() {
  if (!canUpload.value) {
    toast.error('This document type is obsolete and is kept for reference only.')
    return
  }

  resetUploadForm()
  showUploadModal.value = true
}

function closeUpload(force = false) {
  if (uploading.value && !force) return
  showUploadModal.value = false
  resetUploadForm()
}

function onPickFile(e) {
  uploadForm.value.files = Array.from(e.target.files || [])
}

function removeFile(index) {
  const updatedFiles = [...uploadForm.value.files]
  updatedFiles.splice(index, 1)

  uploadForm.value.files = updatedFiles

  if (fileInput.value) {
    const dt = new DataTransfer()

    updatedFiles.forEach((file) => {
      dt.items.add(file)
    })

    fileInput.value.files = dt.files

    if (!updatedFiles.length) {
      fileInput.value.value = ''
    }
  }
}

function submitUpload() {
  if (!canUpload.value) {
    uploadError.value = 'This document type is obsolete and is kept for reference only.'
    toast.error(uploadError.value)
    return
  }

  uploadError.value = ''

  if (!uploadForm.value.files.length) {
    uploadError.value = 'Please choose at least one file to upload.'
    toast.error(uploadError.value)
    return
  }

  if (requiresRevision.value && !isPerformanceForm.value && uploadForm.value.files.length > 1) {
    uploadError.value = 'Multiple upload is not allowed for revision-controlled documents.'
    toast.error(uploadError.value)
    return
  }

  if (requiresRevision.value && !isPerformanceForm.value && !uploadForm.value.revision.trim()) {
    uploadError.value = 'Revision is required for this document type.'
    toast.error(uploadError.value)
    return
  }

  if (isPerformanceForm.value) {
    if (!uploadForm.value.performance_record_type) {
      uploadError.value = 'Record type is required for performance forms.'
      toast.error(uploadError.value)
      return
    }

    if (!uploadForm.value.year) {
      uploadError.value = 'Year is required for performance forms.'
      toast.error(uploadError.value)
      return
    }

    if (!uploadForm.value.period) {
      uploadError.value = 'Period is required for performance forms.'
      toast.error(uploadError.value)
      return
    }
  }

  const data = new FormData()

  uploadForm.value.files.forEach((file) => {
    data.append('files[]', file)
  })

  if (requiresRevision.value && !isPerformanceForm.value) {
    data.append('revision', uploadForm.value.revision.trim())
  }

  if (isPerformanceForm.value) {
    data.append('performance_record_type', String(uploadForm.value.performance_record_type))
    data.append('year', String(uploadForm.value.year))
    data.append('period', String(uploadForm.value.period))
  }

  const uploadedRecordType = uploadForm.value.performance_record_type
  const uploadedYear = uploadForm.value.year
  const uploadedPeriod = uploadForm.value.period

  uploading.value = true
  loading.open('Uploading file...')

  router.post(`/documents/${props.documentType.id}/upload`, data, {
    forceFormData: true,
    preserveScroll: true,
    onError: (errors) => {
      uploadError.value =
        errors.upload ||
        errors.files ||
        errors['files.0'] ||
        errors.revision ||
        errors.performance_record_type ||
        errors.year ||
        errors.period ||
        'Upload failed. Please try again.'

      toast.error(uploadError.value)
    },
    onSuccess: () => {
      const uploadedCount = uploadForm.value.files.length
      closeUpload(true)

      if (isPerformanceForm.value) {
        selectedRecordType.value = uploadedRecordType
        selectedYear.value = uploadedYear
        selectedPeriod.value = uploadedPeriod
      }

      toast.success(
        uploadedCount > 1
          ? 'Files uploaded successfully.'
          : 'File uploaded successfully.'
      )

      if (isPerformanceForm.value) {
        reloadDocuments({
          record_type: uploadedRecordType,
          year: uploadedYear,
          period: uploadedPeriod,
          page: 1,
        })
      }
    },
    onFinish: () => {
      uploading.value = false
      loading.close()
    },
  })
}

function selectPerformanceRecordType(recordType) {
  selectedRecordType.value = recordType
  selectedYear.value = null
  selectedPeriod.value = null
  reloadDocuments({
    record_type: recordType,
    year: undefined,
    period: undefined,
    page: 1,
  })
}

function selectPerformanceYear(recordType, year) {
  selectedRecordType.value = recordType
  selectedYear.value = year
  selectedPeriod.value = null
  reloadDocuments({
    record_type: recordType,
    year,
    period: undefined,
    page: 1,
  })
}

function selectPerformancePeriod(recordType, year, period) {
  selectedRecordType.value = recordType
  selectedYear.value = year
  selectedPeriod.value = period
  reloadDocuments({
    record_type: recordType,
    year,
    period,
    page: 1,
  })
}

function formatDate(date) {
  if (!date) return '—'
  return new Date(date).toLocaleString()
}

function formatPeriod(period) {
  return period === 'JAN_JUN'
    ? 'January – June'
    : period === 'JUL_DEC'
      ? 'July – December'
      : 'Unknown Period'
}

function formatPerformanceRecordType(type) {
  return type === 'TARGET'
    ? 'Target'
    : type === 'ACCOMPLISHMENT'
      ? 'Accomplishment'
      : 'Record Type'
}

function statusClass(status) {
  if (status === 'Active') return 'bg-emerald-50 text-emerald-700 ring-emerald-200'
  return 'bg-rose-50 text-rose-700 ring-rose-200'
}

function formatFileSize(bytes) {
  if (!bytes && bytes !== 0) return ''
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  if (bytes < 1024 * 1024 * 1024) return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
  return `${(bytes / (1024 * 1024 * 1024)).toFixed(1)} GB`
}

function getFileExtension(fileName) {
  if (!fileName) return ''
  const parts = fileName.split('.')
  return parts.length > 1 ? parts.pop().toLowerCase() : ''
}

function getFileTypeLabel(fileName) {
  const ext = getFileExtension(fileName)

  if (['pdf'].includes(ext)) return 'PDF'
  if (['doc', 'docx'].includes(ext)) return 'DOC'
  if (['xls', 'xlsx', 'csv'].includes(ext)) return 'XLS'
  if (['png', 'jpg', 'jpeg', 'gif', 'webp'].includes(ext)) return 'IMG'

  return 'FILE'
}

function getFileTypeClass(fileName) {
  const ext = getFileExtension(fileName)

  if (['pdf'].includes(ext)) {
    return 'bg-rose-50 text-rose-700 ring-rose-200'
  }

  if (['doc', 'docx'].includes(ext)) {
    return 'bg-blue-50 text-blue-700 ring-blue-200'
  }

  if (['xls', 'xlsx', 'csv'].includes(ext)) {
    return 'bg-emerald-50 text-emerald-700 ring-emerald-200'
  }

  if (['png', 'jpg', 'jpeg', 'gif', 'webp'].includes(ext)) {
    return 'bg-amber-50 text-amber-700 ring-amber-200'
  }

  return 'bg-slate-100 text-slate-700 ring-slate-200'
}

const tableColspan = computed(() => (requiresRevision.value ? 6 : 5))
</script>

<template>
  <AdminLayout>
    <div class="space-y-6 p-6">
      <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="bg-gradient-to-r from-slate-900 to-slate-800 px-5 py-4 sm:px-6">
          <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
            <div class="min-w-0 flex-1 xl:max-w-[54%]">
              <div class="flex flex-wrap items-center gap-2">
                <span class="rounded-md bg-white/10 px-2.5 py-1 text-[11px] font-semibold tracking-wide text-white">
                  {{ documentType?.code }}
                </span>

                <span
                  v-if="isPerformanceForm"
                  class="rounded-full bg-sky-500/15 px-2.5 py-1 text-[11px] font-medium text-sky-100 ring-1 ring-sky-400/30"
                >
                  Semestral Filing
                </span>

                <span
                  v-else-if="requiresRevision"
                  class="rounded-full bg-emerald-500/15 px-2.5 py-1 text-[11px] font-medium text-emerald-100 ring-1 ring-emerald-400/30"
                  title="This document type uses revision control"
                >
                  Revision Controlled
                </span>

                <span
                  v-else
                  class="rounded-full bg-white/10 px-2.5 py-1 text-[11px] font-medium text-slate-200 ring-1 ring-white/10"
                >
                  Record Type
                </span>

                <span
                  class="rounded-full px-2.5 py-1 text-[11px] font-medium ring-1"
                  :class="isObsoleteType
                    ? 'bg-amber-500/15 text-amber-100 ring-amber-400/30'
                    : 'bg-emerald-500/15 text-emerald-100 ring-emerald-400/30'"
                >
                  {{ documentType?.status || 'Active' }}
                </span>
              </div>

              <h1 class="mt-2 text-lg font-semibold tracking-tight text-white sm:text-[21px]">
                {{ documentType?.name }}
              </h1>

              <p class="mt-1 max-w-xl text-sm leading-5 text-slate-300">
                <template v-if="isPerformanceForm">
                  Browse uploads by record type, year, and semestral period, then open the files stored inside each folder.
                </template>
                <template v-else>
                  View all uploaded files under this document code.
                </template>
              </p>
            </div>

            <div class="w-full xl:max-w-[520px]">
              <div
                class="grid grid-cols-1 gap-3"
                :class="requiresRevision && !isPerformanceForm ? 'sm:grid-cols-3' : 'sm:grid-cols-1'"
              >
                <div class="rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
                  <div class="text-xs uppercase tracking-wide text-slate-400">Total Files</div>
                  <div class="mt-1 text-2xl font-semibold text-white">{{ stats?.total ?? 0 }}</div>
                </div>

                <div
                  v-if="requiresRevision && !isPerformanceForm"
                  class="rounded-2xl border border-white/10 bg-white/5 px-4 py-3"
                >
                  <div class="text-xs uppercase tracking-wide text-slate-400">Active</div>
                  <div class="mt-1 text-2xl font-semibold text-white">{{ stats?.active ?? 0 }}</div>
                </div>

                <div
                  v-if="requiresRevision && !isPerformanceForm"
                  class="rounded-2xl border border-white/10 bg-white/5 px-4 py-3"
                >
                  <div class="text-xs uppercase tracking-wide text-slate-400">Obsolete</div>
                  <div class="mt-1 text-2xl font-semibold text-white">{{ stats?.obsolete ?? 0 }}</div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div v-if="isObsoleteType" class="border-t border-amber-200 bg-amber-50 px-5 py-4 sm:px-6">
          <div class="rounded-xl border border-amber-200 bg-white/70 px-4 py-3 text-sm text-amber-800">
            <div class="font-semibold">Obsolete Document Type</div>
            <div class="mt-1">
              This document type is obsolete and is kept for reference only.
            </div>
            <div v-if="documentType?.status_note" class="mt-2 text-amber-900">
              Note: {{ documentType.status_note }}
            </div>
          </div>
        </div>

        <div class="border-t border-slate-200 px-5 py-4 sm:px-6">
          <div class="grid grid-cols-1 gap-3 md:grid-cols-12">
            <div class="md:col-span-4">
              <label class="text-xs font-medium text-slate-600">Search</label>
              <input
                v-model="search"
                type="text"
                :placeholder="searchPlaceholder"
                class="mt-1 w-full rounded-xl border border-slate-200 px-4 py-2 transition focus:outline-none focus:ring-2 focus:ring-slate-300"
              />
            </div>

            <div v-if="requiresRevision && !isPerformanceForm" class="md:col-span-2">
              <label class="text-xs font-medium text-slate-600">Status</label>
              <select
                v-model="statusFilter"
                class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 transition focus:outline-none focus:ring-2 focus:ring-slate-300"
              >
                <option value="All">All</option>
                <option value="Active">Active</option>
                <option value="Obsolete">Obsolete</option>
              </select>
            </div>

            <div :class="requiresRevision && !isPerformanceForm ? 'md:col-span-2' : 'md:col-span-3'">
              <label class="text-xs font-medium text-slate-600">Sort</label>
              <select
                v-model="sort"
                class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 transition focus:outline-none focus:ring-2 focus:ring-slate-300"
              >
                <option value="latest">Latest</option>
                <option value="oldest">Oldest</option>
                <option value="name_asc">File Name (A–Z)</option>
                <option value="name_desc">File Name (Z–A)</option>
                <option v-if="requiresRevision && !isPerformanceForm" value="revision_asc">Revision (A–Z)</option>
                <option v-if="requiresRevision && !isPerformanceForm" value="revision_desc">Revision (Z–A)</option>
              </select>
            </div>

            <div class="md:col-span-2">
              <label class="text-xs font-medium text-slate-600">Date From</label>
              <input
                v-model="dateFrom"
                type="date"
                class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 transition focus:outline-none focus:ring-2 focus:ring-slate-300"
              />
            </div>

            <div class="md:col-span-2">
              <label class="text-xs font-medium text-slate-600">Date To</label>
              <input
                v-model="dateTo"
                type="date"
                class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 transition focus:outline-none focus:ring-2 focus:ring-slate-300"
              />
            </div>
          </div>

          <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="text-sm text-slate-600">
              <template v-if="isPerformanceForm">
                {{ performanceSelectedFiles.length }} file(s) in selected period
              </template>
              <template v-else>
                {{ documents?.total ?? 0 }} file(s) found
              </template>
            </div>

            <button
              type="button"
              @click="openUpload"
              :disabled="!canUpload"
              class="inline-flex items-center justify-center rounded-xl px-4 py-2 text-sm font-medium transition"
              :class="canUpload
                ? 'bg-slate-900 text-white hover:bg-slate-800'
                : 'cursor-not-allowed bg-slate-200 text-slate-500'"
            >
              {{ requiresRevision && !isPerformanceForm ? 'Upload Revision' : 'Upload Files' }}
            </button>
          </div>
        </div>
      </div>

      <template v-if="isPerformanceForm">
        <div
          v-if="shouldShowPerformanceFirstUpload"
          class="rounded-2xl border border-dashed border-sky-300 bg-sky-50 px-5 py-6 shadow-sm"
        >
          <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
              <h2 class="text-base font-semibold text-slate-900">
                No uploads yet
              </h2>
              <p class="mt-1 text-sm text-slate-600">
                Upload the first {{ documentType?.name }} file by selecting record type, year, and semestral period.
                After upload, the folder structure will appear automatically.
              </p>
            </div>

            <button
              type="button"
              @click="openUpload"
              class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-800"
            >
              Upload First File
            </button>
          </div>

          <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2">
            <div class="rounded-xl border border-sky-200 bg-white px-4 py-3">
              <div class="text-xs font-medium uppercase tracking-wide text-sky-700">Structure</div>
              <div class="mt-2 text-sm text-slate-700">
                {{ documentType?.name }} → Record Type → Year → Period → Files
              </div>
            </div>

            <div class="rounded-xl border border-sky-200 bg-white px-4 py-3">
              <div class="text-xs font-medium uppercase tracking-wide text-sky-700">Periods</div>
              <div class="mt-2 text-sm text-slate-700">
                January–June and July–December only
              </div>
            </div>
          </div>
        </div>

        <div
          v-else-if="!performanceRecordTypeGroups.length"
          class="rounded-2xl border border-slate-200 bg-white p-10 text-center shadow-sm"
        >
          <div class="text-lg font-semibold text-slate-900">No uploads yet</div>
          <div class="mt-2 text-sm text-slate-600">
            This document type is obsolete and is kept for reference only.
          </div>
        </div>

        <div v-else class="grid grid-cols-1 gap-6 xl:grid-cols-12">
          <div class="space-y-6 xl:col-span-4">
            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
              <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="text-base font-semibold text-slate-900">Record Type</h2>
                <p class="mt-1 text-sm text-slate-600">
                  Select Target or Accomplishment first.
                </p>
              </div>

              <div class="space-y-3 p-4">
                <button
                  v-for="group in performanceRecordTypeGroups"
                  :key="group.record_type"
                  type="button"
                  @click="selectPerformanceRecordType(group.record_type)"
                  class="w-full rounded-2xl border px-4 py-4 text-left transition"
                  :class="selectedRecordType === group.record_type
                    ? 'border-slate-900 bg-slate-900 text-white shadow-sm'
                    : 'border-slate-200 bg-white text-slate-800 hover:border-slate-300 hover:bg-slate-50'"
                >
                  <div class="flex items-start gap-3">
                    <div
                      class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl"
                      :class="selectedRecordType === group.record_type ? 'bg-white/10' : 'bg-amber-50'"
                    >
                      <svg
                        class="h-5 w-5"
                        :class="selectedRecordType === group.record_type ? 'text-white' : 'text-amber-600'"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                      >
                        <path d="M3 7a2 2 0 0 1 2-2h5l2 2h7a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7z" />
                      </svg>
                    </div>

                    <div class="min-w-0 flex-1">
                      <div class="font-semibold">
                        {{ group.record_type_name }}
                      </div>
                      <div
                        class="mt-1 text-xs"
                        :class="selectedRecordType === group.record_type ? 'text-slate-300' : 'text-slate-500'"
                      >
                        {{ group.total_files }} file(s) • {{ group.years_count }} year(s)
                      </div>
                    </div>
                  </div>
                </button>
              </div>
            </div>

            <div
              v-if="selectedRecordType"
              class="rounded-2xl border border-slate-200 bg-white shadow-sm"
            >
              <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="text-base font-semibold text-slate-900">Year</h2>
                <p class="mt-1 text-sm text-slate-600">
                  Open a year folder under {{ performanceSelectedRecordTypeName }}.
                </p>
              </div>

              <div v-if="performanceYearGroups.length" class="grid grid-cols-2 gap-3 p-4">
                <button
                  v-for="yearGroup in performanceYearGroups"
                  :key="yearGroup.year"
                  type="button"
                  @click="selectPerformanceYear(selectedRecordType, yearGroup.year)"
                  class="rounded-2xl border px-4 py-4 text-left transition"
                  :class="selectedYear === yearGroup.year
                    ? 'border-slate-900 bg-slate-900 text-white shadow-sm'
                    : 'border-slate-200 bg-white text-slate-800 hover:border-slate-300 hover:bg-slate-50'"
                >
                  <div class="flex items-start gap-3">
                    <div
                      class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl"
                      :class="selectedYear === yearGroup.year ? 'bg-white/10' : 'bg-amber-50'"
                    >
                      <svg
                        class="h-5 w-5"
                        :class="selectedYear === yearGroup.year ? 'text-white' : 'text-amber-600'"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                      >
                        <path d="M3 7a2 2 0 0 1 2-2h5l2 2h7a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7z" />
                      </svg>
                    </div>

                    <div class="min-w-0 flex-1">
                      <div class="font-semibold">
                        {{ yearGroup.year }}
                      </div>
                      <div
                        class="mt-1 text-xs"
                        :class="selectedYear === yearGroup.year ? 'text-slate-300' : 'text-slate-500'"
                      >
                        {{ yearGroup.total_files }} file(s) • {{ yearGroup.periods_count }} period(s)
                      </div>
                    </div>
                  </div>
                </button>
              </div>

              <div v-else class="p-8 text-center">
                <div class="text-base font-semibold text-slate-900">No year folders yet</div>
                <div class="mt-2 text-sm text-slate-600">
                  Upload a file to create the first year under {{ performanceSelectedRecordTypeName }}.
                </div>
              </div>
            </div>

            <div
              v-if="selectedRecordType && selectedYear"
              class="rounded-2xl border border-slate-200 bg-white shadow-sm"
            >
              <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="text-base font-semibold text-slate-900">Periods</h2>
                <p class="mt-1 text-sm text-slate-600">
                  Open a semestral folder inside year {{ selectedYear }}.
                </p>
              </div>

              <div v-if="selectedRecordTypeGroup && performanceYearGroups.find(y => Number(y.year) === Number(selectedYear))?.periods?.length" class="space-y-3 p-4">
                <button
                  v-for="periodItem in performanceYearGroups.find(y => Number(y.year) === Number(selectedYear))?.periods || []"
                  :key="`${selectedRecordType}-${selectedYear}-${periodItem.period}`"
                  type="button"
                  @click="selectPerformancePeriod(selectedRecordType, selectedYear, periodItem.period)"
                  class="w-full rounded-2xl border px-4 py-4 text-left transition"
                  :class="selectedPeriod === periodItem.period
                    ? 'border-slate-900 bg-slate-900 text-white shadow-sm'
                    : 'border-slate-200 bg-white text-slate-800 hover:border-slate-300 hover:bg-slate-50'"
                >
                  <div class="flex items-start gap-3">
                    <div
                      class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl"
                      :class="selectedPeriod === periodItem.period ? 'bg-white/10' : 'bg-amber-50'"
                    >
                      <svg
                        class="h-5 w-5"
                        :class="selectedPeriod === periodItem.period ? 'text-white' : 'text-amber-600'"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                      >
                        <path d="M3 7a2 2 0 0 1 2-2h5l2 2h7a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7z" />
                      </svg>
                    </div>

                    <div class="min-w-0 flex-1">
                      <div class="font-semibold">
                        {{ periodItem.period_name }}
                      </div>
                      <div
                        class="mt-1 text-xs"
                        :class="selectedPeriod === periodItem.period ? 'text-slate-300' : 'text-slate-500'"
                      >
                        {{ periodItem.files_count }} file(s)
                      </div>
                    </div>
                  </div>
                </button>
              </div>

              <div v-else class="p-8 text-center">
                <div class="text-base font-semibold text-slate-900">No period folders yet</div>
                <div class="mt-2 text-sm text-slate-600">
                  Upload a file to create the first period for {{ selectedYear }}.
                </div>
              </div>
            </div>
          </div>

          <div class="xl:col-span-8">
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
              <div class="border-b border-slate-200 px-5 py-4">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                  <div>
                    <h2 class="text-base font-semibold text-slate-900">
                      <template v-if="selectedRecordType && selectedYear && selectedPeriod">
                        {{ documentType?.name }} — {{ performanceSelectedRecordTypeName }} / {{ selectedYear }} / {{ performanceSelectedPeriodName }}
                      </template>
                      <template v-else-if="selectedRecordType">
                        {{ documentType?.name }} — {{ performanceSelectedRecordTypeName }}
                      </template>
                      <template v-else>
                        Select a record type, year, and period
                      </template>
                    </h2>
                    <p class="mt-1 text-sm text-slate-600">
                      <template v-if="selectedRecordType && selectedYear && selectedPeriod">
                        All files uploaded under this semestral folder are shown below.
                      </template>
                      <template v-else-if="selectedRecordType && !selectedYear">
                        Choose a year from the left panel.
                      </template>
                      <template v-else-if="selectedRecordType && selectedYear && !selectedPeriod">
                        Choose a period from the left panel.
                      </template>
                      <template v-else>
                        Start by selecting a record type from the left panel.
                      </template>
                    </p>
                  </div>

                  <div
                    v-if="selectedRecordType && selectedYear && selectedPeriod"
                    class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-700"
                  >
                    {{ performanceSelectedFiles.length }} file(s)
                  </div>
                </div>
              </div>

              <div v-if="!selectedRecordType" class="p-10 text-center">
                <div class="text-lg font-semibold text-slate-900">No record type selected</div>
                <div class="mt-2 text-sm text-slate-600">
                  Select Target or Accomplishment to continue.
                </div>
              </div>

              <div v-else-if="selectedRecordType && !selectedYear" class="p-10 text-center">
                <div class="text-lg font-semibold text-slate-900">No year selected</div>
                <div class="mt-2 text-sm text-slate-600">
                  Select a year folder from the left panel.
                </div>
              </div>

              <div v-else-if="selectedRecordType && selectedYear && !selectedPeriod" class="p-10 text-center">
                <div class="text-lg font-semibold text-slate-900">No period selected</div>
                <div class="mt-2 text-sm text-slate-600">
                  Select a semestral period from the left panel.
                </div>
              </div>

              <div v-else-if="!performanceSelectedFiles.length" class="p-10 text-center">
                <div class="text-lg font-semibold text-slate-900">No files found</div>
                <div class="mt-2 text-sm text-slate-600">
                  There are no files for the selected period based on the current filters.
                </div>
              </div>

              <div v-else class="space-y-4 p-5">
                <div
                  v-for="doc in performanceSelectedFiles"
                  :key="doc.id"
                  class="rounded-2xl border border-slate-200 bg-slate-50 p-4 transition hover:border-slate-300 hover:bg-white"
                >
                  <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div class="min-w-0 flex-1">
                      <div class="flex min-w-0 items-center gap-2">
                        <span
                          class="inline-flex shrink-0 items-center rounded-md px-1.5 py-[2px] text-[10px] font-bold tracking-wide ring-1"
                          :class="getFileTypeClass(doc.file_name)"
                        >
                          {{ getFileTypeLabel(doc.file_name) }}
                        </span>

                        <div class="truncate text-sm font-semibold text-slate-900" :title="doc.file_name">
                          {{ doc.file_name }}
                        </div>
                      </div>

                      <div class="mt-3 grid grid-cols-1 gap-2 text-sm text-slate-600 sm:grid-cols-2">
                        <div>
                          <span class="font-medium text-slate-700">Uploaded by:</span>
                          {{ doc.uploaded_by_name }}
                        </div>
                        <div>
                          <span class="font-medium text-slate-700">Date:</span>
                          {{ formatDate(doc.created_at) }}
                        </div>
                        <div v-if="doc.performance_record_type">
                          <span class="font-medium text-slate-700">Record Type:</span>
                          {{ formatPerformanceRecordType(doc.performance_record_type) }}
                        </div>
                      </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 lg:justify-end">
                      <Link
                        v-if="doc.ofi_record_id"
                        :href="`/ofi-form?record=${doc.ofi_record_id}`"
                        class="inline-flex items-center rounded-lg bg-indigo-600 px-3 py-1.5 text-xs text-white transition hover:bg-indigo-500"
                      >
                        Edit
                      </Link>

                      <Link
                        v-else-if="doc.dcr_record_id"
                        :href="`/dcr?record=${doc.dcr_record_id}`"
                        class="inline-flex items-center rounded-lg bg-indigo-600 px-3 py-1.5 text-xs text-white transition hover:bg-indigo-500"
                      >
                        Edit
                      </Link>

                      <Link
                        v-else-if="doc.car_record_id"
                        :href="`/car?record=${doc.car_record_id}`"
                        class="inline-flex items-center rounded-lg bg-indigo-600 px-3 py-1.5 text-xs text-white transition hover:bg-indigo-500"
                      >
                        Edit
                      </Link>

                      <a
                        v-if="doc.can_preview !== false"
                        :href="doc.preview_url"
                        target="_blank"
                        rel="noopener"
                        class="inline-flex items-center justify-center rounded-lg bg-slate-900 px-3 py-1.5 text-xs text-white transition hover:bg-slate-800"
                      >
                        Preview
                      </a>

                      <a
                        :href="doc.download_url"
                        class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs text-slate-700 transition hover:bg-slate-50"
                      >
                        Download
                      </a>
                    </div>
                  </div>
                </div>
              </div>

              <div class="border-t border-slate-200 bg-slate-50 px-6 py-3 text-xs text-slate-500">
                Performance forms are organized by record type, year, and semestral period.
              </div>
            </div>
          </div>
        </div>
      </template>

      <template v-else>
        <div
          v-if="!(documents?.data || []).length"
          class="rounded-2xl border border-slate-200 bg-white p-10 text-center shadow-sm"
        >
          <div class="text-lg font-semibold text-slate-900">No uploads yet</div>
          <div class="mt-2 text-sm text-slate-600">
            <template v-if="canUpload">
              Start by uploading the first file for this document type.
            </template>
            <template v-else>
              This document type is obsolete and is kept for reference only.
            </template>
          </div>

          <button
            v-if="canUpload"
            type="button"
            @click="openUpload"
            class="mt-4 inline-block rounded-xl bg-slate-900 px-4 py-2 text-sm text-white transition hover:bg-slate-800"
          >
            {{ requiresRevision ? 'Upload First Revision' : 'Upload Files' }}
          </button>
        </div>

        <div
          v-if="(documents?.data || []).length"
          class="overflow-hidden rounded-2xl border border-slate-200 bg-white"
        >
          <div class="overflow-x-auto">
            <table class="min-w-full table-fixed text-sm">
              <colgroup>
                <template v-if="requiresRevision">
                  <col class="w-[90px]" />
                  <col />
                  <col class="w-[120px]" />
                  <col class="w-[170px]" />
                  <col class="w-[190px]" />
                  <col class="w-[170px]" />
                </template>

                <template v-else>
                  <col />
                  <col class="w-[170px]" />
                  <col class="w-[190px]" />
                  <col class="w-[170px]" />
                </template>
              </colgroup>

              <thead class="border-b border-slate-200 bg-slate-50">
                <tr class="text-left">
                  <th v-if="requiresRevision" class="px-5 py-3 font-semibold text-slate-700">
                    Revision
                  </th>
                  <th class="px-5 py-3 font-semibold text-slate-700">File Name</th>
                  <th v-if="requiresRevision" class="px-5 py-3 font-semibold text-slate-700">
                    Status
                  </th>
                  <th class="px-5 py-3 font-semibold text-slate-700">Uploaded By</th>
                  <th class="px-5 py-3 whitespace-nowrap font-semibold text-slate-700">Date</th>
                  <th class="px-5 py-3 whitespace-nowrap text-right font-semibold text-slate-700">
                    Actions
                  </th>
                </tr>
              </thead>

              <tbody>
                <tr
                  v-for="doc in documents.data"
                  :key="doc.id"
                  class="border-b border-slate-100 transition-colors duration-200 hover:bg-slate-50"
                >
                  <td
                    v-if="requiresRevision"
                    class="whitespace-nowrap px-5 py-4 align-middle font-medium text-slate-900"
                  >
                    {{ doc.revision || '—' }}
                  </td>

                  <td class="px-5 py-4 align-middle text-slate-800">
                    <div class="flex min-w-0 items-center gap-2">
                      <span
                        class="inline-flex shrink-0 items-center rounded-md px-1.5 py-[2px] text-[10px] font-bold tracking-wide ring-1"
                        :class="getFileTypeClass(doc.file_name)"
                      >
                        {{ getFileTypeLabel(doc.file_name) }}
                      </span>

                      <div
                        class="min-w-0 max-w-[250px] truncate overflow-hidden whitespace-nowrap"
                        :title="doc.file_name"
                      >
                        {{ doc.file_name }}
                      </div>
                    </div>
                  </td>

                  <td v-if="requiresRevision" class="whitespace-nowrap px-5 py-4 align-middle">
                    <span
                      class="rounded-full px-2 py-1 text-xs ring-1 transition"
                      :class="statusClass(doc.status)"
                    >
                      {{ doc.status }}
                    </span>
                  </td>

                  <td class="px-5 py-4 align-middle text-slate-600">
                    <div class="line-clamp-2">
                      {{ doc.uploaded_by_name }}
                    </div>
                  </td>

                  <td class="whitespace-nowrap px-5 py-4 align-middle text-slate-600">
                    {{ formatDate(doc.created_at) }}
                  </td>

                  <td class="px-5 py-4 align-middle">
                    <div class="flex items-center justify-end gap-2 whitespace-nowrap">
                      <Link
                        v-if="!requiresRevision && doc.ofi_record_id"
                        :href="`/ofi-form?record=${doc.ofi_record_id}`"
                        class="inline-flex items-center rounded-lg bg-indigo-600 px-3 py-1.5 text-xs text-white transition hover:bg-indigo-500"
                        title="Edit this OFI record"
                      >
                        Edit
                      </Link>

                      <Link
                        v-else-if="!requiresRevision && doc.dcr_record_id"
                        :href="`/dcr?record=${doc.dcr_record_id}`"
                        class="inline-flex items-center rounded-lg bg-indigo-600 px-3 py-1.5 text-xs text-white transition hover:bg-indigo-500"
                        title="Edit this DCR record"
                      >
                        Edit
                      </Link>

                      <Link
                        v-else-if="!requiresRevision && doc.car_record_id"
                        :href="`/car?record=${doc.car_record_id}`"
                        class="inline-flex items-center rounded-lg bg-indigo-600 px-3 py-1.5 text-xs text-white transition hover:bg-indigo-500"
                        title="Edit this CAR record"
                      >
                        Edit
                      </Link>

                      <a
                        v-if="doc.can_preview !== false"
                        :href="doc.preview_url"
                        target="_blank"
                        rel="noopener"
                        class="inline-flex items-center justify-center rounded-lg bg-slate-900 px-3 py-1.5 text-xs text-white transition hover:bg-slate-800"
                      >
                        Preview
                      </a>

                      <a
                        :href="doc.download_url"
                        class="inline-flex items-center justify-center rounded-lg border border-slate-200 px-3 py-1.5 text-xs text-slate-700 transition hover:bg-slate-50"
                      >
                        Download
                      </a>
                    </div>
                  </td>
                </tr>

                <tr v-if="!documents.data.length">
                  <td :colspan="tableColspan" class="px-5 py-6 text-center text-sm text-slate-500">
                    No matching files found.
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="flex flex-col gap-3 border-t border-slate-200 bg-slate-50 px-6 py-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:gap-4">
              <div class="text-sm text-slate-600">
                Showing
                <span class="font-medium text-slate-900">{{ documents.from ?? 0 }}</span>
                to
                <span class="font-medium text-slate-900">{{ documents.to ?? 0 }}</span>
                of
                <span class="font-medium text-slate-900">{{ documents.total ?? 0 }}</span>
                files
              </div>

              <div class="flex items-center gap-2">
                <label class="text-sm text-slate-600">Per page</label>
                <select
                  v-model="perPage"
                  class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm text-slate-700 transition focus:outline-none focus:ring-2 focus:ring-slate-300"
                >
                  <option value="10">10</option>
                  <option value="25">25</option>
                  <option value="50">50</option>
                  <option value="100">100</option>
                </select>
              </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
              <template v-for="(link, index) in documents.links" :key="`${link.label}-${index}`">
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

          <div class="bg-slate-50 px-6 py-3 text-xs text-slate-500">
            <template v-if="requiresRevision">
              ISO Document Control: Only one version should remain Active.
              Uploading a new revision should mark previous versions as Obsolete.
            </template>
            <template v-else>
              Record storage: uploads are treated as records (no revision control).
            </template>
          </div>
        </div>
      </template>

      <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div v-if="showUploadModal" class="fixed inset-0 z-[999]">
          <Transition
            appear
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
          >
            <div
              v-if="showUploadModal"
              class="absolute inset-0 bg-slate-900/50"
              @click="closeUpload()"
            ></div>
          </Transition>

          <div class="absolute inset-0 flex items-center justify-center p-4">
            <Transition
              appear
              enter-active-class="transition duration-200 ease-out"
              enter-from-class="opacity-0 translate-y-2 scale-[0.98]"
              enter-to-class="opacity-100 translate-y-0 scale-100"
              leave-active-class="transition duration-150 ease-in"
              leave-from-class="opacity-100 translate-y-0 scale-100"
              leave-to-class="opacity-0 translate-y-2 scale-[0.98]"
            >
              <div
                v-if="showUploadModal"
                class="w-full max-w-lg overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl"
              >
                <div class="flex items-center justify-between bg-gradient-to-r from-slate-900 to-slate-800 px-6 py-4">
                  <div>
                    <div class="text-xs text-slate-300">Upload under</div>
                    <div class="font-semibold text-white">
                      {{ documentType?.code }} — {{ documentType?.name }}
                    </div>
                  </div>

                  <button
                    type="button"
                    @click="closeUpload()"
                    class="text-slate-200 transition hover:text-white"
                    :disabled="uploading"
                    aria-label="Close"
                  >
                    ✕
                  </button>
                </div>

                <div class="space-y-4 px-6 py-5">
                  <div
                    v-if="uploadError"
                    class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700"
                  >
                    {{ uploadError }}
                  </div>

                  <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    Upload is disabled if the document type is obsolete.
                  </div>

                  <div v-if="isPerformanceForm" class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                      <label class="text-xs font-medium text-slate-600">Record Type</label>
                      <select
                        v-model="uploadForm.performance_record_type"
                        class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 transition focus:outline-none focus:ring-2 focus:ring-slate-300"
                      >
                        <option
                          v-for="recordType in performanceRecordTypeOptions"
                          :key="recordType.value"
                          :value="recordType.value"
                        >
                          {{ recordType.label }}
                        </option>
                      </select>
                    </div>

                    <div>
                      <label class="text-xs font-medium text-slate-600">Year</label>
                      <select
                        v-model="uploadForm.year"
                        class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 transition focus:outline-none focus:ring-2 focus:ring-slate-300"
                      >
                        <option v-for="year in yearOptions" :key="year" :value="year">
                          {{ year }}
                        </option>
                      </select>
                    </div>

                    <div>
                      <label class="text-xs font-medium text-slate-600">Period</label>
                      <select
                        v-model="uploadForm.period"
                        class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 transition focus:outline-none focus:ring-2 focus:ring-slate-300"
                      >
                        <option v-for="period in periodOptions" :key="period.value" :value="period.value">
                          {{ period.label }}
                        </option>
                      </select>
                    </div>
                  </div>

                  <div>
                    <label class="text-xs font-medium text-slate-600">Select File</label>
                    <input
                      ref="fileInput"
                      type="file"
                      @change="onPickFile"
                      :multiple="!requiresRevision || isPerformanceForm"
                      class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 transition focus:outline-none focus:ring-2 focus:ring-slate-300"
                      accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg"
                    />

                    <p class="mt-2 text-xs text-slate-500">
                      <template v-if="isPerformanceForm">
                        Allowed: PDF, Word, Excel, images. Multiple files are allowed in the same semestral folder.
                      </template>
                      <template v-else-if="requiresRevision">
                        Allowed: PDF, Word, Excel, images. One file only for revision-controlled documents.
                      </template>
                      <template v-else>
                        Allowed: PDF, Word, Excel, images. You may select multiple files.
                      </template>
                    </p>

                    <div v-if="uploadForm.files.length" class="mt-4">
                      <div class="mb-2 flex items-center justify-between">
                        <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                          Selected Files
                        </div>
                        <div class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">
                          {{ uploadForm.files.length }}
                          {{ uploadForm.files.length === 1 ? 'file' : 'files' }}
                        </div>
                      </div>

                      <div class="max-h-56 overflow-y-auto rounded-xl border border-slate-200 bg-slate-50">
                        <ul class="divide-y divide-slate-200">
                          <li
                            v-for="(file, index) in uploadForm.files"
                            :key="`${file.name}-${file.size}-${index}`"
                            class="flex items-start gap-3 bg-slate-50 px-3 py-3"
                          >
                            <div
                              class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-sm font-semibold text-indigo-600"
                            >
                              {{ file.name.split('.').pop()?.toUpperCase()?.slice(0, 4) || 'FILE' }}
                            </div>

                            <div class="min-w-0 flex-1">
                              <p class="truncate text-sm font-medium text-slate-800" :title="file.name">
                                {{ file.name }}
                              </p>
                              <p class="mt-0.5 text-xs text-slate-500">
                                {{ formatFileSize(file.size) }}
                              </p>
                            </div>

                            <button
                              type="button"
                              @click="removeFile(index)"
                              class="shrink-0 rounded-lg border border-rose-200 bg-white px-2.5 py-1.5 text-xs font-medium text-rose-600 transition hover:bg-rose-50"
                            >
                              Remove
                            </button>
                          </li>
                        </ul>
                      </div>

                      <p class="mt-2 text-xs text-slate-500">
                        Review the selected files before uploading. The list will scroll if many files are selected.
                      </p>
                    </div>
                  </div>

                  <div v-if="requiresRevision && !isPerformanceForm">
                    <label class="text-xs font-medium text-slate-600">Revision</label>
                    <input
                      v-model="uploadForm.revision"
                      type="text"
                      placeholder="e.g., Rev. 1"
                      class="mt-2 w-full rounded-xl border border-slate-200 px-3 py-2.5 transition focus:outline-none focus:ring-2 focus:ring-slate-300"
                    />
                  </div>
                </div>

                <div class="flex items-center justify-between border-t border-slate-200 bg-slate-50 px-6 py-4">
                  <button
                    type="button"
                    @click="closeUpload()"
                    :disabled="uploading"
                    class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
                  >
                    Cancel
                  </button>

                  <button
                    type="button"
                    @click="submitUpload"
                    :disabled="uploading || !canUpload"
                    class="rounded-xl bg-slate-900 px-4 py-2 text-sm text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
                  >
                    <span v-if="!uploading">Upload</span>
                    <span v-else>Uploading...</span>
                  </button>
                </div>
              </div>
            </Transition>
          </div>
        </div>
      </Transition>
    </div>
  </AdminLayout>
</template>
