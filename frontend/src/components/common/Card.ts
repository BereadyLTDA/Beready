export function useCard() {
  const getVariantClass = (variant: string) => {
    const variants: Record<string, string> = {
      default: '',
      primary: 'card-primary',
      success: 'card-success',
      danger: 'card-danger',
      warning: 'card-warning',
    }
    return variants[variant] || ''
  }

  return { getVariantClass }
}
