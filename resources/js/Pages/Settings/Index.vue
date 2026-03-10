<script setup>
import { ref, computed } from "vue";
import AdminLayout from "@/Layouts/AdminLayoutWithHeader.vue";
import ProfileTab from "./ProfileTab.vue";
import SystemTab from "./SystemTab.vue";
import BackupTab from "./BackupTab.vue";

const activeTab = ref("profile");

const tabIndex = computed(() => {
    if (activeTab.value === "profile") return 0;
    if (activeTab.value === "system") return 1;
    return 2;
});
</script>

<template>
    <AdminLayout>
        <div class="settings-shell">
            <div class="settings-page">
                <!-- PAGE HEADER -->
                <div class="settings-header">
                    <h1>Settings</h1>
                    <p>Manage your account settings and preferences.</p>
                </div>

                <!-- TABS -->
                <div class="settings-tabs-wrap">
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

                    <!-- animated indicator -->
                    <div
                        class="tab-indicator"
                        :style="{ transform: `translateX(${tabIndex * 100}%)` }"
                    ></div>
                </div>

                <!-- TAB CONTENT -->
                <div class="settings-content">
                    <Transition name="tab-slide" mode="out-in">
                        <div :key="activeTab" class="tab-panel">
                            <ProfileTab v-if="activeTab === 'profile'" />
                            <SystemTab v-else-if="activeTab === 'system'" />
                            <BackupTab v-else />
                        </div>
                    </Transition>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<style scoped>
.settings-shell {
    width: 100%;
    padding: 0 40px 40px;
    box-sizing: border-box;
}

.settings-page {
    margin-top: -6px;
    background: transparent;
}

.settings-header {
    margin: 0 0 12px 0;
}

.settings-header h1 {
    font-size: 22px;
    font-weight: 700;
    line-height: 1.2;
    color: #0f172a;
    margin: 0;
}

.settings-header p {
    margin: 6px 0 0;
    font-size: 15px;
    line-height: 1.4;
    color: #64748b;
}

.settings-tabs-wrap {
    position: relative;
    margin-bottom: 16px;
}

.settings-tabs {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    background: #dfdfdf;
    border-radius: 4px;
    overflow: hidden;
}

.tab-btn {
    border: none;
    background: transparent;
    padding: 10px 12px 14px;
    font-size: 15px;
    color: #4a4a4a;
    cursor: pointer;
    transition: color 0.25s ease, background 0.25s ease;
    position: relative;
    z-index: 2;
}

.tab-btn:hover {
    background: rgba(255, 255, 255, 0.15);
}

.tab-btn.active {
    color: #222222;
    font-weight: 500;
}

.tab-indicator {
    position: absolute;
    left: 0;
    bottom: 0;
    width: calc(100% / 3);
    height: 2px;
    background: #7f7f7f;
    transition: transform 0.3s ease;
    z-index: 3;
}

.settings-content {
    background: transparent;
    overflow: hidden;
}

.tab-panel {
    width: 100%;
}

/* tab content animation */
.tab-slide-enter-active,
.tab-slide-leave-active {
    transition: all 0.28s ease;
}

.tab-slide-enter-from {
    opacity: 0;
    transform: translateX(18px);
}

.tab-slide-leave-to {
    opacity: 0;
    transform: translateX(-18px);
}

.tab-slide-enter-to,
.tab-slide-leave-from {
    opacity: 1;
    transform: translateX(0);
}
</style>