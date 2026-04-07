<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { reactive, ref, computed } from 'vue'
import { Head, usePage } from '@inertiajs/vue3'
import axios from 'axios'
import logo from '@/images/LNU_logo.png'
import { useLoadingOverlay } from '@/Composables/useLoadingOverlay'
import { useToast } from '@/Composables/useToast'

const props = defineProps({
  documentTypeId: Number,
  record: Object,
})

const loading = useLoadingOverlay()
const toast = useToast()
const page = usePage()

const recordId = ref(props.record?.id ?? null)
const isSaving = ref(false)
const isSubmitting = ref(false)

const currentUser = computed(() => page.props.auth?.user ?? null)

function emptyFollowUpRow() {
  return {
    date: '',
    status: '',
    auditor: '',
    rep: '',
    effective: '',
  }
}

function emptyForm() {
  return {
    deptSection: '',
    refNo: '',
    auditor: '',
    carNo: '',
    dateRep: '',
    isoClause: '',

    audit: false,
    complaint: false,
    nonConForm: false,

    descript: '',
    objective: '',
    conseq: '',
    depRep: '',
    sigAuditor: '',
    agreedDate: '',

    correction: '',
    actualdate: '',
    notedby: '',

    rootCause: '',
    rootCauseBy: '',
    rootCauseDate: '',
    rootCauseNotedBy: '',
    rootCauseNotedDate: '',

    correctiveAction: '',
    correctiveActionDate: '',
    correctiveActionNotedBy: '',

    riskUpdate: null,
    riskDateUpdated: '',
    riskVerifiedBy: '',

    imsUpdate: null,
    imsDcrNo: '',
    imsVerifiedBy: '',

    followUp: Array.from({ length: 10 }, () => emptyFollowUpRow()),

    caseClosed: '',
    imrSig: '',
    caseClosedDate: '',
    deptHeadNotedBy: '',

    effectProblem: '',
    causeMan: '',
    causeMachine: '',
    causeMaterial: '',
    causeMethod: '',
    causeOthers: '',
  }
}

const form = reactive(emptyForm())

function applyRecordData(data = {}) {
  Object.keys(emptyForm()).forEach((key) => {
    if (key === 'followUp') return
    if (data[key] !== undefined) {
      form[key] = data[key]
    }
  })

  if (Array.isArray(data.followUp)) {
    for (let i = 0; i < 10; i++) {
      const row = data.followUp[i] || {}
      form.followUp[i].date = row.date ?? ''
      form.followUp[i].status = row.status ?? ''
      form.followUp[i].auditor = row.auditor ?? ''
      form.followUp[i].rep = row.rep ?? ''
      form.followUp[i].effective = row.effective ?? ''
    }
  }
}

if (props.record?.data) {
  applyRecordData(props.record.data)
}

const payloadData = computed(() => ({
  deptSection: form.deptSection,
  refNo: form.refNo,
  auditor: form.auditor,
  carNo: form.carNo,
  dateRep: form.dateRep,
  isoClause: form.isoClause,

  audit: form.audit,
  complaint: form.complaint,
  nonConForm: form.nonConForm,

  descript: form.descript,
  objective: form.objective,
  conseq: form.conseq,
  depRep: form.depRep,
  sigAuditor: form.sigAuditor,
  agreedDate: form.agreedDate,

  correction: form.correction,
  actualdate: form.actualdate,
  notedby: form.notedby,

  rootCause: form.rootCause,
  rootCauseBy: form.rootCauseBy,
  rootCauseDate: form.rootCauseDate,
  rootCauseNotedBy: form.rootCauseNotedBy,
  rootCauseNotedDate: form.rootCauseNotedDate,

  correctiveAction: form.correctiveAction,
  correctiveActionDate: form.correctiveActionDate,
  correctiveActionNotedBy: form.correctiveActionNotedBy,

  riskUpdate: form.riskUpdate,
  riskNo: form.riskUpdate === 'NO',
  riskYes: form.riskUpdate === 'YES',
  riskDateUpdated: form.riskDateUpdated,
  riskVerifiedBy: form.riskVerifiedBy,

  imsUpdate: form.imsUpdate,
  imsNo: form.imsUpdate === 'NO',
  imsYes: form.imsUpdate === 'YES',
  imsDcrNo: form.imsDcrNo,
  imsVerifiedBy: form.imsVerifiedBy,

  followUp: form.followUp.map((row) => ({
    date: row.date,
    status: row.status,
    auditor: row.auditor,
    rep: row.rep,
    effective: row.effective,
  })),

  caseClosed: form.caseClosed,
  imrSig: form.imrSig,
  caseClosedDate: form.caseClosedDate,
  deptHeadNotedBy: form.deptHeadNotedBy,

  effectProblem: form.effectProblem,
  causeMan: form.causeMan,
  causeMachine: form.causeMachine,
  causeMaterial: form.causeMaterial,
  causeMethod: form.causeMethod,
  causeOthers: form.causeOthers,
}))

