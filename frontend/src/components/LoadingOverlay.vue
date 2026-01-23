<script setup>
defineProps({
  show: { type: Boolean, default: false },
  text: { type: String, default: "" },
})
</script>

<template>
  <Transition
      enter-active-class="transition duration-150 ease-out"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
  >
    <div
        v-if="show"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-md"
    >
      <div class="flex flex-col items-center gap-4">
        <div class="w-16 h-16 border-4 border-white/20 border-t-blue-400 rounded-full animate-spin"></div>
        <p v-if="text" class="text-white/90 text-sm font-medium animate-pulse">
          {{ text }}
        </p>
      </div>
    </div>
  </Transition>
</template>
