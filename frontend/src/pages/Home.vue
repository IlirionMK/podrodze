<script setup>
</script>

<template>
  <div class="w-full">

    <!-- Hero for logged user -->
    <section
        class="py-20 bg-gradient-to-r from-blue-500 to-purple-600 text-white text-center"
    >
      <h1 class="text-4xl font-bold mb-4">
        {{ $t('home.welcome_title') }}
      </h1>

      <p class="text-lg opacity-90 mb-6">
        {{ $t('home.welcome_subtitle') }}
      </p>
    </section>

    <section class="py-16 max-w-5xl mx-auto">
      <h2 class="text-3xl font-bold text-center mb-10">
        {{ $t('home.features_title') }}
      </h2>

      <!-- Example cards, ready for expansion -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-8 px-4">

        <div class="bg-white shadow rounded-lg p-6 text-center">
          <h3 class="font-semibold mb-2 text-lg">{{ $t('home.feature.plan') }}</h3>
          <p class="text-gray-600 text-sm">{{ $t('home.feature.plan_desc') }}</p>
        </div>

        <div class="bg-white shadow rounded-lg p-6 text-center">
          <h3 class="font-semibold mb-2 text-lg">{{ $t('home.feature.invite') }}</h3>
          <p class="text-gray-600 text-sm">{{ $t('home.feature.invite_desc') }}</p>
        </div>

        <div class="bg-white shadow rounded-lg p-6 text-center">
          <h3 class="font-semibold mb-2 text-lg">{{ $t('home.feature.vote') }}</h3>
          <p class="text-gray-600 text-sm">{{ $t('home.feature.vote_desc') }}</p>
        </div>

      </div>
    </section>

  </div>
</template>
