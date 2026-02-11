<?php
// mover_agendamento.php
header('Content-Type: application/json');
include 'includes/connection.php';
include 'includes/auditoria.php';

// Recebe dados JSON
$input = json_decode(file_get_contents('php://input'), true);

$agendamento_id = $input['agendamento_id'] ?? 0;
$nova_data = $input['nova_data'] ?? '';
$nova_hora = $input['nova_hora'] ?? '';

if (!$agendamento_id || !$nova_data || !$nova_hora) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Dados incompletos'
    ]);
    exit;
}

try {
    // Obter usuário atual
    $usuario_atual = $_COOKIE['log_usuario'] ?? 'SISTEMA_WEB';
    
    // Buscar dados completos do agendamento ANTES da movimentação
    $dados_antes_movimento = buscarDadosCompletosAgendamento($conn, $agendamento_id);
    
    if (!$dados_antes_movimento) {
        throw new Exception('Agendamento não encontrado');
    }
    
    // Extrair informações básicas do agendamento
    $agendamento_atual = [
        'AGENDA_ID' => $dados_antes_movimento['agenda_id'],
        'DATA_AGENDAMENTO' => $dados_antes_movimento['data_agendamento'],
        'HORA_AGENDAMENTO' => $dados_antes_movimento['hora_agendamento']
    ];
    
    // ✅ CORREÇÃO: Verificar disponibilidade considerando TEMPO TOTAL dos exames

    // 1. Buscar TEMPO TOTAL do agendamento sendo movido
    $query_exames_movimento = "SELECT ex.TEMPO_EXAME
                               FROM AGENDAMENTO_EXAMES ae
                               LEFT JOIN LAB_EXAMES ex ON ex.IDEXAME = ae.EXAME_ID
                               WHERE ae.NUMERO_AGENDAMENTO = ?";

    $stmt_exames_mov = ibase_prepare($conn, $query_exames_movimento);
    $result_exames_mov = ibase_execute($stmt_exames_mov, $dados_antes_movimento['numero_agendamento']);

    $tempo_total_movimento = 0;
    while ($exame_mov = ibase_fetch_assoc($result_exames_mov)) {
        $tempo_exame = (int)($exame_mov['TEMPO_EXAME'] ?? 0);
        if ($tempo_exame <= 0) {
            $tempo_exame = 30; // Fallback
        }
        $tempo_total_movimento += $tempo_exame;
    }

    if ($tempo_total_movimento <= 0) {
        $tempo_total_movimento = 30; // Fallback se não encontrou exames
    }

    error_log("mover_agendamento.php - Movendo agendamento com tempo total: {$tempo_total_movimento}min para $nova_data $nova_hora");

    // 2. Calcular janela de tempo que o agendamento vai ocupar
    $inicio_movimento = new DateTime($nova_data . ' ' . $nova_hora);
    $fim_movimento = clone $inicio_movimento;
    $fim_movimento->add(new DateInterval("PT{$tempo_total_movimento}M"));

    // 3. Buscar TODOS os agendamentos existentes no novo dia (exceto o que está sendo movido)
    $query_agendados_dia = "SELECT ag.HORA_AGENDAMENTO, ag.NUMERO_AGENDAMENTO
                            FROM AGENDAMENTOS ag
                            WHERE ag.AGENDA_ID = ?
                            AND ag.DATA_AGENDAMENTO = ?
                            AND ag.STATUS NOT IN ('CANCELADO', 'FALTOU')
                            AND ag.ID != ?
                            ORDER BY ag.HORA_AGENDAMENTO";

    $stmt_agendados = ibase_prepare($conn, $query_agendados_dia);
    $result_agendados = ibase_execute($stmt_agendados,
        $agendamento_atual['AGENDA_ID'],
        $nova_data,
        $agendamento_id
    );

    // 4. Verificar conflito com CADA agendamento existente
    while ($agd_existente = ibase_fetch_assoc($result_agendados)) {
        $hora_existente = substr($agd_existente['HORA_AGENDAMENTO'], 0, 5);
        $numero_existente = trim($agd_existente['NUMERO_AGENDAMENTO']);

        // Buscar tempo total do agendamento existente
        $query_exames_exist = "SELECT ex.TEMPO_EXAME
                               FROM AGENDAMENTO_EXAMES ae
                               LEFT JOIN LAB_EXAMES ex ON ex.IDEXAME = ae.EXAME_ID
                               WHERE ae.NUMERO_AGENDAMENTO = ?";

        $stmt_exames_exist = ibase_prepare($conn, $query_exames_exist);
        $result_exames_exist = ibase_execute($stmt_exames_exist, $numero_existente);

        $tempo_total_existente = 0;
        while ($exame_exist = ibase_fetch_assoc($result_exames_exist)) {
            $tempo_exame_exist = (int)($exame_exist['TEMPO_EXAME'] ?? 0);
            if ($tempo_exame_exist <= 0) {
                $tempo_exame_exist = 30;
            }
            $tempo_total_existente += $tempo_exame_exist;
        }

        if ($tempo_total_existente <= 0) {
            $tempo_total_existente = 30;
        }

        // Calcular janela de tempo do agendamento existente
        $inicio_existente = new DateTime($nova_data . ' ' . $hora_existente);
        $fim_existente = clone $inicio_existente;
        $fim_existente->add(new DateInterval("PT{$tempo_total_existente}M"));

        // Verificar se há sobreposição
        if ($inicio_movimento < $fim_existente && $fim_movimento > $inicio_existente) {
            // Há conflito!
            $msg_conflito = sprintf(
                "Conflito de horário! O novo horário (%s - %s) conflita com agendamento existente às %s (duração: %dmin)",
                $inicio_movimento->format('H:i'),
                $fim_movimento->format('H:i'),
                $hora_existente,
                $tempo_total_existente
            );

            error_log("mover_agendamento.php - " . $msg_conflito);
            throw new Exception($msg_conflito);
        }

        ibase_free_result($result_exames_exist);
    }
    
    // Verifica se o horário não passou
    $agora = new DateTime();
    $novo_horario = new DateTime($nova_data . ' ' . $nova_hora);
    
    if ($novo_horario < $agora) {
        throw new Exception('Não é possível mover para um horário que já passou');
    }
    
    // Atualiza o agendamento
    $query_update = "UPDATE AGENDAMENTOS 
                     SET DATA_AGENDAMENTO = ?, 
                         HORA_AGENDAMENTO = ?,
                         DATA_ALTERACAO = ?,
                         ALTERADO_POR = ?
                     WHERE ID = ?";
    
    $stmt_update = ibase_prepare($conn, $query_update);
    $data_alteracao = date('Y-m-d H:i:s');
    
    $result_update = ibase_execute($stmt_update, 
        $nova_data, 
        $nova_hora . ':00',
        $data_alteracao,
        $usuario_atual,
        $agendamento_id
    );
    
    if (!$result_update) {
        throw new Exception('Erro ao atualizar agendamento');
    }
    
    // Buscar dados completos do agendamento APÓS a movimentação
    $dados_apos_movimento = buscarDadosCompletosAgendamento($conn, $agendamento_id);
    
    // Registrar auditoria completa da movimentação
    $observacoes = sprintf(
        "Agendamento movido via drag & drop. " .
        "DE: %s às %s PARA: %s às %s. " .
        "Paciente: %s. Convênio: %s",
        date('d/m/Y', strtotime($dados_antes_movimento['data_agendamento'])),
        substr($dados_antes_movimento['hora_agendamento'], 0, 5),
        date('d/m/Y', strtotime($nova_data)),
        substr($nova_hora, 0, 5),
        $dados_antes_movimento['nome_paciente'],
        $dados_antes_movimento['nome_convenio'] ?? 'N/A'
    );
    
    $resultado_auditoria = auditarAgendamentoCompleto(
        $conn,
        'MOVER',
        $usuario_atual,
        $dados_apos_movimento,
        $dados_antes_movimento,
        $observacoes,
        [
            'metodo_movimentacao' => 'drag_and_drop',
            'horario_origem' => $dados_antes_movimento['data_agendamento'] . ' ' . $dados_antes_movimento['hora_agendamento'],
            'horario_destino' => $nova_data . ' ' . $nova_hora . ':00',
            'ip_movimentacao' => obterIPUsuario(),
            'timestamp_movimentacao' => $data_alteracao
        ]
    );
    
    if (!$resultado_auditoria) {
        error_log("AVISO: Falha ao registrar auditoria de movimentação para agendamento ID {$agendamento_id}");
    }
    
    // Log adicional no banco (mantido para compatibilidade, se existir a tabela)
    try {
        $query_log = "INSERT INTO AGENDAMENTOS_LOG (
                        AGENDAMENTO_ID, 
                        ACAO, 
                        DATA_ANTERIOR, 
                        HORA_ANTERIOR,
                        DATA_NOVA,
                        HORA_NOVA,
                        DATA_ACAO,
                        USUARIO
                      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt_log = ibase_prepare($conn, $query_log);
        ibase_execute($stmt_log,
            $agendamento_id,
            'MOVIDO',
            $agendamento_atual['DATA_AGENDAMENTO'],
            $agendamento_atual['HORA_AGENDAMENTO'],
            $nova_data,
            $nova_hora . ':00',
            $data_alteracao,
            $usuario_atual
        );
    } catch (Exception $log_error) {
        // Ignora erro do log antigo se a tabela não existir
        error_log("INFO: Log complementar não registrado: " . $log_error->getMessage());
    }
    
    // Commit da transação
    ibase_commit($conn);
    
    echo json_encode([
        'status' => 'sucesso',
        'mensagem' => 'Agendamento movido com sucesso',
        'detalhes' => [
            'agendamento_id' => $agendamento_id,
            'paciente' => $dados_antes_movimento['nome_paciente'],
            'horario_anterior' => date('d/m/Y', strtotime($dados_antes_movimento['data_agendamento'])) . ' às ' . substr($dados_antes_movimento['hora_agendamento'], 0, 5),
            'horario_novo' => date('d/m/Y', strtotime($nova_data)) . ' às ' . substr($nova_hora, 0, 5),
            'usuario' => $usuario_atual,
            'auditoria_registrada' => $resultado_auditoria ? 'sim' : 'nao'
        ]
    ]);
    
} catch (Exception $e) {
    // Rollback em caso de erro
    ibase_rollback($conn);
    
    echo json_encode([
        'status' => 'erro',
        'mensagem' => $e->getMessage()
    ]);
}
?>