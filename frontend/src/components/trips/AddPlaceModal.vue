<script setup>
import { ref, watch } from "vue"
import { useI18n } from "vue-i18n"

const { t } = useI18n()

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  lat: { type: Number, default: null },
  lng: { type: Number, default: null },
})

const emit = defineEmits(["update:modelValue", "submit"])

const name = ref("")
const category = ref("other")

watch(
    () => props.modelValue,
    (open) => {
      if (!open) return
      name.value = ""
      category.value = "other"
    }
)

function close() {
  emit("update:modelValue", false)
}

function submit() {
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
        <button
            class="absolute inset-0 bg-black/60"
            @click="close"
            aria-label="Close"
        />

        <div
            class="relative w-full max-w-md rounded-2xl border border-white/15 bg-white/10 backdrop-blur-xl shadow-2xl text-white"
        >
          <div class="p-6">
            <div class="flex items-start justify-between gap-4">
              <div>
                <h2 class="text-xl font-semibold drop-shadow">
                  {{ t("trip.places.add_title", "Add place") }}
                </h2>
                <p class="mt-1 text-sm text-white/70">
                  {{ t("trip.places.add_hint", "Create a custom place for this trip.") }}
                </p>
              </div>

              <button
                  class="rounded-xl px-3 py-1.5 text-sm bg-white/10 border border-white/15 hover:bg-white/15 transition"
                  @click="close"
                  type="button"
              >
                {{ t("actions.cancel", "Cancel") }}
              </button>
            </div>

            <div class="mt-6 space-y-4">
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
                    @keydown.enter.prevent="submit"
                />
              </div>

              <div>
                <label class="block text-sm font-medium mb-1">
                  {{ t("trip.places.category_label", "Category") }}
                </label>
                <select
                    v-model="category"
                    class="w-full rounded-xl border border-white/15 bg-white/10 px-3 py-2 text-white outline-none focus:ring-2 focus:ring-white/20"
                >
                  <option class="bg-[#0d1117]" value="other">
                    {{ t("trip.categories.other", "Other") }}
                  </option>
                  <option class="bg-[#0d1117]" value="food">
                    {{ t("trip.categories.food", "Food") }}
                  </option>
                  <option class="bg-[#0d1117]" value="museum">
                    {{ t("trip.categories.museum", "Museum") }}
                  </option>
                  <option class="bg-[#0d1117]" value="nature">
                    {{ t("trip.categories.nature", "Nature") }}
                  </option>
                  <option class="bg-[#0d1117]" value="nightlife">
                    {{ t("trip.categories.nightlife", "Nightlife") }}
                  </option>
                  <option class="bg-[#0d1117]" value="attraction">
                    {{ t("trip.categories.attraction", "Attraction") }}
                  </option>
                  <option class="bg-[#0d1117]" value="hotel">
                    {{ t("trip.categories.hotel", "Hotel") }}
                  </option>
                  <option class="bg-[#0d1117]" value="airport">
                    {{ t("trip.categories.airport", "Airport") }}
                  </option>
                  <option class="bg-[#0d1117]" value="station">
                    {{ t("trip.categories.station", "Station") }}
                  </option>
                </select>
              </div>

              <div class="pt-2 flex flex-col gap-3">
                <button
                    type="button"
                    class="w-full py-3 rounded-xl text-base font-medium bg-gradient-to-r from-blue-500 to-purple-600 hover:opacity-90 active:opacity-80 transition shadow-lg disabled:opacity-50"
                    :disabled="!name.trim() || lat == null || lng == null"
                    @click="submit"
                >
                  {{ t("trip.places.add_submit", "Add place") }}
                </button>

                <p v-if="lat == null || lng == null" class="text-xs text-white/60 text-center">
                  {{ t("trip.places.coords_missing", "Select a point on the map first.") }}
                </p>
              </div>
            </div>
          </div>

          <div class="px-6 pb-6">
            <div class="rounded-xl border border-white/10 bg-black/20 px-4 py-3 text-xs text-white/70">
              <div class="flex items-center justify-between gap-3">
                <span>{{ t("trip.places.coords_label", "Coordinates") }}</span>
                <span class="font-mono">
                  {{ lat != null && lng != null ? `${lat.toFixed(6)}, ${lng.toFixed(6)}` : "—" }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
