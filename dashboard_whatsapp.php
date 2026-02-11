<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard WhatsApp - Clínica Oitava</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, sans-serif; background: #f5f5f5; }
        .header { background: #25D366; color: white; padding: 1rem; text-align: center; }
        .container { max-width: 1200px; margin: 0 auto; padding: 2rem; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
        .stat-card { background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .stat-value { font-size: 2rem; font-weight: bold; color: #25D366; }
        .stat-label { color: #666; margin-top: 0.5rem; }
        .chart-container { background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 2rem; }
        .status-indicator { display: inline-block; width: 12px; height: 12px; border-radius: 50%; margin-right: 8px; }
        .status-online { background: #25D366; }
        .status-offline { background: #ff4444; }
        .status-warning { background: #ffaa00; }
        .refresh-btn { background: #25D366; color: white; border: none; padding: 0.5rem 1rem; border-radius: 4px; cursor: pointer; }
        .log-viewer { background: #1a1a1a; color: #00ff00; padding: 1rem; border-radius: 4px; font-family: monospace; height: 300px; overflow-y: auto; font-size: 0.9rem; }
        .alert { padding: 1rem; margin: 1rem 0; border-radius: 4px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-warning { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; }
        @media (max-width: 768px) { .grid-2 { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="header">
        <h1>📱 Dashboard WhatsApp - Clínica Oitava</h1>
        <p>Monitoramento em tempo real do sistema de confirmações</p>
    </div>

    <div class="container">
        <!-- Status do Sistema -->
        <div class="chart-container">
            <h2>🔍 Status do Sistema</h2>
            <div id="system-status">
                <div class="alert alert-warning">
                    <strong>Carregando...</strong> Verificando status do sistema...
                </div>
            </div>
        </div>

        <!-- Estatísticas Principais -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value" id="total-confirmacoes">-</div>
                <div class="stat-label">Total de Confirmações</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="enviadas-hoje">-</div>
                <div class="stat-label">Enviadas Hoje</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="confirmadas-hoje">-</div>
                <div class="stat-label">Confirmadas Hoje</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="taxa-resposta">-</div>
                <div class="stat-label">Taxa de Resposta</div>
            </div>
        </div>

        <div class="grid-2">
            <!-- Gráfico de Status -->
            <div class="chart-container">
                <h3>📊 Status das Confirmações</h3>
                <canvas id="status-chart" width="400" height="200"></canvas>
            </div>

            <!-- Gráfico de Envios por Dia -->
            <div class="chart-container">
                <h3>📈 Envios dos Últimos 7 Dias</h3>
                <canvas id="envios-chart" width="400" height="200"></canvas>
            </div>
        </div>

        <!-- Logs em Tempo Real -->
        <div class="chart-container">
            <h3>📋 Logs em Tempo Real <button class="refresh-btn" onclick="carregarLogs()">🔄 Atualizar</button></h3>
            <div class="log-viewer" id="log-viewer">
                Carregando logs...
            </div>
        </div>

        <!-- Últimas Confirmações -->
        <div class="chart-container">
            <h3>📱 Últimas Confirmações</h3>
            <div id="ultimas-confirmacoes">
                <div class="alert alert-warning">Carregando confirmações...</div>
            </div>
        </div>
    </div>

    <script>
        // Função para carregar dados do sistema
        async function carregarDados() {
            try {
                const response = await fetch('dashboard_api.php');
                const data = await response.json();
                
                // Atualizar estatísticas
                document.getElementById('total-confirmacoes').textContent = data.stats.total_confirmacoes || '0';
                document.getElementById('enviadas-hoje').textContent = data.stats.enviadas_hoje || '0';
                document.getElementById('confirmadas-hoje').textContent = data.stats.confirmadas_hoje || '0';
                document.getElementById('taxa-resposta').textContent = (data.stats.taxa_resposta || '0') + '%';
                
                // Atualizar status do sistema
                atualizarStatusSistema(data.system_status);
                
                // Atualizar gráficos
                atualizarGraficoStatus(data.chart_status);
                atualizarGraficoEnvios(data.chart_envios);
                
                // Atualizar últimas confirmações
                atualizarUltimasConfirmacoes(data.ultimas_confirmacoes);
                
            } catch (error) {
                console.error('Erro ao carregar dados:', error);
            }
        }

        // Função para atualizar status do sistema
        function atualizarStatusSistema(status) {
            const container = document.getElementById('system-status');
            let html = '';
            
            // API Status
            const apiStatus = status.api_online ? 'online' : 'offline';
            const apiClass = status.api_online ? 'alert-success' : 'alert-danger';
            html += `<div class="alert ${apiClass}">
                <span class="status-indicator status-${apiStatus}"></span>
                <strong>Evolution API:</strong> ${status.api_online ? 'Online' : 'Offline'}
            </div>`;
            
            // Webhook Status
            const webhookStatus = status.webhook_working ? 'online' : 'warning';
            const webhookClass = status.webhook_working ? 'alert-success' : 'alert-warning';
            html += `<div class="alert ${webhookClass}">
                <span class="status-indicator status-${webhookStatus}"></span>
                <strong>Webhook:</strong> ${status.webhook_working ? 'Funcionando' : 'Verificar configuração'}
            </div>`;
            
            // CRON Status
            const cronStatus = status.cron_active ? 'online' : 'warning';
            const cronClass = status.cron_active ? 'alert-success' : 'alert-warning';
            html += `<div class="alert ${cronClass}">
                <span class="status-indicator status-${cronStatus}"></span>
                <strong>CRON Jobs:</strong> ${status.cron_active ? 'Ativos' : 'Verificar configuração'}
            </div>`;
            
            container.innerHTML = html;
        }

        // Gráfico de Status
        let statusChart;
        function atualizarGraficoStatus(data) {
            const ctx = document.getElementById('status-chart').getContext('2d');
            
            if (statusChart) {
                statusChart.destroy();
            }
            
            statusChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Enviadas', 'Confirmadas', 'Canceladas', 'Pendentes'],
                    datasets: [{
                        data: [
                            data.enviadas || 0,
                            data.confirmadas || 0,
                            data.canceladas || 0,
                            data.pendentes || 0
                        ],
                        backgroundColor: ['#25D366', '#4CAF50', '#f44336', '#ff9800']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
        }

        // Gráfico de Envios
        let enviosChart;
        function atualizarGraficoEnvios(data) {
            const ctx = document.getElementById('envios-chart').getContext('2d');
            
            if (enviosChart) {
                enviosChart.destroy();
            }
            
            enviosChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.labels || [],
                    datasets: [{
                        label: 'Mensagens Enviadas',
                        data: data.values || [],
                        borderColor: '#25D366',
                        backgroundColor: 'rgba(37, 211, 102, 0.1)',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }

        // Função para carregar logs
        async function carregarLogs() {
            try {
                const response = await fetch('dashboard_api.php?action=logs');
                const data = await response.json();
                
                const logViewer = document.getElementById('log-viewer');
                logViewer.innerHTML = data.logs.join('\n');
                logViewer.scrollTop = logViewer.scrollHeight;
                
            } catch (error) {
                console.error('Erro ao carregar logs:', error);
            }
        }

        // Função para atualizar últimas confirmações
        function atualizarUltimasConfirmacoes(confirmacoes) {
            const container = document.getElementById('ultimas-confirmacoes');
            
            if (!confirmacoes || confirmacoes.length === 0) {
                container.innerHTML = '<div class="alert alert-warning">Nenhuma confirmação encontrada</div>';
                return;
            }
            
            let html = '<table style="width: 100%; border-collapse: collapse;">';
            html += '<tr style="background: #f8f9fa; font-weight: bold;"><td style="padding: 8px; border: 1px solid #ddd;">Paciente</td><td style="padding: 8px; border: 1px solid #ddd;">Telefone</td><td style="padding: 8px; border: 1px solid #ddd;">Data/Hora</td><td style="padding: 8px; border: 1px solid #ddd;">Status</td></tr>';
            
            confirmacoes.forEach(conf => {
                const statusColor = {
                    'confirmado': '#4CAF50',
                    'cancelado': '#f44336',
                    'enviado': '#ff9800',
                    'pendente': '#9E9E9E'
                }[conf.STATUS] || '#9E9E9E';
                
                html += `<tr>
                    <td style="padding: 8px; border: 1px solid #ddd;">${conf.PACIENTE_NOME}</td>
                    <td style="padding: 8px; border: 1px solid #ddd;">${conf.PACIENTE_TELEFONE}</td>
                    <td style="padding: 8px; border: 1px solid #ddd;">${conf.DATA_CONSULTA} ${conf.HORA_CONSULTA}</td>
                    <td style="padding: 8px; border: 1px solid #ddd; color: ${statusColor}; font-weight: bold;">${conf.STATUS.toUpperCase()}</td>
                </tr>`;
            });
            
            html += '</table>';
            container.innerHTML = html;
        }

        // Carregar dados iniciais
        carregarDados();

        // Atualizar a cada 30 segundos
        setInterval(carregarDados, 30000);

        // Atualizar logs a cada 10 segundos
        setInterval(carregarLogs, 10000);

        // Carregar logs iniciais
        carregarLogs();
    </script>
</body>
</html>