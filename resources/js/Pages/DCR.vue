<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { reactive, ref } from 'vue'
import axios from 'axios'
import logo from '@/images/LNU_logo.png'

const isGenerating = ref(false)
const errorMessage = ref('')

const form = reactive({
  date: '',
  dcrNo: '',
  toFor: '',
  from: '',

  // checkbox (pick one)
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

  // IMR comments
  requestDecision: '', // 'DENIED' | 'ACCEPTED'
  imrSigDate: '',

  // approving authority
  approvingSigName: '',
  approvingDate: '',

  // document status
  statusNo: '',
  statusVersion: '',
  statusRevision: '',
  effectivityDate: '',
  idsDateUpdated: '',
  updatedBy: '',
})

function pickOnlyOne(key) {
  // only one of amend/newDoc/deleteDoc
  form.amend = false
  form.newDoc = false
  form.deleteDoc = false
  form[key] = true
}

async function generateDocx() {
  isGenerating.value = true
  errorMessage.value = ''
  try {
    // change endpoint to your actual DCR route
    const response = await axios.post('/dcr/generate', form, { responseType: 'blob' })
    const url = window.URL.createObjectURL(new Blob([response.data]))
    const link = document.createElement('a')
    link.href = url
    link.download = `DCR_${form.dcrNo || 'form'}.docx`
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(url)
  } catch (err) {
    console.error(err)
    errorMessage.value = 'Failed to generate document. Please try again.'
  } finally {
    isGenerating.value = false
  }
}
</script>

