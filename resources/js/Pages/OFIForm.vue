<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { reactive, onMounted, ref } from 'vue'
import axios from 'axios'
import logo from '@/images/LNU_logo.png'

const suggestionBox = ref(null)
const isGenerating = ref(false)
const errorMessage = ref('')

const form = reactive({
  date: '',
  refNo: '',
  to: '',
  ofiNo: '',
  from: '',
  isoClause: '',
  sourceIqa: false,
  sourceFeedback: false,
  sourceSurvey: false,
  sourceSystem: false,
  sourceOthersCheck: false,
  sourceOthersText: '',
  suggestion: '',
  deptRepSig1: '',
  requestedBySig: '',
  agreedDate: '',
  beneficialImpact: '',
  associatedRisks: '',
  action: '',
  deptRepDate2: '',
  deptHeadDate2: '',
  assessmentUpdate: null,
  dateUpdated: '',
  verifiedBy1: '',
  imsUpdate: null,
  dcrNo: '',
  verifiedBy2: '',
  followSig: '',
  followUp: [
    { date: '', status: '', effective: '', auditor: '', rep: '' },
    { date: '', status: '', effective: '', auditor: '', rep: '' },
    { date: '', status: '', effective: '', auditor: '', rep: '' },
    { date: '', status: '', effective: '', auditor: '', rep: '' },
  ],
  imrSig: '',
  caseClosedDate: '',
  notedBy: '',
})

onMounted(() => {
  if (suggestionBox.value && form.suggestion) {
    suggestionBox.value.innerText = form.suggestion
  }
})

