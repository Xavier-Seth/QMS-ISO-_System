<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useForm, router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { useLoadingOverlay } from '@/Composables/useLoadingOverlay'
import { useToast } from '@/Composables/useToast'

const props = defineProps({
  category: String,
  pageTitle: String,
  manuals: Object,
  can: Object,
})

const loading = useLoadingOverlay()
const toast = useToast()

// Tabs
const tabs = computed(() => {
  const all = []
  if (props.can?.view_master_copy || props.can?.upload_master_copy) {
    all.push({ key: 'master_copy', label: 'Master Copy' })
  }
  if (props.can?.view_controlled || props.can?.upload_controlled) {
    all.push({ key: 'controlled', label: 'Controlled' })
  }
  if (props.can?.view_uncontrolled || props.can?.upload_uncontrolled) {
    all.push({ key: 'uncontrolled', label: 'Uncontrolled' })
  }
  return all
})

const hasAnyTab = computed(() => tabs.value.length > 0)

const isAdmin = computed(() => !!props.can?.view_master_copy || !!props.can?.upload_master_copy)

const activeTab = ref(isAdmin.value ? 'master_copy' : 'uncontrolled')

// Per-tab search state
const masterCopySearch = ref('')
const controlledSearch = ref('')
const uncontrolledSearch = ref('')

const activeSearch = computed({
  get() {
    if (activeTab.value === 'master_copy') return masterCopySearch.value
    if (activeTab.value === 'controlled') return controlledSearch.value
    return uncontrolledSearch.value
  },
  set(value) {
    if (activeTab.value === 'master_copy') masterCopySearch.value = value
    else if (activeTab.value === 'controlled') controlledSearch.value = value
    else uncontrolledSearch.value = value
  },
})

const filesFor = (access) => props.manuals?.[access]?.files ?? []

const filterFiles = (access, search) => {
  const files = filesFor(access)
  const q = search.toLowerCase().trim()
  if (!q) return files
  return files.filter((f) => f.file_name?.toLowerCase().includes(q))
}

const masterCopyFiles = computed(() => filterFiles('master_copy', masterCopySearch.value))
const controlledFiles = computed(() => filterFiles('controlled', controlledSearch.value))
const uncontrolledFiles = computed(() => filterFiles('uncontrolled', uncontrolledSearch.value))

const currentFiles = computed(() => {
  if (activeTab.value === 'master_copy') return masterCopyFiles.value
  if (activeTab.value === 'controlled') return controlledFiles.value
  return uncontrolledFiles.value
})

const currentCanUpload = computed(() => {
  if (activeTab.value === 'master_copy') return !!props.can?.upload_master_copy
  if (activeTab.value === 'controlled') return !!props.can?.upload_controlled
  return !!props.can?.upload_uncontrolled
})

const currentCanToggle = computed(() => currentCanUpload.value)

const currentEmptyMessage = computed(() => {
  if (activeSearch.value.trim()) return 'No files match your search.'
  if (activeTab.value === 'uncontrolled') return 'No files have been uploaded yet.'
  return 'Upload a file to get started.'
})

// Upload modal
const showUploadModal = ref(false)
const fileInputKey = ref(0)

const uploadForm = useForm({
  files: [],
  access: '',
  remarks: '',
})

const selectedFiles = ref([])

const fileCount = computed(() => selectedFiles.value.length)

const uploadButtonLabel = computed(() => {
  if (uploadForm.processing) return 'Uploading…'
  if (fileCount.value === 0) return 'Upload Files'
  return fileCount.value === 1 ? 'Upload 1 File' : `Upload ${fileCount.value} Files`
})

const tooManyFiles = computed(() => fileCount.value > 20)

const uploadableAccessTypes = computed(() => {
  const types = []
  if (props.can?.upload_master_copy) types.push({ value: 'master_copy', label: 'Master Copy' })
  if (props.can?.upload_controlled) types.push({ value: 'controlled', label: 'Controlled' })
  if (props.can?.upload_uncontrolled) types.push({ value: 'uncontrolled', label: 'Uncontrolled' })
  return types
})

const openUploadModal = () => {
  uploadForm.reset()
  uploadForm.clearErrors()
  uploadForm.access = activeTab.value
  selectedFiles.value = []
  fileInputKey.value++
  showUploadModal.value = true
}

const closeUploadModal = () => {
  showUploadModal.value = false
  uploadForm.reset()
  uploadForm.clearErrors()
  selectedFiles.value = []
}

const onFileChange = (event) => {
  const picked = Array.from(event.target.files ?? [])
  selectedFiles.value = picked
}

const removeFile = (index) => {
  selectedFiles.value = selectedFiles.value.filter((_, i) => i !== index)
  fileInputKey.value++
}

