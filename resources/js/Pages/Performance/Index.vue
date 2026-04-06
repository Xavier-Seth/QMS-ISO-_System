<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { router } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'

const props = defineProps({
  categories: {
    type: Array,
    default: () => [],
  },
  recordTypes: {
    type: Array,
    default: () => [],
  },
  years: {
    type: Array,
    default: () => [],
  },
  periods: {
    type: Array,
    default: () => [],
  },
  files: {
    type: [Array, Object],
    default: () => [],
  },
  filters: {
    type: Object,
    default: () => ({}),
  },
  meta: {
    type: Object,
    default: () => ({}),
  },
})

const search = ref(props.filters?.q ?? '')
const sort = ref(props.filters?.sort ?? 'latest')

const selectedCategory = computed(() => props.filters?.category || 'IPCR')
const selectedRecordType = computed(() => props.filters?.record_type || '')
const selectedYear = computed(() => props.filters?.year ?? null)
const selectedPeriod = computed(() => props.filters?.period || '')

const sortOptions = [
  { value: 'latest', label: 'Latest' },
  { value: 'oldest', label: 'Oldest' },
  { value: 'name_asc', label: 'File Name (A–Z)' },
  { value: 'name_desc', label: 'File Name (Z–A)' },
]

const periodOptions = [
  { value: 'JAN_JUN', label: 'January – June' },
  { value: 'JUL_DEC', label: 'July – December' },
]

const recordTypeOptions = [
  { value: 'TARGET', label: 'Target' },
  { value: 'ACCOMPLISHMENT', label: 'Accomplishment' },
]

const currentYear = new Date().getFullYear()

const yearOptions = computed(() => {
  const years = []
  for (let year = currentYear + 1; year >= 2020; year -= 1) {
    years.push(year)
  }
  return years
})

const fileItems = computed(() => {
  return Array.isArray(props.files) ? props.files : (props.files?.data ?? [])
})

const fileLinks = computed(() => {
  return Array.isArray(props.files) ? [] : (props.files?.links ?? [])
})

const fileTotal = computed(() => {
  return Array.isArray(props.files) ? fileItems.value.length : (props.files?.total ?? 0)
})

const fileFrom = computed(() => {
  return Array.isArray(props.files) ? (fileItems.value.length ? 1 : 0) : (props.files?.from ?? 0)
})

const fileTo = computed(() => {
  return Array.isArray(props.files) ? fileItems.value.length : (props.files?.to ?? 0)
})

const fileLastPage = computed(() => {
  return Array.isArray(props.files) ? 1 : (props.files?.last_page ?? 1)
})

function visitPerformance(params = {}) {
  router.get(
    '/performance',
    {
      category: params.category ?? selectedCategory.value,
      record_type: params.record_type ?? (selectedRecordType.value || undefined),
      year: params.year ?? (selectedYear.value ?? undefined),
      period: params.period ?? (selectedPeriod.value || undefined),
      q: params.q ?? (search.value || undefined),
      sort: params.sort ?? (sort.value || 'latest'),
      page: params.page ?? undefined,
    },
    {
      preserveState: true,
      preserveScroll: true,
      replace: true,
    }
  )
}

function selectCategory(category) {
  search.value = ''
  sort.value = 'latest'

  router.get(
    '/performance',
    {
      category,
    },
    {
      preserveState: true,
      preserveScroll: true,
      replace: true,
    }
  )
}

function selectRecordType(recordType) {
  search.value = ''
  sort.value = 'latest'

  router.get(
    '/performance',
    {
      category: selectedCategory.value,
      record_type: recordType,
    },
    {
      preserveState: true,
      preserveScroll: true,
      replace: true,
    }
  )
}

function selectYear(year) {
  search.value = ''
  sort.value = 'latest'

  router.get(
    '/performance',
    {
      category: selectedCategory.value,
      record_type: selectedRecordType.value,
      year,
    },
    {
      preserveState: true,
      preserveScroll: true,
      replace: true,
    }
  )
}

function selectPeriod(period) {
  router.get(
    '/performance',
    {
      category: selectedCategory.value,
      record_type: selectedRecordType.value,
      year: selectedYear.value,
      period,
      sort: sort.value || 'latest',
      q: search.value || undefined,
    },
    {
      preserveState: true,
      preserveScroll: true,
      replace: true,
    }
  )
}

