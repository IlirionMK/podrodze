import api from "@/composables/api/api"

export function fetchTripMembers(tripId) {
    return api.get(`/trips/${tripId}/members`)
}

export function inviteTripMember(tripId, payload) {
    return api.post(`/trips/${tripId}/members/invite`, payload)
}

export function updateTripMember(tripId, userId, payload) {
    return api.patch(`/trips/${tripId}/members/${userId}`, payload)
}

export function removeTripMember(tripId, userId) {
    return api.delete(`/trips/${tripId}/members/${userId}`)
}

export function acceptTripInvite(tripId) {
    return api.post(`/trips/${tripId}/accept`)
}

export function declineTripInvite(tripId) {
    return api.post(`/trips/${tripId}/decline`)
}

export function fetchMyInvites() {
    return api.get("/users/me/invites")
}

export function fetchSentInvites() {
    return api.get("/users/me/invites/sent")
}