const submitUpload = () => {
  if (fileCount.value === 0) {
    toast.error?.('Please select at least one file.')
    return
  }
  if (tooManyFiles.value) {
    toast.error?.('You can upload a maximum of 20 files at once.')
    return
  }
  if (!uploadForm.access) {
    toast.error?.('Please select an access type.')
    return
  }

  uploadForm.files = selectedFiles.value

  const label = uploadableAccessTypes.value.find((t) => t.value === uploadForm.access)?.label ?? uploadForm.access
  loading.show?.(`Uploading ${label} manual${fileCount.value > 1 ? 's' : ''}...`)

  uploadForm.post(`/manual/${props.category.toLowerCase()}/${uploadForm.access}/upload`, {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => {
      closeUploadModal()
      toast.success?.(`${fileCount.value === 1 ? '1 file' : `${fileCount.value} files`} uploaded successfully.`)
    },
    onError: () => {
      toast.error?.('Upload failed. Please check the form and try again.')
    },
    onFinish: () => {
      loading.hide?.()
    },
  })
}

// Delete
const deleteModalOpen = ref(false)
const selectedRow = ref(null)
const deletingType = ref(false)

const deleteButtonText = computed(() => {
  if (!deletingType.value) return 'Delete Permanently'
  return 'Deleting...'
})

const openDeleteModal = (item) => {
  selectedRow.value = item
  deleteModalOpen.value = true
}

const closeDeleteModal = () => {
  if (deletingType.value) return
  deleteModalOpen.value = false
}

const submitDelete = () => {
  if (!selectedRow.value || deletingType.value) return

  const row = selectedRow.value
  deletingType.value = true

  router.delete(`/manual/uploads/${row.id}`, {
    preserveScroll: true,
    onSuccess: () => {
      toast.success('File deleted successfully.')
      deleteModalOpen.value = false
      selectedRow.value = null
    },
    onError: (errors) => {
      const firstError = Object.values(errors)[0]
      toast.error(Array.isArray(firstError) ? firstError[0] : firstError)
    },
    onFinish: () => {
      deletingType.value = false
    },
  })
}

// Toggle status
const toggleStatus = (upload) => {
  const next = upload.status === 'Active' ? 'Obsolete' : 'Active'

  router.post(
    `/manual/uploads/${upload.id}/toggle-status`,
    {},
    {
      preserveScroll: true,
      onSuccess: () => {
        toast.success?.(`File marked as ${next}.`)
      },
      onError: () => {
        toast.error?.('Could not update file status.')
      },
    },
  )
}

// Helpers
const previewUrl = (uploadId) => `/manual/uploads/${uploadId}/preview`
const downloadUrl = (uploadId) => `/manual/uploads/${uploadId}/download`

const formatDateTime = (value) => {
  if (!value) return '—'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return value
  return date.toLocaleString()
}

const statusBadgeClass = (status) => {
  if (status === 'Active') return 'bg-emerald-50 text-emerald-700 ring-emerald-200'
  if (status === 'Obsolete') return 'bg-slate-100 text-slate-600 ring-slate-200'
  return 'bg-amber-50 text-amber-700 ring-amber-200'
}

const tabBadgeClass = (key) => {
  if (key === 'master_copy') return 'bg-purple-50 text-purple-700 ring-purple-200'
  if (key === 'controlled') return 'bg-violet-50 text-violet-700 ring-violet-200'
  return 'bg-sky-50 text-sky-700 ring-sky-200'
}
</script>

