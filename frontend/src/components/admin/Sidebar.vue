<script setup>
import { ref } from "vue"
const sidebarOpen = ref(false)

const menuItems = [
  { name: "Dashboard", path: "/admin" },
  { name: "Users", path: "/admin/users" },
  { name: "Trips", path: "/admin/trips" },
  { name: "Places", path: "/admin/places" },
  { name: "Settings", path: "/admin/settings" },
]
</script>

<template>
  <aside class="bg-gray-800 w-64 p-6 hidden md:flex flex-col gap-4 min-h-screen text-white">
    <h2 class="font-bold text-xl mb-4">Admin Panel</h2>
    <div v-for="item in menuItems" :key="item.path">
      <router-link :to="item.path" class="hover:text-blue-400">{{ item.name }}</router-link>
    </div>
  </aside>
</template>
