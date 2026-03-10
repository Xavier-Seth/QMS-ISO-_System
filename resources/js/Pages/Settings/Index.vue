<script setup>
import { ref, computed, watch } from "vue";
import { usePage } from "@inertiajs/vue3";
import AdminLayout from "@/Layouts/AdminLayoutWithHeader.vue";
import ProfileTab from "./ProfileTab.vue";
import SystemTab from "./SystemTab.vue";
import BackupTab from "./BackupTab.vue";

const page = usePage();

const user = computed(() => page.props.auth?.user ?? null);
const isAdmin = computed(() => user.value?.role === "admin");

const activeTab = ref("profile");

const availableTabs = computed(() => {
    return isAdmin.value
        ? ["profile", "system", "backup"]
        : ["profile"];
});

watch(
    isAdmin,
    (value) => {
        if (!value && activeTab.value !== "profile") {
            activeTab.value = "profile";
        }
    },
    { immediate: true }
);

const tabIndex = computed(() => {
    return availableTabs.value.indexOf(activeTab.value);
});

const tabGridClass = computed(() => {
    return isAdmin.value ? "grid-cols-3" : "grid-cols-1";
});

const indicatorWidth = computed(() => {
    return `${100 / availableTabs.value.length}%`;
});

const indicatorTransform = computed(() => {
    return `translateX(${tabIndex.value * 100}%)`;
});
</script>

<template>
    <AdminLayout>
        <div class="w-full box-border px-10 pb-10">
            <div class="-mt-1.5 bg-transparent">
                <!-- PAGE HEADER -->
                <div class="mb-3">
                    <h1 class="m-0 text-[22px] font-bold leading-[1.2] text-slate-900">
                        Settings
                    </h1>
                    <p class="mt-1.5 text-[15px] leading-[1.4] text-slate-500">
                        Manage your account settings and preferences.
                    </p>
                </div>

                <!-- TABS -->
                <div class="relative mb-4">
                    <div
                        class="grid overflow-hidden rounded bg-[#dfdfdf]"
                        :class="tabGridClass"
                    >
                        <button
                            type="button"
                            class="relative z-[2] border-none bg-transparent px-3 py-2.5 pb-3.5 text-[15px] text-[#4a4a4a] transition hover:bg-white/15"
                            :class="{ 'font-medium text-[#222222]': activeTab === 'profile' }"
                            @click="activeTab = 'profile'"
                        >
                            Profile
                        </button>

                        <button
                            v-if="isAdmin"
                            type="button"
                            class="relative z-[2] border-none bg-transparent px-3 py-2.5 pb-3.5 text-[15px] text-[#4a4a4a] transition hover:bg-white/15"
                            :class="{ 'font-medium text-[#222222]': activeTab === 'system' }"
                            @click="activeTab = 'system'"
                        >
                            System
                        </button>

                        <button
                            v-if="isAdmin"
                            type="button"
                            class="relative z-[2] border-none bg-transparent px-3 py-2.5 pb-3.5 text-[15px] text-[#4a4a4a] transition hover:bg-white/15"
                            :class="{ 'font-medium text-[#222222]': activeTab === 'backup' }"
                            @click="activeTab = 'backup'"
                        >
                            Backup
                        </button>
                    </div>

                    <!-- animated indicator -->
                    <div
                        class="absolute bottom-0 left-0 z-[3] h-0.5 bg-[#7f7f7f] transition duration-300 ease-out"
                        :style="{
                            width: indicatorWidth,
                            transform: indicatorTransform,
                        }"
                    ></div>
                </div>

                <!-- TAB CONTENT -->
                <div class="overflow-hidden bg-transparent">
                    <Transition name="tab-slide" mode="out-in">
                        <div :key="activeTab" class="w-full">
                            <ProfileTab v-if="activeTab === 'profile'" />
                            <SystemTab v-else-if="activeTab === 'system' && isAdmin" />
                            <BackupTab v-else-if="activeTab === 'backup' && isAdmin" />
                        </div>
                    </Transition>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<style scoped>
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