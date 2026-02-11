#!/bin/bash
# install_evolution_api.sh - Script para instalar Evolution API real
# Execute como root: sudo bash install_evolution_api.sh

echo "🚀 INSTALANDO EVOLUTION API PARA PRODUÇÃO"
echo "========================================"

# Verificar se o Docker está instalado
if ! command -v docker &> /dev/null; then
    echo "📦 Instalando Docker..."
    curl -fsSL https://get.docker.com -o get-docker.sh
    sudo sh get-docker.sh
    sudo usermod -aG docker $USER
    echo "✅ Docker instalado"
fi

# Verificar se o Docker Compose está instalado
if ! command -v docker-compose &> /dev/null; then
    echo "📦 Instalando Docker Compose..."
    sudo curl -L "https://github.com/docker/compose/releases/download/v2.20.2/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
    sudo chmod +x /usr/local/bin/docker-compose
    echo "✅ Docker Compose instalado"
fi

# Criar diretório para Evolution API
mkdir -p /opt/evolution-api
cd /opt/evolution-api

# Criar arquivo docker-compose.yml
cat > docker-compose.yml << 'EOF'
version: '3.8'

services:
  evolution-api:
    image: atendai/evolution-api:v2.0.0
    container_name: evolution-api
    restart: always
    ports:
      - "8080:8080"
    environment:
      # Configurações básicas
      - SERVER_TYPE=http
      - SERVER_PORT=8080
      - SERVER_URL=http://localhost:8080
      
      # Configurações da API
      - CORS_ORIGIN=*
      - CORS_METHODS=GET,POST,PUT,DELETE
      - CORS_CREDENTIALS=true
      
      # Configurações de webhook
      - WEBHOOK_GLOBAL_URL=http://localhost/oitava/agenda/whatsapp_webhook.php
      - WEBHOOK_GLOBAL_ENABLED=true
      - WEBHOOK_GLOBAL_WEBHOOK_BY_EVENTS=true
      
      # Configurações de autenticação
      - AUTHENTICATION_TYPE=apikey
      - AUTHENTICATION_API_KEY=CLINICA_OITAVA_2025_API_KEY
      - AUTHENTICATION_EXPOSE_IN_FETCH_INSTANCES=true
      
      # Configurações de logs
      - LOG_LEVEL=INFO
      - LOG_COLOR=true
      - LOG_BAILEYS=error
      
      # Configurações de QR Code
      - QRCODE_LIMIT=10
      - QRCODE_COLOR=#198754
      
      # Configurações de instância
      - INSTANCE_EXPIRATION_TIME=false
      - DEL_INSTANCE=false
      
      # Configurações de database (opcional - Redis)
      - DATABASE_ENABLED=false
      
      # Configurações de mensagens
      - MESSAGE_FULL_DOMAIN_WEBHOOK=true
      - MESSAGE_UPSERT_FULL_DOMAIN_WEBHOOK=true
      
    volumes:
      - evolution_instances:/evolution/instances
      - evolution_store:/evolution/store
    
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost:8080/health"]
      interval: 30s
      timeout: 10s
      retries: 3
      start_period: 40s

volumes:
  evolution_instances:
  evolution_store:
EOF

echo "✅ Arquivo docker-compose.yml criado"

# Iniciar Evolution API
echo "🚀 Iniciando Evolution API..."
docker-compose up -d

# Aguardar a API ficar online
echo "⏳ Aguardando API ficar online..."
sleep 30

# Verificar se está funcionando
if curl -f http://localhost:8080/health > /dev/null 2>&1; then
    echo "✅ Evolution API está funcionando!"
    echo "🌐 URL: http://localhost:8080"
    echo "🔑 API Key: CLINICA_OITAVA_2025_API_KEY"
else
    echo "❌ Evolution API não está respondendo"
    echo "📋 Verifique os logs com: docker-compose logs evolution-api"
fi

# Exibir informações finais
echo ""
echo "📋 INFORMAÇÕES DE ACESSO:"
echo "========================="
echo "URL da API: http://localhost:8080"
echo "API Key: CLINICA_OITAVA_2025_API_KEY"
echo "Health Check: http://localhost:8080/health"
echo "Swagger UI: http://localhost:8080/manager"
echo ""
echo "📱 PRÓXIMOS PASSOS:"
echo "==================="
echo "1. Acesse http://localhost:8080/manager para ver a documentação"
echo "2. Execute: sudo bash /var/www/html/oitava/agenda/configurar_producao.sh"
echo "3. Configure seu domínio público para webhooks"
echo ""
echo "🔧 COMANDOS ÚTEIS:"
echo "=================="
echo "Ver logs: docker-compose logs -f evolution-api"
echo "Parar: docker-compose down"
echo "Reiniciar: docker-compose restart"
echo "Atualizar: docker-compose pull && docker-compose up -d"