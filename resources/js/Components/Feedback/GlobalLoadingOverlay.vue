<script setup>
import { useLoadingOverlay } from "@/Composables/useLoadingOverlay";

const { isVisible, message } = useLoadingOverlay();
</script>

<template>
  <transition
    enter-active-class="transition duration-200 ease-out"
    enter-from-class="opacity-0"
    enter-to-class="opacity-100"
    leave-active-class="transition duration-150 ease-in"
    leave-from-class="opacity-100"
    leave-to-class="opacity-0"
  >
    <div
      v-if="isVisible"
      class="fixed inset-0 z-[9998] flex items-center justify-center bg-slate-950/45 backdrop-blur-[2px]"
      aria-live="polite"
      aria-busy="true"
    >
      <div
        class="mx-4 w-full max-w-sm rounded-2xl border border-white/10 bg-white shadow-2xl"
      >
        <div class="flex flex-col items-center px-8 py-8 text-center">
          <div
            class="h-14 w-14 rounded-full border-4 border-slate-200 border-t-slate-900 animate-spin"
          ></div>

          <h3 class="mt-5 text-base font-semibold text-slate-900">
            Processing
          </h3>

          <p class="mt-2 text-sm text-slate-600">
            {{ message }}
          </p>
        </div>
      </div>
    </div>
  </transition>
</template>