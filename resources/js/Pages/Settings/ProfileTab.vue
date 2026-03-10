<script setup>
import { computed, ref } from "vue";
import { useForm, usePage } from "@inertiajs/vue3";
import { useLoadingOverlay } from "@/Composables/useLoadingOverlay";
import { useToast } from "@/Composables/useToast";

const page = usePage();
const loading = useLoadingOverlay();
const toast = useToast();

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
                toast.success("Your profile has been updated successfully.");
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
                        <input :value="form.role" type="text" disabled :class="inputClass" />
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
                        <input v-model="form.current_password" type="password" :class="inputClass" />
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
                            <input v-model="form.new_password" type="password" :class="inputClass" />
                            <p v-if="form.errors.new_password" :class="errorClass">
                                {{ form.errors.new_password }}
                            </p>
                        </div>

                        <div class="mt-5">
                            <label :class="labelClass">Confirm New Password:</label>
                            <input
                                v-model="form.new_password_confirmation"
                                type="password"
                                :class="inputClass"
                            />
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