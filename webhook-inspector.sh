#!/bin/bash

# Script para inspeccionar webhooks de WhatsApp en los logs
# Uso: ./webhook-inspector.sh

echo "=================================="
echo "WhatsApp Webhook Inspector"
echo "=================================="
echo ""

# Colores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

LOG_FILE="storage/logs/laravel.log"

if [ ! -f "$LOG_FILE" ]; then
    echo -e "${RED}Error: Log file not found: $LOG_FILE${NC}"
    exit 1
fi

echo -e "${YELLOW}🔍 Buscando webhooks recientes...${NC}"
echo ""

# Buscar últimas 5 entradas de EventPushController
echo -e "${YELLOW}📨 Últimos webhooks recibidos:${NC}"
grep "EventPushController::push()" "$LOG_FILE" | tail -n 5

echo ""
echo -e "${YELLOW}📊 Eventos procesados:${NC}"
grep "ProcessExternalMessageEvent::handle()" "$LOG_FILE" | tail -n 5

echo ""
echo -e "${YELLOW}✅ Mensajes creados:${NC}"
grep "Mensaje creado desde ExternalEvent" "$LOG_FILE" | tail -n 5

echo ""
echo -e "${YELLOW}⚠️ Errores:${NC}"
grep -i "error\|exception" "$LOG_FILE" | grep -i "external\|webhook" | tail -n 5

echo ""
echo "=================================="
echo "Para ver el log completo:"
echo "  tail -f storage/logs/laravel.log"
echo ""
echo "Para filtrar solo webhooks:"
echo "  tail -f storage/logs/laravel.log | grep -E 'EventPushController|ProcessExternalMessageEvent'"
echo "=================================="
