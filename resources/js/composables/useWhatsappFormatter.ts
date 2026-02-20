// resources/js/composables/useWhatsappFormatter.ts
export function useWhatsappFormatter() {
  const escapeHtml = (s: string) =>
    s
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;')

  const formatWhatsappText = (text: string) => {
    if (!text) return ''

    const safe = escapeHtml(text)

    return safe
      .replace(/\*(.*?)\*/g, '<strong>$1</strong>')
      .replace(/_(.*?)_/g, '<em>$1</em>')
      .replace(/{{(.*?)}}/g, '<strong>{{ $1 }}</strong>')
      .replace(/\n/g, '<br>')
  }

  return { formatWhatsappText }
}
