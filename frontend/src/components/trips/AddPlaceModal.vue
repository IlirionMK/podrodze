```vue
<script setup>
import { ref, computed, watch, useSlots } from "vue"
import { useI18n } from "vue-i18n"

const { t } = useI18n()
const slots = useSlots()

const props = defineProps({
  modelValue: { type: Boolean, default: false },

  variant: {
    type: String,
    default: "place",
    validator: (v) => ["place", "custom"].includes(v),
  },

  busy: { type: Boolean, default: false },
  maxWidthClass: { type: String, default: "max-w-md" },

  lat: { type: Number, default: null },
  lng: { type: Number, default: null },

  initialName: { type: String, default: "" },
  initialCategory: { type: String, default: "other" },

  closeOnBackdrop: { type: Boolean, default: true },
})

const emit = defineEmits(["update:modelValue", "submit", "open", "close"])

const name = ref("")
const category = ref("other")

const hasSubtitleSlot = computed(() => Boolean(slots.subtitle))
const hasActionsSlot = computed(() => Boolean(slots.actions))

watch(
    () => props.modelValue,
    (open) => {
      if (!open) return

      emit("open")

      if (props.variant === "place") {
        name.value = String(props.initialName || "")
        category.value = String(props.initialCategory || "other")
      }
    }
)

function close() {
  if (props.busy) return
  emit("update:modelValue", false)
  emit("close")
}

function onBackdropClick() {
  if (!props.closeOnBackdrop) return
  close()
}

const coordsLabel = computed(() => {
  if (props.lat == null || props.lng == null) return "—"
  return `${props.lat.toFixed(6)}, ${props.lng.toFixed(6)}`
})

const canSubmitPlace = computed(() => {
  if (props.busy) return false
  if (!name.value.trim()) return false
  if (props.lat == null || props.lng == null) return false
  return true
})

function submitPlace() {
  const trimmed = name.value.trim()
  if (!trimmed) return

  emit("submit", {
    name: trimmed,
    category: category.value,
    lat: props.lat,
    lon: props.lng,
  })

  close()
}
</script>

<template>
  <Teleport to="body">
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
          v-if="modelValue"
          class="fixed inset-0 z-50 flex items-center justify-center px-4"
          role="dialog"
          aria-modal="true"
      >
        <button class="absolute inset-0 bg-black/60" @click="onBackdropClick" aria-label="Close" />

        <div
            class="relative w-full rounded-2xl border border-white/15 bg-white/10 backdrop-blur-xl shadow-2xl text-white"
            :class="maxWidthClass"
        >
          <div class="p-6">
            <div class="flex items-start justify-between gap-4">
              <div class="min-w-0">
                <h2 class="text-xl font-semibold drop-shadow">
                  <slot name="title">
                    {{
                      variant === "place"
                          ? t("trip.places.add_title", "Add place")
                          : t("modal.title", "Modal")
                    }}
                  </slot>
                </h2>

                <p v-if="hasSubtitleSlot || variant === 'place'" class="mt-1 text-sm text-white/70">
                  <slot name="subtitle">
                    {{
                      variant === "place"
                          ? t("trip.places.add_hint", "Create a custom place for this trip.")
                          : ""
                    }}
                  </slot>
                </p>
              </div>

              <div class="flex items-center gap-2">
                <button
                    type="button"
                    class="rounded-xl px-3 py-1.5 text-sm bg-white/10 border border-white/15 hover:bg-white/15 transition disabled:opacity-50"
                    @click="close"
                    :disabled="busy"
                >
                  <slot name="cancelText">
                    {{ t("actions.cancel", "Cancel") }}
                  </slot>
                </button>
              </div>
            </div>

            <div class="mt-6">
              <template v-if="variant === 'custom'">
                <slot name="body" :close="close">
                  <slot :close="close" />
                </slot>
              </template>

              <template v-else>
                <div class="space-y-4">
                  <div>
                    <label class="block text-sm font-medium mb-1">
                      {{ t("trip.places.name_label", "Name") }}
                    </label>
                    <input
                        v-model="name"
                        type="text"
                        class="w-full rounded-xl border border-white/15 bg-white/10 px-3 py-2 text-white placeholder:text-white/40 outline-none focus:ring-2 focus:ring-white/20"
                        :placeholder="t('trip.places.name_placeholder', 'Place name')"
                        autocomplete="off"
                        :disabled="busy"
                        @keydown.enter.prevent="submitPlace"
                    />
                  </div>

                  <div>
                    <label class="block text-sm font-medium mb-1">
                      {{ t("trip.places.category_label", "Category") }}
                    </label>

                    <select
                        v-model="category"
                        class="w-full rounded-xl border border-white/15 bg-white/10 px-3 py-2 text-white outline-none focus:ring-2 focus:ring-white/20"
                        :disabled="busy"
                    >
                      <option class="bg-[#0d1117]" value="other">{{ t("trip.categories.other", "Other") }}</option>
                      <option class="bg-[#0d1117]" value="food">{{ t("trip.categories.food", "Food") }}</option>
                      <option class="bg-[#0d1117]" value="museum">{{ t("trip.categories.museum", "Museum") }}</option>
                      <option class="bg-[#0d1117]" value="nature">{{ t("trip.categories.nature", "Nature") }}</option>
                      <option class="bg-[#0d1117]" value="nightlife">{{ t("trip.categories.nightlife", "Nightlife") }}</option>
                      <option class="bg-[#0d1117]" value="attraction">{{ t("trip.categories.attraction", "Attraction") }}</option>
                      <option class="bg-[#0d1117]" value="hotel">{{ t("trip.categories.hotel", "Hotel") }}</option>
                      <option class="bg-[#0d1117]" value="airport">{{ t("trip.categories.airport", "Airport") }}</option>
                      <option class="bg-[#0d1117]" value="station">{{ t("trip.categories.station", "Station") }}</option>
                    </select>
                  </div>

                  <div class="pt-2 flex flex-col gap-3">
                    <button
                        type="button"
                        class="w-full py-3 rounded-xl text-base font-medium bg-gradient-to-r from-blue-500 to-purple-600 hover:opacity-90 active:opacity-80 transition shadow-lg disabled:opacity-50"
                        :disabled="!canSubmitPlace"
                        @click="submitPlace"
                    >
                      {{ busy ? t("loading", "Loading...") : t("trip.places.add_submit", "Add place") }}
                    </button>

                    <p v-if="lat == null || lng == null" class="text-xs text-white/60 text-center">
                      {{ t("trip.places.coords_missing", "Select a point on the map first.") }}
                    </p>
                  </div>
                </div>
              </template>
            </div>
          </div>

          <div class="px-6 pb-6">
            <template v-if="variant === 'custom'">
              <div v-if="$slots.footer">
                <slot name="footer" :close="close" />
              </div>

              <div v-else-if="hasActionsSlot">
                <slot name="actions" :close="close" />
              </div>
            </template>

            <template v-else>
              <div class="rounded-xl border border-white/10 bg-black/20 px-4 py-3 text-xs text-white/70">
                <div class="flex items-center justify-between gap-3">
                  <span>{{ t("trip.places.coords_label", "Coordinates") }}</span>
                  <span class="font-mono">{{ coordsLabel }}</span>
                </div>
              </div>
            </template>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

