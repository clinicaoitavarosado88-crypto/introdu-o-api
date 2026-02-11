# 📱 Manual de Operação - Sistema WhatsApp Clínica Oitava

## 🎯 Visão Geral

Este sistema automatiza o envio de confirmações de consultas via WhatsApp, aumentando a taxa de confirmação e reduzindo faltas.

### ✨ Funcionalidades Principais

- **Envio Automático**: Mensagens 24h antes das consultas
- **Respostas Inteligentes**: Processa confirmações, cancelamentos e reagendamentos
- **Lembretes**: Notificações 2h antes das consultas confirmadas
- **Relatórios**: Dashboard com estatísticas e gráficos
- **Integração Total**: Funciona automaticamente com agendamentos existentes

---

## 🚀 Instalação e Configuração

### 1. Instalação da Evolution API

```bash
# Execute como root
sudo bash /var/www/html/oitava/agenda/install_evolution_api.sh
```

### 2. Configuração para Produção

```bash
# Configure o sistema para produção
sudo bash /var/www/html/oitava/agenda/configurar_producao.sh
```

### 3. Configuração do Webhook Público

```bash
# Configure webhook seguro
sudo bash /var/www/html/oitava/agenda/configurar_webhook_publico.sh
```

### 4. Monitoramento Avançado

```bash
# Configure monitoramento completo
sudo bash /var/www/html/oitava/agenda/configurar_monitoramento.sh
```

---

## 🔧 Configurações Principais

### Arquivo: `whatsapp_config.php`

```php
// Configurações da API
$WHATSAPP_CONFIG = [
    'api_provider' => 'evolution',
    'api_url' => 'http://localhost:8080',
    'instance_name' => 'CLINICA_OITAVA',
    'api_key' => 'CLINICA_OITAVA_2025_API_KEY',
    'webhook_url' => 'https://seudominio.com/oitava/agenda/whatsapp_webhook.php'
];

// Configurações de timing
$TIMING_CONFIG = [
    'hours_before_appointment' => 24,  // Horas antes para confirmação
    'reminder_hours' => 2,             // Horas antes para lembrete
    'max_attempts_per_appointment' => 3,
    'retry_interval_minutes' => 120
];
```

### Configurações da Clínica

Edite as informações da clínica em `whatsapp_config.php`:

```php
'clinic_info' => [
    'name' => 'CLÍNICA OITAVA',
    'phone' => '(84) 3421-8410',
    'address' => 'Mossoró - RN',
    'website' => 'https://seudominio.com'
]
```

---

## 🎮 Operação Diária

### Dashboard Principal

Acesse: `http://localhost/oitava/agenda/dashboard_whatsapp.php`

**Recursos:**
- Status do sistema em tempo real
- Estatísticas de confirmações
- Gráficos de performance
- Logs em tempo real
- Últimas confirmações

### Relatórios

Acesse: `http://localhost/oitava/agenda/whatsapp_relatorios.php`

**Informações disponíveis:**
- Taxa de confirmação por período
- Estatísticas por médico/especialidade
- Horários de maior resposta
- Análise de cancelamentos

---

## 📊 Monitoramento

### Logs Principais

| Arquivo | Descrição |
|---------|-----------|
| `/var/www/html/oitava/agenda/logs/whatsapp.log` | Log principal do sistema |
| `/var/www/html/oitava/agenda/logs/webhook_validation.log` | Log de validação do webhook |
| `/var/www/html/oitava/agenda/logs/cron_output.log` | Log dos CRON jobs |
| `/var/log/whatsapp_monitor.log` | Log do monitoramento |

### Comandos Úteis

```bash
# Ver logs em tempo real
tail -f /var/www/html/oitava/agenda/logs/whatsapp.log

# Verificar status do sistema
php /var/www/html/oitava/agenda/whatsapp_teste.php

# Monitor de webhook
php /var/www/html/oitava/agenda/monitor_webhook.php

# Executar monitoramento manual
sudo /usr/local/bin/monitor_whatsapp.sh
```

---

## 🔄 CRON Jobs

### Jobs Configurados

```bash
# Envio de confirmações (a cada hora)
0 * * * * php /var/www/html/oitava/agenda/whatsapp_cron_envios.php

# Lembretes (a cada 30 minutos)
*/30 * * * * php /var/www/html/oitava/agenda/whatsapp_lembretes.php

# Monitoramento (a cada 15 minutos)
*/15 * * * * /usr/local/bin/monitor_whatsapp.sh

# Relatório diário (23h)
0 23 * * * /usr/local/bin/whatsapp_daily_report.sh
```

### Verificar CRON Jobs

```bash
# Ver jobs ativos
crontab -l

# Ver logs do CRON
tail -f /var/www/html/oitava/agenda/logs/cron_output.log
```

---

## 🧪 Testes

### Teste Completo do Sistema

```bash
php /var/www/html/oitava/agenda/whatsapp_teste.php
```

### Teste com Números Reais

```bash
php /var/www/html/oitava/agenda/testar_numeros_reais.php
```

### Teste do Webhook Público

```bash
php /var/www/html/oitava/agenda/testar_webhook_publico.php
```

