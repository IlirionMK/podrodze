<script setup>
import { useRouter } from "vue-router"
import { useI18n } from "vue-i18n"

const router = useRouter()
const { t } = useI18n({ useScope: "global" })

const tiles = [
  { titleKey: "app.admin.menu.users", descKey: "dashboard.tiles.users_desc", to: "/admin/users" },
  { titleKey: "app.admin.menu.trips", descKey: "dashboard.tiles.trips_desc", to: "/admin/trips" },
  { titleKey: "app.admin.menu.places", descKey: "dashboard.tiles.places_desc", to: "/admin/places" },
  { titleKey: "app.admin.menu.settings", descKey: "dashboard.tiles.settings_desc", to: "/admin/settings" },
]
</script>

<template>
  <div>
    <h1 class="text-3xl font-bold mb-2">{{ t("app.admin.title") }}</h1>
    <p class="text-gray-600 mb-8">{{ t("app.admin.subtitle") }}</p>

    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-6">
      <button
        v-for="tile in tiles"
        :key="tile.titleKey"
        @click="router.push(tile.to)"
        class="bg-white rounded-xl p-6 shadow
               hover:shadow-lg transition text-left"
      >
        <h2 class="text-xl font-semibold mb-2">{{ t(tile.titleKey) }}</h2>
        <p class="text-gray-500 text-sm">{{ t(tile.descKey) }}</p>
      </button>
    </div>
  </div>
</template>
