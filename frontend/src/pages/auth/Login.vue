<script setup>
import { ref } from "vue"
import { useRouter } from "vue-router"
import { useAuth } from "@/composables/useAuth"
import { useValidator } from "@/composables/useValidator"
import api from "@/composables/api/api"
import BaseInput from "@/components/forms/BaseInput.vue"

const router = useRouter()
const { setAuth } = useAuth()

const email = ref("")
const password = ref("")
const loading = ref(false)
const globalError = ref(null)

const { errors, validate } = useValidator()

async function onSubmit() {
  globalError.value = null

  const isValid = validate({
    email: {
      value: email.value,
      required: true,
      email: true,
      messages: {
        required: "auth.errors.incorrect_data",
        email: "auth.errors.incorrect_data",
      },
    },
    password: {
      value: password.value,
      required: true,
      min: 6,
      messages: {
        required: "auth.errors.incorrect_data",
        min: "auth.errors.incorrect_data",
      },
    },
  })

  if (!isValid) return
  loading.value = true

  try {
    const { data } = await api.post("/login", {
      email: email.value,
      password: password.value,
    })

    if (!data?.token || !data?.user) {
      globalError.value = "auth.errors.incorrect_data"
      return
    }

    setAuth(data.user, data.token)

    const intended = localStorage.getItem("intended")
    if (intended) {
      localStorage.removeItem("intended")
      return router.push(intended)
    }

    if (data.user.role === "admin") {
      return router.push({ name: "admin.dashboard" })
    }

    router.push({ name: "app.home" })
  } catch {
    globalError.value = "auth.errors.incorrect_data"
  } finally {
    loading.value = false
  }
}

async function redirectToGoogle() {
  try {
    const res = await fetch(import.meta.env.VITE_API_URL + "/auth/google/url", {
      headers: { Accept: "application/json" },
    })
    const data = await res.json()
    if (data?.url) window.location.href = data.url
  } catch (e) {}
}

async function redirectToFacebook() {
  try {
    const res = await fetch(import.meta.env.VITE_API_URL + "/auth/facebook/url", {
      headers: { Accept: "application/json" },
    })
    const data = await res.json()
    if (data?.url) window.location.href = data.url
  } catch (e) {}
}
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-[#0d1117] px-4 py-10 relative">
    <div class="absolute inset-0 bg-gradient-to-br from-blue-600/20 to-purple-600/20 blur-3xl opacity-40"></div>

    <Transition
        appear
        enter-active-class="transition duration-500 ease-out"
        enter-from-class="opacity-0 translate-y-3"
        enter-to-class="opacity-100 translate-y-0"
    >
      <div
          v-if="true"
          class="relative w-full max-w-md p-8 rounded-2xl bg-white/10 backdrop-blur-xl
               border border-white/20 shadow-2xl text-white"
      >
        <h1 class="text-3xl font-semibold mb-6 text-center drop-shadow">
          {{ $t("auth.login.title") }}
        </h1>

        <form class="space-y-5" @submit.prevent="onSubmit">
          <BaseInput
              v-model="email"
              :label="$t('auth.email')"
              autocomplete="email"
              :error="errors.email ? $t(errors.email) : null"
          />
          <p class="text-xs text-white/50 -mt-1">
            {{ $t("auth.hints.email_format") }}
          </p>

          <BaseInput
              v-model="password"
              :label="$t('auth.password')"
              type="password"
              autocomplete="current-password"
              :error="errors.password ? $t(errors.password) : null"
          />
          <p class="text-xs text-white/50 -mt-1">
            {{ $t("auth.hints.password_min") }}
          </p>

          <p v-if="globalError" class="text-red-300 text-sm text-center">
            {{ $t(globalError) }}
          </p>

          <button
              type="submit"
              :disabled="loading"
              class="w-full py-3 rounded-xl text-lg font-medium
                   bg-gradient-to-r from-blue-500 to-purple-600
                   hover:opacity-90 active:opacity-80 transition
                   disabled:opacity-50 shadow-lg"
          >
            {{ loading ? $t("auth.loading") : $t("auth.login.submit") }}
          </button>
        </form>

        <div class="mt-8 flex flex-col gap-3">
          <button
              @click="redirectToGoogle"
              class="w-full py-3 rounded-xl bg-red-500 text-white font-medium shadow-lg hover:opacity-90 transition"
          >
            {{ $t("auth.login.google") }}
          </button>

          <button
              @click="redirectToFacebook"
              class="w-full py-3 rounded-xl bg-blue-600 text-white font-medium shadow-lg hover:opacity-90 transition"
          >
            {{ $t("auth.login.facebook") }}
          </button>
        </div>
      </div>
    </Transition>
  </div>
</template>
