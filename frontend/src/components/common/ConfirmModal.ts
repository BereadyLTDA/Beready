import { computed } from 'vue'

export function useConfirmModal() {
  const props = defineProps({
    modelValue: {
      type: Boolean,
      default: false
    },
    title: {
      type: String,
      default: 'Confirmar ação'
    },
    message: {
      type: String,
      default: 'Tem certeza que deseja realizar esta ação?'
    },
    confirmText: {
      type: String,
      default: 'Confirmar'
    },
    type: {
      type: String,
      default: 'danger',
      validator: (value: string) => ['danger', 'warning', 'info'].includes(value)
    },
    itemName: {
      type: String,
      default: ''
    },
    loading: {
      type: Boolean,
      default: false
    }
  })

  const emit = defineEmits(['update:modelValue', 'confirm'])

  const handleClose = () => {
    emit('update:modelValue', false)
  }

  const handleConfirm = () => {
    emit('confirm')
  }

  return {
    modelValue: computed(() => props.modelValue),
    title: computed(() => props.title),
    message: computed(() => props.message),
    confirmText: computed(() => props.confirmText),
    type: computed(() => props.type),
    itemName: computed(() => props.itemName),
    loading: computed(() => props.loading),
    handleClose,
    handleConfirm
  }
}