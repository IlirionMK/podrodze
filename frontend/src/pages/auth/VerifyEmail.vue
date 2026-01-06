<script setup>
import { ref, onMounted, computed } from "vue"
import { useRoute, useRouter } from "vue-router"

const route = useRoute()
const router = useRouter()

const loading = ref(true)
const status = ref("loading") // loading | success | already | invalid | error
const messageKey = ref(null)

const verifyUrl = computed(() => route.query.url)
const verifyStatus = computed(() => route.query.status)

function mapStatusToMessage(s) {
  if (s === "verified") return { st: "success", key: "auth.verify.success" }
  if (s === "already") return { st: "already", key: "auth.verify.already_verified" }
  if (s === "invalid") return { st: "invalid", key: "auth.verify.invalid_link" }
  if (s === "error") return { st: "error", key: "auth.verify.error" }
  return null
}

async function runVerify() {
  loading.value = true
  status.value = "loading"
  messageKey.value = null

  // Mode A: backend redirected to SPA with status=...
  if (verifyStatus.value && typeof verifyStatus.value === "string") {
    const mapped = mapStatusToMessage(verifyStatus.value)
    if (mapped) {
      status.value = mapped.st
      messageKey.value = mapped.key
      loading.value = false
      return
    }
  }

  // Mode B: SPA was opened with url=... (frontend calls API itself)
  if (!verifyUrl.value || typeof verifyUrl.value !== "string") {
    status.value = "invalid"
    messageKey.value = "auth.verify.invalid_link"
    loading.value = false
    return
  }

  try {
    const res = await fetch(verifyUrl.value, {
      method: "GET",
      headers: { Accept: "application/json" },
    })

    const data = await res.json().catch(() => null)

    if (!res.ok) {
      status.value = "invalid"
      messageKey.value = "auth.verify.invalid_link"
      loading.value = false
      return
    }

    if (data?.code === "already_verified") {
      status.value = "already"
      messageKey.value = "auth.verify.already_verified"
    } else if (data?.code === "verified") {
      status.value = "success"
      messageKey.value = "auth.verify.success"
    } else {
      status.value = "success"
      messageKey.value = "auth.verify.success"
    }
  } catch (e) {
    status.value = "error"
    messageKey.value = "auth.verify.error"
  } finally {
    loading.value = false
  }
}

onMounted(runVerify)

function goLogin() {
  router.push({ name: "auth.login" })
}

function goHome() {
  router.push({ name: "guest.home" })
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
          class="relative w-full max-w-md p-8 rounded-2xl bg-white/10 backdrop-blur-xl border border-white/20 shadow-2xl text-white"
      >
        <h1 class="text-3xl font-semibold mb-4 text-center drop-shadow">
          {{ $t("auth.verify.title") }}
        </h1>

        <p class="text-white/80 text-center mb-6">
          <span v-if="loading">{{ $t("auth.loading") }}</span>
          <span v-else-if="messageKey">{{ $t(messageKey) }}</span>
        </p>

        <div v-if="!loading" class="flex flex-col gap-3">
          <button
              @click="goLogin"
              class="w-full py-3 rounded-xl text-lg font-medium bg-gradient-to-r from-blue-500 to-purple-600 hover:opacity-90 active:opacity-80 transition shadow-lg"
          >
            {{ $t("auth.verify.go_login") }}
          </button>

          <button
              v-if="status === 'invalid' || status === 'error'"
              @click="runVerify"
              class="w-full py-3 rounded-xl bg-white/10 border border-white/20 hover:bg-white/15 transition"
          >
            {{ $t("actions.refresh") }}
          </button>

          <button
              @click="goHome"
              class="w-full py-3 rounded-xl bg-white/10 border border-white/20 hover:bg-white/15 transition"
          >
            {{ $t("nav.dashboard") }}
          </button>
        </div>
      </div>
    </Transition>
  </div>
</template>
