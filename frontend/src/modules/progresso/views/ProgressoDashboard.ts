import { ref, onMounted } from 'vue'
import { useProgresso as useProgressoComposable } from '@/modules/progresso/composables/useProgresso'

export function useProgresso() {
    const { progresso, loading, fetchProgresso } = useProgressoComposable()

    const getCurrentUserId = (): number | null => {
        const userData = localStorage.getItem('user')
        if (!userData) return null
        try {
            const user = JSON.parse(userData)
            return user.id
        } catch {
            return null
        }
    }

    onMounted(async () => {
        const userId = getCurrentUserId()
        if (userId) {
            await fetchProgresso(userId)
        }
    })

    return {
        progresso,
        loading,
    }
}
