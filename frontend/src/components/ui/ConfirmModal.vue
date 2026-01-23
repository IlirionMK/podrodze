<script setup>
import { computed } from "vue"
import BaseModal from "@/components/ui/BaseModal.vue"

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  title: { type: String, default: "" },
  description: { type: String, default: "" },
  confirmText: { type: String, default: "OK" },
  cancelText: { type: String, default: "Anuluj" },
  busy: { type: Boolean, default: false },
  tone: { type: String, default: "danger" },
})

const emit = defineEmits(["update:modelValue", "confirm", "cancel"])

function close() {
  emit("update:modelValue", false)
}

function onCancel() {
  emit("cancel")
  close()
}

function onConfirm() {
  emit("confirm")
}

const confirmClass = computed(() => {
  if (props.tone === "danger") return "btn-modal-danger"
  return "btn-modal-primary"
})
</script>

<template>
  <BaseModal
      :model-value="modelValue"
      @update:modelValue="(v) => emit('update:modelValue', v)"
      :title="title"
      :description="description"
      :busy="busy"
      max-width-class="max-w-md"
  >
    <div />
    <template #footer>
      <button type="button" class="btn-modal-ghost w-full sm:w-auto" @click="onCancel" :disabled="busy">
        {{ cancelText }}
      </button>

      <button type="button" :class="confirmClass + ' w-full sm:w-auto'" @click="onConfirm" :disabled="busy">
        {{ confirmText }}
      </button>
    </template>
  </BaseModal>
</template>
