<script setup>
import { reactive, ref, computed, onMounted } from "vue";
import axios from "axios";

const form = reactive({
    system_name: "Quality Management System",
    institution_name: "Leyte Normal University",
    office_name: "QMS (ISO) Office",
    maintenance_mode: false,
});

const loading = ref(false);
const uploadingTemplate = ref(false);
const savingField = ref(false);
const templateInput = ref(null);
const selectedModule = ref("DCR");

const activeTemplate = ref(null);
const templateHistory = ref([]);
const dynamicFields = ref([]);

const qmsModules = [
    {
        code: "DCR",
        name: "DCR",
        description: "Document Change Request",
    },
    {
        code: "OFI",
        name: "OFI",
        description: "Opportunity for Improvement",
    },
    {
        code: "CAR",
        name: "CAR",
        description: "Corrective Action Request",
    },
];

const newField = reactive({
    label: "",
    field_key: "",
    field_type: "text",
    is_required: false,
    is_active: true,
    sort_order: 0,
});

const editingFieldId = ref(null);

const hasFields = computed(() => dynamicFields.value.length > 0);
const selectedModuleConfig = computed(
    () =>
        qmsModules.find((module) => module.code === selectedModule.value) ??
        qmsModules[0]
);

function qmsTemplateSettingsUrl(path = "") {
    const suffix = path ? `/${path}` : "";

    return `/settings/qms-templates/${selectedModule.value}${suffix}`;
}

function mapDynamicFields(fields) {
    return fields.map((field) => ({
        id: field.id,
        module: field.module,
        label: field.label,
        field_key: field.field_key,
        field_type: field.field_type,
        is_required: !!field.is_required,
        is_active: !!field.is_active,
        sort_order: Number(field.sort_order ?? 0),
        isEditing: false,
        isSaving: false,
        isDeleting: false,
    }));
}

async function loadQmsTemplateSettings() {
    loading.value = true;

    try {
        const { data } = await axios.get(qmsTemplateSettingsUrl());

        activeTemplate.value = data.active_template ?? null;
        templateHistory.value = data.templates ?? [];
        dynamicFields.value = mapDynamicFields(data.fields ?? []);
    } catch (error) {
        console.error("Failed to load QMS template settings:", error);
        alert(
            error?.response?.data?.message ||
                `Failed to load ${selectedModule.value} template settings.`
        );
    } finally {
        loading.value = false;
    }
}

async function selectModule(moduleCode) {
    if (selectedModule.value === moduleCode || loading.value) {
        return;
    }

    selectedModule.value = moduleCode;
    editingFieldId.value = null;
    resetNewField();
    await loadQmsTemplateSettings();
}

function triggerTemplateUpload() {
    templateInput.value?.click();
}

async function handleTemplateSelected(event) {
    const file = event.target.files?.[0];

    if (!file) return;

    const formData = new FormData();
    formData.append("template_file", file);
    formData.append("name", file.name);
    formData.append("set_active", "1");

    uploadingTemplate.value = true;

    try {
        await axios.post(qmsTemplateSettingsUrl("upload"), formData, {
            headers: {
                "Content-Type": "multipart/form-data",
            },
        });

        await loadQmsTemplateSettings();
        alert(`${selectedModule.value} template uploaded successfully.`);
    } catch (error) {
        console.error("Failed to upload template:", error);
        alert(
            error?.response?.data?.message ||
                `Failed to upload ${selectedModule.value} template.`
        );
    } finally {
        uploadingTemplate.value = false;

        if (templateInput.value) {
            templateInput.value.value = "";
        }
    }
}

async function activateTemplate(templateId) {
    if (!templateId) return;

    try {
        await axios.patch(qmsTemplateSettingsUrl(`${templateId}/activate`));
        await loadQmsTemplateSettings();
        alert(`Active ${selectedModule.value} template updated successfully.`);
    } catch (error) {
        console.error("Failed to activate template:", error);
        alert(
            error?.response?.data?.message ||
                `Failed to activate ${selectedModule.value} template.`
        );
    }
}

function resetNewField() {
    newField.label = "";
    newField.field_key = "";
    newField.field_type = "text";
    newField.is_required = false;
    newField.is_active = true;
    newField.sort_order = 0;
}

async function createField() {
    savingField.value = true;

    try {
        await axios.post(qmsTemplateSettingsUrl("fields"), {
            label: newField.label,
            field_key: newField.field_key || null,
            field_type: newField.field_type,
            is_required: newField.is_required,
            is_active: newField.is_active,
            sort_order: Number(newField.sort_order ?? 0),
        });

        resetNewField();
        await loadQmsTemplateSettings();
        alert(`${selectedModule.value} field created successfully.`);
    } catch (error) {
        console.error("Failed to create field:", error);

        if (error?.response?.status === 422) {
            const errors = error.response.data.errors || {};
            const firstError = Object.values(errors)[0]?.[0];
            alert(firstError || "Please check the field input.");
        } else {
            alert(
                error?.response?.data?.message ||
                    `Failed to create ${selectedModule.value} field.`
            );
        }
    } finally {
        savingField.value = false;
    }
}

