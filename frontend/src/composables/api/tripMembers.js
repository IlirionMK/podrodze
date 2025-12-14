import api from "@/composables/api/api"

export function fetchTripMembers(tripId) {
    return api.get(`/trips/${tripId}/members`)
}

export function inviteTripMember(tripId, payload) {
    return api.post(`/trips/${tripId}/members/invite`, payload)
}
