<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { computed, ref, watch } from 'vue'
import { router, Link } from '@inertiajs/vue3'

const props = defineProps({
  documentTypes: {
    type: Array,
    default: () => [],
  },
  seriesOptions: {
    type: Array,
    default: () => [],
  },
  filters: {
    type: Object,
    default: () => ({
      q: '',
      series: 'All',
      sort: 'code_asc',
      view: 'group',
    }),
  },
})

const q = ref(props.filters.q ?? '')
const series = ref(props.filters.series ?? 'All') // ✅ NEW
const sort = ref(props.filters.sort ?? 'code_asc')
const view = ref(props.filters.view ?? 'group')
const isFiltering = ref(false)

let debounceTimer = null
function applyFilters() {
  isFiltering.value = true
  router.get(
    '/documents',
    {
      q: q.value || undefined,
      series: series.value !== 'All' ? series.value : undefined, // ✅ NEW
      sort: sort.value || undefined,
      view: view.value || undefined,
    },
    {
      preserveState: true,
      replace: true,
      preserveScroll: true,
      onFinish: () => (isFiltering.value = false),
    }
  )
}

watch([q, series, sort, view], () => {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(applyFilters, 220)
})

function resetFilters() {
  q.value = ''
  series.value = 'All'
  sort.value = 'code_asc'
  view.value = 'group'
  applyFilters()
}

// ✅ No more client-side filtering for series/uploads.
// Controller already filtered and returned `documentTypes` based on query params.
const normalized = computed(() => {
  let rows = [...props.documentTypes]

  // Still keep quick local search filtering for instant UI feel (optional)
  const needle = (q.value || '').trim().toLowerCase()
  if (needle) {
    rows = rows.filter((r) => `${r.code} ${r.name}`.toLowerCase().includes(needle))
  }

  const byCode = (a, b) => (a.code || '').localeCompare(b.code || '')
  const byName = (a, b) => (a.name || '').localeCompare(b.name || '')
  const byUploads = (a, b) => (b.documents_count || 0) - (a.documents_count || 0)
  const byLatest = (a, b) => {
    const da = a.latest_upload_at ? new Date(a.latest_upload_at).getTime() : 0
    const db = b.latest_upload_at ? new Date(b.latest_upload_at).getTime() : 0
    return db - da
  }

  if (sort.value === 'code_asc') rows.sort(byCode)
  if (sort.value === 'name_asc') rows.sort(byName)
  if (sort.value === 'uploads_desc') rows.sort(byUploads)
  if (sort.value === 'latest_desc') rows.sort(byLatest)

  return rows
})

/**
 * ✅ Grouping is now dynamic based on selected series.
 * - R-QMS keeps your original two groups
 * - F-QMS / IPCR will go under "All Items" unless you want specific grouping rules
 */
const grouped = computed(() => {
  // If series is R-QMS or All, keep the original ranges
  if (series.value === 'R-QMS' || series.value === 'All') {
    const groups = { 'R-QMS-001 to R-QMS-061': [], 'R-QMS-100 to R-QMS-112': [], Other: [] }
    for (const r of normalized.value) {
      const m = (r.code || '').match(/R-QMS-(\d+)/i)
      const n = m ? parseInt(m[1], 10) : null
      if (n !== null && n >= 1 && n <= 61) groups['R-QMS-001 to R-QMS-061'].push(r)
      else if (n !== null && n >= 100 && n <= 112) groups['R-QMS-100 to R-QMS-112'].push(r)
      else groups.Other.push(r)
    }
    return groups
  }

  // For F-QMS / IPCR: show a single group
  return { 'All Items': normalized.value }
})

function statusClass(count) {
  if (!count) return 'bg-rose-50 text-rose-700 ring-rose-200'
  return 'bg-emerald-50 text-emerald-700 ring-emerald-200'
}

function formatDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString()
}
</script>

