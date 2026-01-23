import api from "@/composables/api/api.js"

export function fetchTripPlaces(tripId) {
    return api.get(`/trips/${tripId}/places`)
}

export function createTripPlace(tripId, payload) {
    return api.post(`/trips/${tripId}/places`, payload)
}

export function updateTripPlace(tripId, placeId, payload) {
    return api.patch(`/trips/${tripId}/places/${placeId}`, payload)
}

export function voteTripPlace(tripId, placeId, score) {
    return api.post(`/trips/${tripId}/places/${placeId}/vote`, { score: Number(score) })
}

export function deleteTripPlace(tripId, placeId) {
    return api.delete(`/trips/${tripId}/places/${placeId}`)
}

export function searchExternalPlaces(query) {
    return api.get("/places/autocomplete", {
        params: { q: query },
    })
}

export function getPlaceDetails(googlePlaceId) {
    return api.get(`/places/google/${googlePlaceId}`)
}

export function getAiSuggestions(tripId, params = {}) {
    return api.get(`/trips/${tripId}/places/suggestions`, { params })
}
