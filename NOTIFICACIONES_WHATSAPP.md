# Sistema de Notificaciones de Mensajes WhatsApp

## ⚠️ IMPORTANTE: Estructura del Webhook NO Verificada

La estructura JSON que espera este sistema **FUE ASUMIDA** basada en:
- Los campos del modelo `Message`
- Los datos usados en el frontend
- Patrones comunes de webhooks

**PERO NO ESTÁ VERIFICADA contra el omnichannel real que usa tu aplicación.**

### 📝 Próximos pasos:
1. **Envía un mensaje desde WhatsApp** a tu número de prueba
2. **Revisa los logs** en `storage/logs/laravel.log`
3. **Busca la entrada** `EventPushController::push() recibió webhook`
4. **Copia el JSON completo** que ves en los logs
5. **Compara con la estructura esperada** abajo
6. **Si son diferentes**, necesitaremos ajustar el listener

---

## Problema Identificado

El sistema no emitía notificaciones cuando se recibía un mensaje desde WhatsApp. El evento `ExternalEventReceived` se disparaba pero no había un listener que procesara el evento y creara el registro de `Message` en la BD.

## Solución Implementada

Se agregó un flujo completo de procesamiento de eventos externos:

### 1. **Listener: `ProcessExternalMessageEvent`**
   - Archivo: `app/Listeners/ProcessExternalMessageEvent.php`
   - Escucha el evento `ExternalEventReceived`
   - Procesa el payload y crea un registro de `Message` en la BD
   - Eager loads la relación `thread` para que esté disponible en el evento broadcast
   - **Loguea el payload completo para debugging**

### 2. **Event Service Provider: `EventServiceProvider`**
   - Archivo: `app/Providers/EventServiceProvider.php`
   - Registra el mapping entre `ExternalEventReceived` y `ProcessExternalMessageEvent`
   - Se agregó a `bootstrap/providers.php`

### 3. **Actualización del Evento `MessageCreated`**
   - Archivo: `app/Events/MessageCreated.php`
   - Se corrigieron los campos en `broadcastWith()` para usar los nombres correctos del modelo
   - Ahora envía a ambos canales:
     - `chat.thread.{threadId}` - para actualizaciones en el chat abierto
     - `chat.company.{companyId}` - para actualizaciones en el listado de conversaciones

### 4. **Modelo `Customer`**
   - Archivo: `app/Models/Customer.php`
   - Se creó el modelo faltante utilizado por el listener y Message

### 5. **Logging Mejorado**
   - `EventPushController::push()` ahora loguea todo el payload recibido
   - `ProcessExternalMessageEvent::handle()` loguea la estructura del evento

## Flujo Completo

```
WhatsApp Meta → omnichannel.talina.xyz → POST /api/external-event
    ↓
EventPushController::push() recibe y valida (loguea payload completo)
    ↓
broadcast(new ExternalEventReceived($data))
    ↓
ProcessExternalMessageEvent::handle() procesa (loguea estructura)
    ↓
Message::create() crea el registro
    ↓
MessageObserver::created() se dispara
    ↓
broadcast(new MessageCreated($message))
    ↓
Frontend recibe en canales privados
    ├─ chat.thread.{threadId}
    └─ chat.company.{companyId}
```

## 🔍 ESTRUCTURA DEL PAYLOAD - IMPORTANTE

La estructura exacta que envía `omnichannel.talina.xyz` **NO ESTÁ DOCUMENTADA EN EL CÓDIGO**. He hecho una **asunción educada** basada en:

1. Los campos que espera el modelo `Message`
2. Los datos que usa el frontend
3. Los patrones comunes de webhooks

**PERO NECESITAS VERIFICAR** cuál es la estructura real que envía tu omnichannel.

### Para determinar la estructura real:

**Opción 1: Ver los logs**
```bash
tail -f storage/logs/laravel.log | grep -A 20 "EventPushController::push"
```

**Opción 2: Enviar un mensaje desde WhatsApp y revisar**
1. Abre WhatsApp
2. Envía un mensaje a tu número de prueba
3. Revisa los logs en `storage/logs/laravel.log`
4. Busca la entrada `EventPushController::push() recibió webhook`
5. Copia el `all_data` completo

### Estructura Asumida (puede variar)

```json
{
  "type": "message.incoming",
  "data": {
    "thread_id": 123,
    "customer_id": 456,
    "external_id": "wamid.xxxxx",
    "item_type": "text",
    "item_content": "Hola, ¿cómo estás?",
    "create_date": "2025-02-17 10:30:00"
  }
}
```

### Campos esperados en `data`:
- `thread_id` (integer) - ID del thread en la BD
- `customer_id` (integer) - ID del customer en la BD  
- `external_id` (string) - ID único del mensaje en WhatsApp (evita duplicados)
- `item_type` (string) - Tipo de contenido, e.g., "text"
- `item_content` (string) - Contenido del mensaje
- `create_date` (string) - Fecha/hora del mensaje

**⚠️ Si la estructura real es diferente, necesitaremos ajustar el listener.**

## Datos Emitidos al Frontend

El evento `MessageCreated` emite los siguientes datos:

