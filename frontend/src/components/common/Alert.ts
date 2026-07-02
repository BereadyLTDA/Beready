import { ref, onMounted } from 'vue'

export function useAlertComponent(props: { duration: number; onClose: () => void }) {
  const visible = ref(true)

  const close = () => {
    visible.value = false
    setTimeout(() => {
      props.onClose()
    }, 300)
  }

  onMounted(() => {
    if (props.duration > 0) {
      setTimeout(() => {
        close()
      }, props.duration)
    }
  })

  return {
    visible,
    close,
  }
}
