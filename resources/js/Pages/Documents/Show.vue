<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { Link, router } from '@inertiajs/vue3'
import { ref, computed, watch } from 'vue'

const props = defineProps({
  documentType: Object,
  documents: Array,
})

/* ===============================
   Document rules (R-QMS vs F-QMS)
   - R-QMS: typically no revision control
   - F-QMS: revision + status (Active/Obsolete)
================================ */
const requiresRevision = computed(() => {
  // Prefer backend flag if you add it later: documentType.requires_revision
  if (props.documentType && typeof props.documentType.requires_revision === 'boolean') {
    return props.documentType.requires_revision
  }
  // Fallback rule if you don't have the flag yet:
  const code = (props.documentType?.code || '').toUpperCase()
  return code.startsWith('F-QMS')
})

/* ===============================
   Search + Filters
================================ */
const search = ref('')
const statusFilter = ref('All') // All | Active | Obsolete (only when requiresRevision)

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
    docs = docs.filter(doc => doc.status === statusFilter.value)
  }

  return docs
})

/* ===============================
   Upload Modal (WORKING upload)
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
  uploadForm.value = { file: null, revision: '', remarks: '' }
  showUploadModal.value = true
}

function closeUpload() {
  showUploadModal.value = false
}

function onPickFile(e) {
  uploadForm.value.file = e.target.files?.[0] || null
}

function submitUpload() {
  uploadError.value = ''

  if (!uploadForm.value.file) {
    uploadError.value = 'Please choose a file to upload.'
    return
  }

  // ✅ Enforce revision for F-QMS
  if (requiresRevision.value && !uploadForm.value.revision.trim()) {
    uploadError.value = 'Revision is required for this document type.'
    return
  }

  const data = new FormData()
  data.append('file', uploadForm.value.file)
  if (requiresRevision.value) data.append('revision', uploadForm.value.revision.trim())
  data.append('remarks', uploadForm.value.remarks || '')

  uploading.value = true

  router.post(`/documents/${props.documentType.id}/upload`, data, {
    forceFormData: true,
    preserveScroll: true,
    onError: (errors) => {
      uploadError.value =
        errors.file ||
        errors.revision ||
        errors.remarks ||
        'Upload failed. Please try again.'
    },
    onSuccess: () => {
      closeUpload()
    },
    onFinish: () => {
      uploading.value = false
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

const activeCount = computed(() => (props.documents || []).filter(d => d.status === 'Active').length)
const obsoleteCount = computed(() => (props.documents || []).filter(d => d.status === 'Obsolete').length)

const tableColspan = computed(() => (requiresRevision.value ? 6 : 5))
</script>

<template>
  <AdminLayout>
    <div class="p-6 space-y-6">

      <!-- ================= HEADER ================= -->
      <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="px-6 py-6 bg-gradient-to-r from-slate-900 to-slate-800">
          <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <div class="flex items-center gap-3">
                <span class="text-xs font-semibold bg-white/10 text-white px-3 py-1 rounded-md">
                  {{ documentType?.code }}
                </span>

                <span class="text-xs rounded-full px-2 py-1 ring-1 bg-indigo-500/20 text-indigo-200 ring-indigo-400/30">
                  {{ documentType?.file_type }}
                </span>

                <span
                  v-if="requiresRevision"
                  class="text-xs rounded-full px-2 py-1 ring-1 bg-emerald-500/15 text-emerald-200 ring-emerald-400/30"
                  title="This document type uses revision control"
                >
                  Revision Controlled
                </span>
              </div>

              <h1 class="text-2xl font-semibold text-white mt-3">
                {{ documentType?.name }}
              </h1>

              <p class="text-sm text-slate-300 mt-1">
                View all uploaded files under this document code.
              </p>
            </div>

            <div class="flex items-center gap-2">
              <Link
                href="/documents"
                class="px-3 py-2 rounded-lg bg-white/10 hover:bg-white/15 text-white text-sm border border-white/10"
              >
                Back
              </Link>

              <button
                type="button"
                @click="openUpload"
                class="px-4 py-2 rounded-lg bg-indigo-500 hover:bg-indigo-400 text-white text-sm font-medium"
              >
                {{ requiresRevision ? 'Upload New Revision' : 'Upload File' }}
              </button>
            </div>
          </div>
        </div>

        <!-- Stats -->
        <div class="px-6 py-4 border-t border-slate-200 flex flex-wrap gap-6 text-sm text-slate-600">
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

      <!-- ================= SEARCH + FILTER (ALWAYS VISIBLE) ================= -->
      <div class="bg-white rounded-2xl border border-slate-200 p-5">
        <div class="grid grid-cols-1 md:grid-cols-12 gap-4">

          <!-- Search -->
          <div :class="requiresRevision ? 'md:col-span-8' : 'md:col-span-12'">
            <label class="text-xs font-medium text-slate-600">Search File</label>
            <div class="mt-2 relative">
              <input
                v-model="search"
                type="text"
                :placeholder="requiresRevision ? 'Search by file name or revision...' : 'Search by file name...'"
                class="w-full rounded-xl border border-slate-200 px-4 py-2.5 pr-10 focus:outline-none focus:ring-2 focus:ring-slate-300"
              />
              <div class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400">🔍</div>
            </div>
          </div>

          <!-- Status Filter -->
          <div v-if="requiresRevision" class="md:col-span-4">
            <label class="text-xs font-medium text-slate-600">Status</label>
            <select
              v-model="statusFilter"
              class="mt-2 w-full rounded-xl border border-slate-200 px-3 py-2.5 bg-white focus:outline-none focus:ring-2 focus:ring-slate-300"
            >
              <option value="All">All</option>
              <option value="Active">Active</option>
              <option value="Obsolete">Obsolete</option>
            </select>
          </div>

        </div>
      </div>

      <!-- ================= EMPTY STATE ================= -->
      <div
        v-if="!(documents || []).length"
        class="bg-white rounded-2xl border border-slate-200 p-10 text-center"
      >
        <div class="text-slate-900 font-semibold text-lg">No uploads yet</div>
        <div class="text-sm text-slate-600 mt-2">
          Start by uploading the first file for this document type.
        </div>

        <button
          type="button"
          @click="openUpload"
          class="inline-block mt-4 px-4 py-2 rounded-xl bg-slate-900 text-white hover:bg-slate-800 text-sm"
        >
          {{ requiresRevision ? 'Upload First Revision' : 'Upload File' }}
        </button>
      </div>

      <!-- ================= VERSION / FILE TABLE ================= -->
      <div
        v-if="(documents || []).length"
        class="bg-white rounded-2xl border border-slate-200 overflow-hidden"
      >
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead class="bg-slate-50 border-b border-slate-200">
              <tr class="text-left">
                <th v-if="requiresRevision" class="px-5 py-3 font-semibold text-slate-700">Revision</th>
                <th class="px-5 py-3 font-semibold text-slate-700">File Name</th>
                <th v-if="requiresRevision" class="px-5 py-3 font-semibold text-slate-700">Status</th>
                <th class="px-5 py-3 font-semibold text-slate-700">Uploaded By</th>
                <th class="px-5 py-3 font-semibold text-slate-700">Date</th>
                <th class="px-5 py-3 font-semibold text-slate-700 text-right">Actions</th>
              </tr>
            </thead>

            <tbody>
              <tr
                v-for="doc in filteredDocuments"
                :key="doc.id"
                class="border-b border-slate-100 hover:bg-slate-50 transition"
              >
                <td v-if="requiresRevision" class="px-5 py-4 font-medium text-slate-900">
                  {{ doc.revision || '—' }}
                </td>

                <td class="px-5 py-4 text-slate-800">
                  {{ doc.file_name }}
                </td>

                <td v-if="requiresRevision" class="px-5 py-4">
                  <span class="text-xs rounded-full px-2 py-1 ring-1" :class="statusClass(doc.status)">
                    {{ doc.status }}
                  </span>
                </td>

                <td class="px-5 py-4 text-slate-600">
                  {{ doc.uploaded_by_name }}
                </td>

                <td class="px-5 py-4 text-slate-600">
                  {{ formatDate(doc.created_at) }}
                </td>

                <td class="px-5 py-4 text-right space-x-2">
                  <a
                    :href="doc.file_url"
                    target="_blank"
                    class="px-3 py-1.5 rounded-lg bg-slate-900 text-white hover:bg-slate-800 text-xs"
                  >
                    View
                  </a>

                  <a
                    :href="doc.file_url"
                    download
                    class="px-3 py-1.5 rounded-lg border border-slate-200 text-slate-700 hover:bg-slate-50 text-xs"
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

        <div class="px-6 py-3 bg-slate-50 text-xs text-slate-500">
          <template v-if="requiresRevision">
            ISO Document Control: Only one version should remain Active.
            Uploading a new revision should mark previous versions as Obsolete.
          </template>
          <template v-else>
            Record storage: uploads are treated as records (no revision control).
          </template>
        </div>
      </div>

      <!-- ================= UPLOAD MODAL ================= -->
      <div v-if="showUploadModal" class="fixed inset-0 z-[999]">
        <div class="absolute inset-0 bg-slate-900/50" @click="closeUpload"></div>

        <div class="absolute inset-0 flex items-center justify-center p-4">
          <div class="w-full max-w-lg bg-white rounded-2xl shadow-xl border border-slate-200 overflow-hidden">
            <div class="px-6 py-4 bg-gradient-to-r from-slate-900 to-slate-800 flex items-center justify-between">
              <div>
                <div class="text-xs text-slate-300">Upload under</div>
                <div class="text-white font-semibold">
                  {{ documentType?.code }} — {{ documentType?.name }}
                </div>
              </div>

              <button type="button" @click="closeUpload" class="text-slate-200 hover:text-white" aria-label="Close">
                ✕
              </button>
            </div>

            <div class="px-6 py-5 space-y-4">
              <div v-if="uploadError" class="text-sm text-rose-700 bg-rose-50 border border-rose-200 rounded-xl px-4 py-3">
                {{ uploadError }}
              </div>

              <div>
                <label class="text-xs font-medium text-slate-600">Select File</label>
                <input
                  type="file"
                  @change="onPickFile"
                  class="mt-2 w-full rounded-xl border border-slate-200 px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-slate-300"
                  accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg"
                />
                <p class="mt-2 text-xs text-slate-500">
                  Allowed: PDF, Word, Excel, images.
                </p>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
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

            <div class="px-6 py-4 bg-slate-50 border-t border-slate-200 flex items-center justify-between">
              <button
                type="button"
                @click="closeUpload"
                class="px-4 py-2 rounded-xl border border-slate-200 text-slate-700 bg-white hover:bg-slate-50 text-sm"
              >
                Cancel
              </button>

              <button
                type="button"
                @click="submitUpload"
                :disabled="uploading"
                class="px-4 py-2 rounded-xl bg-slate-900 text-white hover:bg-slate-800 text-sm disabled:opacity-60 disabled:cursor-not-allowed"
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