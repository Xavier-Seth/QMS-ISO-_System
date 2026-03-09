<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { Link, router } from '@inertiajs/vue3'
import { ref, computed, watch } from 'vue'
import { useLoadingOverlay } from '@/Composables/useLoadingOverlay'
import { useToast } from '@/Composables/useToast'

const props = defineProps({
  documentType: Object,
  documents: Array,
})

const loading = useLoadingOverlay()
const toast = useToast()

/* ===============================
   Document rules (R-QMS vs F-QMS)
   - R-QMS: typically no revision control
   - F-QMS: revision + status (Active/Obsolete)
================================ */
const requiresRevision = computed(() => {
  if (props.documentType && typeof props.documentType.requires_revision === 'boolean') {
    return props.documentType.requires_revision
  }

  const code = (props.documentType?.code || '').toUpperCase()
  return code.startsWith('F-QMS')
})

/* ===============================
   Search + Filters
================================ */
const search = ref('')
const statusFilter = ref('All')

watch(requiresRevision, (val) => {
  if (!val) statusFilter.value = 'All'
})

const filteredDocuments = computed(() => {
  let docs = [...(props.documents || [])]

  if (search.value) {
    const keyword = search.value.toLowerCase()
    docs = docs.filter((doc) => {
      const fileHit = (doc.file_name || '').toLowerCase().includes(keyword)
      const revHit = requiresRevision.value
        ? (doc.revision || '').toLowerCase().includes(keyword)
        : false

      return fileHit || revHit
    })
  }

  if (requiresRevision.value && statusFilter.value !== 'All') {
    docs = docs.filter((doc) => doc.status === statusFilter.value)
  }

  return docs
})

/* ===============================
   Upload Modal
================================ */
const showUploadModal = ref(false)
const uploading = ref(false)
const uploadError = ref('')

const uploadForm = ref({
  file: null,
  revision: '',
  remarks: '',
})

function openUpload() {
  uploadError.value = ''
  uploadForm.value = {
    file: null,
    revision: '',
    remarks: '',
  }
  showUploadModal.value = true
}

function closeUpload() {
  if (uploading.value) return
  showUploadModal.value = false
}

function onPickFile(e) {
  uploadForm.value.file = e.target.files?.[0] || null
}