function goToPage(url) {
  if (!url) return

  router.visit(url, {
    preserveScroll: true,
    preserveState: true,
    replace: true,
  })
}

let searchTimeout = null

watch(search, () => {
  if (!selectedRecordType.value || !selectedYear.value || !selectedPeriod.value) return

  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    visitPerformance({ q: search.value || undefined, page: 1 })
  }, 300)
})

watch(sort, () => {
  if (!selectedRecordType.value || !selectedYear.value || !selectedPeriod.value) return
  visitPerformance({ sort: sort.value || 'latest', page: 1 })
})

function formatDate(date) {
  if (!date) return '—'
  return new Date(date).toLocaleString()
}

function formatFileSize(bytes) {
  if (!bytes && bytes !== 0) return ''
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  if (bytes < 1024 * 1024 * 1024) return `${(bytes / (1024 * 1024)).toFixed(2)} MB`
  return `${(bytes / (1024 * 1024 * 1024)).toFixed(2)} GB`
}

function getFileExtension(fileName) {
  if (!fileName) return ''
  const parts = fileName.split('.')
  return parts.length > 1 ? parts.pop().toLowerCase() : ''
}

function getFileTypeLabel(fileName) {
  const ext = getFileExtension(fileName)

  if (['pdf'].includes(ext)) return 'PDF'
  if (['doc', 'docx'].includes(ext)) return 'DOCX'
  if (['xls', 'xlsx', 'csv'].includes(ext)) return 'XLS'
  if (['png', 'jpg', 'jpeg', 'gif', 'webp'].includes(ext)) return 'IMG'

  return 'FILE'
}

function getFileTypeClass(fileName) {
  const ext = getFileExtension(fileName)

  if (['pdf'].includes(ext)) return 'bg-rose-50 text-rose-700 ring-rose-200'
  if (['doc', 'docx'].includes(ext)) return 'bg-blue-50 text-blue-700 ring-blue-200'
  if (['xls', 'xlsx', 'csv'].includes(ext)) return 'bg-emerald-50 text-emerald-700 ring-emerald-200'
  if (['png', 'jpg', 'jpeg', 'gif', 'webp'].includes(ext)) return 'bg-amber-50 text-amber-700 ring-amber-200'

  return 'bg-slate-100 text-slate-700 ring-slate-200'
}

const showUploadModal = ref(false)
const uploading = ref(false)
const uploadError = ref('')
const fileInput = ref(null)

const uploadForm = ref({
  performance_record_type: props.filters?.record_type || 'TARGET',
  year: props.filters?.year ?? currentYear,
  period: props.filters?.period || 'JAN_JUN',
  files: [],
})

const canOpenUpload = computed(() => !!selectedCategory.value && !!selectedRecordType.value)

const selectedCategoryLabel = computed(() => selectedCategory.value || 'IPCR')
const selectedRecordTypeLabel = computed(() => props.meta?.record_type_label || 'Record Type')
const selectedPeriodLabel = computed(() => props.meta?.period_label || 'Period')

const showYearSection = computed(() => !!selectedRecordType.value)
const showPeriodSection = computed(() => !!selectedRecordType.value && !!selectedYear.value)
const showFilesSection = computed(() => !!selectedRecordType.value && !!selectedYear.value && !!selectedPeriod.value)

function resetUploadForm() {
  uploadError.value = ''
  uploadForm.value = {
    performance_record_type: selectedRecordType.value || 'TARGET',
    year: selectedYear.value ?? currentYear,
    period: selectedPeriod.value || 'JAN_JUN',
    files: [],
  }

  if (fileInput.value) {
    fileInput.value.value = ''
  }
}

function openUpload() {
  if (!selectedCategory.value || !selectedRecordType.value) {
    uploadError.value = 'Please select a record type first.'
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
    updatedFiles.forEach((file) => dt.items.add(file))
    fileInput.value.files = dt.files

    if (!updatedFiles.length) {
      fileInput.value.value = ''
    }
  }
}

