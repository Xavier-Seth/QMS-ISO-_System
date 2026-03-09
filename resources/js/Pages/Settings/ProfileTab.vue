<script setup>
import { computed, ref } from "vue";
import { useForm, usePage } from "@inertiajs/vue3";

const page = usePage();

const user = computed(() => page.props.auth?.user ?? {});
const flash = computed(() => page.props.flash ?? {});

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
    const file = event.target.files[0];
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
        });
};
</script>

<template>
    <div class="profile-wrap">
        <div class="section-head">
            <h2>Profile Settings</h2>
            <p>Update your profile settings and address</p>
        </div>

        <div v-if="flash.success" class="alert success-alert">
            {{ flash.success }}
        </div>

        <div v-if="flash.error" class="alert error-alert">
            {{ flash.error }}
        </div>

        <form @submit.prevent="submit">
            <div class="form-grid">
                <div class="field">
                    <label>First Name:</label>
                    <input v-model="form.first_name" type="text" />
                    <p v-if="form.errors.first_name" class="error-text">{{ form.errors.first_name }}</p>
                </div>

                <div class="field">
                    <label>Middle Name:</label>
                    <input v-model="form.middle_name" type="text" />
                    <p v-if="form.errors.middle_name" class="error-text">{{ form.errors.middle_name }}</p>
                </div>

                <div class="field">
                    <label>Last Name:</label>
                    <input v-model="form.last_name" type="text" />
                    <p v-if="form.errors.last_name" class="error-text">{{ form.errors.last_name }}</p>
                </div>

                <div class="field">
                    <label>Email:</label>
                    <input v-model="form.email" type="email" />
                    <p v-if="form.errors.email" class="error-text">{{ form.errors.email }}</p>
                </div>

                <div class="field">
                    <label>Role:</label>
                    <input :value="form.role" type="text" disabled />
                </div>

                <div class="field">
                    <label>Position:</label>
                    <input v-model="form.position" type="text" />
                    <p v-if="form.errors.position" class="error-text">{{ form.errors.position }}</p>
                </div>

                <div class="field">
                    <label>Department:</label>
                    <input v-model="form.department" type="text" />
                    <p v-if="form.errors.department" class="error-text">{{ form.errors.department }}</p>
                </div>

                <div class="field">
                    <label>Office Location:</label>
                    <input v-model="form.office_location" type="text" />
                    <p v-if="form.errors.office_location" class="error-text">
                        {{ form.errors.office_location }}
                    </p>
                </div>

                <div class="field">
                    <label>Current Password:</label>
                    <input v-model="form.current_password" type="password" />
                    <p v-if="form.errors.current_password" class="error-text">
                        {{ form.errors.current_password }}
                    </p>
                </div>
            </div>

            <div class="bottom-grid">
                <div class="photo-block">
                    <label class="photo-label">Your Photo:</label>

                    <div class="photo-box">
                        <img
                            v-if="previewPhoto"
                            :src="previewPhoto"
                            alt="Profile Photo"
                            class="photo-preview"
                        />
                        <img
                            v-else
                            :src="'/images/profile-placeholder.png'"
                            alt="Profile Photo"
                            class="photo-preview"
                        />
                    </div>

                    <input
                        ref="fileInput"
                        type="file"
                        accept="image/*"
                        class="hidden-file"
                        @change="onPhotoChange"
                    />

                    <p v-if="form.errors.profile_photo" class="error-text photo-error">
                        {{ form.errors.profile_photo }}
                    </p>

                    <div class="photo-actions">
                        <button type="button" class="upload-btn" @click="choosePhoto">
                            Upload
                        </button>
                        <button type="button" class="cancel-btn" @click="cancelPhoto">
                            Cancel
                        </button>
                    </div>
                </div>

                <div class="right-side">
                    <div class="field new-pass-field">
                        <label>New Password:</label>
                        <input v-model="form.new_password" type="password" />
                        <p v-if="form.errors.new_password" class="error-text">
                            {{ form.errors.new_password }}
                        </p>
                    </div>

                    <div class="field new-pass-field confirm-field">
                        <label>Confirm New Password:</label>
                        <input v-model="form.new_password_confirmation" type="password" />
                    </div>

                    <div class="confirm-wrap">
                        <button type="submit" class="confirm-btn" :disabled="form.processing">
                            {{ form.processing ? "Saving..." : "Confirm" }}
                        </button>
                    </div>
                </div>
            </div>

            <div class="save-wrap">
                <button type="submit" class="save-btn" :disabled="form.processing">
                    {{ form.processing ? "Saving..." : "Save" }}
                </button>
            </div>
        </form>
    </div>