function submitUpload() {
  uploadError.value = ''

  if (!uploadForm.value.file) {
    uploadError.value = 'Please choose a file to upload.'
    toast.error(uploadError.value)
    return
  }

  if (requiresRevision.value && !uploadForm.value.revision.trim()) {
    uploadError.value = 'Revision is required for this document type.'
    toast.error(uploadError.value)
    return
  }

  const data = new FormData()
  data.append('file', uploadForm.value.file)

  if (requiresRevision.value) {
    data.append('revision', uploadForm.value.revision.trim())
  }

  data.append('remarks', uploadForm.value.remarks || '')

  uploading.value = true
  loading.open('Uploading file...')

  router.post(`/documents/${props.documentType.id}/upload`, data, {
    forceFormData: true,
    preserveScroll: true,
    onError: (errors) => {
      uploadError.value =
        errors.file ||
        errors.revision ||
        errors.remarks ||
        'Upload failed. Please try again.'

      toast.error(uploadError.value)
    },
    onSuccess: () => {
      closeUpload()
      toast.success('File uploaded successfully.')
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

const activeCount = computed(() =>
  (props.documents || []).filter((d) => d.status === 'Active').length
)

const obsoleteCount = computed(() =>
  (props.documents || []).filter((d) => d.status === 'Obsolete').length
)

const tableColspan = computed(() => (requiresRevision.value ? 6 : 5))
</script>

<template>
  <AdminLayout>
    <div class="space-y-6 p-6">
      <!-- ================= HEADER ================= -->
      <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="bg-gradient-to-r from-slate-900 to-slate-800 px-6 py-6">
          <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <!-- LEFT -->
            <div class="min-w-0">
              <div class="flex flex-wrap items-center gap-3">
                <span class="rounded-md bg-white/10 px-3 py-1 text-xs font-semibold text-white">
                  {{ documentType?.code }}
                </span>

                <span class="rounded-full bg-indigo-500/20 px-2 py-1 text-xs text-indigo-200 ring-1 ring-indigo-400/30">
                  {{ documentType?.file_type }}
                </span>

                <span
                  v-if="requiresRevision"
                  class="rounded-full bg-emerald-500/15 px-2 py-1 text-xs text-emerald-200 ring-1 ring-emerald-400/30"
                  title="This document type uses revision control"
                >
                  Revision Controlled
                </span>
              </div>

              <h1 class="mt-3 truncate text-2xl font-semibold text-white">
                {{ documentType?.name }}
              </h1>

              <p class="mt-1 text-sm text-slate-300">
                View all uploaded files under this document code.
              </p>
            </div>

            <!-- MIDDLE -->
            <div class="w-full lg:w-[520px]">
              <div class="grid grid-cols-1 gap-2 sm:grid-cols-12">
                <div :class="requiresRevision ? 'sm:col-span-8' : 'sm:col-span-12'">
                  <div class="relative">
                    <input
                      v-model="search"
                      type="text"
                      :placeholder="requiresRevision ? 'Search file or revision...' : 'Search file...'"
                      class="w-full rounded-xl border border-white/15 bg-white/10 px-4 py-2.5 pr-10 text-white placeholder:text-slate-300 focus:outline-none focus:ring-2 focus:ring-white/20"
                    />
                    <div class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-200">
                      🔍
                    </div>
                  </div>
                </div>

                <div v-if="requiresRevision" class="sm:col-span-4">
                  <select
                    v-model="statusFilter"
                    class="w-full rounded-xl border border-white/15 bg-white/10 px-3 py-2.5 text-white focus:outline-none focus:ring-2 focus:ring-white/20"
                  >
                    <option class="text-slate-900" value="All">All</option>
                    <option class="text-slate-900" value="Active">Active</option>
                    <option class="text-slate-900" value="Obsolete">Obsolete</option>
                  </select>
                </div>
              </div>
            </div>

            <!-- RIGHT -->
            <div class="flex items-center justify-end gap-2">
              <Link
                href="/documents"
                class="rounded-lg border border-white/10 bg-white/10 px-3 py-2 text-sm text-white hover:bg-white/15"
              >
                Back
              </Link>

              <button
                type="button"
                @click="openUpload"
                class="rounded-lg bg-indigo-500 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-400"
              >
                {{ requiresRevision ? 'Upload New Revision' : 'Upload File' }}
              </button>
            </div>
          </div>
        </div>

        <!-- Stats -->
        <div class="flex flex-wrap gap-6 border-t border-slate-200 px-6 py-4 text-sm text-slate-600">
          <div>
            Total:
            <span class="font-semibold text-slate-900">
              {{ (documents || []).length }}
            </span>
          </div>

          <template v-if="requiresRevision">
            <div>
              Active:
              <span class="font-semibold text-emerald-600">
                {{ activeCount }}
              </span>
            </div>

            <div>
              Obsolete:
              <span class="font-semibold text-rose-600">
                {{ obsoleteCount }}
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
        v-if="!(documents || []).length"
        class="rounded-2xl border border-slate-200 bg-white p-10 text-center"
      >
        <div class="text-lg font-semibold text-slate-900">No uploads yet</div>
        <div class="mt-2 text-sm text-slate-600">
          Start by uploading the first file for this document type.
        </div>

        <button
          type="button"
          @click="openUpload"
          class="mt-4 inline-block rounded-xl bg-slate-900 px-4 py-2 text-sm text-white hover:bg-slate-800"
        >
          {{ requiresRevision ? 'Upload First Revision' : 'Upload File' }}
        </button>
      </div>

      <!-- TABLE -->
      <div
        v-if="(documents || []).length"
        class="overflow-hidden rounded-2xl border border-slate-200 bg-white"
      >
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
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
                <th class="px-5 py-3 font-semibold text-slate-700">Date</th>
                <th class="px-5 py-3 text-right font-semibold text-slate-700">Actions</th>
              </tr>
            </thead>

            <tbody>
              <tr
                v-for="doc in filteredDocuments"
                :key="doc.id"
                class="border-b border-slate-100 transition hover:bg-slate-50"
              >
                <td v-if="requiresRevision" class="px-5 py-4 font-medium text-slate-900">
                  {{ doc.revision || '—' }}
                </td>

                <td class="px-5 py-4 text-slate-800">
                  {{ doc.file_name }}
                </td>

                <td v-if="requiresRevision" class="px-5 py-4">
                  <span class="rounded-full px-2 py-1 text-xs ring-1" :class="statusClass(doc.status)">
                    {{ doc.status }}
                  </span>
                </td>

                <td class="px-5 py-4 text-slate-600">
                  {{ doc.uploaded_by_name }}
                </td>

                <td class="px-5 py-4 text-slate-600">
                  {{ formatDate(doc.created_at) }}
                </td>

                <td class="space-x-2 px-5 py-4 text-right">
                  <Link
                    v-if="!requiresRevision && doc.ofi_record_id"
                    :href="`/ofi-form?record=${doc.ofi_record_id}`"
                    class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs text-white hover:bg-indigo-500"
                    title="Edit this OFI record"
                  >
                    Edit
                  </Link>

                  <a
                    :href="doc.preview_url || doc.file_url"
                    target="_blank"
                    rel="noopener"
                    class="rounded-lg bg-slate-900 px-3 py-1.5 text-xs text-white hover:bg-slate-800"
                  >
                    View
                  </a>

                  <a
                    :href="doc.download_url || doc.file_url"
                    class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs text-slate-700 hover:bg-slate-50"
                  >
                    Download
                  </a>
                </td>
              </tr>

              <tr v-if="!filteredDocuments.length">
                <td :colspan="tableColspan" class="px-5 py-6 text-center text-sm text-slate-500">
                  No matching files found.
                </td>
              </tr>
            </tbody>
          </table>
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
      <div v-if="showUploadModal" class="fixed inset-0 z-[999]">
        <div class="absolute inset-0 bg-slate-900/50" @click="closeUpload"></div>

        <div class="absolute inset-0 flex items-center justify-center p-4">
          <div class="w-full max-w-lg overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl">
            <div class="flex items-center justify-between bg-gradient-to-r from-slate-900 to-slate-800 px-6 py-4">
              <div>
                <div class="text-xs text-slate-300">Upload under</div>
                <div class="font-semibold text-white">
                  {{ documentType?.code }} — {{ documentType?.name }}
                </div>
              </div>

              <button
                type="button"
                @click="closeUpload"
                class="text-slate-200 hover:text-white"
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
                  type="file"
                  @change="onPickFile"
                  class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 focus:outline-none focus:ring-2 focus:ring-slate-300"
                  accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg"
                />
                <p class="mt-2 text-xs text-slate-500">
                  Allowed: PDF, Word, Excel, images.
                </p>
              </div>

              <div class="grid grid-cols-1 gap-3 md:grid-cols-12">
                <div v-if="requiresRevision" class="md:col-span-4">
                  <label class="text-xs font-medium text-slate-600">Revision</label>
                  <input
                    v-model="uploadForm.revision"
                    type="text"
                    placeholder="e.g., Rev. 1"
                    class="mt-2 w-full rounded-xl border border-slate-200 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-slate-300"
                  />
                </div>

                <div :class="requiresRevision ? 'md:col-span-8' : 'md:col-span-12'">
                  <label class="text-xs font-medium text-slate-600">Remarks (optional)</label>
                  <input
                    v-model="uploadForm.remarks"
                    type="text"
                    placeholder="Short note about this upload"
                    class="mt-2 w-full rounded-xl border border-slate-200 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-slate-300"
                  />
                </div>
              </div>
            </div>

            <div class="flex items-center justify-between border-t border-slate-200 bg-slate-50 px-6 py-4">
              <button
                type="button"
                @click="closeUpload"
                :disabled="uploading"
                class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
              >
                Cancel
              </button>

              <button
                type="button"
                @click="submitUpload"
                :disabled="uploading"
                class="rounded-xl bg-slate-900 px-4 py-2 text-sm text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
              >
                <span v-if="!uploading">Upload</span>
                <span v-else>Uploading...</span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>