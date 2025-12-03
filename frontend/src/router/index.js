import { createRouter, createWebHistory } from "vue-router"

import GuestLayout from "../layouts/GuestLayout.vue"
import UserLayout from "../layouts/UserLayout.vue"
import AdminLayout from "../layouts/AdminLayout.vue"

import HomePage from "../pages/Home.vue"
import AuthLoginPage from "../pages/auth/Login.vue"
import AuthRegisterPage from "../pages/auth/Register.vue"

// Helpers
const isAuthenticated = () => !!localStorage.getItem("token")
const isAdmin = () => localStorage.getItem("role") === "admin"

const router = createRouter({
    history: createWebHistory(),
    routes: [

        // Public
        {
            path: "/",
            component: GuestLayout,
            meta: { guest: true },
            children: [
                { path: "", name: "guest.start", component: HomePage },
                { path: "login", name: "auth.login", component: AuthLoginPage },
                { path: "register", name: "auth.register", component: AuthRegisterPage }
            ]
        },

        // Authenticated
        {
            path: "/app",
            component: UserLayout,
            meta: { auth: true },
            children: [
                { path: "", redirect: { name: "home" } }, // redirect /app → /app/home
                { path: "home", name: "home", component: HomePage },
                {
                    path: "profile",
                    name: "app.profile",
                    component: () => import("../pages/app/Profile.vue")
                }
            ]
        },

        // Admin
        {
            path: "/admin",
            component: AdminLayout,
            meta: { admin: true },
            children: []
        },

        // Errors
        {
            path: "/403",
            name: "error.403",
            component: () => import("../pages/errors/Forbidden.vue")
        }
    ]
})


// GLOBAL GUARD
router.beforeEach((to, from, next) => {

    // Guest-only pages
    if (to.meta.guest && isAuthenticated()) {
        return next({ name: "home" })
    }

    // Auth-required pages
    if (to.meta.auth && !isAuthenticated()) {
        localStorage.setItem("intended", to.fullPath)   // save target page
        return next({ name: "auth.login" })
    }

    // Admin-only pages
    if (to.meta.admin) {
        if (!isAuthenticated()) {
            return next({ name: "auth.login" })
        }
        if (!isAdmin()) {
            return next({ name: "error.403" })
        }
    }

    next()
})

export default router