async function runTask(flagRef, message, task) {
  flagRef.value = true
  loading.open(message)

  try {
    return await task()
  } finally {
    flagRef.value = false
    loading.close()
  }
}

async function saveDraft() {
  if (!props.documentTypeId && !props.record?.document_type_id) {
    toast.error('Missing CAR document type ID.')
    return
  }

  await runTask(isSaving, 'Saving draft...', async () => {
    const payload = {
      document_type_id: props.record?.document_type_id ?? props.documentTypeId,
      data: payloadData.value,
    }

    let res

    if (!recordId.value) {
      res = await axios.post('/car/records', payload)
      recordId.value = res.data.id
      toast.success(`Draft saved. Record #${recordId.value}`)
    } else {
      res = await axios.put(`/car/records/${recordId.value}`, payload)
      toast.success(`Draft updated. Record #${recordId.value}`)
    }
  }).catch((err) => {
    console.error(err)
    toast.error(
      err?.response?.data?.message ||
      'Failed to save draft.'
    )
  })
}

async function submitToAdmin() {
  await runTask(isSubmitting, 'Submitting CAR to admin...', async () => {
    if (!recordId.value) {
      await saveDraft()
    }

    if (!recordId.value) {
      throw new Error('Draft was not created.')
    }

    await axios.post(`/car/records/${recordId.value}/submit`)
    toast.success('CAR submitted to admin successfully.')
  }).catch((err) => {
    console.error(err)
    toast.error(
      err?.response?.data?.message ||
      err?.message ||
      'Failed to submit CAR.'
    )
  })
}
</script>

