<script setup>
import AdminLayout from "@/Layouts/AdminLayout.vue";
import { Link, router } from "@inertiajs/vue3";
import { computed, ref } from "vue";

const props = defineProps({
    records: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        required: true,
    },
    counts: {
        type: Object,
        required: true,
    },
});

/* ---------------------------
   Filters
--------------------------- */
const workflowStatus = ref(props.filters?.workflow_status ?? "pending");
const type = ref(props.filters?.type ?? "all");

function applyFilters() {
    router.get(
        "/inbox",
        {
            workflow_status: workflowStatus.value,
            type: type.value,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        }
    );
}

/* ---------------------------
   Tabs
--------------------------- */
const tabs = computed(() => {
    const source = props.counts?.[type.value] ?? props.counts?.all ?? {};

    return [
        {
            key: "pending",
            label: "Pending",
            count: source.pending ?? 0,
        },
        {
            key: "approved",
            label: "Approved",
            count: source.approved ?? 0,
        },
        {
            key: "rejected",
            label: "Rejected",
            count: source.rejected ?? 0,
        },
    ];
});

/* ---------------------------
   Reject Modal
--------------------------- */
const showRejectModal = ref(false);
const rejectRecord = ref(null);
const rejectReason = ref("");

function openRejectModal(record) {
    rejectRecord.value = record;
    rejectReason.value = "";
    showRejectModal.value = true;
}

function closeRejectModal() {
    showRejectModal.value = false;
    rejectRecord.value = null;
    rejectReason.value = "";
}

function submitReject() {
    if (!rejectRecord.value) return;

    if (!rejectReason.value.trim()) {
        alert("Rejection reason is required.");
        return;
    }

    router.post(
        rejectRecord.value.reject_url,
        {
            rejection_reason: rejectReason.value.trim(),
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                closeRejectModal();
            },
            onError: (errors) => {
                alert(errors.rejection_reason || "Failed to reject record.");
            },
        }
    );
}

/* ---------------------------
   Actions
--------------------------- */
function approveRecord(record) {
    if (record.workflow_status !== "pending") return;

    router.post(
        record.approve_url,
        {},
        {
            preserveScroll: true,
        }
    );
}

/* ---------------------------
   Helpers
--------------------------- */
function workflowBadgeClass(status) {
    if (status === "approved") {
        return "bg-emerald-50 text-emerald-700 ring-emerald-200";
    }

    if (status === "rejected") {
        return "bg-rose-50 text-rose-700 ring-rose-200";
    }

    return "bg-amber-50 text-amber-700 ring-amber-200";
}

function resolutionBadgeClass(status) {
    if (status === "closed") {
        return "bg-emerald-50 text-emerald-700 ring-emerald-200";
    }

    if (status === "ongoing") {
        return "bg-blue-50 text-blue-700 ring-blue-200";
    }

    if (status === "open") {
        return "bg-sky-50 text-sky-700 ring-sky-200";
    }

    return "bg-slate-100 text-slate-700 ring-slate-200";
}

function typeBadgeClass(typeValue) {
    if (typeValue === "car") {
        return "bg-blue-50 text-blue-700 ring-blue-200";
    }

    return "bg-slate-100 text-slate-700 ring-slate-200";
}
</script>

