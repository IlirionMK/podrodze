<script setup>
import LogoIcon from "./icons/LogoIcon.vue"
import { useI18n } from "vue-i18n"

const { t } = useI18n({ useScope: "global" })
</script>

<template>
  <footer class="bg-[#0d1117] text-gray-300 pt-14 pb-10 mt-20 border-t border-gray-800">
    <div class="max-w-6xl mx-auto px-4 grid grid-cols-1 md:grid-cols-3 gap-12">

      <!-- Brand -->
      <div>
        <div class="flex items-center gap-2 mb-4">
          <LogoIcon class="w-7 h-7 text-blue-400 drop-shadow-sm" />
          <span
              class="text-lg font-semibold bg-gradient-to-r from-blue-400 to-purple-400 text-transparent bg-clip-text"
          >
            PoDrodze
          </span>
        </div>

        <p class="text-sm text-gray-400 leading-relaxed">
          {{ t("footer.description") }}
        </p>
      </div>

      <!-- Project (reduced) -->
      <div>
        <h3 class="font-medium mb-3 text-gray-200 tracking-wide uppercase text-xs opacity-80">
          {{ t("footer.section.project") }}
        </h3>
        <ul class="space-y-2 text-sm">

          <li class="hover:text-gray-200 cursor-pointer transition">
            {{ t("footer.links.about_us") }}
          </li>

        </ul>
      </div>

      <!-- Team -->
      <div>
        <h3 class="font-medium mb-3 text-gray-200 tracking-wide uppercase text-xs opacity-80">
          {{ t("footer.section.team") }}
        </h3>
        <ul class="space-y-2 text-sm">
          <li class="hover:text-gray-200 cursor-pointer transition">
            {{ t("footer.links.about_us") }}
          </li>
          <li class="hover:text-gray-200 cursor-pointer transition">
            {{ t("footer.links.contact") }}
          </li>
        </ul>
      </div>

    </div>

    <!-- Bottom -->
    <div class="max-w-6xl mx-auto mt-10 px-4">
      <div class="border-t border-gray-800 pt-6 text-center text-sm text-gray-500">
        © {{ new Date().getFullYear() }} PoDrodze • {{ t("footer.student_project") }}
      </div>
    </div>
  </footer>
</template>
