import { ref } from "vue"

const token = ref(localStorage.getItem("token") || null)
const user = ref(JSON.parse(localStorage.getItem("user")) || null)

export function useAuth() {

    function setToken(newToken) {
        token.value = newToken
        localStorage.setItem("token", newToken)
    }

    function setUser(newUser) {
        user.value = newUser
        localStorage.setItem("user", JSON.stringify(newUser))
    }

    function clearAuth() {
        token.value = null
        user.value = null
        localStorage.removeItem("token")
        localStorage.removeItem("user")
        localStorage.removeItem("intended")
    }

    return {
        token,
        user,
        setToken,
        setUser,
        clearAuth
    }
}
