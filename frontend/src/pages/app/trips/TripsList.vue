<script setup>
import { ref, onMounted } from "vue"
import { fetchUserTrips } from "@/composables/api/trips"

const trips = ref([])
const loading = ref(true)
const error = ref(null)

onMounted(async () => {
  try {
    const response = await fetchUserTrips()
    trips.value = response.data.data ?? response.data
  } catch (e) {
    error.value = "Failed to load trips"
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="p-6">

    <div class="flex justify-between mb-4">
      <h1 class="text-xl font-semibold">Your Trips</h1>

      <router-link
          :to="{ name: 'app.trips.create' }"
          class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition"
      >
        + New Trip
      </router-link>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="text-gray-500">
      Loading trips...
    </div>

    <!-- Error -->
    <div v-else-if="error" class="text-red-500">
      {{ error }}
    </div>

    <!-- Empty -->
    <div v-else-if="trips.length === 0" class="text-gray-500">
      You are not part of any trips yet.
    </div>

    <!-- Trips list -->
    <ul v-else class="space-y-3">
      <li
          v-for="trip in trips"
          :key="trip.id"
          class="p-4 border border-gray-300 rounded-lg hover:bg-gray-50 cursor-pointer transition"
          @click="$router.push({ name: 'app.trips.show', params: { id: trip.id }})"
      >
        <div class="font-semibold">{{ trip.name }}</div>
        <div class="text-sm text-gray-600">
          {{ trip.start_date }} – {{ trip.end_date }}
        </div>
      </li>
    </ul>

  </div>
</template>
