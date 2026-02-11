<?php
// verificar_retornos.php
header('Content-Type: application/json');
include 'includes/connection.php';

// Suporte para linha de comando
if (php_sapi_name() === 'cli') {
    parse_str(getenv('QUERY_STRING') ?: '', $_GET);
}

$agenda_id = $_GET['agenda_id'] ?? 0;
$data = $_GET['data'] ?? '';

if (!$agenda_id || !$data) {
    echo json_encode(['erro' => 'Parâmetros obrigatórios não fornecidos']);
    exit;
}

try {
    // Buscar informações da agenda incluindo o TIPO
    $query_agenda = "SELECT 
                        LIMITE_RETORNOS_DIA,
                        POSSUI_RETORNO,
                        TIPO
                     FROM AGENDAS 
                     WHERE ID = ?";
    
    $stmt_agenda = ibase_prepare($conn, $query_agenda);
    $result_agenda = ibase_execute($stmt_agenda, $agenda_id);
    $agenda = ibase_fetch_assoc($result_agenda);
    
    if (!$agenda) {
        echo json_encode(['erro' => 'Agenda não encontrada']);
        exit;
    }
    
    $limiteRetornos = (int)($agenda['LIMITE_RETORNOS_DIA'] ?? 0);
    $possuiRetorno = (bool)($agenda['POSSUI_RETORNO'] ?? false);
    $tipo = trim($agenda['TIPO'] ?? '');
    
    
    // Se não é consulta, não mostrar retornos
    if ($tipo !== 'consulta') {
        echo json_encode([
            'permite_retornos' => false,
            'limite_total' => $limiteRetornos,
            'retornos_ocupados' => 0,
            'retornos_disponiveis' => 0,
            'pode_retornar' => false,
            'tipo' => $tipo,
            'mensagem' => 'Retornos disponíveis apenas para agendas do tipo consulta'
        ]);
        exit;
    }
    
    // Se limite é 0, não permite retornos
    if ($limiteRetornos <= 0) {
        echo json_encode([
            'permite_retornos' => false,
            'limite_total' => $limiteRetornos,
            'retornos_ocupados' => 0,
            'retornos_disponiveis' => 0,
            'pode_retornar' => false,
            'tipo' => $tipo,
            'mensagem' => 'Limite de retornos não configurado (deve ser maior que 0)'
        ]);
        exit;
    }
    
    // Se não possui retorno configurado, permitir mesmo assim (já que tem limite > 0)
    
    // Contar retornos já agendados para esta data
    $query_retornos = "SELECT COUNT(*) as TOTAL_RETORNOS 
                       FROM AGENDAMENTOS 
                       WHERE AGENDA_ID = ? 
                       AND DATA_AGENDAMENTO = ? 
                       AND TIPO_AGENDAMENTO = 'RETORNO'
                       AND STATUS NOT IN ('CANCELADO', 'FALTOU')";
    
    $stmt_retornos = ibase_prepare($conn, $query_retornos);
    $result_retornos = ibase_execute($stmt_retornos, $agenda_id, $data);
    $row_retornos = ibase_fetch_assoc($result_retornos);
    
    $retornosOcupados = (int)($row_retornos['TOTAL_RETORNOS'] ?? 0);
    $retornosDisponiveis = max(0, $limiteRetornos - $retornosOcupados);
    $podeRetornar = $retornosDisponiveis > 0;
    
    // Preparar resposta
    $resposta = [
        'permite_retornos' => true,
        'limite_total' => $limiteRetornos,
        'retornos_ocupados' => $retornosOcupados,
        'retornos_disponiveis' => $retornosDisponiveis,
        'pode_retornar' => $podeRetornar,
        'tipo' => $tipo,
        'mensagem' => $podeRetornar 
            ? "Há {$retornosDisponiveis} vaga(s) de retorno disponível(is)"
            : "Limite de retornos atingido para esta data"
    ];
    
    echo json_encode($resposta);
    
} catch (Exception $e) {
    echo json_encode([
        'erro' => 'Erro interno do servidor',
        'detalhes' => $e->getMessage()
    ]);
}
?>