import { onMounted, ref } from 'vue';

type Appearance = 'light' | 'dark' | 'system';
type StatusVariant = 'default' | 'secondary' | 'destructive' | 'outline'
const normalizeUpper = (s: unknown) => String(s ?? '').trim().toUpperCase()

const statusVariant = (s: unknown): StatusVariant => {
  const v = normalizeUpper(s)
  if (['SENT', 'FINALLY', 'READY', 'SCHEDULED'].includes(v)) return 'default'
  if (['RUNNING', 'PROCESSING', 'SENDING', 'UPLOADED'].includes(v)) return 'secondary'
  if (['FAILED', 'FINALLY_FAILED'].includes(v)) return 'destructive'
  return 'outline'
}

const badgeClass = (s: unknown): string => {
  const v = normalizeUpper(s)
  if (['FINALLY', 'SENT', 'READY'].includes(v)) {
    return 'bg-emerald-600/20 text-emerald-300 border-emerald-600/30'
  }
  if (['RUNNING', 'PROCESSING', 'SENDING', 'UPLOADED', 'SCHEDULED'].includes(v)) {
    return 'bg-sky-600/20 text-sky-300 border-sky-600/30'
  }
  if (['FAILED', 'FINALLY_FAILED'].includes(v)) {
    return 'bg-red-600/20 text-red-300 border-red-600/30'
  }
  if (v === 'PENDING') {
    return 'bg-amber-600/20 text-amber-300 border-amber-600/30'
  }
  return 'bg-muted text-foreground'
}

const statusBadgeClass = (s: string) => {
  const v = String(s ?? '').toUpperCase()
  if (v.includes('LEIDO') || v.includes('READ')) return 'bg-emerald-600/20 text-emerald-300 border border-emerald-600/30'
  if (v.includes('ENTREGADO') || v.includes('DELIVER')) return 'bg-sky-600/20 text-sky-300 border border-sky-600/30'
  if (v.includes('ENVIADO') || v.includes('SENT')) return 'bg-amber-600/20 text-amber-300 border border-amber-600/30'
  if (v.includes('FALLIDO') || v.includes('SENT')) return 'bg-red-600/20 text-red-300 border border-red-600/30'
  return 'bg-muted text-foreground border border-border'
}

export function updateTheme(value: Appearance) {
    if (typeof window === 'undefined') {
        return;
    }

    if (value === 'system') {
        const mediaQueryList = window.matchMedia('(prefers-color-scheme: dark)');
        const systemTheme = mediaQueryList.matches ? 'dark' : 'light';

        document.documentElement.classList.toggle('dark', systemTheme === 'dark');
    } else {
        document.documentElement.classList.toggle('dark', value === 'dark');
    }
}

const setCookie = (name: string, value: string, days = 365) => {
    if (typeof document === 'undefined') {
        return;
    }

    const maxAge = days * 24 * 60 * 60;

    document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
};

const mediaQuery = () => {
    if (typeof window === 'undefined') {
        return null;
    }

    return window.matchMedia('(prefers-color-scheme: dark)');
};

const getStoredAppearance = () => {
    if (typeof window === 'undefined') {
        return null;
    }

    return localStorage.getItem('appearance') as Appearance | null;
};

const handleSystemThemeChange = () => {
    const currentAppearance = getStoredAppearance();

    updateTheme(currentAppearance || 'system');
};

export function initializeTheme() {
    if (typeof window === 'undefined') {
        return;
    }

    const savedAppearance = getStoredAppearance();
    updateTheme(savedAppearance || 'system');
    mediaQuery()?.addEventListener('change', handleSystemThemeChange);
}

export function useAppearance() {
    const appearance = ref<Appearance>('system');

    onMounted(() => {
        initializeTheme();

        const savedAppearance = localStorage.getItem('appearance') as Appearance | null;

        if (savedAppearance) {
            appearance.value = savedAppearance;
        }
    });

    function updateAppearance(value: Appearance) {
        appearance.value = value;
        localStorage.setItem('appearance', value);
        setCookie('appearance', value);
        updateTheme(value);
    }

    return {
        appearance,
        updateAppearance,
        normalizeUpper,
        statusVariant,
        badgeClass,
        statusBadgeClass,
    };
}
