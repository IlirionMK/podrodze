<script setup>
import LogoIcon from "./icons/LogoIcon.vue"
import { RouterLink } from "vue-router"
import { useI18n } from "vue-i18n"

const { t } = useI18n({ useScope: "global" })

const links = [
  { labelKey: "footer.links.about_us", to: "/" },
  { labelKey: "footer.links.contact", to: "/" },
  { labelKey: "legal.privacy.title", to: "/privacy" },
  { labelKey: "legal.terms.title", to: "/terms" },
  { labelKey: "legal.data_deletion.title", to: "/data-deletion" },
]
</script>

<template>
  <footer class="bg-[#0d1117] text-gray-300 pt-12 pb-10 mt-20 border-t border-gray-800">
    <div class="max-w-6xl mx-auto px-4">
      <!-- Top -->
      <div
          class="flex flex-col items-center text-center gap-6
               md:flex-row md:items-start md:justify-between md:text-left"
      >
        <!-- Brand -->
        <div class="min-w-0">
          <div class="flex items-center justify-center gap-2 md:justify-start">
            <LogoIcon class="w-7 h-7 text-blue-400 drop-shadow-sm shrink-0" />
            <span class="text-lg font-semibold bg-gradient-to-r from-blue-400 to-purple-400 text-transparent bg-clip-text">
              PoDrodze
            </span>
          </div>

          <p class="mt-3 text-sm text-gray-400 leading-relaxed max-w-xl mx-auto md:mx-0">
            {{ t("footer.description") }}
          </p>
        </div>

        <!-- Links -->
        <nav class="flex flex-wrap justify-center gap-x-5 gap-y-2 text-sm md:justify-end">
          <RouterLink
              v-for="l in links"
              :key="l.to"
              :to="l.to"
              class="text-gray-400 hover:text-gray-200 transition"
          >
            {{ t(l.labelKey) }}
          </RouterLink>
        </nav>
      </div>

      <!-- Bottom -->
      <div
          class="mt-8 border-t border-gray-800 pt-6
               flex flex-col items-center gap-2 text-center
               sm:flex-row sm:justify-between sm:text-left
               text-sm text-gray-500"
      >
        <div>© {{ new Date().getFullYear() }} PoDrodze</div>
        <div>{{ t("footer.student_project") }}</div>
      </div>
    </div>
  </footer>
</template>
