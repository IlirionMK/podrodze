<script setup>
import { ref, onMounted } from "vue"
import axios from "axios"
import { useI18n } from "vue-i18n"

const { t } = useI18n({ useScope: "global" })
const sidebar = ref(false)

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

onMounted(fetchPlaces)
</script>

<template>
<div class="flex min-h-screen bg-blue-1000">

  

  <!-- Mobile toggle button -->
  <button
      @click="sidebar = !sidebar"
      class="md:hidden absolute top-4 left-4 z-20 bg-white p-2 rounded shadow"
  >
    ☰
  </button>

  <!-- Mobile Sidebar -->
  <transition
      enter-active-class="transition-transform duration-200 ease-linear"
      enter-from-class="-translate-x-full"
      enter-to-class="translate-x-0"
      leave-active-class="transition-transform duration-200 ease-linear"
      leave-from-class="translate-x-0"
      leave-to-class="-translate-x-full"
  >
    <aside
        v-if="sidebar"
        class="absolute left-0 top-0 bottom-0 bg-white w-56 p-4 flex flex-col gap-4 shadow-md z-30 md:hidden"
    >
      <h2 class="font-bold text-lg">{{ t("app.admin.title") }}</h2>
      <router-link to="/admin/dashboard" class="hover:underline text-sm">Dashboard</router-link>
      <router-link to="/admin/users" class="hover:underline text-sm">Users</router-link>
      <router-link to="/admin/trips" class="hover:underline text-sm">Trips</router-link>
      <router-link to="/admin/places" class="hover:underline text-sm font-semibold">Places</router-link>
      <router-link to="/admin/settings" class="hover:underline text-sm">Settings</router-link>
    </aside>
  </transition>

  <!-- Main Content -->
  <main class="flex-1 p-6">
    <h1 class="text-2xl font-bold text-white mb-4 bg-blue-600 p-3 rounded-md">Places</h1>

    <table class="w-full text-left bg-white rounded-lg shadow overflow-hidden">
      <thead class="bg-gray-600">
        <tr>
          <th class="px-4 py-2">ID</th>
          <th class="px-4 py-2">Name</th>
          <th class="px-4 py-2">Location</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="place in places" :key="place.id" class="border-b hover:bg-gray-50">
          <td class="px-4 py-2">{{ place.id }}</td>
          <td class="px-4 py-2">{{ place.name }}</td>
          <td class="px-4 py-2">{{ place.location }}</td>
        </tr>
        <tr v-if="loading">
          <td colspan="3" class="text-center p-4">Loading...</td>
        </tr>
        <tr v-if="!loading && places.length === 0">
          <td colspan="3" class="text-center p-4">No places found</td>
        </tr>
      </tbody>
    </table>
  </main>

</div>
</template>
