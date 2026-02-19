# Cómo Verificar la Estructura Real del Webhook de WhatsApp

## El Problema

No sabemos exactamente qué estructura JSON envía `omnichannel.talina.xyz` cuando un mensaje llega desde WhatsApp. El código actual **asume** una estructura, pero podría ser diferente.

## La Solución: Logging del Webhook Real

Se ha agregado logging detallado en dos lugares:

### 1. En `EventPushController::push()`
```php
Log::info('EventPushController::push() recibió webhook', [
    'content_type' => $request->header('Content-Type'),
    'all_data' => $request->all(),
]);
```

### 2. En `ProcessExternalMessageEvent::handle()`
```php
Log::debug('ProcessExternalMessageEvent::handle() procesando evento', [
    'type' => $payload['type'] ?? 'unknown',
    'payload_keys' => array_keys($payload),
    'data_keys' => isset($payload['data']) ? array_keys($payload['data']) : [],
    'full_payload' => $payload,
]);
```

## Pasos para Verificar la Estructura

### Paso 1: Asegúrate que el logging está habilitado
```bash
# Edita tu .env
LOG_LEVEL=debug
```

### Paso 2: Limpia los logs anteriores (opcional)
```bash
rm storage/logs/laravel.log
touch storage/logs/laravel.log
```

### Paso 3: Envía un mensaje desde WhatsApp
- Abre WhatsApp
- Envía un mensaje a tu número de prueba
- Espera 2-3 segundos

### Paso 4: Revisa los logs
```bash
tail -f storage/logs/laravel.log
```

Busca una línea como:
```
[2025-02-17 10:30:45] local.INFO: EventPushController::push() recibió webhook 
{"content_type":"application/json","all_data":{"type":"message.incoming","data":{...}}}
```

### Paso 5: Extrae el JSON completo
Copia el contenido de `all_data` completo.

### Paso 6: Compara con la estructura esperada

La estructura **esperada** es:
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

### Paso 7: Si es diferente, ajusta el listener

Si la estructura real es diferente (ej: los campos se llaman `message_id` en lugar de `external_id`, o `body` en lugar de `item_content`), necesitaremos actualizar `ProcessExternalMessageEvent`.

**Ejemplo**: Si la estructura real es:
```json
{
  "type": "message.incoming",
  "content": {
    "msg_id": "123",
    "conversation_id": "456",
    "msg_text": "Hola"
  }
}
```

Entonces en el listener haremos:
```php
$externalId = (string) ($data['msg_id'] ?? '');
$threadId = (int) ($data['conversation_id'] ?? 0);
$itemContent = (string) ($data['msg_text'] ?? '');
```

## Alternativa: Inspeccionar directamente en BD

Si los webhooks ya han llegado, puedes revisar en la BD:

```sql
-- Ver los últimos payloads si los guardaste en algún lugar
SELECT * FROM webhooks ORDER BY created_at DESC LIMIT 5;
-- o ver los mensajes creados
SELECT * FROM messages WHERE origin = 'EXTERNAL' ORDER BY id DESC LIMIT 5;
```

## Contactar al soporte del Omnichannel

Si no puedes verificar los webhooks fácilmente, contacta a:
- **Talina/Omnichannel Support**
- Solicita: "Documentación de la estructura del webhook para `/api/external-event`"
- Incluye: El tipo de evento (`message.incoming`) y los campos exactos que envían

## Debugging Avanzado

Si aún no ves los logs, verifica:

1. **¿Llega el webhook?**
   ```bash
   # Agrega un log inicial en EventPushController
   Log::info('WEBHOOK RECIBIDO!'); // Antes de validate
   ```

2. **¿Se ejecuta el listener?**
   ```bash
   # Revisa si hay logs de ProcessExternalMessageEvent
   grep "ProcessExternalMessageEvent" storage/logs/laravel.log
   ```

3. **¿Está el EventServiceProvider registrado?**
   ```bash
   # Verifica bootstrap/providers.php
   cat bootstrap/providers.php
   ```

4. **¿Se crean los mensajes?**
   ```bash
   # Revisa si hay nuevos mensajes
   SELECT COUNT(*) FROM messages WHERE create_date > NOW() - INTERVAL '5 minutes';
   ```

5. **¿Se emiten los broadcasts?**
   - Abre el chat en el navegador
   - Abre DevTools → Console
   - Busca logs de Echo.listen() o broadcast
   - Verifica que Reverb esté corriendo: `php artisan reverb:start`
