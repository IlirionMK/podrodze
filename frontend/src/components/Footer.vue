<template>
  <footer class="bg-[#0d1117] text-gray-300 pt-12 pb-8 mt-20 border-t border-gray-800">
    <div class="max-w-6xl mx-auto px-4 grid grid-cols-1 md:grid-cols-4 gap-10">

      <div>
        <div class="flex items-center gap-2 mb-3">
          <LogoIcon />
          <span class="text-lg font-semibold">{{ $t('app.brand') }}</span>
        </div>

        <p class="text-sm text-gray-400">
          {{ $t('footer.description') }}
        </p>
      </div>

      <div>
        <h3 class="font-medium mb-3 text-gray-200">{{ $t('footer.section.project') }}</h3>
        <ul class="space-y-2 text-sm">
          <li>{{ $t('footer.links.about') }}</li>
          <li>{{ $t('footer.links.features') }}</li>
          <li>{{ $t('footer.links.faq') }}</li>
        </ul>
      </div>

      <div>
        <h3 class="font-medium mb-3 text-gray-200">{{ $t('footer.section.team') }}</h3>
        <ul class="space-y-2 text-sm">
          <li>{{ $t('footer.links.about_us') }}</li>
          <li>{{ $t('footer.links.contact') }}</li>
        </ul>
      </div>

      <div>
        <h3 class="font-medium mb-3 text-gray-200">{{ $t('footer.section.legal') }}</h3>
        <ul class="space-y-2 text-sm">
          <li>{{ $t('footer.links.privacy') }}</li>
          <li>{{ $t('footer.links.terms') }}</li>
        </ul>
      </div>

    </div>

    <div class="max-w-6xl mx-auto mt-10 px-4">
      <div class="border-t border-gray-800 pt-6 text-center text-sm text-gray-500">
        © {{ new Date().getFullYear() }} {{ $t('app.brand') }} – {{ $t('footer.student_project') }}
      </div>
    </div>
  </footer>
</template>

<script setup>
import LogoIcon from "./icons/LogoIcon.vue"
</script>
