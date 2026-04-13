<script setup>
import { reactive, ref, computed } from "vue";

const form = reactive({
    system_name: "Quality Management System",
    institution_name: "Leyte Normal University",
    office_name: "QMS (ISO) Office",
    maintenance_mode: false,
});

const templates = reactive([
    { module: "OFI", current_file: "F-QMS-007.docx" },
    { module: "DCR", current_file: "F-QMS-001.docx" },
    { module: "CAR", current_file: "F-QMS-006.docx" },
]);

const selectedModule = ref("OFI");

const dynamicFields = reactive({
    OFI: [],
    DCR: [],
    CAR: [],
});

const currentFields = computed(() => dynamicFields[selectedModule.value]);

function addField() {
    dynamicFields[selectedModule.value].push({
        id: Date.now(),
        label: "",
        key: "",
        type: "text",
        required: false,
    });
}

function removeField(id) {
    dynamicFields[selectedModule.value] =
        dynamicFields[selectedModule.value].filter(f => f.id !== id);
}
</script>

<template>
<div>

    <!-- HEADER -->
    <div class="mb-8">
        <h2 class="text-2xl font-semibold text-slate-900">
            System Settings
        </h2>
        <p class="text-sm text-slate-500 mt-1">
            Configure system-wide settings, templates, and additional fields
        </p>
    </div>

    <!-- GENERAL SETTINGS -->
    <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm">
        <h3 class="text-lg font-semibold text-slate-900 mb-6">
            General Settings
        </h3>

        <div class="grid grid-cols-2 gap-8">

            <div class="space-y-4">
                <div>
                    <label class="text-sm text-slate-600">System Name</label>
                    <input v-model="form.system_name"
                        class="mt-1 w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-slate-200 outline-none"/>
                </div>

                <div>
                    <label class="text-sm text-slate-600">Institution Name</label>
                    <input v-model="form.institution_name"
                        class="mt-1 w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-slate-200 outline-none"/>
                </div>

                <div>
                    <label class="text-sm text-slate-600">Office Name</label>
                    <input v-model="form.office_name"
                        class="mt-1 w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-slate-200 outline-none"/>
                </div>

                <div class="flex items-center gap-2 mt-4">
                    <input type="checkbox" v-model="form.maintenance_mode" />
                    <label class="text-sm text-slate-700">
                        Enable Maintenance Mode
                    </label>
                </div>
            </div>

            <div class="space-y-6">

                <!-- LOGO -->
                <div>
                    <label class="text-sm text-slate-600">System Logo</label>
                    <div class="mt-2 flex items-center gap-4">
                        <div class="w-20 h-20 bg-gray-100 border rounded-lg flex items-center justify-center text-xs text-gray-400">
                            Logo
                        </div>

                        <div class="flex gap-2">
                            <button class="px-4 py-2 bg-slate-800 text-white rounded-lg text-sm hover:bg-slate-900">
                                Upload
                            </button>
                            <button class="px-4 py-2 border rounded-lg text-sm">
                                Remove
                            </button>
                        </div>
                    </div>
                </div>

                <!-- SIGNATURE -->
                <div>
                    <label class="text-sm text-slate-600">Authorized Signature</label>
                    <div class="mt-2 flex items-center gap-4">
                        <div class="w-24 h-16 bg-gray-100 border rounded-lg flex items-center justify-center text-xs text-gray-400">
                            Signature
                        </div>

                        <div class="flex gap-2">
                            <button class="px-4 py-2 bg-slate-800 text-white rounded-lg text-sm hover:bg-slate-900">
                                Upload
                            </button>
                            <button class="px-4 py-2 border rounded-lg text-sm">
                                Remove
                            </button>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- TEMPLATE MANAGEMENT -->
    <div class="mt-8 bg-white border border-slate-200 rounded-2xl p-6 shadow-sm">
        <h3 class="text-lg font-semibold text-slate-900 mb-4">
            Template Management
        </h3>

        <div class="space-y-4">
            <div v-for="t in templates" :key="t.module"
                class="border rounded-xl p-4 flex justify-between items-center">

                <div>
                    <p class="text-sm font-semibold text-slate-800">
                        {{ t.module }} Template
                    </p>
                    <p class="text-sm text-slate-500">
                        {{ t.current_file || "No template uploaded" }}
                    </p>
                </div>

                <button class="px-4 py-2 bg-slate-800 text-white rounded-lg text-sm hover:bg-slate-900">
                    Upload Template
                </button>

            </div>
        </div>
    </div>

    <!-- ADDITIONAL FIELDS -->
    <div class="mt-8 bg-white border border-slate-200 rounded-2xl p-6 shadow-sm">
        <h3 class="text-lg font-semibold text-slate-900 mb-4">
            Additional Fields Setup
        </h3>

        <!-- SELECT MODULE -->
        <div class="flex justify-between items-end mb-4">
            <div>
                <label class="text-sm text-slate-600">Module</label>
                <select v-model="selectedModule"
                    class="mt-1 border rounded-lg px-3 py-2 text-sm">
                    <option>OFI</option>
                    <option>DCR</option>
                    <option>CAR</option>
                </select>
            </div>

            <button @click="addField"
                class="px-4 py-2 bg-slate-800 text-white rounded-lg text-sm hover:bg-slate-900">
                + Add Field
            </button>
        </div>

        <!-- FIELDS -->
        <div v-if="currentFields.length" class="space-y-4">
            <div v-for="field in currentFields" :key="field.id"
                class="border rounded-xl p-4 grid grid-cols-5 gap-4">

                <input v-model="field.label"
                    placeholder="Label"
                    class="border rounded-lg px-3 py-2 text-sm"/>

                <input v-model="field.key"
                    placeholder="Placeholder key"
                    class="border rounded-lg px-3 py-2 text-sm"/>

                <select v-model="field.type"
                    class="border rounded-lg px-3 py-2 text-sm">
                    <option value="text">Text</option>
                    <option value="textarea">Textarea</option>
                    <option value="date">Date</option>
                </select>

                <div class="flex items-center gap-2">
                    <input type="checkbox" v-model="field.required" />
                    <span class="text-sm">Required</span>
                </div>

                <button @click="removeField(field.id)"
                    class="text-red-600 text-sm">
                    Remove
                </button>

            </div>
        </div>

        <div v-else class="text-sm text-slate-400">
            No additional fields for {{ selectedModule }}
        </div>

        <!-- NOTE -->
        <div class="mt-4 text-xs text-amber-600">
            Placeholder key must match Word template (e.g. ${officeCode})
        </div>
    </div>

    <!-- SAVE -->
    <div class="mt-10">
        <button class="px-6 py-2 bg-slate-800 text-white rounded-lg text-sm hover:bg-slate-900">
            Save Settings
        </button>
    </div>

</div>
</template>