<script setup>
import { onMounted, ref } from "vue"
import { useRoute, useRouter } from "vue-router"
import { useAuth } from "@/composables/useAuth"

const router = useRouter()
const route = useRoute()
const { setAuth } = useAuth()

const message = ref("Signing you in with Google...")

onMounted(async () => {
  const code = route.query.code

  if (!code) {
    return router.push({ name: "auth.login" })
  }

  message.value = "Authenticating..."

  try {
    const res = await fetch("/api/v1/auth/google/callback", {
      method: "POST",
      headers: { "Content-Type": "application/json", Accept: "application/json" },
      body: JSON.stringify({ code }),
    })

    let data = null
    try {
      data = await res.json()
    } catch {
      data = null
    }

    if (!res.ok || !data?.token || !data?.user) {
      message.value = "Authentication failed. Redirecting..."
      return setTimeout(() => router.push({ name: "auth.login" }), 800)
    }

    message.value = "Finalizing..."

    setAuth(data.user, data.token)

    const intended = localStorage.getItem("intended")
    if (intended) {
      localStorage.removeItem("intended")
      return router.push(intended)
    }

    return router.push({ name: "app.home" })
  } catch {
    message.value = "Network error. Redirecting..."
    return setTimeout(() => router.push({ name: "auth.login" }), 800)
  }
})
</script>

<template>
  <div class="min-h-screen flex flex-col items-center justify-center bg-[#0d1117] relative overflow-hidden">
    <div class="absolute inset-0 bg-gradient-to-br from-blue-600/20 to-purple-600/20 blur-3xl opacity-40"></div>

    <div
        class="w-16 h-16 border-4 border-white/20 border-t-blue-400 rounded-full animate-spin drop-shadow-xl transition-all duration-500"
    ></div>

    <p class="mt-6 text-white text-lg font-medium animate-pulse">
      {{ message }}
    </p>
  </div>
</template>