function editField(field) {
    if (editingFieldId.value !== null) {
        const currentlyEditing = dynamicFields.value.find(
            (f) => f.id === editingFieldId.value
        );
        if (currentlyEditing) {
            currentlyEditing.isEditing = false;
        }
    }

    editingFieldId.value = field.id;
    field.isEditing = true;
}

function cancelEditField(field) {
    field.isEditing = false;
    editingFieldId.value = null;
    loadQmsTemplateSettings();
}

async function updateField(field) {
    field.isSaving = true;

    try {
        await axios.put(qmsTemplateSettingsUrl(`fields/${field.id}`), {
            label: field.label,
            field_key: field.field_key,
            field_type: field.field_type,
            is_required: field.is_required,
            is_active: field.is_active,
            sort_order: Number(field.sort_order ?? 0),
        });

        editingFieldId.value = null;
        await loadQmsTemplateSettings();
        alert(`${selectedModule.value} field updated successfully.`);
    } catch (error) {
        console.error("Failed to update field:", error);

        if (error?.response?.status === 422) {
            const errors = error.response.data.errors || {};
            const firstError = Object.values(errors)[0]?.[0];
            alert(firstError || "Please check the field input.");
        } else {
            alert(
                error?.response?.data?.message ||
                    `Failed to update ${selectedModule.value} field.`
            );
        }
    } finally {
        field.isSaving = false;
    }
}

async function deleteField(field) {
    const confirmed = window.confirm(
        `Delete the field "${field.label}"?`
    );

    if (!confirmed) return;

    field.isDeleting = true;

    try {
        await axios.delete(qmsTemplateSettingsUrl(`fields/${field.id}`));
        await loadQmsTemplateSettings();
        alert(`${selectedModule.value} field deleted successfully.`);
    } catch (error) {
        console.error("Failed to delete field:", error);
        alert(
            error?.response?.data?.message ||
                `Failed to delete ${selectedModule.value} field.`
        );
    } finally {
        field.isDeleting = false;
    }
}

