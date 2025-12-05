<script setup>
import { ref } from "vue"
import { useRouter } from "vue-router"
import { useAuth } from "@/composables/useAuth"
import { useValidator } from "@/composables/useValidator"

import BaseInput from "@/components/forms/BaseInput.vue"

const router = useRouter()
const { setToken, setUser } = useAuth()

const email = ref("")
const password = ref("")
const confirmPassword = ref("")
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
        email: "auth.errors.incorrect_data"
      }
    },
    password: {
      value: password.value,
      required: true,
      min: 6,
      messages: {
        required: "auth.errors.incorrect_data",
        min: "auth.errors.incorrect_data"
      }
    },
    confirmPassword: {
      value: confirmPassword.value,
      required: true,
      messages: {
        required: "auth.errors.incorrect_data"
      }
    }
  })

  if (!isValid) return

  if (password.value !== confirmPassword.value) {
    globalError.value = "auth.errors.incorrect_data"
    return
  }

  loading.value = true

  // --- STUB: emulate registration ---
  setTimeout(() => {
    const fakeToken = "register_stub_token_123"

    setToken(fakeToken)

    // Same structure as login
    setUser({
      name: email.value.split("@")[0],
      role: "user"
    })

    // Redirect to intended page
    const intended = localStorage.getItem("intended")
    if (intended) {
      localStorage.removeItem("intended")
      return router.push(intended)
    }

    // Default user home route
    return router.push({ name: "app.home" })

  }, 600)
}
</script>

<template>
  <div class="min-h-screen px-4 py-12 flex items-center justify-center bg-[#0d1117] relative">

    <div class="absolute inset-0 bg-gradient-to-br from-blue-600/20 to-purple-600/20 blur-3xl opacity-40"></div>

    <Transition
        appear
        enter-active-class="transition duration-500 ease-out"
        enter-from-class="opacity-0 translate-y-3"
        enter-to-class="opacity-100 translate-y-0"
    >
      <div
          v-if="true"
          class="relative w-full max-w-md p-8 rounded-2xl border border-white/20 bg-white/10
               backdrop-blur-xl shadow-2xl text-white"
      >
        <h1 class="text-3xl font-semibold mb-6 text-center drop-shadow">
          {{ $t("auth.register.title") }}
        </h1>

        <form class="space-y-5" @submit.prevent="onSubmit">

          <!-- EMAIL -->
          <BaseInput
              v-model="email"
              :label="$t('auth.email')"
              autocomplete="email"
              :error="errors.email ? $t(errors.email) : null"
          />
          <p class="text-xs text-white/50 -mt-1">
            {{ $t("auth.hints.email_format") }}
          </p>

          <!-- PASSWORD -->
          <BaseInput
              v-model="password"
              :label="$t('auth.password')"
              type="password"
              autocomplete="new-password"
              :error="errors.password ? $t(errors.password) : null"
          />
          <p class="text-xs text-white/50 -mt-1">
            {{ $t("auth.hints.password_min") }}
          </p>

          <!-- CONFIRM PASSWORD -->
          <BaseInput
              v-model="confirmPassword"
              :label="$t('auth.password_confirm')"
              type="password"
              autocomplete="new-password"
              :error="errors.confirmPassword ? $t(errors.confirmPassword) : null"
          />
          <p class="text-xs text-white/50 -mt-1">
            {{ $t("auth.hints.password_confirm") }}
          </p>

          <!-- GLOBAL ERROR -->
          <p v-if="globalError" class="text-red-300 text-center text-sm mt-2">
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
            {{ loading ? $t("auth.loading") : $t("auth.register.submit") }}
          </button>
        </form>

        <!-- Disabled social login -->
        <div class="mt-8 flex flex-col gap-3">
          <button disabled class="w-full py-3 rounded-xl bg-red-500/40 text-white/70 cursor-not-allowed shadow-inner">
            {{ $t("auth.register.google") }}
          </button>

          <button disabled class="w-full py-3 rounded-xl bg-blue-600/40 text-white/70 cursor-not-allowed shadow-inner">
            {{ $t("auth.register.facebook") }}
          </button>
        </div>

      </div>
    </Transition>

  </div>
</template>