</template>

<style scoped>
.profile-wrap {
    padding-top: 0;
}

.section-head {
    border-bottom: 1px solid #bfc3c9;
    padding-bottom: 10px;
    margin-bottom: 26px;
    max-width: 875px;
}

.section-head h2 {
    margin: 0;
    font-size: 17px;
    font-weight: 600;
    color: #111111;
    line-height: 1.2;
}

.section-head p {
    margin: 4px 0 0;
    font-size: 14px;
    color: #666666;
    line-height: 1.3;
}

.alert {
    max-width: 985px;
    margin-bottom: 18px;
    padding: 12px 14px;
    border-radius: 6px;
    font-size: 14px;
}

.success-alert {
    background: #eaf7ee;
    border: 1px solid #9fd3ad;
    color: #246b38;
}

.error-alert {
    background: #fdecec;
    border: 1px solid #efb2b2;
    color: #a61b1b;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(210px, 1fr));
    column-gap: 115px;
    row-gap: 28px;
    max-width: 985px;
}

.field label {
    display: block;
    margin-bottom: 7px;
    font-size: 15px;
    font-weight: 600;
    color: #111111;
    line-height: 1.2;
}

.field input {
    width: 100%;
    height: 38px;
    border: 1px solid #8e8e8e;
    border-radius: 6px;
    background: transparent;
    padding: 0 14px;
    font-size: 16px;
    color: #444;
    outline: none;
    box-sizing: border-box;
}

.field input:disabled {
    background: #f1f3f5;
    color: #6b7280;
    cursor: not-allowed;
}

.error-text {
    margin: 6px 0 0;
    font-size: 13px;
    color: #d11a2a;
}

.bottom-grid {
    margin-top: 22px;
    display: grid;
    grid-template-columns: 1fr 1fr;
    column-gap: 160px;
    align-items: start;
    max-width: 985px;
}

.photo-block {
    width: 255px;
}

.photo-label {
    display: block;
    margin-bottom: 8px;
    font-size: 15px;
    font-weight: 600;
    color: #111111;
    line-height: 1.2;
}

.photo-box {
    width: 250px;
    height: 210px;
    border: 1px solid #8e8e8e;
    border-radius: 6px;
    background: transparent;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.photo-preview {
    width: 198px;
    height: 198px;
    object-fit: cover;
}

.photo-actions {
    display: flex;
    gap: 34px;
    margin-top: 14px;
}

.upload-btn,
.cancel-btn,
.confirm-btn,
.save-btn {
    height: 30px;
    min-width: 110px;
    border-radius: 5px;
    font-size: 14px;
    cursor: pointer;
    border: 1px solid #b4b4b4;
}

.upload-btn {
    background: #59a7f2;
    border-color: #59a7f2;
    color: #ffffff;
}

.cancel-btn {
    background: transparent;
    color: #707070;
}

.right-side {
    width: 100%;
    padding-top: 4px;
}

.new-pass-field {
    max-width: 252px;
    margin-left: auto;
}

.confirm-field {
    margin-top: 18px;
}

.confirm-wrap {
    max-width: 252px;
    margin-left: auto;
    display: flex;
    justify-content: flex-end;
    margin-top: 32px;
}

.confirm-btn {
    background: transparent;
    color: #707070;
}

.save-wrap {
    max-width: 985px;
    display: flex;
    justify-content: flex-end;
    margin-top: 16px;
}

.save-btn {
    background: #16233d;
    border-color: #16233d;
    color: #ffffff;
}

.hidden-file {
    display: none;
}

.photo-error {
    max-width: 250px;
}

@media (max-width: 1200px) {
    .form-grid {
        column-gap: 45px;
    }

    .bottom-grid {
        column-gap: 70px;
    }
}

@media (max-width: 980px) {
    .form-grid {
        grid-template-columns: 1fr;
        max-width: 520px;
    }

    .bottom-grid {
        grid-template-columns: 1fr;
        row-gap: 26px;
    }

    .right-side,
    .new-pass-field,
    .confirm-wrap {
        margin-left: 0;
        max-width: 320px;
    }

    .save-wrap {
        justify-content: flex-start;
    }
}
</style>