<script setup>
import { ref } from "vue"
import { useRouter } from "vue-router"
import { useAuth } from "@/composables/useAuth"
import { useValidator } from "@/composables/useValidator"
import BaseInput from "@/components/forms/BaseInput.vue"
import LoadingOverlay from "@/components/LoadingOverlay.vue"

const router = useRouter()
const { setAuth } = useAuth()

const email = ref("")
const password = ref("")
const confirmPassword = ref("")
const loading = ref(false)
const globalError = ref(null)

const { errors, validate } = useValidator()

function nextFrame() {
  return new Promise((r) => requestAnimationFrame(() => r()))
}

async function onSubmit() {
  if (loading.value) return
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
    confirmPassword: {
      value: confirmPassword.value,
      required: true,
      messages: {
        required: "auth.errors.incorrect_data",
      },
    },
  })

  if (!isValid) return

  if (password.value !== confirmPassword.value) {
    globalError.value = "auth.errors.incorrect_data"
    return
  }

  loading.value = true
  await nextFrame()

  try {
    const res = await fetch(import.meta.env.VITE_API_URL + "/register", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
      body: JSON.stringify({
        name: email.value?.split("@")?.[0] || "User",
        email: email.value,
        password: password.value,
        password_confirmation: confirmPassword.value,
      }),
    })

    let data = null
    try {
      data = await res.json()
    } catch {
      data = null
    }

    if (!res.ok || !data?.token || !data?.user) {
      globalError.value = "auth.errors.incorrect_data"
      return
    }

    setAuth(data.user, data.token)

    const intended = localStorage.getItem("intended")
    if (intended) {
      localStorage.removeItem("intended")
      return router.push(intended)
    }

    return router.push({ name: "app.home" })
  } catch {
    globalError.value = "auth.errors.incorrect_data"
  } finally {
    loading.value = false
  }
}

async function redirectToGoogle() {
  if (loading.value) return
  loading.value = true
  await nextFrame()

  try {
    const res = await fetch(import.meta.env.VITE_API_URL + "/auth/google/url", {
      headers: { Accept: "application/json" },
    })
    const data = await res.json()
    if (data?.url) window.location.href = data.url
  } catch {
    loading.value = false
  }
}

async function redirectToFacebook() {
  if (loading.value) return
  loading.value = true
  await nextFrame()

  try {
    const res = await fetch(import.meta.env.VITE_API_URL + "/auth/facebook/url", {
      headers: { Accept: "application/json" },
    })
    const data = await res.json()
    if (data?.url) window.location.href = data.url
  } catch {
    loading.value = false
  }
}
</script>

