<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { router, usePage } from '@inertiajs/vue3'
import { reactive, onMounted, onBeforeUnmount, ref, computed } from 'vue'
import axios from 'axios'
import logo from '@/images/LNU_logo.png'
import { useLoadingOverlay } from '@/Composables/useLoadingOverlay'
import { useToast } from '@/Composables/useToast'

const loading = useLoadingOverlay()
const toast = useToast()
const page = usePage()

// states
const recordId = ref(null)
const isSaving = ref(false)
const isGenerating = ref(false)
const isPublishing = ref(false)
const isSubmitting = ref(false)
const isLoadingRecord = ref(false)

const publishFileName = ref('')
const showPublishRenameModal = ref(false)

// workflow states
const recordStatus = ref('draft')
const workflowStatus = ref(null)
const resolutionStatus = ref('open')

const isAdmin = computed(() => {
  const role = String(page.props.auth?.user?.role || '').toLowerCase().trim()
  return role === 'admin'
})

const canSubmitToAdmin = computed(() => {
  return !isAdmin.value && !!recordId.value && workflowStatus.value !== 'pending' && workflowStatus.value !== 'approved'
})

const isFormLocked = computed(() => {
  if (isAdmin.value) {
    return false
  }

  return workflowStatus.value === 'pending' || workflowStatus.value === 'approved'
})

const workflowBadgeClass = computed(() => {
  if (workflowStatus.value === 'approved') return 'border-emerald-200 bg-emerald-50 text-emerald-700'
  if (workflowStatus.value === 'rejected') return 'border-rose-200 bg-rose-50 text-rose-700'
  if (workflowStatus.value === 'pending') return 'border-amber-200 bg-amber-50 text-amber-700'
  return 'border-slate-200 bg-white text-slate-600'
})

const resolutionBadgeClass = computed(() => {
  if (resolutionStatus.value === 'closed') return 'border-emerald-200 bg-emerald-50 text-emerald-700'
  if (resolutionStatus.value === 'ongoing') return 'border-blue-200 bg-blue-50 text-blue-700'
  return 'border-slate-200 bg-white text-slate-600'
})

function emptyForm() {
  return {
    date: '',
    dcrNo: '',
    toFor: '',
    from: '',

    amend: false,
    newDoc: false,
    deleteDoc: false,

    documentNumber: '',
    documentTitle: '',
    revisionStatus: '',

    changesRequested: '',
    reason: '',

    requestedBy: '',
    deptUnitHead: '',

    requestDecision: '',
    imrSigDate: '',

    approvingSigName: '',
    approvingDate: '',

    statusNo: '',
    statusVersion: '',
    statusRevision: '',
    effectivityDate: '',
    idsDateUpdated: '',
    updatedBy: '',
  }
}

const form = reactive(emptyForm())

const suggestedPublishName = computed(() => `DCR_${form.dcrNo || recordId.value || 'record'}`)

function currentFileLabel() {
  return (
    publishFileName.value?.trim() ||
    (form.dcrNo ? `DCR_${form.dcrNo}` : '') ||
    (recordId.value ? `DCR_${recordId.value}` : 'DCR_record')
  )
}

function withDocx(name) {
  const n = (name || '').trim()
  if (!n) return null
  return n.toLowerCase().endsWith('.docx') ? n : `${n}.docx`
}

function downloadBlob(blobData, filename) {
  const url = window.URL.createObjectURL(new Blob([blobData]))
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  document.body.appendChild(link)
  link.click()
  link.remove()
  window.URL.revokeObjectURL(url)
}

async function runTask(flagRef, loadingMessage, task) {
  flagRef.value = true
  loading.open(loadingMessage)

  try {
    return await task()
  } finally {
    flagRef.value = false
    loading.close()
  }
}

function pickOnlyOne(key) {
  form.amend = false
  form.newDoc = false
  form.deleteDoc = false
  form[key] = true
}

function resetFormState() {
  const fresh = emptyForm()

  Object.keys(fresh).forEach((key) => {
    form[key] = fresh[key]
  })

  recordId.value = null
  recordStatus.value = 'draft'
  workflowStatus.value = null
  resolutionStatus.value = 'open'
  publishFileName.value = ''
  showPublishRenameModal.value = false

  const url = new URL(window.location.href)
  url.searchParams.delete('record')
  window.history.replaceState({}, '', url)
}

