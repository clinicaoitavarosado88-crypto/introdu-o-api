<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel WhatsApp - Confirmações</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-bold text-gray-800">
                    <i class="bi bi-whatsapp text-green-500 mr-2"></i>
                    Painel WhatsApp - Confirmações
                </h1>
                <div class="flex space-x-2">
                    <button onclick="verificarStatus()" class="px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded transition">
                        <i class="bi bi-arrow-clockwise mr-1"></i>
                        Verificar Status
                    </button>
                    <button onclick="dispararConfirmacoes()" class="px-4 py-2 bg-green-500 hover:bg-green-600 text-white rounded transition">
                        <i class="bi bi-send mr-1"></i>
                        Disparar Agora
                    </button>
                </div>
            </div>

            <!-- Status do Sistema -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <div class="bg-gray-50 p-4 rounded-lg">
                    <div class="flex items-center">
                        <div class="w-3 h-3 rounded-full mr-2" id="server-status-dot"></div>
                        <span class="text-sm font-medium">Servidor Node.js</span>
                    </div>
                    <div class="text-xs text-gray-600 mt-1" id="server-status-text">Verificando...</div>
                </div>
                <div class="bg-gray-50 p-4 rounded-lg">
                    <div class="flex items-center">
                        <div class="w-3 h-3 rounded-full mr-2" id="whatsapp-status-dot"></div>
                        <span class="text-sm font-medium">WhatsApp</span>
                    </div>
                    <div class="text-xs text-gray-600 mt-1" id="whatsapp-status-text">Verificando...</div>
                </div>
                <div class="bg-gray-50 p-4 rounded-lg">
                    <div class="flex items-center">
                        <div class="w-3 h-3 bg-blue-500 rounded-full mr-2"></div>
                        <span class="text-sm font-medium">Última Verificação</span>
                    </div>
                    <div class="text-xs text-gray-600 mt-1" id="last-check">--</div>
                </div>
            </div>

            <!-- Estatísticas -->
            <div class="grid grid-cols-2 md:grid-cols-6 gap-4 mb-6" id="stats-container">
                <div class="bg-blue-50 p-4 rounded-lg text-center">
                    <div class="text-2xl font-bold text-blue-600" id="stat-total">0</div>
                    <div class="text-xs text-blue-600">Total</div>
                </div>
                <div class="bg-yellow-50 p-4 rounded-lg text-center">
                    <div class="text-2xl font-bold text-yellow-600" id="stat-enviados">0</div>
                    <div class="text-xs text-yellow-600">Enviados</div>
                </div>
                <div class="bg-green-50 p-4 rounded-lg text-center">
                    <div class="text-2xl font-bold text-green-600" id="stat-confirmados">0</div>
                    <div class="text-xs text-green-600">Confirmados</div>
                </div>
                <div class="bg-red-50 p-4 rounded-lg text-center">
                    <div class="text-2xl font-bold text-red-600" id="stat-cancelados">0</div>
                    <div class="text-xs text-red-600">Cancelados</div>
                </div>
                <div class="bg-purple-50 p-4 rounded-lg text-center">
                    <div class="text-2xl font-bold text-purple-600" id="stat-reagendar">0</div>
                    <div class="text-xs text-purple-600">Reagendar</div>
                </div>
                <div class="bg-gray-50 p-4 rounded-lg text-center">
                    <div class="text-2xl font-bold text-gray-600" id="stat-taxa">0%</div>
                    <div class="text-xs text-gray-600">Taxa Resposta</div>
                </div>
            </div>

            <!-- Filtros -->
            <div class="flex flex-wrap items-center gap-4 mb-6 p-4 bg-gray-50 rounded-lg">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Data Início</label>
                    <input type="date" id="data-inicio" class="border border-gray-300 rounded px-3 py-1 text-sm" 
                           value="<?= date('Y-m-d') ?>">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Data Fim</label>
                    <input type="date" id="data-fim" class="border border-gray-300 rounded px-3 py-1 text-sm" 
                           value="<?= date('Y-m-d', strtotime('+7 days')) ?>">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Status</label>
                    <select id="filtro-status" class="border border-gray-300 rounded px-3 py-1 text-sm">
                        <option value="">Todos</option>
                        <option value="enviado">Enviados</option>
                        <option value="confirmado">Confirmados</option>
                        <option value="cancelado">Cancelados</option>
                        <option value="reagendar">Reagendar</option>
                        <option value="erro">Erros</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <button onclick="carregarConfirmacoes()" class="px-4 py-1.5 bg-blue-500 hover:bg-blue-600 text-white rounded text-sm transition">
                        <i class="bi bi-search mr-1"></i>
                        Filtrar
                    </button>
                </div>
            </div>

            <!-- Tabela de Confirmações -->
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white border border-gray-300 rounded-lg">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Paciente</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Telefone</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Consulta</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Médico</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Enviado</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Resposta</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="confirmacoes-tbody" class="divide-y divide-gray-200">
                        <tr>
                            <td colspan="8" class="px-4 py-8 text-center text-gray-500">
                                <i class="bi bi-hourglass-split text-2xl mb-2"></i>
                                <div>Carregando confirmações...</div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal de Envio Manual -->
    <div id="modal-envio" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg p-6 w-full max-w-md">
                <h3 class="text-lg font-semibold mb-4">Enviar Confirmação Manual</h3>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">ID do Agendamento</label>
                    <input type="number" id="agendamento-id" class="w-full border border-gray-300 rounded px-3 py-2" 
                           placeholder="Digite o ID do agendamento">
                </div>
                <div class="flex justify-end space-x-2">
                    <button onclick="fecharModalEnvio()" class="px-4 py-2 border border-gray-300 rounded text-gray-700 hover:bg-gray-50 transition">
                        Cancelar
                    </button>
                    <button onclick="enviarConfirmacaoManual()" class="px-4 py-2 bg-green-500 hover:bg-green-600 text-white rounded transition">
                        Enviar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Verificar status do sistema
        async function verificarStatus() {
            try {
                const response = await fetch('whatsapp_api.php?action=status');
                const data = await response.json();
                
                // Atualizar indicadores visuais
                const serverDot = document.getElementById('server-status-dot');
                const serverText = document.getElementById('server-status-text');
                const whatsappDot = document.getElementById('whatsapp-status-dot');
                const whatsappText = document.getElementById('whatsapp-status-text');
                const lastCheck = document.getElementById('last-check');
                
                if (data.server_online) {
                    serverDot.className = 'w-3 h-3 bg-green-500 rounded-full mr-2';
                    serverText.textContent = 'Online';
                } else {
                    serverDot.className = 'w-3 h-3 bg-red-500 rounded-full mr-2';
                    serverText.textContent = 'Offline';
                }
                
                if (data.whatsapp_connected) {
                    whatsappDot.className = 'w-3 h-3 bg-green-500 rounded-full mr-2';
                    whatsappText.textContent = 'Conectado';
                } else {
                    whatsappDot.className = 'w-3 h-3 bg-red-500 rounded-full mr-2';
                    whatsappText.textContent = 'Desconectado';
                }
                
                lastCheck.textContent = new Date().toLocaleTimeString('pt-BR');
                
            } catch (error) {
                console.error('Erro ao verificar status:', error);
                mostrarNotificacao('Erro ao verificar status do sistema', 'error');
            }
        }

        // Carregar estatísticas
        async function carregarEstatisticas() {
            try {
                const dataInicio = document.getElementById('data-inicio').value;
                const dataFim = document.getElementById('data-fim').value;
                
                const response = await fetch(`whatsapp_api.php?action=get_stats&data_inicio=${dataInicio}&data_fim=${dataFim}`);
                const stats = await response.json();
                
                document.getElementById('stat-total').textContent = stats.total || 0;
                document.getElementById('stat-enviados').textContent = stats.enviados || 0;
                document.getElementById('stat-confirmados').textContent = stats.confirmados || 0;
                document.getElementById('stat-cancelados').textContent = stats.cancelados || 0;
                document.getElementById('stat-reagendar').textContent = stats.reagendar || 0;
                document.getElementById('stat-taxa').textContent = (stats.taxa_resposta || 0) + '%';
                
            } catch (error) {
                console.error('Erro ao carregar estatísticas:', error);
            }
        }

        // Carregar confirmações
        async function carregarConfirmacoes() {
            try {
                const dataInicio = document.getElementById('data-inicio').value;
                const dataFim = document.getElementById('data-fim').value;
                const status = document.getElementById('filtro-status').value;
                
                let url = `whatsapp_api.php?action=get_confirmations&data_inicio=${dataInicio}&data_fim=${dataFim}`;
                if (status) url += `&status=${status}`;
                
                const response = await fetch(url);
                const confirmacoes = await response.json();
                
                const tbody = document.getElementById('confirmacoes-tbody');
                
                if (confirmacoes.length === 0) {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="8" class="px-4 py-8 text-center text-gray-500">
                                <i class="bi bi-inbox text-2xl mb-2"></i>
                                <div>Nenhuma confirmação encontrada</div>
                            </td>
                        </tr>
                    `;
                    return;
                }
                
                tbody.innerHTML = confirmacoes.map(conf => `
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm">${conf.nome_paciente}</td>
                        <td class="px-4 py-3 text-sm">${conf.telefone}</td>
                        <td class="px-4 py-3 text-sm">
                            ${formatarData(conf.data_consulta)}<br>
                            <span class="text-gray-500">${conf.horario_consulta}</span>
                        </td>
                        <td class="px-4 py-3 text-sm">${conf.medico_nome || '-'}</td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-1 text-xs rounded-full ${getStatusClass(conf.status)}">
                                ${getStatusText(conf.status)}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-500">
                            ${formatarDataHora(conf.data_envio)}
                        </td>
                        <td class="px-4 py-3 text-xs">
                            ${conf.resposta_paciente || '-'}
                        </td>
                        <td class="px-4 py-3">
                            <button onclick="reenviarConfirmacao(${conf.agendamento_id})" 
                                    class="text-blue-600 hover:text-blue-800 text-xs">
                                <i class="bi bi-arrow-clockwise"></i>
                            </button>
                        </td>
                    </tr>
                `).join('');
                
            } catch (error) {
                console.error('Erro ao carregar confirmações:', error);
                mostrarNotificacao('Erro ao carregar confirmações', 'error');
            }
        }

        // Disparar confirmações manualmente
        async function dispararConfirmacoes() {
            if (!confirm('Tem certeza que deseja disparar as confirmações agora?')) return;
            
            try {
                mostrarNotificacao('Disparando confirmações...', 'info');
                
                const response = await fetch('whatsapp_api.php?action=trigger_manual', {
                    method: 'POST'
                });
                const result = await response.json();
                
                if (result.success) {
                    mostrarNotificacao('Confirmações disparadas com sucesso!', 'success');
                    setTimeout(() => {
                        carregarConfirmacoes();
                        carregarEstatisticas();
                    }, 2000);
                } else {
                    mostrarNotificacao('Erro ao disparar confirmações', 'error');
                }
            } catch (error) {
                console.error('Erro:', error);
                mostrarNotificacao('Erro ao disparar confirmações', 'error');
            }
        }

        // Reenviar confirmação
        async function reenviarConfirmacao(agendamentoId) {
            if (!confirm('Reenviar confirmação para este agendamento?')) return;
            
            try {
                const response = await fetch('whatsapp_api.php?action=send_confirmation', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `agendamento_id=${agendamentoId}`
                });
                const result = await response.json();
                
                if (result.success) {
                    mostrarNotificacao('Confirmação reenviada!', 'success');
                    carregarConfirmacoes();
                } else {
                    mostrarNotificacao(result.error || 'Erro ao reenviar', 'error');
                }
            } catch (error) {
                console.error('Erro:', error);
                mostrarNotificacao('Erro ao reenviar confirmação', 'error');
            }
        }

        // Utilitários
        function getStatusClass(status) {
            const classes = {
                'enviado': 'bg-yellow-100 text-yellow-800',
                'confirmado': 'bg-green-100 text-green-800',
                'cancelado': 'bg-red-100 text-red-800',
                'reagendar': 'bg-purple-100 text-purple-800',
                'erro': 'bg-gray-100 text-gray-800'
            };
            return classes[status] || 'bg-gray-100 text-gray-800';
        }

        function getStatusText(status) {
            const texts = {
                'enviado': 'Enviado',
                'confirmado': 'Confirmado',
                'cancelado': 'Cancelado',
                'reagendar': 'Reagendar',
                'erro': 'Erro'
            };
            return texts[status] || status;
        }

        function formatarData(data) {
            if (!data) return '-';
            return new Date(data + 'T00:00:00').toLocaleDateString('pt-BR');
        }

        function formatarDataHora(dataHora) {
            if (!dataHora) return '-';
            return new Date(dataHora).toLocaleString('pt-BR');
        }

        function mostrarNotificacao(mensagem, tipo) {
            // Criar notificação toast simples
            const notif = document.createElement('div');
            notif.className = `fixed top-4 right-4 p-4 rounded-lg text-white z-50 ${
                tipo === 'success' ? 'bg-green-500' : 
                tipo === 'error' ? 'bg-red-500' : 'bg-blue-500'
            }`;
            notif.textContent = mensagem;
            document.body.appendChild(notif);
            
            setTimeout(() => {
                notif.remove();
            }, 3000);
        }

        // Inicialização
        document.addEventListener('DOMContentLoaded', function() {
            verificarStatus();
            carregarEstatisticas();
            carregarConfirmacoes();
            
            // Auto-refresh a cada 30 segundos
            setInterval(verificarStatus, 30000);
        });
    </script>
</body>
</html>