<template>
  <Head title="Create CAR Form" />

  <AdminLayout>
    <div class="h-screen overflow-hidden bg-slate-100 font-sans">
      <div class="flex h-full flex-col gap-6 px-10 py-8">
        <div class="flex shrink-0 flex-wrap items-end justify-between gap-3">
          <div class="min-w-0">
            <div class="flex items-center gap-3">
              <h1 class="truncate text-2xl font-bold tracking-tight text-slate-900">
                Create CAR Form
              </h1>

              <span
                v-if="recordId"
                class="inline-flex items-center rounded-full border border-slate-200 bg-white px-3 py-1 text-[12px] font-semibold text-slate-600"
              >
                Record #{{ recordId }}
              </span>
            </div>

            <p class="mt-1 text-[13px] text-slate-400">
              Corrective Action Request · Leyte Normal University
            </p>
          </div>

          <div class="flex flex-wrap items-center gap-2.5">
            <button
              class="rounded-xl border border-slate-200 bg-white px-5 py-2 text-[13.5px] font-medium text-slate-500 transition hover:bg-slate-50 hover:text-slate-700"
              @click="$inertia.visit('/dashboard')"
            >
              Cancel
            </button>

            <button
              class="rounded-xl border border-slate-200 bg-white px-5 py-2 text-[13.5px] font-semibold text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
              @click="saveDraft"
              :disabled="isSaving"
            >
              <span v-if="!isSaving">{{ recordId ? 'Update Draft' : 'Save Draft' }}</span>
              <span v-else>Saving...</span>
            </button>

            <button
              class="inline-flex items-center gap-2 rounded-xl bg-amber-600 px-5 py-2 text-[13.5px] font-semibold text-white transition hover:bg-amber-700 disabled:cursor-not-allowed disabled:opacity-60"
              @click="submitToAdmin"
              :disabled="isSubmitting"
            >
              <span>{{ isSubmitting ? 'Submitting...' : 'Submit to Admin' }}</span>
            </button>
          </div>
        </div>

        <div
          class="flex flex-1 items-start justify-center overflow-y-auto rounded-2xl border border-slate-200 bg-white p-8 shadow-[0_2px_12px_rgba(0,0,0,0.06)]"
        >
          <div
            class="flex w-[210mm] flex-col border-2 border-black bg-white text-black"
            style="font-family: Arial, Helvetica, sans-serif;"
          >
            <!-- Header -->
            <header class="flex items-center border-b-2 border-black px-2 py-[4px]">
              <div class="mr-2 flex h-[0.57in] w-[0.57in]">
                <img :src="logo" alt="LNU Logo" class="h-full w-full object-contain" />
              </div>
              <div class="flex flex-col">
                <h2 class="mt-[10px] text-[12pt] font-bold leading-[1.1]">LEYTE NORMAL UNIVERSITY</h2>
                <h1 class="text-[12pt] font-bold leading-[1.1]">CORRECTIVE ACTION REQUEST FORM (CAR)</h1>
              </div>
            </header>

            <!-- Main header fields -->
            <section class="border-b-2 border-black px-3 py-2.5">
              <div class="grid grid-cols-[50%_50%] gap-y-2.5">
                <div class="flex items-start pr-8">
                  <div class="w-[88px]"><label class="text-[10pt] font-normal">Dept./ Section</label></div>
                  <span class="mr-3 text-[10pt]">:</span>
                  <input
                    v-model="form.deptSection"
                    type="text"
                    class="h-[14pt] flex-1 border-0 border-b border-black bg-transparent px-1 text-[10pt] outline-none"
                  />
                </div>

                <div class="flex items-start">
                  <div class="w-[70px]"><label class="text-[10pt] font-normal">Ref. No.</label></div>
                  <span class="mr-3 text-[10pt]">:</span>
                  <input
                    v-model="form.refNo"
                    type="text"
                    class="h-[14pt] w-[160px] border-0 border-b border-black bg-transparent px-1 text-[10pt] outline-none"
                  />
                </div>

                <div class="flex items-start pr-8">
                  <div class="w-[88px]"><label class="text-[10pt] font-normal">Auditor</label></div>
                  <span class="mr-3 text-[10pt]">:</span>
                  <input
                    v-model="form.auditor"
                    type="text"
                    class="h-[14pt] flex-1 border-0 border-b border-black bg-transparent px-1 text-[10pt] outline-none"
                  />
                </div>

                <div class="flex items-start">
                  <div class="w-[70px]"><label class="text-[10pt] font-normal">CAR No.</label></div>
                  <span class="mr-3 text-[10pt]">:</span>
                  <input
                    v-model="form.carNo"
                    type="text"
                    class="h-[14pt] w-[160px] border-0 border-b border-black bg-transparent px-1 text-[10pt] outline-none"
                  />
                </div>

                <div class="flex items-start pr-8">
                  <div class="w-[88px]"><label class="text-[10pt] font-normal">Date Reported</label></div>
                  <span class="mr-3 text-[10pt]">:</span>
                  <input
                    v-model="form.dateRep"
                    type="date"
                    class="h-[14pt] flex-1 border-0 border-b border-black bg-transparent px-1 text-[10pt] outline-none"
                  />
                </div>

                <div class="flex items-start">
                  <div class="w-[70px]"><label class="text-[10pt] font-normal">ISO Clause</label></div>
                  <span class="mr-3 text-[10pt]">:</span>
                  <input
                    v-model="form.isoClause"
                    type="text"
                    class="h-[14pt] w-[160px] border-0 border-b border-black bg-transparent px-1 text-[10pt] outline-none"
                  />
                </div>
              </div>

              <div class="mt-2 flex w-full items-center gap-4 text-[10pt]">
                <label class="inline-flex cursor-pointer select-none items-center gap-1 whitespace-nowrap">
                  <input type="checkbox" v-model="form.audit" class="hidden" />
                  <span class="font-normal">Audit</span>
                  <span class="w-[18px] text-center">{{ form.audit ? '(✔)' : '( )' }}</span>
                </label>

                <label class="inline-flex cursor-pointer select-none items-center gap-1 whitespace-nowrap">
                  <input type="checkbox" v-model="form.complaint" class="hidden" />
                  <span class="font-normal">Complaint</span>
                  <span class="w-[18px] text-center">{{ form.complaint ? '(✔)' : '( )' }}</span>
                </label>

                <label class="inline-flex cursor-pointer select-none items-center gap-1 whitespace-nowrap">
                  <input type="checkbox" v-model="form.nonConForm" class="hidden" />
                  <span class="font-normal">Nonconformity</span>
                  <span class="w-[18px] text-center">{{ form.nonConForm ? '(✔)' : '( )' }}</span>
                </label>
              </div>
            </section>

            <!-- 1 Description -->
            <section class="flex min-h-[180pt] flex-col border-b-2 border-black px-2.5 py-1.5">
              <h3 class="mb-[5pt] text-[10pt] font-bold leading-[1.1]">
                DESCRIPTION OF NONCONFORMITY/COMPLAINT:
              </h3>

              <textarea
                v-model="form.descript"
                class="min-h-[65px] w-full resize-none overflow-hidden bg-transparent px-0 py-0 text-[10pt] leading-[1.25] outline-none"
              ></textarea>

              <div class="mt-2">
                <u class="text-[10pt] font-bold">OBJECTIVE EVIDENCE:</u>
                <textarea
                  v-model="form.objective"
                  class="mt-1 min-h-[55px] w-full resize-none overflow-hidden bg-transparent px-0 py-0 text-[10pt] leading-[1.25] outline-none"
                ></textarea>
              </div>

              <div class="mt-2">
                <u class="text-[10pt] font-bold">CONSEQUENCE:</u>
                <textarea
                  v-model="form.conseq"
                  class="mt-1 min-h-[40px] w-full resize-none overflow-hidden bg-transparent px-0 py-0 text-[10pt] leading-[1.25] outline-none"
                ></textarea>
              </div>

              <div class="mt-auto flex justify-between pt-2 text-center">
                <div class="flex w-[30%] flex-col">
                  <input
                    v-model="form.depRep"
                    class="border-0 border-b border-black bg-transparent text-center text-[10pt] outline-none"
                  />
                  <label class="mt-0.5 text-[9pt] leading-[1.2]">
                    Department/Division Representative<br />
                    (Signature over Printed Name)
                  </label>
                </div>

                <div class="flex w-[30%] flex-col">
                  <input
                    v-model="form.sigAuditor"
                    class="border-0 border-b border-black bg-transparent text-center text-[10pt] outline-none"
                  />
                  <label class="mt-0.5 text-[9pt] leading-[1.2]">
                    Auditor<br />
                    (Signature over Printed Name)
                  </label>
                </div>

                <div class="flex w-[30%] flex-col">
                  <input
                    v-model="form.agreedDate"
                    type="date"
                    class="border-0 border-b border-black bg-transparent text-center text-[10pt] outline-none"
                  />
                  <label class="mt-0.5 text-[9pt] leading-[1.2]">
                    Agreed Date of<br />
                    Correction Completion
                  </label>
                </div>
              </div>
            </section>

            <!-- 2 Correction -->
            <section class="flex min-h-[120pt] flex-col border-b-2 border-black px-2.5 py-1.5">
              <h3 class="mb-[5pt] text-[10pt] font-bold leading-[1.1]">
                2. CORRECTION (To be filled out by the Department/Division Representative)
              </h3>

              <textarea
                v-model="form.correction"
                class="min-h-[55px] w-full resize-none overflow-hidden bg-transparent px-0 py-0 text-[10pt] leading-[1.25] outline-none"
              ></textarea>

              <div class="mt-auto flex items-end justify-between gap-6 pt-2">
                <div class="flex flex-1 items-end">
                  <span class="mr-2 text-[10pt] font-normal">Actual Date of Completion:</span>
                  <input
                    v-model="form.actualdate"
                    type="date"
                    class="h-[14pt] flex-1 border-0 border-b border-black bg-transparent px-1 text-[10pt] outline-none"
                  />
                </div>

                <div class="flex flex-1 items-end">
                  <span class="mr-2 text-[10pt] font-normal">Noted By:</span>
                  <input
                    v-model="form.notedby"
                    type="text"
                    class="h-[14pt] flex-1 border-0 border-b border-black bg-transparent px-1 text-[10pt] outline-none"
                  />
                </div>
              </div>
            </section>

            <!-- 3 Root cause -->
            <section class="flex min-h-[130pt] flex-col border-b-2 border-black px-2.5 py-1.5">
              <h3 class="mb-[5pt] text-[10pt] font-bold leading-[1.1]">
                3. ROOT CAUSE ANALYSIS (Use back page as guide.)
              </h3>

              <textarea
                v-model="form.rootCause"
                class="min-h-[55px] w-full resize-none overflow-hidden bg-transparent px-0 py-0 text-[10pt] leading-[1.25] outline-none"
              ></textarea>

              <div class="mt-auto grid grid-cols-2 gap-x-8 gap-y-2 pt-2">
                <div class="flex items-end">
                  <span class="mr-2 text-[10pt]">Conducted by:</span>
                  <input v-model="form.rootCauseBy" class="h-[14pt] flex-1 border-0 border-b border-black bg-transparent px-1 text-[10pt] outline-none" />
                </div>
                <div class="flex items-end">
                  <span class="mr-2 text-[10pt]">Date:</span>
                  <input v-model="form.rootCauseDate" type="date" class="h-[14pt] flex-1 border-0 border-b border-black bg-transparent px-1 text-[10pt] outline-none" />
                </div>
                <div class="flex items-end">
                  <span class="mr-2 text-[10pt]">Noted by:</span>
                  <input v-model="form.rootCauseNotedBy" class="h-[14pt] flex-1 border-0 border-b border-black bg-transparent px-1 text-[10pt] outline-none" />
                </div>
                <div class="flex items-end">
                  <span class="mr-2 text-[10pt]">Date:</span>
                  <input v-model="form.rootCauseNotedDate" type="date" class="h-[14pt] flex-1 border-0 border-b border-black bg-transparent px-1 text-[10pt] outline-none" />
                </div>
              </div>
            </section>

            <!-- 4 Corrective action -->
            <section class="flex min-h-[120pt] flex-col border-b-2 border-black px-2.5 py-1.5">
              <h3 class="mb-[5pt] text-[10pt] font-bold leading-[1.1]">
                4. CORRECTIVE ACTION (To be filled out by the Department/Division Representative)
              </h3>

              <textarea
                v-model="form.correctiveAction"
                class="min-h-[55px] w-full resize-none overflow-hidden bg-transparent px-0 py-0 text-[10pt] leading-[1.25] outline-none"
              ></textarea>

              <div class="mt-auto flex items-end justify-between gap-6 pt-2">
                <div class="flex flex-1 items-end">
                  <span class="mr-2 text-[10pt] font-normal">Actual Date of Completion:</span>
                  <input
                    v-model="form.correctiveActionDate"
                    type="date"
                    class="h-[14pt] flex-1 border-0 border-b border-black bg-transparent px-1 text-[10pt] outline-none"
                  />
                </div>

                <div class="flex flex-1 items-end">
                  <span class="mr-2 text-[10pt] font-normal">Noted By:</span>
                  <input
                    v-model="form.correctiveActionNotedBy"
                    type="text"
                    class="h-[14pt] flex-1 border-0 border-b border-black bg-transparent px-1 text-[10pt] outline-none"
                  />
                </div>
              </div>
            </section>

            <!-- 5 Risk -->
            <section class="border-b-2 border-black px-2.5 py-1.5">
              <h3 class="mb-[5pt] text-[10pt] font-bold leading-[1.1]">
                5. RISK/OPPORTUNITY ASSESSMENT REQUIRES UPDATING?
              </h3>

              <div class="flex items-center gap-2 whitespace-nowrap text-[11px] font-bold">
                <label class="relative inline-flex cursor-pointer items-center gap-1">
                  <input type="radio" v-model="form.riskUpdate" value="NO" class="hidden" />
                  <span class="relative inline-block h-[10px] w-[20px] border-b border-black">
                    <span v-if="form.riskUpdate === 'NO'" class="absolute left-[2px] -top-[13px] text-[9pt]">✔</span>
                  </span>
                  <span>NO</span>
                </label>

                <label class="relative inline-flex cursor-pointer items-center gap-1">
                  <input type="radio" v-model="form.riskUpdate" value="YES" class="hidden" />
                  <span class="relative inline-block h-[10px] w-[20px] border-b border-black">
                    <span v-if="form.riskUpdate === 'YES'" class="absolute left-[2px] -top-[13px] text-[9pt]">✔</span>
                  </span>
                  <span>YES,</span>
                </label>

                <span class="font-bold">Date Updated:</span>
                <input
                  v-model="form.riskDateUpdated"
                  type="date"
                  class="w-[150px] border-0 border-b border-black bg-transparent px-1 text-[10pt] font-normal outline-none"
                />

                <span class="font-bold">Verified By:</span>
                <input
                  v-model="form.riskVerifiedBy"
                  class="min-w-[160px] flex-1 border-0 border-b border-black bg-transparent px-1 text-[10pt] font-normal outline-none"
                />
              </div>
            </section>

            <!-- 6 IMS -->
            <section class="border-b-2 border-black px-2.5 py-1.5">
              <h3 class="mb-[5pt] text-[10pt] font-bold leading-[1.1]">6. IMS REQUIRES UPDATING?</h3>

              <div class="flex items-center gap-2 whitespace-nowrap text-[11px] font-bold">
                <label class="relative inline-flex cursor-pointer items-center gap-1">
                  <input type="radio" v-model="form.imsUpdate" value="NO" class="hidden" />
                  <span class="relative inline-block h-[10px] w-[20px] border-b border-black">
                    <span v-if="form.imsUpdate === 'NO'" class="absolute left-[2px] -top-[13px] text-[9pt]">✔</span>
                  </span>
                  <span>NO</span>
                </label>

                <label class="relative inline-flex cursor-pointer items-center gap-1">
                  <input type="radio" v-model="form.imsUpdate" value="YES" class="hidden" />
                  <span class="relative inline-block h-[10px] w-[20px] border-b border-black">
                    <span v-if="form.imsUpdate === 'YES'" class="absolute left-[2px] -top-[13px] text-[9pt]">✔</span>
                  </span>
                  <span>YES,</span>
                </label>

                <span class="font-bold">DCR No:</span>
                <input
                  v-model="form.imsDcrNo"
                  class="w-[150px] border-0 border-b border-black bg-transparent px-1 text-[10pt] font-normal outline-none"
                />

                <span class="font-bold">Verified By:</span>
                <input
                  v-model="form.imsVerifiedBy"
                  class="min-w-[160px] flex-1 border-0 border-b border-black bg-transparent px-1 text-[10pt] font-normal outline-none"
                />
              </div>
            </section>

            <!-- 7 Follow-up -->
            <section class="border-b-2 border-black px-2.5 py-1.5 pb-1">
              <h3 class="mb-2 text-[10pt] font-bold leading-[1.1]">7. FOLLOW-UP COMMENTS</h3>

              <div class="flex flex-col text-center text-[9pt] leading-[1.2]">
                <div class="flex items-end">
                  <div class="w-[10%]">Date</div>
                  <div class="w-[30%]">Status</div>
                  <div class="w-[20%]">Auditor</div>
                  <div class="w-[20%]">Representative</div>
                  <div class="w-[20%]">Effective? (Y/N)</div>
                </div>
              </div>

              <div class="mt-0.5 flex flex-col gap-1.5">
                <div class="flex min-h-[18px] items-end" v-for="(row, index) in form.followUp" :key="index">
                  <div class="w-[10%] border-b border-black px-0.5 py-[1px]">
                    <input v-model="row.date" type="date" class="h-4 w-full bg-transparent text-center text-[9pt] outline-none" />
                  </div>

                  <div class="w-[30%] border-b border-black px-0.5 py-[1px]">
                    <input v-model="row.status" class="h-4 w-full bg-transparent text-center text-[9pt] outline-none" />
                  </div>

                  <div class="w-[20%] border-b border-black px-0.5 py-[1px]">
                    <input v-model="row.auditor" class="h-4 w-full bg-transparent text-center text-[9pt] outline-none" />
                  </div>

                  <div class="w-[20%] border-b border-black px-0.5 py-[1px]">
                    <input v-model="row.rep" class="h-4 w-full bg-transparent text-center text-[9pt] outline-none" />
                  </div>

                  <div class="w-[20%] border-b border-black px-0.5 py-[1px]">
                    <input v-model="row.effective" class="h-4 w-full bg-transparent text-center text-[9pt] outline-none" />
                  </div>
                </div>
              </div>
            </section>

            <!-- 8 Case closed -->
            <section class="border-b-2 border-black px-2.5 py-1.5">
              <h3 class="mb-[5pt] text-[10pt] font-bold leading-[1.1]">8. CASE CLOSED</h3>

              <div class="mb-2">
                <input
                  v-model="form.caseClosed"
                  class="w-full border-0 border-b border-black bg-transparent px-1 text-[10pt] outline-none"
                />
              </div>

              <div class="mt-4 flex justify-between text-center">
                <div class="flex w-[30%] flex-col">
                  <input v-model="form.imrSig" class="border-0 border-b border-black bg-transparent text-center text-[10pt] outline-none" />
                  <label class="mt-0.5 text-[9pt] leading-[1.2]">Integrated Management Representative</label>
                </div>

                <div class="flex w-[30%] flex-col">
                  <input
                    type="date"
                    v-model="form.caseClosedDate"
                    class="border-0 border-b border-black bg-transparent text-center text-[10pt] outline-none"
                  />
                  <label class="mt-0.5 text-[9pt] leading-[1.2]">Date</label>
                </div>

                <div class="flex w-[30%] flex-col">
                  <input v-model="form.deptHeadNotedBy" class="border-0 border-b border-black bg-transparent text-center text-[10pt] outline-none" />
                  <label class="mt-0.5 text-[9pt] leading-[1.2]">Noted By (Department Head)</label>
                </div>
              </div>
            </section>

            <!-- 9 Cause and effect -->
            <section class="px-2.5 py-1.5">
              <h3 class="mb-[5pt] text-[10pt] font-bold leading-[1.1]">9. CAUSE AND EFFECT DIAGRAM</h3>

              <div class="mb-3">
                <div class="mb-1 text-[10pt] font-bold">EFFECT/PROBLEM</div>
                <textarea
                  v-model="form.effectProblem"
                  class="min-h-[42px] w-full resize-none overflow-hidden border border-black bg-transparent px-2 py-1 text-[10pt] leading-[1.2] outline-none"
                ></textarea>
              </div>

              <div class="grid grid-cols-2 gap-3">
                <div>
                  <div class="mb-1 text-[10pt] font-bold">Man</div>
                  <textarea
                    v-model="form.causeMan"
                    class="min-h-[58px] w-full resize-none overflow-hidden border border-black bg-transparent px-2 py-1 text-[10pt] leading-[1.2] outline-none"
                  ></textarea>
                </div>

                <div>
                  <div class="mb-1 text-[10pt] font-bold">Machine</div>
                  <textarea
                    v-model="form.causeMachine"
                    class="min-h-[58px] w-full resize-none overflow-hidden border border-black bg-transparent px-2 py-1 text-[10pt] leading-[1.2] outline-none"
                  ></textarea>
                </div>

                <div>
                  <div class="mb-1 text-[10pt] font-bold">Material</div>
                  <textarea
                    v-model="form.causeMaterial"
                    class="min-h-[58px] w-full resize-none overflow-hidden border border-black bg-transparent px-2 py-1 text-[10pt] leading-[1.2] outline-none"
                  ></textarea>
                </div>

                <div>
                  <div class="mb-1 text-[10pt] font-bold">Method</div>
                  <textarea
                    v-model="form.causeMethod"
                    class="min-h-[58px] w-full resize-none overflow-hidden border border-black bg-transparent px-2 py-1 text-[10pt] leading-[1.2] outline-none"
                  ></textarea>
                </div>

                <div class="col-span-2">
                  <div class="mb-1 text-[10pt] font-bold">Others</div>
                  <textarea
                    v-model="form.causeOthers"
                    class="min-h-[50px] w-full resize-none overflow-hidden border border-black bg-transparent px-2 py-1 text-[10pt] leading-[1.2] outline-none"
                  ></textarea>
                </div>
              </div>
            </section>

            <footer class="border-t-2 border-black px-2.5 py-1 text-[9px]">
              F-QMS-006 Rev. 1 (02-02-26)
            </footer>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<style scoped></style>