function cancelForm() {
  resetFormState()
  toast.success('Form cleared. You can start a new DCR draft.')
}

function applyDataToForm(data) {
  Object.keys(form).forEach((k) => {
    if (data?.[k] !== undefined) form[k] = data[k]
  })

  if (!publishFileName.value && (data?.dcrNo || form.dcrNo)) {
    publishFileName.value = `DCR_${data?.dcrNo || form.dcrNo}`
  }
}

function getRecordIdFromUrl() {
  const params = new URLSearchParams(window.location.search)
  const v = params.get('record')
  return v ? Number(v) : null
}

async function loadRecord(id) {
  await runTask(isLoadingRecord, 'Loading saved record...', async () => {
    const res = await axios.get(`/dcr/records/${id}`)
    recordId.value = id
    recordStatus.value = res.data.status ?? 'draft'
    workflowStatus.value = res.data.workflow_status ?? null
    resolutionStatus.value = res.data.resolution_status ?? 'open'
    applyDataToForm(res.data.data || {})

    const fileShown = withDocx(currentFileLabel()) || `Record #${id}`
    toast.success(`Loaded saved record: ${fileShown}`)
  }).catch((err) => {
    console.error(err)
    toast.error('Failed to load saved record.')
  })
}

async function upsertRecord(requestedStatus = null) {
  let safeStatus = requestedStatus

  if (recordId.value) {
    const isWorkflowLocked =
      workflowStatus.value === 'pending' ||
      workflowStatus.value === 'approved' ||
      recordStatus.value === 'submitted'

    if (isWorkflowLocked) {
      safeStatus = recordStatus.value || 'submitted'
    } else {
      safeStatus = requestedStatus ?? recordStatus.value ?? 'draft'
    }
  } else {
    safeStatus = requestedStatus ?? 'draft'
  }

  if (!recordId.value) {
    const res = await axios.post('/dcr/records', { ...form, status: safeStatus })
    recordId.value = res.data.id
    recordStatus.value = res.data.status ?? 'draft'
    workflowStatus.value = res.data.workflow_status ?? workflowStatus.value
    resolutionStatus.value = res.data.resolution_status ?? resolutionStatus.value

    const url = new URL(window.location.href)
    url.searchParams.set('record', recordId.value)
    window.history.replaceState({}, '', url)
  } else {
    const res = await axios.put(`/dcr/records/${recordId.value}`, { ...form, status: safeStatus })
    recordStatus.value = res.data.status ?? recordStatus.value
    workflowStatus.value = res.data.workflow_status ?? workflowStatus.value
    resolutionStatus.value = res.data.resolution_status ?? resolutionStatus.value
  }

  if (!publishFileName.value) {
    publishFileName.value = currentFileLabel()
  }

  return recordId.value
}

async function ensureDraftSaved() {
  const requestedStatus = recordId.value
    ? (recordStatus.value || 'draft')
    : 'draft'

  return await upsertRecord(requestedStatus)
}

async function saveDraft() {
  await runTask(isSaving, 'Saving draft...', async () => {
    const wasNew = !recordId.value
    const requestedStatus = wasNew ? 'draft' : (recordStatus.value || 'draft')
    const id = await upsertRecord(requestedStatus)
    const name = currentFileLabel()

    toast.success(
      wasNew
        ? `Saved draft: ${name} (Record #${id})`
        : `Updated draft: ${name} (Record #${id})`
    )
  }).catch((err) => {
    console.error(err)

    const message =
      err?.response?.data?.message ||
      err?.response?.data?.error ||
      (err?.response?.status === 403
        ? 'This DCR record can no longer be edited. Pending and approved records are locked.'
        : 'Failed to save draft. Please try again.')

    toast.error(message)
  })
}

async function submitToAdmin() {
  if (!recordId.value) {
    toast.error('Save the draft first before submitting to admin.')
    return
  }

  if (isAdmin.value) {
    toast.error('Admin-created DCR records do not need inbox submission.')
    return
  }

  await runTask(isSubmitting, 'Submitting DCR to admin...', async () => {
    await ensureDraftSaved()

    const res = await axios.post(`/dcr/records/${recordId.value}/submit`)

    recordStatus.value = res.data.status ?? 'submitted'
    workflowStatus.value = res.data.workflow_status ?? 'pending'

    toast.success(res?.data?.message || 'DCR submitted to admin successfully.')
  }).catch((err) => {
    console.error(err)

    const message =
      err?.response?.data?.message ||
      err?.response?.data?.error ||
      'Failed to submit DCR to admin.'

    toast.error(message)
  })
}

