<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useForm } from '@inertiajs/vue3'
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

const controlledForm = useForm({
  file: null,
  revision: '',
  remarks: '',
})

const uncontrolledForm = useForm({
  file: null,
  revision: '',
  remarks: '',
})

const showUploadModal = ref(false)
const activeUploadAccess = ref(null)

const controlledFileInputKey = ref(0)
const uncontrolledFileInputKey = ref(0)

const normalizedCategory = computed(() => (props.category || '').toLowerCase())

const controlledManual = computed(() => props.manuals?.controlled ?? null)
const uncontrolledManual = computed(() => props.manuals?.uncontrolled ?? null)

const canSeeControlledSection = computed(
  () => !!props.can?.view_controlled || !!props.can?.upload_controlled
)

const canSeeUncontrolledSection = computed(
  () => !!props.can?.view_uncontrolled || !!props.can?.upload_uncontrolled
)

const hasAnyVisibleManual = computed(
  () => canSeeControlledSection.value || canSeeUncontrolledSection.value
)

const controlledHasActiveUpload = computed(() => !!controlledManual.value?.active_upload)
const uncontrolledHasActiveUpload = computed(() => !!uncontrolledManual.value?.active_upload)

const visibleSectionsCount = computed(() => {
  let count = 0
  if (canSeeControlledSection.value) count++
  if (canSeeUncontrolledSection.value) count++
  return count
})

const controlledRows = computed(() => {
  if (!controlledManual.value?.history?.length) return []

  const activeId = controlledManual.value?.active_upload?.id ?? null

  return [...controlledManual.value.history].sort((a, b) => {
    if (a.id === activeId) return -1
    if (b.id === activeId) return 1

    const da = a.uploaded_at ? new Date(a.uploaded_at).getTime() : 0
    const db = b.uploaded_at ? new Date(b.uploaded_at).getTime() : 0
    return db - da
  })
})

const uncontrolledRows = computed(() => {
  if (!uncontrolledManual.value?.history?.length) return []

  const activeId = uncontrolledManual.value?.active_upload?.id ?? null

  return [...uncontrolledManual.value.history].sort((a, b) => {
    if (a.id === activeId) return -1
    if (b.id === activeId) return 1

    const da = a.uploaded_at ? new Date(a.uploaded_at).getTime() : 0
    const db = b.uploaded_at ? new Date(b.uploaded_at).getTime() : 0
    return db - da
  })
})

const activeUploadForm = computed(() => {
  return activeUploadAccess.value === 'controlled' ? controlledForm : uncontrolledForm
})

const activeFileInputKey = computed(() => {
  return activeUploadAccess.value === 'controlled'
    ? controlledFileInputKey.value
    : uncontrolledFileInputKey.value
})

const activeUploadTitle = computed(() => {
  if (activeUploadAccess.value === 'controlled') {
    return controlledHasActiveUpload.value
      ? 'Replace Controlled Manual'
      : 'Upload Controlled Manual'
  }

  if (activeUploadAccess.value === 'uncontrolled') {
    return uncontrolledHasActiveUpload.value
      ? 'Replace Uncontrolled Manual'
      : 'Upload Uncontrolled Manual'
  }

  return 'Upload Manual'
})

const activeUploadDescription = computed(() => {
  if (activeUploadAccess.value === 'controlled') {
    return 'Upload a new controlled manual file. The current active version will move to history.'
  }

  if (activeUploadAccess.value === 'uncontrolled') {
    return 'Upload a new uncontrolled manual file. The current active version will move to history.'
  }

  return 'Upload a manual file.'
})

const onFileChange = (event, form) => {
  form.file = event.target.files?.[0] ?? null
}

const resetForm = (form) => {
  form.reset()
  form.clearErrors()

  if (form === controlledForm) {
    controlledFileInputKey.value++
  }

  if (form === uncontrolledForm) {
    uncontrolledFileInputKey.value++
  }
}

const openUploadModal = (access) => {
  activeUploadAccess.value = access
  const form = access === 'controlled' ? controlledForm : uncontrolledForm
  resetForm(form)
  showUploadModal.value = true
}

