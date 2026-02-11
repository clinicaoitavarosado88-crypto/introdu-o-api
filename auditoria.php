<?php
// auditoria.php - Interface web para visualizar auditoria
include 'includes/connection.php';

// Simular login se necessário (ajuste conforme seu sistema)
if (!isset($_COOKIE["log_usuario"])) {
    setcookie("log_usuario", "RENISON", time() + 3600, "/");
    $_COOKIE["log_usuario"] = "RENISON";
}

$usuario_logado = $_COOKIE["log_usuario"];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auditoria da Agenda</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 0; 
            padding: 20px; 
            background-color: #f5f5f5; 
        }
        .container { 
            max-width: 1400px; 
            margin: 0 auto; 
            background: white; 
            padding: 20px; 
            border-radius: 8px; 
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            color: white; 
            padding: 20px; 
            border-radius: 8px; 
            margin-bottom: 20px; 
        }
        .filters { 
            background: #f8f9fa; 
            padding: 15px; 
            border-radius: 5px; 
            margin-bottom: 20px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
        }
        .filters input, .filters select, .filters button { 
            padding: 8px; 
            border: 1px solid #ddd; 
            border-radius: 4px; 
        }
        .filters button { 
            background: #007bff; 
            color: white; 
            cursor: pointer; 
            border: none;
        }
        .filters button:hover { 
            background: #0056b3; 
        }
        .stats { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); 
            gap: 15px; 
            margin-bottom: 20px; 
        }
        .stat-card { 
            background: #fff; 
            border: 1px solid #ddd; 
            border-radius: 5px; 
            padding: 15px; 
            text-align: center;
        }
        .stat-card h3 { 
            margin: 0 0 10px 0; 
            color: #333; 
        }
        .stat-card .number { 
            font-size: 24px; 
            font-weight: bold; 
            color: #007bff; 
        }
        .audit-table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 20px; 
        }
        .audit-table th, .audit-table td { 
            border: 1px solid #ddd; 
            padding: 8px; 
            text-align: left; 
            font-size: 12px;
        }
        .audit-table th { 
            background-color: #f8f9fa; 
            font-weight: bold; 
        }
        .audit-table tr:nth-child(even) { 
            background-color: #f9f9f9; 
        }
        .acao-badge { 
            display: inline-block; 
            padding: 2px 8px; 
            border-radius: 12px; 
            font-size: 10px; 
            font-weight: bold; 
            text-transform: uppercase; 
        }
        .acao-CRIAR { background: #d4edda; color: #155724; }
        .acao-EDITAR { background: #fff3cd; color: #856404; }
        .acao-CANCELAR { background: #f8d7da; color: #721c24; }
        .acao-BLOQUEAR { background: #d6d8db; color: #383d41; }
        .acao-DESBLOQUEAR { background: #d1ecf1; color: #0c5460; }
        .loading { 
            text-align: center; 
            padding: 20px; 
            color: #666; 
        }
        .error { 
            background: #f8d7da; 
            color: #721c24; 
            padding: 10px; 
            border-radius: 4px; 
            margin: 10px 0; 
        }
        .expandable { 
            cursor: pointer; 
            color: #007bff; 
        }
        .expandable:hover { 
            text-decoration: underline; 
        }
        .expanded-data { 
            background: #f8f9fa; 
            padding: 10px; 
            border-radius: 4px; 
            margin-top: 5px; 
            font-family: monospace; 
            font-size: 11px; 
            white-space: pre-wrap;
            max-height: 200px;
            overflow-y: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📋 Sistema de Auditoria da Agenda</h1>
            <p>Acompanhe todas as ações realizadas no sistema de agenda</p>
            <p><strong>Usuário logado:</strong> <?php echo htmlspecialchars($usuario_logado); ?></p>
        </div>

        <div class="filters">
            <input type="text" id="filtro-usuario" placeholder="Usuário">
            <select id="filtro-acao">
                <option value="">Todas as ações</option>
                <option value="CRIAR">Criar</option>
                <option value="EDITAR">Editar</option>
                <option value="CANCELAR">Cancelar</option>
                <option value="BLOQUEAR">Bloquear</option>
                <option value="DESBLOQUEAR">Desbloquear</option>
            </select>
            <input type="text" id="filtro-agenda" placeholder="ID da Agenda">
            <input type="text" id="filtro-paciente" placeholder="Nome do Paciente">
            <input type="date" id="filtro-data-inicio" placeholder="Data Início">
            <input type="date" id="filtro-data-fim" placeholder="Data Fim">
            <input type="number" id="filtro-limit" placeholder="Limite" value="50" min="1" max="500">
            <button onclick="buscarAuditoria()">🔍 Buscar</button>
            <button onclick="exportarCSV()">📊 Exportar CSV</button>
        </div>

        <div class="stats" id="estatisticas">
            <!-- Estatísticas serão carregadas aqui -->
        </div>

        <div id="resultado">
            <div class="loading">
                🔄 Carregando dados de auditoria...
            </div>
        </div>
    </div>

    <script>
        let dadosAtuais = [];

        // Carregar dados iniciais
        document.addEventListener('DOMContentLoaded', function() {
            // Definir data padrão como hoje
            const hoje = new Date().toISOString().split('T')[0];
            document.getElementById('filtro-data-inicio').value = hoje;
            document.getElementById('filtro-data-fim').value = hoje;
            
            // Buscar dados iniciais
            buscarAuditoria();
        });

        function buscarAuditoria() {
            const params = new URLSearchParams({
                usuario: document.getElementById('filtro-usuario').value,
                acao: document.getElementById('filtro-acao').value,
                agenda_id: document.getElementById('filtro-agenda').value,
                paciente_nome: document.getElementById('filtro-paciente').value,
                data_inicio: document.getElementById('filtro-data-inicio').value,
                data_fim: document.getElementById('filtro-data-fim').value,
                limit: document.getElementById('filtro-limit').value
            });

            // Remover parâmetros vazios
            for (let [key, value] of [...params]) {
                if (!value) params.delete(key);
            }

            console.log('🔍 Buscando auditoria com filtros:', Object.fromEntries(params));

            document.getElementById('resultado').innerHTML = '<div class="loading">🔄 Buscando...</div>';

            fetch(`consultar_auditoria.php?${params}`)
                .then(response => response.json())
                .then(data => {
                    console.log('📊 Dados recebidos:', data);
                    
                    if (data.status === 'erro') {
                        throw new Error(data.mensagem);
                    }
                    
                    dadosAtuais = data.historico;
                    mostrarEstatisticas(data.estatisticas);
                    mostrarHistorico(data.historico);
                })
                .catch(error => {
                    console.error('💥 Erro ao buscar auditoria:', error);
                    document.getElementById('resultado').innerHTML = 
                        `<div class="error">❌ Erro: ${error.message}</div>`;
                });
        }

        function mostrarEstatisticas(stats) {
            const container = document.getElementById('estatisticas');
            
            if (!stats || !stats.total_registros) {
                container.innerHTML = '<div class="stat-card"><h3>Nenhum dado encontrado</h3></div>';
                return;
            }

            let html = `
                <div class="stat-card">
                    <h3>Total de Registros</h3>
                    <div class="number">${stats.total_registros}</div>
                </div>
            `;

            if (stats.acoes_mais_frequentes) {
                const acaoTop = Object.entries(stats.acoes_mais_frequentes)[0];
                html += `
                    <div class="stat-card">
                        <h3>Ação Mais Frequente</h3>
                        <div class="number">${acaoTop[0]}</div>
                        <small>${acaoTop[1]} ocorrências</small>
                    </div>
                `;
            }

            if (stats.usuarios_mais_ativos) {
                const usuarioTop = Object.entries(stats.usuarios_mais_ativos)[0];
                html += `
                    <div class="stat-card">
                        <h3>Usuário Mais Ativo</h3>
                        <div class="number">${usuarioTop[0]}</div>
                        <small>${usuarioTop[1]} ações</small>
                    </div>
                `;
            }

            html += `
                <div class="stat-card">
                    <h3>Período</h3>
                    <small>
                        ${stats.periodo_consulta.inicio}<br>
                        até<br>
                        ${stats.periodo_consulta.fim}
                    </small>
                </div>
            `;

            container.innerHTML = html;
        }

        function mostrarHistorico(historico) {
            const container = document.getElementById('resultado');
            
            if (!historico || historico.length === 0) {
                container.innerHTML = '<div class="loading">📭 Nenhum registro de auditoria encontrado</div>';
                return;
            }

            let html = `
                <h3>📋 Histórico de Auditoria (${historico.length} registros)</h3>
                <table class="audit-table">
                    <thead>
                        <tr>
                            <th>Data/Hora</th>
                            <th>Ação</th>
                            <th>Usuário</th>
                            <th>Agendamento</th>
                            <th>Paciente</th>
                            <th>Status</th>
                            <th>Campos Alterados</th>
                            <th>Observações</th>
                            <th>Dados</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            historico.forEach((registro, index) => {
                const data = new Date(registro.DATA_ACAO).toLocaleString('pt-BR');
                const acao = registro.ACAO || '';
                const temDados = registro.DADOS_ANTIGOS || registro.DADOS_NOVOS;
                
                html += `
                    <tr>
                        <td>${data}</td>
                        <td><span class="acao-badge acao-${acao}">${acao}</span></td>
                        <td>${registro.USUARIO || ''}</td>
                        <td>
                            ID: ${registro.AGENDAMENTO_ID || 'N/A'}<br>
                            <small>${registro.NUMERO_AGENDAMENTO || ''}</small>
                        </td>
                        <td>${registro.PACIENTE_NOME || ''}</td>
                        <td>
                            ${registro.STATUS_ANTERIOR ? `${registro.STATUS_ANTERIOR} →` : ''}
                            ${registro.STATUS_NOVO || ''}
                        </td>
                        <td>${registro.CAMPOS_ALTERADOS || ''}</td>
                        <td>${registro.OBSERVACOES || ''}</td>
                        <td>
                            ${temDados ? `<span class="expandable" onclick="toggleDados(${index})">👁 Ver dados</span>` : ''}
                            <div id="dados-${index}" style="display: none;">
                                ${temDados ? formatarDados(registro) : ''}
                            </div>
                        </td>
                    </tr>
                `;
            });

            html += '</tbody></table>';
            container.innerHTML = html;
        }

        function formatarDados(registro) {
            let html = '';
            
            if (registro.DADOS_ANTIGOS) {
                html += `<strong>📋 Antes:</strong><div class="expanded-data">${registro.DADOS_ANTIGOS}</div>`;
            }
            
            if (registro.DADOS_NOVOS) {
                html += `<strong>📋 Depois:</strong><div class="expanded-data">${registro.DADOS_NOVOS}</div>`;
            }
            
            return html;
        }

        function toggleDados(index) {
            const elemento = document.getElementById(`dados-${index}`);
            elemento.style.display = elemento.style.display === 'none' ? 'block' : 'none';
        }

        function exportarCSV() {
            if (!dadosAtuais || dadosAtuais.length === 0) {
                alert('❌ Nenhum dado para exportar');
                return;
            }

            const csv = [
                ['Data/Hora', 'Ação', 'Usuário', 'ID_Agendamento', 'Número', 'Paciente', 'Status_Anterior', 'Status_Novo', 'Campos_Alterados', 'Observações'].join(',')
            ];

            dadosAtuais.forEach(registro => {
                const linha = [
                    `"${new Date(registro.DATA_ACAO).toLocaleString('pt-BR')}"`,
                    `"${registro.ACAO || ''}"`,
                    `"${registro.USUARIO || ''}"`,
                    `"${registro.AGENDAMENTO_ID || ''}"`,
                    `"${registro.NUMERO_AGENDAMENTO || ''}"`,
                    `"${registro.PACIENTE_NOME || ''}"`,
                    `"${registro.STATUS_ANTERIOR || ''}"`,
                    `"${registro.STATUS_NOVO || ''}"`,
                    `"${registro.CAMPOS_ALTERADOS || ''}"`,
                    `"${(registro.OBSERVACOES || '').replace(/"/g, '""')}"`
                ].join(',');
                csv.push(linha);
            });

            const csvContent = csv.join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            
            const link = document.createElement('a');
            if (link.download !== undefined) {
                const url = URL.createObjectURL(blob);
                link.setAttribute('href', url);
                link.setAttribute('download', `auditoria_agenda_${new Date().toISOString().split('T')[0]}.csv`);
                link.style.visibility = 'hidden';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }
        }
    </script>
</body>
</html>