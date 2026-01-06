<script setup>
import { ref, computed, onMounted } from "vue"
import { useI18n } from "vue-i18n"
import { Circle, Sparkles, Star, Save } from "lucide-vue-next"

import { fetchPreferences, updateMyPreferences } from "@/composables/api/preferences.js"

const props = defineProps({
  excludedSlugs: {
    type: Array,
    default: () => ["hotel", "airport", "station", "other"],
  },
})

const emit = defineEmits(["error"])

const { t, te } = useI18n()

function tr(key, fallback) {
  return te(key) ? t(key) : fallback
}

const prefCategories = ref([])
const prefScores = ref({})

const loading = ref(false)
const saving = ref(false)
const loadedOnce = ref(false)
const query = ref("")

const btnBase =
    "inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-medium transition disabled:opacity-50 disabled:cursor-not-allowed"
const btnPrimary =
    btnBase + " bg-gradient-to-r from-blue-600 to-purple-600 text-white hover:opacity-90 active:opacity-80 shadow"

function clampScore(v) {
  const n = Number.parseInt(v, 10)
  if (Number.isNaN(n)) return 0
  return Math.max(0, Math.min(2, n))
}

function normalizePreferenceResponse(payload) {
  const root = payload?.data ?? payload ?? {}
  const categories = Array.isArray(root.categories) ? root.categories : []
  const user = root.user && typeof root.user === "object" ? root.user : {}
  return { categories, user }
}

async function load() {
  if (loadedOnce.value) return

  loading.value = true
  try {
    const res = await fetchPreferences()
    const { categories, user } = normalizePreferenceResponse(res?.data)

    prefCategories.value = categories

    const nextScores = {}
    for (const c of categories) {
      const slug = c?.slug
      if (!slug) continue
      nextScores[slug] = clampScore(user?.[slug] ?? 0)
    }

    prefScores.value = nextScores
    loadedOnce.value = true
  } catch (e) {
    emit("error", e?.response?.data?.message || tr("errors.default", "Something went wrong."))
  } finally {
    loading.value = false
  }
}

function setScore(slug, score) {
  if (!slug) return
  prefScores.value = {
    ...prefScores.value,
    [slug]: clampScore(score),
  }
}

const excluded = computed(() => new Set((props.excludedSlugs || []).map((s) => String(s))))

const visibleCategories = computed(() => {
  const list = Array.isArray(prefCategories.value) ? prefCategories.value : []
  const q = query.value.trim().toLowerCase()

  return list
      .filter((c) => !excluded.value.has(String(c?.slug ?? "")))
      .filter((c) => {
        if (!q) return true
        const name = String(c?.name ?? "").toLowerCase()
        const slug = String(c?.slug ?? "").toLowerCase()
        return name.includes(q) || slug.includes(q)
      })
})

function scoreBtnClass(active) {
  return active
      ? "border-transparent text-white bg-gradient-to-r from-blue-600 to-purple-600 shadow"
      : "border-gray-200 bg-white text-gray-900 hover:bg-gray-50"
}

async function save() {
  saving.value = true
  try {
    await updateMyPreferences(prefScores.value)
  } catch (e) {
    emit("error", e?.response?.data?.message || e?.response?.data?.error || tr("errors.default", "Something went wrong."))
  } finally {
    saving.value = false
  }
}

onMounted(async () => {
  await load()
})
</script>

<template>
  <section class="bg-white p-6 rounded-2xl border shadow-sm">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-4">
      <div>
        <h2 class="text-xl font-semibold">{{ t("trip.tabs.preferences") }}</h2>
        <div class="text-sm text-gray-600">{{ t("trip.preferences.hint") }}</div>
      </div>

      <button type="button" :class="btnPrimary" @click="save" :disabled="loading || saving">
        <Save class="h-4 w-4" />
        {{ saving ? t("loading") : t("actions.save") }}
      </button>
    </div>

    <div class="mb-4">
      <input
          v-model="query"
          type="text"
          class="w-full h-11 px-4 rounded-xl border border-gray-200 bg-white focus:outline-none focus:ring-2 focus:ring-black/10"
          :placeholder="t('actions.search')"
          :disabled="loading || saving"
      />
    </div>

    <div v-if="loading" class="p-6 rounded-xl border bg-gray-50 text-gray-700">
      {{ t("loading") }}…
    </div>

    <div v-else-if="visibleCategories.length === 0" class="p-6 rounded-xl border bg-gray-50 text-gray-700">
      <div class="font-semibold mb-1">{{ t("trip.preferences.empty_title") }}</div>
      <div class="text-sm text-gray-600">{{ t("trip.preferences.empty_hint") }}</div>
    </div>

    <div v-else class="space-y-3">
      <div
          v-for="c in visibleCategories"
          :key="c.slug"
          class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 p-4 rounded-2xl border border-gray-200"
      >
        <div class="min-w-0">
          <div class="font-medium text-gray-900">{{ c.name }}</div>
          <div class="text-xs text-gray-500">{{ c.slug }}</div>
        </div>

        <div class="flex items-center gap-2 justify-center sm:justify-end">
          <button
              type="button"
              class="h-11 w-11 rounded-xl border transition flex items-center justify-center"
              :class="scoreBtnClass(prefScores[c.slug] === 0)"
              :title="tr('trip.preferences.score_0', 'Nieważne')"
              :aria-label="tr('trip.preferences.score_0', 'Nieważne')"
              @click="setScore(c.slug, 0)"
              :disabled="saving"
          >
            <Circle class="h-5 w-5" />
          </button>

          <button
              type="button"
              class="h-11 w-11 rounded-xl border transition flex items-center justify-center"
              :class="scoreBtnClass(prefScores[c.slug] === 1)"
              :title="tr('trip.preferences.score_1', 'Miło, gdyby było')"
              :aria-label="tr('trip.preferences.score_1', 'Miło, gdyby było')"
              @click="setScore(c.slug, 1)"
              :disabled="saving"
          >
            <Sparkles class="h-5 w-5" />
          </button>

          <button
              type="button"
              class="h-11 w-11 rounded-xl border transition flex items-center justify-center"
              :class="scoreBtnClass(prefScores[c.slug] === 2)"
              :title="tr('trip.preferences.score_2', 'Bardzo ważne')"
              :aria-label="tr('trip.preferences.score_2', 'Bardzo ważne')"
              @click="setScore(c.slug, 2)"
              :disabled="saving"
          >
            <Star class="h-5 w-5" />
          </button>
        </div>
      </div>
    </div>
  </section>
</template>
