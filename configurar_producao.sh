#!/bin/bash
# configurar_producao.sh - Configurar sistema para produção

echo "🔧 CONFIGURANDO SISTEMA PARA PRODUÇÃO"
echo "====================================="

# Backup da configuração atual
echo "💾 Fazendo backup da configuração atual..."
cp /var/www/html/oitava/agenda/whatsapp_config.php /var/www/html/oitava/agenda/whatsapp_config.php.backup
echo "✅ Backup salvo em whatsapp_config.php.backup"

# Verificar se Evolution API está rodando
echo "🔍 Verificando Evolution API..."
if curl -f http://localhost:8080/health > /dev/null 2>&1; then
    echo "✅ Evolution API está funcionando"
else
    echo "❌ Evolution API não está funcionando"
    echo "Execute primeiro: sudo bash install_evolution_api.sh"
    exit 1
fi

# Atualizar configuração para produção
echo "🔧 Atualizando configuração para produção..."

# Solicitar domínio público
read -p "📡 Digite seu domínio público (ex: meusite.com.br): " DOMINIO_PUBLICO

if [ -z "$DOMINIO_PUBLICO" ]; then
    echo "⚠️ Usando localhost (só funcionará localmente)"
    DOMINIO_PUBLICO="localhost"
fi

# Criar nova configuração
cat > /var/www/html/oitava/agenda/whatsapp_config_producao.php << EOF
<?php
// whatsapp_config.php - Configurações do sistema WhatsApp (PRODUÇÃO)

// ========== EVOLUTION API (PRODUÇÃO) ==========
\$WHATSAPP_CONFIG = [
    'api_provider' => 'evolution',
    'api_url' => 'http://localhost:8080', // Evolution API local
    'instance_name' => 'CLINICA_OITAVA',
    'api_key' => 'CLINICA_OITAVA_2025_API_KEY',
    'webhook_url' => 'https://$DOMINIO_PUBLICO/oitava/agenda/whatsapp_webhook.php',
    
    // Configurações de envio otimizadas
    'delay_between_messages' => 2, // Maior delay para produção
    'max_retries' => 5, // Mais tentativas
    'timeout' => 45, // Timeout maior
    
    // Horários de funcionamento
    'working_hours' => [
        'start' => '08:00',
        'end' => '18:00'
    ],
    
    // Configurações da clínica (ATUALIZE AQUI)
    'clinic_info' => [
        'name' => 'CLÍNICA OITAVA',
        'phone' => '(84) 3421-8410',
        'address' => 'Mossoró - RN',
        'website' => 'https://$DOMINIO_PUBLICO'
    ]
];

// CONFIGURAÇÕES DE TIMING (PRODUÇÃO)
\$TIMING_CONFIG = [
    'hours_before_appointment' => 24,
    'reminder_hours' => 2,
    'max_attempts_per_appointment' => 3,
    'retry_interval_minutes' => 120 // 2 horas entre tentativas
];

// TEMPLATES DE MENSAGENS (PRODUÇÃO)
\$MESSAGE_TEMPLATES = [
    'confirmation' => "🏥 *{{clinic_name}}*\\n\\nOlá *{{patient_name}}*!\\n\\nVocê tem consulta agendada para:\\n📅 *{{date}}* às *{{time}}*\\n👨‍⚕️ {{doctor}} - {{specialty}}\\n📍 {{unit}}\\n\\n*Confirme sua presença:*\\n✅ *1* - CONFIRMAR\\n❌ *2* - CANCELAR\\n🔄 *3* - REAGENDAR\\n\\n_Responda apenas o número_\\n\\nEm caso de dúvidas:\\n📞 {{clinic_phone}}",
    
    'reminder' => "🔔 *LEMBRETE - {{clinic_name}}*\\n\\nOlá {{patient_name}}!\\n\\nSua consulta é *HOJE* às *{{time}}*\\n👨‍⚕️ {{doctor}} - {{specialty}}\\n📍 {{unit}}\\n\\n*Por favor, chegue 15 minutos antes*\\n\\nNos vemos em breve! 😊",
    
    'confirmed' => "✅ *Consulta confirmada!*\\n\\nObrigado por confirmar sua presença.\\nEstaremos esperando você no horário agendado.\\n\\n*Lembre-se:*\\n⏰ Chegue 15 minutos antes\\n🆔 Traga um documento com foto\\n💳 Traga a carteirinha do convênio\\n\\n_Em caso de imprevisto, entre em contato:_\\n📞 {{clinic_phone}}",
    
    'cancelled' => "❌ *Consulta cancelada*\\n\\nSua consulta foi cancelada conforme solicitado.\\n\\nPara reagendar, entre em contato conosco:\\n📞 {{clinic_phone}}\\n🌐 {{clinic_website}}",
    
    'reschedule' => "🔄 *Reagendamento solicitado*\\n\\nRecebemos sua solicitação de reagendamento.\\nNossa equipe entrará em contato em até 2 horas úteis para agendar uma nova data.\\n\\n📞 Em caso de urgência: {{clinic_phone}}"
];