<template>
  <AdminLayout>
    <div class="h-screen overflow-hidden bg-slate-100 font-sans">
      <!-- Page Container -->
      <div class="flex h-full flex-col gap-6 px-10 py-8">
        <!-- Page Header (same pattern as OFI) -->
        <div class="flex flex-wrap items-end justify-between gap-3 shrink-0">
          <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Create DCR Form</h1>
            <p class="mt-1 text-[13px] text-slate-400">
              Document Change Request · Leyte Normal University
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
              class="inline-flex items-center gap-2 rounded-xl bg-slate-800 px-5 py-2 text-[13.5px] font-semibold text-white transition hover:bg-slate-900 disabled:cursor-not-allowed disabled:bg-slate-400"
              @click="generateDocx"
              :disabled="isGenerating"
            >
              <svg
                v-if="!isGenerating"
                class="h-4 w-4"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
              >
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                <polyline points="7 10 12 15 17 10" />
                <line x1="12" y1="15" x2="12" y2="3" />
              </svg>

              <svg
                v-else
                class="h-4 w-4 animate-spin"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
              >
                <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/>
              </svg>

              <span>{{ isGenerating ? 'Generating...' : 'Save & Download DOCX' }}</span>
            </button>

            <p v-if="errorMessage" class="m-0 text-xs text-red-500">
              {{ errorMessage }}
            </p>
          </div>
        </div>

        <!-- Form Card (same as OFI) -->
        <div
          class="flex-1 overflow-y-auto rounded-2xl border border-slate-200 bg-white p-8 shadow-[0_2px_12px_rgba(0,0,0,0.06)] flex justify-center items-start"
        >
          <!-- A4 Document -->
          <div
            class="w-[210mm] border-2 border-black bg-white text-black flex flex-col"
            style="font-family: Arial, Helvetica, sans-serif;"
          >
            <!-- Header -->
            <header class="flex items-center border-b-2 border-black px-2 py-[2px]">
              <div class="mr-2 flex h-[0.57in] w-[0.57in]">
                <img :src="logo" alt="LNU Logo" class="h-full w-full object-contain" />
              </div>
              <div class="flex flex-col">
                <h2 class="mt-[19px] text-[12pt] font-bold leading-[1.1]">LEYTE NORMAL UNIVERSITY</h2>
                <h1 class="text-[12pt] font-bold leading-[1.1]">DOCUMENT CHANGE REQUEST</h1>
              </div>
            </header>

            <!-- Meta (DATE, DCR No., TO/FOR, FROM) -->
            <section class="border-b-2 border-black px-3 py-2.5">
              <div class="grid grid-cols-[55%_45%] gap-y-2.5">
                <div class="flex items-start pr-10">
                  <div class="w-[50px]"><label class="text-[10pt]">DATE</label></div>
                  <span class="mr-3 text-[10pt]">:</span>
                  <input
                    type="date"
                    v-model="form.date"
                    class="h-[14pt] flex-1 border-0 border-b border-black bg-transparent px-1 text-[10pt] outline-none"
                  />
                </div>

                <div class="flex items-start">
                  <div class="w-[80px]"><label class="text-[10pt]">DCR No.</label></div>
                  <span class="mr-3 text-[10pt]">:</span>
                  <input
                    type="text"
                    v-model="form.dcrNo"
                    class="h-[14pt] w-[150px] flex-none border-0 border-b border-black bg-transparent px-1 text-[10pt] outline-none"
                  />
                </div>

                <div class="flex items-start pr-10">
                  <div class="w-[50px]"><label class="text-[10pt]">TO/FOR</label></div>
                  <span class="mr-3 text-[10pt]">:</span>
                  <input
                    type="text"
                    v-model="form.toFor"
                    class="h-[14pt] flex-1 border-0 border-b border-black bg-transparent px-1 text-[10pt] outline-none"
                  />
                </div>

                <div class="flex items-start">
                  <div class="w-[80px]"><label class="text-[10pt]">FROM</label></div>
                  <span class="mr-3 text-[10pt]">:</span>
                  <input
                    type="text"
                    v-model="form.from"
                    class="h-[14pt] w-[150px] flex-none border-0 border-b border-black bg-transparent px-1 text-[10pt] outline-none"
                  />
                </div>
              </div>
            </section>

            <!-- Checkbox row (OFI style [✔]) -->
            <section class="border-b-2 border-black px-3 py-2.5">
              <div class="flex flex-wrap gap-6 text-[10pt]">
                <label class="inline-flex items-center gap-2 cursor-pointer select-none" @click="pickOnlyOne('amend')">
                  <span class="w-[18px] text-center">{{ form.amend ? '[✔]' : '[ ]' }}</span>
                  <span>Amend document</span>
                </label>

                <label class="inline-flex items-center gap-2 cursor-pointer select-none" @click="pickOnlyOne('newDoc')">
                  <span class="w-[18px] text-center">{{ form.newDoc ? '[✔]' : '[ ]' }}</span>
                  <span>New document</span>
                </label>

                <label class="inline-flex items-center gap-2 cursor-pointer select-none" @click="pickOnlyOne('deleteDoc')">
                  <span class="w-[18px] text-center">{{ form.deleteDoc ? '[✔]' : '[ ]' }}</span>
                  <span>Delete document</span>
                </label>
              </div>
            </section>

            <!-- 1. DETAILS OF DOCUMENT -->
            <section class="border-b-2 border-black px-3 py-2.5">
              <h3 class="mb-2 text-[10pt] font-bold">1. DETAILS OF DOCUMENT</h3>

              <div class="grid gap-y-2">
                <div class="flex items-start">
                  <div class="w-[110px] text-[10pt]">Document Number</div>
                  <span class="mr-3 text-[10pt]">:</span>
                  <input v-model="form.documentNumber" class="h-[14pt] flex-1 border-0 border-b border-black bg-transparent px-1 text-[10pt] outline-none" />
                </div>

                <div class="flex items-start">
                  <div class="w-[110px] text-[10pt]">Document Title</div>
                  <span class="mr-3 text-[10pt]">:</span>
                  <input v-model="form.documentTitle" class="h-[14pt] flex-1 border-0 border-b border-black bg-transparent px-1 text-[10pt] outline-none" />
                </div>

                <div class="flex items-start">
                  <div class="w-[110px] text-[10pt]">Revision Status</div>
                  <span class="mr-3 text-[10pt]">:</span>
                  <input v-model="form.revisionStatus" class="h-[14pt] flex-1 border-0 border-b border-black bg-transparent px-1 text-[10pt] outline-none" />
                </div>

                <div class="text-[9pt] italic">Note: Please attach draft copy of the document.</div>
              </div>
            </section>

            <!-- 2. CHANGES REQUESTED -->
            <section class="border-b-2 border-black px-3 py-2.5">
              <h3 class="mb-2 text-[10pt] font-bold">2. CHANGE(S) REQUESTED</h3>
              <textarea v-model="form.changesRequested" class="h-[35mm] w-full resize-none border border-black p-2 text-[10pt] outline-none"></textarea>

              <div class="mt-3 text-[10pt] font-bold">REASON FOR THE CHANGE</div>
              <textarea v-model="form.reason" class="mt-2 h-[28mm] w-full resize-none border border-black p-2 text-[10pt] outline-none"></textarea>

              <div class="mt-5 flex justify-between gap-10 text-center">
                <div class="w-1/2">
                  <input v-model="form.requestedBy" class="w-full border-0 border-b border-black bg-transparent text-[10pt] text-center outline-none" />
                  <div class="mt-1 text-[9pt]">Requested by</div>
                </div>
                <div class="w-1/2">
                  <input v-model="form.deptUnitHead" class="w-full border-0 border-b border-black bg-transparent text-[10pt] text-center outline-none" />
                  <div class="mt-1 text-[9pt]">Department/Unit Head</div>
                </div>
              </div>
            </section>

            <!-- 3. IMR COMMENTS -->
            <section class="border-b-2 border-black px-3 py-2.5">
              <h3 class="mb-2 text-[10pt] font-bold">3. INTEGRATED MANAGEMENT REPRESENTATIVE’S COMMENTS</h3>

              <div class="flex items-center gap-10 text-[10pt] font-bold">
                <label class="inline-flex items-center gap-2 cursor-pointer select-none">
                  <input type="radio" class="hidden" value="DENIED" v-model="form.requestDecision" />
                  <span class="w-[18px] text-center">{{ form.requestDecision === 'DENIED' ? '[✔]' : '[ ]' }}</span>
                  <span>Request Denied</span>
                </label>

                <label class="inline-flex items-center gap-2 cursor-pointer select-none">
                  <input type="radio" class="hidden" value="ACCEPTED" v-model="form.requestDecision" />
                  <span class="w-[18px] text-center">{{ form.requestDecision === 'ACCEPTED' ? '[✔]' : '[ ]' }}</span>
                  <span>Request Accepted</span>
                </label>
              </div>

              <div class="mt-5 w-[80mm]">
                <input v-model="form.imrSigDate" class="w-full border-0 border-b border-black bg-transparent text-[10pt] text-center outline-none" />
                <div class="mt-1 text-center text-[9pt]">Signature/Date</div>
              </div>
            </section>

            <!-- 4. APPROVING AUTHORITY -->
            <section class="border-b-2 border-black px-3 py-2.5">
              <h3 class="mb-2 text-[10pt] font-bold">4. APPROVING AUTHORITY</h3>

              <div class="flex items-end gap-10 text-center">
                <div class="flex-1">
                  <input v-model="form.approvingSigName" class="w-full border-0 border-b border-black bg-transparent text-[10pt] text-center outline-none" />
                  <div class="mt-1 text-[9pt]">Signature Over Printed Name</div>
                </div>
                <div class="w-[45mm]">
                  <input v-model="form.approvingDate" class="w-full border-0 border-b border-black bg-transparent text-[10pt] text-center outline-none" />
                  <div class="mt-1 text-[9pt]">Date</div>
                </div>
              </div>
            </section>

            <!-- 5. DOCUMENT STATUS -->
            <section class="px-3 py-2.5">
              <h3 class="mb-2 text-[10pt] font-bold">5. DOCUMENT STATUS</h3>

              <div class="grid gap-y-2 text-[10pt]">
                <div class="flex items-start gap-2">
                  <span class="w-[28px] font-bold">No.</span><span>:</span>
                  <input v-model="form.statusNo" class="flex-1 border-0 border-b border-black bg-transparent px-1 outline-none" />
                  <span class="w-[60px] font-bold">Version</span><span>:</span>
                  <input v-model="form.statusVersion" class="w-[70px] border-0 border-b border-black bg-transparent px-1 outline-none" />
                  <span class="w-[65px] font-bold">Revision</span><span>:</span>
                  <input v-model="form.statusRevision" class="w-[70px] border-0 border-b border-black bg-transparent px-1 outline-none" />
                </div>

                <div class="flex items-start gap-2">
                  <span class="w-[105px] font-bold">Effectivity Date</span><span>:</span>
                  <input v-model="form.effectivityDate" class="flex-1 border-0 border-b border-black bg-transparent px-1 outline-none" />
                </div>

                <div class="flex items-start gap-2">
                  <span class="w-[140px] font-bold">Date Updated in IDS</span><span>:</span>
                  <input v-model="form.idsDateUpdated" class="flex-1 border-0 border-b border-black bg-transparent px-1 outline-none" />
                  <span class="w-[85px] font-bold">Updated by</span><span>:</span>
                  <input v-model="form.updatedBy" class="flex-1 border-0 border-b border-black bg-transparent px-1 outline-none" />
                </div>
              </div>

              <div class="mt-3 border-t-2 border-black pt-1 text-right text-[9px]">
                F-QMS-001 Rev. 1 (01-05-26)
              </div>
            </section>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>