async function downloadDocx() {
  await runTask(isGenerating, 'Generating document...', async () => {
    await ensureDraftSaved()

    const res = await axios.post('/dcr/generate', form, {
      responseType: 'blob',
    })

    const name = withDocx(currentFileLabel()) || 'DCR.docx'
    downloadBlob(res.data, name)
    toast.success(`Downloaded: ${name}`)
  }).catch((err) => {
    console.error(err)
    toast.error('Failed to download DOCX. Please try again.')
  })
}

async function publishToUploads() {
  if (!recordId.value) {
    toast.error('Save the record first before publishing.')
    return
  }

  await runTask(isPublishing, 'Publishing document...', async () => {
    const id = await ensureDraftSaved()

    const res = await axios.post(`/dcr/records/${id}/publish`, {
      file_name: publishFileName.value?.trim() || null,
      remarks: 'Published from DCR form',
    })

    const fn = res?.data?.file_name || withDocx(currentFileLabel()) || 'DOCX'
    toast.success(`Published: ${fn} (Upload #${res.data.upload_id})`)

    resetFormState()
  }).catch((err) => {
    console.error(err)

    const message =
      err?.response?.data?.message ||
      err?.response?.data?.error ||
      (err?.response?.status === 403
        ? 'You are not allowed to publish this DCR record.'
        : 'Failed to publish to uploads. Please try again.')

    toast.error(message)
  })
}

function openPublishRenameModal() {
  if (!recordId.value) {
    toast.error('Save the record first before publishing.')
    return
  }

  if (!publishFileName.value) {
    publishFileName.value = suggestedPublishName.value
  }

  showPublishRenameModal.value = true
}

async function confirmPublish() {
  showPublishRenameModal.value = false
  await publishToUploads()
}

function closePublishModal() {
  if (isPublishing.value) return
  showPublishRenameModal.value = false
}

function onKeydown(e) {
  if (e.key === 'Escape') {
    closePublishModal()
  }
}

onMounted(() => {
  window.addEventListener('keydown', onKeydown)

  const id = getRecordIdFromUrl()
  if (id) {
    loadRecord(id)
  }
})

onBeforeUnmount(() => {
  window.removeEventListener('keydown', onKeydown)
})
</script>

<template>
  <AdminLayout>
    <!-- Publish Rename Modal -->
    <div
      v-if="showPublishRenameModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4"
      @click.self="closePublishModal"
    >
      <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl">
        <div class="flex items-start justify-between gap-4">
          <div>
            <h3 class="text-lg font-bold text-slate-900">Rename file before publishing</h3>
            <p class="mt-1 text-sm text-slate-500">Enter a filename (no need to add <b>.docx</b>).</p>
          </div>

          <button
            class="rounded-lg px-3 py-1 text-sm text-slate-500 hover:bg-slate-100 disabled:opacity-60"
            @click="closePublishModal"
            :disabled="isPublishing"
          >
            ✕
          </button>
        </div>

        <div class="mt-4">
          <label class="text-sm font-semibold text-slate-700">Filename</label>
          <input
            v-model="publishFileName"
            type="text"
            class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-2 text-sm outline-none focus:ring-2 focus:ring-slate-200"
            placeholder="e.g. DCR_2026-001"
          />
          <p class="mt-2 text-xs text-slate-500">
            Suggested: <span class="font-mono">{{ suggestedPublishName }}</span>
          </p>
        </div>

        <div class="mt-6 flex justify-end gap-2">
          <button
            class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-60"
            @click="closePublishModal"
            :disabled="isPublishing"
          >
            Cancel
          </button>

          <button
            class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-60"
            @click="confirmPublish"
            :disabled="isPublishing"
          >
            <span v-if="!isPublishing">Publish</span>
            <span v-else>Publishing...</span>
          </button>
        </div>
      </div>
    </div>

    <div class="h-screen overflow-hidden bg-slate-100 font-sans">
      <div class="flex h-full flex-col gap-6 px-10 py-8">
        <!-- Page Header -->
        <div class="flex shrink-0 flex-wrap items-end justify-between gap-3">
          <div class="min-w-0">
            <div class="flex items-center gap-3">
              <h1 class="truncate text-2xl font-bold tracking-tight text-slate-900">Create DCR Form</h1>

              <span
                v-if="recordId"
                class="inline-flex items-center rounded-full border border-slate-200 bg-white px-3 py-1 text-[12px] font-semibold text-slate-600"
              >
                Record #{{ recordId }}
              </span>
            </div>

            <p class="mt-1 text-[13px] text-slate-400">Document Change Request · Leyte Normal University</p>

            <div v-if="recordId" class="mt-3 flex flex-wrap items-center gap-3">
              <div
                class="inline-flex items-center rounded-full border px-3 py-1 text-[12px] font-semibold"
                :class="workflowBadgeClass"
              >
                Workflow: {{ workflowStatus || 'draft' }}
              </div>

              <div
                class="inline-flex items-center rounded-full border px-3 py-1 text-[12px] font-semibold"
                :class="resolutionBadgeClass"
              >
                Resolution: {{ resolutionStatus || 'open' }}
              </div>

              <div
                class="inline-flex items-center rounded-full border border-slate-200 bg-white px-3 py-1 text-[12px] font-semibold text-slate-600"
              >
                Status: {{ recordStatus || 'draft' }}
              </div>

              <span
                v-if="recordId && isFormLocked"
                class="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-[12px] font-semibold text-amber-800"
              >
                {{ workflowStatus === 'pending' ? 'Read-only while under review' : 'Read-only after approval' }}
              </span>
            </div>
          </div>

          <div class="flex flex-wrap items-center gap-2.5">
            <button
              class="rounded-xl border border-slate-200 bg-white px-5 py-2 text-[13.5px] font-medium text-slate-500 transition hover:bg-slate-50 hover:text-slate-700"
              @click="cancelForm"
  :disabled="isSaving || isGenerating || isPublishing || isSubmitting || isLoadingRecord"
