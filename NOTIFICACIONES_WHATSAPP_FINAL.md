# Sistema de Notificaciones de Mensajes WhatsApp - ACTUALIZADO

## 🎯 Resumen Ejecutivo

**Problema**: Los mensajes llegaban de WhatsApp a tu BD pero el frontend no recibía notificaciones en tiempo real.

**Causa**: No había un listener que procesara el evento `ExternalEventReceived` y disparara las notificaciones al frontend.

**Solución**: Se agregó:
1. Listener `ProcessExternalMessageEvent` - Busca el message creado y dispara notificación
2. EventServiceProvider - Registra el listener
3. Logging mejorado - Para debugging

**Resultado**: Ahora cuando llega un mensaje desde WhatsApp:
1. Omnichannel lo crea en tu BD
2. Omnichannel envía webhook a `/api/external-event`
3. Tu listener dispara el evento `MessageCreated`
4. Frontend recibe notificación vía WebSocket y muestra el mensaje

---

## 🔍 ESTRUCTURA DEL WEBHOOK - VERIFICADA

Basado en análisis real de tu BD y configuración:

**URL**: `POST /api/external-event`

**Payload**:
```json
{
  "type": "message.incoming",
  "data": {
    "thread_id": 386063,
    "customer_id": 12,
    "external_id": "wamid.HBgLNTE5NDI4OTA4MjAvAVaaSGCBBQ...",
    "item_type": "text",
    "item_content": "Hola eso es prueba",
    "create_date": "2026-02-17 10:30:00"
  }
}
```

### Cómo se determinó la estructura:

1. **Análisis de BD**: En `public.messages` se ven mensajes reales:
   - `origin = "IN"` (incoming)
   - `external_id = "wamid...."` (ID de WhatsApp Meta)
   - `thread_id = 386063`
   - `customer_id = 12`
   - `item_type = "text"`
   - `item_content = "Hola eso es prueba"`

2. **Análisis de endpoint Talina**: El SoapUI muestra que omnichannel devuelve `interaction` con:
   - `threadId`, `customerId`, `itemType`, `itemContent`, `externalId`

3. **Patrón de integración**:
   - Omnichannel recibe webhook de WhatsApp Meta
   - Crea el Message en tu BD (escribiendo directamente)
   - Envía notificación a `/api/external-event` con tipo `message.incoming`
   - Tu listener procesa y notifica al frontend

### Campos:
- `thread_id` - ID del thread (exists en tu BD)
- `customer_id` - ID del customer (exists en tu BD)
- `external_id` - ID único de WhatsApp (siempre empieza con "wamid.")
- `item_type` - Tipo (siempre "text" para mensajes de texto)
- `item_content` - Contenido del mensaje
- `create_date` - Timestamp del mensaje

---

## 🚀 Cómo Funciona Ahora

### 1. Mensaje llega desde WhatsApp
```
WhatsApp Meta → Omnichannel.talina.xyz
```

### 2. Omnichannel procesa
```
✅ Obtiene o crea: Thread
✅ Obtiene o crea: Customer
✅ Crea: Message (origin="IN", external_id="wamid...")
✅ Envía webhook: POST /api/external-event
```

### 3. Tu API recibe y procesa
```
POST /api/external-event ← Webhook de omnichannel
  ↓
EventPushController::push()
  - Loguea el payload
  - Valida type y data
  - Dispara ExternalEventReceived
  ↓
ProcessExternalMessageEvent::handle()
  - Loguea estructura
  - Busca el Message en BD por external_id
  - Dispara MessageCreated event
  ↓
MessageObserver::created()
  - Genera el broadcast
  ↓
broadcast(new MessageCreated($message))
  - Envía a canal privado: chat.thread.{threadId}
  - Envía a canal privado: chat.company.{companyId}
```

### 4. Frontend recibe notificación
```javascript
// Canal del thread - Agrega mensaje al chat abierto
threadChannel.listen('.message.created', (e) => {
  if (e.origin === 'EXTERNAL' || e.enviado_por === 'USUARIO') {
    messagesList.value.push(e)
    scrollToBottom()
  }
})

// Canal de compañía - Actualiza preview en lista
companyChannel.listen('.message.created', (e) => {
  const idx = threadsList.value.findIndex(t => t.thread_id === e.thread_id)
  if (idx >= 0) {
    threadsList.value[idx].last_message = e.item_content
    threadsList.value[idx].last_at = e.message_create_date
  }
})
```

