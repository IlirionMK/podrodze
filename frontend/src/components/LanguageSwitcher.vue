<script setup>
import { useI18n } from "vue-i18n"

const { locale } = useI18n({ useScope: "global" })

const languages = [
  { code: "pl", label: "PL" },
  { code: "en", label: "EN" }
]

function changeLang(code) {
  if (locale.value === code) return
  locale.value = code
  localStorage.setItem("lang", code)
}
</script>

<template>
  <div class="flex gap-2">
    <button
        v-for="lang in languages"
        :key="lang.code"
        @click="changeLang(lang.code)"
        :class="[
        'px-2 py-1 border rounded text-sm transition',
        locale.value === lang.code
          ? 'bg-blue-600 text-white border-blue-600'
          : 'bg-white text-gray-700 hover:bg-gray-100'
      ]"
    >
      {{ lang.label }}
    </button>

  </div>
</template>
