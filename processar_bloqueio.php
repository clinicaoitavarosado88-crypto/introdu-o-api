<?php
// processar_bloqueio.php - Processa ações de bloqueio de agendas, dias e horários
header('Content-Type: application/json');
include 'includes/connection.php';
include 'includes/verificar_permissao.php';

// Suporte para linha de comando
if (php_sapi_name() === 'cli') {
    parse_str(getenv('QUERY_STRING') ?: '', $_GET);
}

// Verificar permissão para administrar agendas
$usuario_atual = getUsuarioAtual();
if (!$usuario_atual) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Usuário não autenticado']);
    exit;
}

if (!podeAdministrarAgendas($conn, $usuario_atual)) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Sem permissão para bloquear agendas']);
    exit;
}

$acao = $_GET['acao'] ?? '';

try {
    switch ($acao) {
        case 'bloquear_dia':
            $agenda_id = (int) $_POST['agenda_id'];
            $data = $_POST['data'];
            $motivo = $_POST['motivo'] ?? '';
            
            if (!$agenda_id || !$data) {
                throw new Exception('Agenda e data são obrigatórios');
            }
            
            // Inserir bloqueio de dia
            $sql = "INSERT INTO AGENDA_BLOQUEIOS (AGENDA_ID, DATA_BLOQUEIO, TIPO_BLOQUEIO, MOTIVO, USUARIO_BLOQUEIO) 
                    VALUES (?, ?, 'DIA', ?, ?)";
            
            $stmt = ibase_prepare($conn, $sql);
            $result = ibase_execute($stmt, $agenda_id, $data, $motivo, $usuario_atual);
            
            if ($result) {
                echo json_encode([
                    'status' => 'sucesso',
                    'mensagem' => 'Dia bloqueado com sucesso'
                ]);
            } else {
                throw new Exception('Erro ao bloquear dia');
            }
            break;
            
        case 'bloquear_agenda':
            $agenda_id = (int) $_POST['agenda_id'];
            $periodo = $_POST['periodo'];
            $data_inicio = $_POST['data_inicio'] ?? null;
            $data_fim = $_POST['data_fim'] ?? null;
            $motivo = $_POST['motivo'] ?? '';
            
            if (!$agenda_id || !$periodo) {
                throw new Exception('Agenda e período são obrigatórios');
            }
            
            if ($periodo === 'temporario' && (!$data_inicio || !$data_fim)) {
                throw new Exception('Para bloqueio temporário, data início e fim são obrigatórias');
            }
            
            // Inserir bloqueio de agenda
            $tipo_bloqueio = $periodo === 'permanente' ? 'AGENDA_PERMANENTE' : 'AGENDA_TEMPORARIO';
            
            $sql = "INSERT INTO AGENDA_BLOQUEIOS (AGENDA_ID, DATA_INICIO, DATA_FIM, TIPO_BLOQUEIO, MOTIVO, USUARIO_BLOQUEIO) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            
            $stmt = ibase_prepare($conn, $sql);
            $result = ibase_execute($stmt, $agenda_id, $data_inicio, $data_fim, $tipo_bloqueio, $motivo, $usuario_atual);
            
            if ($result) {
                echo json_encode([
                    'status' => 'sucesso',
                    'mensagem' => 'Agenda bloqueada com sucesso'
                ]);
            } else {
                throw new Exception('Erro ao bloquear agenda');
            }
            break;
            
        case 'bloquear_horario':
            $agenda_id = (int) $_POST['agenda_id'];
            $data = $_POST['data'];
            $horario_inicio = $_POST['horario_inicio'];
            $horario_fim = $_POST['horario_fim'];
            $motivo = $_POST['motivo'] ?? '';
            
            if (!$agenda_id || !$data || !$horario_inicio || !$horario_fim) {
                throw new Exception('Todos os campos são obrigatórios');
            }
            
            // Inserir bloqueio de horário
            $sql = "INSERT INTO AGENDA_BLOQUEIOS (AGENDA_ID, DATA_BLOQUEIO, HORARIO_INICIO, HORARIO_FIM, TIPO_BLOQUEIO, MOTIVO, USUARIO_BLOQUEIO) 
                    VALUES (?, ?, ?, ?, 'HORARIO', ?, ?)";
            
            $stmt = ibase_prepare($conn, $sql);
            $result = ibase_execute($stmt, $agenda_id, $data, $horario_inicio, $horario_fim, $motivo, $usuario_atual);
            
            if ($result) {
                echo json_encode([
                    'status' => 'sucesso',
                    'mensagem' => 'Horário bloqueado com sucesso'
                ]);
            } else {
                throw new Exception('Erro ao bloquear horário');
            }
            break;
            
        case 'desbloquear':
            $bloqueio_id = (int) $_POST['bloqueio_id'];
            
            if (!$bloqueio_id) {
                throw new Exception('ID do bloqueio é obrigatório');
            }
            
            // Marcar bloqueio como inativo ao invés de excluir
            $sql = "UPDATE AGENDA_BLOQUEIOS SET ATIVO = 0 WHERE ID = ?";
            $stmt = ibase_prepare($conn, $sql);
            $result = ibase_execute($stmt, $bloqueio_id);
            
            if ($result) {
                echo json_encode([
                    'status' => 'sucesso',
                    'mensagem' => 'Bloqueio removido com sucesso'
                ]);
            } else {
                throw new Exception('Erro ao remover bloqueio');
            }
            break;
            
        default:
            throw new Exception('Ação não reconhecida');
    }
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => $e->getMessage()
    ]);
}
?>