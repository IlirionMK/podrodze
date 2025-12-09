import axios from "axios"

// MAIN axios instance used by ALL API modules
export const api = axios.create({
    baseURL: import.meta.env.VITE_API_URL || "http://localhost:8081/api/v1",
    withCredentials: true,
    headers: {
        Accept: "application/json",
        Authorization: `Bearer ${localStorage.getItem("token") || ""}`,
    },
})

export function fetchUserTrips() {
    return api.get("/trips")
}

export function fetchTrip(id) {
    return api.get(`/trips/${id}`)
}

export function createTrip(payload) {
    return api.post("/trips", payload)
}

export default {
    fetchUserTrips,
    fetchTrip,
    createTrip,
}
