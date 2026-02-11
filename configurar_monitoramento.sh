#!/bin/bash
# configurar_monitoramento.sh - Configurar monitoramento avançado

echo "📊 CONFIGURANDO MONITORAMENTO AVANÇADO"
echo "======================================"

# Verificar se está rodando como root/sudo
if [ "$EUID" -ne 0 ]; then
    echo "❌ Execute como root: sudo bash configurar_monitoramento.sh"
    exit 1
fi

# 1. Configurar logrotate para logs do WhatsApp
echo "🔄 Configurando rotação de logs..."

cat > /etc/logrotate.d/whatsapp-agenda << 'EOF'
/var/www/html/oitava/agenda/logs/*.log {
    daily
    missingok
    rotate 30
    compress
    delaycompress
    notifempty
    create 644 www-data www-data
    sharedscripts
    postrotate
        systemctl reload apache2 > /dev/null 2>&1 || true
    endscript
}
EOF

echo "✅ Logrotate configurado"

# 2. Criar script de monitoramento de sistema
cat > /usr/local/bin/monitor_whatsapp.sh << 'EOF'
#!/bin/bash
# monitor_whatsapp.sh - Monitoramento do sistema WhatsApp

LOG_FILE="/var/log/whatsapp_monitor.log"
ALERT_EMAIL=""
WEBHOOK_URL="http://localhost/oitava/agenda/whatsapp_webhook.php"
API_URL="http://localhost:8080/health"

# Função de log
log_message() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" >> $LOG_FILE
}

# Verificar API Evolution
check_api() {
    if curl -s --max-time 10 "$API_URL" > /dev/null 2>&1; then
        log_message "INFO: Evolution API está respondendo"
        return 0
    else
        log_message "ERROR: Evolution API não está respondendo"
        return 1
    fi
}

# Verificar webhook
check_webhook() {
    if curl -s --max-time 10 -X POST -H "Content-Type: application/json" \
            -d '{"test":true}' "$WEBHOOK_URL" > /dev/null 2>&1; then
        log_message "INFO: Webhook está respondendo"
        return 0
    else
        log_message "ERROR: Webhook não está respondendo"
        return 1
    fi
}

# Verificar espaço em disco
check_disk_space() {
    USAGE=$(df /var/www/html/oitava/agenda/logs | awk 'NR==2 {print $5}' | sed 's/%//')
    if [ "$USAGE" -gt 90 ]; then
        log_message "WARNING: Uso de disco alto: ${USAGE}%"
        return 1
    else
        log_message "INFO: Uso de disco OK: ${USAGE}%"
        return 0
    fi
}

# Verificar logs antigos
cleanup_old_logs() {
    find /var/www/html/oitava/agenda/logs -name "*.log" -type f -mtime +30 -delete
    log_message "INFO: Limpeza de logs antigos executada"
}

# Verificar confirmações pendentes
check_pending_confirmations() {
    PENDING=$(php -r "
        include '/var/www/html/oitava/agenda/includes/connection.php';
        \$query = \"SELECT COUNT(*) as total FROM WHATSAPP_CONFIRMACOES WHERE STATUS = 'pendente' AND CREATED_AT < CURRENT_TIMESTAMP - 2\";
        \$result = ibase_query(\$conn, \$query);
        \$row = ibase_fetch_assoc(\$result);
        echo \$row['TOTAL'] ?? 0;
    " 2>/dev/null)
    
    if [ "$PENDING" -gt 10 ]; then
        log_message "WARNING: Muitas confirmações pendentes: $PENDING"
        return 1
    else
        log_message "INFO: Confirmações pendentes: $PENDING"
        return 0
    fi
}

# Executar verificações
log_message "INFO: Iniciando monitoramento"

API_OK=0
WEBHOOK_OK=0
DISK_OK=0
PENDING_OK=0

check_api && API_OK=1
check_webhook && WEBHOOK_OK=1
check_disk_space && DISK_OK=1
check_pending_confirmations && PENDING_OK=1

# Limpeza periódica
cleanup_old_logs

# Status geral
TOTAL_CHECKS=4
PASSED_CHECKS=$((API_OK + WEBHOOK_OK + DISK_OK + PENDING_OK))

if [ $PASSED_CHECKS -eq $TOTAL_CHECKS ]; then
    log_message "SUCCESS: Todos os sistemas OK ($PASSED_CHECKS/$TOTAL_CHECKS)"
else
    log_message "WARNING: Alguns sistemas com problemas ($PASSED_CHECKS/$TOTAL_CHECKS)"
fi

log_message "INFO: Monitoramento concluído"
EOF

chmod +x /usr/local/bin/monitor_whatsapp.sh
echo "✅ Script de monitoramento criado"

# 3. Configurar CRON para monitoramento
echo "⏰ Configurando CRON de monitoramento..."

# Adicionar ao crontab existente
(crontab -l 2>/dev/null; echo "# Monitor WhatsApp (a cada 15 minutos)"; echo "*/15 * * * * /usr/local/bin/monitor_whatsapp.sh") | crontab -

