<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { Link } from '@inertiajs/vue3'

const props = defineProps({
  documentType: Object,
  documents: Array,
})

function formatDate(date) {
  if (!date) return '—'
  return new Date(date).toLocaleString()
}

function statusClass(status) {
  if (status === 'Active')
    return 'bg-emerald-50 text-emerald-700 ring-emerald-200'
  return 'bg-rose-50 text-rose-700 ring-rose-200'
}
</script>

<template>
  <AdminLayout>
    <div class="p-6 space-y-6">

      <!-- Header -->
      <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="px-6 py-6 bg-gradient-to-r from-slate-900 to-slate-800">
          <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <div class="flex items-center gap-3">
                <span class="text-xs font-semibold bg-white/10 text-white px-3 py-1 rounded-md">
                  {{ documentType.code }}
                </span>
                <span class="text-xs rounded-full px-2 py-1 ring-1 bg-indigo-500/20 text-indigo-200 ring-indigo-400/30">
                  {{ documentType.file_type }}
                </span>
              </div>

              <h1 class="text-2xl font-semibold text-white mt-3">
                {{ documentType.name }}
              </h1>

              <p class="text-sm text-slate-300 mt-1">
                View all uploaded versions under this document code.
              </p>
            </div>

            <div class="flex items-center gap-2">
              <Link
                href="/documents"
                class="px-3 py-2 rounded-lg bg-white/10 hover:bg-white/15 text-white text-sm border border-white/10"
              >
                Back
              </Link>

              <Link
                :href="`/documents/${documentType.id}/upload`"
                class="px-4 py-2 rounded-lg bg-indigo-500 hover:bg-indigo-400 text-white text-sm font-medium"
              >
                Upload New Revision
              </Link>
            </div>
          </div>
        </div>

        <!-- Stats -->
        <div class="px-6 py-4 border-t border-slate-200 flex flex-wrap gap-6 text-sm text-slate-600">
          <div>
            Total Versions:
            <span class="font-semibold text-slate-900">
              {{ documents.length }}
            </span>
          </div>

          <div>
            Active:
            <span class="font-semibold text-emerald-600">
              {{ documents.filter(d => d.status === 'Active').length }}
            </span>
          </div>

          <div>
            Obsolete:
            <span class="font-semibold text-rose-600">
              {{ documents.filter(d => d.status === 'Obsolete').length }}
            </span>
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div
        v-if="!documents.length"
        class="bg-white rounded-2xl border border-slate-200 p-10 text-center"
      >
        <div class="text-slate-900 font-semibold text-lg">
          No uploads yet
        </div>
        <div class="text-sm text-slate-600 mt-2">
          Start by uploading the first version of this document.
        </div>

        <Link
          :href="`/documents/${documentType.id}/upload`"
          class="inline-block mt-4 px-4 py-2 rounded-xl bg-slate-900 text-white hover:bg-slate-800 text-sm"
        >
          Upload Document
        </Link>
      </div>

      <!-- Version Table -->
      <div
        v-if="documents.length"
        class="bg-white rounded-2xl border border-slate-200 overflow-hidden"
      >
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead class="bg-slate-50 border-b border-slate-200">
              <tr class="text-left">
                <th class="px-5 py-3 font-semibold text-slate-700">Revision</th>
                <th class="px-5 py-3 font-semibold text-slate-700">File Name</th>
                <th class="px-5 py-3 font-semibold text-slate-700">Status</th>
                <th class="px-5 py-3 font-semibold text-slate-700">Uploaded By</th>
                <th class="px-5 py-3 font-semibold text-slate-700">Date</th>
                <th class="px-5 py-3 font-semibold text-slate-700 text-right">Actions</th>
              </tr>
            </thead>

            <tbody>
              <tr
                v-for="doc in documents"
                :key="doc.id"
                class="border-b border-slate-100 hover:bg-slate-50 transition"
              >
                <td class="px-5 py-4 font-medium text-slate-900">
                  {{ doc.revision || '—' }}
                </td>

                <td class="px-5 py-4 text-slate-800">
                  {{ doc.file_name }}
                </td>

                <td class="px-5 py-4">
                  <span
                    class="text-xs rounded-full px-2 py-1 ring-1"
                    :class="statusClass(doc.status)"
                  >
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
            </tbody>
          </table>
        </div>

        <!-- ISO Reminder Footer -->
        <div class="px-6 py-3 bg-slate-50 text-xs text-slate-500">
          ISO Document Control: Only one version should remain Active. 
          Uploading a new revision should mark previous versions as Obsolete.
        </div>
      </div>

    </div>
  </AdminLayout>
</template>