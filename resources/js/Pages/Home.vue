<script setup>
import { Link } from '@inertiajs/vue3'
import lnuLogo from '../images/LNU_logo.png'
</script>

<template>
  <div class="min-h-screen font-sans bg-[#f4f5f7] flex flex-col">

    <!-- ─── HEADER ──────────────────────────────────────────────── -->
    <header class="bg-[#151b2b] text-white pt-14 pb-14 px-4 border-b-[6px] border-[#C9A84C]">
      <div class="max-w-5xl mx-auto flex flex-col items-center">

        <!-- Logo + Title row -->
        <div class="flex flex-row items-center justify-center mb-4 gap-5">
          <img
            :src="lnuLogo"
            alt="LNU Logo"
            class="h-22.5 w-22.5 object-contain shrink-0"
          />
          <div class="text-center">
            <h1 class="text-[2.4rem] leading-[1.15] font-bold tracking-wide">
              Quality Management System (ISO)
            </h1>
            <h2 class="text-[2.4rem] leading-[1.15] font-bold tracking-wide">
              Archiving
            </h2>
          </div>
        </div>

        <!-- Subtitle -->
        <p class="text-[#9ca3af] text-lg mb-14 font-light tracking-wider text-center">
          Secure, Organized, and Reliable
        </p>

        <!-- CTA Buttons — large gap matching the design -->
        <div class="flex flex-row items-center gap-16">
          <Link
            href="/login"
            class="bg-white text-gray-900 w-44 py-2.5 rounded text-center text-base font-medium hover:bg-gray-100 transition shadow-sm"
          >
            Login
          </Link>

          <Link
            href="/dcr"
            class="bg-[#3B82F6] text-white w-44 py-2.5 rounded text-center text-base font-medium hover:bg-blue-600 transition shadow-sm"
          >
            View Documents
          </Link>
        </div>

      </div>
    </header>

    <!-- ─── MAIN ─────────────────────────────────────────────────── -->
    <main class="grow max-w-312 mx-auto w-full py-16 px-8">

      <h2 class="text-[1.85rem] font-bold text-center text-[#1a202c] mb-14 tracking-wide">
        Documents Categories
      </h2>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-7">
        <div
          v-for="n in 3"
          :key="n"
          class="bg-white rounded-lg border border-gray-200 shadow-[2px_4px_18px_rgba(0,0,0,0.07)] px-10 pt-10 pb-16 flex flex-col items-center min-h-60"
        >
          <!-- Category title -->
          <h3 class="text-[#3B82F6] text-[1.35rem] font-normal text-center mb-6">
            Documents Category 0{{ n }}
          </h3>

          <!-- Description -->
          <p class="text-[#374151] text-sm leading-relaxed text-center max-w-65">
            Store and Manage Complete teacher records securely and effieciently
          </p>
        </div>
      </div>

    </main>

    <!-- ─── FOOTER ────────────────────────────────────────────────── -->
    <footer class="bg-[#151b2b] text-[#6b7280] py-5 text-center text-sm">
      <p>© 2026 Quality Management System | Developed For Efficient Record Management</p>
    </footer>

  </div>
</template>

<style scoped>
.font-sans {
  font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
}

</style>