<?php
// registrar_auditoria.php
header('Content-Type: application/json');
include 'includes/connection.php';
include 'includes/auditoria.php';

// Verificar usuário logado
$usuario = $_COOKIE["log_usuario"] ?? 'SISTEMA';

try {
    // Validar parâmetros obrigatórios
    $acao = $_POST['acao'] ?? '';
    $agendamento_id = $_POST['agendamento_id'] ?? null;
    $campo_alterado = $_POST['campo_alterado'] ?? '';
    $valor_anterior = $_POST['valor_anterior'] ?? '';
    $valor_novo = $_POST['valor_novo'] ?? '';
    $observacoes = $_POST['observacoes'] ?? '';
    
    if (empty($acao) || empty($agendamento_id) || empty($campo_alterado)) {
        throw new Exception('Parâmetros obrigatórios não fornecidos');
    }
    
    // Buscar dados do agendamento para auditoria
    $query_agendamento = "SELECT 
                            a.*,
                            COALESCE(p.PACIENTE, a.NOME_PACIENTE) as NOME_PACIENTE_COMPLETO,
                            ag.TIPO_AGENDA as NOME_AGENDA
                          FROM AGENDAMENTOS a
                          LEFT JOIN LAB_PACIENTES p ON a.PACIENTE_ID = p.IDPACIENTE  
                          LEFT JOIN AGENDAS ag ON a.AGENDA_ID = ag.ID
                          WHERE a.ID = ?";
    
    $stmt = ibase_prepare($conn, $query_agendamento);
    $result = ibase_execute($stmt, $agendamento_id);
    $agendamento = ibase_fetch_assoc($result);
    
    if (!$agendamento) {
        throw new Exception('Agendamento não encontrado');
    }
    
    // Preparar dados para auditoria
    $dados_agendamento = [
        'id' => $agendamento['ID'],
        'numero_agendamento' => $agendamento['NUMERO_AGENDAMENTO'],
        'paciente' => utf8_encode($agendamento['NOME_PACIENTE_COMPLETO'] ?? ''),
        'agenda_id' => $agendamento['AGENDA_ID'],
        'data_agendamento' => $agendamento['DATA_AGENDAMENTO'],
        'hora_agendamento' => $agendamento['HORA_AGENDAMENTO'],
        'status' => $agendamento['STATUS']
    ];
    
    // Criar observação detalhada
    $observacao_completa = "Campo '{$campo_alterado}' alterado de '{$valor_anterior}' para '{$valor_novo}'. {$observacoes}";
    
    // Registrar na auditoria usando função existente
    $sucesso = registrarAuditoria($conn, [
        'acao' => $acao,
        'usuario' => $usuario,
        'agendamento_id' => $agendamento_id,
        'numero_agendamento' => $agendamento['NUMERO_AGENDAMENTO'],
        'paciente_nome' => utf8_encode($agendamento['NOME_PACIENTE_COMPLETO'] ?? ''),
        'agenda_id' => $agendamento['AGENDA_ID'],
        'data_agendamento' => $agendamento['DATA_AGENDAMENTO'],
        'hora_agendamento' => $agendamento['HORA_AGENDAMENTO'],
        'campos_alterados' => $campo_alterado,
        'status_anterior' => ($campo_alterado === 'confirmado') ? $valor_anterior : null,
        'status_novo' => ($campo_alterado === 'confirmado') ? $valor_novo : null,
        'observacoes' => $observacao_completa,
        'dados_novos' => json_encode([
            $campo_alterado => $valor_novo
        ], JSON_UNESCAPED_UNICODE),
        'dados_antigos' => json_encode([
            $campo_alterado => $valor_anterior  
        ], JSON_UNESCAPED_UNICODE)
    ]);
    
    if ($sucesso) {
        echo json_encode([
            'sucesso' => true,
            'mensagem' => 'Auditoria registrada com sucesso'
        ]);
    } else {
        throw new Exception('Falha ao registrar auditoria');
    }
    
} catch (Exception $e) {
    error_log("ERRO registrar_auditoria.php: " . $e->getMessage());
    echo json_encode([
        'sucesso' => false,
        'erro' => $e->getMessage()
    ]);
}
?>