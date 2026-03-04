import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

window.Pusher = Pusher

const scheme = import.meta.env.VITE_REVERB_SCHEME ?? 'http'
const host = import.meta.env.VITE_REVERB_HOST ?? window.location.hostname
const port = Number(import.meta.env.VITE_REVERB_PORT ?? 8080)

window.Echo = new Echo({

  broadcaster: 'reverb',
  key: import.meta.env.VITE_REVERB_APP_KEY,
  wsHost: host,
  wsPort: port,
  wssPort: port,
  forceTLS: scheme === 'https',
  enabledTransports: ['ws', 'wss'],

  // 🔥 esto es CLAVE para private channels
  authEndpoint: '/broadcasting/auth',
  auth: {
    headers: {
      'X-Requested-With': 'XMLHttpRequest',
      // Si tienes el meta csrf en tu layout:
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
    },
  },
})