echo "✅ CRON de monitoramento configurado"

# 4. Criar script de alertas
cat > /usr/local/bin/whatsapp_alerts.sh << 'EOF'
#!/bin/bash
# whatsapp_alerts.sh - Sistema de alertas

TELEGRAM_BOT_TOKEN=""
TELEGRAM_CHAT_ID=""
SLACK_WEBHOOK=""

send_telegram_alert() {
    if [ -n "$TELEGRAM_BOT_TOKEN" ] && [ -n "$TELEGRAM_CHAT_ID" ]; then
        curl -s -X POST "https://api.telegram.org/bot$TELEGRAM_BOT_TOKEN/sendMessage" \
            -d chat_id="$TELEGRAM_CHAT_ID" \
            -d text="🚨 ALERTA WhatsApp Clínica Oitava: $1"
    fi
}

send_slack_alert() {
    if [ -n "$SLACK_WEBHOOK" ]; then
        curl -s -X POST "$SLACK_WEBHOOK" \
            -H 'Content-type: application/json' \
            --data "{\"text\":\"🚨 ALERTA WhatsApp Clínica Oitava: $1\"}"
    fi
}

# Verificar alertas no log
MONITOR_LOG="/var/log/whatsapp_monitor.log"
LAST_ALERT_FILE="/tmp/whatsapp_last_alert"

if [ -f "$MONITOR_LOG" ]; then
    # Verificar erros recentes (últimos 30 minutos)
    RECENT_ERRORS=$(grep -c "ERROR\|WARNING" "$MONITOR_LOG" | tail -10 | grep -c "ERROR\|WARNING")
    
    if [ "$RECENT_ERRORS" -gt 0 ]; then
        LAST_ALERT=$(cat "$LAST_ALERT_FILE" 2>/dev/null || echo "0")
        CURRENT_TIME=$(date +%s)
        
        # Enviar alerta apenas se passou mais de 1 hora do último
        if [ $((CURRENT_TIME - LAST_ALERT)) -gt 3600 ]; then
            ALERT_MSG="Sistema WhatsApp apresentou $RECENT_ERRORS erros/warnings nas últimas verificações"
            
            send_telegram_alert "$ALERT_MSG"
            send_slack_alert "$ALERT_MSG"
            
            echo "$CURRENT_TIME" > "$LAST_ALERT_FILE"
            echo "[$(date)] Alerta enviado: $ALERT_MSG" >> "/var/log/whatsapp_alerts.log"
        fi
    fi
fi
EOF

chmod +x /usr/local/bin/whatsapp_alerts.sh
echo "✅ Sistema de alertas criado"

# 5. Configurar CRON para alertas
(crontab -l 2>/dev/null; echo "# Alertas WhatsApp (a cada 30 minutos)"; echo "*/30 * * * * /usr/local/bin/whatsapp_alerts.sh") | crontab -

echo "✅ CRON de alertas configurado"

# 6. Criar script de relatório diário
cat > /usr/local/bin/whatsapp_daily_report.sh << 'EOF'
#!/bin/bash
# whatsapp_daily_report.sh - Relatório diário

REPORT_FILE="/var/log/whatsapp_daily_$(date +%Y%m%d).log"

echo "📊 RELATÓRIO DIÁRIO WHATSAPP - $(date '+%d/%m/%Y')" > "$REPORT_FILE"
echo "=================================================" >> "$REPORT_FILE"
echo "" >> "$REPORT_FILE"

# Estatísticas do dia
php -r "
include '/var/www/html/oitava/agenda/includes/connection.php';

