import { createRouter, createWebHistory } from 'vue-router'

import GuestLayout from '../layouts/GuestLayout.vue'

// Guest pages
import Login from '../pages/auth/Login.vue'
import Register from '../pages/auth/Register.vue'

const router = createRouter({
    history: createWebHistory(),
    routes: [
        {
            path: '/',
            component: GuestLayout,
            children: [
                { path: '', redirect: 'login' },
                { path: 'login', component: Login },
                { path: 'register', component: Register },
            ]
        }
    ]
})

export default router