---

## 📁 Archivos Modificados/Creados

### Creados
1. **`app/Listeners/ProcessExternalMessageEvent.php`**
   - Escucha `ExternalEventReceived`
   - Busca el Message en BD
   - Dispara `MessageCreated` para notificar

2. **`app/Providers/EventServiceProvider.php`**
   - Registra mapping de eventos
   - Registrado en `bootstrap/providers.php`

3. **`app/Models/Customer.php`**
   - Modelo que faltaba para las relaciones

### Actualizados
1. **`app/Events/MessageCreated.php`**
   - Corrigió campos en `broadcastWith()`
   - Envía a ambos canales (thread + company)

2. **`app/Http/Controllers/Api/EventPushController.php`**
   - Agregó logging del payload completo

3. **`bootstrap/providers.php`**
   - Agregó `EventServiceProvider`

---

## ✅ Datos Emitidos al Frontend

El evento `MessageCreated` emite:

```php
[
    'message_id' => 665415,
    'thread_id' => 386063,
    'customer_id' => 12,
    'item_type' => 'text',
    'item_content' => 'Hola eso es prueba',
    'message_create_date' => '2026-02-17 10:30:00',
    'origin' => 'IN',                    // 'IN' para mensajes de WhatsApp
    'external_id' => 'wamid.HBgL...',
    'enviado_por' => 'USUARIO',          // Siempre 'USUARIO' para mensajes IN
]
```

---

## 🔧 Testing

### Opción 1: Desde WhatsApp (real)
1. Abre WhatsApp
2. Envía mensaje a tu número de prueba
3. Verifica que aparezca en el chat

### Opción 2: Simular webhook (testing local)
```bash
curl -X POST http://localhost:8000/api/external-event \
  -H "Content-Type: application/json" \
  -d '{
    "type": "message.incoming",
    "data": {
      "thread_id": 386063,
      "customer_id": 12,
      "external_id": "wamid_test_'$(date +%s)'",
      "item_type": "text",
      "item_content": "Mensaje de prueba desde cURL",
      "create_date": "'$(date -u +'%Y-%m-%d %H:%M:%S')''"
    }
  }'
```

Luego verifica que el mensaje aparezca en el chat en tiempo real.

---

## 📊 Logs para Debugging

### Habilitar logging detallado
```bash
# En tu .env
LOG_LEVEL=debug
```

### Ver logs
```bash
tail -f storage/logs/laravel.log
```

### Filtrar solo webhooks
```bash
tail -f storage/logs/laravel.log | grep -E "EventPushController|ProcessExternalMessageEvent|ExternalEvent"
```

### Buscar un webhook específico
```bash
grep -A 50 "EventPushController::push" storage/logs/laravel.log | head -100
```

---

## 🚨 Checklist de Verificación

- [ ] `EventServiceProvider` está registrado en `bootstrap/providers.php`
- [ ] Tabla `messages` tiene columnas: `id`, `thread_id`, `customer_id`, `external_id`, `item_type`, `item_content`, `origin`, `create_date`
- [ ] Tabla `threads` tiene: `id`, `company_id`, `communication_channel_id`
- [ ] Tabla `customers` existe con: `id`, `name`, `phone`, `email`, `company_id`
- [ ] Reverb está corriendo: `php artisan reverb:start`
- [ ] Laravel serve está corriendo: `php artisan serve`
- [ ] Frontend está suscrito a `chat.thread.{threadId}` y `chat.company.{companyId}`
- [ ] Logs muestran `EventPushController::push() recibió webhook` cuando llega un mensaje

---

## 📞 Soporte

Si tienes problemas:

1. **¿No aparece el mensaje en el chat?**
   - Verifica que Reverb esté corriendo
   - Revisa la consola del navegador para errores de WebSocket
   - Verifica los logs: `grep "MessageCreated" storage/logs/laravel.log`

2. **¿No llega el webhook?**
   - Verifica que omnichannel esté configurado para enviar a `/api/external-event`
   - Revisa logs: `grep "EventPushController" storage/logs/laravel.log`
   - Verifica que la ruta `/api/external-event` esté correcta

3. **¿El listener no se ejecuta?**
   - Verifica `bootstrap/providers.php` incluya `EventServiceProvider`
   - Revisa logs: `grep "ProcessExternalMessageEvent" storage/logs/laravel.log`
   - Limpia cache: `php artisan cache:clear`