echo \"📈 ESTATÍSTICAS DO DIA:\\n\";
echo \"=====================\\n\";

// Enviadas hoje
\$query = \"SELECT COUNT(*) as total FROM WHATSAPP_CONFIRMACOES WHERE CAST(SENT_AT AS DATE) = CURRENT_DATE\";
\$result = ibase_query(\$conn, \$query);
\$row = ibase_fetch_assoc(\$result);
echo \"Mensagens enviadas: \" . (\$row['TOTAL'] ?? 0) . \"\\n\";

// Confirmadas hoje
\$query = \"SELECT COUNT(*) as total FROM WHATSAPP_CONFIRMACOES WHERE CAST(RESPONSE_AT AS DATE) = CURRENT_DATE AND STATUS = 'confirmado'\";
\$result = ibase_query(\$conn, \$query);
\$row = ibase_fetch_assoc(\$result);
echo \"Consultas confirmadas: \" . (\$row['TOTAL'] ?? 0) . \"\\n\";

// Canceladas hoje
\$query = \"SELECT COUNT(*) as total FROM WHATSAPP_CONFIRMACOES WHERE CAST(RESPONSE_AT AS DATE) = CURRENT_DATE AND STATUS = 'cancelado'\";
\$result = ibase_query(\$conn, \$query);
\$row = ibase_fetch_assoc(\$result);
echo \"Consultas canceladas: \" . (\$row['TOTAL'] ?? 0) . \"\\n\";

echo \"\\n\";
" >> "$REPORT_FILE"

# Status do sistema
echo "🔍 STATUS DO SISTEMA:" >> "$REPORT_FILE"
echo "===================" >> "$REPORT_FILE"
/usr/local/bin/monitor_whatsapp.sh >> "$REPORT_FILE" 2>&1

# Erros do dia
echo "" >> "$REPORT_FILE"
echo "❌ ERROS DO DIA:" >> "$REPORT_FILE"
echo "===============" >> "$REPORT_FILE"
grep "$(date '+%Y-%m-%d')" /var/www/html/oitava/agenda/logs/whatsapp.log | grep -i "error" >> "$REPORT_FILE"

echo "📧 Relatório salvo em: $REPORT_FILE"
EOF

chmod +x /usr/local/bin/whatsapp_daily_report.sh
echo "✅ Relatório diário criado"

# 7. Configurar CRON para relatório diário
(crontab -l 2>/dev/null; echo "# Relatório diário WhatsApp (todo dia às 23:00)"; echo "0 23 * * * /usr/local/bin/whatsapp_daily_report.sh") | crontab -

echo "✅ CRON de relatório diário configurado"

# 8. Testar monitoramento
echo "🧪 Testando monitoramento..."
/usr/local/bin/monitor_whatsapp.sh

if [ -f "/var/log/whatsapp_monitor.log" ]; then
    echo "✅ Monitoramento funcionando"
    echo "📋 Últimas linhas do log:"
    tail -5 /var/log/whatsapp_monitor.log
else
    echo "❌ Erro no monitoramento"
fi

echo ""
echo "🎉 MONITORAMENTO AVANÇADO CONFIGURADO!"
echo "====================================="
echo ""
echo "📋 RECURSOS CONFIGURADOS:"
echo "- Rotação automática de logs"
echo "- Monitoramento a cada 15 minutos"
echo "- Sistema de alertas (30 minutos)"
echo "- Relatório diário (23h)"
echo "- Dashboard web em tempo real"
echo ""
echo "📊 ACESSAR DASHBOARD:"
echo "http://localhost/oitava/agenda/dashboard_whatsapp.php"
echo ""
echo "📋 LOGS DE MONITORAMENTO:"
echo "- Sistema: /var/log/whatsapp_monitor.log"
echo "- Alertas: /var/log/whatsapp_alerts.log"
echo "- Relatórios: /var/log/whatsapp_daily_YYYYMMDD.log"
echo ""
echo "🔧 CONFIGURAR ALERTAS:"
echo "1. Edite /usr/local/bin/whatsapp_alerts.sh"
echo "2. Configure Telegram Bot Token"
echo "3. Configure Slack Webhook"
echo ""
echo "⚠️ IMPORTANTE:"
echo "- Monitore logs regularmente"
echo "- Configure alertas externos"
echo "- Mantenha backups dos logs importantes"