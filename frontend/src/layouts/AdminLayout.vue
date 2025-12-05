<script setup>
import { ref } from "vue"
import { useI18n } from "vue-i18n"

const { t } = useI18n({ useScope: "global" })

const sidebar = ref(false)
</script>

<template>
  <div class="flex min-h-screen bg-gray-100">

    <!-- Desktop Sidebar -->
    <aside class="bg-white border-r w-64 p-4 hidden md:flex flex-col gap-4">

      <h2 class="font-bold text-lg">
        {{ t("app.admin.title") }}
      </h2>

      <router-link to="/admin/users" class="hover:underline text-sm">
        {{ t("app.admin.users") }}
      </router-link>

      <router-link to="/admin/trips" class="hover:underline text-sm">
        {{ t("app.admin.trips") }}
      </router-link>

      <router-link to="/admin/places" class="hover:underline text-sm">
        {{ t("app.admin.places") }}
      </router-link>

      <router-link to="/admin/settings" class="hover:underline text-sm">
        {{ t("app.admin.settings") }}
      </router-link>
    </aside>

    <!-- Mobile toggle button -->
    <button
        @click="sidebar = !sidebar"
        class="md:hidden absolute top-4 left-4 z-20 bg-white p-2 rounded shadow"
    >
      ☰
    </button>

    <!-- Mobile Sidebar -->
    <transition
        enter-active-class="transition-transform duration-200 ease-linear"
        enter-from-class="-translate-x-full"
        enter-to-class="translate-x-0"
        leave-active-class="transition-transform duration-200 ease-linear"
        leave-from-class="translate-x-0"
        leave-to-class="-translate-x-full"
    >
      <aside
          v-if="sidebar"
          class="absolute left-0 top-0 bottom-0 bg-white w-56 p-4 flex flex-col gap-4 shadow-md z-30 md:hidden"
      >
        <h2 class="font-bold text-lg">
          {{ t("app.admin.title") }}
        </h2>

        <router-link to="/admin/users" class="hover:underline text-sm">
          {{ t("app.admin.users") }}
        </router-link>

        <router-link to="/admin/trips" class="hover:underline text-sm">
          {{ t("app.admin.trips") }}
        </router-link>

        <router-link to="/admin/places" class="hover:underline text-sm">
          {{ t("app.admin.places") }}
        </router-link>

        <router-link to="/admin/settings" class="hover:underline text-sm">
          {{ t("app.admin.settings") }}
        </router-link>
      </aside>
    </transition>

    <!-- Main Content -->
    <main class="flex-1 p-6">
      <router-view :t="t" />
    </main>

  </div>
</template>
