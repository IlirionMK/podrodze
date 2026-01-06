<script setup>
import { ref, onMounted } from "vue"
import { useI18n } from "vue-i18n"
import axios from "axios"

const { t } = useI18n({ useScope: "global" })

const places = ref([])
const loading = ref(false)

async function fetchPlaces() {
  loading.value = true
  try {
    const res = await axios.get("/api/admin/places")
    places.value = res.data
  } catch (err) {
    console.error("Błąd pobierania miejsc:", err)
  } finally {
    loading.value = false
  }
}

function deletePlace(id) {
  if (!confirm(t("app.admin.places.delete_confirm"))) return
  axios.delete(`/api/admin/places/${id}`).then(() => fetchPlaces())
}

onMounted(fetchPlaces)
</script>

<template>
  <div class="min-h-screen p-6 bg-gray-100">
    <div class="flex justify-between items-center mb-4">
      <h1 class="text-2xl font-bold">{{ t("app.admin.menu.places") }}</h1>
      <button class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
        {{ t("app.admin.places.add") }}
      </button>
    </div>

    <table class="w-full text-left bg-white rounded-lg shadow overflow-hidden">
      <thead class="bg-gray-200">
        <tr>
          <th class="px-4 py-2">ID</th>
          <th class="px-4 py-2">{{ t("app.admin.places.name") }}</th>
          <th class="px-4 py-2">{{ t("app.admin.places.location") }}</th>
          <th class="px-4 py-2">{{ t("app.admin.places.actions") }}</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="place in places" :key="place.id" class="border-b hover:bg-gray-50">
          <td class="px-4 py-2">{{ place.id }}</td>
          <td class="px-4 py-2">{{ place.name }}</td>
          <td class="px-4 py-2">{{ place.location }}</td>
          <td class="px-4 py-2 space-x-2">
            <button class="px-2 py-1 bg-yellow-400 text-white rounded hover:bg-yellow-500">
              {{ t("app.admin.edit") }}
            </button>
            <button class="px-2 py-1 bg-red-600 text-white rounded hover:bg-red-700" @click="deletePlace(place.id)">
              {{ t("app.admin.delete") }}
            </button>
          </td>
        </tr>
        <tr v-if="loading">
          <td colspan="4" class="text-center p-4">{{ t("loading") }}</td>
        </tr>
        <tr v-if="!loading && places.length === 0">
          <td colspan="4" class="text-center p-4">{{ t("app.admin.places.no_places") }}</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
