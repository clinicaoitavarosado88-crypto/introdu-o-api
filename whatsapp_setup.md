# 🚀 Setup do Sistema de Confirmação WhatsApp

## 📋 Pré-requisitos

- [x] PHP 7.4+
- [x] Firebird Database
- [x] cURL habilitado
- [x] Acesso a crontab (para envios automáticos)
- [x] Uma das APIs do WhatsApp configurada

## 🔧 Instalação Passo a Passo

### 1. Criar a Tabela no Banco de Dados

```sql
-- Execute o arquivo sql_whatsapp_confirmacoes.sql no seu banco Firebird
-- Ou copie e execute o SQL diretamente
```

### 2. Configurar a API do WhatsApp

Edite o arquivo `whatsapp_config.php` e escolha uma das opções:

#### Opção A: Evolution API (RECOMENDADA - GRATUITA)

1. **Acesse**: https://github.com/EvolutionAPI/evolution-api
2. **Instale** seguindo a documentação
3. **Configure** no `whatsapp_config.php`:

```php
$WHATSAPP_CONFIG = [
    'api_provider' => 'evolution',
    'api_url' => 'https://sua-instancia.com',
    'instance_name' => 'CLINICA_OITAVA',
    'api_key' => 'sua_api_key',
    'webhook_url' => 'https://seudominio.com/oitava/agenda/whatsapp_webhook.php'
];
```

#### Opção B: Z-API (PAGA)

1. **Cadastre-se**: https://www.z-api.io
2. **Configure** no `whatsapp_config.php`

#### Opção C: WhatsApp Business API Oficial (META)

1. **Configure** via Facebook Business
2. **Ajuste** no `whatsapp_config.php`

### 3. Configurar Webhook

No painel da sua API WhatsApp, configure o webhook para:
```
https://seudominio.com/oitava/agenda/whatsapp_webhook.php
```

### 4. Configurar CRON Job

Adicione ao crontab para execução automática:

```bash
# Editar crontab
sudo crontab -e

# Adicionar linha para executar a cada hora (verificar agendamentos 24h antes)
0 * * * * php /var/www/html/oitava/agenda/whatsapp_cron_envios.php

# Ou a cada 30 minutos para mais precisão
*/30 * * * * php /var/www/html/oitava/agenda/whatsapp_cron_envios.php
```

### 5. Configurar Permissões

Verifique se o usuário tem permissão para acessar o painel:
```php
// O sistema já verifica automaticamente a permissão ID 98 (Administrar agenda)
```

### 6. Teste o Sistema

1. **Acesse**: `whatsapp_painel.php`
2. **Clique**: "Verificar Status"
3. **Teste**: "Disparar Agora" (para teste manual)

## 🛠 Configurações Importantes

### Dados da Clínica

Edite em `whatsapp_config.php`:

```php
'clinic_info' => [
    'name' => 'CLÍNICA OITAVA',
    'phone' => '(XX) XXXX-XXXX',
    'address' => 'Endereço da clínica'
]
```

### Horário de Funcionamento

```php
'working_hours' => [
    'start' => '08:00',  // Não enviar antes das 8h
    'end' => '18:00'     // Não enviar depois das 18h
]
```

### Templates de Mensagem

Personalize as mensagens em `whatsapp_config.php` na seção `MESSAGE_TEMPLATES`.

## 🔒 Segurança

### 1. Proteger Logs

Os logs são automaticamente protegidos com `.htaccess`.

### 2. Webhook Security

Configure em `whatsapp_config.php`:

```php
'security' => [
    'webhook_secret' => 'SUA_CHAVE_SECRETA',
    'allowed_ips' => ['IP_DA_API_WHATSAPP']
]
```

### 3. Rate Limiting

O sistema possui controle de taxa de envio automático.

## 📊 Monitoramento

### Logs Disponíveis

- `logs/whatsapp.log` - Log geral do sistema
- `logs/webhook.log` - Log dos webhooks recebidos
- `logs/cron_whatsapp.log` - Log das execuções automáticas

### Painel de Controle

Acesse `whatsapp_painel.php` para:

- ✅ Verificar status do sistema
- 📊 Ver estatísticas de confirmação
- 📱 Enviar confirmações manuais
- 🔄 Reenviar mensagens
- 📋 Filtrar confirmações por status

## 🚨 Troubleshooting

### Problema: Mensagens não são enviadas

1. **Verifique** se a API está conectada
2. **Confira** as configurações em `whatsapp_config.php`
3. **Veja** os logs em `logs/cron_whatsapp.log`

### Problema: Webhooks não funcionam

1. **Teste** se a URL está acessível
2. **Verifique** se o SSL está funcionando
3. **Confira** os logs em `logs/webhook.log`

### Problema: CRON não executa

1. **Verifique** se o crontab está configurado
2. **Teste** executar manualmente: `php whatsapp_cron_envios.php`
3. **Confira** permissões do arquivo

## 📈 Próximas Funcionalidades

- [ ] Templates personalizáveis por especialidade
- [ ] Integração com calendário Google
- [ ] Relatórios avançados
- [ ] API para integração externa
- [ ] App mobile para gestão
- [ ] Inteligência artificial para respostas

## 🆘 Suporte

Para problemas ou dúvidas:

1. **Verifique** os logs primeiro
2. **Teste** as configurações
3. **Consulte** a documentação da API escolhida

---

## ✅ Checklist Final

- [ ] Tabela criada no banco
- [ ] API WhatsApp configurada
- [ ] Webhook testado
- [ ] CRON configurado
- [ ] Permissões verificadas
- [ ] Dados da clínica atualizados
- [ ] Sistema testado com agendamento real

**🎉 Sistema pronto para uso!**