// CONFIGURAÇÕES DE LOG (PRODUÇÃO)
\$LOG_CONFIG = [
    'enable_logging' => true,
    'log_level' => 'INFO',
    'log_file' => '/var/www/html/oitava/agenda/logs/whatsapp.log',
    'max_log_size' => 50 * 1024 * 1024, // 50MB
    'log_rotation' => true
];

// CONFIGURAÇÕES DE SEGURANÇA (PRODUÇÃO)
\$SECURITY_CONFIG = [
    'webhook_secret' => 'CLINICA_OITAVA_WEBHOOK_SECRET_2025',
    'rate_limit_per_minute' => 30, // Limite conservador
    'blocked_numbers' => [],
    'allowed_ips' => [], // Vazio = todos permitidos
    'enable_ip_whitelist' => false
];

// Funções (iguais ao arquivo original)
function getWhatsAppConfig(\$key = null) {
    global \$WHATSAPP_CONFIG, \$TIMING_CONFIG, \$MESSAGE_TEMPLATES, \$LOG_CONFIG, \$SECURITY_CONFIG;
    
    \$config = [
        'whatsapp' => \$WHATSAPP_CONFIG,
        'timing' => \$TIMING_CONFIG,
        'templates' => \$MESSAGE_TEMPLATES,
        'log' => \$LOG_CONFIG,
        'security' => \$SECURITY_CONFIG
    ];
    
    return \$key ? (\$config[\$key] ?? null) : \$config;
}

function validateConfig() {
    \$config = getWhatsAppConfig('whatsapp');
    \$errors = [];
    
    if (empty(\$config['api_url'])) {
        \$errors[] = 'URL da API não configurada';
    }
    
    if (empty(\$config['instance_name'])) {
        \$errors[] = 'Nome da instância não configurado';
    }
    
    if (empty(\$config['api_key'])) {
        \$errors[] = 'Chave da API não configurada';
    }
    
    if (empty(\$config['webhook_url'])) {
        \$errors[] = 'URL do webhook não configurada';
    }
    
    return \$errors;
}

// Criar diretório de logs se não existir
\$logDir = dirname(\$LOG_CONFIG['log_file']);
if (!is_dir(\$logDir)) {
    mkdir(\$logDir, 0755, true);
}

// Criar arquivo .htaccess para proteger logs
\$htaccessPath = \$logDir . '/.htaccess';
if (!file_exists(\$htaccessPath)) {
    file_put_contents(\$htaccessPath, "Deny from all\\n");
}
?>
EOF

echo "✅ Configuração de produção criada"

# Substituir configuração atual
mv /var/www/html/oitava/agenda/whatsapp_config.php /var/www/html/oitava/agenda/whatsapp_config_teste.php
mv /var/www/html/oitava/agenda/whatsapp_config_producao.php /var/www/html/oitava/agenda/whatsapp_config.php

echo "✅ Configuração de produção ativada"

# Testar nova configuração
echo "🧪 Testando nova configuração..."
php -f /var/www/html/oitava/agenda/whatsapp_teste.php

echo ""
echo "🎉 CONFIGURAÇÃO DE PRODUÇÃO CONCLUÍDA!"
echo "====================================="
echo ""
echo "📋 ARQUIVOS CRIADOS:"
echo "- whatsapp_config.php (PRODUÇÃO - ATIVO)"
echo "- whatsapp_config_teste.php (versão de teste)"
echo "- whatsapp_config.php.backup (backup original)"
echo ""
echo "🔧 PRÓXIMOS PASSOS:"
echo "1. Configure SSL/HTTPS no seu domínio"
echo "2. Execute: sudo bash configurar_webhook_publico.sh"
echo "3. Teste com números reais"
echo ""
echo "⚠️ IMPORTANTE:"
echo "- Mantenha backup das configurações"
echo "- Monitore logs em /var/www/html/oitava/agenda/logs/"
echo "- Use whatsapp_relatorios.php para acompanhar estatísticas"