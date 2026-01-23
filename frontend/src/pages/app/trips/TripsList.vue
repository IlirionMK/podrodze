<script setup>
import { ref, shallowRef, computed, onMounted, onUnmounted } from "vue"
import { useI18n } from "vue-i18n"
import { useRouter } from "vue-router"
import { RefreshCw, Plus } from "lucide-vue-next"
import { fetchUserTrips } from "@/composables/api/trips"

const { t, te, locale } = useI18n()
const router = useRouter()

function tr(key, fallback) {
  return te(key) ? t(key) : fallback
}

function getErrMessage(err) {
  return err?.response?.data?.message || tr("errors.default", "Something went wrong. Please try again.")
}

const trips = shallowRef([])
const loading = ref(true)
const errorMsg = ref("")
const refreshing = ref(false)

const CACHE_KEY = "podrodze_trips_cache_v1"
const CACHE_TTL_MS = 60_000

let abortController = null

function safeJsonParse(value) {
  try {
    return JSON.parse(value)
  } catch {
    return null
  }
}

function readCache() {
  const raw = sessionStorage.getItem(CACHE_KEY)
  const parsed = raw ? safeJsonParse(raw) : null
  if (!parsed || !Array.isArray(parsed.data) || typeof parsed.ts !== "number") return null
  return parsed
}

function writeCache(data) {
  try {
    sessionStorage.setItem(CACHE_KEY, JSON.stringify({ ts: Date.now(), data }))
  } catch {}
}

function parseISO(value) {
  if (!value) return null
  const d = new Date(value)
  return Number.isNaN(d.getTime()) ? null : d
}

const dateFmtShort = computed(() => {
  try {
    return new Intl.DateTimeFormat(locale.value || "en", { day: "2-digit", month: "short" })
  } catch {
    return null
  }
})

const dateFmtLong = computed(() => {
  try {
    return new Intl.DateTimeFormat(locale.value || "en", { day: "2-digit", month: "short", year: "numeric" })
  } catch {
    return null
  }
})

function formatShortDate(value) {
  const d = parseISO(value)
  if (!d) return "—"
  const fmt = dateFmtShort.value
  if (!fmt) return d.toISOString().slice(0, 10)
  try {
    return fmt.format(d)
  } catch {
    return d.toISOString().slice(0, 10)
  }
}

function formatDateRange(start, end) {
  const s = parseISO(start)
  const e = parseISO(end)

  if (!s && !e) return "—"
  if (s && !e) return formatShortDate(start)
  if (!s && e) return formatShortDate(end)

  if (s.getFullYear() === e.getFullYear()) {
    return `${formatShortDate(start)} – ${formatShortDate(end)}`
  }

  const fmt = dateFmtLong.value
  if (!fmt) return `${s.toISOString().slice(0, 10)} – ${e.toISOString().slice(0, 10)}`

  try {
    return `${fmt.format(s)} – ${fmt.format(e)}`
  } catch {
    return `${s.toISOString().slice(0, 10)} – ${e.toISOString().slice(0, 10)}`
  }
}

function tripDays(start, end) {
  const s = parseISO(start)
  const e = parseISO(end)
  if (!s || !e) return null
  const diff = Math.ceil((e - s) / (1000 * 60 * 60 * 24)) + 1
  return Number.isFinite(diff) ? diff : null
}

const viewTrips = computed(() => {
  const list = trips.value || []
  return list.map((trip) => {
    const imgUrl = trip.cover_url || trip.image_url || ""
    const title = trip.name || tr("trips.unnamed", "Trip")
    const subtitle = trip.country || trip.location || ""
    const dateRange = formatDateRange(trip.start_date, trip.end_date)
    const days = tripDays(trip.start_date, trip.end_date)

    return {
      id: trip.id,
      imgUrl,
      title,
      subtitle,
      dateRange,
      days,
      activitiesCount: trip.activities_count,
    }
  })
})

function openTrip(id) {
  router.push({ name: "app.trips.show", params: { id } })
}

async function loadTrips({ silent = false } = {}) {
  if (!silent) loading.value = true
  errorMsg.value = ""

  if (abortController) abortController.abort()
  abortController = new AbortController()

  try {
    const res = await fetchUserTrips({ signal: abortController.signal })
    const data = res.data?.data ?? res.data ?? []
    trips.value = Array.isArray(data) ? data : []
    writeCache(trips.value)
  } catch (e) {
    if (e?.name === "AbortError") return
    errorMsg.value = getErrMessage(e)
  } finally {
    if (!silent) loading.value = false
  }
}

async function refresh() {
  if (refreshing.value) return
  refreshing.value = true
  try {
    await loadTrips({ silent: true })
  } finally {
    refreshing.value = false
  }
}

onMounted(() => {
  const cached = readCache()
  if (cached?.data?.length) {
    trips.value = cached.data
    loading.value = false
    loadTrips({ silent: true })
    if (Date.now() - cached.ts > CACHE_TTL_MS) refresh()
    return
  }
  loadTrips()
})

onUnmounted(() => {
  if (abortController) abortController.abort()
})
</script>

