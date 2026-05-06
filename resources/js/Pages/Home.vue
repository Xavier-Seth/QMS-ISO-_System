<script setup>
import { Link } from '@inertiajs/vue3'
import lnuLogo from '../images/LNU_logo.png'

const modules = [
    {
        key: 'dcr',
        abbr: 'DCR',
        label: 'Document Change Request',
        description: 'Submit and track requests for changes to controlled documents.',
        href: '/dcr',
    },
    {
        key: 'ofi',
        abbr: 'OFI',
        label: 'Opportunities for Improvement',
        description: 'Identify and document opportunities to improve quality processes.',
        href: '/ofi-form',
    },
    {
        key: 'car',
        abbr: 'CAR',
        label: 'Corrective Action Request',
        description: 'Record and resolve nonconformities with corrective actions.',
        href: '/car',
    },
]
</script>

<template>
    <div class="flex min-h-screen flex-col bg-[#f4f5f7] font-sans">
        <!-- HEADER -->
        <header class="relative border-b-[6px] border-[#C9A84C] bg-[#151b2b] text-white">
            <!-- LNU affiliation — top-right corner -->
            <div class="absolute right-6 top-4 flex flex-col items-center gap-1 opacity-80">
                <img :src="lnuLogo" alt="Leyte Normal University" class="h-10 w-10 object-contain" />
                <span class="max-w-[72px] text-center text-[10px] leading-tight tracking-wide text-slate-300">
                    Leyte Normal University
                </span>
            </div>

            <div class="mx-auto max-w-6xl px-6 py-14 md:py-16">
                <!-- Logo + Title -->
                <div class="flex flex-col items-center justify-center gap-6 md:flex-row md:gap-8">
                    <img
                        :src="'/images/QMS_Logo.png'"
                        alt="QMS Logo"
                        class="h-28 w-28 shrink-0 object-contain md:h-36 md:w-36"
                    />

                    <div class="text-center md:text-left">
                        <h1 class="text-2xl font-bold leading-tight tracking-wide md:text-4xl">
                            Quality Management System (ISO)
                        </h1>
                        <h2 class="text-2xl font-bold leading-tight tracking-wide md:text-4xl">
                            Archiving
                        </h2>
                        <p class="mt-4 text-sm tracking-wide text-slate-300 md:text-base">
                            Secure, Organized, and Reliable
                        </p>
                    </div>
                </div>

                <!-- CTA Buttons -->
                <div class="mt-10 flex flex-col items-center justify-center gap-4 sm:flex-row sm:gap-10">
                    <Link
                        href="/login"
                        class="inline-flex h-11 w-full items-center justify-center rounded-lg bg-white text-sm font-semibold text-gray-900 shadow-sm transition hover:bg-gray-100 sm:w-44"
                    >
                        Login
                    </Link>

                    <Link
                        href="/dcr"
                        class="inline-flex h-11 w-full items-center justify-center rounded-lg bg-[#3B82F6] text-sm font-semibold text-white shadow-sm transition hover:bg-blue-600 sm:w-44"
                    >
                        View Documents
                    </Link>
                </div>
            </div>
        </header>

        <!-- MAIN -->
        <main class="mx-auto w-full max-w-6xl grow px-6 py-14">
            <h2 class="mb-10 text-center text-2xl font-bold tracking-wide text-[#1a202c] md:text-3xl">
                Document Categories
            </h2>

            <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                <Link
                    v-for="module in modules"
                    :key="module.key"
                    :href="module.href"
                    class="group flex flex-col rounded-xl border border-gray-200 bg-white px-8 py-10 shadow-[2px_4px_18px_rgba(0,0,0,0.07)] transition-all duration-200 hover:border-blue-300 hover:shadow-md"
                >
                    <!-- Abbr badge -->
                    <span
                        class="mb-4 self-start rounded-full bg-blue-50 px-3 py-1 text-xs font-bold tracking-widest text-[#3B82F6]"
                    >
                        {{ module.abbr }}
                    </span>

                    <h3 class="mb-3 text-lg font-semibold leading-snug text-[#1a202c]">
                        {{ module.label }}
                    </h3>

                    <p class="flex-1 text-sm leading-relaxed text-[#374151]">
                        {{ module.description }}
                    </p>

                    <!-- Arrow hint -->
                    <div class="mt-6 flex justify-end">
                        <span
                            class="text-lg font-semibold text-[#3B82F6] transition-transform duration-150 group-hover:translate-x-1"
                        >
                            →
                        </span>
                    </div>
                </Link>
            </div>
        </main>

        <!-- FOOTER -->
        <footer class="bg-[#151b2b] py-5 text-[#9ca3af]">
            <div class="flex items-center justify-center gap-3">
                <img :src="lnuLogo" alt="LNU" class="h-8 w-8 object-contain opacity-70" />
                <p class="text-sm">© 2026 Quality Management System | Developed For Efficient Record Management</p>
            </div>
        </footer>
    </div>
</template>
