<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatórios WhatsApp - Clínica Oitava</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/date-fns@2.29.3/index.min.js"></script>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-3xl font-bold text-gray-800">
                    <i class="bi bi-graph-up text-blue-500 mr-2"></i>
                    Relatórios WhatsApp
                </h1>
                <div class="flex space-x-2">
                    <button onclick="window.location.href='whatsapp_painel.php'" class="px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded transition">
                        <i class="bi bi-arrow-left mr-1"></i>
                        Voltar ao Painel
                    </button>
                    <button onclick="exportarRelatorio()" class="px-4 py-2 bg-green-500 hover:bg-green-600 text-white rounded transition">
                        <i class="bi bi-download mr-1"></i>
                        Exportar
                    </button>
                </div>
            </div>

            <!-- Filtros -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6 p-4 bg-gray-50 rounded-lg">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Período</label>
                    <select id="periodo" class="w-full border border-gray-300 rounded px-3 py-2 text-sm" onchange="aplicarPeriodo()">
                        <option value="hoje">Hoje</option>
                        <option value="ontem">Ontem</option>
                        <option value="7dias">Últimos 7 dias</option>
                        <option value="30dias" selected>Últimos 30 dias</option>
                        <option value="personalizado">Personalizado</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Data Início</label>
                    <input type="date" id="data-inicio" class="w-full border border-gray-300 rounded px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Data Fim</label>
                    <input type="date" id="data-fim" class="w-full border border-gray-300 rounded px-3 py-2 text-sm">
                </div>
                <div class="flex items-end">
                    <button onclick="carregarRelatorios()" class="w-full px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded text-sm transition">
                        <i class="bi bi-search mr-1"></i>
                        Atualizar
                    </button>
                </div>
            </div>
        </div>

        <!-- Cards de Resumo -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
            <div class="bg-white p-6 rounded-lg shadow-md">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                        <i class="bi bi-envelope text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-semibold text-gray-800" id="total-enviados">0</h3>
                        <p class="text-gray-600 text-sm">Total Enviados</p>
                    </div>
                </div>
            </div>

            <div class="bg-white p-6 rounded-lg shadow-md">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 text-green-600">
                        <i class="bi bi-check-circle text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-semibold text-gray-800" id="total-confirmados">0</h3>
                        <p class="text-gray-600 text-sm">Confirmados</p>
                        <p class="text-xs text-green-600" id="percent-confirmados">0%</p>
                    </div>
                </div>
            </div>

            <div class="bg-white p-6 rounded-lg shadow-md">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-red-100 text-red-600">
                        <i class="bi bi-x-circle text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-semibold text-gray-800" id="total-cancelados">0</h3>
                        <p class="text-gray-600 text-sm">Cancelados</p>
                        <p class="text-xs text-red-600" id="percent-cancelados">0%</p>
                    </div>
                </div>
            </div>

            <div class="bg-white p-6 rounded-lg shadow-md">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                        <i class="bi bi-hourglass-split text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-semibold text-gray-800" id="taxa-resposta">0%</h3>
                        <p class="text-gray-600 text-sm">Taxa de Resposta</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Gráficos -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- Gráfico de Pizza - Status -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Distribuição por Status</h3>
                <div class="relative h-64">
                    <canvas id="grafico-status"></canvas>
                </div>
            </div>

            <!-- Gráfico de Linha - Tendência -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Tendência de Confirmações</h3>
                <div class="relative h-64">
                    <canvas id="grafico-tendencia"></canvas>
                </div>
            </div>
        </div>

        <!-- Gráfico de Barras - Por Médico/Especialidade -->
        <div class="bg-white p-6 rounded-lg shadow-md mb-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Taxa de Confirmação por Especialidade</h3>
            <div class="relative h-80">
                <canvas id="grafico-especialidades"></canvas>
            </div>
        </div>

        <!-- Tabela Detalhada -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold text-gray-800">Detalhamento por Dia</h3>
                <div class="text-sm text-gray-600">
                    <span id="total-registros">0</span> registros encontrados
                </div>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white border border-gray-300 rounded-lg">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Data</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Enviados</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Confirmados</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cancelados</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reagendar</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Taxa Resposta</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Taxa Confirmação</th>
                        </tr>
                    </thead>
                    <tbody id="tabela-detalhes" class="divide-y divide-gray-200">
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                                <i class="bi bi-hourglass-split text-2xl mb-2"></i>
                                <div>Carregando dados...</div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // Variáveis globais para os gráficos
        let graficoStatus, graficoTendencia, graficoEspecialidades;

        // Aplicar período selecionado
        function aplicarPeriodo() {
            const periodo = document.getElementById('periodo').value;
            const hoje = new Date();
            let dataInicio, dataFim;

            switch(periodo) {
                case 'hoje':
                    dataInicio = dataFim = hoje.toISOString().split('T')[0];
                    break;
                case 'ontem':
                    const ontem = new Date(hoje);
                    ontem.setDate(hoje.getDate() - 1);
                    dataInicio = dataFim = ontem.toISOString().split('T')[0];
                    break;
                case '7dias':
                    const semanaAtras = new Date(hoje);
                    semanaAtras.setDate(hoje.getDate() - 7);
                    dataInicio = semanaAtras.toISOString().split('T')[0];
                    dataFim = hoje.toISOString().split('T')[0];
                    break;
                case '30dias':
                    const mesAtras = new Date(hoje);
                    mesAtras.setDate(hoje.getDate() - 30);
                    dataInicio = mesAtras.toISOString().split('T')[0];
                    dataFim = hoje.toISOString().split('T')[0];
                    break;
                case 'personalizado':
                    return; // Não altera as datas
            }

            document.getElementById('data-inicio').value = dataInicio;
            document.getElementById('data-fim').value = dataFim;
            
            if (periodo !== 'personalizado') {
                carregarRelatorios();
            }
        }

        // Carregar dados dos relatórios
        async function carregarRelatorios() {
            try {
                const dataInicio = document.getElementById('data-inicio').value;
                const dataFim = document.getElementById('data-fim').value;

                // Carregar estatísticas gerais
                const responseStats = await fetch(`whatsapp_api.php?action=get_stats&data_inicio=${dataInicio}&data_fim=${dataFim}`);
                const stats = await responseStats.json();
                
                atualizarCards(stats);

                // Carregar dados detalhados
                const responseDetalhes = await fetch(`whatsapp_relatorios_api.php?action=detalhes&data_inicio=${dataInicio}&data_fim=${dataFim}`);
                const detalhes = await responseDetalhes.json();
                
                atualizarTabela(detalhes);
                atualizarGraficos(detalhes);

            } catch (error) {
                console.error('Erro ao carregar relatórios:', error);
                mostrarNotificacao('Erro ao carregar dados dos relatórios', 'error');
            }
        }

        // Atualizar cards de resumo
        function atualizarCards(stats) {
            document.getElementById('total-enviados').textContent = stats.enviados || 0;
            document.getElementById('total-confirmados').textContent = stats.confirmados || 0;
            document.getElementById('total-cancelados').textContent = stats.cancelados || 0;
            document.getElementById('taxa-resposta').textContent = (stats.taxa_resposta || 0) + '%';

            // Calcular percentuais
            const total = stats.enviados || 1;
            const percentConfirmados = Math.round((stats.confirmados / total) * 100);
            const percentCancelados = Math.round((stats.cancelados / total) * 100);

            document.getElementById('percent-confirmados').textContent = percentConfirmados + '%';
            document.getElementById('percent-cancelados').textContent = percentCancelados + '%';
        }

        // Atualizar tabela de detalhes
        function atualizarTabela(dados) {
            const tbody = document.getElementById('tabela-detalhes');
            document.getElementById('total-registros').textContent = dados.length;

            if (dados.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                            <i class="bi bi-inbox text-2xl mb-2"></i>
                            <div>Nenhum dado encontrado para o período selecionado</div>
                        </td>
                    </tr>
                `;
                return;
            }

            tbody.innerHTML = dados.map(item => {
                const total = item.enviados || 1;
                const taxaResposta = Math.round(((item.confirmados + item.cancelados + item.reagendar) / total) * 100);
                const taxaConfirmacao = Math.round((item.confirmados / total) * 100);

                return `
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm">${formatarData(item.data)}</td>
                        <td class="px-4 py-3 text-sm font-medium">${item.enviados}</td>
                        <td class="px-4 py-3 text-sm text-green-600">${item.confirmados}</td>
                        <td class="px-4 py-3 text-sm text-red-600">${item.cancelados}</td>
                        <td class="px-4 py-3 text-sm text-yellow-600">${item.reagendar}</td>
                        <td class="px-4 py-3 text-sm">${taxaResposta}%</td>
                        <td class="px-4 py-3 text-sm font-medium">${taxaConfirmacao}%</td>
                    </tr>
                `;
            }).join('');
        }

        // Atualizar gráficos
        function atualizarGraficos(dados) {
            atualizarGraficoStatus(dados);
            atualizarGraficoTendencia(dados);
            carregarGraficoEspecialidades();
        }

        // Gráfico de pizza - status
        function atualizarGraficoStatus(dados) {
            const ctx = document.getElementById('grafico-status').getContext('2d');
            
            if (graficoStatus) {
                graficoStatus.destroy();
            }

            const totais = dados.reduce((acc, item) => {
                acc.confirmados += item.confirmados;
                acc.cancelados += item.cancelados;
                acc.reagendar += item.reagendar;
                acc.pendentes += item.enviados - item.confirmados - item.cancelados - item.reagendar;
                return acc;
            }, { confirmados: 0, cancelados: 0, reagendar: 0, pendentes: 0 });

            graficoStatus = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Confirmados', 'Cancelados', 'Reagendar', 'Pendentes'],
                    datasets: [{
                        data: [totais.confirmados, totais.cancelados, totais.reagendar, totais.pendentes],
                        backgroundColor: ['#10B981', '#EF4444', '#F59E0B', '#6B7280']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }

        // Gráfico de linha - tendência
        function atualizarGraficoTendencia(dados) {
            const ctx = document.getElementById('grafico-tendencia').getContext('2d');
            
            if (graficoTendencia) {
                graficoTendencia.destroy();
            }

            const labels = dados.map(item => formatarData(item.data));
            const confirmados = dados.map(item => item.confirmados);
            const cancelados = dados.map(item => item.cancelados);

            graficoTendencia = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Confirmados',
                            data: confirmados,
                            borderColor: '#10B981',
                            backgroundColor: 'rgba(16, 185, 129, 0.1)',
                            tension: 0.4
                        },
                        {
                            label: 'Cancelados',
                            data: cancelados,
                            borderColor: '#EF4444',
                            backgroundColor: 'rgba(239, 68, 68, 0.1)',
                            tension: 0.4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
        }

        // Carregar gráfico por especialidades
        async function carregarGraficoEspecialidades() {
            try {
                const dataInicio = document.getElementById('data-inicio').value;
                const dataFim = document.getElementById('data-fim').value;

                const response = await fetch(`whatsapp_relatorios_api.php?action=especialidades&data_inicio=${dataInicio}&data_fim=${dataFim}`);
                const dados = await response.json();

                const ctx = document.getElementById('grafico-especialidades').getContext('2d');
                
                if (graficoEspecialidades) {
                    graficoEspecialidades.destroy();
                }

                const labels = dados.map(item => item.especialidade || 'Outros');
                const confirmados = dados.map(item => item.confirmados);
                const enviados = dados.map(item => item.enviados);

                graficoEspecialidades = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [
                            {
                                label: 'Enviados',
                                data: enviados,
                                backgroundColor: 'rgba(59, 130, 246, 0.5)',
                                borderColor: '#3B82F6',
                                borderWidth: 1
                            },
                            {
                                label: 'Confirmados',
                                data: confirmados,
                                backgroundColor: 'rgba(16, 185, 129, 0.5)',
                                borderColor: '#10B981',
                                borderWidth: 1
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'top'
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1
                                }
                            }
                        }
                    }
                });

            } catch (error) {
                console.error('Erro ao carregar gráfico de especialidades:', error);
            }
        }

        // Exportar relatório
        function exportarRelatorio() {
            const dataInicio = document.getElementById('data-inicio').value;
            const dataFim = document.getElementById('data-fim').value;
            
            window.open(`whatsapp_relatorios_api.php?action=exportar&data_inicio=${dataInicio}&data_fim=${dataFim}&format=csv`, '_blank');
        }

        // Utilitários
        function formatarData(data) {
            return new Date(data + 'T00:00:00').toLocaleDateString('pt-BR');
        }

        function mostrarNotificacao(mensagem, tipo) {
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
            aplicarPeriodo(); // Aplicar período padrão de 30 dias
        });
    </script>
</body>
</html>