<template>
  <div class="max-w-6xl mx-auto px-4 py-8">
    <div class="card-surface card-pad">
      <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
        <div class="min-w-0">
          <h1 class="text-lg sm:text-xl font-semibold text-gray-900">
            {{ tr("trips.title", "My trips") }}
          </h1>
          <p class="mt-1 text-sm text-gray-600">
            {{ tr("trips.subtitle", "Choose a trip to view details and plan activities") }}
          </p>
        </div>

        <div class="flex items-center gap-2 w-full md:w-auto min-w-0">
          <button
              type="button"
              class="btn-primary-icon"
              :disabled="loading || refreshing"
              @click="refresh"
              :aria-label="tr('actions.refresh', 'Refresh')"
              :title="tr('actions.refresh', 'Refresh')"
          >
            <RefreshCw class="h-4 w-4" :class="refreshing ? 'animate-spin' : ''" />
          </button>

          <router-link
              :to="{ name: 'app.trips.create' }"
              class="btn-primary flex-1 md:flex-none"
          >
            <Plus class="h-4 w-4" />
            {{ tr("trips.new", "Add trip") }}
          </router-link>
        </div>
      </div>

      <div v-if="loading" class="mt-6">
        <div class="animate-pulse space-y-4">
          <div class="h-4 w-56 bg-gray-200 rounded"></div>
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <div
                v-for="i in 6"
                :key="i"
                class="rounded-2xl border border-gray-200 shadow-sm overflow-hidden bg-white"
            >
              <div class="h-36 bg-gray-200"></div>
              <div class="p-4 space-y-3">
                <div class="h-4 w-2/3 bg-gray-200 rounded"></div>
                <div class="h-3 w-1/2 bg-gray-200 rounded"></div>
                <div class="flex items-center justify-between pt-2">
                  <div class="h-3 w-24 bg-gray-200 rounded"></div>
                  <div class="h-3 w-20 bg-gray-200 rounded"></div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div v-else-if="errorMsg" class="mt-6">
        <div class="p-4 bg-red-100 text-red-700 border border-red-300 rounded-xl max-w-xl">
          {{ errorMsg }}
        </div>
      </div>

      <div v-else-if="viewTrips.length === 0" class="mt-6">
        <div class="rounded-2xl border border-gray-200 bg-gray-50 p-6 text-gray-700">
          <div class="text-lg font-semibold">
            {{ tr("trips.empty_title", "No trips") }}
          </div>
          <div class="mt-1 text-sm text-gray-600">
            {{ tr("trips.empty", "You don’t have any trips yet. Create your first one to start planning.") }}
          </div>

          <div class="mt-4">
            <router-link :to="{ name: 'app.trips.create' }" class="btn-primary">
              <Plus class="h-4 w-4" />
              {{ tr("trips.new", "Add trip") }}
            </router-link>
          </div>
        </div>
      </div>

      <TransitionGroup
          v-else
          name="cards"
          tag="div"
          class="mt-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4"
      >
        <button
            v-for="trip in viewTrips"
            :key="trip.id"
            type="button"
            class="text-left bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden hover:shadow-md hover:-translate-y-0.5 transform transition"
            @click="openTrip(trip.id)"
        >
          <div class="h-36 bg-gray-100 relative">
            <img
                v-if="trip.imgUrl"
                :src="trip.imgUrl"
                alt=""
                class="absolute inset-0 w-full h-full object-cover"
                loading="lazy"
                decoding="async"
            />
            <div v-else class="absolute inset-0 bg-gradient-to-br from-blue-500/30 to-purple-500/20"></div>
            <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-black/10 to-transparent"></div>

            <div class="absolute left-4 right-4 bottom-4">
              <div class="text-white font-semibold text-lg drop-shadow line-clamp-1">
                {{ trip.title }}
              </div>
              <div class="text-white/90 text-sm line-clamp-1">
                {{ trip.subtitle }}
              </div>
            </div>
          </div>

          <div class="p-4">
            <div class="flex items-center justify-between gap-3">
              <div class="text-sm text-gray-700">
                {{ trip.dateRange }}
              </div>

              <div v-if="trip.days" class="text-xs px-2 py-1 rounded-lg bg-gray-100 text-gray-700">
                {{ trip.days }} {{ tr("trips.days_short", "days") }}
              </div>
            </div>

            <div class="mt-3 flex items-center justify-between">
              <div class="text-xs text-gray-500">
                <span v-if="trip.activitiesCount != null">
                  {{ trip.activitiesCount }} {{ tr("trips.activities", "activities") }}
                </span>
              </div>

              <div class="text-sm font-medium text-gray-900">
                {{ tr("trips.details", "View details") }} →
              </div>
            </div>
          </div>
        </button>
      </TransitionGroup>
    </div>
  </div>
</template>

<style scoped>
.cards-enter-active,
.cards-leave-active {
  transition: all 220ms ease;
}
.cards-enter-from,
.cards-leave-to {
  opacity: 0;
  transform: translateY(6px);
}
</style>
