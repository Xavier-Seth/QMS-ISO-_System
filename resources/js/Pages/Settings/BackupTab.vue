<script setup>
import { computed, ref } from "vue";
import { usePage, useForm, router } from "@inertiajs/vue3";
import { useToast } from "@/Composables/useToast";
import { useConfirm } from "@/Composables/useConfirm";
import { useLoadingOverlay } from "@/Composables/useLoadingOverlay";

const page = usePage();
const toast = useToast();
const confirm = useConfirm();
const loading = useLoadingOverlay();

const backupSettings = computed(() => page.props.backup_settings ?? {});
const latestBackup = computed(() => backupSettings.value.latest_backup ?? null);

const settingsForm = useForm({
    backup_frequency: backupSettings.value.backup_frequency ?? "weekly",
    storage_location: backupSettings.value.storage_location ?? "local",
    auto_backup: backupSettings.value.auto_backup ?? false,
});

const restoreFileInput = ref(null);
const restoreForm = useForm({ backup_file: null });

function saveSettings() {
    settingsForm.post("/settings/backup/settings", {
        preserveScroll: true,
        onSuccess: () => toast.success("Backup settings saved."),
        onError: () => toast.error("Failed to save backup settings."),
    });
}

function createBackup() {
    loading.open("Creating backup...");
    router.post(
        "/settings/backup/create",
        {},
        {
            preserveScroll: true,
            onSuccess: () => toast.success("Backup created successfully."),
            onError: () => toast.error("Failed to create backup."),
            onFinish: () => loading.close(),
        },
    );
}

function downloadBackup() {
    window.location.href = "/settings/backup/download";
}

function handleRestoreFileSelected(event) {
    const file = event.target.files?.[0] ?? null;
    restoreForm.backup_file = file;
}

async function submitRestore() {
    if (!restoreForm.backup_file) {
        toast.error("Please select a backup file.");
        return;
    }

    const confirmed = await confirm.ask({
        title: "Restore Backup",
        message:
            "This will overwrite existing files on the server. This action cannot be undone. Are you sure?",
    });

    if (!confirmed) {
        return;
    }

    loading.open("Restoring backup... Please do not navigate away.");
    restoreForm.post("/settings/backup/restore", {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            restoreForm.reset();
            if (restoreFileInput.value) {
                restoreFileInput.value.value = "";
            }
        },
        onError: () => toast.error("Restore failed. The file may be invalid or corrupted."),
        onFinish: () => loading.close(),
    });
}

function formatBytes(bytes) {
    if (!bytes) return "0 B";
    const units = ["B", "KB", "MB", "GB"];
    let i = 0;
    let size = bytes;
    while (size >= 1024 && i < units.length - 1) {
        size /= 1024;
        i++;
    }
    return `${size.toFixed(1)} ${units[i]}`;
}

function formatDate(iso) {
    if (!iso) return "";
    return new Date(iso).toLocaleString();
}
</script>

