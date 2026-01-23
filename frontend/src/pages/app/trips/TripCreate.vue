<script setup>
import { ref, computed } from "vue"
import { useRouter } from "vue-router"
import { useI18n } from "vue-i18n"
import { ArrowLeft, Plus } from "lucide-vue-next"
import { createTrip } from "@/composables/api/trips.js"

const router = useRouter()
const { t, te } = useI18n()

function tr(key, fallback) {
  return te(key) ? t(key) : fallback
}

const name = ref("")
const startDate = ref("")
const endDate = ref("")
const loading = ref(false)
const errorMsg = ref("")

const inputBase =
    "w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-gray-900 " +
    "placeholder:text-gray-400 outline-none " +
    "focus:ring-2 focus:ring-blue-500/20 focus:border-blue-300 transition"

const dateOrderInvalid = computed(() => {
  return !!(startDate.value && endDate.value && startDate.value > endDate.value)
})

const canSubmit = computed(() => {
  if (loading.value) return false
  if (!name.value.trim()) return false

  // требуем обе даты
  if (!startDate.value || !endDate.value) return false

  // и корректный порядок
  return !dateOrderInvalid.value
})

async function createTripHandler() {
  if (!canSubmit.value) return

  loading.value = true
  errorMsg.value = ""

  try {
    const response = await createTrip({
      name: name.value.trim(),
      start_date: startDate.value,
      end_date: endDate.value,
    })

    const id = response.data?.data?.id ?? response.data?.id
    if (id) return router.push({ name: "app.trips.show", params: { id } })

    errorMsg.value = tr("errors.default", "Something went wrong. Please try again.")
  } catch (err) {
    errorMsg.value = err?.response?.data?.message || tr("errors.default", "Something went wrong. Please try again.")
  } finally {
    loading.value = false
  }
}

function goBack() {
  router.push({ name: "app.trips" })
}
</script>

<template>
  <div class="max-w-3xl mx-auto px-4 py-8">
    <div class="card-surface card-pad">
      <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
        <div class="min-w-0">
          <h1 class="text-lg sm:text-xl font-semibold text-gray-900">
            {{ tr("trip.create.title", "Create trip") }}
          </h1>
          <p class="mt-1 text-sm text-gray-600">
            {{ tr("trip.create.subtitle", "Set a name and dates to start planning.") }}
          </p>
        </div>

        <div class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto min-w-0">
          <button type="button" class="btn-back w-full sm:w-auto" @click="goBack" :disabled="loading">
            <ArrowLeft class="h-4 w-4" />
            {{ tr("actions.back", "Back") }}
          </button>

          <button
              type="button"
              class="btn-primary w-full sm:w-auto"
              @click="createTripHandler"
              :disabled="!canSubmit"
              :aria-disabled="!canSubmit"
          >
            <Plus class="h-4 w-4" />
            {{ loading ? tr("trip.create.creating", "Creating...") : tr("trip.create.button", "Create trip") }}
          </button>
        </div>
      </div>

      <div class="mt-6 grid grid-cols-1 gap-5 text-gray-800">
        <div>
          <label class="block text-sm font-medium text-gray-700">
            {{ tr("trip.fields.name", "Name") }}
          </label>
          <input
              v-model="name"
              :placeholder="tr('trip.fields.name_placeholder', 'Trip name')"
              :class="inputBase"
              autocomplete="off"
              :disabled="loading"
          />
          <p class="mt-1 text-xs text-gray-500">
            {{ tr("trip.create.name_hint", "Example: Barcelona weekend, Summer in Italy...") }}
          </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700">
              {{ tr("trip.fields.start_date", "Start date") }}
            </label>
            <input v-model="startDate" type="date" :class="inputBase" :disabled="loading" />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700">
              {{ tr("trip.fields.end_date", "End date") }}
            </label>
            <input v-model="endDate" type="date" :class="inputBase" :disabled="loading" />
          </div>
        </div>

        <div
            v-if="dateOrderInvalid"
            class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
        >
          {{ tr("trip.create.errors.date_order", "End date must be the same as or after start date.") }}
        </div>

        <div v-if="errorMsg" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
          {{ errorMsg }}
        </div>
      </div>
    </div>
  </div>
</template>
