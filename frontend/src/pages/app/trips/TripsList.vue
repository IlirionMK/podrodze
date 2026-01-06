<script setup>
import { ref, onMounted } from "vue"
import { useI18n } from "vue-i18n"
import { RefreshCw, Plus } from "lucide-vue-next"
import { fetchUserTrips } from "@/composables/api/trips"

const { t, te, locale } = useI18n()

function tr(key, fallback) {
  return te(key) ? t(key) : fallback
}

function getErrMessage(err) {
  return err?.response?.data?.message || tr("errors.default", "Something went wrong.")
}

const trips = ref([])
const loading = ref(true)
const errorMsg = ref("")
const refreshing = ref(false)

const btnPrimary =
    "inline-flex items-center justify-center gap-2 rounded-full px-5 py-2.5 text-sm font-semibold " +
    "bg-gradient-to-r from-blue-600 to-purple-600 text-white shadow-lg " +
    "hover:opacity-95 active:opacity-90 transition " +
    "focus:outline-none focus:ring-2 focus:ring-blue-500/30"

const btnPrimaryIcon =
    "inline-flex items-center justify-center rounded-full w-11 h-11 " +
    "bg-gradient-to-r from-blue-600 to-purple-600 text-white shadow-lg " +
    "hover:opacity-95 active:opacity-90 transition " +
    "disabled:opacity-50 disabled:cursor-not-allowed " +
    "focus:outline-none focus:ring-2 focus:ring-blue-500/30"

function parseISO(value) {
  if (!value) return null
  const d = new Date(value)
  return Number.isNaN(d.getTime()) ? null : d
}

function formatShortDate(value) {
  const d = parseISO(value)
  if (!d) return "—"
  try {
    return new Intl.DateTimeFormat(locale.value || "en", {
      day: "2-digit",
      month: "short",
    }).format(d)
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

  try {
    const fmt = new Intl.DateTimeFormat(locale.value || "en", {
      day: "2-digit",
      month: "short",
      year: "numeric",
    })
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

async function loadTrips({ silent = false } = {}) {
  if (!silent) loading.value = true
  errorMsg.value = ""

  try {
    const res = await fetchUserTrips()
    trips.value = res.data?.data ?? res.data ?? []
  } catch (e) {
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

onMounted(() => loadTrips())
</script>

<template>
  <div class="max-w-6xl mx-auto px-4 py-8">
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5 sm:p-6">
      <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
        <div class="min-w-0">
          <h1 class="text-lg sm:text-xl font-semibold text-gray-900">
            {{ tr("trips.title", "Moje Podróże") }}
          </h1>
          <p class="mt-1 text-sm text-gray-600">
            {{ tr("trips.subtitle", "Wybierz podróż, aby zobaczyć szczegóły i planować aktywności") }}
          </p>
        </div>

        <div class="flex items-center gap-2 w-full md:w-auto">
          <button
              type="button"
              :class="btnPrimaryIcon"
              :disabled="loading || refreshing"
              @click="refresh"
              :aria-label="tr('actions.refresh', 'Odśwież')"
              :title="tr('actions.refresh', 'Odśwież')"
          >
            <RefreshCw class="h-4 w-4" :class="refreshing ? 'animate-spin' : ''" />
          </button>

          <router-link
              :to="{ name: 'app.trips.create' }"
              :class="btnPrimary + ' w-full md:w-auto'"
          >
            <Plus class="h-4 w-4" />
            {{ tr("trips.new", "Dodaj podróż") }}
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

      <div v-else-if="trips.length === 0" class="mt-6">
        <div class="rounded-2xl border border-gray-200 bg-gray-50 p-6 text-gray-700">
          <div class="text-lg font-semibold">
            {{ tr("trips.empty_title", "Brak podróży") }}
          </div>
          <div class="mt-1 text-sm text-gray-600">
            {{ tr("trips.empty", "Nie masz jeszcze żadnych podróży. Utwórz pierwszą, aby zacząć planować.") }}
          </div>

          <div class="mt-4">
            <router-link :to="{ name: 'app.trips.create' }" :class="btnPrimary">
              <Plus class="h-4 w-4" />
              {{ tr("trips.new", "Dodaj podróż") }}
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
            v-for="trip in trips"
            :key="trip.id"
            type="button"
            class="text-left bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden
                 hover:shadow-md hover:-translate-y-0.5 transform transition"
            @click="$router.push({ name: 'app.trips.show', params: { id: trip.id } })"
        >
          <div class="h-36 bg-gray-100 relative">
            <img
                v-if="trip.cover_url || trip.image_url"
                :src="trip.cover_url || trip.image_url"
                alt=""
                class="absolute inset-0 w-full h-full object-cover"
            />
            <div v-else class="absolute inset-0 bg-gradient-to-br from-blue-500/30 to-purple-500/20"></div>
            <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-black/10 to-transparent"></div>

            <div class="absolute left-4 right-4 bottom-4">
              <div class="text-white font-semibold text-lg drop-shadow line-clamp-1">
                {{ trip.name || tr("trips.unnamed", "Podróż") }}
              </div>
              <div class="text-white/90 text-sm line-clamp-1">
                {{ trip.country || trip.location || "" }}
              </div>
            </div>
          </div>

          <div class="p-4">
            <div class="flex items-center justify-between gap-3">
              <div class="text-sm text-gray-700">
                {{ formatDateRange(trip.start_date, trip.end_date) }}
              </div>

              <div
                  v-if="tripDays(trip.start_date, trip.end_date)"
                  class="text-xs px-2 py-1 rounded-lg bg-gray-100 text-gray-700"
              >
                {{ tripDays(trip.start_date, trip.end_date) }} {{ tr("trips.days_short", "dni") }}
              </div>
            </div>

            <div class="mt-3 flex items-center justify-between">
              <div class="text-xs text-gray-500">
                <span v-if="trip.activities_count != null">
                  {{ trip.activities_count }} {{ tr("trips.activities", "aktywności") }}
                </span>
              </div>

              <div class="text-sm font-medium text-gray-900">
                {{ tr("trips.details", "Zobacz szczegóły") }} →
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