function submitUpload() {
  uploadError.value = ''

  if (!selectedCategory.value) {
    uploadError.value = 'Performance category is required.'
    return
  }

  if (!uploadForm.value.performance_record_type) {
    uploadError.value = 'Record type is required.'
    return
  }

  if (!uploadForm.value.year) {
    uploadError.value = 'Year is required.'
    return
  }

  if (!uploadForm.value.period) {
    uploadError.value = 'Period is required.'
    return
  }

  if (!uploadForm.value.files.length) {
    uploadError.value = 'Please choose at least one file.'
    return
  }

  const form = new FormData()
  form.append('performance_category', selectedCategory.value)
  form.append('performance_record_type', uploadForm.value.performance_record_type)
  form.append('year', String(uploadForm.value.year))
  form.append('period', uploadForm.value.period)

  uploadForm.value.files.forEach((file) => {
    form.append('files[]', file)
  })

  uploading.value = true

  router.post('/performance/upload', form, {
    forceFormData: true,
    preserveScroll: true,
    onError: (errors) => {
      uploadError.value =
        errors.performance_category ||
        errors.performance_record_type ||
        errors.year ||
        errors.period ||
        errors.files ||
        errors['files.0'] ||
        'Upload failed. Please try again.'
    },
    onSuccess: () => {
      closeUpload(true)
    },
    onFinish: () => {
      uploading.value = false
    },
  })
}
</script>