<template>
  <AdminLayout>
    <div class="p-6 space-y-6">

      <!-- Top header -->
      <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="px-4 py-2 bg-gradient-to-r from-slate-900 to-slate-800">
          <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <h1 class="text-base sm:text-x1 font-semibold text-white">Documents</h1>
              <p class="text-sm text-slate-300 mt-1">
                Find documents by code (R-QMS-###) or name. Open a code to view all uploads under it.
              </p>
            </div>

            <div class="flex items-center gap-2">
              <!-- optional reset button if you want it back -->
              <!--
              <button
                type="button"
                @click="resetFilters"
                class="px-3 py-2 rounded-lg bg-white/10 hover:bg-white/15 text-white text-sm border border-white/10"
              >
                Reset
              </button>
              -->
            </div>
          </div>
        </div>

        <!-- Controls -->
        <div class="px-4 py-2">
          <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
            <!-- Search -->
            <div class="md:col-span-6">
              <label class="text-xs font-medium text-slate-600">Search</label>
              <div class="mt-1 relative">
                <input
                  v-model="q"
                  type="text"
                  placeholder="Search: R-QMS-001, Filing Chart, OFI, DCR..."
                  class="w-full rounded-xl border border-slate-200 px-4 py-1.5 pr-10 focus:outline-none focus:ring-2 focus:ring-slate-300"
                />
                <div class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400">
                  <svg v-if="!isFiltering" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                       viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M21 21l-4.35-4.35m0 0A7.5 7.5 0 104.5 4.5a7.5 7.5 0 0012.15 12.15z" />
                  </svg>
                  <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 animate-spin" fill="none"
                       viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 4v2m0 12v2m8-8h-2M6 12H4m14.364-6.364l-1.414 1.414M7.05 16.95l-1.414 1.414m0-12.728L7.05 7.05m10.9 9.9l1.414 1.414"/>
                  </svg>
                </div>
              </div>
            </div>

            <!-- (EMPTY SLOT where old File Type was) -->
            <div class="md:col-span-2">
              <!-- optional: leave blank to keep spacing like your screenshot -->
            </div>

            <!-- ✅ Document Series (MOVED into Uploaded position) -->
            <div class="md:col-span-2">
              <label class="text-xs font-medium text-slate-600">File Type</label>
              <select
                v-model="series"
                class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-1.5 bg-white focus:outline-none focus:ring-2 focus:ring-slate-300"
              >
                <option value="All">All</option>
                <option
                  v-for="s in seriesOptions"
                  :key="s.id"
                  :value="s.code_prefix"
                >
                  {{ s.code_prefix }}
                </option>
              </select>
            </div>

            <!-- Sort -->
            <div class="md:col-span-2">
              <label class="text-xs font-medium text-slate-600">Sort</label>
              <select
                v-model="sort"
                class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-1.5 bg-white focus:outline-none focus:ring-2 focus:ring-slate-300"
              >
                <option value="code_asc">Code (A–Z)</option>
                <option value="name_asc">Name (A–Z)</option>
                <option value="uploads_desc">Most uploads</option>
                <option value="latest_desc">Latest updated</option>
              </select>
            </div>
          </div>

          <!-- View toggle -->
          <div class="mt-4 flex items-center justify-between">
            <div class="text-xs text-slate-500">
              Showing <span class="font-semibold text-slate-700">{{ normalized.length }}</span> document type(s)
            </div>

            <div class="inline-flex rounded-xl border border-slate-200 bg-slate-50 p-1">
              <button
                type="button"
                @click="view = 'group'"
                class="px-3 py-1.5 rounded-lg text-sm"
                :class="view === 'group' ? 'bg-white shadow-sm text-slate-900' : 'text-slate-600 hover:text-slate-900'"
              >
                Browse
              </button>
              <button
                type="button"
                @click="view = 'table'"
                class="px-3 py-1.5 rounded-lg text-sm"
                :class="view === 'table' ? 'bg-white shadow-sm text-slate-900' : 'text-slate-600 hover:text-slate-900'"
              >
                Table
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Empty -->
      <div v-if="!normalized.length" class="bg-white rounded-2xl border border-slate-200 p-10 text-center">
        <div class="text-slate-900 font-semibold">No results</div>
        <div class="text-sm text-slate-600 mt-1">Try a different keyword or reset filters.</div>
      </div>

      <!-- Browse / Group View -->
      <div v-if="view === 'group' && normalized.length" class="space-y-6">
        <div v-for="(items, groupName) in grouped" :key="groupName" v-show="items.length">
          <div class="flex items-center justify-between mb-3">
            <h2 class="text-sm font-semibold text-slate-800">{{ groupName }}</h2>
            <span class="text-xs text-slate-500">{{ items.length }} items</span>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
            <div
              v-for="row in items"
              :key="row.id"
              class="bg-white rounded-2xl border border-slate-200 hover:border-slate-300 hover:shadow-sm transition p-4"
            >
              <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                  <div class="flex items-center gap-2">
                    <span class="text-xs font-semibold text-slate-900 bg-slate-100 rounded-md px-2 py-1">
                      {{ row.code }}
                    </span>
                  </div>

                  <div class="mt-2 font-semibold text-slate-900 truncate">
                    {{ row.name }}
                  </div>

                  <div class="mt-1 text-xs text-slate-500">
                    Latest: <span class="text-slate-700">{{ formatDate(row.latest_upload_at) }}</span>
                  </div>
                </div>

                <span class="text-xs rounded-full px-2 py-1 ring-1 whitespace-nowrap" :class="statusClass(row.documents_count)">
                  {{ row.documents_count || 0 }} upload(s)
                </span>
              </div>

              <div class="mt-4 flex items-center gap-2">
                <Link
                  :href="`/documents/${row.id}`"
                  class="flex-1 text-center px-3 py-2 rounded-xl bg-slate-900 text-white hover:bg-slate-800 text-sm"
                >
                  Open
                </Link>

                <Link
                  :href="`/documents/${row.id}`"
                  class="px-3 py-2 rounded-xl border border-slate-200 text-slate-700 bg-white hover:bg-slate-50 text-sm"
                  title="Upload under this code"
                >
                  Upload
                </Link>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Table View -->
      <div v-if="view === 'table' && normalized.length" class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead class="bg-slate-50 border-b border-slate-200">
              <tr class="text-left">
                <th class="px-4 py-3 font-semibold text-slate-700">Code</th>
                <th class="px-4 py-3 font-semibold text-slate-700">File Name</th>
                <th class="px-4 py-3 font-semibold text-slate-700">Uploads</th>
                <th class="px-4 py-3 font-semibold text-slate-700">Latest</th>
                <th class="px-4 py-3 font-semibold text-slate-700 text-right">Action</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in normalized" :key="row.id" class="border-b border-slate-100 hover:bg-slate-50">
                <td class="px-4 py-3 whitespace-nowrap">
                  <span class="text-xs font-semibold text-slate-900 bg-slate-100 rounded-md px-2 py-1">
                    {{ row.code }}
                  </span>
                </td>
                <td class="px-4 py-3 font-medium text-slate-900">
                  {{ row.name }}
                </td>
                <td class="px-4 py-3">
                  <span class="text-xs rounded-full px-2 py-1 ring-1" :class="statusClass(row.documents_count)">
                    {{ row.documents_count || 0 }}
                  </span>
                </td>
                <td class="px-4 py-3 text-slate-700">
                  {{ formatDate(row.latest_upload_at) }}
                </td>
                <td class="px-4 py-3 text-right">
                  <Link
                    :href="`/documents/${row.id}`"
                    class="inline-flex items-center justify-center px-3 py-2 rounded-xl bg-slate-900 text-white hover:bg-slate-800 text-xs"
                  >
                    Open
                  </Link>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="px-4 py-3 text-xs text-slate-500">
          Tip: Search “OFI” or “DCR” to jump directly to those document types.
        </div>
      </div>

    </div>
  </AdminLayout>
</template>