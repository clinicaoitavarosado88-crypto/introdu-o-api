<?php
// verificar_encaixes.php - VERSÃO FINAL SUPER LIMPA
// Para evitar múltiplos JSONs concatenados

// 1. Configurar saída ANTES de qualquer include
if (ob_get_level()) {
    ob_end_clean();
}
ob_start();

// 2. Headers imediatos
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-cache, must-revalidate');

// 3. Suprimir TODOS os outputs de erro
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 0);

// 4. Validar parâmetros ANTES de incluir qualquer coisa
$agenda_id = filter_input(INPUT_GET, 'agenda_id', FILTER_VALIDATE_INT);
$data = filter_input(INPUT_GET, 'data', FILTER_SANITIZE_STRING);

if (!$agenda_id || !$data) {
    ob_clean();
    die('{"erro":"Parâmetros inválidos"}');
}

// 5. Incluir conexão de forma isolada
$conn = null;
try {
    // Incluir apenas o necessário
    if (file_exists('includes/connection.php')) {
        ob_start();
        include_once 'includes/connection.php';
        ob_end_clean();
    } else {
        throw new Exception('Arquivo de conexão não encontrado');
    }
} catch (Exception $e) {
    ob_clean();
    die('{"erro":"Erro de conexão: ' . addslashes($e->getMessage()) . '"}');
}

// 6. Limpar buffer mais uma vez
ob_clean();

// 7. Processar APENAS esta requisição
try {
    // Query simplificada
    $stmt = ibase_prepare($conn, "SELECT COALESCE(LIMITE_ENCAIXES_DIA, 0) as LIMITE FROM AGENDAS WHERE ID = ?");
    $result = ibase_execute($stmt, $agenda_id);
    $agenda = ibase_fetch_assoc($result);
    
    if (!$agenda) {
        throw new Exception('Agenda não encontrada');
    }
    
    $limite = (int)$agenda['LIMITE'];
    
    if ($limite <= 0) {
        $response = [
            'permite_encaixes' => false,
            'limite_total' => 0,
            'encaixes_ocupados' => 0,
            'encaixes_disponiveis' => 0,
            'pode_encaixar' => false,
            'mensagem' => 'Esta agenda não permite encaixes'
        ];
    } else {
        // Contar encaixes ocupados
        $stmt = ibase_prepare($conn, "SELECT COUNT(*) as TOTAL 
               FROM AGENDAMENTOS 
               WHERE AGENDA_ID = ? 
                 AND DATA_AGENDAMENTO = ? 
                 AND (TIPO_AGENDAMENTO = 'ENCAIXE' 
                      OR UPPER(OBSERVACOES) LIKE '%ENCAIXE%'
                      OR UPPER(NUMERO_AGENDAMENTO) LIKE '%ENCAIXE%')
                 AND STATUS NOT IN ('CANCELADO', 'FALTOU')");
        $result = ibase_execute($stmt, $agenda_id, $data);
        $count = ibase_fetch_assoc($result);
        
        $ocupados = (int)($count['TOTAL'] ?? 0);
        $disponiveis = max(0, $limite - $ocupados);
        $pode_encaixar = $disponiveis > 0;
        
        $mensagem = $pode_encaixar ? 
            "Disponível: {$disponiveis} de {$limite} encaixes" :
            "Limite de encaixes atingido ({$ocupados}/{$limite})";
        
        $response = [
            'permite_encaixes' => true,
            'limite_total' => $limite,
            'encaixes_ocupados' => $ocupados,
            'encaixes_disponiveis' => $disponiveis,
            'pode_encaixar' => $pode_encaixar,
            'mensagem' => $mensagem
        ];
    }
    
    // 8. Output APENAS este JSON
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'erro' => $e->getMessage(),
        'permite_encaixes' => false,
        'limite_total' => 0,
        'encaixes_ocupados' => 0,
        'encaixes_disponiveis' => 0,
        'pode_encaixar' => false
    ], JSON_UNESCAPED_UNICODE);
}

// 9. Finalizar e enviar
if (ob_get_level()) {
    ob_end_flush();
}

// 10. Garantir que NADA mais será executado
exit;
?>