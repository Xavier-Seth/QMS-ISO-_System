<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { reactive, onMounted, ref } from "vue";
import axios from "axios";
import logo from '@/images/LNU_logo.png';

const suggestionBox = ref(null);
const isGenerating  = ref(false);
const errorMessage  = ref("");

const form = reactive({
  date: "",
  refNo: "",
  to: "",
  ofiNo: "",
  from: "",
  isoClause: "",
  sourceIqa: false,
  sourceFeedback: false,
  sourceSurvey: false,
  sourceSystem: false,
  sourceOthersCheck: false,
  sourceOthersText: "",
  suggestion: "",
  deptRepSig1: "",
  requestedBySig: "",
  agreedDate: "",
  beneficialImpact: "",
  associatedRisks: "",
  action: "",
  deptRepDate2: "",
  deptHeadDate2: "",
  assessmentUpdate: null,
  dateUpdated: "",
  verifiedBy1: "",
  imsUpdate: null,
  dcrNo: "",
  verifiedBy2: "",
  followUp: [
    { date: "", status: "", effective: "", auditor: "", rep: "" },
    { date: "", status: "", effective: "", auditor: "", rep: "" },
    { date: "", status: "", effective: "", auditor: "", rep: "" },
    { date: "", status: "", effective: "", auditor: "", rep: "" },
  ],
  imrSig: "",
  caseClosedDate: "",
  notedBy: ""
});

onMounted(() => {
  if (suggestionBox.value && form.suggestion) {
    suggestionBox.value.innerText = form.suggestion;
  }
});

async function generateDocx() {
  isGenerating.value = true;
  errorMessage.value = "";
  try {
    const response = await axios.post("/ofi/generate", form, { responseType: "blob" });
    const url  = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement("a");
    link.href  = url;
    link.download = `OFI_${form.ofiNo || "form"}.docx`;
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);
  } catch (err) {
    console.error("Error generating DOCX:", err);
    errorMessage.value = "Failed to generate document. Please try again.";
  } finally {
    isGenerating.value = false;
  }
}
</script>

