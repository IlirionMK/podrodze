<script setup>
const props = defineProps({
  modelValue: { type: String, required: true },
})

const emit = defineEmits(["update:modelValue"])

function setTab(tab) {
  emit("update:modelValue", tab)
}
</script>

<template>
  <div class="bg-white border shadow-sm rounded-2xl p-2">
    <div class="flex gap-2 overflow-x-auto whitespace-nowrap">
      <button
          type="button"
          class="px-4 py-2 rounded-xl font-medium transition"
          :class="modelValue === 'overview' ? 'bg-gray-900 text-white' : 'text-gray-700 hover:bg-gray-100'"
          @click="setTab('overview')"
      >
        <slot name="overview">Overview</slot>
      </button>

      <button
          type="button"
          class="px-4 py-2 rounded-xl font-medium transition"
          :class="modelValue === 'places' ? 'bg-gray-900 text-white' : 'text-gray-700 hover:bg-gray-100'"
          @click="setTab('places')"
      >
        <slot name="places">Places</slot>
      </button>

      <button
          type="button"
          class="px-4 py-2 rounded-xl font-medium transition"
          :class="modelValue === 'plan' ? 'bg-gray-900 text-white' : 'text-gray-700 hover:bg-gray-100'"
          @click="setTab('plan')"
      >
        <slot name="plan">Plan</slot>
      </button>

      <button
          type="button"
          class="px-4 py-2 rounded-xl font-medium transition"
          :class="modelValue === 'team' ? 'bg-gray-900 text-white' : 'text-gray-700 hover:bg-gray-100'"
          @click="setTab('team')"
      >
        <slot name="team">Team</slot>
      </button>

      <button
          type="button"
          class="px-4 py-2 rounded-xl font-medium transition"
          :class="modelValue === 'preferences' ? 'bg-gray-900 text-white' : 'text-gray-700 hover:bg-gray-100'"
          @click="setTab('preferences')"
      >
        <slot name="preferences">Preferences</slot>
      </button>
    </div>
  </div>
</template>
