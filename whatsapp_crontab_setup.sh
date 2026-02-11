#!/bin/bash
# whatsapp_crontab_setup.sh - Setup automático do CRON para sistema WhatsApp
# Execute: chmod +x whatsapp_crontab_setup.sh && ./whatsapp_crontab_setup.sh

echo "🚀 Configurando CRON Jobs para Sistema WhatsApp - Clínica Oitava"
echo "=================================================================="

# Diretório base
BASE_DIR="/var/www/html/oitava/agenda"

# Verificar se diretório existe
if [ ! -d "$BASE_DIR" ]; then
    echo "❌ Erro: Diretório $BASE_DIR não encontrado"
    exit 1
fi

# Verificar se arquivos principais existem
FILES=(
    "whatsapp_cron_envios.php"
    "whatsapp_lembretes.php" 
    "whatsapp_notificacoes.php"
)

echo "🔍 Verificando arquivos necessários..."
for file in "${FILES[@]}"; do
    if [ ! -f "$BASE_DIR/$file" ]; then
        echo "❌ Erro: Arquivo $file não encontrado"
        exit 1
    else
        echo "✅ $file encontrado"
    fi
done

# Criar backup do crontab atual
echo "💾 Criando backup do crontab atual..."
crontab -l > /tmp/crontab_backup_$(date +%Y%m%d_%H%M%S).txt 2>/dev/null || echo "Nenhum crontab existente para fazer backup"

# Remover entradas antigas do sistema WhatsApp (se existirem)
echo "🧹 Removendo entradas antigas do sistema WhatsApp..."
crontab -l 2>/dev/null | grep -v "whatsapp_cron_envios.php" | grep -v "whatsapp_lembretes.php" | grep -v "whatsapp_notificacoes.php" | crontab -

# Criar novo crontab com as configurações do WhatsApp
echo "⚙️ Configurando novos CRON jobs..."

# Obter crontab atual
CURRENT_CRONTAB=$(crontab -l 2>/dev/null)

# Adicionar as novas entradas
NEW_CRONTAB="$CURRENT_CRONTAB

# ========================================
# Sistema WhatsApp - Clínica Oitava
# Adicionado automaticamente em $(date)
# ========================================

# Envio de confirmações 24h antes (a cada hora)
0 * * * * php $BASE_DIR/whatsapp_cron_envios.php >> $BASE_DIR/logs/cron_output.log 2>&1

# Lembretes 2h antes (a cada 30 minutos)
*/30 * * * * php $BASE_DIR/whatsapp_lembretes.php >> $BASE_DIR/logs/cron_output.log 2>&1

# Relatórios automáticos para equipe (a cada 2 horas das 8h às 18h)
0 8,10,12,14,16,18 * * * php $BASE_DIR/whatsapp_notificacoes.php >> $BASE_DIR/logs/cron_output.log 2>&1

# Limpeza de logs antigos (todo domingo às 2h)
0 2 * * 0 find $BASE_DIR/logs -name \"*.log\" -type f -mtime +30 -delete

# ========================================
"

# Aplicar novo crontab
echo "$NEW_CRONTAB" | crontab -

if [ $? -eq 0 ]; then
    echo "✅ CRON jobs configurados com sucesso!"
else
    echo "❌ Erro ao configurar CRON jobs"
    exit 1
fi

# Criar diretório de logs se não existir
echo "📁 Verificando diretório de logs..."
if [ ! -d "$BASE_DIR/logs" ]; then
    mkdir -p "$BASE_DIR/logs"
    chmod 755 "$BASE_DIR/logs"
    echo "✅ Diretório de logs criado"
else
    echo "✅ Diretório de logs já existe"
fi

# Definir permissões corretas
echo "🔒 Configurando permissões..."
chmod 644 "$BASE_DIR/whatsapp_cron_envios.php"
chmod 644 "$BASE_DIR/whatsapp_lembretes.php" 
chmod 644 "$BASE_DIR/whatsapp_notificacoes.php"
chmod 755 "$BASE_DIR/logs"

# Verificar se PHP está disponível
echo "🐘 Verificando PHP..."
if command -v php &> /dev/null; then
    PHP_VERSION=$(php -v | head -n 1)
    echo "✅ PHP encontrado: $PHP_VERSION"
else
    echo "❌ PHP não encontrado no PATH"
    echo "   Certifique-se de que o PHP está instalado e disponível"
fi

# Testar execução dos scripts
echo "🧪 Testando execução dos scripts..."

echo "  Testando whatsapp_cron_envios.php..."
php "$BASE_DIR/whatsapp_cron_envios.php" 2>&1 | head -5
if [ $? -eq 0 ]; then
    echo "  ✅ Script de envios executou sem erros"
else
    echo "  ⚠️ Script de envios pode ter problemas"
fi

echo "  Testando whatsapp_lembretes.php..."
php "$BASE_DIR/whatsapp_lembretes.php" 2>&1 | head -5
if [ $? -eq 0 ]; then
    echo "  ✅ Script de lembretes executou sem erros"
else
    echo "  ⚠️ Script de lembretes pode ter problemas"
fi

# Mostrar crontab final
echo ""
echo "📋 CRON jobs configurados:"
echo "=========================="
crontab -l | grep -A 15 "Sistema WhatsApp"

echo ""
echo "🎉 Configuração concluída!"
echo ""
echo "📊 Monitoramento:"
echo "- Logs em: $BASE_DIR/logs/"
echo "- Para ver logs em tempo real: tail -f $BASE_DIR/logs/cron_output.log"
echo "- Para verificar CRON: crontab -l"
echo "- Para editar CRON: crontab -e"
echo ""
echo "⚠️ Importante:"
echo "- Configure a API do WhatsApp em whatsapp_config.php"
echo "- Execute o teste: php whatsapp_teste.php"
echo "- Verifique as permissões da tabela WHATSAPP_CONFIRMACOES"
echo ""
echo "🚀 Sistema pronto para uso!"