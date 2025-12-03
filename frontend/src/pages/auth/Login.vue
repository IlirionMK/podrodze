<script setup>
import { ref } from "vue"
import { useRouter } from "vue-router"
import { useAuth } from "@/composables/useAuth"

import BaseInput from "@/components/forms/BaseInput.vue"

const router = useRouter()
const { setToken, setUser } = useAuth()

const email = ref("")
const password = ref("")
const errorMessage = ref(null)
const loading = ref(false)

// -------------------------------------
// Validation
// -------------------------------------
function validateForm() {
  if (!email.value) {
    errorMessage.value = "auth.errors.email_required"
    return false
  }

  if (!email.value.includes("@")) {
    errorMessage.value = "auth.errors.invalid_email"
    return false
  }

  if (!password.value) {
    errorMessage.value = "auth.errors.password_required"
    return false
  }

  return true
}

// -------------------------------------
// Regular Login
// -------------------------------------
async function onSubmit() {
  errorMessage.value = null

  if (!validateForm()) return

  loading.value = true

  try {
    const res = await fetch(import.meta.env.VITE_API_URL + "/login", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        email: email.value,
        password: password.value
      })
    })

    const data = await res.json()

    if (!res.ok) {
      errorMessage.value = data.message || "auth.errors.login_failed"
      loading.value = false
      return
    }

    // Save token and fake user
    setToken(data.token)
    setUser({ name: email.value.split("@")[0] })

    loading.value = false

    // Redirect to intended route
    const intended = localStorage.getItem("intended")
    if (intended) {
      localStorage.removeItem("intended")
      return router.push(intended)
    }

    router.push({ name: "home" })

  } catch (e) {
    console.error(e)
    errorMessage.value = "auth.errors.network"
  } finally {
    loading.value = false
  }
}

// -------------------------------------
// Social Login Stubs
// -------------------------------------
function loginGoogleStub() {
  loading.value = true
  setTimeout(() => {
    setToken("google_stub_token_123")
    setUser({ name: "GoogleUser" })
    loading.value = false
    router.push({ name: "home" })
  }, 500)
}

function loginFacebookStub() {
  loading.value = true
  setTimeout(() => {
    setToken("facebook_stub_token_123")
    setUser({ name: "FacebookUser" })
    loading.value = false
    router.push({ name: "home" })
  }, 500)
}
</script>

<template>
  <div class="max-w-md w-full bg-white p-6 rounded shadow">

    <h1 class="text-2xl font-bold mb-4">
      {{ $t("auth.login.title") }}
    </h1>

    <form class="space-y-4" @submit.prevent="onSubmit">

      <BaseInput
          v-model="email"
          :label="$t('auth.email')"
          type="email"
          :error="errorMessage && errorMessage.includes('email') ? $t(errorMessage) : null"
      />

      <BaseInput
          v-model="password"
          :label="$t('auth.password')"
          type="password"
          :error="errorMessage && errorMessage.includes('password') ? $t(errorMessage) : null"
      />

      <!-- GLOBAL ERROR MESSAGE -->
      <p v-if="errorMessage && !errorMessage.includes('email') && !errorMessage.includes('password')"
         class="text-red-600 text-sm">
        {{ $t(errorMessage) }}
      </p>

      <button
          type="submit"
          :disabled="loading"
          class="w-full bg-blue-600 text-white py-2 rounded disabled:opacity-50"
      >
        {{ loading ? $t("auth.loading") : $t("auth.login.submit") }}
      </button>
    </form>

    <!-- Social Login Buttons -->
    <div class="mt-6 flex flex-col gap-3">

      <button
          @click="loginGoogleStub"
          :disabled="loading"
          class="w-full bg-red-600 text-white py-2 rounded disabled:opacity-50"
      >
        {{ $t("auth.login.google") }}
      </button>

      <button
          @click="loginFacebookStub"
          :disabled="loading"
          class="w-full bg-blue-700 text-white py-2 rounded disabled:opacity-50"
      >
        {{ $t("auth.login.facebook") }}
      </button>

    </div>

  </div>
</template>