onMounted(() => {
    loadQmsTemplateSettings();
});
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
                        <input
                            v-model="form.system_name"
                            class="mt-1 w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-slate-200 outline-none"
                        />
                    </div>

                    <div>
                        <label class="text-sm text-slate-600">Institution Name</label>
                        <input
                            v-model="form.institution_name"
                            class="mt-1 w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-slate-200 outline-none"
                        />
                    </div>

                    <div>
                        <label class="text-sm text-slate-600">Office Name</label>
                        <input
                            v-model="form.office_name"
                            class="mt-1 w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-slate-200 outline-none"
                        />
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
                            <div
                                class="w-20 h-20 bg-gray-100 border rounded-lg flex items-center justify-center text-xs text-gray-400"
                            >
                                Logo
                            </div>

                            <div class="flex gap-2">
                                <button
                                    type="button"
                                    class="px-4 py-2 bg-slate-800 text-white rounded-lg text-sm hover:bg-slate-900"
                                >
                                    Upload
                                </button>
                                <button
                                    type="button"
                                    class="px-4 py-2 border rounded-lg text-sm"
                                >
                                    Remove
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- SIGNATURE -->
                    <div>
                        <label class="text-sm text-slate-600">Authorized Signature</label>
                        <div class="mt-2 flex items-center gap-4">
                            <div
                                class="w-24 h-16 bg-gray-100 border rounded-lg flex items-center justify-center text-xs text-gray-400"
                            >
                                Signature
                            </div>

                            <div class="flex gap-2">
                                <button
                                    type="button"
                                    class="px-4 py-2 bg-slate-800 text-white rounded-lg text-sm hover:bg-slate-900"
                                >
                                    Upload
                                </button>
                                <button
                                    type="button"
                                    class="px-4 py-2 border rounded-lg text-sm"
                                >
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
            <div class="flex items-center justify-between gap-4 mb-4">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900">
                        {{ selectedModuleConfig.name }} Template Management
                    </h3>
                    <p class="text-sm text-slate-500 mt-1">
                        Upload and manage the active DOCX template for the
                        {{ selectedModuleConfig.description }} module
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <input
                        ref="templateInput"
                        type="file"
                        accept=".docx"
                        class="hidden"
                        @change="handleTemplateSelected"
                    />

                    <button
                        type="button"
                        @click="triggerTemplateUpload"
                        :disabled="uploadingTemplate"
                        class="px-4 py-2 bg-slate-800 text-white rounded-lg text-sm hover:bg-slate-900 disabled:opacity-60"
                    >
                        {{ uploadingTemplate ? "Uploading..." : "Upload Template" }}
                    </button>
                </div>
            </div>

            <div class="mb-6 grid grid-cols-1 gap-3 md:grid-cols-3">
                <button
                    v-for="module in qmsModules"
                    :key="module.code"
                    type="button"
                    @click="selectModule(module.code)"
                    :disabled="loading || uploadingTemplate || savingField"
                    class="rounded-xl border px-4 py-3 text-left transition disabled:opacity-60"
                    :class="
                        selectedModule === module.code
                            ? 'border-slate-900 bg-slate-900 text-white shadow-sm'
                            : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'
                    "
                >
                    <span class="block text-sm font-semibold">
                        {{ module.name }}
                    </span>
                    <span
                        class="mt-1 block text-xs"
                        :class="
                            selectedModule === module.code
                                ? 'text-slate-200'
                                : 'text-slate-500'
                        "
                    >
                        {{ module.description }}
                    </span>
                </button>
            </div>

            <div v-if="loading" class="text-sm text-slate-500">
                Loading {{ selectedModule }} template settings...
            </div>

            <div v-else class="space-y-4">
                <div class="border rounded-xl p-4 bg-slate-50">
                    <p class="text-sm font-semibold text-slate-800">
                        Active {{ selectedModule }} Template
                    </p>
                    <p class="text-sm text-slate-500 mt-1">
                        {{ activeTemplate?.original_file_name || "No active template uploaded yet" }}
                    </p>
                </div>

                <div>
                    <p class="text-sm font-medium text-slate-700 mb-3">
                        Uploaded Templates
                    </p>

                    <div v-if="templateHistory.length" class="space-y-3">
                        <div
                            v-for="template in templateHistory"
                            :key="template.id"
                            class="border rounded-xl p-4 flex items-center justify-between gap-4"
                        >
                            <div>
                                <p class="text-sm font-semibold text-slate-800">
                                    {{ template.name }}
                                </p>
                                <p class="text-sm text-slate-500">
                                    {{ template.original_file_name }}
                                </p>
                                <p class="text-xs text-slate-400 mt-1">
                                    {{ template.is_active ? "Active template" : "Inactive template" }}
                                </p>
                            </div>

                            <button
                                v-if="!template.is_active"
                                type="button"
                                @click="activateTemplate(template.id)"
                                class="px-4 py-2 border rounded-lg text-sm hover:bg-slate-50"
                            >
                                Set Active
                            </button>

                            <span
                                v-else
                                class="px-3 py-1 text-xs rounded-full bg-emerald-100 text-emerald-700 font-medium"
                            >
                                Active
                            </span>
                        </div>
                    </div>

                    <div v-else class="text-sm text-slate-400">
                        No {{ selectedModule }} templates uploaded yet.
                    </div>
                </div>
            </div>
        </div>

        <!-- ADDITIONAL FIELDS -->
        <div class="mt-8 bg-white border border-slate-200 rounded-2xl p-6 shadow-sm">
            <div class="mb-4">
                <h3 class="text-lg font-semibold text-slate-900">
                    {{ selectedModule }} Additional Fields Setup
                </h3>
                <p class="text-sm text-slate-500 mt-1">
                    Add optional placeholders that must match the uploaded Word template
                </p>
            </div>

            <!-- CREATE NEW FIELD -->
            <div class="border rounded-xl p-4 mb-6">
                <p class="text-sm font-semibold text-slate-800 mb-4">
                    Add New Field
                </p>

                <div class="grid grid-cols-6 gap-4">
                    <div class="col-span-2">
                        <label class="text-sm text-slate-600">Label</label>
                        <input
                            v-model="newField.label"
                            placeholder="e.g. Office Code"
                            class="mt-1 w-full border rounded-lg px-3 py-2 text-sm"
                        />
                    </div>

                    <div>
                        <label class="text-sm text-slate-600">Placeholder Key</label>
                        <input
                            v-model="newField.field_key"
                            placeholder="e.g. officeCode"
                            class="mt-1 w-full border rounded-lg px-3 py-2 text-sm"
                        />
                    </div>

                    <div>
                        <label class="text-sm text-slate-600">Type</label>
                        <select
                            v-model="newField.field_type"
                            class="mt-1 w-full border rounded-lg px-3 py-2 text-sm"
                        >
                            <option value="text">Text</option>
                            <option value="textarea">Textarea</option>
                            <option value="date">Date</option>
                        </select>
                    </div>

                    <div>
                        <label class="text-sm text-slate-600">Sort Order</label>
                        <input
                            v-model.number="newField.sort_order"
                            type="number"
                            min="0"
                            class="mt-1 w-full border rounded-lg px-3 py-2 text-sm"
                        />
                    </div>

                    <div class="flex items-end">
                        <button
                            type="button"
                            @click="createField"
                            :disabled="savingField"
                            class="w-full px-4 py-2 bg-slate-800 text-white rounded-lg text-sm hover:bg-slate-900 disabled:opacity-60"
                        >
                            {{ savingField ? "Saving..." : "+ Add Field" }}
                        </button>
                    </div>
                </div>

                <div class="flex items-center gap-6 mt-4">
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" v-model="newField.is_required" />
                        Required
                    </label>

                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" v-model="newField.is_active" />
                        Active
                    </label>
                </div>
            </div>

            <!-- FIELD LIST -->
            <div v-if="loading" class="text-sm text-slate-500">
                Loading {{ selectedModule }} fields...
            </div>

            <div v-else-if="hasFields" class="space-y-4">
                <div
                    v-for="field in dynamicFields"
                    :key="field.id"
                    class="border rounded-xl p-4"
                >
                    <div class="grid grid-cols-6 gap-4">
                        <div class="col-span-2">
                            <label class="text-sm text-slate-600">Label</label>
                            <input
                                v-model="field.label"
                                :disabled="!field.isEditing"
                                class="mt-1 w-full border rounded-lg px-3 py-2 text-sm disabled:bg-slate-50"
                            />
                        </div>

                        <div>
                            <label class="text-sm text-slate-600">Placeholder Key</label>
                            <input
                                v-model="field.field_key"
                                :disabled="!field.isEditing"
                                class="mt-1 w-full border rounded-lg px-3 py-2 text-sm disabled:bg-slate-50"
                            />
                        </div>

                        <div>
                            <label class="text-sm text-slate-600">Type</label>
                            <select
                                v-model="field.field_type"
                                :disabled="!field.isEditing"
                                class="mt-1 w-full border rounded-lg px-3 py-2 text-sm disabled:bg-slate-50"
                            >
                                <option value="text">Text</option>
                                <option value="textarea">Textarea</option>
                                <option value="date">Date</option>
                            </select>
                        </div>

                        <div>
                            <label class="text-sm text-slate-600">Sort Order</label>
                            <input
                                v-model.number="field.sort_order"
                                type="number"
                                min="0"
                                :disabled="!field.isEditing"
                                class="mt-1 w-full border rounded-lg px-3 py-2 text-sm disabled:bg-slate-50"
                            />
                        </div>

                        <div class="flex items-end gap-2">
                            <button
                                v-if="!field.isEditing"
                                type="button"
                                @click="editField(field)"
                                class="px-4 py-2 border rounded-lg text-sm hover:bg-slate-50"
                            >
                                Edit
                            </button>

                            <template v-else>
                                <button
                                    type="button"
                                    @click="updateField(field)"
                                    :disabled="field.isSaving"
                                    class="px-4 py-2 bg-slate-800 text-white rounded-lg text-sm hover:bg-slate-900 disabled:opacity-60"
                                >
                                    {{ field.isSaving ? "Saving..." : "Save" }}
                                </button>

                                <button
                                    type="button"
                                    @click="cancelEditField(field)"
                                    class="px-4 py-2 border rounded-lg text-sm hover:bg-slate-50"
                                >
                                    Cancel
                                </button>
                            </template>

                            <button
                                type="button"
                                @click="deleteField(field)"
                                :disabled="field.isDeleting"
                                class="px-4 py-2 text-red-600 border border-red-200 rounded-lg text-sm hover:bg-red-50 disabled:opacity-60"
                            >
                                {{ field.isDeleting ? "Deleting..." : "Delete" }}
                            </button>
                        </div>
                    </div>

                    <div class="flex items-center gap-6 mt-4">
                        <label class="flex items-center gap-2 text-sm text-slate-700">
                            <input
                                type="checkbox"
                                v-model="field.is_required"
                                :disabled="!field.isEditing"
                            />
                            Required
                        </label>

                        <label class="flex items-center gap-2 text-sm text-slate-700">
                            <input
                                type="checkbox"
                                v-model="field.is_active"
                                :disabled="!field.isEditing"
                            />
                            Active
                        </label>
                    </div>
                </div>
            </div>

            <div v-else class="text-sm text-slate-400">
                No additional {{ selectedModule }} fields yet.
            </div>

            <div class="mt-4 text-xs text-amber-600">
                Placeholder key must match the Word template placeholder, for example:
                ${officeCode}
            </div>
        </div>

        <!-- REFRESH -->
        <div class="mt-10">
            <button
                type="button"
                @click="loadQmsTemplateSettings"
                class="px-6 py-2 bg-slate-800 text-white rounded-lg text-sm hover:bg-slate-900"
            >
                Refresh {{ selectedModule }} Settings
            </button>
        </div>
    </div>
</template>
