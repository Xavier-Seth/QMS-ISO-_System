<script setup>
import { reactive } from "vue";

const form = reactive({
    backup_frequency: "weekly",
    storage_location: "local",
    auto_backup: true,
});
</script>

<template>
    <div>
        <!-- Header -->
        <div class="mb-8">
            <h2 class="text-2xl font-semibold text-slate-900">
                Backup Settings
            </h2>
            <p class="mt-1 text-sm text-slate-500">
                Manage backup, restore, and storage options
            </p>
        </div>

        <div class="grid grid-cols-2 gap-8">
            <!-- LEFT SIDE -->
            <div class="space-y-4">
                <div>
                    <label class="mb-1 block text-sm">Backup Frequency</label>
                    <select v-model="form.backup_frequency" class="input">
                        <option value="daily">Daily</option>
                        <option value="weekly">Weekly</option>
                        <option value="monthly">Monthly</option>
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-sm">Storage Location</label>
                    <select v-model="form.storage_location" class="input">
                        <option value="local">Local Storage</option>
                        <option value="external">External Drive</option>
                        <option value="cloud">Cloud Storage</option>
                    </select>
                </div>

                <div class="mt-4 flex items-center gap-2">
                    <input type="checkbox" v-model="form.auto_backup" />
                    <label class="text-sm">Enable Automatic Backup</label>
                </div>
            </div>

            <!-- RIGHT SIDE -->
            <div class="space-y-6">
                <div class="rounded-lg border border-slate-200 p-4">
                    <h3 class="mb-2 text-sm font-semibold text-slate-800">
                        Manual Backup
                    </h3>
                    <p class="mb-4 text-sm text-slate-500">
                        Create a backup of the system immediately.
                    </p>
                    <button
                        class="rounded-md bg-slate-800 px-4 py-2 text-sm text-white"
                        type="button"
                    >
                        Create Backup Now
                    </button>
                </div>

                <div class="rounded-lg border border-slate-200 p-4">
                    <h3 class="mb-2 text-sm font-semibold text-slate-800">
                        Restore Backup
                    </h3>
                    <p class="mb-4 text-sm text-slate-500">
                        Restore the system from a previous backup file.
                    </p>
                    <div class="flex items-center gap-3">
                        <input type="file" class="text-sm" />
                        <button
                            class="rounded-md bg-slate-800 px-4 py-2 text-sm text-white"
                            type="button"
                        >
                            Restore
                        </button>
                    </div>
                </div>

                <div class="rounded-lg border border-slate-200 p-4">
                    <h3 class="mb-2 text-sm font-semibold text-slate-800">
                        Latest Backup
                    </h3>
                    <p class="mb-4 text-sm text-slate-500">
                        Last backup: No backup created yet.
                    </p>
                    <button
                        class="rounded-md bg-gray-300 px-4 py-2 text-sm text-slate-700"
                        type="button"
                    >
                        Download Latest Backup
                    </button>
                </div>
            </div>
        </div>

        <!-- SAVE BUTTON -->
        <div class="mt-10">
            <button
                class="rounded-md bg-slate-800 px-6 py-2 text-sm text-white"
                type="button"
            >
                Save Backup Settings
            </button>
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