<template>
  <AdminLayout>
    <div class="space-y-6 p-6">
      <!-- Page header -->
      <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="bg-gradient-to-r from-slate-900 to-slate-800 px-4 py-3">
          <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div>
              <h1 class="text-base font-semibold text-white sm:text-xl">
                {{ pageTitle || 'Manuals' }}
              </h1>
              <p class="mt-1 text-sm text-slate-300">
                Browse the manual file library for
                <span class="font-medium text-white">{{ category }}</span>.
              </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
              <span class="rounded-lg border border-white/10 bg-white/10 px-3 py-1.5 text-xs font-medium text-white">
                Category: {{ category || '—' }}
              </span>
              <span class="rounded-lg border border-white/10 bg-white/10 px-3 py-1.5 text-xs font-medium text-white">
                {{ tabs.length }} section{{ tabs.length === 1 ? '' : 's' }} visible
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- No access -->
      <div
        v-if="!hasAnyTab"
        class="rounded-2xl border border-slate-200 bg-white p-10 text-center shadow-sm"
      >
        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100">
          <svg
            xmlns="http://www.w3.org/2000/svg"
            class="h-7 w-7 text-slate-500"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="1.8"
              d="M9 12h6m-6 4h6M7 3h6.586A2 2 0 0115 3.586L18.414 7A2 2 0 0119 8.414V19a2 2 0 01-2 2H7a2 2 0 01-2-2V5a2 2 0 012-2z"
            />
          </svg>
        </div>
        <h2 class="mt-4 text-lg font-semibold text-slate-900">No manual access</h2>
        <p class="mt-2 text-sm text-slate-600">
          You do not have permission to view any manual in this category.
        </p>
      </div>

      <!-- Tab panel -->
      <div
        v-else
        class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
      >
        <!-- Tab bar -->
        <div class="flex items-center justify-between border-b border-slate-200 px-4">
          <div class="flex gap-1">
            <button
              v-for="tab in tabs"
              :key="tab.key"
              type="button"
              class="relative px-4 py-3.5 text-sm font-medium transition"
              :class="
                activeTab === tab.key
                  ? 'text-slate-900 after:absolute after:bottom-0 after:left-0 after:right-0 after:h-0.5 after:bg-slate-900'
                  : 'text-slate-500 hover:text-slate-700'
              "
              @click="activeTab = tab.key"
            >
              {{ tab.label }}
              <span
                class="ml-1.5 inline-flex rounded-full px-1.5 py-0.5 text-[11px] font-semibold ring-1"
                :class="tabBadgeClass(tab.key)"
              >
                {{ filesFor(tab.key).length }}
              </span>
            </button>
          </div>

          <button
            v-if="currentCanUpload"
            type="button"
            class="my-2 inline-flex items-center justify-center rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800"
            @click="openUploadModal"
          >
            Upload File
          </button>
        </div>

        <!-- Search + table -->
        <div class="p-4">
          <div class="mb-4">
            <input
              v-model="activeSearch"
              type="text"
              placeholder="Search by filename…"
              class="w-full max-w-sm rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-300"
            />
          </div>

          <!-- Files table -->
          <div v-if="currentFiles.length" class="overflow-x-auto">
            <table class="w-full min-w-[480px] text-sm">
              <thead class="border-b border-slate-200 bg-slate-50">
                <tr class="text-left">
                  <th class="px-4 py-3 font-semibold text-slate-700">File Name</th>
                  <th class="px-4 py-3 font-semibold text-slate-700">Status</th>
                  <th class="px-4 py-3 font-semibold text-slate-700">Uploaded By</th>
                  <th class="px-4 py-3 font-semibold text-slate-700">Uploaded Date</th>
                  <th class="px-4 py-3 text-right font-semibold text-slate-700">Actions</th>
                </tr>
              </thead>

              <tbody>
                <tr
                  v-for="item in currentFiles"
                  :key="item.id"
                  class="border-b border-slate-100 align-top transition hover:bg-slate-50"
                >
                  <td class="px-4 py-3">
                    <div class="max-w-[240px] break-words font-medium text-slate-900 md:max-w-[420px]">
                      {{ item.file_name }}
                    </div>
                    <div v-if="item.remarks" class="mt-0.5 text-xs text-slate-500">
                      {{ item.remarks }}
                    </div>
                  </td>

                  <td class="px-4 py-3">
                    <span
                      class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1"
                      :class="statusBadgeClass(item.status)"
                    >
                      {{ item.status || '—' }}
                    </span>
                  </td>

                  <td class="px-4 py-3 text-slate-700">
                    {{ item.uploader?.name || '—' }}
                  </td>

                  <td class="whitespace-nowrap px-4 py-3 text-slate-700">
                    {{ formatDateTime(item.uploaded_at) }}
                  </td>

                  <td class="px-4 py-3 text-right">
                    <div class="inline-flex flex-wrap justify-end gap-2">
                      <a
                        :href="previewUrl(item.id)"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-50"
                      >
                        Preview
                      </a>
                      <a
                        :href="downloadUrl(item.id)"
                        class="inline-flex items-center justify-center rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-slate-800"
                      >
                        Download
                      </a>
                      <button
                        v-if="currentCanToggle"
                        type="button"
                        class="inline-flex items-center justify-center rounded-lg border px-3 py-1.5 text-xs font-medium transition"
                        :class="
                          item.status === 'Active'
                            ? 'border-amber-200 bg-amber-50 text-amber-700 hover:bg-amber-100'
                            : 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100'
                        "
                        @click="toggleStatus(item)"
                      >
                        {{ item.status === 'Active' ? 'Mark Obsolete' : 'Restore' }}
                      </button>
                      <button
                        v-if="currentCanToggle"
                        type="button"
                        class="inline-flex items-center justify-center rounded-lg border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-medium text-rose-700 transition hover:bg-rose-100"
                        @click="openDeleteModal(item)"
                      >
                        Delete
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Empty state -->
          <div
            v-else
            class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-5 py-8 text-center"
          >
            <div class="text-base font-semibold text-slate-900">No files found</div>
            <div class="mt-1 text-sm text-slate-500">{{ currentEmptyMessage }}</div>
          </div>
        </div>
      </div>

      <!-- Upload Modal -->
      <div
        v-if="showUploadModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 px-4 py-6"
      >
        <div class="absolute inset-0" @click="closeUploadModal"></div>

        <div class="relative z-10 w-full max-w-lg rounded-2xl border border-slate-200 bg-white shadow-2xl">
          <div class="flex items-start justify-between border-b border-slate-200 px-5 py-4">
            <div>
              <h3 class="text-base font-semibold text-slate-900">Upload Manual File</h3>
              <p class="mt-1 text-sm text-slate-500">Add a new file to the manual library.</p>
            </div>

            <button
              type="button"
              class="inline-flex h-9 w-9 items-center justify-center rounded-xl text-slate-500 transition hover:bg-slate-100 hover:text-slate-700"
              @click="closeUploadModal"
            >
              <svg
                xmlns="http://www.w3.org/2000/svg"
                class="h-5 w-5"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
              >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <div class="space-y-4 px-5 py-5">
            <div>
              <label class="text-xs font-medium text-slate-600">Access Type</label>
              <select
                v-model="uploadForm.access"
                class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-slate-300"
              >
                <option value="" disabled>Select access type</option>
                <option
                  v-for="type in uploadableAccessTypes"
                  :key="type.value"
                  :value="type.value"
                >
                  {{ type.label }}
                </option>
              </select>
              <div v-if="uploadForm.errors.access" class="mt-1 text-sm text-rose-600">
                {{ uploadForm.errors.access }}
              </div>
            </div>

            <div>
              <label class="text-xs font-medium text-slate-600">Manual Files</label>
              <input
                :key="fileInputKey"
                type="file"
                multiple
                accept=".pdf,.doc,.docx"
                class="mt-1 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-slate-300"
                @change="onFileChange"
              />
              <div v-if="tooManyFiles" class="mt-1 text-sm text-rose-600">
                Maximum 20 files per upload. Please remove {{ fileCount - 20 }} file{{ fileCount - 20 === 1 ? '' : 's' }}.
              </div>
              <div v-if="uploadForm.errors.files" class="mt-1 text-sm text-rose-600">
                {{ uploadForm.errors.files }}
              </div>

              <!-- Selected files preview list -->
              <div v-if="selectedFiles.length" class="mt-3 space-y-1">
                <div class="text-xs font-medium text-slate-500">
                  {{ fileCount }} file{{ fileCount === 1 ? '' : 's' }} selected
                </div>
                <ul class="max-h-48 overflow-y-auto rounded-xl border border-slate-200 bg-slate-50 divide-y divide-slate-100">
                  <li
                    v-for="(file, index) in selectedFiles"
                    :key="index"
                    class="flex items-center justify-between gap-2 px-3 py-2 text-sm"
                  >
                    <span class="truncate text-slate-700">{{ file.name }}</span>
                    <button
                      type="button"
                      class="shrink-0 text-slate-400 hover:text-rose-600 transition"
                      @click="removeFile(index)"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                      </svg>
                    </button>
                  </li>
                </ul>
              </div>
            </div>

            <div>
              <label class="text-xs font-medium text-slate-600">
                Remarks <span class="text-slate-400">(optional)</span>
              </label>
              <input
                v-model="uploadForm.remarks"
                type="text"
                placeholder="Optional remarks"
                class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-slate-300"
              />
              <div v-if="uploadForm.errors.remarks" class="mt-1 text-sm text-rose-600">
                {{ uploadForm.errors.remarks }}
              </div>
            </div>
          </div>

          <div class="flex flex-wrap items-center justify-end gap-2 border-t border-slate-200 px-5 py-4">
            <button
              type="button"
              class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
              @click="closeUploadModal"
            >
              Cancel
            </button>

            <button
              type="button"
              :disabled="uploadForm.processing || tooManyFiles"
              class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
              @click="submitUpload"
            >
              {{ uploadButtonLabel }}
            </button>
          </div>
        </div>
      </div>
      <!-- Delete Modal -->
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
              <div class="break-words font-semibold">{{ selectedRow?.file_name }}</div>
              <div class="mt-2">
                This will permanently delete the file from storage and cannot be undone.
              </div>
            </div>
          </div>

          <div class="flex justify-end gap-2 border-t border-slate-200 px-6 py-4">
            <button
              type="button"
              :disabled="deletingType"
              class="rounded-xl border border-slate-200 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
              @click="closeDeleteModal"
            >
              Cancel
            </button>
            <button
              type="button"
              :disabled="deletingType"
              class="rounded-xl bg-rose-600 px-4 py-2 text-sm text-white hover:bg-rose-700 disabled:cursor-not-allowed disabled:opacity-60"
              @click="submitDelete"
            >
              {{ deleteButtonText }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
