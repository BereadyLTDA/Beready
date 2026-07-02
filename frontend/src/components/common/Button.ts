export function useButton() {
    const getVariantClass = (variant: string) => {
        const variants: Record<string, string> = {
            primary: 'btn-primary',
            secondary: 'btn-secondary',
            danger: 'btn-danger',
            success: 'btn-success',
            outline: 'btn-outline',
        }
        return variants[variant] || 'btn-primary'
    }

    return { getVariantClass }
}
