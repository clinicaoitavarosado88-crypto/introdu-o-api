<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guia Rápido - WhatsApp Clínica Oitava</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, sans-serif; 
            background: linear-gradient(135deg, #25D366 0%, #128C7E 100%);
            min-height: 100vh;
            color: #333;
        }
        .container { 
            max-width: 800px; 
            margin: 0 auto; 
            padding: 2rem; 
            background: white;
            margin-top: 2rem;
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
        }
        .header { 
            text-align: center; 
            margin-bottom: 2rem; 
            padding-bottom: 1rem;
            border-bottom: 2px solid #25D366;
        }
        .header h1 { color: #25D366; margin-bottom: 0.5rem; }
        .section { 
            margin-bottom: 2rem; 
            padding: 1.5rem;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #25D366;
        }
        .section h2 { 
            color: #128C7E; 
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
        }
        .section h2::before {
            content: attr(data-icon);
            margin-right: 0.5rem;
            font-size: 1.2em;
        }
        .step { 
            background: white; 
            padding: 1rem; 
            margin: 0.5rem 0; 
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        .step-number {
            display: inline-block;
            background: #25D366;
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            text-align: center;
            line-height: 24px;
            font-weight: bold;
            margin-right: 0.5rem;
            font-size: 0.9rem;
        }
        .code {
            background: #1a1a1a;
            color: #00ff00;
            padding: 0.5rem;
            border-radius: 4px;
            font-family: monospace;
            margin: 0.5rem 0;
            overflow-x: auto;
        }
        .alert {
            padding: 1rem;
            border-radius: 4px;
            margin: 1rem 0;
            border: 1px solid;
        }
        .alert-success { background: #d4edda; color: #155724; border-color: #c3e6cb; }
        .alert-warning { background: #fff3cd; color: #856404; border-color: #ffeaa7; }
        .alert-info { background: #d1ecf1; color: #0c5460; border-color: #bee5eb; }
        .links {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        .link-card {
            background: white;
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
            text-decoration: none;
            color: #333;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
        }
        .link-card:hover {
            border-color: #25D366;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(37,211,102,0.2);
        }
        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
        }
        .status-online { background: #25D366; }
        .status-offline { background: #ff4444; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📱 Guia Rápido - Sistema WhatsApp</h1>
            <p>Clínica Oitava - Confirmações Automáticas</p>
        </div>

        <!-- Status do Sistema -->
        <div class="section">
            <h2 data-icon="🔍">Status do Sistema</h2>
            <div id="system-status">
                <div class="alert alert-info">Verificando status...</div>
            </div>
        </div>

        <!-- Instalação Rápida -->
        <div class="section">
            <h2 data-icon="🚀">Instalação Rápida</h2>
            
            <div class="step">
                <span class="step-number">1</span>
                <strong>Instalar Evolution API</strong>
                <div class="code">sudo bash /var/www/html/oitava/agenda/install_evolution_api.sh</div>
            </div>
            
            <div class="step">
                <span class="step-number">2</span>
                <strong>Configurar Produção</strong>
                <div class="code">sudo bash /var/www/html/oitava/agenda/configurar_producao.sh</div>
            </div>
            
            <div class="step">
                <span class="step-number">3</span>
                <strong>Configurar Webhook</strong>
                <div class="code">sudo bash /var/www/html/oitava/agenda/configurar_webhook_publico.sh</div>
            </div>
            
            <div class="step">
                <span class="step-number">4</span>
                <strong>Configurar Monitoramento</strong>
                <div class="code">sudo bash /var/www/html/oitava/agenda/configurar_monitoramento.sh</div>
            </div>
            
            <div class="alert alert-success">
                <strong>✅ Pronto!</strong> Sistema configurado e funcionando.
            </div>
        </div>

        <!-- Testes Essenciais -->
        <div class="section">
            <h2 data-icon="🧪">Testes Essenciais</h2>
            
            <div class="step">
                <span class="step-number">1</span>
                <strong>Teste Completo</strong>
                <div class="code">php /var/www/html/oitava/agenda/whatsapp_teste.php</div>
            </div>
            
            <div class="step">
                <span class="step-number">2</span>
                <strong>Teste com Número Real</strong>
                <div class="code">php /var/www/html/oitava/agenda/testar_numeros_reais.php</div>
            </div>
            
            <div class="step">
                <span class="step-number">3</span>
                <strong>Teste do Webhook</strong>
                <div class="code">php /var/www/html/oitava/agenda/testar_webhook_publico.php</div>
            </div>
        </div>

        <!-- Links Rápidos -->
        <div class="section">
            <h2 data-icon="🔗">Links Rápidos</h2>
            <div class="links">
                <a href="dashboard_whatsapp.php" class="link-card">
                    <div>📊</div>
                    <strong>Dashboard</strong>
                    <div>Monitoramento em tempo real</div>
                </a>
                
                <a href="whatsapp_relatorios.php" class="link-card">
                    <div>📈</div>
                    <strong>Relatórios</strong>
                    <div>Estatísticas detalhadas</div>
                </a>
                
                <a href="monitor_webhook.php" class="link-card">
                    <div>🔍</div>
                    <strong>Monitor Webhook</strong>
                    <div>Status do webhook</div>
                </a>
                
                <a href="http://localhost:8080/manager" class="link-card" target="_blank">
                    <div>⚙️</div>
                    <strong>Evolution API</strong>
                    <div>Gerenciar API</div>
                </a>
            </div>
        </div>

        <!-- Comandos Úteis -->
        <div class="section">
            <h2 data-icon="⚡">Comandos Úteis</h2>
            
            <div class="step">
                <strong>Ver logs em tempo real</strong>
                <div class="code">tail -f /var/www/html/oitava/agenda/logs/whatsapp.log</div>
            </div>
            
            <div class="step">
                <strong>Verificar CRON jobs</strong>
                <div class="code">crontab -l</div>
            </div>
            
            <div class="step">
                <strong>Status da Evolution API</strong>
                <div class="code">docker ps | grep evolution-api</div>
            </div>
            
            <div class="step">
                <strong>Reiniciar Evolution API</strong>
                <div class="code">cd /opt/evolution-api && docker-compose restart</div>
            </div>
        </div>

        <!-- Solução de Problemas -->
        <div class="section">
            <h2 data-icon="🚨">Solução Rápida de Problemas</h2>
            
            <div class="alert alert-warning">
                <strong>API Offline?</strong><br>
                Execute: <code>cd /opt/evolution-api && docker-compose restart</code>
            </div>
            
            <div class="alert alert-warning">
                <strong>Webhook não funciona?</strong><br>
                Verifique: <code>php testar_webhook_publico.php</code>
            </div>
            
            <div class="alert alert-warning">
                <strong>Mensagens não enviadas?</strong><br>
                Execute: <code>php whatsapp_cron_envios.php</code>
            </div>
        </div>

        <!-- Configurações Importantes -->
        <div class="section">
            <h2 data-icon="⚙️">Configurações Importantes</h2>
            
            <div class="step">
                <strong>Arquivo de Configuração</strong>
                <div>📁 <code>/var/www/html/oitava/agenda/whatsapp_config.php</code></div>
                <div>Configure informações da clínica, API e templates de mensagens</div>
            </div>
            
            <div class="step">
                <strong>Logs do Sistema</strong>
                <div>📁 <code>/var/www/html/oitava/agenda/logs/</code></div>
                <div>Monitore atividades e erros do sistema</div>
            </div>
            
            <div class="step">
                <strong>Manual Completo</strong>
                <div>📖 <code>/var/www/html/oitava/agenda/MANUAL_WHATSAPP.md</code></div>
                <div>Documentação detalhada do sistema</div>
            </div>
        </div>

        <div class="alert alert-info">
            <strong>💡 Dica:</strong> Mantenha sempre o dashboard aberto para monitorar o sistema em tempo real.
        </div>
    </div>

    <script>
        // Verificar status do sistema
        async function verificarStatus() {
            try {
                const response = await fetch('dashboard_api.php');
                const data = await response.json();
                
                const statusContainer = document.getElementById('system-status');
                let html = '';
                
                // API Status
                const apiStatus = data.system_status.api_online ? 'online' : 'offline';
                const apiText = data.system_status.api_online ? 'Online' : 'Offline';
                html += `<div><span class="status-indicator status-${apiStatus}"></span><strong>Evolution API:</strong> ${apiText}</div>`;
                
                // Webhook Status
                const webhookStatus = data.system_status.webhook_working ? 'online' : 'offline';
                const webhookText = data.system_status.webhook_working ? 'Funcionando' : 'Verificar';
                html += `<div><span class="status-indicator status-${webhookStatus}"></span><strong>Webhook:</strong> ${webhookText}</div>`;
                
                // CRON Status
                const cronStatus = data.system_status.cron_active ? 'online' : 'offline';
                const cronText = data.system_status.cron_active ? 'Ativos' : 'Verificar';
                html += `<div><span class="status-indicator status-${cronStatus}"></span><strong>CRON Jobs:</strong> ${cronText}</div>`;
                
                // Estatísticas
                html += `<div style="margin-top: 1rem; padding: 1rem; background: white; border-radius: 4px;">`;
                html += `<strong>📊 Hoje:</strong> `;
                html += `${data.stats.enviadas_hoje || 0} enviadas, `;
                html += `${data.stats.confirmadas_hoje || 0} confirmadas, `;
                html += `Taxa: ${data.stats.taxa_resposta || 0}%`;
                html += `</div>`;
                
                statusContainer.innerHTML = html;
                
            } catch (error) {
                document.getElementById('system-status').innerHTML = 
                    '<div class="alert alert-warning">Erro ao verificar status</div>';
            }
        }

        // Verificar status ao carregar a página
        verificarStatus();
        
        // Atualizar a cada 30 segundos
        setInterval(verificarStatus, 30000);
    </script>
</body>
</html>