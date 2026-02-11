<?php
// ============================================================================
// buscar_os_agendamento.php - Busca OS vinculada a um agendamento
// ============================================================================

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-cache');

// Suporte para linha de comando e GET
if (php_sapi_name() === 'cli') {
    parse_str(getenv('QUERY_STRING') ?: '', $_GET);
}

// Incluir conexão
include_once 'includes/connection.php';

$agendamento_id = (int)($_GET['agendamento_id'] ?? 0);
$numero_os = (int)($_GET['numero_os'] ?? 0);

try {
    if (!$conn) {
        throw new Exception('Conexão não estabelecida');
    }
    
    if (!$agendamento_id && !$numero_os) {
        echo json_encode([
            'tem_os' => false,
            'erro' => 'ID do agendamento ou número da OS é obrigatório'
        ]);
        exit;
    }
    
    // Buscar OS (por agendamento ou número da OS)
    if ($numero_os) {
        $sql = "SELECT lr.IDRESULTADO as numero_os,
                       lr.DIAEXAME,
                       lr.HORAEXAME,
                       lr.USU_GRAVAC,
                       lr.DAT_GRAVAC,
                       lr.ENVIADO_LAB,
                       lr.COD_ID as agendamento_id,
                       p.PACIENTE as nome_paciente,
                       COUNT(li.ID) as total_exames,
                       SUM(li.PA_SA * li.QUANT) as valor_total
                FROM LAB_RESULTADOS lr
                LEFT JOIN LAB_PACIENTES p ON p.IDPACIENTE = lr.IDPACIENTE
                LEFT JOIN LAB_ITEMRESULTADOS li ON li.IDRESULTADOS = lr.IDRESULTADO
                WHERE lr.IDRESULTADO = ?
                GROUP BY lr.IDRESULTADO, lr.DIAEXAME, lr.HORAEXAME, lr.USU_GRAVAC, 
                         lr.DAT_GRAVAC, lr.ENVIADO_LAB, lr.COD_ID, p.PACIENTE";
        
        $stmt = ibase_prepare($conn, $sql);
        $result = ibase_execute($stmt, $numero_os);
    } else {
        $sql = "SELECT lr.IDRESULTADO as numero_os,
                       lr.DIAEXAME,
                       lr.HORAEXAME,
                       lr.USU_GRAVAC,
                       lr.DAT_GRAVAC,
                       lr.ENVIADO_LAB,
                       p.PACIENTE as nome_paciente,
                       COUNT(li.ID) as total_exames,
                       SUM(li.PA_SA * li.QUANT) as valor_total
                FROM LAB_RESULTADOS lr
                LEFT JOIN LAB_PACIENTES p ON p.IDPACIENTE = lr.IDPACIENTE
                LEFT JOIN LAB_ITEMRESULTADOS li ON li.IDRESULTADOS = lr.IDRESULTADO
                WHERE lr.COD_ID = ?
                GROUP BY lr.IDRESULTADO, lr.DIAEXAME, lr.HORAEXAME, lr.USU_GRAVAC, 
                         lr.DAT_GRAVAC, lr.ENVIADO_LAB, p.PACIENTE";
        
        $stmt = ibase_prepare($conn, $sql);
        $result = ibase_execute($stmt, $agendamento_id);
    }
    
    if (!$result) {
        throw new Exception('Erro na query: ' . ibase_errmsg());
    }
    
    $dados_os = ibase_fetch_assoc($result);
    
    if ($dados_os) {
        $numero_os = $dados_os['NUMERO_OS'];
        $valor_total = floatval($dados_os['VALOR_TOTAL'] ?? 0);
        $total_exames = intval($dados_os['TOTAL_EXAMES'] ?? 0);
        $enviado_lab = $dados_os['ENVIADO_LAB'];
        
        // Determinar status da OS
        $status_os = 'CRIADA';
        if ($enviado_lab == 1) {
            $status_os = 'COMPLETA';
        }
        
        echo json_encode([
            'tem_os' => true,
            'numero_os' => $numero_os,
            'nome_paciente' => $dados_os['NOME_PACIENTE'],
            'data_criacao' => $dados_os['DIAEXAME'],
            'hora_criacao' => $dados_os['HORAEXAME'],
            'usuario_criacao' => $dados_os['USU_GRAVAC'],
            'total_exames' => $total_exames,
            'valor_total' => $valor_total,
            'valor_formatado' => 'R$ ' . number_format($valor_total, 2, ',', '.'),
            'status' => $status_os,
            'pdf_url' => "pdf050_t.php?idresultados=$numero_os",
            'agendamento_id' => $agendamento_id
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
    } else {
        echo json_encode([
            'tem_os' => false,
            'agendamento_id' => $agendamento_id
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
    
    ibase_free_result($result);
    
} catch (Exception $e) {
    error_log('Erro em buscar_os_agendamento.php: ' . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'tem_os' => false,
        'erro' => $e->getMessage(),
        'agendamento_id' => $agendamento_id ?? 0
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
?>