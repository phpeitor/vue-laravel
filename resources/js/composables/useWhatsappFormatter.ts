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
      .replace(/~(.*?)~/g, '<u>$1</u>')
      .replace(/{{(.*?)}}/g, '<strong>{{ $1 }}</strong>')
      .replace(/\n/g, '<br>')
  }

  const htmlToWhatsappText = (html: string) => {
    if (!html) return ''

    const root = document.createElement('div')
    root.innerHTML = html

    const walk = (node: Node): string => {
      if (node.nodeType === Node.TEXT_NODE) {
        return (node.nodeValue ?? '').replace(/\u00A0/g, ' ')
      }

      if (node.nodeType !== Node.ELEMENT_NODE) return ''

      const el = node as HTMLElement
      const tag = el.tagName.toLowerCase()

      if (tag === 'br') return '\n'

      const inner = Array.from(el.childNodes).map(walk).join('')

      if (tag === 'strong' || tag === 'b') return `*${inner}*`
      if (tag === 'em' || tag === 'i') return `_${inner}_`
      if (tag === 'u') return `~${inner}~`

      // bloques -> agrega salto si corresponde
      if (tag === 'div' || tag === 'p') {
        return inner.endsWith('\n') ? inner : inner + '\n'
      }

      return inner
    }

    let out = Array.from(root.childNodes).map(walk).join('')
    // limpia saltos de más al final (opcional)
    out = out.replace(/\n+$/g, '')
    return out
  }

  return { formatWhatsappText, htmlToWhatsappText }
}