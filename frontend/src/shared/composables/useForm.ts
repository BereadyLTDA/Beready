// src/shared/composables/useForm.ts
import { reactive, ref } from 'vue'

export function useForm<T extends Record<string, any>>(initialData: T) {
  const form = reactive<T>({ ...initialData })
  const errors = ref<Partial<Record<keyof T, string>>>({})
  const loading = ref(false)

  const validate = (rules: Partial<Record<keyof T, (value: any) => string | null>>) => {
    let isValid = true
    errors.value = {}
    for (const field in rules) {
      const rule = rules[field]
      if (rule) {
        const value = (form as any)[field]
        const errorMsg = rule(value)
        if (errorMsg) {
          errors.value[field as keyof T] = errorMsg
          isValid = false
        }
      }
    }
    return isValid
  }

  const reset = () => {
    Object.assign(form, initialData)
    errors.value = {}
  }

  const setField = (field: keyof T, value: any) => {
    ;(form as any)[field] = value
    if (errors.value[field]) delete errors.value[field]
  }

  return { form, errors, loading, validate, reset, setField }
}
