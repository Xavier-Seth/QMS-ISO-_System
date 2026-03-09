<script setup>
import { useToast } from "@/Composables/useToast";

const toast = useToast();

function iconClass(type) {
  if (type === "success") return "bg-emerald-100 text-emerald-700";
  if (type === "error") return "bg-rose-100 text-rose-700";
  return "bg-sky-100 text-sky-700";
}

function barClass(type) {
  if (type === "success") return "bg-emerald-500";
  if (type === "error") return "bg-rose-500";
  return "bg-sky-500";
}
</script>

<template>
  <div class="pointer-events-none fixed top-5 right-5 z-[9999] flex w-full max-w-sm flex-col gap-3">
    <transition-group
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="translate-y-2 opacity-0"
      enter-to-class="translate-y-0 opacity-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="translate-y-0 opacity-100"
      leave-to-class="translate-y-2 opacity-0"
    >
      <div
        v-for="item in toast.toasts"
        :key="item.id"
        class="pointer-events-auto overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-lg"
      >
        <div class="flex items-start gap-3 p-4">
          <div
            class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-sm font-semibold"
            :class="iconClass(item.type)"
          >
            <span v-if="item.type === 'success'">✓</span>
            <span v-else-if="item.type === 'error'">!</span>
            <span v-else>i</span>
          </div>

          <div class="min-w-0 flex-1">
            <div class="text-sm font-semibold text-slate-900">
              {{ item.title }}
            </div>

            <div v-if="item.message" class="mt-1 text-sm text-slate-600">
              {{ item.message }}
            </div>
          </div>

          <button
            type="button"
            class="text-slate-400 hover:text-slate-700"
            @click="toast.remove(item.id)"
          >
            ✕
          </button>
        </div>

        <div class="h-1 w-full bg-slate-100">
          <div class="h-full w-full" :class="barClass(item.type)"></div>
        </div>
      </div>
    </transition-group>
  </div>
</template>