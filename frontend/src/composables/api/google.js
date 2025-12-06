import axios from "axios"

export const fetchGoogleMapsKey = () => {
    return axios.get("/google/maps-key")
}