<template>
  <AdminLayout>
    <div class="page-container">

      <!-- Page Header -->
      <div class="page-header">
        <div class="page-header-left">
          <h1 class="page-title">Create OFI Form</h1>
          <p class="page-subtitle">Opportunities for Improvement · Leyte Normal University</p>
        </div>
        <div class="page-header-right">
          <button class="btn-cancel" @click="$inertia.visit('/dashboard')">Cancel</button>
          <button class="btn-save" @click="generateDocx" :disabled="isGenerating">
            <svg v-if="!isGenerating" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
              <polyline points="7 10 12 15 17 10"/>
              <line x1="12" y1="15" x2="12" y2="3"/>
            </svg>
            <svg v-else class="spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/>
            </svg>
            {{ isGenerating ? 'Generating...' : 'Save & Download DOCX' }}
          </button>
          <p v-if="errorMessage" class="error-msg">{{ errorMessage }}</p>
        </div>
      </div>

      <!-- Form Card -->
      <div class="form-card">
        <div class="form-document">

          <!-- Header -->
          <header class="form-header">
            <div class="logo-container">
              <img :src="logo" alt="LNU Logo" />
            </div>
            <div class="header-text">
              <h2>LEYTE NORMAL UNIVERSITY</h2>
              <h1>OPPORTUNITIES FOR IMPROVEMENT FORM (OFI)</h1>
            </div>
          </header>

          <!-- Metadata -->
          <section class="metadata-section">
            <div class="meta-grid">
              <div class="input-group left-col">
                <div class="label-box"><label>DATE</label></div><span>:</span>
                <input type="date" v-model="form.date" />
              </div>
              <div class="input-group right-col">
                <div class="label-box"><label>REF. NO</label></div><span>:</span>
                <input type="text" v-model="form.refNo" />
              </div>
              <div class="input-group left-col">
                <div class="label-box"><label>TO</label></div><span>:</span>
                <div class="stacked-input">
                  <input type="text" v-model="form.to" />
                  <small>Area Concerned</small>
                </div>
              </div>
              <div class="input-group right-col">
                <div class="label-box"><label>OFI NO.</label></div><span>:</span>
                <input type="text" v-model="form.ofiNo" />
              </div>
              <div class="input-group left-col">
                <div class="label-box"><label>FROM</label></div><span>:</span>
                <input type="text" v-model="form.from" />
              </div>
              <div class="input-group right-col">
                <div class="label-box stacked-label">
                  <label>ISO Clause</label>
                  <small>(if applicable)</small>
                </div><span>:</span>
                <input type="text" v-model="form.isoClause" />
              </div>
            </div>
            <div class="source-row">
              <span class="source-label">SOURCE: </span>
              <label class="custom-checkbox">IQA <input type="checkbox" v-model="form.sourceIqa" /><span class="box"></span></label>
              <label class="custom-checkbox">Employee Feedback <input type="checkbox" v-model="form.sourceFeedback" /><span class="box"></span></label>
              <label class="custom-checkbox">Survey <input type="checkbox" v-model="form.sourceSurvey" /><span class="box"></span></label>
              <label class="custom-checkbox">System Review <input type="checkbox" v-model="form.sourceSystem" /><span class="box"></span></label>
              <label class="custom-checkbox others-label">
                Others <input type="checkbox" v-model="form.sourceOthersCheck" /><span class="box"></span>
                <input type="text" class="inline-underscore" v-model="form.sourceOthersText" />
              </label>
            </div>
          </section>

          <!-- Section 1 -->
          <section class="numbered-section flex-section suggestion-section">
            <h3>1. SUGGESTION/RECOMMENDATION</h3>
            <div
              ref="suggestionBox"
              class="fixed-box shadow-text"
              contenteditable="true"
              @input="form.suggestion = $event.target.innerText"
            ></div>
            <div class="signature-row trio">
              <div class="sig-box">
                <input type="text" v-model="form.deptRepSig1" />
                <label>Department Representative<br>(Signature over Printed Name)</label>
              </div>
              <div class="sig-box">
                <input type="text" v-model="form.requestedBySig" />
                <label>Requested By<br>(Signature over Printed Name)</label>
              </div>
              <div class="sig-box">
                <input type="text" v-model="form.agreedDate" />
                <label>Agreed Date<br>1st Follow-up</label>
              </div>
            </div>
          </section>

          <!-- Section 2 -->
          <section class="numbered-section flex-section" style="min-height: 250px;">
            <h3>2. ANALYSIS (To be filled out by the Dept. Representative)</h3>
            <div class="analysis-block">
              <u>BENEFICIAL IMPACT</u>
              <textarea v-model="form.beneficialImpact" class="analysis-box" rows="2"></textarea>
            </div>
            <div class="analysis-block">
              <u>ASSOCIATED RISKS</u>
              <textarea v-model="form.associatedRisks" class="analysis-box" rows="2"></textarea>
            </div>
            <div class="analysis-block flex-grow-block">
              <u>ACTION</u>
              <textarea v-model="form.action" class="analysis-box grow"></textarea>
            </div>
            <div class="signature-row duo">
              <div class="sig-box">
                <input type="text" v-model="form.deptRepDate2" />
                <label>Department Representative/Date</label>
              </div>
              <div class="sig-box">
                <input type="text" v-model="form.deptHeadDate2" />
                <label>Department Head/Date</label>
              </div>
            </div>
          </section>

          <!-- Section 3 -->
          <section class="numbered-section">
            <h3>3. RISK/OPPORTUNITY ASSESSMENT REQUIRES UPDATING?</h3>
            <div class="assessment-line">
              <label class="radio-inline">
                <input type="radio" v-model="form.assessmentUpdate" value="NO" />
                <span class="radio-line"></span>NO
              </label>
              <label class="radio-inline">
                <input type="radio" v-model="form.assessmentUpdate" value="YES" />
                <span class="radio-line"></span>YES,
              </label>
              <span class="inline-label">Date Updated:</span>
              <input type="text" v-model="form.dateUpdated" class="inline-input" />
              <span class="inline-label">Verified By:</span>
              <input type="text" v-model="form.verifiedBy1" class="inline-input flex-input" />
            </div>
          </section>

          <!-- Section 4 -->
          <section class="numbered-section">
            <h3>4. QMS REQUIRES UPDATING?</h3>
            <div class="assessment-line">
              <label class="radio-inline">
                <input type="radio" v-model="form.imsUpdate" value="NO" />
                <span class="radio-line"></span>NO
              </label>
              <label class="radio-inline">
                <input type="radio" v-model="form.imsUpdate" value="YES" />
                <span class="radio-line"></span>YES,
              </label>
              <span class="inline-label">DCR No:</span>
              <input type="text" v-model="form.dcrNo" class="inline-input" />
              <span class="inline-label">Verified By:</span>
              <input type="text" v-model="form.verifiedBy2" class="inline-input flex-input" />
            </div>
          </section>

          <!-- Section 5 -->
          <section class="numbered-section followup-section">
            <h3>5. FOLLOW-UP</h3>
            <div class="fu-header">
              <div class="fu-top-labels">
                <div class="fu-top-date"></div>
                <div class="fu-top-status"></div>
                <div class="fu-top-effective">Effective?</div>
                <div class="fu-top-auditor"></div>
                <div class="fu-top-rep"></div>
              </div>
              <div class="fu-bottom-labels">
                <div class="fu-col-date">Date</div>
                <div class="fu-col-status">Status/Comments</div>
                <div class="fu-col-effective">(Y/N)</div>
                <div class="fu-col-auditor">Auditor</div>
                <div class="fu-col-rep">Representative</div>
              </div>
            </div>
            <div class="fu-rows">
              <div class="fu-row" v-for="(row, index) in form.followUp" :key="index">
                <div class="fu-cell fu-col-date"><input v-model="row.date" /></div>
                <div class="fu-cell fu-col-status"><input v-model="row.status" /></div>
                <div class="fu-cell fu-col-effective"><input v-model="row.effective" /></div>
                <div class="fu-cell fu-col-auditor"><input v-model="row.auditor" /></div>
                <div class="fu-cell fu-col-rep"><input v-model="row.rep" /></div>
              </div>
            </div>
          </section>

          <!-- Section 6 -->
          <section class="numbered-section">
            <h3>6. CASE CLOSED</h3>
            <div class="signature-row trio" style="margin-top: 15px;">
              <div class="sig-box">
                <input v-model="form.imrSig" />
                <label>Quality Management Representative</label>
              </div>
              <div class="sig-box">
                <input type="date" v-model="form.caseClosedDate" />
                <label>Date</label>
              </div>
              <div class="sig-box">
                <input v-model="form.notedBy" />
                <label>Noted By (Department Head)</label>
              </div>
            </div>
          </section>

          <footer class="form-footer">
            F-QMS-007 Rev. 1 (11-23-22)
          </footer>

        </div>
      </div>

    </div>
  </AdminLayout>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap');

