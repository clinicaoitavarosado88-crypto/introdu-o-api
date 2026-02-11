#!/bin/bash
# evolution_api_setup.sh - Setup da Evolution API local para WhatsApp

echo "🚀 CONFIGURANDO EVOLUTION API - CLÍNICA OITAVA"
echo "=============================================="

# Verificar se Docker está instalado
if ! command -v docker &> /dev/null; then
    echo "❌ Docker não encontrado. Instalando Docker..."
    
    # Atualizar sistema
    apt-get update
    
    # Instalar dependências
    apt-get install -y apt-transport-https ca-certificates curl gnupg lsb-release
    
    # Adicionar chave GPG do Docker
    curl -fsSL https://download.docker.com/linux/ubuntu/gpg | gpg --dearmor -o /usr/share/keyrings/docker-archive-keyring.gpg
    
    # Adicionar repositório
    echo "deb [arch=$(dpkg --print-architecture) signed-by=/usr/share/keyrings/docker-archive-keyring.gpg] https://download.docker.com/linux/ubuntu $(lsb_release -cs) stable" | tee /etc/apt/sources.list.d/docker.list > /dev/null
    
    # Instalar Docker
    apt-get update
    apt-get install -y docker-ce docker-ce-cli containerd.io docker-compose-plugin
    
    # Iniciar Docker
    systemctl start docker
    systemctl enable docker
    
    echo "✅ Docker instalado com sucesso!"
else
    echo "✅ Docker já está instalado"
fi

# Verificar se Docker Compose está disponível
if ! command -v docker-compose &> /dev/null && ! docker compose version &> /dev/null; then
    echo "📦 Instalando Docker Compose..."
    curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
    chmod +x /usr/local/bin/docker-compose
    echo "✅ Docker Compose instalado!"
else
    echo "✅ Docker Compose já está disponível"
fi

# Criar diretório para Evolution API
EVOLUTION_DIR="/opt/evolution-api"
mkdir -p $EVOLUTION_DIR
cd $EVOLUTION_DIR

echo "📁 Criando configuração da Evolution API..."

# Criar docker-compose.yml
cat > docker-compose.yml << 'EOF'
version: '3.8'

services:
  evolution-api:
    container_name: evolution_api_clinica
    image: atendai/evolution-api:latest
    restart: unless-stopped
    ports:
      - "8080:8080"
    environment:
      # Configurações básicas
      - SERVER_URL=http://localhost:8080
      - WEBHOOK_GLOBAL_URL=http://localhost/oitava/agenda/whatsapp_webhook.php
      - WEBHOOK_GLOBAL_ENABLED=true
      - WEBHOOK_GLOBAL_WEBHOOK_BY_EVENTS=true
      
      # Configurações de segurança
      - AUTHENTICATION_API_KEY=CLINICA_OITAVA_2025_API_KEY
      - AUTHENTICATION_EXPOSE_IN_FETCH_INSTANCES=true
      
      # Configurações do banco (SQLite para simplicidade)
      - DATABASE_ENABLED=true
      - DATABASE_CONNECTION_URI=file:./evolution.db
      
      # Configurações de logs
      - LOG_LEVEL=info
      - LOG_COLOR=true
      
      # Configurações do WhatsApp
      - QRCODE_LIMIT=10
      - INSTANCE_EXPIRY_TIME=false
      
    volumes:
      - evolution_data:/evolution/instances
      - evolution_db:/evolution/db
    
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost:8080/health"]
      interval: 30s
      timeout: 10s
      retries: 3

volumes:
  evolution_data:
  evolution_db:
EOF

echo "🐳 Iniciando Evolution API..."
docker-compose up -d

echo "⏳ Aguardando API inicializar (30 segundos)..."
sleep 30

# Verificar se API está rodando
if curl -f http://localhost:8080/health &> /dev/null; then
    echo "✅ Evolution API está rodando!"
    echo ""
    echo "🔗 INFORMAÇÕES DE ACESSO:"
    echo "========================"
    echo "URL da API: http://localhost:8080"
    echo "API Key: CLINICA_OITAVA_2025_API_KEY"
    echo "Documentação: http://localhost:8080/manager"
    echo ""
    echo "📱 PRÓXIMOS PASSOS:"
    echo "1. Criar instância do WhatsApp"
    echo "2. Escanear QR Code"
    echo "3. Configurar webhook"
else
    echo "❌ Erro ao iniciar Evolution API"
    docker-compose logs
fi

echo ""
echo "🚀 Configuração da Evolution API concluída!"