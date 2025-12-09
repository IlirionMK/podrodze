<script setup>
import { ref } from "vue"
import { useRouter } from "vue-router"
import { useI18n } from "vue-i18n"
import tripsApi from "@/composables/api/trips.js"

const router = useRouter()
const { t } = useI18n()

const name = ref("")
const startDate = ref("")
const endDate = ref("")
const loading = ref(false)
const errorMsg = ref("")
const successMsg = ref("")

async function createTrip() {
  loading.value = true
  errorMsg.value = ""
  successMsg.value = ""

  try {
    const response = await tripsApi.createTrip({
      name: name.value,
      start_date: startDate.value || null,
      end_date: endDate.value || null,
    })

    const id = response.data.data.id
    router.push(`/app/trips/${id}`)

  } catch (err) {
    errorMsg.value = err.response?.data?.message || t("errors.default")
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="p-4 md:p-8 max-w-2xl mx-auto">

    <h1 class="text-2xl font-semibold mb-6">
      {{ t("trip.create.title") }}
    </h1>

    <div class="rounded-xl border border-gray-300 p-4 md:p-6 bg-white shadow-sm">

      <div class="flex flex-col gap-5 text-gray-700">

        <!-- Name -->
        <div>
          <label class="font-medium">{{ t("trip.fields.name") }}</label>
          <input
              v-model="name"
              class="w-full mt-1 border rounded-lg p-2"
              :placeholder="t('trip.fields.name_placeholder')"
          />
        </div>

        <!-- Dates -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="font-medium">{{ t("trip.fields.start_date") }}</label>
            <input v-model="startDate" type="date" class="w-full mt-1 border rounded-lg p-2" />
          </div>

          <div>
            <label class="font-medium">{{ t("trip.fields.end_date") }}</label>
            <input v-model="endDate" type="date" class="w-full mt-1 border rounded-lg p-2" />
          </div>
        </div>

        <button
            @click="createTrip"
            :disabled="loading"
            class="bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700 transition disabled:opacity-50"
        >
          {{ loading ? t("trip.create.creating") : t("trip.create.button") }}
        </button>

        <p v-if="errorMsg" class="text-red-600">{{ errorMsg }}</p>
        <p v-if="successMsg" class="text-green-600">{{ successMsg }}</p>

      </div>

    </div>

  </div>
</template>
