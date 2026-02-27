<template>
  <div class="min-h-screen bg-slate-200 p-5">
    <!-- TOP NAV (no print) -->
    <div class="no-print mx-auto mb-2 flex w-[210mm] items-center justify-between font-sans">
      <div class="flex items-center gap-2">
        <span class="rounded bg-[#C9A84C] px-2 py-[2px] text-[10px] font-bold uppercase text-white">
          {{ $page.props.auth.user.role }}
        </span>
        <span class="text-sm text-slate-800">Welcome, {{ $page.props.auth.user.name }}</span>
      </div>

      <button class="text-sm text-red-600 underline" @click="logout">Logout</button>
    </div>

    <!-- A4 (Word-like single bordered table) -->
    <div class="mx-auto w-[210mm] bg-white font-sans text-black shadow-lg print:shadow-none">
      <table class="w-full border-collapse border border-black text-[12px]">
        <!-- HEADER ROW -->
        <tr>
          <td class="w-[22mm] border-r border-black p-2 align-middle">
            
            <img :src="logoUrl" class="h-[18mm] w-[18mm] object-contain" alt="LNU Logo" />
          </td>
          <td class="p-2 align-middle">
            <div class="font-bold leading-tight">LEYTE NORMAL UNIVERSITY</div>
            <div class="font-bold leading-tight">DOCUMENT CHANGE REQUEST</div>
          </td>
        </tr>

        <!-- META BLOCK (DATE / TO/FOR / FROM + DCR No.) -->
        <tr>
          <td colspan="2" class="border-t border-black p-0">
            <table class="w-full border-collapse">
              <tr>
                <td class="w-[18mm] p-2">DATE</td>
                <td class="w-[4mm] p-2">:</td>
                <td class="p-2">
                  <input class="w-full border-0 border-b border-black bg-transparent outline-none" type="text" />
                </td>

                <td class="w-[20mm] p-2 text-right">DCR No.</td>
                <td class="w-[4mm] p-2">:</td>
                <td class="w-[45mm] p-2">
                  <input class="w-full border-0 border-b border-black bg-transparent outline-none" type="text" />
                </td>
              </tr>

              <tr>
                <td class="p-2">TO/FOR</td>
                <td class="p-2">:</td>
                <td class="p-2" colspan="4">
                  <input class="w-full border-0 border-b border-black bg-transparent outline-none" type="text" />
                </td>
              </tr>

              <tr>
                <td class="p-2">FROM</td>
                <td class="p-2">:</td>
                <td class="p-2" colspan="4">
                  <input class="w-full border-0 border-b border-black bg-transparent outline-none" type="text" />
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- CHECKBOX ROW -->
        <tr>
          <td colspan="2" class="border-t border-black p-0">
            <div class="grid grid-cols-3 items-center py-3 text-center">
              <label class="flex items-center justify-center gap-2">
                <span class="inline-block h-3 w-3 border border-black"></span>
                <span>Amend document</span>
              </label>
              <label class="flex items-center justify-center gap-2">
                <span class="inline-block h-3 w-3 border border-black"></span>
                <span>New document</span>
              </label>
              <label class="flex items-center justify-center gap-2">
                <span class="inline-block h-3 w-3 border border-black"></span>
                <span>Delete document</span>
              </label>
            </div>
          </td>
        </tr>

        <!-- SECTION 1 -->
        <tr>
          <td colspan="2" class="border-t border-black p-0">
            <div class="flex items-center gap-3 px-2 py-2">
              <div class="w-[8mm] font-bold">1.</div>
              <div class="font-bold">DETAILS OF DOCUMENT</div>
            </div>
          </td>
        </tr>

        <tr>
          <td colspan="2" class="p-0">
            <div class="px-2 pb-3">
              <div class="mt-2 flex items-center gap-2">
                <div class="w-[45mm]">Document Number</div>
                <div class="w-[4mm]">:</div>
                <div class="flex-1">
                  <input class="w-full border-0 border-b border-black bg-transparent outline-none" type="text" />
                </div>
              </div>

              <div class="mt-2 flex items-center gap-2">
                <div class="w-[45mm]">Document Title</div>
                <div class="w-[4mm]">:</div>
                <div class="flex-1">
                  <input class="w-full border-0 border-b border-black bg-transparent outline-none" type="text" />
                </div>
              </div>

              <div class="mt-2 flex items-center gap-2">
                <div class="w-[45mm]">Revision Status</div>
                <div class="w-[4mm]">:</div>
                <div class="flex-1">
                  <input class="w-full border-0 border-b border-black bg-transparent outline-none" type="text" />
                </div>
              </div>

              <div class="mt-2 text-[11px] italic">
                Note: Please attach draft copy of the document.
              </div>
            </div>
          </td>
        </tr>

        <!-- SECTION 2 -->
        <tr>
          <td colspan="2" class="border-t border-black p-0">
            <div class="flex items-center gap-3 px-2 py-2">
              <div class="w-[8mm] font-bold">2.</div>
              <div class="font-bold">CHANGE(S) REQUESTED</div>
            </div>
          </td>
        </tr>

        <tr>
          <td colspan="2" class="p-2">
            <textarea class="h-[35mm] w-full resize-none border border-black p-2 outline-none"></textarea>

            <div class="mt-3 font-bold">REASON FOR THE CHANGE</div>
            <textarea class="mt-2 h-[28mm] w-full resize-none border border-black p-2 outline-none"></textarea>

            <div class="mt-5 flex justify-between gap-10">
              <div class="w-1/2">
                <div class="h-6 border-b border-black"></div>
                <div class="mt-1 text-center text-[11px]">Requested by</div>
              </div>
              <div class="w-1/2">
                <div class="h-6 border-b border-black"></div>
                <div class="mt-1 text-center text-[11px]">Department/Unit Head</div>
              </div>
            </div>
          </td>
        </tr>

        <!-- SECTION 3 -->
        <tr>
          <td colspan="2" class="border-t border-black p-0">
            <div class="flex items-center gap-3 px-2 py-2">
              <div class="w-[8mm] font-bold">3.</div>
              <div class="font-bold">INTEGRATED MANAGEMENT REPRESENTATIVE'S COMMENTS</div>
            </div>
          </td>
        </tr>

        <tr>
          <td colspan="2" class="p-2">
            <div class="flex items-center gap-10">
              <label class="flex items-center gap-2">
                <span class="inline-block h-3 w-3 border border-black"></span>
                <span>Request Denied</span>
              </label>
              <label class="flex items-center gap-2">
                <span class="inline-block h-3 w-3 border border-black"></span>
                <span>Request Accepted</span>
              </label>
            </div>

            <div class="mt-5 w-[70mm]">
              <div class="h-6 border-b border-black"></div>
              <div class="mt-1 text-center text-[11px]">Signature/Date</div>
            </div>
          </td>
        </tr>

        <!-- SECTION 4 -->
        <tr>
          <td colspan="2" class="border-t border-black p-0">
            <div class="flex items-center gap-3 px-2 py-2">
              <div class="w-[8mm] font-bold">4.</div>
              <div class="font-bold">APPROVING AUTHORITY</div>
            </div>
          </td>
        </tr>

        <tr>
          <td colspan="2" class="p-2">
            <div class="flex items-end gap-10">
              <div class="flex-1">
                <div class="h-6 border-b border-black"></div>
                <div class="mt-1 text-center text-[11px]">Signature Over Printed Name</div>
              </div>
              <div class="w-[45mm]">
                <div class="h-6 border-b border-black"></div>
                <div class="mt-1 text-center text-[11px]">Date</div>
              </div>
            </div>
          </td>
        </tr>

        <!-- SECTION 5 -->
        <tr>
          <td colspan="2" class="border-t border-black p-0">
            <div class="flex items-center gap-3 px-2 py-2">
              <div class="w-[8mm] font-bold">5.</div>
              <div class="font-bold">DOCUMENT STATUS</div>
            </div>
          </td>
        </tr>

        <tr>
          <td colspan="2" class="p-2">
            <table class="w-full table-fixed border-collapse">
              <tr>
                <td class="w-[10mm] font-bold">No.</td>
                <td class="w-[4mm]">:</td>
                <td class="pr-3">
                  <input class="w-full border-0 border-b border-black bg-transparent outline-none" type="text" />
                </td>

                <td class="w-[16mm] font-bold">Version</td>
                <td class="w-[4mm]">:</td>
                <td class="pr-3">
                  <input class="w-full border-0 border-b border-black bg-transparent outline-none" type="text" />
                </td>

                <td class="w-[18mm] font-bold">Revision</td>
                <td class="w-[4mm]">:</td>
                <td class="pr-3">
                  <input class="w-full border-0 border-b border-black bg-transparent outline-none" type="text" />
                </td>

                <td class="w-[28mm] font-bold">Effectivity Date</td>
                <td class="w-[4mm]">:</td>
                <td>
                  <input class="w-full border-0 border-b border-black bg-transparent outline-none" type="text" />
                </td>
              </tr>

              <tr>
                <td class="pt-3 font-bold" colspan="2">Date Updated in IDS</td>
                <td class="pt-3 pr-3" colspan="4">
                  <input class="w-full border-0 border-b border-black bg-transparent outline-none" type="text" />
                </td>

                <td class="pt-3 font-bold" colspan="2">Updated by</td>
                <td class="pt-3" colspan="4">
                  <input class="w-full border-0 border-b border-black bg-transparent outline-none" type="text" />
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- FOOTER -->
        <tr>
          <td colspan="2" class="border-t border-black p-2">
            <div class="text-right text-[10px]">F-QMS-001 Rev. 1 (01-05-26)</div>
          </td>
        </tr>
      </table>
    </div>

    <!-- Buttons (no print) -->
    <div class="no-print mx-auto mt-5 flex w-[210mm] justify-center gap-3">
      <button class="rounded border border-slate-800 bg-slate-800 px-5 py-2 font-bold text-white" @click="printPage">
        Print Form
      </button>
      <button class="rounded border border-slate-800 bg-white px-5 py-2 font-bold text-slate-800" @click="goToOFI">
        OFI FORM
      </button>
      <button class="rounded border border-red-600 bg-red-600 px-5 py-2 font-bold text-white" @click="logout">
        Logout System
      </button>
    </div>
  </div>
</template>

<script setup>
import { router } from '@inertiajs/vue3'

const logoUrl = `${window.location.origin}/images/LNU_logo.png`

const printPage = () => window.print()
const goToOFI = () => router.visit('/ofi-form')
const logout = () => {
  if (confirm('Are you sure you want to log out?')) router.post('/logout')
}
</script>

<style scoped>
@media print {
  .no-print { display: none !important; }
}
@page {
  size: A4;
  margin: 0;
}
</style>