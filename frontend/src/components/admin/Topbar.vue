<script setup>
import { useRouter } from "vue-router"
const router = useRouter()

const logout = () => {
  localStorage.removeItem("token")
  localStorage.removeItem("role")
  router.push("/login")
}
</script>

<template>
  <header class="bg-gray-900 flex justify-between items-center p-4 shadow-md text-white">
    <h1 class="text-xl font-bold">Admin Panel</h1>
    <button @click="logout" class="bg-red-500 hover:bg-red-600 px-4 py-2 rounded">Logout</button>
  </header>
</template>
