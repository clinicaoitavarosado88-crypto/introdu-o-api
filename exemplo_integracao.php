<?php
// exemplo_integracao.php
// Exemplo de como integrar o módulo de agenda no sistema principal

// Simular o login do sistema principal (ajuste conforme seu sistema)
if (!isset($_COOKIE["log_usuario"])) {
    // Se não há cookie, definir um para teste (remover em produção)
    setcookie("log_usuario", "RENISON", time() + 3600, "/"); // 1 hora
    $_COOKIE["log_usuario"] = "RENISON";
}

$log_usuario = $_COOKIE["log_usuario"];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema Principal - Agenda</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .header { background: #f0f0f0; padding: 10px; margin-bottom: 20px; border-radius: 5px; }
        .user-info { background: #e8f5e8; padding: 10px; margin-bottom: 20px; border-radius: 5px; }
        .agenda-container { border: 1px solid #ddd; padding: 20px; border-radius: 5px; }
        .test-buttons { margin: 20px 0; }
        .test-buttons button { margin: 5px; padding: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>🏥 Sistema Principal - Módulo de Agenda</h1>
        <p>Exemplo de integração com o sistema de permissões</p>
    </div>

    <div class="user-info">
        <h3>👤 Informações do Usuário Logado</h3>
        <p><strong>Usuário:</strong> <?php echo htmlspecialchars($log_usuario); ?></p>
        <p><strong>Cookie:</strong> <?php echo isset($_COOKIE["log_usuario"]) ? "✅ Presente" : "❌ Ausente"; ?></p>
        <p><strong>Permissões:</strong> Qualquer usuário logado pode cancelar agendamentos</p>
        <p><strong>Bloqueio/Desbloqueio:</strong> Apenas usuários com permissão "Administrar agenda"</p>
    </div>

    <div class="test-buttons">
        <h3>🧪 Testes do Sistema</h3>
        <button onclick="testarDeteccaoUsuario()">Testar Detecção de Usuário</button>
        <button onclick="testarPermissoes()">Verificar Permissões</button>
        <button onclick="testarCancelamento()">Testar Cancelamento</button>
        <button onclick="listarCancelados()">Ver Cancelados</button>
        <button onclick="window.open('auditoria.php', '_blank')">📋 Ver Auditoria</button>
        <button onclick="instalarAuditoria()">🔧 Instalar Sistema de Auditoria</button>
        <button onclick="simularLogout()">Simular Logout</button>
        <button onclick="simularLogin()">Simular Login</button>
    </div>

    <div class="agenda-container">
        <h3>📅 Módulo de Agenda</h3>
        <div id="area-visualizacao">
            <!-- Aqui seria incluído o conteúdo da agenda -->
            <p>Área onde o módulo de agenda seria carregado...</p>
            <p>Console do navegador mostrará os logs de integração.</p>
        </div>
    </div>

    <!-- Scripts do sistema de agenda -->
    <script src="includes/agenda-new.js"></script>
    <script src="configurar_usuario_renison.js"></script>

    <script>
        console.log('🔗 Sistema Principal carregado');
        console.log('👤 Usuário do PHP:', '<?php echo $log_usuario; ?>');

        function testarDeteccaoUsuario() {
            console.log('🧪 Testando detecção de usuário...');
            
            // Verificar cookie diretamente
            const cookieUser = getCookie('log_usuario');
            console.log('🍪 Cookie log_usuario:', cookieUser);
            
            // Verificar usuário configurado no sistema
            console.log('👤 Usuário atual configurado:', window.usuarioAtual);
            
            // Chamar API de detecção
            fetch('includes/verificar_permissao.php?acao=obter_usuario_atual')
                .then(response => response.json())
                .then(data => {
                    console.log('🔍 Resposta do backend:', data);
                    alert('Usuário detectado: ' + (data.usuario || 'Nenhum') + 
                          '\nFonte: ' + (data.fonte || 'N/A'));
                })
                .catch(error => {
                    console.error('💥 Erro:', error);
                    alert('Erro ao detectar usuário: ' + error.message);
                });
        }

        function testarPermissoes() {
            const usuario = window.usuarioAtual || getCookie('log_usuario');
            if (!usuario) {
                alert('Nenhum usuário detectado!');
                return;
            }
            
            console.log('🔐 Testando permissões para:', usuario);
            
            fetch(`testar_permissoes.php?usuario=${encodeURIComponent(usuario)}`)
                .then(response => response.json())
                .then(data => {
                    console.log('🔐 Permissões:', data);
                    const pode = data.pode_administrar_agendas ? 'SIM' : 'NÃO';
                    alert(`Usuário: ${usuario}\nPode administrar agendas: ${pode}\nTotal de permissões: ${data.total_permissoes}`);
                })
                .catch(error => {
                    console.error('💥 Erro:', error);
                    alert('Erro ao verificar permissões: ' + error.message);
                });
        }

        function simularLogout() {
            document.cookie = "log_usuario=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;";
            window.usuarioAtual = null;
            window.usuarioPermissoes = null;
            console.log('🚪 Logout simulado');
            alert('Logout simulado! Cookie removido.');
            location.reload();
        }

        function simularLogin() {
            const novoUsuario = prompt('Digite o usuário para simular login:', 'RENISON');
            if (novoUsuario) {
                document.cookie = `log_usuario=${novoUsuario}; path=/`;
                console.log('🔑 Login simulado para:', novoUsuario);
                alert('Login simulado para: ' + novoUsuario);
                location.reload();
            }
        }

        function testarCancelamento() {
            const agendamentoId = prompt('Digite o ID do agendamento para cancelar:', '150');
            if (!agendamentoId) return;
            
            const motivo = prompt('Motivo do cancelamento:', 'Teste via página de exemplo');
            
            console.log('🗑️ Testando cancelamento via API...');
            
            const formData = new FormData();
            formData.append('agendamento_id', agendamentoId);
            formData.append('motivo_cancelamento', motivo || 'Teste');
            
            fetch('cancelar_agendamento.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                console.log('📊 Resposta do cancelamento:', data);
                if (data.status === 'sucesso') {
                    alert(`✅ ${data.mensagem}\n\nPaciente: ${data.paciente}\nData/Hora: ${data.data_hora}`);
                } else {
                    alert(`❌ Erro: ${data.mensagem}`);
                }
            })
            .catch(error => {
                console.error('💥 Erro:', error);
                alert('Erro ao cancelar: ' + error.message);
            });
        }

        function listarCancelados() {
            console.log('📋 Buscando agendamentos cancelados...');
            
            const agendaId = prompt('ID da agenda:', '2');
            if (!agendaId) return;
            
            const dataInicio = prompt('Data início (YYYY-MM-DD):', '2025-08-01');
            const dataFim = prompt('Data fim (YYYY-MM-DD):', '2025-08-31');
            
            fetch(`listar_cancelados.php?agenda_id=${agendaId}&data_inicio=${dataInicio}&data_fim=${dataFim}`)
            .then(response => response.json())
            .then(data => {
                console.log('📋 Cancelados encontrados:', data);
                
                if (data.erro) {
                    alert('Erro: ' + data.erro);
                    return;
                }
                
                let mensagem = `📋 Agendamentos Cancelados\n\n`;
                mensagem += `Agenda: ${data.agenda_id}\n`;
                mensagem += `Período: ${data.periodo.inicio} a ${data.periodo.fim}\n`;
                mensagem += `Total: ${data.total}\n\n`;
                
                if (data.cancelados.length === 0) {
                    mensagem += 'Nenhum agendamento cancelado encontrado.';
                } else {
                    data.cancelados.forEach((item, index) => {
                        mensagem += `${index + 1}. ID ${item.id} - ${item.paciente}\n`;
                        mensagem += `   Data/Hora: ${item.data_formatada} ${item.hora}\n`;
                        mensagem += `   Convênio: ${item.convenio}\n\n`;
                    });
                }
                
                alert(mensagem);
            })
            .catch(error => {
                console.error('💥 Erro:', error);
                alert('Erro ao listar cancelados: ' + error.message);
            });
        }

        function instalarAuditoria() {
            if (confirm('🔧 Deseja instalar/atualizar o sistema de auditoria?\n\nIsso criará a tabela AGENDA_AUDITORIA no banco de dados.')) {
                console.log('🔧 Instalando sistema de auditoria...');
                
                const novaJanela = window.open('criar_tabela_auditoria.php', '_blank');
                
                if (!novaJanela) {
                    alert('❌ Pop-up bloqueado! Abra manualmente: criar_tabela_auditoria.php');
                } else {
                    alert('✅ Instalação iniciada! Verifique a nova janela.');
                }
            }
        }

        function getCookie(name) {
            const value = `; ${document.cookie}`;
            const parts = value.split(`; ${name}=`);
            if (parts.length === 2) return parts.pop().split(';').shift();
            return null;
        }
    </script>
</body>
</html>