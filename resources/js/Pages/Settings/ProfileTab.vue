<script setup>
import { computed, ref } from "vue";
import { useForm, usePage } from "@inertiajs/vue3";
import { useLoadingOverlay } from "@/Composables/useLoadingOverlay";
import { useToast } from "@/Composables/useToast";
import { useRoleFormatter } from "@/Composables/useRoleFormatter";

const page = usePage();
const loading = useLoadingOverlay();
const toast = useToast();
const { formatRole } = useRoleFormatter();

const user = computed(() => page.props.auth?.user ?? {});

const splitName = (fullName = "") => {
    const parts = fullName.trim().split(/\s+/).filter(Boolean);

    if (parts.length === 0) {
        return {
            first_name: "",
            middle_name: "",
            last_name: "",
        };
    }

    if (parts.length === 1) {
        return {
            first_name: parts[0],
            middle_name: "",
            last_name: "",
        };
    }

    if (parts.length === 2) {
        return {
            first_name: parts[0],
            middle_name: "",
            last_name: parts[1],
        };
    }

    return {
        first_name: parts[0],
        middle_name: parts.slice(1, -1).join(" "),
        last_name: parts[parts.length - 1],
    };
};

const nameParts = splitName(user.value.name ?? "");

const previewPhoto = ref(user.value.profile_photo ?? null);
const fileInput = ref(null);
const showCurrentPassword = ref(false);
const showNewPassword = ref(false);
const showConfirmPassword = ref(false);

const form = useForm({
    first_name: nameParts.first_name,
    middle_name: nameParts.middle_name,
    last_name: nameParts.last_name,
    email: user.value.email ?? "",
    role: user.value.role ?? "",
    position: user.value.position ?? "",
    department: user.value.department ?? "",
    office_location: user.value.office_location ?? "",
    current_password: "",
    new_password: "",
    new_password_confirmation: "",
    profile_photo: null,
});

const choosePhoto = () => {
    fileInput.value?.click();
};

const onPhotoChange = (event) => {
    const file = event.target.files?.[0];
    if (!file) return;

    form.profile_photo = file;
    previewPhoto.value = URL.createObjectURL(file);
};

const cancelPhoto = () => {
    form.profile_photo = null;
    previewPhoto.value = user.value.profile_photo ?? null;

    if (fileInput.value) {
        fileInput.value.value = "";
    }
};

const submit = () => {
    loading.open("Saving profile...");

    form
        .transform((data) => ({
            ...data,
            name: [data.first_name, data.middle_name, data.last_name]
                .filter((value) => value && value.trim() !== "")
                .join(" "),
        }))
        .post("/settings/profile", {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                form.reset("current_password", "new_password", "new_password_confirmation");
            },
            onError: () => {
                toast.error("Profile update failed. Please check the form and try again.");
            },
            onFinish: () => {
                loading.close();
            },
        });
};

const inputClass =
    "w-full h-11 rounded-lg border border-slate-300 bg-white px-3 text-[15px] text-slate-700 outline-none transition focus:border-slate-400 focus:ring-2 focus:ring-slate-200 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-500";

const labelClass =
    "mb-2 block text-[15px] font-semibold leading-tight text-slate-900";

const errorClass =
    "mt-1.5 text-[13px] text-rose-600";
</script>

