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
})

const loading = useLoadingOverlay()
const toast = useToast()

/* ===============================
   Document rules (R-QMS vs F-QMS)
================================ */
const requiresRevision = computed(() => {
  if (props.documentType && typeof props.documentType.requires_revision === 'boolean') {
    return props.documentType.requires_revision
  }

  const code = (props.documentType?.code || '').toUpperCase()
  return code.startsWith('F-QMS')
})

/* ===============================
   Search + Filters (server-side)
================================ */
const search = ref(props.filters?.q ?? '')
const statusFilter = ref(props.filters?.status ?? 'All')
const sort = ref(props.filters?.sort ?? 'latest')
const perPage = ref(String(props.filters?.per_page ?? 10))
const dateFrom = ref(props.filters?.date_from ?? '')
const dateTo = ref(props.filters?.date_to ?? '')

const searchPlaceholder = computed(() => {
  return requiresRevision.value
    ? 'Search file, revision, remarks, uploader...'
    : 'Search file, remarks, uploader...'
})

watch(requiresRevision, (val) => {
  if (!val) statusFilter.value = 'All'
})

function reloadDocuments(extra = {}) {
  router.get(
    `/documents/${props.documentType.id}`,
    {
      q: search.value || undefined,
      status: requiresRevision.value ? statusFilter.value : undefined,
      sort: sort.value || 'latest',
      per_page: perPage.value || 10,
      date_from: dateFrom.value || undefined,
      date_to: dateTo.value || undefined,
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
  if (!requiresRevision.value) return
  reloadDocuments({ page: 1 })
})

watch(sort, () => reloadDocuments({ page: 1 }))
watch(perPage, () => reloadDocuments({ page: 1 }))
watch(dateFrom, () => reloadDocuments({ page: 1 }))
watch(dateTo, () => reloadDocuments({ page: 1 }))

/* ===============================
   Upload Modal
================================ */
const showUploadModal = ref(false)
const uploading = ref(false)
const uploadError = ref('')
const fileInput = ref(null)

const uploadForm = ref({
  files: [],
  revision: '',
})

function resetUploadForm() {
  uploadError.value = ''
  uploadForm.value = {
    files: [],
    revision: '',
  }

  if (fileInput.value) {
    fileInput.value.value = ''
  }
}

function openUpload() {
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
  uploadError.value = ''

  if (!uploadForm.value.files.length) {
    uploadError.value = 'Please choose at least one file to upload.'
    toast.error(uploadError.value)
    return
  }

  if (requiresRevision.value && uploadForm.value.files.length > 1) {
    uploadError.value = 'Multiple upload is not allowed for revision-controlled documents.'
    toast.error(uploadError.value)
    return
  }

  if (requiresRevision.value && !uploadForm.value.revision.trim()) {
    uploadError.value = 'Revision is required for this document type.'
    toast.error(uploadError.value)
    return
  }

  const data = new FormData()

  uploadForm.value.files.forEach((file) => {
    data.append('files[]', file)
  })

  if (requiresRevision.value) {
    data.append('revision', uploadForm.value.revision.trim())
  }

  uploading.value = true
  loading.open('Uploading file...')

  router.post(`/documents/${props.documentType.id}/upload`, data, {
    forceFormData: true,
    preserveScroll: true,
    onError: (errors) => {
      uploadError.value =
        errors.files ||
        errors['files.0'] ||
        errors.revision ||
        'Upload failed. Please try again.'

      toast.error(uploadError.value)
    },
    onSuccess: () => {
      const uploadedCount = uploadForm.value.files.length

      closeUpload(true)

      toast.success(
        uploadedCount > 1
          ? 'Files uploaded successfully.'
          : 'File uploaded successfully.'
      )
    },
    onFinish: () => {
      uploading.value = false
      loading.close()
    },
  })
}

/* ===============================
   Helpers
================================ */
function formatDate(date) {
  if (!date) return '—'
  return new Date(date).toLocaleString()
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

/* ===============================
   File Type Indicator
================================ */
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
      <!-- HEADER -->
      <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="bg-gradient-to-r from-slate-900 to-slate-800 px-5 py-5 sm:px-6">
          <div class="flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
            <!-- LEFT -->
            <div class="min-w-0 flex-1 xl:max-w-[42%]">
              <div class="flex flex-wrap items-center gap-2">
                <span class="rounded-md bg-white/10 px-2.5 py-1 text-[11px] font-semibold tracking-wide text-white">
                  {{ documentType?.code }}
                </span>

                <span
                  v-if="requiresRevision"
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
              </div>

              <h1 class="mt-3 text-xl font-semibold tracking-tight text-white sm:text-[22px]">
                {{ documentType?.name }}
              </h1>

              <p class="mt-1 max-w-xl text-sm leading-6 text-slate-300">
                View all uploaded files under this document code.
              </p>
            </div>

            <!-- RIGHT -->
            <div class="w-full xl:max-w-[720px]">
              <div class="space-y-3">
                <!-- Main toolbar -->
                <div class="grid grid-cols-1 gap-3 md:grid-cols-12">
                  <div :class="requiresRevision ? 'md:col-span-6' : 'md:col-span-7'">
                    <div class="relative">
                      <input
                        v-model="search"
                        type="text"
                        :placeholder="searchPlaceholder"
                        class="w-full rounded-lg border border-white/15 bg-white/10 px-3.5 py-2.5 pr-10 text-sm text-white placeholder:text-slate-300 transition focus:outline-none focus:ring-2 focus:ring-white/20"
                      />
                      <div class="absolute right-3 top-1/2 -translate-y-1/2 text-sm text-slate-200">
                        🔍
                      </div>
                    </div>
                  </div>

                  <div class="md:col-span-3">
                    <select
                      v-model="sort"
                      class="w-full rounded-lg border border-white/15 bg-white/10 px-3 py-2.5 text-sm text-white transition focus:outline-none focus:ring-2 focus:ring-white/20"
                    >
                      <option class="text-slate-900" value="latest">Newest First</option>
                      <option class="text-slate-900" value="oldest">Oldest First</option>
                      <option class="text-slate-900" value="name_asc">File Name (A-Z)</option>
                      <option class="text-slate-900" value="name_desc">File Name (Z-A)</option>
                      <option
                        v-if="requiresRevision"
                        class="text-slate-900"
                        value="revision_asc"
                      >
                        Revision (A-Z)
                      </option>
                      <option
                        v-if="requiresRevision"
                        class="text-slate-900"
                        value="revision_desc"
                      >
                        Revision (Z-A)
                      </option>
                    </select>
                  </div>

                  <div v-if="requiresRevision" class="md:col-span-3">
                    <select
                      v-model="statusFilter"
                      class="w-full rounded-lg border border-white/15 bg-white/10 px-3 py-2.5 text-sm text-white transition focus:outline-none focus:ring-2 focus:ring-white/20"
                    >
                      <option class="text-slate-900" value="All">All Status</option>
                      <option class="text-slate-900" value="Active">Active</option>
                      <option class="text-slate-900" value="Obsolete">Obsolete</option>
                    </select>
                  </div>

                  <div v-else class="md:col-span-2">
                    <div class="flex h-full items-center justify-center rounded-lg border border-white/10 bg-white/5 px-3 py-2.5 text-sm text-slate-300">
                      Records
                    </div>
                  </div>
                </div>

                <!-- Secondary filters -->
                <div class="grid grid-cols-1 gap-3 md:grid-cols-12">
                  <div class="md:col-span-4">
                    <label class="mb-1 block text-[11px] font-medium uppercase tracking-wide text-slate-300">
                      From Date
                    </label>
                    <input
                      v-model="dateFrom"
                      type="date"
                      class="w-full rounded-lg border border-white/15 bg-white/10 px-3 py-2.5 text-sm text-white transition focus:outline-none focus:ring-2 focus:ring-white/20"
                    />
                  </div>

                  <div class="md:col-span-4">
                    <label class="mb-1 block text-[11px] font-medium uppercase tracking-wide text-slate-300">
                      To Date
                    </label>
                    <input
                      v-model="dateTo"
                      type="date"
                      class="w-full rounded-lg border border-white/15 bg-white/10 px-3 py-2.5 text-sm text-white transition focus:outline-none focus:ring-2 focus:ring-white/20"
                    />
                  </div>

                  <div class="md:col-span-4">
                    <div class="mb-1 block text-[11px] font-medium uppercase tracking-wide text-transparent">
                      Actions
                    </div>

                    <div class="flex flex-wrap items-center justify-end gap-2">
                      <Link
                        href="/documents"
                        class="inline-flex items-center justify-center rounded-lg border border-white/10 bg-white/10 px-3 py-2.5 text-sm text-white transition hover:bg-white/15"
                      >
                        Back
                      </Link>

                      <button
                        type="button"
                        @click="openUpload"
                        class="inline-flex items-center justify-center rounded-lg bg-indigo-500 px-3.5 py-2.5 text-sm font-medium text-white transition-all duration-200 hover:-translate-y-[1px] hover:bg-indigo-400"
                      >
                        {{ requiresRevision ? 'Upload' : 'Upload Files' }}
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Stats -->
        <div class="flex flex-wrap items-center gap-x-5 gap-y-2 border-t border-slate-200 px-5 py-3 text-sm text-slate-600 sm:px-6">
          <div>
            Total:
            <span class="font-semibold text-slate-900">
              {{ stats?.total ?? 0 }}
            </span>
          </div>

          <template v-if="requiresRevision">
            <div>
              Active:
              <span class="font-semibold text-emerald-600">
                {{ stats?.active ?? 0 }}
              </span>
            </div>

            <div>
              Obsolete:
              <span class="font-semibold text-rose-600">
                {{ stats?.obsolete ?? 0 }}
              </span>
            </div>
          </template>

          <template v-else>
            <div class="text-xs text-slate-500">
              Note: This type is treated as a record (no revision control).
            </div>
          </template>
        </div>
      </div>

      <!-- EMPTY -->
      <div
        v-if="!(documents?.data || []).length"
        class="rounded-2xl border border-slate-200 bg-white p-10 text-center"
      >
        <div class="text-lg font-semibold text-slate-900">No uploads yet</div>
        <div class="mt-2 text-sm text-slate-600">
          Start by uploading the first file for this document type.
        </div>

        <button
          type="button"
          @click="openUpload"
          class="mt-4 inline-block rounded-xl bg-slate-900 px-4 py-2 text-sm text-white transition hover:bg-slate-800"
        >
          {{ requiresRevision ? 'Upload First Revision' : 'Upload Files' }}
        </button>
      </div>

      <!-- TABLE -->
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
                <th class="px-5 py-3 font-semibold text-slate-700 whitespace-nowrap">Date</th>
                <th class="px-5 py-3 text-right font-semibold text-slate-700 whitespace-nowrap">
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
                  class="px-5 py-4 font-medium text-slate-900 whitespace-nowrap align-middle"
                >
                  {{ doc.revision || '—' }}
                </td>

                <td class="px-5 py-4 text-slate-800 align-middle">
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

                <td v-if="requiresRevision" class="px-5 py-4 align-middle whitespace-nowrap">
                  <span
                    class="rounded-full px-2 py-1 text-xs ring-1 transition"
                    :class="statusClass(doc.status)"
                  >
                    {{ doc.status }}
                  </span>
                </td>

                <td class="px-5 py-4 text-slate-600 align-middle">
                  <div class="line-clamp-2">
                    {{ doc.uploaded_by_name }}
                  </div>
                </td>

                <td class="px-5 py-4 text-slate-600 align-middle whitespace-nowrap">
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

                    <a
                      :href="doc.preview_url || doc.file_url"
                      target="_blank"
                      rel="noopener"
                      class="inline-flex items-center justify-center rounded-lg bg-slate-900 px-3 py-1.5 text-xs text-white transition hover:bg-slate-800"
                    >
                      View
                    </a>

                    <a
                      :href="doc.download_url || doc.file_url"
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

        <!-- Pagination -->
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

      <!-- UPLOAD MODAL -->
      <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div v-if="showUploadModal" class="fixed inset-0 z-[999]">
          <!-- Backdrop -->
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

          <!-- Modal -->
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

                  <div>
                    <label class="text-xs font-medium text-slate-600">Select File</label>
                    <input
                      ref="fileInput"
                      type="file"
                      @change="onPickFile"
                      :multiple="!requiresRevision"
                      class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 transition focus:outline-none focus:ring-2 focus:ring-slate-300"
                      accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg"
                    />

                    <p class="mt-2 text-xs text-slate-500">
                      <template v-if="requiresRevision">
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

                  <div v-if="requiresRevision">
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
                    :disabled="uploading"
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