```php
[
    'message_id' => $this->message->id,
    'thread_id' => $this->message->thread_id,
    'customer_id' => $this->message->customer_id,
    'item_type' => $this->message->item_type,
    'item_content' => $this->message->item_content,
    'message_create_date' => $this->message->create_date,
    'origin' => $this->message->origin,           // 'EXTERNAL' o 'APP'
    'external_id' => $this->message->external_id,
    'enviado_por' => 'USUARIO' o 'BOT',
]
```

## Frontend - Chat/Index.vue

El frontend ya está configurado para escuchar estos eventos:

### En el canal del thread:
```javascript
threadChannel.listen('.message.created', (e) => {
  // Agrega el mensaje al chat si es un usuario externo
  if (e.origin === 'EXTERNAL' || e.enviado_por === 'USUARIO') {
    messagesList.value.push(e)
  }
})
```

### En el canal de la compañía:
```javascript
companyChannel.listen('.message.created', (e) => {
  // Actualiza el preview del thread en el listado
  const idx = threadsList.value.findIndex(t => t.thread_id === e.thread_id)
  if (idx >= 0) {
    threadsList.value[idx] = {
      ...threadsList.value[idx],
      last_message: e.item_content,
      last_at: e.message_create_date,
    }
  }
})
```

## 📚 Documentación Adicional

- **[VERIFICAR_ESTRUCTURA_WEBHOOK.md](VERIFICAR_ESTRUCTURA_WEBHOOK.md)** - Guía completa para verificar la estructura real del webhook
- **webhook-inspector.sh** - Script bash para inspeccionar logs de webhooks

## Logs

El sistema registra en logs:
- Payloads completos recibidos en EventPushController
- Estructura de eventos en ProcessExternalMessageEvent
- Mensajes creados correctamente
- Eventos ignorados (tipo incorrecto)
- Errores de validación
- Duplicados detectados

Ver logs:
```bash
tail -f storage/logs/laravel.log
```

### Buscar logs específicos del webhook:
```bash
tail -f storage/logs/laravel.log | grep -E "EventPushController|ProcessExternalMessageEvent|ExternalEvent"
```

## Testing y Validación

### 1️⃣ Enviar mensaje desde WhatsApp y revisar logs
- Abre WhatsApp y envía un mensaje a tu número de prueba
- Revisa los logs para ver qué estructura real envía el omnichannel
- Busca `EventPushController::push() recibió webhook`
- **Copia los keys y valores reales**
- Compara con la estructura asumida arriba

### 2️⃣ Simular webhook manualmente (si sabes la estructura real)

```bash
curl -X POST http://localhost:8000/api/external-event \
  -H "Content-Type: application/json" \
  -d '{
    "type": "message.incoming",
    "data": {
      "thread_id": 1,
      "customer_id": 1,
      "external_id": "test_'$(date +%s)'",
      "item_type": "text",
      "item_content": "Mensaje de prueba desde cURL",
      "create_date": "'$(date -u +'%Y-%m-%d %H:%M:%S')''"
    }
  }'
```

### 3️⃣ Revisar que se creó el mensaje
```bash
# En la BD, busca el mensaje creado
SELECT * FROM messages WHERE external_id LIKE 'test_%' ORDER BY id DESC LIMIT 5;
```

### 4️⃣ Revisar que se emitió el broadcast
- Abre la interfaz de Chat en el navegador
- Deberías ver el mensaje aparecer en tiempo real
- Si no aparece, revisa la consola del navegador para errores de broadcasting

## Requisitos de BD

Asegúrate de que las tablas existan con los campos correctos:

### Tabla: messages
- `id` (PK)
- `thread_id` (FK)
- `customer_id` (FK)
- `external_id` (string, unique)
- `item_type` (string)
- `item_content` (text)
- `origin` (string) - 'EXTERNAL', 'APP', etc.
- `create_date` (datetime)

### Tabla: threads
- `id` (PK)
- `company_id` (FK)
- `communication_channel_id` (FK)
- ... otros campos

### Tabla: customers
- `id` (PK)
- `name` (string)
- `phone` (string)
- `email` (string, nullable)
- `company_id` (FK, nullable)
- `create_date` (datetime)

## 🔗 Integración con Omnichannel (Talina)

Tu sistema usa **omnichannel.talina.xyz** como intermediario entre WhatsApp Meta y tu API.

### Configuración actual:
- `CHAT_THREAD_BASE_URL=https://omnichannel.talina.xyz/webhook/thread` - Para responder mensajes
- `WHATSAPP_SEND_URL`, `WHATSAPP_SYNC_URL`, `WHATSAPP_NEW_URL` - Para operaciones de WhatsApp

### Flujo de mensajes:
1. **Mensaje entra a WhatsApp Meta** → Webhook de Meta
2. **Omnichannel.talina.xyz recibe el webhook** → Procesa y transforma
3. **Omnichannel envía POST a tu `/api/external-event`** ← **AQUÍ ES DONDE VERIFICA LA ESTRUCTURA**
4. **Tu sistema procesa y emite notificaciones** → Frontend recibe via WebSocket

### Para verificar la configuración del omnichannel:
- Contacta con el equipo de Talina/Omnichannel
- Solicita la documentación de la estructura del webhook que envían
- Verifica que la URL de callback esté configurada como `{tu_app}/api/external-event`
