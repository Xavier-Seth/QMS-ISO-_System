<template>
  <div class="form-wrapper">
    <div class="top-nav no-print">
      <div class="user-info">
        <span class="role-badge">{{ $page.props.auth.user.role }}</span>
        <span class="username">Welcome, {{ $page.props.auth.user.name }}</span>
      </div>
      <button class="logout-link" @click="logout">Logout</button>
    </div>

    <div class="a4">
      <div class="header">
        <h3>LEYTE NORMAL UNIVERSITY</h3>
        <h2>DOCUMENT CHANGE REQUEST</h2>
      </div>

      <div class="row">
        <div>
          <label>DATE:</label>
          <input type="text">
        </div>
        <div>
          <label>DCR No.:</label>
          <input type="text">
        </div>
      </div>

      <div class="row">
        <div>
          <label>TO/FOR:</label>
          <input type="text">
        </div>
        <div>
          <label>FROM:</label>
          <input type="text">
        </div>
      </div>

      <div class="checkbox-group">
        <label><input type="checkbox"> Amend Document</label>
        <label><input type="checkbox"> New Document</label>
        <label><input type="checkbox"> Delete Document</label>
      </div>

      <div class="section">
        <div class="section-title">1. DETAILS OF DOCUMENT</div>
        <div class="row">
          <label>Document Number:</label>
          <input type="text" class="wide-input">
        </div>
        <div class="row">
          <label>Document Title:</label>
          <input type="text" class="wide-input">
        </div>
        <div class="row">
          <label>Revision Status:</label>
          <input type="text" class="wide-input">
        </div>
        <p><i>Note: Please attach draft copy of the document.</i></p>
      </div>

      <div class="section">
        <div class="section-title">2. CHANGE(S) REQUESTED</div>
        <textarea></textarea>
        <div class="section-title" style="margin-top:10px;">REASON FOR THE CHANGE</div>
        <textarea></textarea>

        <div class="row signatures">
          <div>
            <div class="signature-line"></div>
            <div>Requested by</div>
          </div>
          <div>
            <div class="signature-line"></div>
            <div>Department/Unit Head</div>
          </div>
        </div>
      </div>

      <div class="section">
        <div class="section-title">3. INTEGRATED MANAGEMENT REPRESENTATIVE'S COMMENTS</div>
        <label><input type="checkbox"> Request Denied</label>
        <label style="margin-left: 20px;"><input type="checkbox"> Request Accepted</label>
        <div style="margin-top: 20px;">
          <div class="signature-line"></div>
          <div>Signature/Date</div>
        </div>
      </div>

      <div class="section">
        <div class="section-title">4. APPROVING AUTHORITY</div>
        <div class="row signatures">
          <div>
            <div class="signature-line"></div>
            <div>Signature Over Printed Name</div>
          </div>
          <div>
            <div class="signature-line"></div>
            <div>Date</div>
          </div>
        </div>
      </div>

      <div class="section">
        <div class="section-title">5. DOCUMENT STATUS</div>
        <div class="row wrap">
          <label>No.:</label>
          <input type="text">
          <label>Version:</label>
          <input type="text">
          <label>Revision:</label>
          <input type="text">
          <label>Effectivity Date:</label>
          <input type="text">
        </div>
        <div class="row">
          <label>Date Updated in IDS:</label>
          <input type="text">
          <label>Updated by:</label>
          <input type="text">
        </div>
      </div>

      <div class="footer">
        F-QMS-001 Rev. 1 (01-05-26)
      </div>
    </div>

    <div class="button-container no-print">
      <button class="print-btn" @click="printPage">Print Form</button>
      <button class="ofi-btn" @click="goToOFI">OFI FORM</button>
      <button class="logout-btn" @click="logout">Logout System</button>
    </div>
  </div>
</template>

<script setup>
import { router } from '@inertiajs/vue3'

const printPage = () => {
  window.print();
};

const goToOFI = () => {
  router.visit('/ofi-form');
};

const logout = () => {
  if (confirm('Are you sure you want to log out?')) {
    router.post('/logout');
  }
};
</script>

<style scoped>
/* OUTER WRAPPER */
.form-wrapper {
  background: #eee;
  padding: 20px;
  min-height: 100vh;
}

/* TOP NAVIGATION */
.top-nav {
  max-width: 210mm;
  margin: 0 auto 10px auto;
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 0;
  font-family: Arial, sans-serif;
}

.user-info {
  display: flex;
  align-items: center;
  gap: 10px;
}

.role-badge {
  background: #C9A84C;
  color: white;
  padding: 2px 8px;
  border-radius: 4px;
  font-size: 10px;
  text-transform: uppercase;
  font-weight: bold;
}

.username {
  font-size: 14px;
  color: #333;
}

.logout-link {
  background: none;
  border: none;
  color: #dc2626;
  cursor: pointer;
  text-decoration: underline;
  font-size: 14px;
}

/* REAL A4 SIZE */
.a4 {
  width: 210mm;
  min-height: 297mm;
  padding: 15mm;
  margin: auto;
  background: white;
  box-sizing: border-box;
  font-family: Arial, sans-serif;
  color: black;
  box-shadow: 0 0 10px rgba(0,0,0,0.1);
}

.header { text-align: center; margin-bottom: 15px; }
.header h3 { margin: 0; font-size: 14px; }
.header h2 { margin: 3px 0 10px 0; font-size: 16px; border-bottom: 2px solid black; display: inline-block; padding: 0 10px; }

.row { display: flex; justify-content: space-between; margin-bottom: 8px; gap: 10px; flex-wrap: wrap; }
label { font-weight: bold; font-size: 12px; }

input[type="text"] {
  border: none;
  border-bottom: 1px solid black;
  outline: none;
  width: 120px;
  font-size: 12px;
}

.wide-input { width: 70% !important; }

textarea {
  width: 100%;
  height: 60px;
  resize: none;
  border: 1px solid black;
  margin-bottom: 8px;
  font-size: 12px;
}

.checkbox-group { display: flex; justify-content: space-around; margin: 10px 0; font-size: 12px; }

.section {
  border: 1px solid black;
  padding: 8px;
  margin-top: 10px;
  page-break-inside: avoid;
}

.section-title { font-weight: bold; margin-bottom: 6px; font-size: 12px; background: #f0f0f0; padding: 2px 5px; }

.signature-line { border-bottom: 1px solid black; width: 160px; height: 18px; margin-top: 20px; }
.footer { margin-top: 10px; font-size: 10px; text-align: right; }

/* BUTTONS */
.button-container {
  display: flex;
  justify-content: center;
  gap: 15px;
  margin: 20px auto;
  max-width: 210mm;
}

.print-btn, .ofi-btn, .logout-btn {
  padding: 10px 20px;
  cursor: pointer;
  font-weight: bold;
  border-radius: 4px;
  transition: 0.2s;
  border: 1px solid #333;
}

.print-btn { background: #333; color: white; }
.ofi-btn { background: white; color: #333; }
.logout-btn { background: #dc2626; color: white; border-color: #dc2626; }

.logout-btn:hover { background: #b91c1c; }

/* PRINT LOGIC */
@media print {
  .no-print { display: none !important; }
  .form-wrapper { padding: 0; background: white; }
  .a4 { box-shadow: none; margin: 0; width: 100%; }
}

@page {
  size: A4;
  margin: 0;
}
</style>