<template>
  <div class="relative flex-1 bg-[#0d1117] overflow-hidden">
    <div class="absolute inset-0 bg-gradient-to-br from-blue-600/20 to-purple-600/20 blur-3xl opacity-40"></div>

    <LoadingOverlay :show="loading" :text="$t('auth.loading')" />

    <div class="relative w-full max-w-md mx-auto px-4 py-10 sm:py-16 flex flex-col">
      <div class="flex-1 flex items-start sm:items-center justify-center sm:pt-6">
        <Transition
            appear
            enter-active-class="transition duration-500 ease-out"
            enter-from-class="opacity-0 translate-y-3"
            enter-to-class="opacity-100 translate-y-0"
        >
          <div
              class="relative w-full p-6 sm:p-8 rounded-2xl border border-white/20 bg-white/10 backdrop-blur-xl shadow-2xl text-white"
          >
            <h1 class="text-2xl sm:text-3xl font-semibold mb-6 text-center drop-shadow">
              {{ $t("auth.register.title") }}
            </h1>

            <form class="space-y-5" @submit.prevent="onSubmit">
              <fieldset :disabled="loading" class="space-y-5">
                <div>
                  <BaseInput
                      v-model="email"
                      :label="$t('auth.email')"
                      autocomplete="email"
                      :error="errors.email ? $t(errors.email) : null"
                  />
                  <p class="text-xs text-white/50 mt-1">
                    {{ $t("auth.hints.email_format") }}
                  </p>
                </div>

                <div>
                  <BaseInput
                      v-model="password"
                      :label="$t('auth.password')"
                      type="password"
                      autocomplete="new-password"
                      :error="errors.password ? $t(errors.password) : null"
                  />
                  <p class="text-xs text-white/50 mt-1">
                    {{ $t("auth.hints.password_min") }}
                  </p>
                </div>

                <div>
                  <BaseInput
                      v-model="confirmPassword"
                      :label="$t('auth.password_confirm')"
                      type="password"
                      autocomplete="new-password"
                      :error="errors.confirmPassword ? $t(errors.confirmPassword) : null"
                  />
                  <p class="text-xs text-white/50 mt-1">
                    {{ $t("auth.hints.password_confirm") }}
                  </p>
                </div>

                <p v-if="globalError" class="text-red-300 text-center text-sm">
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
                  {{ $t("auth.register.submit") }}
                </button>
              </fieldset>
            </form>

            <div class="my-7">
              <div class="h-px w-full bg-white/15"></div>
            </div>

            <div class="flex flex-col gap-3">
              <button
                  type="button"
                  @click="redirectToGoogle"
                  :disabled="loading"
                  class="w-full py-3 rounded-xl bg-white text-slate-900 font-medium shadow-lg
                       hover:bg-slate-100 active:scale-[0.99] transition
                       disabled:opacity-60 disabled:cursor-not-allowed
                       inline-flex items-center justify-center gap-3"
              >
                <svg width="18" height="18" viewBox="0 0 48 48" aria-hidden="true">
                  <path fill="#EA4335" d="M24 9.5c3.15 0 5.95 1.08 8.16 3.2l6.08-6.08C34.6 3.05 29.7 1 24 1 14.7 1 6.86 6.38 3.23 14.2l7.4 5.75C12.4 14.1 17.75 9.5 24 9.5z"/>
                  <path fill="#4285F4" d="M46.1 24.5c0-1.55-.14-3.04-.4-4.5H24v8.52h12.4c-.53 2.84-2.16 5.25-4.6 6.88l7.06 5.46c4.12-3.8 6.24-9.4 6.24-16.36z"/>
                  <path fill="#FBBC05" d="M10.63 28.05c-.5-1.48-.78-3.07-.78-4.7s.28-3.22.78-4.7l-7.4-5.75C1.8 16.1 1 19 1 23.35S1.8 30.6 3.23 33.8l7.4-5.75z"/>
                  <path fill="#34A853" d="M24 46.7c5.7 0 10.6-1.88 14.13-5.1l-7.06-5.46c-1.96 1.32-4.47 2.1-7.07 2.1-6.25 0-11.6-4.6-13.37-10.7l-7.4 5.75C6.86 41.62 14.7 46.7 24 46.7z"/>
                </svg>
                <span>{{ $t("auth.register.google") }}</span>
              </button>

              <button
                  type="button"
                  @click="redirectToFacebook"
                  :disabled="loading"
                  class="w-full py-3 rounded-xl bg-[#1877F2] text-white font-medium shadow-lg
                       hover:opacity-95 active:scale-[0.99] transition
                       disabled:opacity-60 disabled:cursor-not-allowed
                       inline-flex items-center justify-center gap-3"
              >
                <svg width="18" height="18" viewBox="0 0 24 24" fill="white" aria-hidden="true">
                  <path d="M22 12a10 10 0 1 0-11.56 9.87v-6.99H7.9V12h2.54V9.8c0-2.5 1.5-3.89 3.77-3.89 1.09 0 2.23.2 2.23.2v2.46h-1.26c-1.24 0-1.62.77-1.62 1.56V12h2.76l-.44 2.88h-2.32v6.99A10 10 0 0 0 22 12z"/>
                </svg>
                <span>{{ $t("auth.register.facebook") }}</span>
              </button>
            </div>
          </div>
        </Transition>
      </div>
    </div>
  </div>
</template>
