<?php
// cancelar_agendamento.php

// ✅ Headers CORS para evitar erro 400 no preflight
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type');
header('Content-Type: application/json');

// ✅ Tratar requisição OPTIONS (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

include 'includes/connection.php';
include 'includes/verificar_permissao.php';
include 'includes/auditoria.php';

// Log de debug
error_log("=== CANCELAR AGENDAMENTO ===");
error_log("POST data: " . print_r($_POST, true));

// Verificar se há usuário logado (qualquer usuário pode cancelar)
$usuario_atual = getUsuarioAtual();
if (!$usuario_atual) {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Usuário não autenticado'
    ]);
    exit;
}

try {
    // Validar campos obrigatórios
    $agendamento_id = $_POST['agendamento_id'] ?? '';
    $motivo_cancelamento = $_POST['motivo_cancelamento'] ?? 'Cancelado pelo usuário';
    
    if (empty($agendamento_id)) {
        throw new Exception('ID do agendamento é obrigatório');
    }
    
    error_log("📝 Cancelando agendamento:");
    error_log("  - ID: $agendamento_id");
    error_log("  - Motivo: $motivo_cancelamento");
    
    // Iniciar transação
    $trans = ibase_trans($conn);
    
    try {
        // Primeiro, verificar se o agendamento existe e obter dados
        $query_verificar = "SELECT 
                              a.ID, 
                              a.NUMERO_AGENDAMENTO, 
                              a.STATUS,
                              COALESCE(p.PACIENTE, a.NOME_PACIENTE) as PACIENTE_NOME,
                              a.DATA_AGENDAMENTO,
                              a.HORA_AGENDAMENTO
                            FROM AGENDAMENTOS a
                            LEFT JOIN LAB_PACIENTES p ON a.PACIENTE_ID = p.IDPACIENTE
                            WHERE a.ID = ?";
        
        $stmt_verificar = ibase_prepare($trans, $query_verificar);
        $result_verificar = ibase_execute($stmt_verificar, $agendamento_id);
        $agendamento = ibase_fetch_assoc($result_verificar);
        
        if (!$agendamento) {
            throw new Exception('Agendamento não encontrado');
        }
        
        // Verificar se já está cancelado
        if ($agendamento['STATUS'] === 'CANCELADO') {
            throw new Exception('Agendamento já está cancelado');
        }
        
        // Verificar se é um bloqueio (não pode ser cancelado, deve ser desbloqueado)
        if ($agendamento['STATUS'] === 'BLOQUEADO') {
            throw new Exception('Horários bloqueados devem ser desbloqueados, não cancelados');
        }
        
        error_log("✅ Agendamento encontrado: " . $agendamento['PACIENTE_NOME']);
        
        // Preparar dados para auditoria (dados antes do cancelamento)
        $dados_antes_cancelamento = [
            'id' => $agendamento['ID'],
            'numero_agendamento' => $agendamento['NUMERO_AGENDAMENTO'],
            'status' => $agendamento['STATUS'],
            'paciente' => $agendamento['PACIENTE_NOME'],
            'data_agendamento' => $agendamento['DATA_AGENDAMENTO'],
            'hora_agendamento' => $agendamento['HORA_AGENDAMENTO']
        ];
        
        // Atualizar o status para CANCELADO
        $query_cancelar = "UPDATE AGENDAMENTOS SET 
                           STATUS = 'CANCELADO',
                           OBSERVACOES = COALESCE(OBSERVACOES, '') || ? 
                           WHERE ID = ?";
        
        $observacao_cancelamento = "\n[CANCELADO em " . date('d/m/Y H:i') . "] " . $motivo_cancelamento;
        
        $stmt_cancelar = ibase_prepare($trans, $query_cancelar);
        $result_cancelar = ibase_execute($stmt_cancelar, $observacao_cancelamento, $agendamento_id);
        
        if (!$result_cancelar) {
            $error_info = ibase_errmsg();
            throw new Exception('Erro ao cancelar agendamento: ' . $error_info);
        }
        
        // Preparar dados após cancelamento para auditoria
        $dados_apos_cancelamento = [
            'id' => $agendamento['ID'],
            'numero_agendamento' => $agendamento['NUMERO_AGENDAMENTO'],
            'status' => 'CANCELADO',
            'paciente' => $agendamento['PACIENTE_NOME'],
            'data_agendamento' => $agendamento['DATA_AGENDAMENTO'],
            'hora_agendamento' => $agendamento['HORA_AGENDAMENTO'],
            'motivo_cancelamento' => $motivo_cancelamento
        ];
        
        // Registrar auditoria do cancelamento
        auditarAgendamento(
            $conn, 
            'CANCELAR', 
            $usuario_atual, 
            $dados_apos_cancelamento, 
            $dados_antes_cancelamento,
            "Agendamento cancelado. Motivo: {$motivo_cancelamento}"
        );
        
        // Log do cancelamento
        error_log("✅ Agendamento cancelado com sucesso:");
        error_log("  - ID: $agendamento_id");
        error_log("  - Paciente: " . $agendamento['PACIENTE_NOME']);
        error_log("  - Data/Hora: " . $agendamento['DATA_AGENDAMENTO'] . " " . $agendamento['HORA_AGENDAMENTO']);
        error_log("  - Cancelado por: $usuario_atual");
        error_log("  - Motivo: $motivo_cancelamento");
        
        // Confirmar transação
        ibase_commit($trans);
        
        // ============================================================================
        // INTEGRAÇÃO WHATSAPP - Processar cancelamento
        // ============================================================================
        try {
            include_once 'whatsapp_hooks.php';
            
            $agendamentoWhatsApp = [
                'id' => $agendamento_id
            ];
            
            $resultadoWhatsApp = processarHookAgendamento('cancelar', $agendamentoWhatsApp);
            error_log("WhatsApp Hook cancelamento resultado: $resultadoWhatsApp");
            
        } catch (Exception $e_whatsapp) {
            error_log("Erro no hook WhatsApp (não crítico): " . $e_whatsapp->getMessage());
            // Não interrompe o processo se WhatsApp falhar
        }
        
        echo json_encode([
            'status' => 'sucesso',
            'mensagem' => 'Agendamento cancelado com sucesso',
            'agendamento_id' => $agendamento_id,
            'paciente' => utf8_encode($agendamento['PACIENTE_NOME']),
            'data_hora' => $agendamento['DATA_AGENDAMENTO'] . ' ' . substr($agendamento['HORA_AGENDAMENTO'], 0, 5)
        ]);
        
    } catch (Exception $e) {
        ibase_rollback($trans);
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("❌ Erro ao cancelar agendamento: " . $e->getMessage());
    echo json_encode([
        'status' => 'erro',
        'mensagem' => $e->getMessage()
    ]);
}
?>