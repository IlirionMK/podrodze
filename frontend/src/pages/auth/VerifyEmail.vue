<script setup>
import { ref, computed, watch } from "vue"
import { useRoute, useRouter } from "vue-router"
import { useAuth } from "@/composables/useAuth"

const route = useRoute()
const router = useRouter()
const { isAuthenticated } = useAuth()

const loading = ref(true)
const status = ref("loading")
const messageKey = ref(null)

const verifyUrlRaw = computed(() => route.query.url)
const verifyStatus = computed(() => route.query.status)

const apiBase = (import.meta.env.VITE_API_URL || "").replace(/\/$/, "")
let apiOrigin = ""
try {
  apiOrigin = apiBase ? new URL(apiBase).origin : ""
} catch {
  apiOrigin = ""
}

function mapStatusToMessage(s) {
  if (s === "verified") return { st: "success", key: "auth.verify.success" }
  if (s === "already") return { st: "already", key: "auth.verify.already_verified" }
  if (s === "invalid") return { st: "invalid", key: "auth.verify.invalid_link" }
  if (s === "error") return { st: "error", key: "auth.verify.error" }
  return null
}

function normalizeAllowedUrl(raw) {
  if (!raw || typeof raw !== "string") return null
  const trimmed = raw.trim()
  if (!trimmed) return null

  if (trimmed.startsWith("/")) {
    if (!trimmed.startsWith("/api")) return null
    return trimmed
  }

  if (trimmed.startsWith("http://") || trimmed.startsWith("https://")) {
    if (apiBase && trimmed.startsWith(apiBase)) return trimmed
    if (apiOrigin && trimmed.startsWith(apiOrigin)) return trimmed
    return null
  }

  return null
}

async function runVerify() {
  loading.value = true
  status.value = "loading"
  messageKey.value = null

  const qs = verifyStatus.value
  if (qs && typeof qs === "string") {
    const mapped = mapStatusToMessage(qs)
    if (mapped) {
      status.value = mapped.st
      messageKey.value = mapped.key
      loading.value = false
      return
    }
  }

  const safeUrl = normalizeAllowedUrl(verifyUrlRaw.value)
  if (!safeUrl) {
    status.value = "invalid"
    messageKey.value = "auth.verify.invalid_link"
    loading.value = false
    return
  }

  try {
    const res = await fetch(safeUrl, {
      method: "GET",
      headers: { Accept: "application/json" },
    })

    const data = await res.json().catch(() => null)

    if (!res.ok) {
      status.value = "invalid"
      messageKey.value = "auth.verify.invalid_link"
      return
    }

    if (data?.code === "already_verified") {
      status.value = "already"
      messageKey.value = "auth.verify.already_verified"
      return
    }

    status.value = "success"
    messageKey.value = "auth.verify.success"
  } catch {
    status.value = "error"
    messageKey.value = "auth.verify.error"
  } finally {
    loading.value = false
  }
}

watch(
    () => route.query,
    () => runVerify(),
    { immediate: true }
)

function goHome() {
  router.push({ name: isAuthenticated.value ? "app.home" : "guest.home" })
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
      <div class="relative w-full max-w-md p-8 rounded-2xl bg-white/10 backdrop-blur-xl border border-white/20 shadow-2xl text-white">
        <h1 class="text-3xl font-semibold mb-4 text-center drop-shadow">
          {{ $t("auth.verify.title") }}
        </h1>

        <div class="flex flex-col items-center justify-center gap-4 mb-6 min-h-[84px]">
          <div
              v-if="loading"
              class="w-12 h-12 border-4 border-white/20 border-t-blue-400 rounded-full animate-spin"
          ></div>

          <p class="text-white/80 text-center">
            <span v-if="loading">{{ $t("auth.loading") }}</span>
            <span v-else-if="messageKey">{{ $t(messageKey) }}</span>
          </p>
        </div>

        <button
            v-if="!loading"
            @click="goHome"
            class="w-full py-3 rounded-xl text-lg font-medium bg-gradient-to-r from-blue-500 to-purple-600 hover:opacity-90 active:opacity-80 transition shadow-lg"
        >
          {{ $t("nav.home") }}
        </button>
      </div>
    </Transition>
  </div>
</template>
