<script setup>
import { ref, computed } from "vue"
import { Eye, EyeOff } from "lucide-vue-next"

const props = defineProps({
  modelValue: { type: [String, Number], default: "" },
  label: { type: String, default: "" },
  type: { type: String, default: "text" },
  error: { type: String, default: null },
  placeholder: { type: String, default: "" },
  name: { type: String, default: "" },
  autocomplete: { type: String, default: "off" },
})

const emit = defineEmits(["update:modelValue"])

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
    <label
        v-if="label"
        class="text-sm font-medium"
        :class="error ? 'text-red-300' : 'text-white/90'"
    >
      {{ label }}
    </label>

    <div
        class="w-full rounded-lg border bg-white/10 backdrop-blur-xl transition flex items-stretch"
        :class="error
        ? 'border-red-400'
        : 'border-white/30 focus-within:ring-2 focus-within:ring-blue-300 focus-within:border-blue-400'"
    >
      <input
          :type="inputType"
          :value="modelValue"
          :placeholder="placeholder"
          :autocomplete="autocomplete"
          :name="name"
          @input="emit('update:modelValue', $event.target.value)"
          class="flex-1 bg-transparent text-white placeholder-white/40 outline-none
               px-3 py-2.5 leading-6 min-w-0 disabled:opacity-70"
          :aria-invalid="!!error"
      />

      <button
          v-if="type === 'password'"
          type="button"
          @click="togglePassword"
          class="w-12 inline-flex items-center justify-center
               text-white/60 hover:text-white transition
               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-300
               rounded-r-lg"
          :aria-label="showPassword ? 'Hide password' : 'Show password'"
      >
        <Eye v-if="!showPassword" class="w-5 h-5" />
        <EyeOff v-else class="w-5 h-5" />
      </button>
    </div>

    <p v-if="error" class="text-sm text-red-300">
      {{ error }}
    </p>
  </div>
</template>
