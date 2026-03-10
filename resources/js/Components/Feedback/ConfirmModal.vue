<script setup>
import { computed } from "vue";
import { useConfirm } from "@/Composables/useConfirm";

const confirmState = useConfirm();

const toneClasses = computed(() => {
  if (confirmState.state.tone === "warning") {
    return {
      iconWrap: "bg-amber-100 text-amber-700",
      confirmBtn: "bg-amber-600 hover:bg-amber-500 focus:ring-amber-300",
    };
  }

  if (confirmState.state.tone === "primary") {
    return {
      iconWrap: "bg-sky-100 text-sky-700",
      confirmBtn: "bg-slate-900 hover:bg-slate-800 focus:ring-slate-300",
    };
  }

  return {
    iconWrap: "bg-rose-100 text-rose-700",
    confirmBtn: "bg-rose-600 hover:bg-rose-500 focus:ring-rose-300",
  };
});
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
      v-if="confirmState.state.open"
      class="fixed inset-0 z-[9997] flex items-center justify-center bg-slate-950/50 p-4"
      @click.self="confirmState.cancel()"
    >
      <div class="w-full max-w-md overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
        <div class="px-6 pt-6">
          <div class="flex items-start gap-4">
            <div
              class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full text-lg font-semibold"
              :class="toneClasses.iconWrap"
            >
              !
            </div>

            <div class="min-w-0">
              <h3 class="text-lg font-semibold text-slate-900">
                {{ confirmState.state.title }}
              </h3>
              <p class="mt-2 text-sm leading-6 text-slate-600">
                {{ confirmState.state.message }}
              </p>
            </div>
          </div>
        </div>

        <div class="mt-6 flex items-center justify-end gap-3 border-t border-slate-200 bg-slate-50 px-6 py-4">
          <button
            type="button"
            class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100"
            :disabled="confirmState.state.loading"
            @click="confirmState.cancel()"
          >
            {{ confirmState.state.cancelText }}
          </button>

          <button
            type="button"
            class="rounded-xl px-4 py-2 text-sm font-medium text-white focus:outline-none focus:ring-2"
            :class="toneClasses.confirmBtn"
            :disabled="confirmState.state.loading"
            @click="confirmState.confirm()"
          >
            {{ confirmState.state.loading ? "Please wait..." : confirmState.state.confirmText }}
          </button>
        </div>
      </div>
    </div>
  </transition>
</template>