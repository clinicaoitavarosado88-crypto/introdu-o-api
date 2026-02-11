<?php
// ============================================================================
// adicionar_exames_os.php - Adiciona exames a uma OS existente
// ============================================================================

error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

function debug_log($message) {
    error_log('[ADICIONAR_EXAMES_OS] ' . date('Y-m-d H:i:s') . ' - ' . $message);
}

// Limpar qualquer output anterior
if (ob_get_level()) {
    ob_end_clean();
}
ob_start();

// Headers
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-cache, must-revalidate');

debug_log('=== INÍCIO adicionar_exames_os.php ===');

// Suporte para linha de comando
if (php_sapi_name() === 'cli') {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    parse_str(getenv('POST_DATA') ?: '', $_POST);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_clean();
    die('{"status":"erro","mensagem":"Método não permitido"}');
}

try {
    // Verificar arquivo de conexão
    if (!file_exists('includes/connection.php')) {
        throw new Exception('Arquivo de conexão não encontrado');
    }
    
    // Incluir conexão e auditoria sem output
    ob_start();
    include_once 'includes/connection.php';
    include_once 'includes/auditoria.php';
    ob_end_clean();
    
    if (!isset($conn)) {
        throw new Exception('Conexão não estabelecida');
    }
    
    debug_log('Conexão estabelecida com sucesso');
    
    // ============================================================================
    // 1. COLETAR E VALIDAR DADOS
    // ============================================================================
    
    debug_log('Dados recebidos via POST:');
    foreach ($_POST as $key => $value) {
        if (is_array($value)) {
            debug_log("  $key: " . json_encode($value));
        } else {
            debug_log("  $key: \"$value\"");
        }
    }
    
    $numero_os = (int)($_POST['numero_os'] ?? 0);
    $exames = $_POST['exames'] ?? [];
    $usuario = $_COOKIE['log_usuario'] ?? trim($_POST['usuario'] ?? 'SISTEMA');
    
    // Validação básica
    if (!$numero_os) {
        throw new Exception('Número da OS é obrigatório');
    }
    
    if (empty($exames) || !is_array($exames)) {
        throw new Exception('Lista de exames é obrigatória');
    }
    
    debug_log("Processando OS: $numero_os com " . count($exames) . " exames");
    
    // ============================================================================
    // 2. BUSCAR DADOS DA OS EXISTENTE
    // ============================================================================
    
    $query_os = "SELECT * FROM LAB_RESULTADOS WHERE IDRESULTADO = ?";
    $stmt_os = ibase_prepare($conn, $query_os);
    $result_os = ibase_execute($stmt_os, $numero_os);
    $dados_os = ibase_fetch_assoc($result_os);
    
    if (!$dados_os) {
        throw new Exception("OS não encontrada: $numero_os");
    }
    
    $idpaciente = $dados_os['IDPACIENTE'];
    $idconvenio = $dados_os['IDCONVENIO'];
    $diaexame = $dados_os['DIAEXAME'];
    $idposto = $dados_os['IDPOSTO'];
    $idmedico = $dados_os['IDMEDICO'];
    $agendamento_id = $dados_os['COD_ID'] ?? null; // Campo que vincula ao agendamento
    
    debug_log("OS encontrada - Paciente: $idpaciente, Convênio: $idconvenio, Agendamento: " . ($agendamento_id ?: 'N/A'));
    
    // Buscar dados do paciente
    $query_paciente = "SELECT PACIENTE, SEXO, ANIVERSARIO FROM LAB_PACIENTES WHERE IDPACIENTE = ?";
    $stmt_paciente = ibase_prepare($conn, $query_paciente);
    $result_paciente = ibase_execute($stmt_paciente, $idpaciente);
    $dados_paciente = ibase_fetch_assoc($result_paciente);
    
    if (!$dados_paciente) {
        throw new Exception("Paciente não encontrado: $idpaciente");
    }
    
    $nome_paciente = $dados_paciente['PACIENTE'];
    $sexo = $dados_paciente['SEXO'];
    $aniversario = $dados_paciente['ANIVERSARIO'];
    $data_sem_traco = str_replace("-", "", $aniversario);
    
    debug_log("Paciente: $nome_paciente");
    
    // ============================================================================
    // 3. PROCESSAR CADA EXAME
    // ============================================================================
    
    $exames_adicionados = [];
    $total_valor = 0;
    
    foreach ($exames as $exame_data) {
        $idexame = (int)($exame_data['id'] ?? 0);
        $quantidade = (int)($exame_data['quantidade'] ?? 1);
        
        if (!$idexame) {
            debug_log("AVISO: Exame inválido ignorado - ID: $idexame");
            continue;
        }
        
        debug_log("Processando exame ID: $idexame, Quantidade: $quantidade");
        
        // Buscar dados do exame
        $query_exame = "SELECT e.EXAME, e.IDUNIDADE, e.IDPERFIL, u.MODALIDADE 
                        FROM LAB_EXAMES e
                        LEFT JOIN LAB_UNIDADES u ON u.ID = e.IDUNIDADE 
                        WHERE e.IDEXAME = ? AND e.AI = 1";
        $stmt_exame = ibase_prepare($conn, $query_exame);
        $result_exame = ibase_execute($stmt_exame, $idexame);
        $dados_exame = ibase_fetch_assoc($result_exame);
        
        if (!$dados_exame) {
            debug_log("AVISO: Exame não encontrado - ID: $idexame");
            continue;
        }
        
        $nome_exame = $dados_exame['EXAME'];
        $idunidade = $dados_exame['IDUNIDADE'];
        $idperfil = $dados_exame['IDPERFIL'] ?: 'NULL';
        $modalidade = $dados_exame['MODALIDADE'] ?: '';
        
        // Verificar cobertura do convênio
        $query_convenio = "SELECT PR_UNIT FROM LAB_CONVENIOSTAB_IT 
                           WHERE IDCONVENIO = ? AND IDEXAME = ? AND AI = 1";
        $stmt_convenio = ibase_prepare($conn, $query_convenio);
        $result_convenio = ibase_execute($stmt_convenio, $idconvenio, $idexame);
        $dados_convenio = ibase_fetch_assoc($result_convenio);
        
        if (!$dados_convenio) {
            debug_log("AVISO: Exame $nome_exame não coberto pelo convênio $idconvenio");
            continue;
        }
        
        $pr_unit = $dados_convenio['PR_UNIT'];
        
        if ($pr_unit <= 0) {
            debug_log("AVISO: Exame $nome_exame com preço zerado - não adicionado");
            continue;
        }
        
        // Inserir na LAB_ITEMRESULTADOS seguindo padrão existente
        $dat_gravac = date('m/d/Y H:i:s');
        
        $sql_insert_item = "INSERT INTO lab_itemresultados (
            idresultados, numeroguia, quant, diaexame, idexame,
            usu_gravac, dat_gravac, ai, idunidade, idperfil, 
            situacao, entregue, pa_sa
        ) VALUES (
            $numero_os, $numero_os, $quantidade, '$diaexame', $idexame,
            '$usuario', '$dat_gravac', 1, $idunidade, $idperfil,
            'N', 'N', $pr_unit
        )";
        
        debug_log("Inserindo exame: $nome_exame");
        debug_log("Query: $sql_insert_item");
        
        $result_insert = ibase_query($conn, $sql_insert_item);
        
        if (!$result_insert) {
            $error_msg = ibase_errmsg();
            debug_log("ERRO ao inserir exame $nome_exame: $error_msg");
            throw new Exception("Erro ao adicionar exame $nome_exame: $error_msg");
        }
        
        $exames_adicionados[] = [
            'id' => $idexame,
            'nome' => $nome_exame,
            'quantidade' => $quantidade,
            'valor_unitario' => $pr_unit,
            'valor_total' => $pr_unit * $quantidade
        ];
        
        $total_valor += ($pr_unit * $quantidade);
        
        debug_log("Exame $nome_exame adicionado com sucesso");
    }
    
    // ============================================================================
    // 3.1. ATUALIZAR LAB_RESULTADOS - MARCAR OS COMO COMPLETA
    // ============================================================================
    
    if (!empty($exames_adicionados)) {
        $sql_update_os = "UPDATE lab_resultados SET 
                          enviado_lab = 1,
                          usu_gravac = '$usuario',
                          dat_gravac = '" . date('m/d/Y H:i:s') . "'
                          WHERE idresultado = $numero_os";
        
        debug_log("Atualizando OS como completa: $sql_update_os");
        
        $result_update = ibase_query($conn, $sql_update_os);
        if (!$result_update) {
            $error_msg = ibase_errmsg();
            debug_log("AVISO: Erro ao atualizar OS: $error_msg");
        } else {
            debug_log("OS $numero_os marcada como completa");
        }
        
        // ============================================================================
        // 3.2. VINCULAR NÚMERO DA OS AO AGENDAMENTO (SE HOUVER)
        // ============================================================================
        
        if ($agendamento_id) {
            $sql_update_agendamento = "UPDATE AGENDAMENTOS SET 
                                       observacoes = COALESCE(observacoes, '') || ' | OS: $numero_os finalizada em " . date('d/m/Y H:i:s') . "'
                                       WHERE ID = $agendamento_id";
            
            debug_log("Vinculando OS finalizada ao agendamento: $sql_update_agendamento");
            
            $result_update_agend = ibase_query($conn, $sql_update_agendamento);
            if (!$result_update_agend) {
                $error_msg = ibase_errmsg();
                debug_log("AVISO: Erro ao vincular OS ao agendamento: $error_msg");
            } else {
                debug_log("OS $numero_os vinculada ao agendamento $agendamento_id");
            }
        }
    }
    
    // Confirmar transação
    if (!ibase_commit($conn)) {
        $error_msg = ibase_errmsg();
        debug_log("Erro no commit: $error_msg");
        throw new Exception('Erro ao confirmar transação: ' . $error_msg);
    }
    
    debug_log("Transação confirmada - " . count($exames_adicionados) . " exames adicionados");
    
    // ============================================================================
    // 4. REGISTRAR AUDITORIA
    // ============================================================================
    
    if (!empty($exames_adicionados)) {
        $dados_auditoria = [
            'numero_os' => $numero_os,
            'paciente_nome' => $nome_paciente,
            'exames_adicionados' => $exames_adicionados,
            'total_exames' => count($exames_adicionados),
            'valor_total' => $total_valor,
            'usuario' => $usuario,
            'data_adicao' => date('Y-m-d H:i:s')
        ];
        
        $params_auditoria = [
            'acao' => 'ADICIONAR_EXAMES_OS',
            'usuario' => $usuario,
            'tabela_afetada' => 'LAB_ITEMRESULTADOS',
            'numero_agendamento' => $numero_os,
            'observacoes' => "Adicionados " . count($exames_adicionados) . " exames na OS $numero_os para $nome_paciente. Valor total: R$ " . number_format($total_valor, 2, ',', '.'),
            'dados_novos' => json_encode($dados_auditoria, JSON_UNESCAPED_UNICODE),
            'paciente_nome' => $nome_paciente,
            'status_novo' => 'EXAMES_ADICIONADOS'
        ];
        
        $auditoria_ok = registrarAuditoria($conn, $params_auditoria);
        
        if ($auditoria_ok) {
            debug_log('Auditoria registrada com sucesso');
        } else {
            debug_log('AVISO: Falha ao registrar auditoria');
        }
    }
    
    // ============================================================================
    // 5. RESPOSTA DE SUCESSO
    // ============================================================================
    
    $resposta = [
        'status' => 'sucesso',
        'mensagem' => count($exames_adicionados) . ' exame(s) adicionado(s) com sucesso à OS!',
        'numero_os' => $numero_os,
        'exames_adicionados' => $exames_adicionados,
        'total_exames' => count($exames_adicionados),
        'valor_total' => $total_valor,
        'valor_formatado' => 'R$ ' . number_format($total_valor, 2, ',', '.'),
        'auditoria_registrada' => $auditoria_ok ?? false,
        'agendamento_vinculado' => $agendamento_id,
        'os_completa' => true,
        'pdf_url' => "pdf050_t.php?idresultados=$numero_os"
    ];
    
    debug_log("Exames adicionados com sucesso na OS $numero_os");
    
    ob_clean();
    echo json_encode($resposta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    debug_log('ERRO: ' . $e->getMessage());
    
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'status' => 'erro',
        'mensagem' => $e->getMessage(),
        'detalhes' => 'Verifique os logs do servidor para mais informações'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

debug_log('=== FIM adicionar_exames_os.php ===');
?>