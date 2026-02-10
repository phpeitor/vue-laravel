// resources/js/composables/useWhatsappFormatter.ts

export function useWhatsappFormatter() {
  const formatWhatsappText = (text: string) => {
    if (!text) return ''

    return text
      .replace(/\*(.*?)\*/g, '<strong>$1</strong>')
      .replace(/_(.*?)_/g, '<em>$1</em>')
      .replace(/{{(.*?)}}/g, '<strong>{{ $1 }}</strong>')
      .replace(/\n/g, '<br>')
  }

  return {
    formatWhatsappText,
  }
}
