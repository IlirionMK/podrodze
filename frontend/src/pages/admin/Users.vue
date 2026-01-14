<script setup>
import { ref, onMounted } from "vue"
import { useI18n } from "vue-i18n"
import axios from "axios"

const { t } = useI18n({ useScope: "global" })

const users = ref([])
const loading = ref(false)

async function fetchUsers() {
  loading.value = true
  try {
    const res = await axios.get("/api/admin/users")
    users.value = res.data
  } catch (err) {
    console.error("Błąd pobierania użytkowników:", err)
  } finally {
    loading.value = false
  }
}

function deleteUser(id) {

  if (!confirm(t("app.admin.users.delete_confirm"))) return
  axios.delete(`/api/admin/users/${id}`).then(() => fetchUsers())
}

onMounted(fetchUsers)
</script>

<template>
  <div class="min-h-screen p-6 bg-gray-100">
    <div class="flex justify-between items-center mb-4">
      <h1 class="text-2xl font-bold">{{ t("app.admin.menu.users") }}</h1>
      <button class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
        {{ t("app.admin.users.add") }}
      </button>
    </div>

    <table class="w-full text-left bg-white rounded-lg shadow overflow-hidden">
      <thead class="bg-gray-200">
        <tr>
          <th class="px-4 py-2">ID</th>
          <th class="px-4 py-2">{{ t("app.admin.users.name") }}</th>
          <th class="px-4 py-2">{{ t("app.admin.users.email") }}</th>
          <th class="px-4 py-2">{{ t("app.admin.users.actions") }}</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="user in users" :key="user.id" class="border-b hover:bg-gray-50">
          <td class="px-4 py-2">{{ user.id }}</td>
          <td class="px-4 py-2">{{ user.name }}</td>
          <td class="px-4 py-2">{{ user.email }}</td>
          <td class="px-4 py-2 space-x-2">
            <button class="px-2 py-1 bg-yellow-400 text-white rounded hover:bg-yellow-500">
              {{ t("app.admin.edit") }}
            </button>
            <button class="px-2 py-1 bg-red-600 text-white rounded hover:bg-red-700" @click="deleteUser(user.id)">
              {{ t("app.admin.delete") }}
            </button>
          </td>
        </tr>
        <tr v-if="loading">
          <td colspan="4" class="text-center p-4">{{ t("loading") }}</td>
        </tr>
        <tr v-if="!loading && users.length === 0">
          <td colspan="4" class="text-center p-4">{{ t("app.admin.users.no_users") }}</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
