<script setup>
import { onMounted, ref } from "vue"
import { useRoute, useRouter } from "vue-router"
import { useAuth } from "@/composables/useAuth"
import { useI18n } from "vue-i18n"

const { t } = useI18n()
const router = useRouter()
const route = useRoute()

// важно: используем setAuth, а не setToken/setUser по отдельности
const { setAuth } = useAuth()

const message = ref(t("auth.google.connecting"))

onMounted(async () => {
  const code = route.query.code

  if (!code) {
    return router.push({ name: "auth.login" })
  }

  message.value = t("auth.google.authenticating")

  try {
    const res = await fetch(import.meta.env.VITE_API_URL + "/auth/google/callback", {
      method: "POST",
      headers: { "Content-Type": "application/json", Accept: "application/json" },
      body: JSON.stringify({ code }),
    })

    let data = null
    try {
      data = await res.json()
    } catch (_) {
      data = null
    }

    // если бек вернул ошибку или нет токена/юзера — уходим на логин
    if (!res.ok || !data?.token || !data?.user) {
      message.value = t("auth.google.error")
      return router.push({ name: "auth.login" })
    }

    message.value = t("auth.google.loading")

    // один источник истины: сохранит user+token так, как ждёт router guard
    setAuth(data.user, data.token)

    // если был intended — уважаем его (как в login/register)
    const intended = localStorage.getItem("intended")
    if (intended) {
      localStorage.removeItem("intended")
      return router.push(intended)
    }

    return router.push({ name: "app.home" })
  } catch (e) {
    message.value = t("auth.google.error")
    return router.push({ name: "auth.login" })
  }
})
</script>

<template>
  <div class="min-h-screen flex flex-col items-center justify-center bg-[#0d1117] relative overflow-hidden">
    <!-- background glow -->
    <div class="absolute inset-0 bg-gradient-to-br from-blue-600/20 to-purple-600/20 blur-3xl opacity-40"></div>

    <!-- Loader -->
    <div
        class="w-16 h-16 border-4 border-white/20 border-t-blue-400 rounded-full animate-spin drop-shadow-xl transition-all duration-500"
    ></div>

    <!-- Text -->
    <p class="mt-6 text-white text-lg font-medium animate-pulse">
      {{ message }}
    </p>
  </div>
</template>
