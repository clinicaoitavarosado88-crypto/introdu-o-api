<?php
// ============================================================================
// buscar_exames_convenio.php - Busca exames disponíveis para um convênio
// ============================================================================

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-cache');

// Suporte para linha de comando e GET
if (php_sapi_name() === 'cli') {
    parse_str(getenv('QUERY_STRING') ?: '', $_GET);
}

// Incluir conexão
include_once 'includes/connection.php';

$idconvenio = (int)($_GET['idconvenio'] ?? 0);
$busca = trim($_GET['busca'] ?? '');
$limit = (int)($_GET['limit'] ?? 50);

try {
    if (!$conn) {
        throw new Exception('Conexão não estabelecida');
    }
    
    if (!$idconvenio) {
        echo json_encode([
            'results' => [],
            'pagination' => ['more' => false],
            'total' => 0,
            'erro' => 'ID do convênio é obrigatório'
        ]);
        exit;
    }
    
    // Query para buscar exames do convênio
    $whereClause = "WHERE b.idconvenio = ? AND a.ai = 1";
    $params = [$idconvenio];
    
    if ($busca) {
        $whereClause .= " AND UPPER(a.exame) LIKE UPPER(?)";
        $busca_param = "%$busca%";
        $params[] = $busca_param;
    }
    
    $sql = "SELECT FIRST $limit 
                a.idexame, 
                a.exame,
                a.idperfil,
                b.pr_unit as valor_convenio, 
                b.valor_sindicato,
                CASE 
                    WHEN a.idperfil > 0 THEN 'PERFIL' 
                    ELSE 'EXAME' 
                END as tipo_item
            FROM lab_exames a
            JOIN lab_conveniostab_it b ON b.idexame = a.idexame 
            $whereClause
            ORDER BY a.exame";
    
    $stmt = ibase_prepare($conn, $sql);
    $result = ibase_execute($stmt, ...$params);
    
    if (!$result) {
        throw new Exception('Erro na query: ' . ibase_errmsg());
    }
    
    $exames = [];
    while ($row = ibase_fetch_assoc($result)) {
        $idexame = $row['IDEXAME'];
        $exame = $row['EXAME']; 
        $valor_convenio = $row['VALOR_CONVENIO'] ?? 0;
        $valor_sindicato = $row['VALOR_SINDICATO'] ?? 0;
        $tipo_item = $row['TIPO_ITEM'];
        $idperfil = $row['IDPERFIL'] ?? 0;
        
        // Determinar o valor a mostrar (sindicato tem prioridade se existir)
        $valor_display = ($valor_sindicato > 0) ? $valor_sindicato : $valor_convenio;
        $valor_formatado = 'R$ ' . number_format($valor_display, 2, ',', '.');
        
        // Texto para exibição
        $texto_completo = $exame . " - $valor_formatado";
        
        if ($tipo_item === 'PERFIL') {
            $texto_completo .= " [PERFIL]";
        }
        
        $exames[] = [
            'id' => $idexame,
            'text' => $texto_completo,
            'exame' => $exame,
            'valor' => $valor_display,
            'valor_formatado' => $valor_formatado,
            'tipo' => $tipo_item,
            'idperfil' => $idperfil,
            'data-exame' => $exame,
            'data-valor' => $valor_display,
            'data-tipo' => $tipo_item
        ];
    }
    
    ibase_free_result($result);
    
    // Contar total (para paginação)
    $sql_count = "SELECT COUNT(*) as total 
                  FROM lab_exames a
                  JOIN lab_conveniostab_it b ON b.idexame = a.idexame 
                  $whereClause";
    
    $stmt_count = ibase_prepare($conn, $sql_count);
    $result_count = ibase_execute($stmt_count, ...$params);
    $row_count = ibase_fetch_assoc($result_count);
    $total = $row_count['TOTAL'] ?? count($exames);
    
    echo json_encode([
        'results' => $exames,
        'pagination' => ['more' => count($exames) >= $limit],
        'total' => $total,
        'convenio_id' => $idconvenio,
        'busca' => $busca
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log('Erro em buscar_exames_convenio.php: ' . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'results' => [],
        'pagination' => ['more' => false],
        'total' => 0,
        'erro' => $e->getMessage()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
?>