const closeUploadModal = () => {
  if (activeUploadAccess.value === 'controlled') {
    resetForm(controlledForm)
  }

  if (activeUploadAccess.value === 'uncontrolled') {
    resetForm(uncontrolledForm)
  }

  showUploadModal.value = false
  activeUploadAccess.value = null
}

const submitUpload = (access, form) => {
  if (!form.file) {
    toast.error?.('Please select a file first.')
    return
  }

  loading.show?.(`Uploading ${access} manual...`)

  form.post(`/manual/${normalizedCategory.value}/${access}/upload`, {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => {
      resetForm(form)
      showUploadModal.value = false
      activeUploadAccess.value = null
      toast.success?.(`${capitalize(access)} manual uploaded successfully.`)
    },
    onError: () => {
      toast.error?.('Upload failed. Please check the form and try again.')
    },
    onFinish: () => {
      loading.hide?.()
    },
  })
}

const previewUrl = (uploadId) => `/manual/uploads/${uploadId}/preview`
const downloadUrl = (uploadId) => `/manual/uploads/${uploadId}/download`

const formatDateTime = (value) => {
  if (!value) return '—'

  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return value

  return date.toLocaleString()
}

const capitalize = (value) => {
  if (!value) return ''
  return value.charAt(0).toUpperCase() + value.slice(1)
}

const statusBadgeClass = (status) => {
  if (status === 'Active') {
    return 'bg-emerald-50 text-emerald-700 ring-emerald-200'
  }

  if (status === 'Obsolete') {
    return 'bg-slate-100 text-slate-600 ring-slate-200'
  }

  return 'bg-amber-50 text-amber-700 ring-amber-200'
}

const accessBadgeClass = (access) => {
  if (access === 'controlled') {
    return 'bg-violet-50 text-violet-700 ring-violet-200'
  }

  return 'bg-sky-50 text-sky-700 ring-sky-200'
}

const isCurrentUpload = (item, manual) => {
  return item?.id === manual?.active_upload?.id
}
</script>