<template>
    <div>
        <!-- Header -->
        <div class="mb-8">
            <h2 class="text-2xl font-semibold text-slate-900">Backup Settings</h2>
            <p class="mt-1 text-sm text-slate-500">
                Manage backup, restore, and storage options
            </p>
        </div>

        <div class="grid grid-cols-1 gap-8 md:grid-cols-2">
            <!-- LEFT SIDE: Settings -->
            <div class="space-y-4">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700"
                        >Backup Frequency</label
                    >
                    <select v-model="settingsForm.backup_frequency" class="input">
                        <option value="daily">Daily</option>
                        <option value="weekly">Weekly</option>
                        <option value="monthly">Monthly</option>
                    </select>
                    <p
                        v-if="settingsForm.errors.backup_frequency"
                        class="mt-1 text-xs text-red-600"
                    >
                        {{ settingsForm.errors.backup_frequency }}
                    </p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700"
                        >Storage Location</label
                    >
                    <select v-model="settingsForm.storage_location" class="input">
                        <option value="local">Local Storage</option>
                        <option value="external" disabled>
                            External Drive (coming soon)
                        </option>
                        <option value="cloud" disabled>Cloud Storage (coming soon)</option>
                    </select>
                    <p
                        v-if="settingsForm.errors.storage_location"
                        class="mt-1 text-xs text-red-600"
                    >
                        {{ settingsForm.errors.storage_location }}
                    </p>
                </div>

                <div class="mt-4 flex items-center gap-2">
                    <input
                        id="auto-backup"
                        v-model="settingsForm.auto_backup"
                        type="checkbox"
                        class="h-4 w-4 rounded border-slate-300 text-slate-800"
                    />
                    <label for="auto-backup" class="text-sm text-slate-700"
                        >Enable Automatic Backup</label
                    >
                </div>

                <div class="pt-2">
                    <button
                        class="rounded-md bg-slate-800 px-6 py-2 text-sm text-white transition hover:bg-slate-700 disabled:opacity-60"
                        type="button"
                        :disabled="settingsForm.processing"
                        @click="saveSettings"
                    >
                        Save Backup Settings
                    </button>
                </div>
            </div>

            <!-- RIGHT SIDE: Actions -->
            <div class="space-y-6">
                <!-- Manual Backup -->
                <div class="rounded-lg border border-slate-200 p-4">
                    <h3 class="mb-2 text-sm font-semibold text-slate-800">Manual Backup</h3>
                    <p class="mb-4 text-sm text-slate-500">
                        Create a backup of all uploaded files immediately.
                    </p>
                    <button
                        class="rounded-md bg-slate-800 px-4 py-2 text-sm text-white transition hover:bg-slate-700"
                        type="button"
                        @click="createBackup"
                    >
                        Create Backup Now
                    </button>
                </div>

                <!-- Latest Backup -->
                <div class="rounded-lg border border-slate-200 p-4">
                    <h3 class="mb-2 text-sm font-semibold text-slate-800">Latest Backup</h3>
                    <p class="mb-1 text-sm text-slate-500">
                        <template v-if="latestBackup">
                            {{ latestBackup.filename }} &mdash;
                            {{ formatBytes(latestBackup.size) }} &mdash;
                            {{ formatDate(latestBackup.created_at) }}
                        </template>
                        <template v-else> No backup created yet. </template>
                    </p>
                    <button
                        class="mt-3 rounded-md px-4 py-2 text-sm transition"
                        :class="
                            latestBackup
                                ? 'bg-slate-800 text-white hover:bg-slate-700'
                                : 'cursor-not-allowed bg-slate-200 text-slate-400'
                        "
                        type="button"
                        :disabled="!latestBackup"
                        @click="downloadBackup"
                    >
                        Download Latest Backup
                    </button>
                </div>

                <!-- Restore -->
                <div class="rounded-lg border border-slate-200 p-4">
                    <h3 class="mb-2 text-sm font-semibold text-slate-800">Restore Backup</h3>
                    <p class="mb-4 text-sm text-slate-500">
                        Restore files from a previously created backup ZIP.
                    </p>
                    <div class="space-y-3">
                        <input
                            ref="restoreFileInput"
                            type="file"
                            accept=".zip"
                            class="block w-full text-sm text-slate-600 file:mr-3 file:rounded file:border-0 file:bg-slate-100 file:px-3 file:py-1 file:text-sm file:text-slate-700"
                            @change="handleRestoreFileSelected"
                        />
                        <p
                            v-if="restoreForm.errors.backup_file"
                            class="text-xs text-red-600"
                        >
                            {{ restoreForm.errors.backup_file }}
                        </p>
                        <button
                            class="rounded-md bg-red-700 px-4 py-2 text-sm text-white transition hover:bg-red-800 disabled:opacity-60"
                            type="button"
                            :disabled="restoreForm.processing || !restoreForm.backup_file"
                            @click="submitRestore"
                        >
                            Restore
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.input {
    width: 100%;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    padding: 8px 10px;
    font-size: 14px;
}
</style>
