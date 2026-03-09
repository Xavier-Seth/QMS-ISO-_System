<script setup>
import { reactive } from "vue";

const form = reactive({
    system_name: "Quality Management System",
    institution_name: "Leyte Normal University",
    office_name: "QMS (ISO) Office",
    timezone: "Asia/Manila",
    maintenance_mode: false,
});
</script>

<template>
<div>

    <!-- Header -->
    <div class="mb-8">
        <h2 class="text-2xl font-semibold text-slate-900">
            System Settings
        </h2>
        <p class="text-sm text-slate-500 mt-1">
            Configure system-wide settings and branding
        </p>
    </div>


    <div class="grid grid-cols-2 gap-8">

        <!-- LEFT SIDE -->
        <div class="space-y-4">

            <div>
                <label class="block text-sm mb-1">System Name</label>
                <input v-model="form.system_name" class="input"/>
            </div>

            <div>
                <label class="block text-sm mb-1">Institution Name</label>
                <input v-model="form.institution_name" class="input"/>
            </div>

            <div>
                <label class="block text-sm mb-1">Office Name</label>
                <input v-model="form.office_name" class="input"/>
            </div>

            <div>
                <label class="block text-sm mb-1">Timezone</label>
                <select v-model="form.timezone" class="input">
                    <option value="Asia/Manila">Asia/Manila</option>
                    <option value="UTC">UTC</option>
                </select>
            </div>

            <div class="flex items-center gap-2 mt-4">
                <input type="checkbox" v-model="form.maintenance_mode" />
                <label class="text-sm">
                    Enable Maintenance Mode
                </label>
            </div>

        </div>


        <!-- RIGHT SIDE -->
        <div class="space-y-6">

            <!-- LOGO -->
            <div>
                <label class="block text-sm mb-2">System Logo</label>

                <div class="flex items-center gap-4">

                    <div class="w-20 h-20 rounded-md bg-gray-200 flex items-center justify-center">
                        <span class="text-xs text-gray-500">Logo</span>
                    </div>

                    <div class="flex gap-2">
                        <button class="px-4 py-2 bg-slate-800 text-white rounded-md text-sm">
                            Upload
                        </button>

                        <button class="px-4 py-2 bg-gray-300 rounded-md text-sm">
                            Remove
                        </button>
                    </div>

                </div>
            </div>


            <!-- E-SIGNATURE -->
            <div>
                <label class="block text-sm mb-2">
                    Authorized Signature
                </label>

                <div class="flex items-center gap-4">

                    <div class="w-24 h-16 rounded-md bg-gray-200 flex items-center justify-center">
                        <span class="text-xs text-gray-500">Signature</span>
                    </div>

                    <div class="flex gap-2">
                        <button class="px-4 py-2 bg-slate-800 text-white rounded-md text-sm">
                            Upload
                        </button>

                        <button class="px-4 py-2 bg-gray-300 rounded-md text-sm">
                            Remove
                        </button>
                    </div>

                </div>
            </div>

        </div>

    </div>


    <!-- SAVE BUTTON -->
    <div class="mt-10">
        <button class="px-6 py-2 bg-slate-800 text-white rounded-md text-sm">
            Save System Settings
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