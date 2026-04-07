<script setup>
import { ref, computed, watch } from "vue";
import { Link, usePage } from "@inertiajs/vue3";

const page = usePage();

const currentPath = computed(() => {
    const url = page.url || "/";
    const path = url.split("?")[0];
    return path !== "/" ? path.replace(/\/+$/, "") : "/";
});

const user = computed(() => page.props.auth?.user ?? null);
const isAdmin = computed(() => user.value?.role === "admin");

const openCreateDocuments = ref(false);
const openManual = ref(false);
const openDocuments = ref(false);

const isActive = (href) => currentPath.value === href;

const isStartsWith = (prefix) =>
    currentPath.value === prefix || currentPath.value.startsWith(prefix + "/");

watch(
    currentPath,
    () => {
        openManual.value = isStartsWith("/manual");
        openCreateDocuments.value =
            isStartsWith("/dcr") ||
            isStartsWith("/ofi") ||
            isStartsWith("/ofi-form") ||
            isStartsWith("/car");
        openDocuments.value =
            isStartsWith("/documents") || isStartsWith("/performance");
    },
    { immediate: true }
);

const navItemClass = (active = false) => [
    "group flex w-full items-center gap-3 rounded-[10px] px-[14px] py-[11px] text-left text-sm tracking-[0.01em] transition",
    active
        ? "bg-indigo-500/15 text-white font-medium"
        : "text-slate-400 hover:bg-slate-400/10 hover:text-slate-200",
];

const iconClass = (active = false) => [
    "h-[18px] w-[18px] shrink-0 transition",
    active ? "stroke-indigo-400" : "stroke-slate-500 group-hover:stroke-slate-400",
];

const dropdownItemClass = (active = false) => [
    "block rounded-lg px-[14px] py-[9px] pl-11 text-[13px] tracking-[0.01em] transition",
    active
        ? "bg-indigo-500/10 text-indigo-300"
        : "text-slate-500 hover:bg-slate-400/5 hover:text-slate-300",
];
</script>