>
              Cancel
            </button>

            <button
              class="rounded-xl border border-slate-200 bg-white px-5 py-2 text-[13.5px] font-semibold text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
              @click="saveDraft"
              :disabled="isSaving || isFormLocked"
            >
              <span v-if="!isSaving">{{ recordId ? 'Update Draft' : 'Save Draft' }}</span>
              <span v-else>Saving...</span>
            </button>

            <button
              class="inline-flex items-center gap-2 rounded-xl bg-slate-800 px-5 py-2 text-[13.5px] font-semibold text-white transition hover:bg-slate-900 disabled:cursor-not-allowed disabled:bg-slate-400"
              @click="downloadDocx"
              :disabled="isGenerating"
              title="Auto-saves draft, then generates & downloads DOCX"
            >
              <span>{{ isGenerating ? 'Downloading...' : 'Download DOCX' }}</span>
            </button>

            <button
              v-if="!isAdmin"
              class="inline-flex items-center gap-2 rounded-xl bg-amber-600 px-5 py-2 text-[13.5px] font-semibold text-white transition hover:bg-amber-700 disabled:cursor-not-allowed disabled:opacity-60"
              @click="submitToAdmin"
              :disabled="!canSubmitToAdmin || isSubmitting || isFormLocked"
              title="Submit this DCR to admin for review"
            >
              <span>{{ isSubmitting ? 'Submitting...' : 'Submit to Admin' }}</span>
            </button>

            <button
              v-if="isAdmin"
              class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-5 py-2 text-[13.5px] font-semibold text-white transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60"
              @click="openPublishRenameModal"
              :disabled="isPublishing || !recordId"
              title="Saves DOCX to uploads list (R-QMS-013)"
            >
              <span>{{ isPublishing ? 'Publishing...' : 'Publish' }}</span>
            </button>
          </div>
        </div>

        <!-- Form Card -->
        <div
          class="flex flex-1 items-start justify-center overflow-y-auto rounded-2xl border border-slate-200 bg-white p-8 shadow-[0_2px_12px_rgba(0,0,0,0.06)]"
        >
          <div
            class="flex w-[210mm] flex-col border-2 border-black bg-white px-6 py-5 text-black transition"
            :class="{ 'pointer-events-none opacity-70': isFormLocked }"
            style="font-family: Arial, Helvetica, sans-serif;"
          >
            <!-- Header -->
            <div class="flex items-start gap-4 border-b border-black pb-3">
              <img :src="logo" alt="LNU Logo" class="h-[70px] w-[70px] object-contain" />
              <div class="flex-1 text-center">
                <h2 class="text-[12pt] font-bold leading-tight">LEYTE NORMAL UNIVERSITY</h2>
                <h1 class="mt-1 text-[14pt] font-bold leading-tight">DOCUMENT CHANGE REQUEST</h1>
              </div>
            </div>

            <!-- Metadata -->
            <div class="mt-4 grid grid-cols-2 gap-x-10 gap-y-3 text-[10pt]">
              <div class="flex items-center">
                <span class="w-[70px]">DATE</span>
                <span class="mr-2">:</span>
                <input v-model="form.date" type="date" class="flex-1 border-0 border-b border-black bg-transparent outline-none" />
              </div>

              <div class="flex items-center">
                <span class="w-[90px]">DCR No.</span>
                <span class="mr-2">:</span>
                <input v-model="form.dcrNo" type="text" class="flex-1 border-0 border-b border-black bg-transparent outline-none" />
              </div>

              <div class="col-span-2 flex items-center">
                <span class="w-[70px]">TO/FOR</span>
                <span class="mr-2">:</span>
                <input v-model="form.toFor" type="text" class="flex-1 border-0 border-b border-black bg-transparent outline-none" />
              </div>

              <div class="col-span-2 flex items-center">
                <span class="w-[70px]">FROM</span>
                <span class="mr-2">:</span>
                <input v-model="form.from" type="text" class="flex-1 border-0 border-b border-black bg-transparent outline-none" />
              </div>
            </div>

            <!-- Document Type -->
            <div class="mt-5 text-[10pt]">
              <div class="flex flex-wrap gap-6">
                <label class="inline-flex items-center gap-2 cursor-pointer">
                  <input
                    type="checkbox"
                    :checked="form.amend"
                    @change="pickOnlyOne('amend')"
                    class="hidden"
                  />
                  <span>Amend document</span>
                  <span>{{ form.amend ? '[✔]' : '[ ]' }}</span>
                </label>

                <label class="inline-flex items-center gap-2 cursor-pointer">
                  <input
                    type="checkbox"
                    :checked="form.newDoc"
                    @change="pickOnlyOne('newDoc')"
                    class="hidden"
                  />
                  <span>New document</span>
                  <span>{{ form.newDoc ? '[✔]' : '[ ]' }}</span>
                </label>

                <label class="inline-flex items-center gap-2 cursor-pointer">
                  <input
                    type="checkbox"
                    :checked="form.deleteDoc"
                    @change="pickOnlyOne('deleteDoc')"
                    class="hidden"
                  />
                  <span>Delete document</span>
                  <span>{{ form.deleteDoc ? '[✔]' : '[ ]' }}</span>
                </label>
              </div>
            </div>

            <!-- Section 1 -->
            <div class="mt-5 border-t border-black pt-3 text-[10pt]">
              <h3 class="mb-3 font-bold">1. DETAILS OF DOCUMENT</h3>

              <div class="space-y-3">
                <div class="flex items-center">
                  <span class="w-[140px]">Document Number</span>
                  <span class="mr-2">:</span>
                  <input v-model="form.documentNumber" class="flex-1 border-0 border-b border-black bg-transparent outline-none" />
                </div>

                <div class="flex items-center">
                  <span class="w-[140px]">Document Title</span>
                  <span class="mr-2">:</span>
                  <input v-model="form.documentTitle" class="flex-1 border-0 border-b border-black bg-transparent outline-none" />
                </div>

                <div class="flex items-center">
                  <span class="w-[140px]">Revision Status</span>
                  <span class="mr-2">:</span>
                  <input v-model="form.revisionStatus" class="flex-1 border-0 border-b border-black bg-transparent outline-none" />
                </div>

                <p class="text-[9pt] italic">Note: Please attach draft copy of the document.</p>
              </div>
            </div>

            <!-- Section 2 -->
            <div class="mt-5 border-t border-black pt-3 text-[10pt]">
              <h3 class="mb-3 font-bold">2. CHANGE(S) REQUESTED</h3>
              <textarea
                v-model="form.changesRequested"
                rows="5"
                class="w-full resize-none border border-black bg-transparent p-2 outline-none"
              ></textarea>

              <h3 class="mb-3 mt-4 font-bold">REASON FOR THE CHANGE</h3>
              <textarea
                v-model="form.reason"
                rows="5"
                class="w-full resize-none border border-black bg-transparent p-2 outline-none"
              ></textarea>

              <div class="mt-4 grid grid-cols-2 gap-6">
                <div class="flex flex-col">
                  <input v-model="form.requestedBy" class="border-0 border-b border-black bg-transparent text-center outline-none" />
                  <label class="mt-1 text-center text-[9pt]">Requested by</label>
                </div>

                <div class="flex flex-col">
                  <input v-model="form.deptUnitHead" class="border-0 border-b border-black bg-transparent text-center outline-none" />
                  <label class="mt-1 text-center text-[9pt]">Department/Unit Head</label>
                </div>
              </div>
            </div>

            <!-- Section 3 -->
            <div class="mt-5 border-t border-black pt-3 text-[10pt]">
              <h3 class="mb-3 font-bold">3. INTEGRATED MANAGEMENT REPRESENTATIVE’S COMMENTS</h3>

              <div class="flex flex-wrap items-center gap-6">
                <label class="inline-flex items-center gap-2 cursor-pointer">
                  <input type="radio" v-model="form.requestDecision" value="DENIED" />
                  <span>Request Denied</span>
                </label>

                <label class="inline-flex items-center gap-2 cursor-pointer">
                  <input type="radio" v-model="form.requestDecision" value="ACCEPTED" />
                  <span>Request Accepted</span>
                </label>
              </div>

              <div class="mt-4 flex items-center">
                <span class="w-[120px]">Signature/Date</span>
                <span class="mr-2">:</span>
                <input v-model="form.imrSigDate" class="flex-1 border-0 border-b border-black bg-transparent outline-none" />
              </div>
            </div>

            <!-- Section 4 -->
            <div class="mt-5 border-t border-black pt-3 text-[10pt]">
              <h3 class="mb-3 font-bold">4. APPROVING AUTHORITY</h3>

              <div class="grid grid-cols-2 gap-6">
                <div class="flex flex-col">
                  <input v-model="form.approvingSigName" class="border-0 border-b border-black bg-transparent text-center outline-none" />
                  <label class="mt-1 text-center text-[9pt]">Signature Over Printed Name</label>
                </div>

                <div class="flex flex-col">
                  <input v-model="form.approvingDate" class="border-0 border-b border-black bg-transparent text-center outline-none" />
                  <label class="mt-1 text-center text-[9pt]">Date</label>
                </div>
              </div>
            </div>

            <!-- Section 5 -->
            <div class="mt-5 border-t border-black pt-3 text-[10pt]">
              <h3 class="mb-3 font-bold">5. DOCUMENT STATUS</h3>

              <div class="grid grid-cols-2 gap-x-10 gap-y-3">
                <div class="flex items-center">
                  <span class="w-[120px]">No.</span>
                  <span class="mr-2">:</span>
                  <input v-model="form.statusNo" class="flex-1 border-0 border-b border-black bg-transparent outline-none" />
                </div>

                <div class="flex items-center">
                  <span class="w-[120px]">Version</span>
                  <span class="mr-2">:</span>
                  <input v-model="form.statusVersion" class="flex-1 border-0 border-b border-black bg-transparent outline-none" />
                </div>

                <div class="flex items-center">
                  <span class="w-[120px]">Revision</span>
                  <span class="mr-2">:</span>
                  <input v-model="form.statusRevision" class="flex-1 border-0 border-b border-black bg-transparent outline-none" />
                </div>

                <div class="flex items-center">
                  <span class="w-[120px]">Effectivity Date</span>
                  <span class="mr-2">:</span>
                  <input v-model="form.effectivityDate" class="flex-1 border-0 border-b border-black bg-transparent outline-none" />
                </div>

                <div class="col-span-2 flex items-center">
                  <span class="w-[160px]">Date Updated in the IDS</span>
                  <span class="mr-2">:</span>
                  <input v-model="form.idsDateUpdated" class="flex-1 border-0 border-b border-black bg-transparent outline-none" />
                </div>

                <div class="col-span-2 flex items-center">
                  <span class="w-[120px]">Updated by</span>
                  <span class="mr-2">:</span>
                  <input v-model="form.updatedBy" class="flex-1 border-0 border-b border-black bg-transparent outline-none" />
                </div>
              </div>
            </div>

            <div class="mt-6 border-t border-black pt-2 text-[9pt]">
              F-QMS-001 Rev. 1 (01-05-26)
            </div>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<style scoped></style>