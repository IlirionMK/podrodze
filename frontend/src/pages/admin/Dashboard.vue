<script setup>
import { useRouter } from "vue-router"

const router = useRouter()

const tiles = [
  { title: "Users", desc: "Manage users", to: "/admin/users" },
  { title: "Trips", desc: "Manage trips", to: "/admin/trips" },
  { title: "Places", desc: "Manage places", to: "/admin/places" },
  { title: "Settings", desc: "System settings", to: "/admin/settings" },
]
</script>

<template>
  <div>
    <h1 class="text-3xl font-bold mb-2">Admin Panel</h1>
    <p class="text-gray-600 mb-8">
      System management dashboard
    </p>

    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-6">
      <button
        v-for="tile in tiles"
        :key="tile.title"
        @click="router.push(tile.to)"
        class="bg-white rounded-xl p-6 shadow
               hover:shadow-lg transition text-left"
      >
        <h2 class="text-xl font-semibold mb-2">{{ tile.title }}</h2>
        <p class="text-gray-500 text-sm">{{ tile.desc }}</p>
      </button>
    </div>
  </div>
</template>
