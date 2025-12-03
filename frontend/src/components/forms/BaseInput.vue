<script setup>
import { ref, computed } from "vue"

// Props
const props = defineProps({
  modelValue: {
    type: [String, Number],
    default: ""
  },
  label: {
    type: String,
    default: ""
  },
  type: {
    type: String,
    default: "text"
  },
  error: {
    type: String,
    default: null
  },
  placeholder: {
    type: String,
    default: ""
  }
})

// Emits
const emit = defineEmits(["update:modelValue"])

// Password visibility toggle
const showPassword = ref(false)

const inputType = computed(() => {
  if (props.type !== "password") return props.type
  return showPassword.value ? "text" : "password"
})

function togglePassword() {
  showPassword.value = !showPassword.value
}
</script>


<template>
  <div class="flex flex-col gap-1">

    <!-- LABEL -->
    <label
        v-if="label"
        class="text-sm font-medium text-gray-700"
    >
      {{ label }}
    </label>

    <!-- INPUT WRAPPER -->
    <div
        class="relative"
    >
      <input
          :type="inputType"
          :value="modelValue"
          :placeholder="placeholder"
          @input="emit('update:modelValue', $event.target.value)"
          class="w-full border rounded px-3 py-2 pr-10 transition
               focus:ring-2 focus:ring-blue-300 focus:border-blue-500
               placeholder-gray-400
               border-gray-300
               text-gray-900
               disabled:bg-gray-100 disabled:text-gray-500"
          :class="{ 'border-red-500': error }"
          aria-invalid="error ? 'true' : 'false'"
      />

      <!-- PASSWORD TOGGLE BUTTON -->
      <button
          v-if="type === 'password'"
          type="button"
          @click="togglePassword"
          class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700"
      >
        <!-- Eye icon -->
        <svg
            v-if="!showPassword"
            xmlns="http://www.w3.org/2000/svg"
            class="h-5 w-5"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
        >
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7s-8.268-2.943-9.542-7z" />
          <circle cx="12" cy="12" r="3" stroke-width="1.5" />
        </svg>

        <!-- Eye off icon -->
        <svg
            v-else
            xmlns="http://www.w3.org/2000/svg"
            class="h-5 w-5"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
        >
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                d="M3.98 8.223C5.766 6.104 8.734 4.75 12 4.75c5.523 0 10 3.75 11 7.25-1 3.5-5.477 7.25-11 7.25-3.266 0-6.234-1.354-8.02-3.473M3 3l18 18" />
        </svg>
      </button>
    </div>

    <!-- ERROR MESSAGE -->
    <p v-if="error" class="text-sm text-red-600">
      {{ error }}
    </p>

  </div>
</template>
