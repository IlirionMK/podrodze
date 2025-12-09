<script setup>
import { ref, watch } from "vue"

const props = defineProps({
  modelValue: Boolean,
  lat: Number,
  lng: Number,
})

const emit = defineEmits(["update:modelValue", "submit"])

const name = ref("")
const category = ref("custom")

watch(
    () => props.modelValue,
    (val) => {
      if (val) {
        name.value = ""
        category.value = "custom"
      }
    }
)

function close() {
  emit("update:modelValue", false)
}

function submit() {
  if (!name.value.trim()) return

  emit("submit", {
    name: name.value.trim(),
    category: category.value,
    latitude: props.lat,
    longitude: props.lng,
  })

  close()
}
</script>

<template>
  <div
      v-if="modelValue"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
  >
    <div class="bg-white p-6 rounded-xl w-full max-w-md shadow-lg">
      <h2 class="text-xl font-semibold mb-4">Add place</h2>

      <div class="flex flex-col gap-4">
        <div>
          <label class="font-medium">Name</label>
          <input
              v-model="name"
              type="text"
              class="w-full border rounded-lg p-2"
              placeholder="Place name"
          />
        </div>

        <div>
          <label class="font-medium">Category</label>
          <select v-model="category" class="w-full border rounded-lg p-2">
            <option value="custom">Custom</option>
            <option value="food">Food</option>
            <option value="museum">Museum</option>
            <option value="nature">Nature</option>
            <option value="nightlife">Nightlife</option>
          </select>
        </div>

        <button
            @click="submit"
            class="bg-green-600 text-white py-2 rounded-lg hover:bg-green-700"
        >
          Add place
        </button>

        <button
            @click="close"
            class="text-gray-600 hover:underline mt-2"
        >
          Cancel
        </button>
      </div>
    </div>
  </div>
</template>
