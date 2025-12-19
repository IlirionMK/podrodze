<script setup>
import { ref, onMounted } from "vue"
import { useI18n } from "vue-i18n"

const { t } = useI18n({ useScope: "global" })
const sidebar = ref(false)

// Mock settings, możesz potem fetchować z API
const settings = ref([
  { key: "Site Name", value: "PoDrodze" },
  { key: "Google API Key", value: "AIza..." },
])
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
      <router-link to="/admin/places" class="hover:underline text-sm">Places</router-link>
      <router-link to="/admin/settings" class="hover:underline text-sm font-semibold">Settings</router-link>
    </aside>
  </transition>

  <!-- Main Content -->
  <main class="flex-1 p-6">
    <h1 class="text-2xl font-bold text-white mb-4 bg-blue-600 p-3 rounded-md">Settings</h1>

    <div class="bg-white p-6 rounded-lg shadow">
      <div v-for="item in settings" :key="item.key" class="mb-4">
        <label class="block font-medium mb-1">{{ item.key }}</label>
        <input type="text" v-model="item.value" class="w-full border rounded p-2" />
      </div>
      <button class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Save Settings</button>
    </div>
  </main>

</div>
</template>
