<script setup>
import { reactive } from "vue";

const form = reactive({
    first_name: "Maria",
    middle_name: "Reyes",
    last_name: "Dela Cruz",
    email: "mariareyes.delacruz@email.com",
    role: "Admin",
    position: "Admin Staff",
    department: "QMS(ISO)",
    office_location: "College Building",
    current_password: "••••••••••••••••••••",
    new_password: "••••••••••••••••••••",
});
</script>

<template>
    <div class="profile-wrap">
        <div class="section-head">
            <h2>Profile Settings</h2>
            <p>Update your profile settings and address</p>
        </div>

        <div class="form-grid">
            <!-- Row 1 -->
            <div class="field">
                <label>First Name:</label>
                <input v-model="form.first_name" type="text" />
            </div>

            <div class="field">
                <label>Middle Name:</label>
                <input v-model="form.middle_name" type="text" />
            </div>

            <div class="field">
                <label>Last Name:</label>
                <input v-model="form.last_name" type="text" />
            </div>

            <!-- Row 2 -->
            <div class="field">
                <label>Email:</label>
                <input v-model="form.email" type="email" />
            </div>

            <div class="field">
                <label>Role:</label>
                <input v-model="form.role" type="text" />
            </div>

            <div class="field">
                <label>Position:</label>
                <input v-model="form.position" type="text" />
            </div>

            <!-- Row 3 -->
            <div class="field">
                <label>Department:</label>
                <input v-model="form.department" type="text" />
            </div>

            <div class="field">
                <label>Office Location:</label>
                <input v-model="form.office_location" type="text" />
            </div>

            <div class="field">
                <label>Current Password:</label>
                <input v-model="form.current_password" type="text" />
            </div>
        </div>

        <div class="bottom-grid">
            <!-- Photo -->
            <div class="photo-block">
                <label class="photo-label">Your Photo:</label>

                <div class="photo-box">
                    <img
                        :src="'/images/profile-placeholder.png'"
                        alt="Profile Photo"
                        class="photo-preview"
                    />
                </div>

                <div class="photo-actions">
                    <button type="button" class="upload-btn">Upload</button>
                    <button type="button" class="cancel-btn">Cancel</button>
                </div>
            </div>

            <!-- Right side -->
            <div class="right-side">
                <div class="field new-pass-field">
                    <label>New Password:</label>
                    <input v-model="form.new_password" type="text" />
                </div>

                <div class="confirm-wrap">
                    <button type="button" class="confirm-btn">Confirm</button>
                </div>
            </div>
        </div>

        <div class="save-wrap">
            <button type="button" class="save-btn">Save</button>
        </div>
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
    color: #7a7a7a;
    outline: none;
    box-sizing: border-box;
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
    object-fit: contain;
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