<script setup>
import { ref } from "vue"
import { useRouter } from "vue-router"
import { useAuth } from "@/composables/useAuth"

import BaseInput from "@/components/forms/BaseInput.vue"

const router = useRouter()
const { setToken, setUser } = useAuth()

const email = ref("")
const password = ref("")
const confirmPassword = ref("")

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

  if (password.value.length < 6) {
    errorMessage.value = "auth.errors.password_too_short"
    return false
  }

  if (password.value !== confirmPassword.value) {
    errorMessage.value = "auth.errors.password_mismatch"
    return false
  }

  return true
}


// -------------------------------------
// Register Stub
// -------------------------------------
async function onSubmit() {
  errorMessage.value = null

  if (!validateForm()) return

  loading.value = true

  setTimeout(() => {
    const fakeToken = "register_stub_token_123"

    setToken(fakeToken)
    setUser({ name: email.value.split("@")[0] })

    loading.value = false

    const intended = localStorage.getItem("intended")
    if (intended) {
      localStorage.removeItem("intended")
      return router.push(intended)
    }

    router.push({ name: "home" })

  }, 500)
}


// -------------------------------------
// Social Register (Google / Facebook)
// -------------------------------------
function registerGoogleStub() {
  loading.value = true
  setTimeout(() => {
    setToken("google_register_stub_token_123")
    setUser({ name: "GoogleUser" })
    loading.value = false
    router.push({ name: "home" })
  }, 500)
}

function registerFacebookStub() {
  loading.value = true
  setTimeout(() => {
    setToken("facebook_register_stub_token_123")
    setUser({ name: "FacebookUser" })
    loading.value = false
    router.push({ name: "home" })
  }, 500)
}
</script>




<template>
  <div class="max-w-md w-full bg-white p-6 rounded shadow">

    <h1 class="text-2xl font-bold mb-4">
      {{ $t("auth.register.title") }}
    </h1>

    <form class="space-y-4" @submit.prevent="onSubmit">

      <!-- EMAIL -->
      <BaseInput
          v-model="email"
          :label="$t('auth.email')"
          type="email"
          :error="errorMessage && errorMessage.includes('email') ? $t(errorMessage) : null"
      />

      <!-- PASSWORD -->
      <BaseInput
          v-model="password"
          :label="$t('auth.password')"
          type="password"
          :error="errorMessage && errorMessage.includes('password') ? $t(errorMessage) : null"
      />

      <!-- CONFIRM PASSWORD -->
      <BaseInput
          v-model="confirmPassword"
          :label="$t('auth.password_confirm')"
          type="password"
          :error="errorMessage && errorMessage.includes('mismatch') ? $t(errorMessage) : null"
      />

      <!-- GLOBAL ERROR -->
      <p
          v-if="errorMessage && !errorMessage.includes('email') && !errorMessage.includes('password')"
          class="text-red-600 text-sm"
      >
        {{ $t(errorMessage) }}
      </p>

      <!-- SUBMIT -->
      <button
          type="submit"
          :disabled="loading"
          class="w-full bg-blue-600 text-white py-2 rounded disabled:opacity-50"
      >
        {{ loading ? $t("auth.loading") : $t("auth.register.submit") }}
      </button>

    </form>

    <!-- Social Buttons -->
    <div class="mt-6 flex flex-col gap-3">

      <button
          @click="registerGoogleStub"
          :disabled="loading"
          class="w-full bg-red-600 text-white py-2 rounded disabled:opacity-50"
      >
        {{ $t("auth.register.google") }}
      </button>

      <button
          @click="registerFacebookStub"
          :disabled="loading"
          class="w-full bg-blue-700 text-white py-2 rounded disabled:opacity-50"
      >
        {{ $t("auth.register.facebook") }}
      </button>

    </div>

  </div>
</template>
