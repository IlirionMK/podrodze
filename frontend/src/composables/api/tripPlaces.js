import axios from "axios"

const api = axios.create({
    baseURL: import.meta.env.VITE_API_URL || "http://localhost:8081/api/v1",
    headers: {
        Accept: "application/json",
        Authorization: `Bearer ${localStorage.getItem("token") || ""}`,
    },
})

export function fetchTripPlaces(tripId) {
    return api.get(`/trips/${tripId}/places`)
}

export function createTripPlace(tripId, payload) {
    return api.post(`/trips/${tripId}/places`, payload)
}

export default {
    fetchTripPlaces,
    createTripPlace,
}
