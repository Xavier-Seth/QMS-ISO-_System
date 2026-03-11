<script setup>
import AdminLayout from "@/Layouts/AdminLayoutWithHeader.vue";
import { router, useForm } from "@inertiajs/vue3";
import { ref } from "vue";
import { useLoadingOverlay } from "@/Composables/useLoadingOverlay";
import { useToast } from "@/Composables/useToast";
import { useConfirm } from "@/Composables/useConfirm";

const props = defineProps({
  users: Object,
  filters: Object,
});

const loading = useLoadingOverlay();
const toast = useToast();
const confirm = useConfirm();

const q = ref(props.filters?.q ?? "");
const expandedId = ref(null);
const showCreate = ref(false);

const createForm = useForm({
  username: "",
  name: "",
  email: "",
  role: "admin_officer",
  position: "",
  department: "",
  office_location: "",
  password: "",
});

const runSearch = () => {
  router.get(
    "/users",
    { q: q.value },
    { preserveState: true, replace: true, preserveScroll: true }
  );
};

const onHeaderSearch = (value) => {
  q.value = value ?? "";
  runSearch();
};

const toggleExpand = (id) => {
  expandedId.value = expandedId.value === id ? null : id;
};

const isExpanded = (id) => expandedId.value === id;

const goTo = (url) => {
  if (!url) return;
  router.get(url, {}, { preserveState: true, preserveScroll: true });
};

const openCreate = () => {
  createForm.reset();
  createForm.clearErrors();
  showCreate.value = true;
};

const closeCreate = () => {
  showCreate.value = false;
};

const submitCreate = () => {
  loading.open("Creating user...");

  createForm.post("/users", {
    preserveScroll: true,
    onSuccess: () => {
      showCreate.value = false;
      createForm.reset();

      router.reload({
        preserveScroll: true,
      });
    },
    onError: () => {
      toast.error("Failed to create user. Please check the form and try again.");
    },
    onFinish: () => {
      loading.close();
    },
  });
};

const deleteUser = async (user) => {
  const confirmed = await confirm.ask({
    title: "Delete User",
    message: `Are you sure you want to delete ${user.name}? This action cannot be undone.`,
    confirmText: "Delete",
    cancelText: "Cancel",
    tone: "danger",
  });

  if (!confirmed) return;

  loading.open("Deleting user...");

  router.delete(`/users/${user.id}`, {
    preserveScroll: true,
    onSuccess: () => {
      router.reload({
        preserveScroll: true,
      });
    },
    onError: () => {
      toast.error("Failed to delete user.");
    },
    onFinish: () => {
      loading.close();
    },
  });
};
</script>