/* ── Page Shell ── */
.page-container {
  padding: 32px 40px;
  background: #f1f5f9;
  height: 100vh; /* Changed from min-height */
  box-sizing: border-box; /* Added */
  overflow: hidden; /* Added */
  font-family: 'DM Sans', sans-serif;
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.page-header {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 12px;
  flex-shrink: 0; /* Added */
}

.page-title {
  font-size: 24px;
  font-weight: 700;
  color: #0f172a;
  margin: 0 0 4px;
  letter-spacing: -0.4px;
}

.page-subtitle {
  font-size: 13px;
  color: #94a3b8;
  margin: 0;
}

.page-header-right {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.btn-cancel {
  padding: 9px 20px;
  font-size: 13.5px;
  font-weight: 500;
  border: 1px solid #e2e8f0;
  background: #fff;
  color: #64748b;
  border-radius: 10px;
  cursor: pointer;
  font-family: 'DM Sans', sans-serif;
  transition: background 0.15s, color 0.15s;
}
.btn-cancel:hover { background: #f8fafc; color: #334155; }

.btn-save {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 9px 20px;
  font-size: 13.5px;
  font-weight: 600;
  background: #1e293b;
  color: #fff;
  border: none;
  border-radius: 10px;
  cursor: pointer;
  font-family: 'DM Sans', sans-serif;
  transition: background 0.15s;
}
.btn-save:hover { background: #0f172a; }
.btn-save:disabled { background: #94a3b8; cursor: not-allowed; }
.btn-save svg { width: 16px; height: 16px; }

@keyframes spin { to { transform: rotate(360deg); } }
.spin { animation: spin 0.8s linear infinite; }

.error-msg { color: #ef4444; font-size: 12px; margin: 0; }

/* ── Form Card ── */
/* ── Form Card ── */
.form-card {
  background: #fff;
  border-radius: 16px;
  border: 1px solid #e2e8f0;
  box-shadow: 0 2px 12px rgba(0,0,0,0.06);
  padding: 32px;
  display: flex;
  justify-content: center;
  align-items: flex-start; /* <-- ADD THIS LINE */
  flex: 1;
  overflow-y: auto; 
}

/* ── Document (identical to original but self-contained) ── */
.form-document {
  width: 210mm;
  background: #fff;
  border: 2px solid #000;
  box-sizing: border-box;
  display: flex;
  flex-direction: column;
  font-family: Arial, Helvetica, sans-serif;
  color: #000;
}

h1, h2, h3 { margin: 0; line-height: 1.1; font-family: Arial, sans-serif; }
h3 { font-size: 10pt; font-weight: bold; margin-bottom: 5pt; }

input, textarea {
  font-family: Arial, sans-serif;
  font-size: 10pt;
  border: none;
  background: transparent;
  outline: none;
  line-height: 1.25;
  color: #000;
}
textarea::-webkit-scrollbar { display: none; }
textarea { -ms-overflow-style: none; scrollbar-width: none; }
.shadow-text { white-space: pre-wrap; word-wrap: break-word; }

.fixed-box {
  font-family: Arial, sans-serif;
  font-size: 10pt;
  line-height: 1.3;
  text-indent: 10pt;
  padding: 5px 0;
  min-height: 85px;
  width: 100%;
  flex: none;
  resize: none;
  box-sizing: border-box;
  white-space: pre-wrap;
  word-wrap: break-word;
  cursor: text;
  outline: none;
}

.signature-row {
  display: flex;
  justify-content: space-between;
  margin-top: auto;
  padding-top: 6px;
  text-align: center;
  flex-shrink: 0;
}

section { border-bottom: 2px solid black; padding: 6px 10px; }
section:last-of-type { border-bottom: none; }

.suggestion-section { min-height: 130pt; display: flex; flex-direction: column; }
.analysis-box { width: 100%; padding: 2px 0; overflow: hidden; resize: none; box-sizing: border-box; }
.flex-section { display: flex; flex-direction: column; }
.flex-grow-block { flex-grow: 1; display: flex; flex-direction: column; }
.grow { flex-grow: 1; min-height: 40px; }

.form-header { display: flex; align-items: center; border-bottom: 2px solid black; padding: 2px 8px; }
.logo-container { width: 0.57in; height: 0.57in; margin-right: 8px; display: flex; }
.logo-container img { width: 100%; height: auto; }
.header-text { display: flex; flex-direction: column; }
.header-text h2 { margin-top: 19px; font-size: 12pt; font-weight: bold; margin-bottom: 0px; }
.header-text h1 { font-size: 12pt; font-weight: bold; }

.metadata-section { padding: 10px 12px; }
.meta-grid { display: grid; grid-template-columns: 55% 45%; row-gap: 10px; }
.input-group { display: flex; align-items: flex-start; }
.left-col .label-box { width: 50px; }
.right-col .label-box { width: 80px; }
.left-col { padding-right: 40px; }
.label-box label { font-size: 10pt; font-weight: normal; }
.input-group span { margin-right: 12px; font-size: 10pt; }
.input-group input { border-bottom: 1px solid black; padding: 0 2px; font-size: 10pt; height: 14pt; }
.left-col input { flex: 1; }
.right-col input { width: 150px; flex: none; border-bottom: 1px solid black; }
.stacked-input { flex: 1; display: flex; flex-direction: column; align-items: center; }
.stacked-input input { width: 100%; border-bottom: 1px solid black; }
.stacked-input small { font-size: 9pt; margin-top: 3px; }
.stacked-label { display: flex; flex-direction: column; }
.stacked-label small { font-size: 9pt; margin-top: 3px; }

.source-row { margin-top: 6px; font-size: 10pt; display: flex; align-items: center; gap: 12px; width: 100%; box-sizing: border-box; }
.source-label { font-size: 10pt; font-weight: bold; margin-right: 5px; }
.custom-checkbox { display: flex; align-items: center; position: relative; cursor: pointer; font-size: 10pt; white-space: nowrap; gap: 4px; }
.custom-checkbox input[type="checkbox"] { position: absolute; opacity: 0; cursor: pointer; height: 0; width: 0; }
.box { display: inline-flex; align-items: center; justify-content: center; width: 10pt; height: 10pt; }
.box::before { content: "["; margin-right: auto; }
.box::after  { content: "]"; margin-left: auto; }
.custom-checkbox input:checked ~ .box::before { content: "[✔"; white-space: pre; }
.others-label { display: flex; align-items: center; flex: 1; min-width: 100px; }
.inline-underscore { flex: 1; border-bottom: 1px solid black; margin-left: 5px; height: 14pt; font-size: 10pt; width: 100%; }

.analysis-block { margin-bottom: 8px; }
.analysis-block u { font-size: 11px; font-weight: bold; }

.trio .sig-box { width: 30%; display: flex; flex-direction: column; }
.duo .sig-box  { width: 45%; }
.sig-box input { width: 100%; border-bottom: 1px solid black; margin-bottom: 2px; text-align: center; font-size: 10pt; }
.sig-box label { font-size: 9pt; line-height: 1.2; display: block; }

.assessment-line { display: flex; align-items: center; gap: 8px; font-size: 11px; font-weight: bold; flex-wrap: nowrap; }
.radio-inline { display: flex; align-items: center; gap: 4px; position: relative; cursor: pointer; white-space: nowrap; }
.radio-inline input[type="radio"] { position: absolute; opacity: 0; width: 0; height: 0; }
.radio-line { width: 20px; height: 10px; border-bottom: 1px solid black; display: inline-block; position: relative; }
.radio-inline input[type="radio"]:checked + .radio-line::after { content: "✔"; font-size: 9pt; position: absolute; left: 2px; top: -13px; color: #000; }
.inline-label { white-space: nowrap; font-size: 11px; }
.inline-input { border: none; border-bottom: 1px solid black; width: 150px; padding: 1px 2px; font-weight: normal; flex-shrink: 0; }
.flex-input { flex: 1; width: auto; }

/* Section 5 Follow-up */
.followup-section { padding-bottom: 4px; }
.fu-col-date      { width: 8%;  flex-shrink: 0; }
.fu-col-status    { width: 45%; flex-shrink: 0; }
.fu-col-effective { width: 9%;  flex-shrink: 0; }
.fu-col-auditor   { width: 19%; flex-shrink: 0; }
.fu-col-rep       { width: 19%; flex-shrink: 0; }

.fu-header { display: flex; flex-direction: column; font-size: 9pt; text-align: center; line-height: 1.2; }
.fu-top-labels { display: flex; align-items: flex-end; }
.fu-top-date      { width: 8%; }
.fu-top-status    { width: 45%; }
.fu-top-effective { width: 9%;  font-size: 9pt; }
.fu-top-auditor   { width: 19%; font-size: 9pt; }
.fu-top-rep       { width: 19%; }
.fu-bottom-labels { display: flex; align-items: flex-end; font-size: 9pt; }

.fu-rows { display: flex; flex-direction: column; gap: 6px; margin-top: 2px; }
.fu-row  { display: flex; align-items: flex-end; min-height: 18px; }
.fu-cell { display: flex; align-items: center; border-bottom: 1px solid black; padding: 1px 2px; box-sizing: border-box; }
.fu-cell input { width: 100%; text-align: center; font-size: 9pt; border: none; background: transparent; outline: none; padding: 0; font-family: Arial, sans-serif; color: #000; height: 16px; }

.form-footer { padding: 4px 10px; font-size: 9px; border-top: 2px solid black; }

/* ── Print ── */
@media print {
  @page { size: A4; margin: 0; }
  .page-container { padding: 0 !important; background: white !important; }
  .page-header, .form-card { box-shadow: none !important; border: none !important; border-radius: 0 !important; padding: 0 !important; }
  .btn-cancel, .btn-save, .page-header { display: none !important; }
  .form-document { width: 210mm; border: 2px solid #000; }
  * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
}
</style>