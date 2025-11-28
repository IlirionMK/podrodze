import { createI18n } from "vue-i18n"

import pl from "./pl.json"
import en from "./en.json"

export const i18n = createI18n({
    legacy: false,
    locale: "pl",
    fallbackLocale: "en",
    messages: { pl, en }
})
