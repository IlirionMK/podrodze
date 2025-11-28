import { ref } from 'vue'


const token = ref(localStorage.getItem('token') || null)
const user = ref(null)


export function useAuth() {
    function setToken(newToken) {
        token.value = newToken
        localStorage.setItem('token', newToken)
    }


    function clearAuth() {
        token.value = null
        user.value = null
        localStorage.removeItem('token')
    }


    return {
        token,
        user,
        setToken,
        clearAuth,
    }
}





