<?php
// ============================================================================
// processar_encaixe.php - VERSÃO COM CORREÇÃO PARA HORÁRIO ESPECÍFICO
// ✅ Mantém sua versão original + corrige PACIENTE_ID + HORÁRIO ESPECÍFICO
// ============================================================================

error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

function debug_log($message) {
    error_log('[ENCAIXE_ENDERECO] ' . date('Y-m-d H:i:s') . ' - ' . $message);
}

// Limpar qualquer output anterior
if (ob_get_level()) {
    ob_end_clean();
}
ob_start();

// Headers
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-cache, must-revalidate');

debug_log('=== INÍCIO processar_encaixe.php - VERSÃO COM CORREÇÃO DE HORÁRIO ===');

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
    // 1. COLETAR E VALIDAR DADOS DO FORMULÁRIO (INCLUINDO HORÁRIO ESPECÍFICO)
    // ============================================================================
    
    debug_log('Dados recebidos via POST:');
    foreach ($_POST as $key => $value) {
        debug_log("  $key: \"$value\"");
    }
    
    // Dados básicos obrigatórios
    $agenda_id = (int)($_POST['agenda_id'] ?? 0);
    $data_agendamento = trim($_POST['data_agendamento'] ?? '');
    $nome_paciente = trim($_POST['nome_paciente'] ?? '');
    $telefone_paciente = trim($_POST['telefone_paciente'] ?? '');
    $convenio_id = (int)($_POST['convenio_id'] ?? 0);
    $observacoes = trim($_POST['observacoes'] ?? '');
    
    // ✅ Especialidade selecionada (para médicos com múltiplas especialidades)
    $especialidade_id = (int)($_POST['especialidade_id'] ?? 0);
    debug_log('Especialidade ID selecionada: ' . $especialidade_id);
    
    // ✅ NOVO: Capturar IDs dos exames selecionados (múltiplos)
    $exames_ids_raw = trim($_POST['exames_ids'] ?? '');
    $exames_ids = [];
    
    if (!empty($exames_ids_raw)) {
        // Converter string "1,2,3" em array de inteiros
        $exames_ids = array_map('intval', array_filter(explode(',', $exames_ids_raw)));
        $exames_ids = array_unique($exames_ids); // Remover duplicatas
    }
    
    debug_log('IDs dos exames recebidos: ' . $exames_ids_raw);
    debug_log('Exames processados: ' . json_encode($exames_ids));
    debug_log('Quantidade de exames: ' . count($exames_ids));
    
    // ✅ NOVO: PROCESSAMENTO DE HORÁRIO ESPECÍFICO
    debug_log('=== PROCESSAMENTO DE HORÁRIO ESPECÍFICO ===');
    
    $horario_especifico_enviado = false;
    $hora_agendamento_final = null;
    
    // Log de todos os campos relacionados a horário recebidos
    debug_log('Campos de horário recebidos:');
    debug_log('- tipo_horario: ' . ($_POST['tipo_horario'] ?? 'não enviado'));
    debug_log('- horario_agendamento: ' . ($_POST['horario_agendamento'] ?? 'não enviado'));
    debug_log('- horario_especifico: ' . ($_POST['horario_especifico'] ?? 'não enviado'));
    debug_log('- hora_agendamento: ' . ($_POST['hora_agendamento'] ?? 'não enviado'));
    debug_log('- horario_digitado: ' . ($_POST['horario_digitado'] ?? 'não enviado'));
    
    // Verificar se foi enviado horário específico
    if (isset($_POST['tipo_horario']) && $_POST['tipo_horario'] === 'especifico') {
        debug_log('🎯 TIPO HORÁRIO ESPECÍFICO DETECTADO');
        
        // Tentar capturar o horário de várias formas
        $horario_candidatos = [
            $_POST['horario_agendamento'] ?? null,
            $_POST['horario_especifico'] ?? null,
            $_POST['horario_digitado'] ?? null,
            $_POST['hora_agendamento'] ?? null
        ];
        
        debug_log('Analisando candidatos a horário:');
        foreach ($horario_candidatos as $i => $horario_candidato) {
            debug_log("Candidato $i: " . ($horario_candidato ?? 'null'));
            
            if (!empty($horario_candidato) && $horario_candidato !== 'undefined') {
                // Remover segundos se existirem
                $horario_limpo = substr($horario_candidato, 0, 5);
                
                // Validar formato HH:MM
                if (preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $horario_limpo)) {
                    $hora_agendamento_final = $horario_limpo . ':00'; // Adicionar segundos
                    $horario_especifico_enviado = true;
                    debug_log("✅ HORÁRIO ESPECÍFICO CAPTURADO: $hora_agendamento_final");
                    break;
                } else {
                    debug_log("❌ Formato inválido para: $horario_limpo");
                }
            }
        }
    }
    
    // Se não capturou horário específico, gerar automático
    if (!$horario_especifico_enviado || empty($hora_agendamento_final)) {
        $hora_base = time();
        $incremento_segundos = rand(1, 59);
        $hora_agendamento_final = date('H:i:s', $hora_base + $incremento_segundos);
        debug_log("🕐 HORÁRIO AUTOMÁTICO GERADO: $hora_agendamento_final");
    } else {
        debug_log("🎯 USANDO HORÁRIO ESPECÍFICO: $hora_agendamento_final");
    }
    
    debug_log('=== FIM PROCESSAMENTO DE HORÁRIO ===');
    
    // Flags de controle - ANÁLISE DETALHADA
    $usar_paciente_existente_raw = $_POST['usar_paciente_existente'] ?? '';
    $cadastrar_paciente_raw = $_POST['cadastrar_paciente'] ?? '';
    $paciente_selecionado_id_raw = $_POST['paciente_selecionado_id'] ?? '';
    $paciente_id_raw = $_POST['paciente_id'] ?? '';
    
    debug_log('=== ANÁLISE DETALHADA DOS FLAGS ===');
    debug_log("usar_paciente_existente (raw): '$usar_paciente_existente_raw'");
    debug_log("cadastrar_paciente (raw): '$cadastrar_paciente_raw'");
    debug_log("paciente_selecionado_id (raw): '$paciente_selecionado_id_raw'");
    debug_log("paciente_id (raw): '$paciente_id_raw'");
    
    $usar_paciente_existente = ($usar_paciente_existente_raw === 'true');
    $cadastrar_paciente = ($cadastrar_paciente_raw === 'true');
    
    // ✅ CORREÇÃO PRINCIPAL: Capturar corretamente o ID do paciente existente
    $paciente_selecionado_id = 0;
    if (!empty($paciente_selecionado_id_raw) && $paciente_selecionado_id_raw !== 'NULL') {
        $paciente_selecionado_id = (int)$paciente_selecionado_id_raw;
    } elseif (!empty($paciente_id_raw) && $paciente_id_raw !== 'NULL') {
        $paciente_selecionado_id = (int)$paciente_id_raw;
    }
    
    debug_log("usar_paciente_existente (boolean): " . ($usar_paciente_existente ? 'TRUE' : 'FALSE'));
    debug_log("cadastrar_paciente (boolean): " . ($cadastrar_paciente ? 'TRUE' : 'FALSE'));
    debug_log("paciente_selecionado_id (int): $paciente_selecionado_id");
    
    // ✅ NOVOS CAMPOS: Dados para cadastro completo de paciente - NOMES CORRIGIDOS
    $cpf_paciente = trim($_POST['cpf_paciente'] ?? '');
    $data_nascimento = trim($_POST['data_nascimento'] ?? '');
    $sexo = trim($_POST['sexo'] ?? '');
    $email_paciente = trim($_POST['email_paciente'] ?? '');
    $rg = trim($_POST['rg'] ?? '');
    $orgao_emissor = trim($_POST['orgao_emissor'] ?? '');
    
    // ✅ CAMPOS DE ENDEREÇO COMPLETO - NOMES CORRIGIDOS
    $cep = trim($_POST['cep'] ?? '');
    $endereco = trim($_POST['endereco'] ?? '');
    $numero = trim($_POST['numero'] ?? '');
    $complemento = trim($_POST['complemento'] ?? '');
    $bairro = trim($_POST['bairro'] ?? '');
    $cidade = trim($_POST['cidade'] ?? '');
    $uf = trim($_POST['uf'] ?? '');
    
    debug_log('=== CAMPOS DE ENDEREÇO E CADASTRO RECEBIDOS ===');
    debug_log("cpf_paciente: '$cpf_paciente'");
    debug_log("data_nascimento: '$data_nascimento'");
    debug_log("email_paciente: '$email_paciente'");
    debug_log("cep: '$cep'");
    debug_log("endereco: '$endereco'");
    debug_log("numero: '$numero'");
    debug_log("complemento: '$complemento'");
    debug_log("bairro: '$bairro'");
    debug_log("cidade: '$cidade'");
    debug_log("uf: '$uf'");
    
    // Validações básicas
    if ($agenda_id <= 0) {
        throw new Exception('ID da agenda inválido');
    }
    
    if (empty($data_agendamento) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_agendamento)) {
        throw new Exception('Data de agendamento inválida');
    }
    
    if (empty($nome_paciente)) {
        throw new Exception('Nome do paciente é obrigatório');
    }
    
    if (empty($telefone_paciente)) {
        throw new Exception('Telefone do paciente é obrigatório');
    }
    
    if ($convenio_id <= 0) {
        throw new Exception('Convênio é obrigatório');
    }
    
    // ============================================================================
    // 2. VERIFICAR ESTRUTURA DAS TABELAS
    // ============================================================================
    
    function campo_existe_na_tabela($conn, $tabela, $campo) {
        try {
            $query = "SELECT COUNT(*) as EXISTE FROM RDB\$RELATION_FIELDS 
                      WHERE RDB\$RELATION_NAME = ? AND RDB\$FIELD_NAME = ?";
            $stmt = ibase_prepare($conn, $query);
            $result = ibase_execute($stmt, strtoupper($tabela), strtoupper($campo));
            $row = ibase_fetch_assoc($result);
            return $row['EXISTE'] > 0;
        } catch (Exception $e) {
            return false;
        }
    }
    
    // Verificar campos na tabela AGENDAMENTOS
    $tem_hora_agendamento = campo_existe_na_tabela($conn, 'AGENDAMENTOS', 'HORA_AGENDAMENTO');
    $tem_nome_paciente = campo_existe_na_tabela($conn, 'AGENDAMENTOS', 'NOME_PACIENTE');
    $tem_telefone_paciente = campo_existe_na_tabela($conn, 'AGENDAMENTOS', 'TELEFONE_PACIENTE');
    $tem_tipo_agendamento = campo_existe_na_tabela($conn, 'AGENDAMENTOS', 'TIPO_AGENDAMENTO');
    $tem_exame_id = campo_existe_na_tabela($conn, 'AGENDAMENTOS', 'EXAME_ID');
    $tem_exames_ids = campo_existe_na_tabela($conn, 'AGENDAMENTOS', 'EXAMES_IDS');
    
    // ✅ VERIFICAR CAMPOS NA TABELA LAB_PACIENTES PARA ENDEREÇO
    $tem_endereco = campo_existe_na_tabela($conn, 'LAB_PACIENTES', 'ENDERECO');
    $tem_numero = campo_existe_na_tabela($conn, 'LAB_PACIENTES', 'NUMERO');
    $tem_complemento = campo_existe_na_tabela($conn, 'LAB_PACIENTES', 'COMPLEMENTO');
    $tem_bairro = campo_existe_na_tabela($conn, 'LAB_PACIENTES', 'BAIRRO');
    $tem_cidade = campo_existe_na_tabela($conn, 'LAB_PACIENTES', 'CIDADE');
    $tem_uf = campo_existe_na_tabela($conn, 'LAB_PACIENTES', 'UF');
    $tem_cep = campo_existe_na_tabela($conn, 'LAB_PACIENTES', 'CEP');
    
    debug_log("Campos disponíveis na tabela AGENDAMENTOS:");
    debug_log("- HORA_AGENDAMENTO: " . ($tem_hora_agendamento ? 'SIM' : 'NÃO'));
    debug_log("- NOME_PACIENTE: " . ($tem_nome_paciente ? 'SIM' : 'NÃO'));
    debug_log("- TELEFONE_PACIENTE: " . ($tem_telefone_paciente ? 'SIM' : 'NÃO'));
    debug_log("- TIPO_AGENDAMENTO: " . ($tem_tipo_agendamento ? 'SIM' : 'NÃO'));
    debug_log("- EXAME_ID: " . ($tem_exame_id ? 'SIM' : 'NÃO'));
    debug_log("- EXAMES_IDS: " . ($tem_exames_ids ? 'SIM' : 'NÃO'));
    
    debug_log("Campos de endereço disponíveis na tabela LAB_PACIENTES:");
    debug_log("- ENDERECO: " . ($tem_endereco ? 'SIM' : 'NÃO'));
    debug_log("- NUMERO: " . ($tem_numero ? 'SIM' : 'NÃO'));
    debug_log("- COMPLEMENTO: " . ($tem_complemento ? 'SIM' : 'NÃO'));
    debug_log("- BAIRRO: " . ($tem_bairro ? 'SIM' : 'NÃO'));
    debug_log("- CIDADE: " . ($tem_cidade ? 'SIM' : 'NÃO'));
    debug_log("- UF: " . ($tem_uf ? 'SIM' : 'NÃO'));
    debug_log("- CEP: " . ($tem_cep ? 'SIM' : 'NÃO'));
    
    // ============================================================================
    // 3. VERIFICAR LIMITE DE ENCAIXES (mantido igual)
    // ============================================================================
    
    // Buscar configuração da agenda
    $query_agenda = "SELECT LIMITE_ENCAIXES_DIA, ESPECIALIDADE_ID, TIPO, MEDICO_ID FROM AGENDAS WHERE ID = ?";
    $stmt_agenda = ibase_prepare($conn, $query_agenda);
    $result_agenda = ibase_execute($stmt_agenda, $agenda_id);
    $agenda_config = ibase_fetch_assoc($result_agenda);

    $limite_encaixes = (int)($agenda_config['LIMITE_ENCAIXES_DIA'] ?? 10);
    $tipo_agenda_encaixe = trim($agenda_config['TIPO'] ?? '');

    // ✅ ESPECIALIDADE: Validar contra as especialidades do médico (LAB_MEDICOS_ESPECIALIDADES)
    $medico_id_encaixe = (int)($agenda_config['MEDICO_ID'] ?? 0);
    $esp_agenda = !empty($agenda_config['ESPECIALIDADE_ID']) ? (int)$agenda_config['ESPECIALIDADE_ID'] : 0;

    if ($medico_id_encaixe > 0 && $especialidade_id > 0) {
        $query_validar_esp = "SELECT COUNT(*) as EXISTE FROM LAB_MEDICOS_ESPECIALIDADES WHERE MEDICO_ID = ? AND ESPECIALIDADE_ID = ?";
        $stmt_validar_esp = ibase_prepare($conn, $query_validar_esp);
        $result_validar_esp = ibase_execute($stmt_validar_esp, $medico_id_encaixe, $especialidade_id);
        $row_validar = ibase_fetch_assoc($result_validar_esp);

        if ($row_validar['EXISTE'] > 0) {
            debug_log("✅ ESPECIALIDADE_ID $especialidade_id é válida para o médico $medico_id_encaixe.");
        } else {
            debug_log("⚠️ ESPECIALIDADE_ID $especialidade_id NÃO pertence ao médico. Usando da agenda ($esp_agenda).");
            $especialidade_id = $esp_agenda;
        }
    } elseif ($especialidade_id <= 0 && $esp_agenda > 0) {
        debug_log("ℹ️ Nenhuma especialidade enviada. Usando da agenda ($esp_agenda).");
        $especialidade_id = $esp_agenda;
    }

    // ✅ VALIDAÇÃO: Consulta NÃO aceita exames
    if ($tipo_agenda_encaixe === 'consulta' && count($exames_ids) > 0) {
        debug_log("⚠️ VALIDAÇÃO: Agenda de CONSULTA não aceita exames. Ignorando " . count($exames_ids) . " exames recebidos");
        $exames_ids = [];
        $exames_ids_raw = '';
    }
    
    // Contar encaixes já feitos no dia
    $query_encaixes = "SELECT COUNT(*) as TOTAL_ENCAIXES 
                       FROM AGENDAMENTOS 
                       WHERE AGENDA_ID = ? 
                       AND DATA_AGENDAMENTO = ? 
                       AND (TIPO_AGENDAMENTO = 'ENCAIXE' OR HORA_AGENDAMENTO IS NULL)
                       AND STATUS NOT IN ('CANCELADO', 'FALTOU')";
    
    $stmt_encaixes = ibase_prepare($conn, $query_encaixes);
    $result_encaixes = ibase_execute($stmt_encaixes, $agenda_id, $data_agendamento);
    $encaixes_count = ibase_fetch_assoc($result_encaixes);
    
    $encaixes_ocupados = (int)($encaixes_count['TOTAL_ENCAIXES'] ?? 0);
    $encaixes_disponiveis = $limite_encaixes - $encaixes_ocupados;
    
    debug_log("Controle de encaixes:");
    debug_log("- Limite: $limite_encaixes");
    debug_log("- Ocupados: $encaixes_ocupados");
    debug_log("- Disponíveis: $encaixes_disponiveis");
    
    if ($encaixes_disponiveis <= 0) {
        throw new Exception("Limite de encaixes atingido para este dia ($encaixes_ocupados/$limite_encaixes)");
    }
    
    // ============================================================================
    // 4. GERAR NÚMERO DO AGENDAMENTO (mantido igual)
    // ============================================================================
    
    // Iniciar transação
    $trans = ibase_trans($conn);
    
    try {
        // Buscar o próximo número sequencial
        $query_max_numero = "SELECT MAX(CAST(SUBSTRING(NUMERO_AGENDAMENTO FROM 5) AS INTEGER)) as MAX_NUM 
                             FROM AGENDAMENTOS 
                             WHERE NUMERO_AGENDAMENTO LIKE 'AGD-%'";
        
        $result_max = ibase_query($trans, $query_max_numero);
        $max_row = ibase_fetch_assoc($result_max);
        
        $proximo_numero = ((int)($max_row['MAX_NUM'] ?? 0)) + 1;
        $numero_agendamento = 'AGD-' . str_pad($proximo_numero, 4, '0', STR_PAD_LEFT);
        
        debug_log("✅ NUMERO_AGENDAMENTO gerado: $numero_agendamento");

        // ✅ LIMPEZA PREVENTIVA: Remover registros órfãos de AGENDAMENTO_EXAMES
        try {
            $query_limpar_orfaos = "DELETE FROM AGENDAMENTO_EXAMES WHERE NUMERO_AGENDAMENTO = ?";
            $stmt_limpar = ibase_prepare($trans, $query_limpar_orfaos);
            $result_limpar = ibase_execute($stmt_limpar, $numero_agendamento);
            if ($result_limpar) {
                debug_log("✅ Limpeza preventiva de AGENDAMENTO_EXAMES para $numero_agendamento");
            }
        } catch (Exception $e_limpar) {
            debug_log("⚠️ Aviso na limpeza preventiva: " . $e_limpar->getMessage());
        }

        // ============================================================================
        // 5. DETERMINAR TIPO DE OPERAÇÃO E PACIENTE_ID - VERSÃO CORRIGIDA
        // ============================================================================
        
        debug_log('=== INÍCIO DA LÓGICA DE DETERMINAÇÃO DO PACIENTE_ID ===');
        
        // ✅ INICIALIZAÇÃO FORÇADA COMO NULL
        $paciente_id_final = null;
        $paciente_nome_final = $nome_paciente;
        $tipo_operacao = 'sem_cadastro';
        $endereco_salvo = false;
        
        debug_log("STEP 1: Inicialização - PACIENTE_ID = NULL, tipo = sem_cadastro");
        
        // ✅ CENÁRIO 1: PACIENTE EXISTENTE - CORREÇÃO AQUI
        if ($usar_paciente_existente && $paciente_selecionado_id > 0) {
            debug_log("STEP 2A: Entrando no cenário PACIENTE EXISTENTE");
            debug_log("STEP 2A: usar_paciente_existente = TRUE, paciente_selecionado_id = $paciente_selecionado_id");
            
            $query_paciente = "SELECT IDPACIENTE, PACIENTE FROM LAB_PACIENTES WHERE IDPACIENTE = ?";
            $stmt_paciente = ibase_prepare($trans, $query_paciente);
            $result_paciente = ibase_execute($stmt_paciente, $paciente_selecionado_id);
            $paciente_existente = ibase_fetch_assoc($result_paciente);
            
            if ($paciente_existente) {
                $paciente_id_final = $paciente_selecionado_id; // ✅ AQUI ESTÁ A CORREÇÃO
                $paciente_nome_final = mb_convert_encoding($paciente_existente['PACIENTE'], 'UTF-8', 'Windows-1252');
                $tipo_operacao = 'paciente_existente';
                debug_log("STEP 2A: ✅ PACIENTE_ID corrigido = $paciente_id_final");
                debug_log("STEP 2A: ✅ tipo_operacao = $tipo_operacao");
                debug_log("STEP 2A: ✅ Nome do paciente: $paciente_nome_final");
            } else {
                throw new Exception('Paciente selecionado não encontrado no sistema');
            }
            
        // ✅ CENÁRIO 2: NOVO CADASTRO (EXPANDIDO COM ENDEREÇO)
        } elseif ($cadastrar_paciente) {
            debug_log("STEP 2B: Entrando no cenário NOVO CADASTRO");
            debug_log("STEP 2B: cadastrar_paciente = TRUE");
            
            // Verificar se checkbox "não tem CPF" foi marcado
            $nao_tem_cpf = isset($_POST['nao_tem_cpf']) && $_POST['nao_tem_cpf'] === 'on';
            
            if (empty($data_nascimento)) {
                throw new Exception('Data de nascimento é obrigatória para cadastro');
            }
            
            // CPF é obrigatório apenas se não foi marcado "não tem CPF"
            if (!$nao_tem_cpf && empty($cpf_paciente)) {
                throw new Exception('CPF é obrigatório para cadastro. Marque "Não tem CPF" se o paciente não possuir.');
            }
            
            // Verificar se CPF já existe (somente se fornecido)
            $cpf_limpo = !empty($cpf_paciente) ? preg_replace('/[^0-9]/', '', $cpf_paciente) : '';
            
            if (!empty($cpf_limpo)) {
                if (strlen($cpf_limpo) !== 11) {
                    throw new Exception('CPF deve ter 11 dígitos');
                }
                
                $query_cpf = "SELECT COUNT(*) as EXISTE FROM LAB_PACIENTES WHERE CPF = ?";
                $stmt_cpf = ibase_prepare($trans, $query_cpf);
                $result_cpf = ibase_execute($stmt_cpf, $cpf_limpo);
                $cpf_existe = ibase_fetch_assoc($result_cpf);
                
                if ($cpf_existe['EXISTE'] > 0) {
                    throw new Exception('CPF já cadastrado no sistema');
                }
            }
            
            // Gerar próximo ID para paciente
            $query_max_id = "SELECT MAX(IDPACIENTE) as MAX_ID FROM LAB_PACIENTES";
            $result_max_id = ibase_query($trans, $query_max_id);
            $max_id_row = ibase_fetch_assoc($result_max_id);
            $novo_id = ((int)$max_id_row['MAX_ID']) + 1;
            
            debug_log("STEP 2B: Novo ID de paciente será: $novo_id");
            
            // ✅ INSERIR NOVO PACIENTE COM ENDEREÇO COMPLETO
            $telefone_limpo = preg_replace('/[^0-9]/', '', $telefone_paciente);
            $cep_limpo = preg_replace('/[^0-9]/', '', $cep);
            
            // Preparar campos e valores dinamicamente
            $campos_paciente = ['IDPACIENTE', 'PACIENTE', 'FONE1'];
            $valores_paciente = [$novo_id, $nome_paciente, $telefone_limpo];
            
            // Adicionar CPF somente se fornecido
            if (!empty($cpf_limpo)) {
                $campos_paciente[] = 'CPF';
                $valores_paciente[] = $cpf_limpo;
            }
            
            // Adicionar campos opcionais se preenchidos e se existem na tabela
            if (!empty($email_paciente)) {
                $campos_paciente[] = 'EMAIL';
                $valores_paciente[] = $email_paciente;
            }
            
            if (!empty($data_nascimento)) {
                $campos_paciente[] = 'ANIVERSARIO';
                $valores_paciente[] = $data_nascimento;
            }
            
            if (!empty($sexo)) {
                $campos_paciente[] = 'SEXO';
                $valores_paciente[] = strtoupper($sexo);
            }
            
            if (!empty($rg)) {
                $campos_paciente[] = 'RG';
                $valores_paciente[] = $rg;
            }
            
            // ✅ ADICIONAR CAMPOS DE ENDEREÇO SE EXISTIREM NA TABELA
            if ($tem_endereco && !empty($endereco)) {
                $campos_paciente[] = 'ENDERECO';
                $valores_paciente[] = $endereco;
                $endereco_salvo = true;
            }
            
            if ($tem_numero && !empty($numero)) {
                $campos_paciente[] = 'NUMERO';
                $valores_paciente[] = $numero;
                $endereco_salvo = true;
            }
            
            if ($tem_complemento && !empty($complemento)) {
                $campos_paciente[] = 'COMPLEMENTO';
                $valores_paciente[] = $complemento;
            }
            
            if ($tem_bairro && !empty($bairro)) {
                $campos_paciente[] = 'BAIRRO';
                $valores_paciente[] = $bairro;
                $endereco_salvo = true;
            }
            
            if ($tem_cidade && !empty($cidade)) {
                $campos_paciente[] = 'CIDADE';
                $valores_paciente[] = $cidade;
                $endereco_salvo = true;
            }
            
            if ($tem_uf && !empty($uf)) {
                $campos_paciente[] = 'UF';
                $valores_paciente[] = $uf;
                $endereco_salvo = true;
            }
            
            if ($tem_cep && !empty($cep_limpo)) {
                $campos_paciente[] = 'CEP';
                $valores_paciente[] = $cep_limpo;
                $endereco_salvo = true;
            }
            
            debug_log("=== INSERÇÃO DO PACIENTE COM ENDEREÇO ===");
            debug_log("Total de campos: " . count($campos_paciente));
            for ($i = 0; $i < count($campos_paciente); $i++) {
                debug_log("[$i] {$campos_paciente[$i]} = \"{$valores_paciente[$i]}\"");
            }
            debug_log("Endereço será salvo: " . ($endereco_salvo ? 'SIM' : 'NÃO'));
            
            // Construir query dinâmica
            $placeholders_paciente = str_repeat('?,', count($valores_paciente));
            $placeholders_paciente = rtrim($placeholders_paciente, ',');
            
            $query_insert_paciente = "INSERT INTO LAB_PACIENTES (" . implode(', ', $campos_paciente) . ") 
                                      VALUES ($placeholders_paciente)";
            
            debug_log("Query de inserção do paciente: $query_insert_paciente");
            
            $stmt_insert_paciente = ibase_prepare($trans, $query_insert_paciente);
            $result_insert_paciente = ibase_execute($stmt_insert_paciente, ...$valores_paciente);
            
            if (!$result_insert_paciente) {
                $erro_paciente = ibase_errmsg();
                debug_log("❌ Erro ao inserir paciente: $erro_paciente");
                throw new Exception("Erro ao cadastrar paciente: $erro_paciente");
            }
            
            $paciente_id_final = $novo_id;
            $tipo_operacao = 'novo_cadastro';
            debug_log("STEP 2B: ALTERAÇÃO - PACIENTE_ID agora = $paciente_id_final");
            debug_log("STEP 2B: ALTERAÇÃO - tipo_operacao agora = $tipo_operacao");
            debug_log("STEP 2B: Paciente cadastrado com endereço: " . ($endereco_salvo ? 'SIM' : 'NÃO'));
            
        // ✅ CENÁRIO 3: SEM CADASTRO (mantido igual)
        } else {
            debug_log("STEP 2C: Entrando no cenário SEM CADASTRO");
            $paciente_id_final = null;
            $tipo_operacao = 'sem_cadastro';
        }
        
        // ============================================================================
        // 6. INSERIR AGENDAMENTO - COM CORREÇÃO PARA HORÁRIO ESPECÍFICO
        // ============================================================================
        
        debug_log('Preparando inserção do agendamento...');
        debug_log("PACIENTE_ID que será usado: " . ($paciente_id_final !== null ? $paciente_id_final : 'NULL'));
        debug_log("HORA_AGENDAMENTO que será inserida: $hora_agendamento_final");
        debug_log("Horário específico foi enviado: " . ($horario_especifico_enviado ? 'SIM' : 'NÃO'));
        
        $campos_insert = [];
        $valores_insert = [];
        
        // Campos obrigatórios sempre presentes
        $campos_insert[] = 'NUMERO_AGENDAMENTO';
        $valores_insert[] = $numero_agendamento;
        
        $campos_insert[] = 'AGENDA_ID';
        $valores_insert[] = $agenda_id;
        
        $campos_insert[] = 'CONVENIO_ID';
        $valores_insert[] = $convenio_id;
        
        // ✅ CAMPO CRÍTICO: PACIENTE_ID - CORRIGIDO
        // Só incluir PACIENTE_ID se não for NULL
        if ($paciente_id_final !== null) {
            $campos_insert[] = 'PACIENTE_ID';
            $valores_insert[] = $paciente_id_final;
            debug_log("✅ PACIENTE_ID $paciente_id_final será inserido no agendamento");
        } else {
            debug_log("✅ PACIENTE_ID NULL - campo não será incluído na inserção");
        }
        
        $campos_insert[] = 'DATA_AGENDAMENTO';
        $valores_insert[] = $data_agendamento;
        
        $campos_insert[] = 'STATUS';
        $valores_insert[] = 'AGENDADO';
        
        // ✅ CAMPO CRÍTICO: HORA_AGENDAMENTO - SEMPRE FORÇAR INSERÇÃO
        if ($tem_hora_agendamento) {
            $campos_insert[] = 'HORA_AGENDAMENTO';
            $valores_insert[] = $hora_agendamento_final;
            debug_log("💾 HORA_AGENDAMENTO inserindo: $hora_agendamento_final");
        } else {
            debug_log("⚠️ Campo HORA_AGENDAMENTO não existe na tabela");
        }
        
        // ✅ NOME_PACIENTE
        if ($tem_nome_paciente) {
            $campos_insert[] = 'NOME_PACIENTE';
            $valores_insert[] = $paciente_nome_final;
        }
        
        // ✅ TELEFONE_PACIENTE
        if ($tem_telefone_paciente && !empty($telefone_paciente)) {
            $telefone_limpo = preg_replace('/[^0-9]/', '', $telefone_paciente);
            $campos_insert[] = 'TELEFONE_PACIENTE';
            $valores_insert[] = $telefone_limpo;
        }
        
        // ✅ TIPO_AGENDAMENTO
        if ($tem_tipo_agendamento) {
            $campos_insert[] = 'TIPO_AGENDAMENTO';
            $valores_insert[] = 'ENCAIXE';
        }
        
        // ✅ EXAMES (MÚLTIPLOS) - Estratégia híbrida
        if (count($exames_ids) > 0) {
            // Opção 1: Campo EXAMES_IDS (lista de IDs separados por vírgula)
            if ($tem_exames_ids) {
                $campos_insert[] = 'EXAMES_IDS';
                $valores_insert[] = implode(',', $exames_ids);
                debug_log("💾 EXAMES_IDS inserindo: " . implode(',', $exames_ids));
            }
            
            // Opção 2: Campo EXAME_ID (compatibilidade - primeiro exame da lista)
            if ($tem_exame_id) {
                $campos_insert[] = 'EXAME_ID';
                $valores_insert[] = $exames_ids[0]; // Primeiro exame para compatibilidade
                debug_log("💾 EXAME_ID inserindo (primeiro da lista): " . $exames_ids[0]);
            }
            
            debug_log("📋 Total de exames para processar: " . count($exames_ids));
        } else {
            debug_log("⚠️ EXAMES não serão inseridos: nenhum exame selecionado");
        }
        
        // OBSERVACOES
        if ($tipo_operacao === 'sem_cadastro') {
            $observacoes_finais = "ENCAIXE - Dados: $paciente_nome_final | Tel: $telefone_paciente";
            if (!empty($observacoes)) {
                $observacoes_finais .= " | Obs: $observacoes";
            }
            if ($horario_especifico_enviado) {
                $observacoes_finais .= " | Horário específico: " . substr($hora_agendamento_final, 0, 5);
            }
            if (count($exames_ids) > 0) {
                $observacoes_finais .= " | Exames IDs: " . implode(',', $exames_ids) . 
                                     " (Total: " . count($exames_ids) . ")";
            }
        } else {
            $observacoes_finais = !empty($observacoes) ? $observacoes : 'Encaixe agendado';
            if ($horario_especifico_enviado) {
                $observacoes_finais .= " | Horário específico: " . substr($hora_agendamento_final, 0, 5);
            }
            if (count($exames_ids) > 0) {
                $observacoes_finais .= " | Exames IDs: " . implode(',', $exames_ids) . 
                                     " (Total: " . count($exames_ids) . ")";
            }
        }
        
        // ✅ Adicionar especialidade_id se fornecida (para consultas)
        if ($especialidade_id > 0) {
            $campos_insert[] = 'ESPECIALIDADE_ID';
            $valores_insert[] = $especialidade_id;
            debug_log('Adicionando ESPECIALIDADE_ID ao encaixe: ' . $especialidade_id);
        }
        
        $campos_insert[] = 'OBSERVACOES';
        $valores_insert[] = $observacoes_finais;
        
        // ============================================================================
        // 7. EXECUTAR INSERÇÃO DO AGENDAMENTO
        // ============================================================================
        
        $placeholders = str_repeat('?,', count($valores_insert));
        $placeholders = rtrim($placeholders, ',');
        
        $query_insert = "INSERT INTO AGENDAMENTOS (" . implode(', ', $campos_insert) . ") 
                         VALUES ($placeholders)";
        
        debug_log("Query de inserção do agendamento: $query_insert");
        debug_log("Campos sendo inseridos: " . implode(', ', $campos_insert));
        debug_log("Total de valores: " . count($valores_insert));
        
        // ✅ DEBUG DETALHADO DOS VALORES
        debug_log("=== VALORES SENDO INSERIDOS ===");
        for ($i = 0; $i < count($campos_insert); $i++) {
            debug_log("[$i] {$campos_insert[$i]} = \"{$valores_insert[$i]}\"");
        }
        
        $stmt_insert = ibase_prepare($trans, $query_insert);
        $result_insert = ibase_execute($stmt_insert, ...$valores_insert);
        
        if (!$result_insert) {
            $erro_detalhado = ibase_errmsg();
            debug_log("❌ Erro na inserção do agendamento: $erro_detalhado");
            throw new Exception("Erro ao inserir agendamento: $erro_detalhado");
        }
        
        debug_log('✅ Agendamento inserido com sucesso');
        
        // ============================================================================
        // 7B. SALVAR EXAMES EM TABELA DE RELACIONAMENTO (se necessário)
        // ============================================================================
        
        if (count($exames_ids) > 0 && !$tem_exames_ids) {
            debug_log('=== SALVANDO EXAMES EM TABELA DE RELACIONAMENTO ===');
            
            // Verificar se existe tabela de relacionamento AGENDAMENTO_EXAMES
            $tem_tabela_relacionamento = false;
            try {
                $query_check_table = "SELECT COUNT(*) as EXISTE FROM RDB\$RELATIONS WHERE RDB\$RELATION_NAME = 'AGENDAMENTO_EXAMES'";
                $result_check = ibase_query($trans, $query_check_table);
                $row_check = ibase_fetch_assoc($result_check);
                $tem_tabela_relacionamento = $row_check['EXISTE'] > 0;
            } catch (Exception $e) {
                debug_log("Erro ao verificar tabela de relacionamento: " . $e->getMessage());
            }
            
            if ($tem_tabela_relacionamento) {
                debug_log("✅ Tabela AGENDAMENTO_EXAMES encontrada - inserindo relacionamentos");
                
                foreach ($exames_ids as $exame_id) {
                    try {
                        $query_rel = "INSERT INTO AGENDAMENTO_EXAMES (NUMERO_AGENDAMENTO, EXAME_ID) VALUES (?, ?)";
                        $stmt_rel = ibase_prepare($trans, $query_rel);
                        $result_rel = ibase_execute($stmt_rel, $numero_agendamento, $exame_id);
                        
                        if ($result_rel) {
                            debug_log("✅ Relacionamento inserido: {$numero_agendamento} <-> {$exame_id}");
                        } else {
                            debug_log("❌ Erro ao inserir relacionamento: {$numero_agendamento} <-> {$exame_id}");
                        }
                    } catch (Exception $e) {
                        debug_log("❌ Erro na inserção do relacionamento {$exame_id}: " . $e->getMessage());
                    }
                }
            } else {
                debug_log("⚠️ Tabela AGENDAMENTO_EXAMES não existe - exames salvos apenas nas observações");
            }
        } else if (count($exames_ids) > 0 && $tem_exames_ids) {
            debug_log("✅ Exames salvos no campo EXAMES_IDS - tabela de relacionamento não necessária");
        }
        
        // ============================================================================
        // 8. AUDITORIA COMPLETA DO ENCAIXE CRIADO
        // ============================================================================
        
        // Buscar informações do usuário atual
        $usuario_atual = isset($_COOKIE['log_usuario']) ? $_COOKIE['log_usuario'] : 'RENISON';
        
        // Buscar dados completos do encaixe para auditoria
        $query_buscar_encaixe = "SELECT 
                                   a.*, 
                                   COALESCE(p.PACIENTE, a.NOME_PACIENTE) as NOME_COMPLETO_PACIENTE,
                                   c.NOME as NOME_CONVENIO,
                                   ag.TIPO_AGENDA as NOME_AGENDA,
                                   m.NOME as NOME_MEDICO
                                 FROM AGENDAMENTOS a
                                 LEFT JOIN LAB_PACIENTES p ON a.PACIENTE_ID = p.IDPACIENTE
                                 LEFT JOIN CONVENIOS c ON a.CONVENIO_ID = c.ID
                                 LEFT JOIN AGENDAS ag ON a.AGENDA_ID = ag.ID
                                 LEFT JOIN LAB_MEDICOS_PRES m ON ag.MEDICO_ID = m.ID
                                 WHERE a.NUMERO_AGENDAMENTO = ?";
        
        $stmt_buscar = ibase_prepare($trans, $query_buscar_encaixe);
        $result_buscar = ibase_execute($stmt_buscar, $numero_agendamento);
        
        if ($result_buscar && $row_encaixe = ibase_fetch_assoc($result_buscar)) {
            // Preparar dados para auditoria
            $dados_encaixe_criado = [
                'id' => $row_encaixe['ID'],
                'numero_agendamento' => $numero_agendamento,
                'paciente_id' => $paciente_id_final,
                'paciente' => utf8_encode($row_encaixe['NOME_COMPLETO_PACIENTE'] ?? $nome_paciente),
                'agenda_id' => $agenda_id,
                'nome_agenda' => utf8_encode($row_encaixe['NOME_AGENDA'] ?? ''),
                'nome_medico' => utf8_encode($row_encaixe['NOME_MEDICO'] ?? ''),
                'convenio_id' => $convenio_id,
                'nome_convenio' => utf8_encode($row_encaixe['NOME_CONVENIO'] ?? ''),
                'data_agendamento' => $data_agendamento,
                'hora_agendamento' => $hora_agendamento_final,
                'tipo_agendamento' => 'ENCAIXE',
                'telefone' => $telefone_paciente,
                'observacoes' => utf8_encode($observacoes ?? ''),
                'exames' => $exames_ids,
                'total_exames' => count($exames_ids)
            ];
            
            // Preparar observações para auditoria
            $observacoes_auditoria = sprintf(
                "ENCAIXE criado: %s agendado para %s às %s - %s - Tipo: %s - Exames: %d",
                $dados_encaixe_criado['paciente'],
                date('d/m/Y', strtotime($data_agendamento)),
                substr($hora_agendamento_final, 0, 5),
                $dados_encaixe_criado['nome_convenio'] ?? 'N/A',
                'ENCAIXE',
                count($exames_ids)
            );
            
            // Adicionar informações sobre endereço se foi cadastrado
            if ($tipo_operacao === 'novo_cadastro' && $endereco_salvo) {
                $observacoes_auditoria .= " | Endereço completo cadastrado";
            }
            
            if ($horario_especifico_enviado) {
                $observacoes_auditoria .= " | Horário específico: " . substr($hora_agendamento_final, 0, 5);
            }
            
            // Registrar auditoria completa
            $resultado_auditoria = auditarAgendamentoCompleto(
                $conn,
                'CRIAR_ENCAIXE',
                $usuario_atual,
                $dados_encaixe_criado,
                null, // Não há dados anteriores em uma criação
                $observacoes_auditoria,
                [
                    'metodo_criacao' => 'formulario_web_encaixe',
                    'tipo_operacao' => $tipo_operacao,
                    'endereco_salvo' => $endereco_salvo,
                    'horario_especifico' => $horario_especifico_enviado,
                    'hora_especifica' => $horario_especifico_enviado ? substr($hora_agendamento_final, 0, 5) : null,
                    'exames_quantidade' => count($exames_ids),
                    'exames_ids' => implode(',', $exames_ids),
                    'convenio_id' => $convenio_id,
                    'telefone_paciente' => $telefone_paciente,
                    'ip_criacao' => obterIPUsuario(),
                    'timestamp_criacao' => date('Y-m-d H:i:s')
                ]
            );
            
            if ($resultado_auditoria) {
                debug_log("✅ Auditoria de criação de ENCAIXE registrada com sucesso");
            } else {
                debug_log("❌ AVISO: Falha ao registrar auditoria de criação de ENCAIXE para número $numero_agendamento");
            }
        } else {
            debug_log("❌ AVISO: Não foi possível buscar dados do encaixe para auditoria");
        }
        
        // ============================================================================
        // 9. CONFIRMAR TRANSAÇÃO E RESPOSTA
        // ============================================================================
        
        ibase_commit($trans);
        debug_log('✅ Transação confirmada com sucesso');
        
        // Preparar mensagem de sucesso
        $mensagens = [
            'paciente_existente' => 'Encaixe agendado com sucesso! Paciente vinculado ao cadastro existente.',
            'novo_cadastro' => 'Paciente cadastrado e encaixe agendado com sucesso!',
            'sem_cadastro' => 'Encaixe agendado com sucesso! Dados salvos no agendamento.'
        ];
        
        // ✅ ADICIONAR INFORMAÇÃO SOBRE HORÁRIO ESPECÍFICO NA MENSAGEM
        if ($horario_especifico_enviado) {
            $horario_display = substr($hora_agendamento_final, 0, 5);
            switch ($tipo_operacao) {
                case 'paciente_existente':
                    $mensagens['paciente_existente'] = "Encaixe agendado para às $horario_display! Paciente vinculado ao cadastro existente.";
                    break;
                case 'novo_cadastro':
                    $mensagens['novo_cadastro'] = "Paciente cadastrado e encaixe agendado para às $horario_display com sucesso!";
                    break;
                case 'sem_cadastro':
                    $mensagens['sem_cadastro'] = "Encaixe agendado para às $horario_display com sucesso! Dados salvos no agendamento.";
                    break;
            }
        }
        
        // ✅ ADICIONAR INFORMAÇÃO SOBRE ENDEREÇO NA RESPOSTA
        if ($tipo_operacao === 'novo_cadastro') {
            if ($endereco_salvo) {
                $mensagens['novo_cadastro'] = str_replace('cadastrado e', 'cadastrado com endereço completo e', $mensagens['novo_cadastro']);
            }
        }
        
        $response = [
            'status' => 'sucesso',
            'mensagem' => $mensagens[$tipo_operacao],
            'numero_agendamento' => $numero_agendamento,
            'paciente_id' => $paciente_id_final,
            'paciente_nome' => $paciente_nome_final,
            'paciente_telefone' => $telefone_paciente,
            'tipo_operacao' => $tipo_operacao,
            'paciente_cadastrado' => ($tipo_operacao === 'novo_cadastro'),
            'paciente_existente_usado' => ($tipo_operacao === 'paciente_existente'),
            'encaixe_sem_cadastro' => ($tipo_operacao === 'sem_cadastro'),
            'endereco_salvo' => $endereco_salvo,
            'exames_ids' => $exames_ids, // ✅ NOVA INFORMAÇÃO
            'exames_quantidade' => count($exames_ids), // ✅ NOVA INFORMAÇÃO
            'auditoria_registrada' => isset($resultado_auditoria) ? ($resultado_auditoria ? 'sim' : 'nao') : 'nao',
            'exames_selecionados' => count($exames_ids) > 0, // ✅ NOVA INFORMAÇÃO
            'horario_especifico_usado' => $horario_especifico_enviado, // ✅ NOVA INFORMAÇÃO
            'horario_agendamento' => $hora_agendamento_final, // ✅ HORÁRIO FINAL USADO
            'tipo' => $horario_especifico_enviado ? 'ENCAIXE_HORARIO_ESPECIFICO' : 'ENCAIXE',
            'data_encaixe' => date('Y-m-d H:i:s'),
            'limite_usado' => ($encaixes_ocupados + 1) . '/' . $limite_encaixes,
            'hora_gerada' => $hora_agendamento_final, // ✅ CORRIGIDO: Mostrar o horário real usado
            'debug_final' => [
                'paciente_id_enviado' => $paciente_id_final,
                'tipo_operacao' => $tipo_operacao,
                'campos_inseridos' => count($campos_insert),
                'numero_agendamento' => $numero_agendamento,
                'endereco_processado' => $endereco_salvo,
                'exames_processados' => count($exames_ids) > 0,
                'exames_salvos_exames_ids' => $tem_exames_ids && count($exames_ids) > 0,
                'exames_salvos_exame_id' => $tem_exame_id && count($exames_ids) > 0,
                'horario_processamento' => [  // ✅ NOVO DEBUG DO HORÁRIO
                    'horario_especifico_enviado' => $horario_especifico_enviado,
                    'hora_final_inserida' => $hora_agendamento_final,
                    'tipo_horario_post' => $_POST['tipo_horario'] ?? 'não informado',
                    'horario_agendamento_post' => $_POST['horario_agendamento'] ?? 'não informado',
                    'horario_especifico_post' => $_POST['horario_especifico'] ?? 'não informado',
                    'horario_digitado_post' => $_POST['horario_digitado'] ?? 'não informado'
                ],
                'campos_endereco_recebidos' => [
                    'cep' => !empty($cep),
                    'endereco' => !empty($endereco),
                    'numero' => !empty($numero),
                    'complemento' => !empty($complemento),
                    'bairro' => !empty($bairro),
                    'cidade' => !empty($cidade),
                    'uf' => !empty($uf)
                ],
                'flags_recebidos' => [
                    'usar_paciente_existente' => $usar_paciente_existente_raw,
                    'cadastrar_paciente' => $cadastrar_paciente_raw,
                    'paciente_id' => $paciente_id_raw,
                    'paciente_selecionado_id' => $paciente_selecionado_id_raw
                ],
                'paciente_id_final_usado' => $paciente_id_final
            ]
        ];
        
        debug_log('✅ Resposta de sucesso preparada');
        debug_log("Endereço foi salvo: " . ($endereco_salvo ? 'SIM' : 'NÃO'));
        debug_log("PACIENTE_ID final na resposta: " . ($paciente_id_final ?? 'NULL'));
        debug_log("HORÁRIO ESPECÍFICO final na resposta: $hora_agendamento_final");
        debug_log("Horário específico foi usado: " . ($horario_especifico_enviado ? 'SIM' : 'NÃO'));
        debug_log("EXAMES processados: " . (count($exames_ids) > 0 ? count($exames_ids) . ' exames - IDs: ' . implode(',', $exames_ids) : 'NENHUM'));
        debug_log("Campo EXAMES_IDS disponível: " . ($tem_exames_ids ? 'SIM' : 'NÃO'));
        debug_log("Campo EXAME_ID disponível: " . ($tem_exame_id ? 'SIM' : 'NÃO'));
        
        ob_clean();
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e_inner) {
        debug_log('❌ ERRO na transação: ' . $e_inner->getMessage());
        if (isset($trans)) {
            ibase_rollback($trans);
        }
        throw $e_inner;
    }
    
} catch (Exception $e) {
    debug_log('❌ ERRO GERAL: ' . $e->getMessage());
    debug_log('Arquivo do erro: ' . $e->getFile());
    debug_log('Linha do erro: ' . $e->getLine());
    
    $response = [
        'status' => 'erro',
        'mensagem' => $e->getMessage(),
        'debug_info' => [
            'post_data' => $_POST,
            'timestamp' => date('Y-m-d H:i:s'),
            'erro_completo' => $e->getMessage(),
            'arquivo_erro' => basename($e->getFile()),
            'linha_erro' => $e->getLine(),
            'horario_debug' => [
                'tipo_horario' => $_POST['tipo_horario'] ?? 'não enviado',
                'horario_agendamento' => $_POST['horario_agendamento'] ?? 'não enviado',
                'horario_especifico' => $_POST['horario_especifico'] ?? 'não enviado',
                'horario_digitado' => $_POST['horario_digitado'] ?? 'não enviado'
            ]
        ]
    ];
    
    ob_clean();
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
}

debug_log('=== FIM processar_encaixe.php - VERSÃO COM CORREÇÃO DE HORÁRIO ===');

if (ob_get_level()) {
    ob_end_flush();
}
exit;
?>