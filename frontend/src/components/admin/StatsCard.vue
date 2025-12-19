<script setup>
defineProps({
  title: String,
  value: [Number, String],
  icon: String
})
</script>

<template>
  <div class="bg-gray-700 rounded-lg p-6 shadow flex items-center gap-4">
    <div class="text-3xl">{{ icon }}</div>
    <div>
      <p class="text-gray-300 text-sm">{{ title }}</p>
      <p class="text-white text-2xl font-bold">{{ value }}</p>
    </div>
  </div>
</template>