<template>
  <AdminLayout>
    <div class="space-y-6 p-6">
      <!-- Top header -->
      <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="bg-gradient-to-r from-slate-900 to-slate-800 px-4 py-3">
          <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div>
              <h1 class="text-base font-semibold text-white sm:text-xl">
                {{ pageTitle || 'Manuals' }}
              </h1>
              <p class="mt-1 text-sm text-slate-300">
                Browse the current controlled and uncontrolled manuals for
                <span class="font-medium text-white">{{ category }}</span>.
              </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
              <span class="rounded-lg border border-white/10 bg-white/10 px-3 py-1.5 text-xs font-medium text-white">
                Category: {{ category || '—' }}
              </span>
              <span class="rounded-lg border border-white/10 bg-white/10 px-3 py-1.5 text-xs font-medium text-white">
                {{ visibleSectionsCount }} section{{ visibleSectionsCount === 1 ? '' : 's' }} visible
              </span>
            </div>
          </div>
        </div>

        <div class="px-4 py-3">
          <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
            <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
              <div class="text-xs font-medium text-slate-500">Controlled Manual</div>
              <div class="mt-1 text-sm font-semibold text-slate-900">
                {{ canSeeControlledSection ? 'Available' : 'No access' }}
              </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
              <div class="text-xs font-medium text-slate-500">Uncontrolled Manual</div>
              <div class="mt-1 text-sm font-semibold text-slate-900">
                {{ canSeeUncontrolledSection ? 'Available' : 'No access' }}
              </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
              <div class="text-xs font-medium text-slate-500">Active Manual Files</div>
              <div class="mt-1 text-sm font-semibold text-slate-900">
                {{ (controlledHasActiveUpload ? 1 : 0) + (uncontrolledHasActiveUpload ? 1 : 0) }}
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Empty state -->
      <div
        v-if="!hasAnyVisibleManual"
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

        <h2 class="mt-4 text-lg font-semibold text-slate-900">No manual access available</h2>
        <p class="mt-2 text-sm text-slate-600">
          You do not currently have permission to view any manual in this category.
        </p>
      </div>

      <!-- Manual sections stacked vertically -->
      <div
        v-else
        class="grid grid-cols-1 gap-6"
      >
        <!-- Controlled Manual -->
        <section
          v-if="canSeeControlledSection"
          class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
        >
          <div class="border-b border-slate-200 px-5 py-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
              <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                  <h2 class="text-base font-semibold text-slate-900">Controlled Manual</h2>
                  <span
                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1"
                    :class="accessBadgeClass('controlled')"
                  >
                    Controlled
                  </span>
                </div>

                <p class="mt-1 text-sm text-slate-500">
                  Restricted copy for authorized users and manual replacement workflow.
                </p>
              </div>

              <button
                v-if="can?.upload_controlled"
                type="button"
                class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800"
                @click="openUploadModal('controlled')"
              >
                {{ controlledHasActiveUpload ? 'Replace Manual' : 'Upload Manual' }}
              </button>
            </div>
          </div>

          <div v-if="controlledRows.length" class="overflow-x-auto">
            <table class="min-w-full text-sm">
              <thead class="border-b border-slate-200 bg-slate-50">
                <tr class="text-left">
                  <th class="px-4 py-3 font-semibold text-slate-700">File</th>
                  <th class="px-4 py-3 font-semibold text-slate-700">Revision</th>
                  <th class="px-4 py-3 font-semibold text-slate-700">Status</th>
                  <th class="px-4 py-3 font-semibold text-slate-700">Uploaded By</th>
                  <th class="px-4 py-3 font-semibold text-slate-700">Uploaded Date</th>
                  <th class="px-4 py-3 text-right font-semibold text-slate-700">Action</th>
                </tr>
              </thead>

              <tbody>
                <tr
                  v-for="item in controlledRows"
                  :key="item.id"
                  class="border-b border-slate-100 align-top transition hover:bg-slate-50"
                  :class="isCurrentUpload(item, controlledManual) ? 'bg-emerald-50/50' : 'bg-white'"
                >
                  <td class="px-4 py-3">
                    <div class="max-w-[420px] break-words font-medium text-slate-900">
                      {{ item.file_name }}
                    </div>

                    <div class="mt-1 flex flex-wrap items-center gap-2">
                      <span
                        v-if="isCurrentUpload(item, controlledManual)"
                        class="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-semibold text-emerald-700"
                      >
                        Current
                      </span>

                      <span class="text-xs text-slate-500">
                        {{ item.remarks || 'No remarks' }}
                      </span>
                    </div>
                  </td>

                  <td class="whitespace-nowrap px-4 py-3 text-slate-700">
                    {{ item.revision || '—' }}
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
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div v-else class="px-5 py-10">
            <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-5 py-8 text-center">
              <div class="text-base font-semibold text-slate-900">No controlled manual uploaded</div>
              <div class="mt-1 text-sm text-slate-500">
                Upload a controlled manual to create the first record for this section.
              </div>
            </div>
          </div>
        </section>

        <!-- Uncontrolled Manual -->
        <section
          v-if="canSeeUncontrolledSection"
          class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
        >
          <div class="border-b border-slate-200 px-5 py-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
              <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                  <h2 class="text-base font-semibold text-slate-900">Uncontrolled Manual</h2>
                  <span
                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1"
                    :class="accessBadgeClass('uncontrolled')"
                  >
                    Uncontrolled
                  </span>
                </div>

                <p class="mt-1 text-sm text-slate-500">
                  Shared copy intended for broader manual access and download.
                </p>
              </div>

              <button
                v-if="can?.upload_uncontrolled"
                type="button"
                class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800"
                @click="openUploadModal('uncontrolled')"
              >
                {{ uncontrolledHasActiveUpload ? 'Replace Manual' : 'Upload Manual' }}
              </button>
            </div>
          </div>

          <div v-if="uncontrolledRows.length" class="overflow-x-auto">
            <table class="min-w-full text-sm">
              <thead class="border-b border-slate-200 bg-slate-50">
                <tr class="text-left">
                  <th class="px-4 py-3 font-semibold text-slate-700">File</th>
                  <th class="px-4 py-3 font-semibold text-slate-700">Revision</th>
                  <th class="px-4 py-3 font-semibold text-slate-700">Status</th>
                  <th class="px-4 py-3 font-semibold text-slate-700">Uploaded By</th>
                  <th class="px-4 py-3 font-semibold text-slate-700">Uploaded Date</th>
                  <th class="px-4 py-3 text-right font-semibold text-slate-700">Action</th>
                </tr>
              </thead>

              <tbody>
                <tr
                  v-for="item in uncontrolledRows"
                  :key="item.id"
                  class="border-b border-slate-100 align-top transition hover:bg-slate-50"
                  :class="isCurrentUpload(item, uncontrolledManual) ? 'bg-emerald-50/50' : 'bg-white'"
                >
                  <td class="px-4 py-3">
                    <div class="max-w-[420px] break-words font-medium text-slate-900">
                      {{ item.file_name }}
                    </div>

                    <div class="mt-1 flex flex-wrap items-center gap-2">
                      <span
                        v-if="isCurrentUpload(item, uncontrolledManual)"
                        class="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-semibold text-emerald-700"
                      >
                        Current
                      </span>

                      <span class="text-xs text-slate-500">
                        {{ item.remarks || 'No remarks' }}
                      </span>
                    </div>
                  </td>

                  <td class="whitespace-nowrap px-4 py-3 text-slate-700">
                    {{ item.revision || '—' }}
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
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div v-else class="px-5 py-10">
            <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-5 py-8 text-center">
              <div class="text-base font-semibold text-slate-900">No uncontrolled manual uploaded</div>
              <div class="mt-1 text-sm text-slate-500">
                Upload an uncontrolled manual to create the first record for this section.
              </div>
            </div>
          </div>
        </section>
      </div>

      <!-- Upload Modal -->
      <div
        v-if="showUploadModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 px-4 py-6"
      >
        <div class="absolute inset-0" @click="closeUploadModal"></div>

        <div class="relative z-10 w-full max-w-2xl rounded-2xl border border-slate-200 bg-white shadow-2xl">
          <div class="flex items-start justify-between border-b border-slate-200 px-5 py-4">
            <div>
              <h3 class="text-base font-semibold text-slate-900">
                {{ activeUploadTitle }}
              </h3>
              <p class="mt-1 text-sm text-slate-500">
                {{ activeUploadDescription }}
              </p>
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
              <label class="text-xs font-medium text-slate-600">Manual File</label>
              <input
                :key="activeFileInputKey"
                type="file"
                accept=".pdf,.doc,.docx"
                class="mt-1 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-slate-300"
                @change="onFileChange($event, activeUploadForm)"
              />
              <div v-if="activeUploadForm.errors.file" class="mt-1 text-sm text-rose-600">
                {{ activeUploadForm.errors.file }}
              </div>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
              <div class="md:col-span-1">
                <label class="text-xs font-medium text-slate-600">Revision</label>
                <input
                  v-model="activeUploadForm.revision"
                  type="text"
                  placeholder="e.g. Rev. 2"
                  class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-slate-300"
                />
                <div v-if="activeUploadForm.errors.revision" class="mt-1 text-sm text-rose-600">
                  {{ activeUploadForm.errors.revision }}
                </div>
              </div>

              <div class="md:col-span-2">
                <label class="text-xs font-medium text-slate-600">Remarks</label>
                <input
                  v-model="activeUploadForm.remarks"
                  type="text"
                  placeholder="Optional remarks"
                  class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-slate-300"
                />
                <div v-if="activeUploadForm.errors.remarks" class="mt-1 text-sm text-rose-600">
                  {{ activeUploadForm.errors.remarks }}
                </div>
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
              :disabled="activeUploadForm.processing || !activeUploadAccess"
              class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
              @click="submitUpload(activeUploadAccess, activeUploadForm)"
            >
              {{ activeUploadForm.processing ? 'Uploading...' : 'Save Upload' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>