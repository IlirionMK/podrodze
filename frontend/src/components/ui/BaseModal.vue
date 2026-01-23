<script setup>
import { computed, onMounted, onUnmounted, watch, useSlots } from "vue"
import { X } from "lucide-vue-next"

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  title: { type: String, default: "" },
  description: { type: String, default: "" },
  busy: { type: Boolean, default: false },
  maxWidthClass: { type: String, default: "max-w-lg" },
  closeOnBackdrop: { type: Boolean, default: true },
  closeOnEsc: { type: Boolean, default: true },
})

const emit = defineEmits(["update:modelValue", "close"])
const slots = useSlots()

const hasBody = computed(() => !!slots.default)
const hasFooter = computed(() => !!slots.footer)

function close() {
  emit("update:modelValue", false)
  emit("close")
}

function onKeydown(e) {
  if (!props.modelValue) return
  if (props.closeOnEsc && e.key === "Escape") close()
}

function lockBodyScroll(lock) {
  const el = document.documentElement
  if (lock) el.classList.add("overflow-hidden")
  else el.classList.remove("overflow-hidden")
}

watch(
    () => props.modelValue,
    (v) => lockBodyScroll(v),
    { immediate: true }
)

onMounted(() => document.addEventListener("keydown", onKeydown))
onUnmounted(() => {
  document.removeEventListener("keydown", onKeydown)
  lockBodyScroll(false)
})
</script>

<template>
  <Teleport to="body">
    <Transition
        appear
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
      <div v-if="modelValue" class="fixed inset-0 z-50 flex items-center justify-center px-4" role="dialog" aria-modal="true">
        <button
            class="absolute inset-0 bg-black/60"
            :disabled="busy"
            @click="closeOnBackdrop ? close() : null"
            aria-label="Close"
        />

        <Transition
            appear
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
        >
          <div
              class="relative w-full rounded-2xl border border-white/15 bg-white/10 backdrop-blur-xl shadow-2xl text-white overflow-hidden flex flex-col"
              :class="maxWidthClass"
          >
            <div class="p-6 border-b border-white/10 bg-white/5">
              <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                  <h3 class="text-xl font-semibold drop-shadow">{{ title }}</h3>
                  <div v-if="description" class="mt-1 text-sm text-white/70">
                    {{ description }}
                  </div>
                </div>

                <button
                    type="button"
                    class="h-10 w-10 rounded-xl bg-white/10 border border-white/15 hover:bg-white/15 transition flex items-center justify-center disabled:opacity-50"
                    @click="close"
                    :disabled="busy"
                    aria-label="Close"
                >
                  <X class="h-4 w-4" />
                </button>
              </div>
            </div>

            <div v-if="hasBody" class="p-6 bg-black/10">
              <slot />
            </div>

            <div v-if="hasFooter" class="p-6 border-t border-white/10 bg-white/5 flex justify-end gap-2">
              <slot name="footer" />
            </div>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>
