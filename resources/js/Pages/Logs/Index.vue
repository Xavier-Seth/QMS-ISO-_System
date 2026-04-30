<script setup>
import AdminLayoutWithHeader from '@/Layouts/AdminLayoutWithHeader.vue'
import { router } from '@inertiajs/vue3'
import { ref, watch } from 'vue'

const props = defineProps({
  logs: { type: Object, required: true },
  filters: { type: Object, default: () => ({}) },
  options: {
    type: Object,
    default: () => ({
      departments: [],
      file_types: [],
      actions: [],
      modules: [],
      users: [],
    }),
  },
})

const search = ref(props.filters?.q ?? '')
const department = ref(props.filters?.department ?? '')
const fileType = ref(props.filters?.file_type ?? '')
const action = ref(props.filters?.action ?? '')
const user = ref(props.filters?.user ?? '')

let debounceTimer = null

function applyFilters() {
  router.get(
    '/logs',
    {
      q: search.value || undefined,
      department: department.value || undefined,
      file_type: fileType.value || undefined,
      action: action.value || undefined,
      user: user.value || undefined,
    },
    {
      preserveScroll: true,
      replace: true,
    }
  )
}

watch(search, () => {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => {
    applyFilters()
  }, 400)
})

function goToPage(url) {
  if (!url) return
  router.visit(url, { preserveScroll: true })
}

const actionBadgeMap = {
  login:    'bg-blue-50 text-blue-600',
  logout:   'bg-slate-100 text-slate-500',
  upload:   'bg-indigo-50 text-indigo-600',
  download: 'bg-emerald-50 text-emerald-600',
  delete:   'bg-red-50 text-red-600',
  create:   'bg-violet-50 text-violet-600',
  update:   'bg-amber-50 text-amber-600',
  view:     'bg-sky-50 text-sky-600',
  approve:  'bg-emerald-50 text-emerald-600',
  reject:   'bg-red-50 text-red-600',
}

function actionBadge(val) {
  return actionBadgeMap[val?.toLowerCase()] ?? 'bg-slate-100 text-slate-500'
}
</script>

<template>
  <AdminLayoutWithHeader>
    <template #headerLeft>
      <div>
        <p class="text-xs text-slate-400 leading-none">System</p>
        <p class="text-sm font-semibold text-slate-700 leading-tight mt-0.5">Activity Logs</p>
      </div>
    </template>

    <div class="px-8 py-7 space-y-6 bg-[#f4f6f8] min-h-screen">

      <!-- Filter bar -->
      <div class="bg-white rounded-xl border border-slate-200 px-5 py-4">
        <div class="flex flex-wrap items-center gap-3">

          <!-- Search -->
          <div class="relative min-w-[220px] flex-1 max-w-xs">
            <svg class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400"
              viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="11" cy="11" r="7" />
              <path d="M20 20l-3.5-3.5" />
            </svg>
            <input
              v-model="search"
              type="text"
              placeholder="Search logs…"
              class="h-9 w-full rounded-lg border border-slate-200 bg-slate-50 pl-9 pr-4 text-sm text-slate-700 outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100"
            />
          </div>

          <!-- Department -->
          <select
            v-model="department"
            @change="applyFilters()"
            class="h-9 rounded-lg border border-slate-200 bg-slate-50 px-3 text-sm text-slate-600 outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100"
          >
            <option value="">All Departments</option>
            <option v-for="item in props.options.departments" :key="item" :value="item">{{ item }}</option>
          </select>

          <!-- File Type -->
          <select
            v-model="fileType"
            @change="applyFilters()"
            class="h-9 rounded-lg border border-slate-200 bg-slate-50 px-3 text-sm text-slate-600 outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100"
          >
            <option value="">All File Types</option>
            <option v-for="item in props.options.file_types" :key="item" :value="item">{{ item }}</option>
          </select>

          <!-- Action -->
          <select
            v-model="action"
            @change="applyFilters()"
            class="h-9 rounded-lg border border-slate-200 bg-slate-50 px-3 text-sm text-slate-600 outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100"
          >
            <option value="">All Actions</option>
            <option v-for="item in props.options.actions" :key="item" :value="item">{{ item }}</option>
          </select>

          <!-- User -->
          <select
            v-model="user"
            @change="applyFilters()"
            class="h-9 rounded-lg border border-slate-200 bg-slate-50 px-3 text-sm text-slate-600 outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100"
          >
            <option value="">All Users</option>
            <option v-for="item in props.options.users" :key="item" :value="item">{{ item }}</option>
          </select>

        </div>
      </div>

      <!-- Table card -->
      <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full">

            <thead>
              <tr class="border-b border-slate-200 bg-slate-50">
                <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Date &amp; Time</th>
                <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">User</th>
                <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Department</th>
                <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Module</th>
                <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Record</th>
                <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Action</th>
                <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Description</th>
              </tr>
            </thead>

            <tbody class="divide-y divide-slate-100">
              <tr
                v-for="log in props.logs.data"
                :key="log.id"
                class="transition-colors hover:bg-slate-50"
              >
                <td class="whitespace-nowrap px-5 py-3.5 text-sm text-slate-600">{{ log.created_at || '—' }}</td>
                <td class="px-5 py-3.5 text-sm font-medium text-slate-700">{{ log.user_name || 'System' }}</td>
                <td class="px-5 py-3.5 text-sm text-slate-600">{{ log.department || '—' }}</td>
                <td class="px-5 py-3.5 text-sm text-slate-600">{{ log.module || '—' }}</td>
                <td class="px-5 py-3.5 text-sm text-slate-600">{{ log.record_label || '—' }}</td>
                <td class="px-5 py-3.5">
                  <span
                    class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium capitalize"
                    :class="actionBadge(log.action)"
                  >
                    {{ log.action || '—' }}
                  </span>
                </td>
                <td class="max-w-[260px] truncate px-5 py-3.5 text-sm text-slate-500" :title="log.description">
                  {{ log.description || '—' }}
                </td>
              </tr>

              <tr v-if="!props.logs.data.length">
                <td colspan="7" class="px-6 py-12 text-center text-sm text-slate-400">
                  No logs found.
                </td>
              </tr>
            </tbody>

          </table>
        </div>

        <!-- Pagination -->
        <div
          v-if="props.logs.links?.length > 3"
          class="flex flex-col gap-3 border-t border-slate-100 px-5 py-4 md:flex-row md:items-center md:justify-between"
        >
          <p class="text-sm text-slate-400">
            Showing <span class="font-medium text-slate-600">{{ props.logs.from || 0 }}</span>
            to <span class="font-medium text-slate-600">{{ props.logs.to || 0 }}</span>
            of <span class="font-medium text-slate-600">{{ props.logs.total || 0 }}</span> logs
          </p>

          <div class="flex flex-wrap gap-1.5">
            <button
              v-for="(link, index) in props.logs.links"
              :key="`${index}-${link.label}`"
              :disabled="!link.url"
              @click="goToPage(link.url)"
              class="min-w-[36px] rounded-lg border px-3 py-1.5 text-sm transition"
              :class="[
                link.active
                  ? 'border-indigo-500 bg-indigo-500 text-white'
                  : 'border-slate-200 bg-white text-slate-600 hover:border-indigo-300 hover:text-indigo-600',
                !link.url ? 'cursor-not-allowed opacity-40' : '',
              ]"
              v-html="link.label"
            />
          </div>
        </div>

      </div>
    </div>
  </AdminLayoutWithHeader>
</template>
