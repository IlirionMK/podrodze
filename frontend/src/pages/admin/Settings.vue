<script setup>
import { ref, onMounted } from "vue"
import { useI18n } from "vue-i18n"
import axios from "axios"

const { t } = useI18n({ useScope: "global" })

const settings = ref([])
const loading = ref(false)

async function fetchSettings() {
  loading.value = true
  try {
    const res = await axios.get("/api/admin/settings")
    settings.value = res.data
  } catch (err) {
    console.error("Błąd pobierania ustawień:", err)
  } finally {
    loading.value = false
  }
}

function updateSetting(setting) {
  axios.put(`/api/admin/settings/${setting.id}`, setting)
}

onMounted(fetchSettings)
</script>

<template>
  <div class="min-h-screen p-6 bg-gray-100">
    <h1 class="text-2xl font-bold mb-4">{{ t("app.admin.menu.settings") }}</h1>

    <div class="bg-white p-6 rounded-lg shadow space-y-4">
      <div v-for="setting in settings" :key="setting.id" class="flex flex-col md:flex-row md:items-center gap-2">
        <label class="font-medium w-40">{{ setting.key }}</label>
        <input v-model="setting.value" class="flex-1 border rounded p-2" />
        <button @click="updateSetting(setting)" class="px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700">
          {{ t("app.admin.save") }}
        </button>
      </div>
      <div v-if="loading" class="text-center text-gray-500">{{ t("loading") }}</div>
    </div>
  </div>
</template>