<template>
    <AdminLayout>
        <!-- Reject Modal -->
        <div
            v-if="showRejectModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4"
            @click.self="closeRejectModal"
        >
            <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl">
                <h3 class="text-lg font-semibold text-slate-900">
                    Reject {{ rejectRecord?.type_label }} Record
                </h3>

                <p class="mt-1 text-sm text-slate-500">
                    Please provide a reason for rejecting this record.
                </p>

                <textarea
                    v-model="rejectReason"
                    rows="4"
                    class="mt-4 w-full rounded-xl border border-slate-200 px-4 py-2 text-sm outline-none focus:ring-2 focus:ring-slate-200"
                    placeholder="Enter rejection reason..."
                ></textarea>

                <div class="mt-6 flex justify-end gap-2">
                    <button
                        type="button"
                        class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                        @click="closeRejectModal"
                    >
                        Cancel
                    </button>

                    <button
                        type="button"
                        class="rounded-xl bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700"
                        @click="submitReject"
                    >
                        Reject Record
                    </button>
                </div>
            </div>
        </div>

        <div class="w-full space-y-6 p-6">
            <!-- Header -->
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="bg-gradient-to-r from-slate-900 to-slate-800 px-6 py-5">
                    <h1 class="text-xl font-semibold text-white">Admin Inbox</h1>
                    <p class="mt-1 text-sm text-slate-300">
                        Review submitted OFI and CAR records from departments and users.
                    </p>
                </div>

                <div class="border-t border-slate-200 px-6 py-4">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        <!-- Status Tabs -->
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="tab in tabs"
                                :key="tab.key"
                                type="button"
                                @click="
                                    workflowStatus = tab.key;
                                    applyFilters();
                                "
                                class="inline-flex items-center gap-2 rounded-xl border px-4 py-2 text-sm transition"
                                :class="
                                    workflowStatus === tab.key
                                        ? 'border-slate-900 bg-slate-900 text-white'
                                        : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'
                                "
                            >
                                <span>{{ tab.label }}</span>

                                <span
                                    class="rounded-full px-2 py-0.5 text-xs"
                                    :class="
                                        workflowStatus === tab.key
                                            ? 'bg-white/15 text-white'
                                            : 'bg-slate-100 text-slate-600'
                                    "
                                >
                                    {{ tab.count }}
                                </span>
                            </button>
                        </div>

                        <!-- Type Filter -->
                        <div class="flex items-center gap-2">
                            <label class="text-sm font-medium text-slate-600">Type</label>

                            <select
                                v-model="type"
                                @change="applyFilters"
                                class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm text-slate-700 outline-none focus:ring-2 focus:ring-slate-200"
                            >
                                <option value="all">All</option>
                                <option value="ofi">OFI</option>
                                <option value="car">CAR</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Table -->
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b border-slate-200 bg-slate-50">
                            <tr class="text-left">
                                <th class="px-5 py-3 font-semibold text-slate-700 whitespace-nowrap">Type</th>
                                <th class="px-5 py-3 font-semibold text-slate-700 whitespace-nowrap">Record No.</th>
                                <th class="px-5 py-3 font-semibold text-slate-700">Subject / Target</th>
                                <th class="px-5 py-3 font-semibold text-slate-700">Submitted By</th>
                                <th class="px-5 py-3 font-semibold text-slate-700 whitespace-nowrap">Department</th>
                                <th class="px-5 py-3 font-semibold text-slate-700 whitespace-nowrap">Workflow</th>
                                <th class="px-5 py-3 font-semibold text-slate-700 whitespace-nowrap">Resolution</th>
                                <th class="px-5 py-3 font-semibold text-slate-700 whitespace-nowrap">Date Submitted</th>
                                <th class="px-5 py-3 text-right font-semibold text-slate-700 whitespace-nowrap">Actions</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr
                                v-for="record in records.data"
                                :key="`${record.type}-${record.id}`"
                                class="border-b border-slate-100 hover:bg-slate-50"
                            >
                                <td class="px-5 py-4 whitespace-nowrap">
                                    <span
                                        class="rounded-full px-2.5 py-1 text-xs font-semibold ring-1"
                                        :class="typeBadgeClass(record.type)"
                                    >
                                        {{ record.type_label }}
                                    </span>
                                </td>

                                <td class="px-5 py-4 font-medium text-slate-900 whitespace-nowrap">
                                    {{ record.record_no || "—" }}
                                </td>

                                <td class="px-5 py-4 text-slate-600">
                                    <div class="max-w-[220px] truncate" :title="record.subject || '—'">
                                        {{ record.subject || "—" }}
                                    </div>
                                </td>

                                <td class="px-5 py-4 text-slate-600">
                                    <div class="max-w-[170px] leading-5">
                                        {{ record.submitted_by || "—" }}
                                    </div>
                                </td>

                                <td class="px-5 py-4 text-slate-600 whitespace-nowrap">
                                    {{ record.department || "—" }}
                                </td>

                                <td class="px-5 py-4 whitespace-nowrap">
                                    <span
                                        class="rounded-full px-2 py-1 text-xs ring-1"
                                        :class="workflowBadgeClass(record.workflow_status)"
                                    >
                                        {{ record.workflow_status }}
                                    </span>
                                </td>

                                <td class="px-5 py-4 whitespace-nowrap">
                                    <span
                                        class="rounded-full px-2 py-1 text-xs ring-1"
                                        :class="resolutionBadgeClass(record.resolution_status)"
                                    >
                                        {{ record.resolution_status || "—" }}
                                    </span>
                                </td>

                                <td class="px-5 py-4 whitespace-nowrap text-slate-600">
                                    {{ record.date_submitted || "—" }}
                                </td>

                                <td class="px-5 py-4">
                                    <div class="flex justify-end gap-2 whitespace-nowrap">
                                        <Link
                                            :href="record.view_url"
                                            class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs text-slate-700 hover:bg-slate-50"
                                        >
                                            View
                                        </Link>

                                        <template v-if="record.workflow_status === 'pending'">
                                            <button
                                                type="button"
                                                @click="approveRecord(record)"
                                                class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs text-white hover:bg-emerald-500"
                                            >
                                                Approve
                                            </button>

                                            <button
                                                type="button"
                                                @click="openRejectModal(record)"
                                                class="rounded-lg bg-rose-600 px-3 py-1.5 text-xs text-white hover:bg-rose-500"
                                            >
                                                Reject
                                            </button>
                                        </template>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="!records.data.length">
                                <td colspan="9" class="px-5 py-8 text-center text-sm text-slate-500">
                                    No records found for this filter.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="flex flex-wrap items-center gap-2 border-t border-slate-200 bg-slate-50 px-6 py-4">
                    <template
                        v-for="(link, index) in records.links"
                        :key="`${link.label}-${index}`"
                    >
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            preserve-scroll
                            preserve-state
                            class="inline-flex items-center rounded-lg border px-3 py-1.5 text-sm transition"
                            :class="
                                link.active
                                    ? 'border-slate-900 bg-slate-900 text-white'
                                    : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-100'
                            "
                        >
                            <span v-html="link.label" />
                        </Link>

                        <span
                            v-else
                            class="inline-flex cursor-not-allowed items-center rounded-lg border border-slate-200 bg-slate-100 px-3 py-1.5 text-sm text-slate-400"
                        >
                            <span v-html="link.label" />
                        </span>
                    </template>
                </div>
            </div>

            <!-- Fallback Links -->
            <div class="flex flex-wrap gap-2">
                <Link
                    href="/inbox/ofi"
                    class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                >
                    OFI Inbox
                </Link>

                <Link
                    href="/inbox/car"
                    class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                >
                    CAR Inbox
                </Link>
            </div>
        </div>
    </AdminLayout>
</template>