<template>
    <aside
        class="fixed left-0 top-0 z-[100] flex h-screen w-[280px] flex-col overflow-hidden border-r border-white/5 bg-[linear-gradient(180deg,#0d1424_0%,#111827_50%,#0f1e2e_100%)] font-sans text-slate-200"
    >
        <!-- Logo -->
        <div class="flex shrink-0 items-center gap-3.5 px-6 pb-6 pt-7">
            <img
                :src="`/images/QMS_logo.png`"
                alt="QMS Logo"
                class="h-[52px] w-[52px] shrink-0 object-contain drop-shadow-[0_2px_8px_rgba(0,0,0,0.4)]"
            />
            <div class="flex flex-col text-[13.5px] font-medium leading-[1.4] tracking-[0.01em] text-slate-300">
                <span>Quality Management</span>
                <span>System</span>
            </div>
        </div>

        <div class="mx-5 mb-5 h-px shrink-0 bg-gradient-to-r from-transparent via-slate-400/15 to-transparent"></div>

        <nav
            class="flex min-h-0 flex-1 flex-col gap-0.5 overflow-y-auto overflow-x-hidden px-3 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
        >
            <!-- Dashboard -->
            <Link
                href="/dashboard"
                :class="navItemClass(isActive('/dashboard'))"
            >
                <svg
                    :class="iconClass(isActive('/dashboard'))"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                >
                    <rect x="3" y="3" width="7" height="7" rx="1.5" />
                    <rect x="14" y="3" width="7" height="7" rx="1.5" />
                    <rect x="3" y="14" width="7" height="7" rx="1.5" />
                    <rect x="14" y="14" width="7" height="7" rx="1.5" />
                </svg>
                <span>Dashboard</span>
            </Link>

            <!-- Create Documents -->
            <div class="flex shrink-0 flex-col gap-0.5">
                <button
                    type="button"
                    @click="openCreateDocuments = !openCreateDocuments"
                    :class="navItemClass(
                        openCreateDocuments ||
                        isStartsWith('/dcr') ||
                        isStartsWith('/ofi') ||
                        isStartsWith('/ofi-form') ||
                        isStartsWith('/car')
                    )"
                    class="justify-between border-0 bg-transparent"
                >
                    <div class="flex items-center gap-3">
                        <svg
                            :class="iconClass(
                                openCreateDocuments ||
                                isStartsWith('/dcr') ||
                                isStartsWith('/ofi') ||
                                isStartsWith('/ofi-form') ||
                                isStartsWith('/car')
                            )"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                        >
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                            <polyline points="14 2 14 8 20 8" />
                            <line x1="12" y1="18" x2="12" y2="12" />
                            <line x1="9" y1="15" x2="15" y2="15" />
                        </svg>
                        <span>Create Documents</span>
                    </div>

                    <svg
                        class="h-[15px] w-[15px] shrink-0 stroke-slate-500 transition duration-200"
                        :class="{ 'rotate-180': openCreateDocuments }"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                    >
                        <polyline points="6 9 12 15 18 9" />
                    </svg>
                </button>

                <div v-if="openCreateDocuments" class="flex flex-col gap-px pb-1 pt-0.5">
                    <Link
                        href="/dcr"
                        :class="dropdownItemClass(isActive('/dcr'))"
                    >
                        Create DCR Forms
                    </Link>

                    <Link
                        href="/ofi-form"
                        :class="dropdownItemClass(isActive('/ofi-form'))"
                    >
                        Create OFI Forms
                    </Link>

                    <Link
                        href="/car"
                        :class="dropdownItemClass(isActive('/car'))"
                    >
                        Create CAR Forms
                    </Link>
                </div>
            </div>

            <!-- Documents - admin only -->
            <div v-if="isAdmin" class="flex shrink-0 flex-col gap-0.5">
                <button
                    type="button"
                    @click="openDocuments = !openDocuments"
                    :class="navItemClass(openDocuments || isStartsWith('/documents') || isStartsWith('/performance'))"
                    class="justify-between border-0 bg-transparent"
                >
                    <div class="flex items-center gap-3">
                        <svg
                            :class="iconClass(openDocuments || isStartsWith('/documents') || isStartsWith('/performance'))"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                        >
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                            <polyline points="14 2 14 8 20 8" />
                            <line x1="16" y1="13" x2="8" y2="13" />
                            <line x1="16" y1="17" x2="8" y2="17" />
                        </svg>
                        <span>Documents</span>
                    </div>

                    <svg
                        class="h-[15px] w-[15px] shrink-0 stroke-slate-500 transition duration-200"
                        :class="{ 'rotate-180': openDocuments }"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                    >
                        <polyline points="6 9 12 15 18 9" />
                    </svg>
                </button>

                <div v-if="openDocuments" class="flex flex-col gap-px pb-1 pt-0.5">
                    <Link
                        href="/documents?series=F-QMS"
                        :class="dropdownItemClass(page.url.includes('series=F-QMS'))"
                    >
                        F-QMS
                    </Link>

                    <Link
                        href="/documents?series=R-QMS"
                        :class="dropdownItemClass(page.url.includes('series=R-QMS'))"
                    >
                        R-QMS
                    </Link>

                    <Link
                        href="/performance"
                        :class="dropdownItemClass(isStartsWith('/performance'))"
                    >
                        Performance Commitment and Review Forms
                    </Link>
                </div>
            </div>

            <!-- Manual -->
            <div class="flex shrink-0 flex-col gap-0.5">
                <button
                    type="button"
                    @click="openManual = !openManual"
                    :class="navItemClass(openManual || isStartsWith('/manual'))"
                    class="justify-between border-0 bg-transparent"
                >
                    <div class="flex items-center gap-3">
                        <svg
                            :class="iconClass(openManual || isStartsWith('/manual'))"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                        >
                            <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20" />
                            <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z" />
                            <line x1="8" y1="7" x2="18" y2="7" />
                            <line x1="8" y1="11" x2="18" y2="11" />
                        </svg>
                        <span>Manual</span>
                    </div>

                    <svg
                        class="h-[15px] w-[15px] shrink-0 stroke-slate-500 transition duration-200"
                        :class="{ 'rotate-180': openManual }"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                    >
                        <polyline points="6 9 12 15 18 9" />
                    </svg>
                </button>

                <div v-if="openManual" class="flex flex-col gap-px pb-1 pt-0.5">
                    <Link href="/manual/asm" :class="dropdownItemClass(isActive('/manual/asm'))">ASM</Link>
                    <Link href="/manual/qsm" :class="dropdownItemClass(isActive('/manual/qsm'))">QSM</Link>
                    <Link href="/manual/hrm" :class="dropdownItemClass(isActive('/manual/hrm'))">HRM</Link>
                    <Link href="/manual/riem" :class="dropdownItemClass(isActive('/manual/riem'))">RIEM</Link>
                    <Link href="/manual/rem" :class="dropdownItemClass(isActive('/manual/rem'))">REM</Link>
                </div>
            </div>

            <!-- Upload - admin only -->
            <Link
                v-if="isAdmin"
                href="/upload"
                :class="navItemClass(isActive('/upload'))"
            >
                <svg
                    :class="iconClass(isActive('/upload'))"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                >
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                    <polyline points="17 8 12 3 7 8" />
                    <line x1="12" y1="3" x2="12" y2="15" />
                </svg>
                <span>Upload</span>
            </Link>

            <!-- Admin Inbox -->
            <Link
                v-if="isAdmin"
                href="/inbox/ofi"
                :class="navItemClass(isActive('/inbox/ofi'))"
            >
                <svg
                    :class="iconClass(isActive('/inbox/ofi'))"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                >
                    <polyline points="22 12 16 12 14 15 10 15 8 12 2 12" />
                    <path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z" />
                </svg>
                <span>Inbox</span>
            </Link>

            <!-- My OFIs - non-admin -->
            <Link
                v-else
                href="/ofi/my-records"
                :class="navItemClass(isActive('/ofi/my-records'))"
            >
                <svg
                    :class="iconClass(isActive('/ofi/my-records'))"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                >
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                    <polyline points="14 2 14 8 20 8" />
                    <line x1="8" y1="13" x2="16" y2="13" />
                    <line x1="8" y1="17" x2="13" y2="17" />
                </svg>
                <span>My OFIs</span>
            </Link>

            <!-- Users - admin only -->
            <Link
                v-if="isAdmin"
                href="/users"
                :class="navItemClass(isActive('/users'))"
            >
                <svg
                    :class="iconClass(isActive('/users'))"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                >
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                    <circle cx="12" cy="7" r="4" />
                </svg>
                <span>Users</span>
            </Link>

            <!-- Logs - admin only -->
            <Link
                v-if="isAdmin"
                href="/logs"
                :class="navItemClass(isActive('/logs'))"
            >
                <svg
                    :class="iconClass(isActive('/logs'))"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                >
                    <circle cx="12" cy="12" r="10" />
                    <polyline points="12 6 12 12 16 14" />
                </svg>
                <span>Logs</span>
            </Link>

            <!-- Settings -->
            <Link
                href="/settings"
                :class="navItemClass(isActive('/settings'))"
            >
                <svg
                    :class="iconClass(isActive('/settings'))"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                >
                    <circle cx="12" cy="12" r="3" />
                    <path
                        d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"
                    />
                </svg>
                <span>Settings</span>
            </Link>
        </nav>

        <!-- Logout -->
        <div class="shrink-0 border-t border-slate-400/10 px-3 py-4">
            <Link
                href="/logout"
                method="post"
                as="button"
                class="flex w-full items-center gap-3 rounded-[10px] px-[14px] py-[11px] text-sm tracking-[0.01em] text-slate-500 transition hover:bg-red-500/10 hover:text-red-400"
            >
                <svg
                    class="h-[18px] w-[18px] shrink-0 stroke-current transition"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke-width="1.8"
                >
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                    <polyline points="16 17 21 12 16 7" />
                    <line x1="21" y1="12" x2="9" y2="12" />
                </svg>
                <span>Log out</span>
            </Link>
        </div>
    </aside>
</template>