<template>
  <AdminLayout>
    <div class="space-y-6 p-6">
      <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="bg-gradient-to-r from-slate-900 to-slate-800 px-6 py-5">
          <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
              <h1 class="text-xl font-semibold tracking-tight text-white">
                Performance Commitment and Review Forms
              </h1>
              <p class="mt-1 text-sm text-slate-300">
                Browse IPCR, DPCR, and UPCR by record type, year, and period.
              </p>
            </div>

            <button
              v-if="canOpenUpload"
              type="button"
              @click="openUpload"
              class="inline-flex items-center justify-center rounded-xl bg-white px-4 py-2 text-sm font-medium text-slate-900 transition hover:bg-slate-100"
            >
              Upload Files
            </button>
          </div>
        </div>

        <div class="border-t border-slate-200 px-6 py-5">
          <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <button
              v-for="category in categories"
              :key="category.value"
              type="button"
              @click="selectCategory(category.value)"
              class="rounded-2xl border px-5 py-5 text-center transition"
              :class="selectedCategory === category.value
                ? 'border-slate-900 bg-slate-100 shadow-sm'
                : 'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50'"
            >
              <div class="text-[28px] font-semibold text-slate-900">
                {{ category.label }}
              </div>
              <div class="mt-1 text-sm text-slate-500">
                {{ category.files_count }} file<span v-if="category.files_count !== 1">s</span>
              </div>
            </button>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 gap-6 xl:grid-cols-12">
        <div class="space-y-6 xl:col-span-4">
          <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
              <h2 class="text-base font-semibold text-slate-900">
                {{ selectedCategoryLabel }}
              </h2>
              <p class="mt-1 text-sm text-slate-600">
                Select a record type folder first.
              </p>
            </div>

            <div class="space-y-3 p-4">
              <button
                v-for="recordType in recordTypes"
                :key="recordType.value"
                type="button"
                @click="selectRecordType(recordType.value)"
                class="w-full rounded-2xl border px-4 py-4 text-left transition"
                :class="selectedRecordType === recordType.value
                  ? 'border-slate-900 bg-slate-900 text-white shadow-sm'
                  : 'border-slate-200 bg-white text-slate-800 hover:border-slate-300 hover:bg-slate-50'"
              >
                <div class="flex items-start gap-3">
                  <div
                    class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl"
                    :class="selectedRecordType === recordType.value ? 'bg-white/10' : 'bg-amber-50'"
                  >
                    <svg
                      class="h-5 w-5"
                      :class="selectedRecordType === recordType.value ? 'text-white' : 'text-amber-600'"
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
                      {{ recordType.label }}
                    </div>
                    <div
                      class="mt-1 text-xs"
                      :class="selectedRecordType === recordType.value ? 'text-slate-300' : 'text-slate-500'"
                    >
                      {{ recordType.files_count }} file<span v-if="recordType.files_count !== 1">s</span>
                    </div>
                  </div>
                </div>
              </button>
            </div>
          </div>

          <div
            v-if="showYearSection"
            class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
          >
            <div class="border-b border-slate-200 px-5 py-4">
              <div class="flex items-center justify-between gap-3">
                <div>
                  <h2 class="text-base font-semibold text-slate-900">Year</h2>
                  <p class="mt-1 text-sm text-slate-600">
                    Open a year folder under {{ selectedRecordTypeLabel }}.
                  </p>
                </div>

                <button
                  type="button"
                  @click="openUpload"
                  class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-3 py-2 text-sm font-medium text-white transition hover:bg-slate-800"
                >
                  Upload
                </button>
              </div>
            </div>

            <div v-if="years.length" class="grid grid-cols-2 gap-3 p-4">
              <button
                v-for="year in years"
                :key="year.value"
                type="button"
                @click="selectYear(year.value)"
                class="rounded-2xl border px-4 py-4 text-left transition"
                :class="selectedYear === year.value
                  ? 'border-slate-900 bg-slate-900 text-white shadow-sm'
                  : 'border-slate-200 bg-white text-slate-800 hover:border-slate-300 hover:bg-slate-50'"
              >
                <div class="flex items-start gap-3">
                  <div
                    class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl"
                    :class="selectedYear === year.value ? 'bg-white/10' : 'bg-amber-50'"
                  >
                    <svg
                      class="h-5 w-5"
                      :class="selectedYear === year.value ? 'text-white' : 'text-amber-600'"
                      viewBox="0 0 24 24"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="1.8"
                    >
                      <path d="M3 7a2 2 0 0 1 2-2h5l2 2h7a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7z" />
                    </svg>
                  </div>

                  <div class="font-semibold">
                    {{ year.label }}
                  </div>
                </div>
              </button>
            </div>

            <div v-else class="p-8 text-center">
              <div class="text-base font-semibold text-slate-900">No year folders yet</div>
              <div class="mt-2 text-sm text-slate-600">
                Create the first {{ selectedRecordTypeLabel.toLowerCase() }} year folder by uploading a file.
              </div>
              <button
                type="button"
                @click="openUpload"
                class="mt-4 inline-flex items-center justify-center rounded-xl bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-800"
              >
                Upload First File
              </button>
            </div>
          </div>

          <div
            v-if="showPeriodSection"
            class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
          >
            <div class="border-b border-slate-200 px-5 py-4">
              <div class="flex items-center justify-between gap-3">
                <div>
                  <h2 class="text-base font-semibold text-slate-900">Periods</h2>
                  <p class="mt-1 text-sm text-slate-600">
                    Open a semestral folder inside year {{ selectedYear }}.
                  </p>
                </div>

                <button
                  type="button"
                  @click="openUpload"
                  class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-3 py-2 text-sm font-medium text-white transition hover:bg-slate-800"
                >
                  Upload
                </button>
              </div>
            </div>

            <div v-if="periods.length" class="space-y-3 p-4">
              <button
                v-for="period in periods"
                :key="period.value"
                type="button"
                @click="selectPeriod(period.value)"
                class="w-full rounded-2xl border px-4 py-4 text-left transition"
                :class="selectedPeriod === period.value
                  ? 'border-slate-900 bg-slate-900 text-white shadow-sm'
                  : 'border-slate-200 bg-white text-slate-800 hover:border-slate-300 hover:bg-slate-50'"
              >
                <div class="flex items-start gap-3">
                  <div
                    class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl"
                    :class="selectedPeriod === period.value ? 'bg-white/10' : 'bg-amber-50'"
                  >
                    <svg
                      class="h-5 w-5"
                      :class="selectedPeriod === period.value ? 'text-white' : 'text-amber-600'"
                      viewBox="0 0 24 24"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="1.8"
                    >
                      <path d="M3 7a2 2 0 0 1 2-2h5l2 2h7a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7z" />
                    </svg>
                  </div>

                  <div class="font-semibold">
                    {{ period.label }}
                  </div>
                </div>
              </button>
            </div>

            <div v-else class="p-8 text-center">
              <div class="text-base font-semibold text-slate-900">No period folders yet</div>
              <div class="mt-2 text-sm text-slate-600">
                Upload a file to create the first period for {{ selectedYear }}.
              </div>
              <button
                type="button"
                @click="openUpload"
                class="mt-4 inline-flex items-center justify-center rounded-xl bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-800"
              >
                Upload File
              </button>
            </div>
          </div>
        </div>

        <div class="space-y-6 xl:col-span-8">
          <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
              <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                  <h2 class="text-base font-semibold text-slate-900">
                    <template v-if="showFilesSection">
                      {{ selectedCategoryLabel }} / {{ selectedRecordTypeLabel }} / {{ selectedYear }} / {{ selectedPeriodLabel }}
                    </template>
                    <template v-else-if="selectedRecordType">
                      {{ selectedCategoryLabel }} / {{ selectedRecordTypeLabel }}
                    </template>
                    <template v-else>
                      {{ selectedCategoryLabel }}
                    </template>
                  </h2>

                  <p class="mt-1 text-sm text-slate-600">
                    <template v-if="showFilesSection">
                      Files inside the selected folder are shown below.
                    </template>
                    <template v-else-if="selectedRecordType && !selectedYear">
                      Select a year folder from the left panel.
                    </template>
                    <template v-else-if="selectedRecordType && selectedYear && !selectedPeriod">
                      Select a period folder from the left panel.
                    </template>
                    <template v-else>
                      Select a record type folder to continue.
                    </template>
                  </p>
                </div>

                <button
                  v-if="canOpenUpload"
                  type="button"
                  @click="openUpload"
                  class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-800"
                >
                  Upload
                </button>
              </div>
            </div>

            <div v-if="showFilesSection" class="border-b border-slate-200 px-5 py-4">
              <div class="grid grid-cols-1 gap-3 md:grid-cols-12">
                <div class="md:col-span-8">
                  <label class="text-xs font-medium text-slate-600">Search</label>
                  <input
                    v-model="search"
                    type="text"
                    placeholder="Search file, remarks, uploader..."
                    class="mt-1 w-full rounded-xl border border-slate-200 px-4 py-2 transition focus:outline-none focus:ring-2 focus:ring-slate-300"
                  />
                </div>

                <div class="md:col-span-4">
                  <label class="text-xs font-medium text-slate-600">Sort</label>
                  <select
                    v-model="sort"
                    class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 transition focus:outline-none focus:ring-2 focus:ring-slate-300"
                  >
                    <option
                      v-for="option in sortOptions"
                      :key="option.value"
                      :value="option.value"
                    >
                      {{ option.label }}
                    </option>
                  </select>
                </div>
              </div>
            </div>

            <div v-if="!selectedRecordType" class="p-10 text-center">
              <div class="text-lg font-semibold text-slate-900">Select a record type folder</div>
              <div class="mt-2 text-sm text-slate-600">
                Start with Target or Accomplishment under {{ selectedCategoryLabel }}.
              </div>
            </div>

            <div v-else-if="selectedRecordType && !selectedYear" class="p-10 text-center">
              <div class="text-lg font-semibold text-slate-900">Select a year folder</div>
              <div class="mt-2 text-sm text-slate-600">
                Choose a year from the left panel or create one using the upload button.
              </div>
            </div>

            <div v-else-if="selectedRecordType && selectedYear && !selectedPeriod" class="p-10 text-center">
              <div class="text-lg font-semibold text-slate-900">Select a period folder</div>
              <div class="mt-2 text-sm text-slate-600">
                Choose January–June or July–December from the left panel.
              </div>
            </div>

            <div v-else-if="!fileItems.length" class="p-10 text-center">
              <div class="text-lg font-semibold text-slate-900">No files yet</div>
              <div class="mt-2 text-sm text-slate-600">
                Upload the first file inside this period folder.
              </div>
              <button
                type="button"
                @click="openUpload"
                class="mt-4 inline-flex items-center justify-center rounded-xl bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-800"
              >
                Upload First File
              </button>
            </div>

            <div v-else class="space-y-4 p-5">
              <div
                v-for="file in fileItems"
                :key="file.id"
                class="rounded-2xl border border-slate-200 bg-slate-50 p-4 transition hover:border-slate-300 hover:bg-white"
              >
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                  <div class="min-w-0 flex-1">
                    <div class="flex min-w-0 items-center gap-2">
                      <span
                        class="inline-flex shrink-0 items-center rounded-md px-1.5 py-[2px] text-[10px] font-bold tracking-wide ring-1"
                        :class="getFileTypeClass(file.file_name)"
                      >
                        {{ getFileTypeLabel(file.file_name) }}
                      </span>

                      <div class="truncate text-sm font-semibold text-slate-900" :title="file.file_name">
                        {{ file.file_name }}
                      </div>
                    </div>

                    <div class="mt-3 grid grid-cols-1 gap-2 text-sm text-slate-600 sm:grid-cols-2">
                      <div>
                        <span class="font-medium text-slate-700">Uploaded by:</span>
                        {{ file.uploaded_by_name }}
                      </div>

                      <div>
                        <span class="font-medium text-slate-700">Date:</span>
                        {{ formatDate(file.created_at) }}
                      </div>

                      <div v-if="file.remarks" class="sm:col-span-2">
                        <span class="font-medium text-slate-700">Remarks:</span>
                        {{ file.remarks }}
                      </div>
                    </div>
                  </div>

                  <div class="flex flex-wrap items-center gap-2 lg:justify-end">
                    <a
                      :href="file.preview_url"
                      target="_blank"
                      rel="noopener"
                      class="inline-flex items-center justify-center rounded-lg bg-slate-900 px-3 py-1.5 text-xs text-white transition hover:bg-slate-800"
                    >
                      Preview
                    </a>

                    <a
                      :href="file.download_url"
                      class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs text-slate-700 transition hover:bg-slate-50"
                    >
                      Download
                    </a>
                  </div>
                </div>
              </div>
            </div>

            <div
              v-if="showFilesSection && fileLastPage > 1"
              class="border-t border-slate-200 bg-white px-5 py-4"
            >
              <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="text-sm text-slate-600">
                  Showing {{ fileFrom }} to {{ fileTo }} of {{ fileTotal }} files
                </div>

                <div class="flex flex-wrap items-center gap-2">
                  <button
                    v-for="(link, index) in fileLinks"
                    :key="`${index}-${link.label}`"
                    type="button"
                    :disabled="!link.url || link.active"
                    @click="goToPage(link.url)"
                    class="rounded-lg border px-3 py-1.5 text-sm transition"
                    :class="link.active
                      ? 'border-slate-900 bg-slate-900 text-white'
                      : link.url
                        ? 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'
                        : 'cursor-not-allowed border-slate-200 bg-slate-100 text-slate-400'"
                    v-html="link.label"
                  />
                </div>
              </div>
            </div>

            <div class="border-t border-slate-200 bg-slate-50 px-6 py-3 text-xs text-slate-500">
              Performance files are organized as folders:
              Category → Record Type → Year → Period → Files
            </div>
          </div>
        </div>
      </div>

      <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div v-if="showUploadModal" class="fixed inset-0 z-[999]">
          <div
            class="absolute inset-0 bg-slate-900/50"
            @click="closeUpload()"
          ></div>

          <div class="absolute inset-0 flex items-center justify-center p-4">
            <div class="w-full max-w-lg overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl">
              <div class="flex items-center justify-between bg-gradient-to-r from-slate-900 to-slate-800 px-6 py-4">
                <div>
                  <div class="text-xs text-slate-300">Upload under</div>
                  <div class="font-semibold text-white">
                    {{ selectedCategoryLabel }}
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

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                  <div>
                    <label class="text-xs font-medium text-slate-600">Record Type</label>
                    <select
                      v-model="uploadForm.performance_record_type"
                      class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 transition focus:outline-none focus:ring-2 focus:ring-slate-300"
                    >
                      <option
                        v-for="recordType in recordTypeOptions"
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
                      <option
                        v-for="year in yearOptions"
                        :key="year"
                        :value="year"
                      >
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
                      <option
                        v-for="period in periodOptions"
                        :key="period.value"
                        :value="period.value"
                      >
                        {{ period.label }}
                      </option>
                    </select>
                  </div>
                </div>

                <div>
                  <label class="text-xs font-medium text-slate-600">Select Files</label>
                  <input
                    ref="fileInput"
                    type="file"
                    multiple
                    @change="onPickFile"
                    class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 transition focus:outline-none focus:ring-2 focus:ring-slate-300"
                    accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.png,.jpg,.jpeg,.gif,.webp"
                  />

                  <p class="mt-2 text-xs text-slate-500">
                    Allowed: PDF, Word, Excel, images. Multiple files are allowed in the same semestral folder.
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
                  </div>
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
                  :disabled="uploading"
                  class="rounded-xl bg-slate-900 px-4 py-2 text-sm text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
                >
                  <span v-if="!uploading">Upload</span>
                  <span v-else>Uploading...</span>
                </button>
              </div>
            </div>
          </div>
        </div>
      </Transition>
    </div>
  </AdminLayout>
</template>