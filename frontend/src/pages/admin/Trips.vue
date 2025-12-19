<script setup>
import { ref, onMounted } from "vue"
import { useI18n } from "vue-i18n"
import axios from "axios"

const { t } = useI18n({ useScope: "global" })

const trips = ref([])
const loading = ref(false)

async function fetchTrips() {
  loading.value = true
  try {
    const res = await axios.get("/api/admin/trips")
    trips.value = res.data
  } catch (err) {
    console.error("Błąd pobierania podróży:", err)
  } finally {
    loading.value = false
  }
}

function deleteTrip(id) {
  if (!confirm(t("admin.trips.delete_confirm"))) return
  axios.delete(`/api/admin/trips/${id}`).then(() => fetchTrips())
}

onMounted(fetchTrips)
</script>

<template>
  <div class="min-h-screen p-6 bg-gray-100">
    <div class="flex justify-between items-center mb-4">
      <h1 class="text-2xl font-bold">{{ t("app.admin.menu.trips") }}</h1>
      <button class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
        {{ t("app.admin.trips.add") }}
      </button>
    </div>

    <table class="w-full text-left bg-white rounded-lg shadow overflow-hidden">
      <thead class="bg-gray-200">
        <tr>
          <th class="px-4 py-2">ID</th>
          <th class="px-4 py-2">{{ t("app.admin.trips.name") }}</th>
          <th class="px-4 py-2">{{ t("app.admin.trips.start_date") }}</th>
          <th class="px-4 py-2">{{ t("app.admin.trips.end_date") }}</th>
          <th class="px-4 py-2">{{ t("app.admin.trips.actions") }}</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="trip in trips" :key="trip.id" class="border-b hover:bg-gray-50">
          <td class="px-4 py-2">{{ trip.id }}</td>
          <td class="px-4 py-2">{{ trip.name }}</td>
          <td class="px-4 py-2">{{ trip.start_date }}</td>
          <td class="px-4 py-2">{{ trip.end_date }}</td>
          <td class="px-4 py-2 space-x-2">
            <button class="px-2 py-1 bg-yellow-400 text-white rounded hover:bg-yellow-500">
              {{ t("app.admin.edit") }}
            </button>
            <button class="px-2 py-1 bg-red-600 text-white rounded hover:bg-red-700" @click="deleteTrip(trip.id)">
              {{ t("app.admin.delete") }}
            </button>
          </td>
        </tr>
        <tr v-if="loading">
          <td colspan="5" class="text-center p-4">{{ t("loading") }}</td>
        </tr>
        <tr v-if="!loading && trips.length === 0">
          <td colspan="5" class="text-center p-4">{{ t("app.admin.trips.no_trips") }}</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
