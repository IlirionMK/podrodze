<template>
  <div>
    <h2 class="text-3xl font-bold mb-4">
      Dashboard
    </h2>

    <p class="text-gray-600">
      Welcome to your travel dashboard! 🌍
    </p>

    <div class="mt-8">
      <RouterLink 
          to="/app/trips"
          class="inline-block bg-indigo-600 text-white px-6 py-3 rounded-lg hover:bg-indigo-700 transition"
      >
        Go to Trips
      </RouterLink>
    </div>
  </div>
</template>

<script setup>
</script>