async function generateDocx() {
  isGenerating.value = true
  errorMessage.value = ''
  try {
    const response = await axios.post('/ofi/generate', form, { responseType: 'blob' })
    const url = window.URL.createObjectURL(new Blob([response.data]))
    const link = document.createElement('a')
    link.href = url
    link.download = `OFI_${form.ofiNo || 'form'}.docx`
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(url)
  } catch (err) {
    console.error('Error generating DOCX:', err)
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
        <!-- Page Header -->
        <div class="flex flex-wrap items-end justify-between gap-3 shrink-0">
          <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Create OFI Form</h1>
            <p class="mt-1 text-[13px] text-slate-400">
              Opportunities for Improvement · Leyte Normal University
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
                <path
                  d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"
                />
              </svg>

              <span>{{ isGenerating ? 'Generating...' : 'Save & Download DOCX' }}</span>
            </button>

            <p v-if="errorMessage" class="m-0 text-xs text-red-500">
              {{ errorMessage }}
            </p>
          </div>
        </div>

        <!-- Form Card -->
        <div
          class="flex-1 overflow-y-auto rounded-2xl border border-slate-200 bg-white p-8 shadow-[0_2px_12px_rgba(0,0,0,0.06)] flex justify-center items-start"
        >
          <!-- Document (A4 width) -->
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
                <h1 class="text-[12pt] font-bold leading-[1.1]">
                  OPPORTUNITIES FOR IMPROVEMENT FORM (OFI)
                </h1>
              </div>
            </header>

            <!-- Metadata -->
            <section class="border-b-2 border-black px-3 py-2.5">
              <div class="grid grid-cols-[55%_45%] gap-y-2.5">
                <!-- DATE -->
                <div class="flex items-start pr-10">
                  <div class="w-[50px]">
                    <label class="text-[10pt] font-normal">DATE</label>
                  </div>
                  <span class="mr-3 text-[10pt]">:</span>
                  <input
                    type="date"
                    v-model="form.date"
                    class="h-[14pt] flex-1 border-0 border-b border-black bg-transparent px-1 text-[10pt] outline-none"
                  />
                </div>

                <!-- REF NO -->
                <div class="flex items-start">
                  <div class="w-[80px]">
                    <label class="text-[10pt] font-normal">REF. NO</label>
                  </div>
                  <span class="mr-3 text-[10pt]">:</span>
                  <input
                    type="text"
                    v-model="form.refNo"
                    class="h-[14pt] w-[150px] flex-none border-0 border-b border-black bg-transparent px-1 text-[10pt] outline-none"
                  />
                </div>

                <!-- TO -->
                <div class="flex items-start pr-10">
                  <div class="w-[50px]">
                    <label class="text-[10pt] font-normal">TO</label>
                  </div>
                  <span class="mr-3 text-[10pt]">:</span>
                  <div class="flex flex-1 flex-col items-center">
                    <input
                      type="text"
                      v-model="form.to"
                      class="h-[14pt] w-full border-0 border-b border-black bg-transparent px-1 text-[10pt] outline-none"
                    />
                    <small class="mt-1 text-[9pt]">Area Concerned</small>
                  </div>
                </div>

                <!-- OFI NO -->
                <div class="flex items-start">
                  <div class="w-[80px]">
                    <label class="text-[10pt] font-normal">OFI NO.</label>
                  </div>
                  <span class="mr-3 text-[10pt]">:</span>
                  <input
                    type="text"
                    v-model="form.ofiNo"
                    class="h-[14pt] w-[150px] flex-none border-0 border-b border-black bg-transparent px-1 text-[10pt] outline-none"
                  />
                </div>

                <!-- FROM -->
                <div class="flex items-start pr-10">
                  <div class="w-[50px]">
                    <label class="text-[10pt] font-normal">FROM</label>
                  </div>
                  <span class="mr-3 text-[10pt]">:</span>
                  <input
                    type="text"
                    v-model="form.from"
                    class="h-[14pt] flex-1 border-0 border-b border-black bg-transparent px-1 text-[10pt] outline-none"
                  />
                </div>

                <!-- ISO Clause -->
                <div class="flex items-start">
                  <div class="w-[80px] flex flex-col">
                    <label class="text-[10pt] font-normal">ISO Clause</label>
                    <small class="mt-1 text-[9pt]">(if applicable)</small>
                  </div>
                  <span class="mr-3 text-[10pt]">:</span>
                  <input
                    type="text"
                    v-model="form.isoClause"
                    class="h-[14pt] w-[150px] flex-none border-0 border-b border-black bg-transparent px-1 text-[10pt] outline-none"
                  />
                </div>
              </div>

              <!-- SOURCE -->
              <div class="mt-1.5 flex w-full items-center gap-3 text-[10pt]">
                <span class="mr-1 font-bold">SOURCE:</span>

                <label class="inline-flex items-center gap-1 whitespace-nowrap cursor-pointer select-none">
                  <input type="checkbox" v-model="form.sourceIqa" class="hidden" />
                  <span class="font-normal">IQA</span>
                  <span class="w-[18px] text-center">{{ form.sourceIqa ? '[✔]' : '[ ]' }}</span>
                </label>

                <label class="inline-flex items-center gap-1 whitespace-nowrap cursor-pointer select-none">
                  <input type="checkbox" v-model="form.sourceFeedback" class="hidden" />
                  <span class="font-normal">Employee Feedback</span>
                  <span class="w-[18px] text-center">{{ form.sourceFeedback ? '[✔]' : '[ ]' }}</span>
                </label>

                <label class="inline-flex items-center gap-1 whitespace-nowrap cursor-pointer select-none">
                  <input type="checkbox" v-model="form.sourceSurvey" class="hidden" />
                  <span class="font-normal">Survey</span>
                  <span class="w-[18px] text-center">{{ form.sourceSurvey ? '[✔]' : '[ ]' }}</span>
                </label>

                <label class="inline-flex items-center gap-1 whitespace-nowrap cursor-pointer select-none">
                  <input type="checkbox" v-model="form.sourceSystem" class="hidden" />
                  <span class="font-normal">System Review</span>
                  <span class="w-[18px] text-center">{{ form.sourceSystem ? '[✔]' : '[ ]' }}</span>
                </label>

                <div class="flex min-w-[100px] flex-1 items-center gap-1">
                  <label class="inline-flex items-center gap-1 whitespace-nowrap cursor-pointer select-none">
                    <input type="checkbox" v-model="form.sourceOthersCheck" class="hidden" />
                    <span class="font-normal">Others</span>
                    <span class="w-[18px] text-center">{{ form.sourceOthersCheck ? '[✔]' : '[ ]' }}</span>
                  </label>

                  <input
                    type="text"
                    v-model="form.sourceOthersText"
                    class="h-[14pt] w-full border-0 border-b border-black bg-transparent px-1 text-[10pt] outline-none"
                  />
                </div>
              </div>
            </section>

            <!-- Section 1 -->
            <section class="border-b-2 border-black px-2.5 py-1.5 flex flex-col min-h-[130pt]">
              <h3 class="mb-[5pt] text-[10pt] font-bold leading-[1.1]">1. SUGGESTION/RECOMMENDATION</h3>

              <div
                ref="suggestionBox"
                contenteditable="true"
                class="min-h-[85px] w-full cursor-text whitespace-pre-wrap break-words px-0 py-[5px] text-[10pt] leading-[1.3] outline-none"
                style="text-indent: 10pt;"
                @input="form.suggestion = $event.target.innerText"
              ></div>

              <div class="mt-auto flex justify-between pt-1.5 text-center">
                <div class="w-[30%] flex flex-col">
                  <input
                    type="text"
                    v-model="form.deptRepSig1"
                    class="border-0 border-b border-black bg-transparent text-center text-[10pt] outline-none"
                  />
                  <label class="mt-0.5 text-[9pt] leading-[1.2]">
                    Department Representative<br />(Signature over Printed Name)
                  </label>
                </div>

                <div class="w-[30%] flex flex-col">
                  <input
                    type="text"
                    v-model="form.requestedBySig"
                    class="border-0 border-b border-black bg-transparent text-center text-[10pt] outline-none"
                  />
                  <label class="mt-0.5 text-[9pt] leading-[1.2]">
                    Requested By<br />(Signature over Printed Name)
                  </label>
                </div>

                <div class="w-[30%] flex flex-col">
                  <input
                    type="text"
                    v-model="form.agreedDate"
                    class="border-0 border-b border-black bg-transparent text-center text-[10pt] outline-none"
                  />
                  <label class="mt-0.5 text-[9pt] leading-[1.2]">
                    Agreed Date<br />1st Follow-up
                  </label>
                </div>
              </div>
            </section>

            <!-- Section 2 -->
            <section class="border-b-2 border-black px-2.5 py-1.5 flex flex-col min-h-[250px]">
              <h3 class="mb-[5pt] text-[10pt] font-bold leading-[1.1]">
                2. ANALYSIS (To be filled out by the Dept. Representative)
              </h3>

              <div class="mb-2">
                <u class="text-[11px] font-bold">BENEFICIAL IMPACT</u>
                <textarea
                  v-model="form.beneficialImpact"
                  rows="2"
                  class="mt-1 w-full resize-none overflow-hidden bg-transparent px-0 py-0 text-[10pt] leading-[1.25] outline-none"
                ></textarea>
              </div>

              <div class="mb-2">
                <u class="text-[11px] font-bold">ASSOCIATED RISKS</u>
                <textarea
                  v-model="form.associatedRisks"
                  rows="2"
                  class="mt-1 w-full resize-none overflow-hidden bg-transparent px-0 py-0 text-[10pt] leading-[1.25] outline-none"
                ></textarea>
              </div>

              <div class="flex flex-col flex-1">
                <u class="text-[11px] font-bold">ACTION</u>
                <textarea
                  v-model="form.action"
                  class="mt-1 w-full flex-1 resize-none overflow-hidden bg-transparent px-0 py-0 text-[10pt] leading-[1.25] outline-none min-h-[40px]"
                ></textarea>
              </div>

              <div class="mt-auto flex justify-between pt-1.5 text-center">
                <div class="w-[45%] flex flex-col">
                  <input
                    type="text"
                    v-model="form.deptRepDate2"
                    class="border-0 border-b border-black bg-transparent text-center text-[10pt] outline-none"
                  />
                  <label class="mt-0.5 text-[9pt] leading-[1.2]">
                    Department Representative/Date
                  </label>
                </div>

                <div class="w-[45%] flex flex-col">
                  <input
                    type="text"
                    v-model="form.deptHeadDate2"
                    class="border-0 border-b border-black bg-transparent text-center text-[10pt] outline-none"
                  />
                  <label class="mt-0.5 text-[9pt] leading-[1.2]">Department Head/Date</label>
                </div>
              </div>
            </section>

            <!-- Section 3 -->
            <section class="border-b-2 border-black px-2.5 py-1.5">
              <h3 class="mb-[5pt] text-[10pt] font-bold leading-[1.1]">
                3. RISK/OPPORTUNITY ASSESSMENT REQUIRES UPDATING?
              </h3>

              <div class="flex items-center gap-2 text-[11px] font-bold whitespace-nowrap">
                <label class="relative inline-flex items-center gap-1 cursor-pointer">
                  <input type="radio" v-model="form.assessmentUpdate" value="NO" class="hidden" />
                  <span class="inline-block h-[10px] w-[20px] border-b border-black relative">
                    <span v-if="form.assessmentUpdate === 'NO'" class="absolute left-[2px] -top-[13px] text-[9pt]"
                      >✔</span
                    >
                  </span>
                  <span>NO</span>
                </label>

                <label class="relative inline-flex items-center gap-1 cursor-pointer">
                  <input type="radio" v-model="form.assessmentUpdate" value="YES" class="hidden" />
                  <span class="inline-block h-[10px] w-[20px] border-b border-black relative">
                    <span v-if="form.assessmentUpdate === 'YES'" class="absolute left-[2px] -top-[13px] text-[9pt]"
                      >✔</span
                    >
                  </span>
                  <span>YES,</span>
                </label>

                <span class="font-bold">Date Updated:</span>
                <input
                  type="text"
                  v-model="form.dateUpdated"
                  class="w-[150px] border-0 border-b border-black bg-transparent px-1 text-[10pt] font-normal outline-none"
                />

                <span class="font-bold">Verified By:</span>
                <input
                  type="text"
                  v-model="form.verifiedBy1"
                  class="min-w-[160px] flex-1 border-0 border-b border-black bg-transparent px-1 text-[10pt] font-normal outline-none"
                />
              </div>
            </section>

            <!-- Section 4 -->
            <section class="border-b-2 border-black px-2.5 py-1.5">
              <h3 class="mb-[5pt] text-[10pt] font-bold leading-[1.1]">4. QMS REQUIRES UPDATING?</h3>

              <div class="flex items-center gap-2 text-[11px] font-bold whitespace-nowrap">
                <label class="relative inline-flex items-center gap-1 cursor-pointer">
                  <input type="radio" v-model="form.imsUpdate" value="NO" class="hidden" />
                  <span class="inline-block h-[10px] w-[20px] border-b border-black relative">
                    <span v-if="form.imsUpdate === 'NO'" class="absolute left-[2px] -top-[13px] text-[9pt]">✔</span>
                  </span>
                  <span>NO</span>
                </label>

                <label class="relative inline-flex items-center gap-1 cursor-pointer">
                  <input type="radio" v-model="form.imsUpdate" value="YES" class="hidden" />
                  <span class="inline-block h-[10px] w-[20px] border-b border-black relative">
                    <span v-if="form.imsUpdate === 'YES'" class="absolute left-[2px] -top-[13px] text-[9pt]">✔</span>
                  </span>
                  <span>YES,</span>
                </label>

                <span class="font-bold">DCR No:</span>
                <input
                  type="text"
                  v-model="form.dcrNo"
                  class="w-[150px] border-0 border-b border-black bg-transparent px-1 text-[10pt] font-normal outline-none"
                />

                <span class="font-bold">Verified By:</span>
                <input
                  type="text"
                  v-model="form.verifiedBy2"
                  class="min-w-[160px] flex-1 border-0 border-b border-black bg-transparent px-1 text-[10pt] font-normal outline-none"
                />
              </div>
            </section>

            <!-- Section 5 (✅ improved positioning) -->
            <section class="border-b-2 border-black px-2.5 py-1.5 pb-1">
              <!-- Title + Signature aligned like Word -->
              <div class="flex items-start justify-between mb-2">
                <h3 class="text-[10pt] font-bold leading-[1.1]">5. FOLLOW-UP</h3>

                <div class="flex items-center gap-2 mt-[2px]">
                  <span class="text-[10pt] font-bold whitespace-nowrap">Signature:</span>
                  <input
                    v-model="form.followSig"
                    class="w-[260px] border-0 border-b border-black bg-transparent px-1 text-center text-[10pt] outline-none"
                  />
                </div>
              </div>

              <!-- header labels -->
              <div class="flex flex-col text-center text-[9pt] leading-[1.2]">
                <div class="flex items-end">
                  <div class="w-[8%]"></div>
                  <div class="w-[45%]"></div>
                  <div class="w-[9%] text-[9pt]">Effective?</div>
                  <div class="w-[19%]"></div>
                  <div class="w-[19%]"></div>
                </div>
                <div class="flex items-end text-[9pt]">
                  <div class="w-[8%]">Date</div>
                  <div class="w-[45%]">Status/Comments</div>
                  <div class="w-[9%]">(Y/N)</div>
                  <div class="w-[19%]">Auditor</div>
                  <div class="w-[19%]">Representative</div>
                </div>
              </div>

              <!-- rows -->
              <div class="mt-0.5 flex flex-col gap-1.5">
                <div class="flex min-h-[18px] items-end" v-for="(row, index) in form.followUp" :key="index">
                  <div class="w-[8%] border-b border-black px-0.5 py-[1px]">
                    <input v-model="row.date" class="h-4 w-full bg-transparent text-center text-[9pt] outline-none" />
                  </div>

                  <div class="w-[45%] border-b border-black px-0.5 py-[1px]">
                    <input v-model="row.status" class="h-4 w-full bg-transparent text-center text-[9pt] outline-none" />
                  </div>

                  <div class="w-[9%] border-b border-black px-0.5 py-[1px]">
                    <input
                      v-model="row.effective"
                      class="h-4 w-full bg-transparent text-center text-[9pt] outline-none"
                    />
                  </div>

                  <div class="w-[19%] border-b border-black px-0.5 py-[1px]">
                    <input
                      v-model="row.auditor"
                      class="h-4 w-full bg-transparent text-center text-[9pt] outline-none"
                    />
                  </div>

                  <div class="w-[19%] border-b border-black px-0.5 py-[1px]">
                    <input v-model="row.rep" class="h-4 w-full bg-transparent text-center text-[9pt] outline-none" />
                  </div>
                </div>
              </div>
            </section>

            <!-- Section 6 -->
            <section class="px-2.5 py-1.5">
              <h3 class="mb-[5pt] text-[10pt] font-bold leading-[1.1]">6. CASE CLOSED</h3>

              <div class="mt-4 flex justify-between text-center">
                <div class="w-[30%] flex flex-col">
                  <input v-model="form.imrSig" class="border-0 border-b border-black bg-transparent text-center text-[10pt] outline-none" />
                  <label class="mt-0.5 text-[9pt] leading-[1.2]">Quality Management Representative</label>
                </div>

                <div class="w-[30%] flex flex-col">
                  <input type="date" v-model="form.caseClosedDate" class="border-0 border-b border-black bg-transparent text-center text-[10pt] outline-none" />
                  <label class="mt-0.5 text-[9pt] leading-[1.2]">Date</label>
                </div>

                <div class="w-[30%] flex flex-col">
                  <input v-model="form.notedBy" class="border-0 border-b border-black bg-transparent text-center text-[10pt] outline-none" />
                  <label class="mt-0.5 text-[9pt] leading-[1.2]">Noted By (Department Head)</label>
                </div>
              </div>
            </section>

            <!-- Footer -->
            <footer class="border-t-2 border-black px-2.5 py-1 text-[9px]">F-QMS-007 Rev. 1 (11-23-22)</footer>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<style scoped></style>