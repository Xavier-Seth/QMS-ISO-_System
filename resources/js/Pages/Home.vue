<script setup>
import { Link } from '@inertiajs/vue3'
import lnuLogo from '../images/LNU_logo.png'
</script>

<template>
  <div class="min-h-screen bg-[#f4f5f7] flex flex-col font-sans">
    <!-- ─── HEADER ──────────────────────────────────────────────── -->
    <header class="bg-[#151b2b] text-white border-b-[6px] border-[#C9A84C]">
      <div class="max-w-6xl mx-auto px-6 py-14 md:py-16">
        <!-- Logo + Title -->
        <div class="flex flex-col md:flex-row items-center justify-center gap-6 md:gap-8">
          <img
            :src="lnuLogo"
            alt="LNU Logo"
            class="w-28 h-28 md:w-36 md:h-36 object-contain shrink-0"
          />

          <div class="text-center md:text-left">
            <h1 class="text-2xl md:text-4xl font-bold tracking-wide leading-tight">
              Quality Management System (ISO)
            </h1>
            <h2 class="text-2xl md:text-4xl font-bold tracking-wide leading-tight">
              Archiving
            </h2>

            <p class="mt-4 text-slate-300 text-sm md:text-base tracking-wide text-center md:text-left">
              Secure, Organized, and Reliable
            </p>
          </div>
        </div>

        <!-- CTA Buttons -->
        <div class="mt-10 flex flex-col sm:flex-row items-center justify-center gap-4 sm:gap-10">
          <Link
            href="/login"
            class="inline-flex items-center justify-center w-full sm:w-44 h-11 rounded-lg bg-white text-gray-900
                   text-sm font-semibold hover:bg-gray-100 transition shadow-sm"
          >
            Login
          </Link>

          <Link
            href="/dcr"
            class="inline-flex items-center justify-center w-full sm:w-44 h-11 rounded-lg bg-[#3B82F6] text-white
                   text-sm font-semibold hover:bg-blue-600 transition shadow-sm"
          >
            View Documents
          </Link>
        </div>
      </div>
    </header>

    <!-- ─── MAIN ─────────────────────────────────────────────────── -->
    <main class="grow max-w-6xl mx-auto w-full py-14 px-6">
      <h2 class="text-2xl md:text-3xl font-bold text-center text-[#1a202c] mb-10 tracking-wide">
        Document Categories
      </h2>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div
          v-for="n in 3"
          :key="n"
          class="bg-white rounded-xl border border-gray-200 shadow-[2px_4px_18px_rgba(0,0,0,0.07)]
                 px-8 py-10 flex flex-col items-center"
        >
          <h3 class="text-[#3B82F6] text-lg md:text-xl font-medium text-center mb-4">
            Documents Category 0{{ n }}
          </h3>

          <p class="text-[#374151] text-sm leading-relaxed text-center max-w-sm">
            Store and manage complete teacher records securely and efficiently.
          </p>
        </div>
      </div>
    </main>

    <!-- ─── FOOTER ──────────────────────────────────────────────── -->
    <footer class="bg-[#151b2b] text-[#9ca3af] py-5 text-center text-sm">
      <p>© 2026 Quality Management System | Developed For Efficient Record Management</p>
    </footer>
  </div>
</template>

<style scoped>
.font-sans {
  font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
}
</style>