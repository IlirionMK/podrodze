import { createRouter, createWebHistory } from 'vue-router'

import GuestLayout from '../layouts/GuestLayout.vue'
import UserLayout from '../layouts/UserLayout.vue'

import StartPage from '../pages/StartPage.vue'
import AuthLoginPage from '../pages/auth/Login.vue'
import AuthRegisterPage from '../pages/auth/Register.vue'
import HomePage from '../pages/Home.vue'

const router = createRouter({
    history: createWebHistory(),
    routes: [

        {
            path: '/',
            component: GuestLayout,
            children: [
                { path: '', name: 'guest.start', component: StartPage },
                { path: 'login', name: 'auth.login', component: AuthLoginPage },
                { path: 'register', name: 'auth.register', component: AuthRegisterPage },
            ]
        },

        // Authenticated user routes
        {
            path: '/app',
            component: UserLayout,
            children: [
                { path: 'home', name: 'home', component: HomePage },
            ]
        }
    ]
})

export default router
