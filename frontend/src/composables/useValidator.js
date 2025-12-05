import { ref } from "vue"

export function useValidator() {
    const errors = ref({})

    const emailRegex =
        /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/

    function validate(fields) {
        errors.value = {}

        for (const key in fields) {
            const rule = fields[key]

            // Required
            if (rule.required && !rule.value) {
                errors.value[key] = rule.messages.required
                continue
            }

            // Email format
            if (rule.email && !emailRegex.test(rule.value)) {
                errors.value[key] = rule.messages.email
                continue
            }

            // Min length
            if (rule.min && rule.value.length < rule.min) {
                errors.value[key] = rule.messages.min
                continue
            }
        }

        return Object.keys(errors.value).length === 0
    }

    return {
        errors,
        validate,
    }
}
