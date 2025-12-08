<script setup>
import { onMounted } from "vue"
import { useRoute, useRouter } from "vue-router"
import { useAuth } from "@/composables/useAuth"

const route = useRoute()
const router = useRouter()
const { setToken, setUser } = useAuth()

onMounted(async () => {
  const code = route.query.code

  if (!code) {
    return router.push({ name: "auth.login" })
  }

  try {
    const res = await fetch(import.meta.env.VITE_API_URL + "/auth/google/callback", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ code })
    })

    const data = await res.json()

    if (!data?.token) {
      return router.push({ name: "auth.login" })
    }

    setToken(data.token)
    setUser(data.user)

    return router.push({ name: "app.home" })
  } catch (e) {
    console.error("Google callback error:", e)
    return router.push({ name: "auth.login" })
  }
})
</script>

<template>
  <div class="flex items-center justify-center min-h-screen text-white text-xl">
    Logging in with Google…
  </div>
</template>
