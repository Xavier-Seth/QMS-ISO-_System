<script setup>
import { ref } from "vue";
import AdminLayout from "@/Layouts/AdminLayoutWithHeader.vue";
import ProfileTab from "./ProfileTab.vue";
import SystemTab from "./SystemTab.vue";
import BackupTab from "./BackupTab.vue";

const activeTab = ref("profile");
</script>

<template>
    <AdminLayout>
        <div class="settings-page">
            <!-- PAGE HEADER -->
            <div class="settings-header">
                <h1>Settings</h1>
                <p>Manage your account settings and preferences.</p>
            </div>

            <!-- TABS -->
            <div class="settings-tabs">
                <button
                    type="button"
                    class="tab-btn"
                    :class="{ active: activeTab === 'profile' }"
                    @click="activeTab = 'profile'"
                >
                    Profile
                </button>

                <button
                    type="button"
                    class="tab-btn"
                    :class="{ active: activeTab === 'system' }"
                    @click="activeTab = 'system'"
                >
                    System
                </button>

                <button
                    type="button"
                    class="tab-btn"
                    :class="{ active: activeTab === 'backup' }"
                    @click="activeTab = 'backup'"
                >
                    Backup
                </button>
            </div>

            <div class="settings-content">
                <ProfileTab v-if="activeTab === 'profile'" />
                <SystemTab v-else-if="activeTab === 'system'" />
                <BackupTab v-else />
            </div>
        </div>
    </AdminLayout>
</template>

<style scoped>
.settings-page {
    padding: 8px 40px 40px 40px;
    background: transparent;
}

.settings-header {
    margin-bottom: 10px;
}

.settings-header h1 {
    font-size: 22px;
    font-weight: 700;
    color: #0f172a;
    margin: 0;
}

.settings-header p {
    margin: 4px 0 0;
    font-size: 15px;
    color: #64748b;
}

.settings-tabs {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    background: #dfdfdf;
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 16px;
}

.tab-btn {
    border: none;
    background: transparent;
    padding: 10px 12px;
    font-size: 15px;
    color: #4a4a4a;
    cursor: pointer;
    border-bottom: 1px solid transparent;
}

.tab-btn.active {
    border-bottom: 1px solid #7f7f7f;
    color: #222222;
}

.settings-content {
    background: transparent;
}
</style>