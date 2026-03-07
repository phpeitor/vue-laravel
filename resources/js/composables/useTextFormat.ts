// resources/js/composables/useTextFormat.ts

export type TitleCaseOptions = {
  /**
   * Mantiene siglas en mayúscula: "CRM", "TI", "SMS", "API"
   * Se considera sigla si la palabra original venía en MAYÚSCULAS
   * y tiene longitud <= maxAcronymLength
   */
  keepAcronyms?: boolean
  maxAcronymLength?: number

  /**
   * Lista blanca de palabras que siempre deben quedar en MAYÚSCULAS
   * (útil para TI, CRM, SMS aunque vengan mezcladas)
   */
  acronyms?: string[]
}

const defaultOpts: Required<TitleCaseOptions> = {
  keepAcronyms: true,
  maxAcronymLength: 4,
  acronyms: ['TI', 'CRM', 'SMS', 'API', 'OK', 'QA'],
}

const normalizeSpaces = (s: unknown) => String(s ?? '').replace(/\s+/g, ' ').trim()

/** Quita "undefined" (cualquier case) y limpia espacios */
const stripUndefined = (s: unknown) =>
  normalizeSpaces(String(s ?? '').replace(/\bundefined\b/gi, ''))

/**
 * Title Case seguro:
 * - respeta palabras con números
 * - respeta siglas si opts.keepAcronyms = true
 */
const toTitleCase = (input: unknown, options?: TitleCaseOptions) => {
  const opts = { ...defaultOpts, ...(options ?? {}) }
  const raw = String(input ?? '')
  const cleaned = normalizeSpaces(raw)

  if (!cleaned) return ''

  // Para decidir si era sigla: miramos la palabra original (antes de lower)
  const originalWords = cleaned.split(' ')
  const upperWhitelist = new Set(opts.acronyms.map(a => a.toUpperCase()))

  const out = originalWords.map((w) => {
    const justWord = w.replace(/[^\p{L}\p{N}]+/gu, '') // letras/números
    const isAllUpper = justWord && justWord === justWord.toUpperCase()

    // sigla por whitelist
    if (upperWhitelist.has(justWord.toUpperCase())) return w.toUpperCase()

    // sigla por regla
    if (
      opts.keepAcronyms &&
      isAllUpper &&
      justWord.length > 1 &&
      justWord.length <= opts.maxAcronymLength
    ) {
      return w.toUpperCase()
    }

    // normal: Title Case (solo primera letra)
    const lower = w.toLowerCase()
    return lower.charAt(0).toUpperCase() + lower.slice(1)
  })

  return out.join(' ')
}

const displayThreadName = (t: { name?: string | null; phone?: string | null; thread_id?: number | string }) => {
  const base = stripUndefined(t.name)

  if (base) return toTitleCase(base)
  if (t.phone) return String(t.phone)
  if (t.thread_id !== undefined && t.thread_id !== null) return `#${t.thread_id}`
  return ''
}

const formatPE = (utc: string | null) => {
  if (!utc) return ''

  // Normaliza: "2026-02-18 07:39:51" -> "2026-02-18T07:39:51Z"
  const normalized = utc.includes('T') ? utc : utc.replace(' ', 'T')
  const withTZ = /Z$|[+\-]\d{2}:\d{2}$/.test(normalized) ? normalized : `${normalized}Z`

  const d = new Date(withTZ)

  // Formato fijo: 18/2/2026, 7:39:51 a. m. (siempre)
  return new Intl.DateTimeFormat('es-PE', {
    timeZone: 'America/Lima', // ✅ UTC-5
    year: 'numeric',
    month: 'numeric',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
    second: '2-digit',
    hour12: true,
  }).format(d)
}

/** Escapa caracteres especiales HTML para uso seguro en atributos y contenido */
const escHtml = (s: unknown): string =>
  String(s ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;')

/**
 * Formatea un mensaje de tipo "referral" (anuncio de Facebook/Meta).
 * Muestra: badge de anuncio, imagen (si media_type=image), titular, cuerpo y URL de origen.
 * @param json  Cadena JSON del item_content o el objeto ya parseado
 */
const formatReferral = (json: unknown): string => {
  let data: Record<string, any>
  try {
    data = typeof json === 'string' ? JSON.parse(json) : (json as Record<string, any> ?? {})
  } catch {
    return escHtml(json)
  }

  const body      = String(data.body       ?? '').trim()
  const headline  = String(data.headline   ?? '').trim()
  const sourceUrl = String(data.source_url ?? '').trim()
  const mediaType = String(data.media_type ?? '').toLowerCase()
  const imageUrl  = String(data.image_url  ?? '').trim()

  const parts: string[] = []

  // Badge de anuncio
  parts.push(
    `<div style="display:inline-flex;align-items:center;gap:4px;font-size:11px;opacity:0.65;margin-bottom:8px;">` +
    `<span style="background:rgba(0,0,0,.12);border-radius:4px;padding:1px 6px;font-weight:600;">📢 Anuncio</span>` +
    `</div>`
  )

  // Imagen
  if (mediaType === 'image' && imageUrl) {
    parts.push(
      `<img src="${escHtml(imageUrl)}" alt="${escHtml(headline || 'Anuncio')}" ` +
      `style="border-radius:8px;max-width:100%;display:block;margin-bottom:8px;" ` +
      `loading="lazy" onerror="this.style.display='none'" />`
    )
  }

  // Titular
  if (headline) {
    parts.push(
      `<div style="font-weight:600;font-size:13px;margin-bottom:4px;">${escHtml(headline)}</div>`
    )
  }

  // Cuerpo
  if (body) {
    parts.push(
      `<div style="font-size:13px;line-height:1.45;">${escHtml(body)}</div>`
    )
  }

  // URL de origen
  if (sourceUrl) {
    parts.push(
      `<a href="${escHtml(sourceUrl)}" target="_blank" rel="noopener noreferrer" ` +
      `style="font-size:11px;opacity:0.65;text-decoration:underline;display:block;margin-top:8px;` +
      `overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:100%;">${escHtml(sourceUrl)}</a>`
    )
  }

  return `<div style="display:flex;flex-direction:column;">${parts.join('')}</div>`
}

export function useTextFormat() {
  return {
    normalizeSpaces,
    stripUndefined,
    toTitleCase,
    displayThreadName,
    formatPE,
    escHtml,
    formatReferral,
  }
}
