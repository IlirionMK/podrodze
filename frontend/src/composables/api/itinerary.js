import api from "@/composables/api/api"

export function fetchTripItinerary(tripId) {
    return api.get(`/trips/${tripId}/itinerary/generate`)
}

export function fetchTripItineraryFull(tripId, payload) {
    return api.post(`/trips/${tripId}/itinerary/generate-full`, payload)
}

export function fetchSavedTripItinerary(tripId) {
    return api.get(`/trips/${tripId}/itinerary`)
}

export function updateSavedTripItinerary(tripId, payload) {
    return api.patch(`/trips/${tripId}/itinerary`, payload)
}
