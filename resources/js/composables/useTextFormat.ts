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

export function useTextFormat() {
  return {
    normalizeSpaces,
    stripUndefined,
    toTitleCase,
    displayThreadName,
    formatPE,
  }
}
