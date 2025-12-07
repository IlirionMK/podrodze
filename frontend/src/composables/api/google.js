import axios from "axios"

export const fetchGoogleMapsKey = () => {
    return axios.get("/api/v1/google/maps-key")
}
