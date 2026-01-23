<script setup>
defineProps({
  modelValue: { type: String, required: true },
})

const emit = defineEmits(["update:modelValue"])

function setTab(tab) {
  emit("update:modelValue", tab)
}

const tabBase = "px-4 py-2 rounded-xl font-medium transition whitespace-nowrap"
const tabActive = "text-white bg-gradient-to-r from-blue-600 to-purple-600 shadow"
const tabInactive = "text-gray-700 hover:bg-gray-100"
</script>

<template>
  <div class="bg-white border shadow-sm rounded-2xl p-2">
    <div class="flex gap-2 overflow-x-auto whitespace-nowrap justify-start sm:justify-center">
      <button
          type="button"
          :class="[tabBase, modelValue === 'places' ? tabActive : tabInactive]"
          @click="setTab('places')"
      >
        <slot name="places">Places</slot>
      </button>

      <button
          type="button"
          :class="[tabBase, modelValue === 'plan' ? tabActive : tabInactive]"
          @click="setTab('plan')"
      >
        <slot name="plan">Plan</slot>
      </button>

      <button
          type="button"
          :class="[tabBase, modelValue === 'team' ? tabActive : tabInactive]"
          @click="setTab('team')"
      >
        <slot name="team">Team</slot>
      </button>

      <button
          type="button"
          :class="[tabBase, modelValue === 'preferences' ? tabActive : tabInactive]"
          @click="setTab('preferences')"
      >
        <slot name="preferences">Preferences</slot>
      </button>
    </div>
  </div>
</template>
