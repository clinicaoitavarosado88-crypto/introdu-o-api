#!/bin/bash

# =============================================================================
# Script de Verificação: Integração do Sistema de Ressonância
# =============================================================================
# Data: 19/01/2026
# Objetivo: Verificar se todos os componentes estão instalados corretamente
# =============================================================================

echo "╔════════════════════════════════════════════════════════════════╗"
echo "║     VERIFICAÇÃO: Integração do Sistema de Ressonância         ║"
echo "╚════════════════════════════════════════════════════════════════╝"
echo ""

ERROS=0
AVISOS=0

# -----------------------------------------------------------------------------
# 1. Verificar se arquivo integracao_ressonancia.js existe
# -----------------------------------------------------------------------------
echo "📝 [1/6] Verificando arquivo integracao_ressonancia.js..."
if [ -f "/var/www/html/oitava/agenda/integracao_ressonancia.js" ]; then
    echo "   ✅ Arquivo encontrado"
else
    echo "   ❌ ERRO: Arquivo não encontrado!"
    ERROS=$((ERROS + 1))
fi
echo ""

# -----------------------------------------------------------------------------
# 2. Verificar se index.php inclui o script
# -----------------------------------------------------------------------------
echo "📝 [2/6] Verificando inclusão no index.php..."
if grep -q "integracao_ressonancia.js" /var/www/html/oitava/agenda/index.php; then
    echo "   ✅ Script incluído no index.php"
else
    echo "   ❌ ERRO: Script não incluído no index.php!"
    ERROS=$((ERROS + 1))
fi
echo ""

# -----------------------------------------------------------------------------
# 3. Verificar modificações em agenda-new.js
# -----------------------------------------------------------------------------
echo "📝 [3/6] Verificando modificações em agenda-new.js..."

# Verificar detecção de agenda
if grep -q "30, 76" /var/www/html/oitava/agenda/includes/agenda-new.js && \
   grep -q "adicionarCheckboxSedacao" /var/www/html/oitava/agenda/includes/agenda-new.js; then
    echo "   ✅ Detecção de agenda implementada"
else
    echo "   ⚠️  AVISO: Detecção pode não estar implementada corretamente"
    AVISOS=$((AVISOS + 1))
fi

# Verificar uso de API especializada
if grep -q "buscar_horarios_ressonancia.php" /var/www/html/oitava/agenda/includes/agenda-new.js; then
    echo "   ✅ API especializada configurada"
else
    echo "   ❌ ERRO: API especializada não configurada!"
    ERROS=$((ERROS + 1))
fi
echo ""

# -----------------------------------------------------------------------------
# 4. Verificar API buscar_horarios_ressonancia.php
# -----------------------------------------------------------------------------
echo "📝 [4/6] Verificando API especializada..."
if [ -f "/var/www/html/oitava/agenda/buscar_horarios_ressonancia.php" ]; then
    echo "   ✅ API encontrada"

    # Verificar se tem as validações principais
    if grep -q "TEM_MEDICO" /var/www/html/oitava/agenda/buscar_horarios_ressonancia.php && \
       grep -q "ACEITA_ANESTESIA" /var/www/html/oitava/agenda/buscar_horarios_ressonancia.php; then
        echo "   ✅ Validações implementadas"
    else
        echo "   ⚠️  AVISO: Validações podem estar incompletas"
        AVISOS=$((AVISOS + 1))
    fi
else
    echo "   ❌ ERRO: API não encontrada!"
    ERROS=$((ERROS + 1))
fi
echo ""

# -----------------------------------------------------------------------------
# 5. Verificar container de mensagens
# -----------------------------------------------------------------------------
echo "📝 [5/6] Verificando container de mensagens..."
if grep -q "container-mensagens" /var/www/html/oitava/agenda/carregar_agendamento.php; then
    echo "   ✅ Container de mensagens encontrado"
else
    echo "   ⚠️  AVISO: Container de mensagens não encontrado"
    AVISOS=$((AVISOS + 1))
fi
echo ""

# -----------------------------------------------------------------------------
# 6. Verificar configuração do banco de dados
# -----------------------------------------------------------------------------
echo "📝 [6/6] Verificando configuração do banco..."

# Testar query básica
TESTE_SQL=$(php -r "
include '/var/www/html/oitava/agenda/includes/connection.php';
\$sql = \"SELECT COUNT(*) as TOTAL FROM AGENDA_HORARIOS WHERE AGENDA_ID = 30 AND ACEITA_ANESTESIA = 'S'\";
\$res = ibase_query(\$conn, \$sql);
if (\$res) {
    \$row = ibase_fetch_assoc(\$res);
    echo \$row['TOTAL'];
} else {
    echo 'ERRO';
}
" 2>&1)

if [ "$TESTE_SQL" = "ERRO" ]; then
    echo "   ⚠️  AVISO: Não foi possível verificar configuração"
    AVISOS=$((AVISOS + 1))
elif [ "$TESTE_SQL" -gt "0" ]; then
    echo "   ✅ Quinta-feira configurada para anestesia ($TESTE_SQL dia)"
else
    echo "   ⚠️  AVISO: Nenhum dia configurado para anestesia"
    echo "      Execute: sql_ressonancia_campos.sql"
    AVISOS=$((AVISOS + 1))
fi
echo ""

# -----------------------------------------------------------------------------
# RESUMO
# -----------------------------------------------------------------------------
echo "╔════════════════════════════════════════════════════════════════╗"
echo "║                         RESUMO                                 ║"
echo "╚════════════════════════════════════════════════════════════════╝"
echo ""

if [ $ERROS -eq 0 ] && [ $AVISOS -eq 0 ]; then
    echo "🎉 ✅ TUDO CERTO! Integração completa e funcionando!"
    echo ""
    echo "Próximos passos:"
    echo "  1. Abra o sistema no navegador"
    echo "  2. Clique em uma agenda de ressonância (ID 30 ou 76)"
    echo "  3. Verifique se o checkbox de sedação aparece"
    echo "  4. Teste agendamento com e sem sedação"
    echo ""
    exit 0
elif [ $ERROS -eq 0 ]; then
    echo "⚠️  Integração completa com $AVISOS aviso(s)"
    echo ""
    echo "A integração está funcionando, mas alguns itens precisam de atenção."
    echo "Revise os avisos acima."
    echo ""
    exit 0
else
    echo "❌ Foram encontrados $ERROS erro(s) e $AVISOS aviso(s)"
    echo ""
    echo "Por favor, corrija os erros antes de prosseguir."
    echo "Consulte: INTEGRACAO_COMPLETA_RESSONANCIA.md"
    echo ""
    exit 1
fi
