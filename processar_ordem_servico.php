<?php
// ============================================================================
// processar_ordem_servico.php - Processa criação de Ordens de Serviço
// ============================================================================

error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

function debug_log($message) {
    error_log('[ORDEM_SERVICO] ' . date('Y-m-d H:i:s') . ' - ' . $message);
}

// Limpar qualquer output anterior
if (ob_get_level()) {
    ob_end_clean();
}
ob_start();

// Headers
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-cache, must-revalidate');

debug_log('=== INÍCIO processar_ordem_servico.php ===');

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
    // 1. COLETAR E VALIDAR DADOS DO FORMULÁRIO
    // ============================================================================
    
    debug_log('Dados recebidos via POST:');
    foreach ($_POST as $key => $value) {
        debug_log("  $key: \"$value\"");
    }
    
    // Dados básicos obrigatórios para OS
    $idpaciente = (int)($_POST['idpaciente'] ?? 0);
    $agendamento_id = (int)($_POST['agendamento_id'] ?? 0);
    $diaexame = trim($_POST['diaexame'] ?? '');
    $idposto = (int)($_POST['idposto'] ?? 0);
    $nm_posto = trim($_POST['nm_posto'] ?? '');
    $idmedico = (int)($_POST['idmedico'] ?? 0);
    $nm_medico = trim($_POST['nm_medico'] ?? '');
    $idunidade = (int)($_POST['idunidade'] ?? 0);
    $nm_unidade = trim($_POST['nm_unidade'] ?? '');
    
    // Campos de convênio (opcionais)
    $eh_convenio = $_POST['eh_convenio'] ?? false;
    $idconvenio = (int)($_POST['idconvenio'] ?? 0);
    $nm_convenio = trim($_POST['nm_convenio'] ?? '');
    $carteira = trim($_POST['carteira'] ?? '');
    $token = trim($_POST['token'] ?? '');
    $guia_convenio = trim($_POST['guia_convenio'] ?? '');
    $senha_autorizacao = trim($_POST['senha_autorizacao'] ?? '');
    $validade_senha = trim($_POST['validade_senha'] ?? '');
    
    // Outros campos
    $cartao_sus = trim($_POST['cartao_sus'] ?? '');
    $observacao = trim($_POST['observacao'] ?? '');
    $usuario = $_COOKIE['log_usuario'] ?? trim($_POST['usuario'] ?? 'SISTEMA');
    
    // Validação básica
    if (!$idpaciente) {
        throw new Exception('ID do paciente é obrigatório');
    }
    if (!$idposto) {
        throw new Exception('Posto/Clínica é obrigatório');
    }
    if (!$idmedico) {
        throw new Exception('Médico solicitante é obrigatório');
    }
    if (!$idunidade) {
        throw new Exception('Especialidade é obrigatória');
    }
    
    debug_log("Validação básica OK - Paciente: $idpaciente, Posto: $idposto, Médico: $idmedico");
    
    // ============================================================================
    // 1.1. VERIFICAR SE JÁ EXISTE OS PARA ESTE AGENDAMENTO
    // ============================================================================
    
    if ($agendamento_id) {
        $query_os_existente = "SELECT IDRESULTADO FROM LAB_RESULTADOS WHERE COD_ID = ?";
        $stmt_os_existente = ibase_prepare($conn, $query_os_existente);
        $result_os_existente = ibase_execute($stmt_os_existente, $agendamento_id);
        $os_existente = ibase_fetch_assoc($result_os_existente);
        
        if ($os_existente) {
            $numero_os_existente = $os_existente['IDRESULTADO'];
            debug_log("AVISO: Já existe OS $numero_os_existente para o agendamento $agendamento_id");
            
            ob_clean();
            echo json_encode([
                'status' => 'erro',
                'mensagem' => 'Já existe uma OS para este agendamento!',
                'numero_os_existente' => $numero_os_existente,
                'pode_visualizar' => true,
                'pdf_url' => "pdf050_t.php?idresultados=$numero_os_existente"
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        debug_log("Agendamento $agendamento_id verificado - não possui OS existente");
    }
    
    // ============================================================================
    // 2. BUSCAR DADOS DO PACIENTE
    // ============================================================================
    
    $query_paciente = "SELECT PACIENTE, CPF, FONE1, EMAIL, ANIVERSARIO 
                       FROM LAB_PACIENTES 
                       WHERE IDPACIENTE = ?";
    $stmt_paciente = ibase_prepare($conn, $query_paciente);
    $result_paciente = ibase_execute($stmt_paciente, $idpaciente);
    $dados_paciente = ibase_fetch_assoc($result_paciente);
    
    if (!$dados_paciente) {
        throw new Exception("Paciente não encontrado: ID $idpaciente");
    }
    
    $nome_paciente = $dados_paciente['PACIENTE'];
    debug_log("Paciente encontrado: $nome_paciente");
    
    // ============================================================================
    // 3. INSERIR OS NO SISTEMA LAB_RESULTADOS SEGUINDO PADRÃO EXISTENTE
    // ============================================================================
    
    debug_log('Inserindo OS na tabela LAB_RESULTADOS...');
    
    // Preparar dados seguindo exatamente o padrão do processar_os.php existente
    $data_formatada = date('Y-m-d', strtotime($diaexame));  // Formato Y-m-d
    $hora_exame = date('H:i:s');
    $data_gravacao = date('m/d/Y H:i:s');  // Formato americano para DAT_GRAVAC
    $usuario_sistema = $usuario ?: 'SISTEMA';
    
    // Campos adicionais padrão
    $id_uniq = substr(md5(uniqid()), 0, 13);
    $CaracteresAceitos = 'abcdxywzABCDZYWZ0123456789';
    $max = strlen($CaracteresAceitos) - 1;
    $senha_net = '';
    for ($i = 0; $i < 8; $i++) {
        $senha_net .= $CaracteresAceitos[mt_rand(0, $max)];
    }
    
    // Query de inserção seguindo exatamente o padrão existente com RETURNING
    // Incluir cod_id se houver agendamento vinculado
    $sql_insert = "INSERT INTO lab_resultados (
        diaexame, horaexame, usu_gravac, dat_gravac, idunidade, idpaciente, idconvenio,
        idposto, idmedico, observacao, id_uniq, senha_net, autorizacao, ai, idstatus" . 
        ($agendamento_id ? ", cod_id" : "") . "
    ) VALUES (
        '$data_formatada', '$hora_exame', '$usuario_sistema', '$data_gravacao', $idunidade, $idpaciente, " . ($eh_convenio ? $idconvenio : 1) . ",
        $idposto, $idmedico, '$observacao', '$id_uniq', '$senha_net', current_date, 1, 1" . 
        ($agendamento_id ? ", $agendamento_id" : "") . "
    ) RETURNING idresultado";
    
    debug_log("Executando query de inserção...");
    debug_log("Query: " . $sql_insert);
    
    $result_insert = ibase_query($conn, $sql_insert);
    
    if (!$result_insert) {
        $error_msg = ibase_errmsg();
        debug_log("Erro na execução: " . $error_msg);
        throw new Exception('Erro ao inserir OS na LAB_RESULTADOS: ' . $error_msg);
    }
    
    // Recuperar o idresultado gerado (este é o número da OS)
    $row_resultado = ibase_fetch_assoc($result_insert);
    $numero_os = $row_resultado['IDRESULTADO'];
    
    // Confirmar transação
    if (!ibase_commit($conn)) {
        $error_msg = ibase_errmsg();
        debug_log("Erro no commit: " . $error_msg);
        throw new Exception('Erro ao confirmar transação: ' . $error_msg);
    }
    
    debug_log("OS $numero_os inserida com sucesso na tabela LAB_RESULTADOS");
    
    // ============================================================================
    // 4. REGISTRAR AUDITORIA COMPLETA
    // ============================================================================
    
    // Preparar dados completos da OS para auditoria
    $dados_os = [
        'numero_os' => $numero_os,
        'paciente_id' => $idpaciente,
        'paciente_nome' => $nome_paciente,
        'agendamento_id' => $agendamento_id,
        'data_abertura' => $diaexame,
        'posto_id' => $idposto,
        'posto_nome' => $nm_posto,
        'medico_id' => $idmedico,
        'medico_nome' => $nm_medico,
        'especialidade_id' => $idunidade,
        'especialidade_nome' => $nm_unidade,
        'eh_convenio' => $eh_convenio ? 'S' : 'N',
        'convenio_id' => $eh_convenio ? $idconvenio : null,
        'convenio_nome' => $eh_convenio ? $nm_convenio : null,
        'carteira' => $eh_convenio ? $carteira : null,
        'token' => $eh_convenio ? $token : null,
        'guia_convenio' => $eh_convenio ? $guia_convenio : null,
        'senha_autorizacao' => $eh_convenio ? $senha_autorizacao : null,
        'validade_senha' => $eh_convenio ? $validade_senha : null,
        'cartao_sus' => $cartao_sus,
        'observacoes' => $observacao,
        'status' => 'CRIADA',
        'data_criacao' => date('Y-m-d H:i:s'),
        'usuario_criacao' => $usuario
    ];
    
    debug_log('Dados da OS preparados para auditoria');
    
    // Registrar na auditoria com ação específica para OS
    $params_auditoria = [
        'acao' => 'CRIAR_OS',
        'usuario' => $usuario,
        'tabela_afetada' => 'ORDENS_SERVICO',
        'agendamento_id' => $agendamento_id ?: null,
        'numero_agendamento' => $numero_os,
        'observacoes' => "OS criada: $numero_os para paciente $nome_paciente. Posto: $nm_posto, Médico: $nm_medico, Especialidade: $nm_unidade" . 
                        ($eh_convenio ? " | Convênio: $nm_convenio" : " | PARTICULAR"),
        'dados_novos' => json_encode($dados_os, JSON_UNESCAPED_UNICODE),
        'paciente_nome' => $nome_paciente,
        'status_novo' => 'OS_CRIADA'
    ];
    
    $auditoria_ok = registrarAuditoria($conn, $params_auditoria);
    
    if (!$auditoria_ok) {
        debug_log('AVISO: Falha ao registrar auditoria da OS');
    } else {
        debug_log('Auditoria da OS registrada com sucesso');
    }
    
    // ============================================================================
    // 5. REGISTRAR NO HISTÓRICO DO AGENDAMENTO (SE HOUVER AGENDAMENTO VINCULADO)
    // ============================================================================
    
    if ($agendamento_id) {
        // Buscar dados do agendamento para o histórico
        $query_agendamento = "SELECT * FROM AGENDAMENTOS WHERE ID = ?";
        $stmt_agendamento = ibase_prepare($conn, $query_agendamento);
        $result_agendamento = ibase_execute($stmt_agendamento, $agendamento_id);
        $dados_agendamento = ibase_fetch_assoc($result_agendamento);
        
        if ($dados_agendamento) {
            // Preparar dados do agendamento com a informação da OS criada
            $dados_agendamento_os = $dados_agendamento;
            $dados_agendamento_os['observacoes_os'] = "OS $numero_os criada em " . date('d/m/Y H:i:s');
            $dados_agendamento_os['numero_os_vinculada'] = $numero_os;
            
            $params_hist_agendamento = [
                'acao' => 'VINCULAR_OS',
                'usuario' => $usuario,
                'agendamento_id' => $agendamento_id,
                'observacoes' => "OS $numero_os vinculada ao agendamento",
                'dados_novos' => json_encode($dados_agendamento_os, JSON_UNESCAPED_UNICODE),
                'paciente_nome' => $nome_paciente,
                'status_novo' => 'COM_OS_VINCULADA'
            ];
            
            $hist_agendamento_ok = registrarAuditoria($conn, $params_hist_agendamento);
            
            if ($hist_agendamento_ok) {
                debug_log("Histórico do agendamento $agendamento_id atualizado com OS $numero_os");
            } else {
                debug_log("AVISO: Falha ao registrar histórico do agendamento $agendamento_id");
            }
        }
    }
    
    // ============================================================================
    // 6. RESPOSTA DE SUCESSO
    // ============================================================================
    
    $resposta = [
        'status' => 'sucesso',
        'mensagem' => 'Ordem de Serviço criada com sucesso!',
        'numero_os' => $numero_os,
        'dados' => [
            'paciente' => $nome_paciente,
            'posto' => $nm_posto,
            'medico' => $nm_medico,
            'especialidade' => $nm_unidade,
            'convenio' => $eh_convenio ? $nm_convenio : 'PARTICULAR',
            'data_criacao' => date('d/m/Y H:i:s'),
            'agendamento_vinculado' => $agendamento_id ?: null
        ],
        'auditoria_registrada' => $auditoria_ok,
        'historico_agendamento' => isset($hist_agendamento_ok) ? $hist_agendamento_ok : null
    ];
    
    debug_log("OS $numero_os criada com sucesso para paciente $nome_paciente");
    
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

debug_log('=== FIM processar_ordem_servico.php ===');
?>