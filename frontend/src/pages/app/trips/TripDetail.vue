<script setup>
import { ref, onMounted } from "vue"
import { useRoute } from "vue-router"
import { fetchTrip } from "@/composables/api/trips"

const route = useRoute()
const tripId = route.params.id

const trip = ref(null)
const loading = ref(true)
const error = ref(null)

onMounted(async () => {
  try {
    const res = await fetchTrip(tripId)
    trip.value = res.data.data ?? res.data
  } catch (e) {
    console.error("Trip load error", e.response ?? e)
    error.value = "Failed to load trip"
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="p-6 space-y-4">
    <div v-if="loading">Loading...</div>

    <div v-else-if="error" class="text-red-500">
      {{ error }}
    </div>

    <template v-else>
      <h1 class="text-xl font-semibold">
        {{ trip.name ?? `Trip #${tripId}` }}
      </h1>

      <div class="text-gray-500 text-sm mb-4">
        {{ trip.start_date }} — {{ trip.end_date }}
      </div>

      <div class="rounded-xl border border-gray-300 p-4 text-gray-600">
        Map placeholder
      </div>

      <div class="rounded-xl border border-gray-300 p-4 text-gray-600">
        Trip detail content placeholder
      </div>
    </template>
  </div>
</template>