<template>
    <div class="pt-0">
        <div class="mx-auto w-full max-w-6xl">
            <!-- Header -->
            <div class="mb-6 border-b border-slate-300 pb-3">
                <h2 class="text-[18px] font-semibold leading-tight text-slate-900">
                    Profile Settings
                </h2>
                <p class="mt-1 text-sm leading-snug text-slate-500">
                    Update your profile settings and address
                </p>
            </div>

            <form @submit.prevent="submit" class="space-y-8">
                <!-- Main fields -->
                <div class="grid grid-cols-1 gap-x-8 gap-y-6 md:grid-cols-2 xl:grid-cols-3">
                    <div>
                        <label :class="labelClass">First Name:</label>
                        <input v-model="form.first_name" type="text" :class="inputClass" />
                        <p v-if="form.errors.first_name" :class="errorClass">
                            {{ form.errors.first_name }}
                        </p>
                    </div>

                    <div>
                        <label :class="labelClass">Middle Name:</label>
                        <input v-model="form.middle_name" type="text" :class="inputClass" />
                        <p v-if="form.errors.middle_name" :class="errorClass">
                            {{ form.errors.middle_name }}
                        </p>
                    </div>

                    <div>
                        <label :class="labelClass">Last Name:</label>
                        <input v-model="form.last_name" type="text" :class="inputClass" />
                        <p v-if="form.errors.last_name" :class="errorClass">
                            {{ form.errors.last_name }}
                        </p>
                    </div>

                    <div>
                        <label :class="labelClass">Email:</label>
                        <input v-model="form.email" type="email" :class="inputClass" />
                        <p v-if="form.errors.email" :class="errorClass">
                            {{ form.errors.email }}
                        </p>
                    </div>

                    <div>
                        <label :class="labelClass">Role:</label>
                        <input :value="formatRole(form.role)" type="text" disabled :class="inputClass" />
                    </div>

                    <div>
                        <label :class="labelClass">Position:</label>
                        <input v-model="form.position" type="text" :class="inputClass" />
                        <p v-if="form.errors.position" :class="errorClass">
                            {{ form.errors.position }}
                        </p>
                    </div>

                    <div>
                        <label :class="labelClass">Department:</label>
                        <input v-model="form.department" type="text" :class="inputClass" />
                        <p v-if="form.errors.department" :class="errorClass">
                            {{ form.errors.department }}
                        </p>
                    </div>

                    <div>
                        <label :class="labelClass">Office Location:</label>
                        <input v-model="form.office_location" type="text" :class="inputClass" />
                        <p v-if="form.errors.office_location" :class="errorClass">
                            {{ form.errors.office_location }}
                        </p>
                    </div>

                    <div>
                        <label :class="labelClass">Current Password:</label>
                        <div class="relative">
                            <input v-model="form.current_password" :type="showCurrentPassword ? 'text' : 'password'" :class="inputClass" class="pr-10" />
                            <button
                                type="button"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                                @click="showCurrentPassword = !showCurrentPassword"
                            >
                                <svg v-if="!showCurrentPassword" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-4 w-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.644C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .638C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.964-7.178z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-4 w-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.584 10.587A2.25 2.25 0 0013.5 13.5" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.88 5.09A9.953 9.953 0 0112 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .638a11.983 11.983 0 01-3.12 4.568M6.228 6.228A11.965 11.965 0 002.037 11.68a1.012 1.012 0 000 .644C3.423 16.493 7.36 19.5 12 19.5a9.95 9.95 0 005.205-1.462" />
                                </svg>
                            </button>
                        </div>
                        <p v-if="form.errors.current_password" :class="errorClass">
                            {{ form.errors.current_password }}
                        </p>
                    </div>
                </div>

                <!-- Bottom section -->
                <div class="grid grid-cols-1 gap-8 lg:grid-cols-[300px,minmax(0,420px)] lg:justify-between">
                    <!-- Photo -->
                    <div class="w-full">
                        <label class="mb-2 block text-[15px] font-semibold leading-tight text-slate-900">
                            Your Photo:
                        </label>

                        <div class="flex h-[220px] w-full max-w-[260px] items-center justify-center overflow-hidden rounded-lg border border-slate-300 bg-white">
                            <img
                                v-if="previewPhoto"
                                :src="previewPhoto"
                                alt="Profile Photo"
                                class="h-[198px] w-[198px] object-cover"
                            />
                            <img
                                v-else
                                :src="'/images/profile-placeholder.png'"
                                alt="Profile Photo"
                                class="h-[198px] w-[198px] object-cover"
                            />
                        </div>

                        <input
                            ref="fileInput"
                            type="file"
                            accept="image/*"
                            class="hidden"
                            @change="onPhotoChange"
                        />

                        <p
                            v-if="form.errors.profile_photo"
                            class="mt-2 max-w-[260px] text-[13px] text-rose-600"
                        >
                            {{ form.errors.profile_photo }}
                        </p>

                        <div class="mt-4 flex flex-wrap gap-3">
                            <button
                                type="button"
                                @click="choosePhoto"
                                class="inline-flex h-10 min-w-[110px] items-center justify-center rounded-lg border border-sky-500 bg-sky-500 px-4 text-sm text-white transition hover:bg-sky-600"
                            >
                                Upload
                            </button>

                            <button
                                type="button"
                                @click="cancelPhoto"
                                class="inline-flex h-10 min-w-[110px] items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-sm text-slate-600 transition hover:bg-slate-50"
                            >
                                Cancel
                            </button>
                        </div>
                    </div>

                    <!-- Password update -->
                    <div class="w-full max-w-[420px]">
                        <div>
                            <label :class="labelClass">New Password:</label>
                            <div class="relative">
                                <input v-model="form.new_password" :type="showNewPassword ? 'text' : 'password'" :class="inputClass" class="pr-10" />
                                <button
                                    type="button"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                                    @click="showNewPassword = !showNewPassword"
                                >
                                    <svg v-if="!showNewPassword" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-4 w-4">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.644C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .638C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.964-7.178z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-4 w-4">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.584 10.587A2.25 2.25 0 0013.5 13.5" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.88 5.09A9.953 9.953 0 0112 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .638a11.983 11.983 0 01-3.12 4.568M6.228 6.228A11.965 11.965 0 002.037 11.68a1.012 1.012 0 000 .644C3.423 16.493 7.36 19.5 12 19.5a9.95 9.95 0 005.205-1.462" />
                                    </svg>
                                </button>
                            </div>
                            <p v-if="form.errors.new_password" :class="errorClass">
                                {{ form.errors.new_password }}
                            </p>
                        </div>

                        <div class="mt-5">
                            <label :class="labelClass">Confirm New Password:</label>
                            <div class="relative">
                                <input
                                    v-model="form.new_password_confirmation"
                                    :type="showConfirmPassword ? 'text' : 'password'"
                                    :class="inputClass"
                                    class="pr-10"
                                />
                                <button
                                    type="button"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                                    @click="showConfirmPassword = !showConfirmPassword"
                                >
                                    <svg v-if="!showConfirmPassword" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-4 w-4">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.644C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .638C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.964-7.178z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-4 w-4">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.584 10.587A2.25 2.25 0 0013.5 13.5" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.88 5.09A9.953 9.953 0 0112 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .638a11.983 11.983 0 01-3.12 4.568M6.228 6.228A11.965 11.965 0 002.037 11.68a1.012 1.012 0 000 .644C3.423 16.493 7.36 19.5 12 19.5a9.95 9.95 0 005.205-1.462" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div class="mt-6 flex justify-start">
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="inline-flex h-10 min-w-[120px] items-center justify-center rounded-lg border border-slate-900 bg-slate-900 px-4 text-sm text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                {{ form.processing ? "Saving..." : "Save Changes" }}
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</template>