<template>
  <AdminLayout :showSearch="true" :searchValue="q" @search="onHeaderSearch">
    <div class="px-10 pb-8">
      <div class="mb-10 ml-[380px]">
        <button
          type="button"
          @click="openCreate"
          class="group inline-flex items-center gap-2 text-sm text-slate-800 transition duration-200 hover:-translate-y-[1px] hover:text-slate-950 active:translate-y-0"
        >
          <svg
            class="h-4 w-4 transition-transform duration-200 group-hover:rotate-90"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
          >
            <circle cx="12" cy="12" r="10" />
            <path d="M12 8v8" />
            <path d="M8 12h8" />
          </svg>
          <span>Add New User</span>
        </button>
      </div>

      <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="bg-slate-900 px-4 py-3 text-white">
          <div
            class="grid items-center"
            style="grid-template-columns: 44px 1.2fr 1fr 1fr 1.2fr 110px"
          >
            <div class="flex items-center justify-center">
              <input type="checkbox" class="accent-indigo-400" />
            </div>
            <div class="text-xs font-semibold tracking-wide">Name</div>
            <div class="text-xs font-semibold tracking-wide">Position</div>
            <div class="text-xs font-semibold tracking-wide">Department</div>
            <div class="text-xs font-semibold tracking-wide">Email</div>
            <div class="text-center text-xs font-semibold tracking-wide">Actions</div>
          </div>
        </div>

        <div v-if="users.data.length">
          <template v-for="u in users.data" :key="u.id">
            <div
              class="border-b border-slate-100 px-4 py-2"
              :class="isExpanded(u.id) ? 'bg-slate-50' : 'bg-white'"
            >
              <div
                class="grid items-center"
                style="grid-template-columns: 44px 1.2fr 1fr 1fr 1.2fr 110px"
              >
                <div class="flex items-center justify-center gap-2">
                  <input type="checkbox" class="accent-indigo-500" />

                  <button
                    class="text-slate-500 hover:text-slate-800"
                    @click="toggleExpand(u.id)"
                    type="button"
                    title="Expand"
                  >
                    <svg
                      class="h-4 w-4"
                      :class="isExpanded(u.id) ? 'rotate-180' : ''"
                      viewBox="0 0 24 24"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="2"
                    >
                      <polyline points="6 9 12 15 18 9" />
                    </svg>
                  </button>
                </div>

                <div class="text-sm font-medium text-slate-800">
                  {{ u.name }}
                </div>
                <div class="text-sm text-slate-600">{{ u.position ?? "-" }}</div>
                <div class="text-sm text-slate-600">{{ u.department ?? "-" }}</div>
                <div class="text-sm text-slate-600">{{ u.email }}</div>

                <div class="flex items-center justify-center gap-4 text-slate-500">
                  <button
                    class="hover:text-slate-800"
                    type="button"
                    title="Edit"
                  >
                    <svg
                      class="h-4 w-4"
                      viewBox="0 0 24 24"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="2"
                    >
                      <path d="M12 20h9" />
                      <path d="M16.5 3.5a2.1 2.1 0 013 3L7 19l-4 1 1-4 12.5-12.5z" />
                    </svg>
                  </button>

                  <button
                    class="hover:text-red-600"
                    type="button"
                    title="Delete"
                    @click="deleteUser(u)"
                  >
                    <svg
                      class="h-4 w-4"
                      viewBox="0 0 24 24"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="2"
                    >
                      <path d="M3 6h18" />
                      <path d="M8 6V4h8v2" />
                      <path d="M19 6l-1 14H6L5 6" />
                    </svg>
                  </button>
                </div>
              </div>

              <div v-if="isExpanded(u.id)" class="mt-3">
                <div
                  class="rounded-lg border border-indigo-200 bg-white px-6 py-4 shadow-[0_6px_18px_rgba(15,23,42,0.04)]"
                >
                  <div class="grid gap-10 md:grid-cols-2">
                    <div>
                      <div class="mb-2 text-xs font-semibold text-slate-700">
                        Office Location
                      </div>
                      <div class="flex items-start gap-2 text-sm text-slate-600">
                        <svg
                          class="mt-[2px] h-4 w-4"
                          viewBox="0 0 24 24"
                          fill="none"
                          stroke="currentColor"
                          stroke-width="2"
                        >
                          <path d="M12 21s-6-5.3-6-10a6 6 0 1112 0c0 4.7-6 10-6 10z" />
                          <circle cx="12" cy="11" r="2" />
                        </svg>
                        <span>{{ u.office_location ?? "-" }}</span>
                      </div>
                    </div>

                    <div>
                      <div class="mb-2 text-xs font-semibold text-slate-700">Account</div>
                      <div class="text-sm text-slate-600">
                        <div>
                          <span class="text-slate-500">Username:</span> {{ u.username }}
                        </div>
                        <div class="mt-1">
                          <span class="text-slate-500">Role:</span> {{ u.role }}
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </template>
        </div>

        <div v-else class="px-6 py-10 text-center text-sm text-slate-500">
          No users found.
        </div>

        <div class="flex items-center justify-between px-6 py-4">
          <div class="text-xs text-slate-500">
            Showing {{ users.from ?? 0 }}–{{ users.to ?? 0 }} of {{ users.total ?? 0 }}
          </div>

          <div class="flex gap-2">
            <button
              class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm text-slate-700 transition duration-150 hover:-translate-y-[1px] hover:bg-slate-50 disabled:opacity-50 disabled:hover:translate-y-0 disabled:hover:bg-white"
              :disabled="!users.prev_page_url"
              @click="goTo(users.prev_page_url)"
              type="button"
            >
              Prev
            </button>

            <button
              class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm text-slate-700 transition duration-150 hover:-translate-y-[1px] hover:bg-slate-50 disabled:opacity-50 disabled:hover:translate-y-0 disabled:hover:bg-white"
              :disabled="!users.next_page_url"
              @click="goTo(users.next_page_url)"
              type="button"
            >
              Next
            </button>
          </div>
        </div>
      </div>

      <Transition
        enter-active-class="transition-opacity duration-150 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition-opacity duration-100 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div
          v-if="showCreate"
          class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4 backdrop-blur-[2px]"
          @click.self="closeCreate"
        >
          <Transition
            appear
            enter-active-class="transition-all duration-200 ease-out"
            enter-from-class="opacity-0 translate-y-2 scale-[0.985]"
            enter-to-class="opacity-100 translate-y-0 scale-100"
            leave-active-class="transition-all duration-150 ease-in"
            leave-from-class="opacity-100 translate-y-0 scale-100"
            leave-to-class="opacity-0 translate-y-2 scale-[0.985]"
          >
            <div
              v-if="showCreate"
              class="w-full max-w-2xl overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl"
            >
              <div class="flex items-center justify-between bg-slate-900 px-5 py-4 text-white">
                <div class="font-semibold">Create New User</div>
                <button
                  class="text-white/80 transition duration-200 hover:rotate-90 hover:text-white"
                  type="button"
                  @click="closeCreate"
                >
                  ✕
                </button>
              </div>

              <div class="p-5">
                <div class="grid gap-4 md:grid-cols-2">
                  <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-700">Username</label>
                    <input
                      v-model="createForm.username"
                      type="text"
                      class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm transition duration-150 focus:border-[#C9A84C] focus:outline-none focus:ring-2 focus:ring-[#C9A84C]/20"
                    />
                    <p v-if="createForm.errors.username" class="mt-1 text-xs text-red-600">
                      {{ createForm.errors.username }}
                    </p>
                  </div>

                  <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-700">Role</label>
                    <select
                      v-model="createForm.role"
                      class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm transition duration-150 focus:border-[#C9A84C] focus:outline-none focus:ring-2 focus:ring-[#C9A84C]/20"
                    >
                      <option value="admin_officer">Admin Officer</option>
                      <option value="admin">Admin</option>
                    </select>
                    <p v-if="createForm.errors.role" class="mt-1 text-xs text-red-600">
                      {{ createForm.errors.role }}
                    </p>
                  </div>

                  <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-700">Full Name</label>
                    <input
                      v-model="createForm.name"
                      type="text"
                      class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm transition duration-150 focus:border-[#C9A84C] focus:outline-none focus:ring-2 focus:ring-[#C9A84C]/20"
                    />
                    <p v-if="createForm.errors.name" class="mt-1 text-xs text-red-600">
                      {{ createForm.errors.name }}
                    </p>
                  </div>

                  <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-700">Email</label>
                    <input
                      v-model="createForm.email"
                      type="email"
                      class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm transition duration-150 focus:border-[#C9A84C] focus:outline-none focus:ring-2 focus:ring-[#C9A84C]/20"
                    />
                    <p v-if="createForm.errors.email" class="mt-1 text-xs text-red-600">
                      {{ createForm.errors.email }}
                    </p>
                  </div>

                  <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-700">Position</label>
                    <input
                      v-model="createForm.position"
                      type="text"
                      class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm transition duration-150 focus:border-[#C9A84C] focus:outline-none focus:ring-2 focus:ring-[#C9A84C]/20"
                    />
                  </div>

                  <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-700">Department</label>
                    <input
                      v-model="createForm.department"
                      type="text"
                      class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm transition duration-150 focus:border-[#C9A84C] focus:outline-none focus:ring-2 focus:ring-[#C9A84C]/20"
                    />
                  </div>

                  <div class="md:col-span-2">
                    <label class="mb-1 block text-xs font-semibold text-slate-700">
                      Office Location
                    </label>
                    <input
                      v-model="createForm.office_location"
                      type="text"
                      class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm transition duration-150 focus:border-[#C9A84C] focus:outline-none focus:ring-2 focus:ring-[#C9A84C]/20"
                    />
                  </div>

                  <div class="md:col-span-2">
                    <label class="mb-1 block text-xs font-semibold text-slate-700">
                      Temporary Password
                    </label>
                    <input
                      v-model="createForm.password"
                      type="password"
                      class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm transition duration-150 focus:border-[#C9A84C] focus:outline-none focus:ring-2 focus:ring-[#C9A84C]/20"
                    />
                    <p v-if="createForm.errors.password" class="mt-1 text-xs text-red-600">
                      {{ createForm.errors.password }}
                    </p>
                  </div>
                </div>

                <div class="mt-6 flex justify-end gap-2">
                  <button
                    class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-slate-700 transition duration-150 hover:-translate-y-[1px] hover:bg-slate-50 active:translate-y-0"
                    type="button"
                    @click="closeCreate"
                  >
                    Cancel
                  </button>

                  <button
                    class="rounded-lg bg-[#C9A84C] px-4 py-2 text-black transition duration-150 hover:-translate-y-[1px] hover:opacity-90 active:translate-y-0 disabled:opacity-50 disabled:hover:translate-y-0"
                    type="button"
                    :disabled="createForm.processing"
                    @click="submitCreate"
                  >
                    {{ createForm.processing ? "Creating..." : "Create User" }}
                  </button>
                </div>
              </div>
            </div>
          </Transition>
        </div>
      </Transition>
    </div>
  </AdminLayout>
</template>