---

## 🚨 Solução de Problemas

### Problema: API não responde

**Sintomas:**
- Mensagens não são enviadas
- Status "API Offline" no dashboard

**Solução:**
```bash
# Verificar se Evolution API está rodando
docker ps | grep evolution-api

# Reiniciar se necessário
cd /opt/evolution-api
docker-compose restart

# Verificar logs
docker-compose logs evolution-api
```

### Problema: Webhook não funciona

**Sintomas:**
- Respostas dos pacientes não são processadas
- Status "Webhook com problemas"

**Solução:**
```bash
# Verificar configuração do webhook
php /var/www/html/oitava/agenda/testar_webhook_publico.php

# Verificar logs do webhook
tail -f /var/www/html/oitava/agenda/logs/webhook_validation.log

# Verificar configuração SSL/HTTPS
```

### Problema: Confirmações não são criadas

**Sintomas:**
- Novos agendamentos não geram confirmações
- Hooks não funcionam

**Solução:**
```bash
# Verificar integração
grep "whatsapp_hooks" /var/www/html/oitava/agenda/processar_agendamento.php

# Testar hook manualmente
php -r "
include 'whatsapp_hooks.php';
\$result = processarHookAgendamento('criar', ['id' => 999, 'numero' => 'TESTE']);
echo \$result;
"
```

### Problema: CRON Jobs não executam

**Sintomas:**
- Mensagens não são enviadas automaticamente
- Logs não são atualizados

**Solução:**
```bash
# Verificar se CRON está rodando
systemctl status cron

# Verificar configuração
crontab -l

# Testar execução manual
php /var/www/html/oitava/agenda/whatsapp_cron_envios.php
```

---

## 🔒 Segurança

### Configurações de Segurança

1. **Webhook com validação**
   - Apenas POST requests
   - Validação de JSON
   - Limite de tamanho de payload

2. **Logs protegidos**
   - `.htaccess` bloqueia acesso direto
   - Rotação automática de logs

3. **Rate limiting**
   - Máximo de mensagens por minuto
   - Delay entre envios

### Backup e Recuperação

```bash
# Backup das configurações
cp /var/www/html/oitava/agenda/whatsapp_config.php /backup/
cp -r /var/www/html/oitava/agenda/logs/ /backup/logs/

# Backup do banco de dados (confirmações)
# Use seu método de backup Firebird existente
```

---

## 📈 Otimização

### Performance

1. **Banco de dados**
   - Índices nas tabelas WhatsApp
   - Limpeza de registros antigos

2. **Logs**
   - Rotação automática configurada
   - Limpeza de logs antigos

3. **API**
   - Timeout configurado
   - Retry logic implementado

### Escalabilidade

- Sistema suporta múltiplas instâncias
- Load balancing para webhooks
- Backup automático de configurações

---

## 📞 Suporte

### Contatos de Emergência

- **Administrador do Sistema**: [Seu contato]
- **Suporte Técnico**: [Contato técnico]

### Procedimentos de Emergência

1. **Sistema totalmente fora**
   ```bash
   # Parar todos os serviços
   docker-compose -f /opt/evolution-api/docker-compose.yml down
   
   # Reiniciar tudo
   sudo bash /var/www/html/oitava/agenda/install_evolution_api.sh
   ```

2. **Mensagens em fila**
   ```bash
   # Executar envios manualmente
   php /var/www/html/oitava/agenda/whatsapp_cron_envios.php
   ```

3. **Logs crescendo muito**
   ```bash
   # Limpeza manual
   find /var/www/html/oitava/agenda/logs -name "*.log" -mtime +7 -delete
   ```

---

## 📋 Checklist de Manutenção

### Diário
- [ ] Verificar dashboard para alertas
- [ ] Conferir taxa de confirmação
- [ ] Verificar logs de erro

### Semanal
- [ ] Revisar relatórios de performance
- [ ] Verificar espaço em disco
- [ ] Testar webhook com número real

### Mensal
- [ ] Backup completo do sistema
- [ ] Revisão de configurações
- [ ] Limpeza de logs antigos
- [ ] Atualização da Evolution API

---

## 🔄 Atualizações

### Atualizar Evolution API

```bash
cd /opt/evolution-api
docker-compose pull
docker-compose up -d
```

### Atualizar Sistema WhatsApp

```bash
# Backup antes de atualizar
cp /var/www/html/oitava/agenda/whatsapp_config.php /tmp/

# Aplicar atualizações (conforme instruções)
# Restaurar configurações personalizadas
```

---

## 📊 Métricas de Sucesso

### KPIs Principais

- **Taxa de Confirmação**: Meta > 70%
- **Taxa de Resposta**: Meta > 60%
- **Tempo de Resposta**: < 2 horas
- **Disponibilidade do Sistema**: > 99%

### Relatórios Mensais

- Performance por médico
- Horários de maior engajamento
- Análise de cancelamentos
- ROI do sistema (redução de faltas)

---

*Este manual foi gerado automaticamente pelo sistema de implementação WhatsApp da Clínica Oitava. Mantenha sempre atualizado e accessible à equipe técnica.*