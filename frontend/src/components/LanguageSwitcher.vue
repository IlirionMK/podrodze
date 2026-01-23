<script setup>
import { useI18n } from "vue-i18n"

const { locale } = useI18n({ useScope: "global" })

const languages = [
  { code: "pl", label: "PL" },
  { code: "en", label: "EN" },
]

function changeLang(code) {
  if (locale.value === code) return
  locale.value = code
  localStorage.setItem("lang", code)
}
</script>

<template>
  <div class="inline-flex rounded-lg border border-gray-200 bg-white p-1 shadow-sm">
    <button
        v-for="lang in languages"
        :key="lang.code"
        type="button"
        @click="changeLang(lang.code)"
        :class="[
        'px-3 py-1.5 text-xs font-semibold rounded-md transition select-none',
        'focus:outline-none focus:ring-2 focus:ring-blue-500/30',
        locale.value === lang.code
          ? 'bg-blue-600 text-white shadow-sm'
          : 'text-gray-700 hover:bg-gray-100 active:scale-[0.98]'
      ]"
    >
      {{ lang.label }}
    </button>
  </div>
</template>
