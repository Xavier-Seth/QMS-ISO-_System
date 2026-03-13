<script setup>
import AdminLayoutWithHeader from '@/Layouts/AdminLayoutWithHeader.vue'
import { router } from '@inertiajs/vue3'
import { ref, watch } from 'vue'

const props = defineProps({
  logs: {
    type: Object,
    required: true,
  },
  filters: {
    type: Object,
    default: () => ({}),
  },
  options: {
    type: Object,
    default: () => ({
      departments: [],
      file_types: [],
      actions: [],
      modules: [],
    }),
  },
})

const search = ref(props.filters?.q ?? '')
const department = ref(props.filters?.department ?? '')
const fileType = ref(props.filters?.file_type ?? '')
const action = ref(props.filters?.action ?? '')
const moduleValue = ref(props.filters?.module ?? '')
const dateFrom = ref(props.filters?.date_from ?? '')
const dateTo = ref(props.filters?.date_to ?? '')

let debounceTimer = null

function applyFilters() {
  router.get(
    route('logs'),
    {
      q: search.value || undefined,
      department: department.value || undefined,
      file_type: fileType.value || undefined,
      action: action.value || undefined,
      module: moduleValue.value || undefined,
      date_from: dateFrom.value || undefined,
      date_to: dateTo.value || undefined,
    },
    {
      preserveState: true,
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

watch([department, fileType, action, moduleValue, dateFrom, dateTo], () => {
  applyFilters()
})

function goToPage(url) {
  if (!url) return

  router.visit(url, {
    preserveState: true,
    preserveScroll: true,
  })
}
</script>

<template>
  <AdminLayoutWithHeader>
    <div class="px-8 pt-6 pb-8">
      <div class="mx-auto w-full max-w-[1120px]">
        <!-- Filters -->
        <div class="mb-8 flex flex-wrap items-center gap-3">
          <div class="relative w-[275px]">
            <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M21 21l-4.35-4.35m1.85-5.15a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
            </span>
            <input
              v-model="search"
              type="text"
              placeholder="Search..."
              class="h-11 w-full rounded-full border border-transparent bg-[#e5e5e5] pl-12 pr-4 text-sm text-gray-700 outline-none transition focus:border-[#243452] focus:bg-white"
            />
          </div>

          <select
            v-model="department"
            class="h-11 min-w-[140px] rounded-xl border border-[#425170] bg-white px-4 text-sm text-[#425170] outline-none"
          >
            <option value="">Office/Department</option>
            <option v-for="item in props.options.departments" :key="item" :value="item">
              {{ item }}
            </option>
          </select>

          <select
            v-model="fileType"
            class="h-11 min-w-[95px] rounded-xl border border-[#425170] bg-white px-4 text-sm text-[#425170] outline-none"
          >
            <option value="">File Type</option>
            <option v-for="item in props.options.file_types" :key="item" :value="item">
              {{ item }}
            </option>
          </select>

          <select
            v-model="action"
            class="h-11 min-w-[95px] rounded-xl border border-[#425170] bg-white px-4 text-sm text-[#425170] outline-none"
          >
            <option value="">Action</option>
            <option v-for="item in props.options.actions" :key="item" :value="item">
              {{ item }}
            </option>
          </select>

              <select
            v-model="action"
            class="h-11 min-w-[95px] rounded-xl border border-[#425170] bg-white px-4 text-sm text-[#425170] outline-none"
          >
            <option value="">User</option>
            <option v-for="item in props.options.actions" :key="item" :value="item">
              {{ item }}
            </option>
          </select>
        </div>

        

        <!-- Table Card -->
        <div class="overflow-hidden rounded-2xl bg-white shadow-sm">
          <div class="overflow-x-auto">
            <table class="min-w-full table-fixed">
              <thead class="bg-[#14203a] text-white">
                <tr>
                  <th class="w-[150px] px-7 py-5 text-left text-sm font-semibold">Date &amp; Time</th>
                  <th class="w-[145px] px-5 py-5 text-left text-sm font-semibold">User</th>
                  <th class="w-[120px] px-5 py-5 text-left text-sm font-semibold">Department</th>
                  <th class="w-[110px] px-5 py-5 text-left text-sm font-semibold">Module</th>
                  <th class="w-[120px] px-5 py-5 text-left text-sm font-semibold">Record</th>
                  <th class="w-[115px] px-5 py-5 text-left text-sm font-semibold">Action</th>
                  <th class="w-[260px] px-5 py-5 text-left text-sm font-semibold">Description</th>
                </tr>
              </thead>

              <tbody>
                <tr
                  v-for="log in props.logs.data"
                  :key="log.id"
                  class="text-sm text-gray-700 odd:bg-[#d9d9d9] even:bg-[#ececec]"
                >
                  <td class="px-7 py-3 align-middle">
                    {{ log.created_at || '—' }}
                  </td>
                  <td class="px-5 py-3 align-middle">
                    {{ log.user_name || 'System' }}
                  </td>
                  <td class="px-5 py-3 align-middle">
                    {{ log.department || '—' }}
                  </td>
                  <td class="px-5 py-3 align-middle">
                    {{ log.module || '—' }}
                  </td>
                  <td class="px-5 py-3 align-middle">
                    {{ log.record_label || '—' }}
                  </td>
                  <td class="px-5 py-3 align-middle">
                    {{ log.action || '—' }}
                  </td>
                  <td
                    class="max-w-[250px] truncate px-5 py-3 align-middle"
                    :title="log.description"
                  >
                    {{ log.description || '—' }}
                  </td>
                </tr>

                <tr v-if="!props.logs.data.length">
                  <td colspan="7" class="px-6 py-10 text-center text-sm text-gray-500">
                    No logs found.
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Pagination -->
          <div
            v-if="props.logs.links?.length > 3"
            class="flex flex-col gap-3 border-t border-gray-200 bg-white px-6 py-4 md:flex-row md:items-center md:justify-between"
          >
            <div class="text-sm text-gray-500">
              Showing {{ props.logs.from || 0 }} to {{ props.logs.to || 0 }} of {{ props.logs.total || 0 }} logs
            </div>

            <div class="flex flex-wrap gap-2">
              <button
                v-for="(link, index) in props.logs.links"
                :key="`${index}-${link.label}`"
                :disabled="!link.url"
                @click="goToPage(link.url)"
                class="rounded-lg px-3 py-2 text-sm transition"
                :class="[
                  link.active
                    ? 'bg-[#14203a] text-white'
                    : 'bg-gray-100 text-gray-700 hover:bg-gray-200',
                  !link.url ? 'cursor-not-allowed opacity-50' : ''
                ]"
                v-html="link.label"
              />
            </div>
          </div>
        </div>
      </div>
    </div>
  </AdminLayoutWithHeader>
</template>