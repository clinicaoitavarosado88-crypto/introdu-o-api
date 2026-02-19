<?php
// ============================================================================
// processar_agendamento.php - BASEADO NA LÓGICA DO ENCAIXE PARA AGENDAMENTO NORMAL
// ✅ Adaptado do processar_encaixe.php para agendamento normal
// ============================================================================

error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

function debug_log($message) {
    error_log('[AGENDAMENTO] ' . date('Y-m-d H:i:s') . ' - ' . $message);
}

// Limpar qualquer output anterior
if (ob_get_level()) {
    ob_end_clean();
}
ob_start();

// Headers
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-cache, must-revalidate');

debug_log('=== INÍCIO processar_agendamento.php - BASEADO EM ENCAIXE ===');

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
    
    // Dados básicos obrigatórios
    $agenda_id = (int)($_POST['agenda_id'] ?? 0);
    $data_agendamento = trim($_POST['data_agendamento'] ?? '');

    // ✅ ACEITAR AMBOS: horario_agendamento OU hora_agendamento
    $horario_agendamento = trim($_POST['horario_agendamento'] ?? $_POST['hora_agendamento'] ?? '');

    $nome_paciente = trim($_POST['nome_paciente'] ?? '');
    $telefone_paciente = trim($_POST['telefone_paciente'] ?? '');
    $convenio_id = (int)($_POST['convenio_id'] ?? 0);
    $observacoes = trim($_POST['observacoes'] ?? '');
    $idade = (int)($_POST['idade'] ?? 0);
    $tipo_consulta = trim($_POST['tipo_consulta'] ?? '');
    
    // ✅ Novos campos de atendimento
    $confirmado = (int)($_POST['confirmado'] ?? 0);
    $tipo_atendimento = trim($_POST['tipo_atendimento'] ?? 'NORMAL');

    // ✅ SEDAÇÃO: Capturar se o paciente precisa de sedação/anestesia
    $precisa_sedacao = isset($_POST['precisa_sedacao']) && $_POST['precisa_sedacao'] === 'true' ? 'S' : 'N';
    debug_log('💉 SEDAÇÃO: ' . ($precisa_sedacao === 'S' ? 'SIM' : 'NÃO'));

    // ✅ Especialidade selecionada (para médicos com múltiplas especialidades)
    $especialidade_id = (int)($_POST['especialidade_id'] ?? 0);
    debug_log('Especialidade ID selecionada: ' . $especialidade_id);
    
    // ✅ Capturar IDs dos exames selecionados (múltiplos)
    $exames_ids_raw = trim($_POST['exames_ids'] ?? '');
    $exames_ids = [];

    // 🔍 ARRAY GLOBAL PARA DEBUG - Será incluído na resposta JSON
    $debug_trace_exames = [
        'timestamp' => date('Y-m-d H:i:s'),
        'todos_campos_post' => []
    ];

    // 🐛 DEBUG DETALHADO: Verificar TODOS os campos POST que contenham "exame"
    debug_log('=== DEBUG: TODOS OS CAMPOS POST RELACIONADOS A EXAMES ===');
    foreach ($_POST as $key => $value) {
        if (stripos($key, 'exame') !== false) {
            debug_log("POST['$key']: \"$value\"");
            $debug_trace_exames['todos_campos_post'][$key] = $value; // 🔍 Capturar para debug
        }
    }
    debug_log('=== FIM DEBUG CAMPOS EXAMES ===');

    $debug_trace_exames['exames_ids_raw'] = $exames_ids_raw; // 🔍 Capturar valor raw

    if (!empty($exames_ids_raw)) {
        // Converter string "1,2,3" em array de inteiros
        $exames_ids_exploded = explode(',', $exames_ids_raw);
        $debug_trace_exames['passo_1_explode'] = $exames_ids_exploded; // 🔍
        debug_log('Após explode: ' . json_encode($exames_ids_exploded));

        $exames_ids_filtered = array_filter($exames_ids_exploded);
        $debug_trace_exames['passo_2_array_filter'] = array_values($exames_ids_filtered); // 🔍
        debug_log('Após array_filter: ' . json_encode($exames_ids_filtered));

        $exames_ids = array_map('intval', $exames_ids_filtered);
        $debug_trace_exames['passo_3_array_map'] = array_values($exames_ids); // 🔍
        debug_log('Após array_map: ' . json_encode($exames_ids));

        $exames_ids = array_unique($exames_ids); // Remover duplicatas
        $debug_trace_exames['passo_4_array_unique'] = array_values($exames_ids); // 🔍
        debug_log('Após array_unique: ' . json_encode($exames_ids));
    }

    $debug_trace_exames['exames_ids_final'] = array_values($exames_ids); // 🔍
    $debug_trace_exames['quantidade_final'] = count($exames_ids); // 🔍

    debug_log('IDs dos exames recebidos (raw): "' . $exames_ids_raw . '"');
    debug_log('Exames processados (final): ' . json_encode($exames_ids));
    debug_log('Quantidade de exames: ' . count($exames_ids));
    
    // ✅ HORÁRIO PARA AGENDAMENTO NORMAL
    debug_log('=== PROCESSAMENTO DE HORÁRIO NORMAL ===');
    
    $hora_agendamento_final = null;
    
    // Para agendamento normal, usar o horário enviado
    if (!empty($horario_agendamento)) {
        // Remover segundos se existirem
        $horario_limpo = substr($horario_agendamento, 0, 5);
        
        // Validar formato HH:MM
        if (preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $horario_limpo)) {
            $hora_agendamento_final = $horario_limpo . ':00'; // Adicionar segundos
            debug_log("✅ HORÁRIO NORMAL CAPTURADO: $hora_agendamento_final");
        } else {
            throw new Exception("Horário inválido: $horario_limpo");
        }
    } else {
        throw new Exception('Horário do agendamento é obrigatório');
    }
    
    debug_log('=== FIM PROCESSAMENTO DE HORÁRIO ===');
    
    // Flags de controle - ANÁLISE DETALHADA
    $usar_paciente_existente_raw = $_POST['usar_paciente_existente'] ?? '';
    $cadastrar_paciente_raw = $_POST['deve_cadastrar_paciente'] ?? ''; // Nome diferente do encaixe
    $paciente_selecionado_id_raw = $_POST['paciente_selecionado_id'] ?? '';
    $paciente_id_raw = $_POST['paciente_id'] ?? '';
    
    debug_log('=== ANÁLISE DETALHADA DOS FLAGS ===');
    debug_log("usar_paciente_existente (raw): '$usar_paciente_existente_raw'");
    debug_log("deve_cadastrar_paciente (raw): '$cadastrar_paciente_raw'");
    debug_log("paciente_selecionado_id (raw): '$paciente_selecionado_id_raw'");
    debug_log("paciente_id (raw): '$paciente_id_raw'");
    
    $usar_paciente_existente = ($usar_paciente_existente_raw === 'true');
    $cadastrar_paciente = ($cadastrar_paciente_raw === 'true');
    
    // ✅ CORREÇÃO: Capturar corretamente o ID do paciente existente
    $paciente_selecionado_id = 0;
    if (!empty($paciente_selecionado_id_raw) && $paciente_selecionado_id_raw !== 'NULL') {
        $paciente_selecionado_id = (int)$paciente_selecionado_id_raw;
    } elseif (!empty($paciente_id_raw) && $paciente_id_raw !== 'NULL') {
        $paciente_selecionado_id = (int)$paciente_id_raw;
    }
    
    debug_log("usar_paciente_existente (boolean): " . ($usar_paciente_existente ? 'TRUE' : 'FALSE'));
    debug_log("deve_cadastrar_paciente (boolean): " . ($cadastrar_paciente ? 'TRUE' : 'FALSE'));
    debug_log("paciente_selecionado_id (int): $paciente_selecionado_id");
    
    // ✅ Dados para cadastro completo de paciente
    $cpf_paciente = trim($_POST['cpf_paciente'] ?? '');
    $data_nascimento = trim($_POST['data_nascimento'] ?? '');
    $sexo = trim($_POST['sexo'] ?? '');
    $email_paciente = trim($_POST['email_paciente'] ?? '');
    $rg = trim($_POST['rg'] ?? '');
    $orgao_emissor = trim($_POST['orgao_emissor'] ?? '');
    
    // ✅ CAMPOS DE ENDEREÇO COMPLETO
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
    // 1.5 VERIFICAR BLOQUEIOS ANTES DE PROCESSAR
    // ============================================================================
    
    debug_log('=== VERIFICANDO BLOQUEIOS ===');
    
    // 1. Verificar se a agenda está bloqueada permanentemente
    $query_bloqueio_permanente = "SELECT COUNT(*) as BLOQUEADO 
                                  FROM AGENDA_BLOQUEIOS 
                                  WHERE AGENDA_ID = ? 
                                  AND TIPO_BLOQUEIO = 'AGENDA_PERMANENTE' 
                                  AND ATIVO = 1";
    
    $stmt_bloqueio_perm = ibase_prepare($conn, $query_bloqueio_permanente);
    $result_bloqueio_perm = ibase_execute($stmt_bloqueio_perm, $agenda_id);
    $bloqueio_perm = ibase_fetch_assoc($result_bloqueio_perm);
    
    if ($bloqueio_perm['BLOQUEADO'] > 0) {
        debug_log("❌ BLOQUEIO: Agenda $agenda_id bloqueada permanentemente");
        throw new Exception('Esta agenda está bloqueada permanentemente e não aceita novos agendamentos');
    }
    
    // 2. Verificar se a agenda está bloqueada temporariamente na data
    $query_bloqueio_temp = "SELECT COUNT(*) as BLOQUEADO 
                            FROM AGENDA_BLOQUEIOS 
                            WHERE AGENDA_ID = ? 
                            AND TIPO_BLOQUEIO = 'AGENDA_TEMPORARIO' 
                            AND ATIVO = 1 
                            AND ? BETWEEN DATA_INICIO AND DATA_FIM";
    
    $stmt_bloqueio_temp = ibase_prepare($conn, $query_bloqueio_temp);
    $result_bloqueio_temp = ibase_execute($stmt_bloqueio_temp, $agenda_id, $data_agendamento);
    $bloqueio_temp = ibase_fetch_assoc($result_bloqueio_temp);
    
    if ($bloqueio_temp['BLOQUEADO'] > 0) {
        debug_log("❌ BLOQUEIO: Agenda $agenda_id bloqueada temporariamente na data $data_agendamento");
        throw new Exception('Esta agenda está bloqueada temporariamente na data selecionada');
    }
    
    // 3. Verificar se o dia específico está bloqueado
    $query_bloqueio_dia = "SELECT COUNT(*) as BLOQUEADO 
                           FROM AGENDA_BLOQUEIOS 
                           WHERE AGENDA_ID = ? 
                           AND TIPO_BLOQUEIO = 'DIA' 
                           AND ATIVO = 1 
                           AND DATA_BLOQUEIO = ?";
    
    $stmt_bloqueio_dia = ibase_prepare($conn, $query_bloqueio_dia);
    $result_bloqueio_dia = ibase_execute($stmt_bloqueio_dia, $agenda_id, $data_agendamento);
    $bloqueio_dia = ibase_fetch_assoc($result_bloqueio_dia);
    
    if ($bloqueio_dia['BLOQUEADO'] > 0) {
        debug_log("❌ BLOQUEIO: Dia $data_agendamento bloqueado para agenda $agenda_id");
        throw new Exception('O dia selecionado está bloqueado para agendamentos');
    }
    
    // 4. Verificar se o horário específico está bloqueado
    if (!empty($hora_agendamento_final)) {
        $query_horario_bloqueado = "SELECT COUNT(*) as BLOQUEADO 
                                    FROM AGENDA_BLOQUEIOS 
                                    WHERE AGENDA_ID = ? 
                                    AND TIPO_BLOQUEIO = 'HORARIO' 
                                    AND ATIVO = 1 
                                    AND DATA_BLOQUEIO = ? 
                                    AND ? BETWEEN HORARIO_INICIO AND HORARIO_FIM";
        
        $stmt_horario_bloq = ibase_prepare($conn, $query_horario_bloqueado);
        $result_horario_bloq = ibase_execute($stmt_horario_bloq, $agenda_id, $data_agendamento, $hora_agendamento_final);
        $bloqueio_horario = ibase_fetch_assoc($result_horario_bloq);
        
        if ($bloqueio_horario['BLOQUEADO'] > 0) {
            debug_log("❌ BLOQUEIO: Horário $hora_agendamento_final bloqueado para agenda $agenda_id na data $data_agendamento");
            throw new Exception('O horário selecionado está bloqueado para agendamentos');
        }
    }
    
    debug_log('✅ VERIFICAÇÃO DE BLOQUEIOS: Nenhum bloqueio encontrado - agendamento pode prosseguir');
    
    // ============================================================================
    // 1.6 VERIFICAR LIMITES DE CONVÊNIO
    // ============================================================================
    
    debug_log('=== VERIFICANDO LIMITES DE CONVÊNIO ===');

    // 0. Validar se o convênio pertence à agenda
    $query_validar_conv = "SELECT COUNT(*) as EXISTE FROM AGENDA_CONVENIOS WHERE AGENDA_ID = ? AND CONVENIO_ID = ?";
    $stmt_validar_conv = ibase_prepare($conn, $query_validar_conv);
    $result_validar_conv = ibase_execute($stmt_validar_conv, $agenda_id, $convenio_id);
    $row_validar_conv = ibase_fetch_assoc($result_validar_conv);

    if ($row_validar_conv['EXISTE'] == 0) {
        $query_nome_conv_err = "SELECT NOME FROM CONVENIOS WHERE ID = ?";
        $stmt_nome_conv_err = ibase_prepare($conn, $query_nome_conv_err);
        $result_nome_conv_err = ibase_execute($stmt_nome_conv_err, $convenio_id);
        $nome_conv_err = ibase_fetch_assoc($result_nome_conv_err);
        $nome_convenio_err = $nome_conv_err ? mb_convert_encoding(trim($nome_conv_err['NOME']), 'UTF-8', 'Windows-1252') : "ID $convenio_id";
        debug_log("❌ Convênio '$nome_convenio_err' não pertence a esta agenda");
        throw new Exception("O convênio '$nome_convenio_err' não está disponível para esta agenda");
    }

    // 1. Verificar se existe limite específico para o convênio
    $query_limite_convenio = "SELECT LIMITE_ATENDIMENTOS
                              FROM AGENDA_CONVENIOS
                              WHERE AGENDA_ID = ? AND CONVENIO_ID = ?";
    
    $stmt_limite_conv = ibase_prepare($conn, $query_limite_convenio);
    $result_limite_conv = ibase_execute($stmt_limite_conv, $agenda_id, $convenio_id);
    $limite_convenio_data = ibase_fetch_assoc($result_limite_conv);
    
    if ($limite_convenio_data && $limite_convenio_data['LIMITE_ATENDIMENTOS'] > 0) {
        $limite_convenio = (int)$limite_convenio_data['LIMITE_ATENDIMENTOS'];
        debug_log("📋 Convênio $convenio_id tem limite de $limite_convenio atendimentos por dia");
        
        // 2. Verificar quantos agendamentos já existem para este convênio no dia
        $query_conv_ocupados = "SELECT COUNT(*) as TOTAL 
                                FROM AGENDAMENTOS 
                                WHERE AGENDA_ID = ? 
                                AND DATA_AGENDAMENTO = ? 
                                AND CONVENIO_ID = ? 
                                AND STATUS NOT IN ('CANCELADO', 'FALTOU')
                                AND (TIPO_AGENDAMENTO IS NULL OR TIPO_AGENDAMENTO = 'NORMAL')";
        
        $stmt_conv_ocupados = ibase_prepare($conn, $query_conv_ocupados);
        $result_conv_ocupados = ibase_execute($stmt_conv_ocupados, $agenda_id, $data_agendamento, $convenio_id);
        $conv_ocupados = ibase_fetch_assoc($result_conv_ocupados);
        $total_convenio_ocupado = (int)($conv_ocupados['TOTAL'] ?? 0);
        
        debug_log("📊 Convênio $convenio_id já tem $total_convenio_ocupado agendamentos no dia $data_agendamento");
        
        // 3. Verificar se pode agendar mais um
        if ($total_convenio_ocupado >= $limite_convenio) {
            // Buscar nome do convênio para mensagem mais amigável
            $query_nome_conv = "SELECT NOME FROM CONVENIOS WHERE ID = ?";
            $stmt_nome_conv = ibase_prepare($conn, $query_nome_conv);
            $result_nome_conv = ibase_execute($stmt_nome_conv, $convenio_id);
            $nome_conv = ibase_fetch_assoc($result_nome_conv);
            $nome_convenio = $nome_conv['NOME'] ?? "Convênio ID $convenio_id";
            
            debug_log("❌ LIMITE CONVÊNIO: $nome_convenio atingiu limite de $limite_convenio agendamentos");
            throw new Exception("O limite de atendimentos para o convênio '$nome_convenio' foi atingido para este dia ($total_convenio_ocupado/$limite_convenio)");
        } else {
            $vagas_restantes_convenio = $limite_convenio - $total_convenio_ocupado;
            debug_log("✅ LIMITE CONVÊNIO: Convênio pode agendar ($total_convenio_ocupado/$limite_convenio - restam $vagas_restantes_convenio vagas)");
        }
    } else {
        debug_log("ℹ️ Convênio $convenio_id não tem limite específico de atendimentos");
    }
    
    debug_log('✅ VERIFICAÇÃO DE LIMITES: Agendamento pode prosseguir');

    // ============================================================================
    // 1.7 BUSCAR INFORMAÇÕES DA AGENDA PARA RESPOSTA
    // ============================================================================

    debug_log('=== BUSCANDO INFORMAÇÕES DA AGENDA ===');

    $query_agenda = "SELECT
        a.ID as AGENDA_ID,
        a.TIPO as TIPO_AGENDA,
        a.MEDICO_ID,
        a.PROCEDIMENTO_ID,
        a.ESPECIALIDADE_ID as AGENDA_ESPECIALIDADE_ID,
        m.NOME as NOME_MEDICO,
        p.NOME as NOME_PROCEDIMENTO,
        u.NOME_UNIDADE,
        a.SALA
    FROM AGENDAS a
    LEFT JOIN LAB_MEDICOS_PRES m ON m.ID = a.MEDICO_ID
    LEFT JOIN GRUPO_EXAMES p ON p.ID = a.PROCEDIMENTO_ID
    LEFT JOIN LAB_CIDADES u ON u.ID = a.UNIDADE_ID
    WHERE a.ID = ?";

    $stmt_agenda = ibase_prepare($conn, $query_agenda);
    $result_agenda = ibase_execute($stmt_agenda, $agenda_id);
    $info_agenda = ibase_fetch_assoc($result_agenda);

    if (!$info_agenda) {
        throw new Exception("Agenda ID $agenda_id não encontrada");
    }

    // Converter encoding
    $nome_medico = $info_agenda['NOME_MEDICO'] ? mb_convert_encoding(trim($info_agenda['NOME_MEDICO']), 'UTF-8', 'Windows-1252') : null;
    $nome_procedimento = $info_agenda['NOME_PROCEDIMENTO'] ? mb_convert_encoding(trim($info_agenda['NOME_PROCEDIMENTO']), 'UTF-8', 'Windows-1252') : null;
    $nome_unidade = $info_agenda['NOME_UNIDADE'] ? mb_convert_encoding(trim($info_agenda['NOME_UNIDADE']), 'UTF-8', 'Windows-1252') : null;
    $tipo_agenda = trim($info_agenda['TIPO_AGENDA']);

    // Buscar nome da especialidade se foi especificada
    $nome_especialidade = null;
    if ($especialidade_id > 0) {
        $query_esp = "SELECT NOME FROM ESPECIALIDADES WHERE ID = ?";
        $stmt_esp = ibase_prepare($conn, $query_esp);
        $result_esp = ibase_execute($stmt_esp, $especialidade_id);
        $esp_data = ibase_fetch_assoc($result_esp);
        if ($esp_data) {
            $nome_especialidade = mb_convert_encoding(trim($esp_data['NOME']), 'UTF-8', 'Windows-1252');
        }
    }

    // ✅ ESPECIALIDADE: Validar contra as especialidades do médico (LAB_MEDICOS_ESPECIALIDADES)
    $medico_id = (int)$info_agenda['MEDICO_ID'];
    $esp_agenda = !empty($info_agenda['AGENDA_ESPECIALIDADE_ID']) ? (int)$info_agenda['AGENDA_ESPECIALIDADE_ID'] : 0;

    if ($medico_id > 0 && $especialidade_id > 0) {
        // Verificar se a especialidade enviada pertence ao médico
        $query_validar_esp = "SELECT COUNT(*) as EXISTE FROM LAB_MEDICOS_ESPECIALIDADES WHERE MEDICO_ID = ? AND ESPECIALIDADE_ID = ?";
        $stmt_validar_esp = ibase_prepare($conn, $query_validar_esp);
        $result_validar_esp = ibase_execute($stmt_validar_esp, $medico_id, $especialidade_id);
        $row_validar = ibase_fetch_assoc($result_validar_esp);

        if ($row_validar['EXISTE'] > 0) {
            // Especialidade é válida para o médico - usar a enviada
            debug_log("✅ ESPECIALIDADE_ID $especialidade_id é válida para o médico $medico_id. Usando a enviada.");
        } else {
            // Especialidade NÃO pertence ao médico - usar a da agenda como fallback
            debug_log("⚠️ ESPECIALIDADE_ID $especialidade_id NÃO pertence ao médico $medico_id. Usando da agenda ($esp_agenda).");
            $especialidade_id = $esp_agenda;
            // Rebuscar nome da especialidade
            if ($especialidade_id > 0) {
                $query_esp2 = "SELECT NOME FROM ESPECIALIDADES WHERE ID = ?";
                $stmt_esp2 = ibase_prepare($conn, $query_esp2);
                $result_esp2 = ibase_execute($stmt_esp2, $especialidade_id);
                $esp_data2 = ibase_fetch_assoc($result_esp2);
                if ($esp_data2) {
                    $nome_especialidade = mb_convert_encoding(trim($esp_data2['NOME']), 'UTF-8', 'Windows-1252');
                }
            }
        }
    } elseif ($especialidade_id <= 0 && $esp_agenda > 0) {
        // Nenhuma especialidade enviada - usar a da agenda
        debug_log("ℹ️ Nenhuma especialidade enviada. Usando da agenda ($esp_agenda).");
        $especialidade_id = $esp_agenda;
        $query_esp2 = "SELECT NOME FROM ESPECIALIDADES WHERE ID = ?";
        $stmt_esp2 = ibase_prepare($conn, $query_esp2);
        $result_esp2 = ibase_execute($stmt_esp2, $especialidade_id);
        $esp_data2 = ibase_fetch_assoc($result_esp2);
        if ($esp_data2) {
            $nome_especialidade = mb_convert_encoding(trim($esp_data2['NOME']), 'UTF-8', 'Windows-1252');
        }
    }

    // ✅ VALIDAÇÃO: Consulta NÃO aceita exames - ignorar exames_ids para agendas de consulta
    if ($tipo_agenda === 'consulta' && count($exames_ids) > 0) {
        debug_log("⚠️ VALIDAÇÃO: Agenda de CONSULTA não aceita exames. Ignorando " . count($exames_ids) . " exames recebidos: " . implode(',', $exames_ids));
        $exames_ids = [];
        $exames_ids_raw = '';
    }

    debug_log("✅ Agenda encontrada: Tipo=$tipo_agenda, Médico=$nome_medico, Procedimento=$nome_procedimento, Especialidade=$especialidade_id");

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
    $tem_rua = campo_existe_na_tabela($conn, 'LAB_PACIENTES', 'RUA');
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
    debug_log("- RUA: " . ($tem_rua ? 'SIM' : 'NÃO'));
    debug_log("- NUMERO: " . ($tem_numero ? 'SIM' : 'NÃO'));
    debug_log("- COMPLEMENTO: " . ($tem_complemento ? 'SIM' : 'NÃO'));
    debug_log("- BAIRRO: " . ($tem_bairro ? 'SIM' : 'NÃO'));
    debug_log("- CIDADE: " . ($tem_cidade ? 'SIM' : 'NÃO'));
    debug_log("- UF: " . ($tem_uf ? 'SIM' : 'NÃO'));
    debug_log("- CEP: " . ($tem_cep ? 'SIM' : 'NÃO'));
    
    // ============================================================================
    // 3. GERAR NÚMERO DO AGENDAMENTO
    // ============================================================================
    
    // Iniciar transação
    $trans = ibase_trans($conn);
    
    try {
        // Buscar o próximo número sequencial para agendamento normal
        $query_max_numero = "SELECT MAX(CAST(SUBSTRING(NUMERO_AGENDAMENTO FROM 5) AS INTEGER)) as MAX_NUM 
                             FROM AGENDAMENTOS 
                             WHERE NUMERO_AGENDAMENTO LIKE 'AGD-%'";
        
        $result_max = ibase_query($trans, $query_max_numero);
        $max_row = ibase_fetch_assoc($result_max);
        
        $proximo_numero = ((int)($max_row['MAX_NUM'] ?? 0)) + 1;
        $numero_agendamento = 'AGD-' . str_pad($proximo_numero, 4, '0', STR_PAD_LEFT);
        
        debug_log("✅ NUMERO_AGENDAMENTO gerado: $numero_agendamento");

        // ✅ LIMPEZA PREVENTIVA: Remover registros órfãos de AGENDAMENTO_EXAMES
        // (podem existir de agendamentos antigos que usaram o mesmo NUMERO_AGENDAMENTO)
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
        // 4. DETERMINAR TIPO DE OPERAÇÃO E PACIENTE_ID
        // ============================================================================
        
        debug_log('=== INÍCIO DA LÓGICA DE DETERMINAÇÃO DO PACIENTE_ID ===');
        
        // ✅ INICIALIZAÇÃO FORÇADA COMO NULL
        $paciente_id_final = null;
        $paciente_nome_final = $nome_paciente;
        $tipo_operacao = 'sem_cadastro';
        $endereco_salvo = false;
        
        debug_log("STEP 1: Inicialização - PACIENTE_ID = NULL, tipo = sem_cadastro");
        
        // ✅ CENÁRIO 1: PACIENTE EXISTENTE
        if ($usar_paciente_existente && $paciente_selecionado_id > 0) {
            debug_log("STEP 2A: Entrando no cenário PACIENTE EXISTENTE");
            debug_log("STEP 2A: usar_paciente_existente = TRUE, paciente_selecionado_id = $paciente_selecionado_id");
            
            $query_paciente = "SELECT IDPACIENTE, PACIENTE FROM LAB_PACIENTES WHERE IDPACIENTE = ?";
            $stmt_paciente = ibase_prepare($trans, $query_paciente);
            $result_paciente = ibase_execute($stmt_paciente, $paciente_selecionado_id);
            $paciente_existente = ibase_fetch_assoc($result_paciente);
            
            if ($paciente_existente) {
                $paciente_id_final = $paciente_selecionado_id;
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
            debug_log("STEP 2B: deve_cadastrar_paciente = TRUE");
            
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
            // Para banco Windows-1252, usar iconv em vez de mb_convert_encoding
            $nome_convertido = iconv('UTF-8', 'Windows-1252//IGNORE', $nome_paciente);
            $valores_paciente = [$novo_id, $nome_convertido, $telefone_limpo];
            
            // Adicionar CPF somente se fornecido
            if (!empty($cpf_limpo)) {
                $campos_paciente[] = 'CPF';
                $valores_paciente[] = $cpf_limpo;
            }
            
            // Adicionar campos opcionais se preenchidos e se existem na tabela
            if (!empty($email_paciente)) {
                $campos_paciente[] = 'EMAIL';
                $email_convertido = iconv('UTF-8', 'Windows-1252//IGNORE', $email_paciente);
                $valores_paciente[] = $email_convertido;
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
            // Priorizar campo RUA se existir, senão usar ENDERECO
            if (!empty($endereco)) {
                if ($tem_rua) {
                    $campos_paciente[] = 'RUA';
                    $endereco_convertido = iconv('UTF-8', 'Windows-1252//IGNORE', $endereco);
                    $valores_paciente[] = $endereco_convertido;
                    $endereco_salvo = true;
                } elseif ($tem_endereco) {
                    $campos_paciente[] = 'ENDERECO';
                    $endereco_convertido = iconv('UTF-8', 'Windows-1252//IGNORE', $endereco);
                    $valores_paciente[] = $endereco_convertido;
                    $endereco_salvo = true;
                }
            }
            
            if ($tem_numero && !empty($numero)) {
                $campos_paciente[] = 'NUMERO';
                $valores_paciente[] = $numero;
                $endereco_salvo = true;
            }
            
            if ($tem_complemento && !empty($complemento)) {
                $campos_paciente[] = 'COMPLEMENTO';
                $complemento_convertido = iconv('UTF-8', 'Windows-1252//IGNORE', $complemento);
                $valores_paciente[] = $complemento_convertido;
            }
            
            if ($tem_bairro && !empty($bairro)) {
                $campos_paciente[] = 'BAIRRO';
                $bairro_convertido = iconv('UTF-8', 'Windows-1252//IGNORE', $bairro);
                $valores_paciente[] = $bairro_convertido;
                $endereco_salvo = true;
            }
            
            if ($tem_cidade && !empty($cidade)) {
                $campos_paciente[] = 'CIDADE';
                $cidade_convertida = iconv('UTF-8', 'Windows-1252//IGNORE', $cidade);
                $valores_paciente[] = $cidade_convertida;
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
            
        // ✅ CENÁRIO 3: SEM CADASTRO
        } else {
            debug_log("STEP 2C: Entrando no cenário SEM CADASTRO");
            $paciente_id_final = null;
            $tipo_operacao = 'sem_cadastro';
        }
        
        // ============================================================================
        // 5. INSERIR AGENDAMENTO
        // ============================================================================
        
        debug_log('Preparando inserção do agendamento...');
        debug_log("PACIENTE_ID que será usado: " . ($paciente_id_final !== null ? $paciente_id_final : 'NULL'));
        debug_log("HORA_AGENDAMENTO que será inserida: $hora_agendamento_final");
        
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
            // Converter nome para Windows-1252 para compatibilidade com banco
            $nome_agendamento_convertido = iconv('UTF-8', 'Windows-1252//IGNORE', $paciente_nome_final);
            $valores_insert[] = $nome_agendamento_convertido;
        }
        
        // ✅ TELEFONE_PACIENTE - limitado a 15 caracteres
        if ($tem_telefone_paciente && !empty($telefone_paciente)) {
            $telefone_truncado = substr($telefone_paciente, 0, 15);
            $campos_insert[] = 'TELEFONE_PACIENTE';
            $valores_insert[] = $telefone_truncado;
            debug_log("TELEFONE_PACIENTE inserindo: $telefone_truncado");
            if (strlen($telefone_paciente) > 15) {
                debug_log("⚠️ Telefone truncado de " . strlen($telefone_paciente) . " para 15 caracteres");
            }
        }
        
        // ✅ TIPO_AGENDAMENTO - NORMAL ao invés de ENCAIXE
        if ($tem_tipo_agendamento) {
            $campos_insert[] = 'TIPO_AGENDAMENTO';
            $valores_insert[] = 'NORMAL';
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
            $observacoes_finais = "AGENDAMENTO - Dados: $paciente_nome_final | Tel: $telefone_paciente";
            if (!empty($observacoes)) {
                $observacoes_finais .= " | Obs: $observacoes";
            }
            if (count($exames_ids) > 0) {
                $observacoes_finais .= " | Exames IDs: " . implode(',', $exames_ids) . 
                                     " (Total: " . count($exames_ids) . ")";
            }
        } else {
            $observacoes_finais = !empty($observacoes) ? $observacoes : 'Agendamento normal';
            if (count($exames_ids) > 0) {
                $observacoes_finais .= " | Exames IDs: " . implode(',', $exames_ids) . 
                                     " (Total: " . count($exames_ids) . ")";
            }
        }
        
        // ✅ Adicionar especialidade_id se fornecida (para consultas)
        if ($especialidade_id > 0) {
            $campos_insert[] = 'ESPECIALIDADE_ID';
            $valores_insert[] = $especialidade_id;
            debug_log('Adicionando ESPECIALIDADE_ID ao agendamento: ' . $especialidade_id);
        }
        
        // ✅ IDADE - Adicionar se fornecida
        if ($idade > 0) {
            $campos_insert[] = 'IDADE';
            $valores_insert[] = $idade;
            debug_log("💾 IDADE inserindo: $idade anos");
        }
        
        // ✅ TIPO_CONSULTA - Adicionar se fornecido (apenas para consultas)
        if (!empty($tipo_consulta)) {
            $campos_insert[] = 'TIPO_CONSULTA';
            $valores_insert[] = $tipo_consulta;
            debug_log("💾 TIPO_CONSULTA inserindo: $tipo_consulta");
        }
        
        // ✅ CONFIRMADO - Status de confirmação (sempre inserir)
        $campos_insert[] = 'CONFIRMADO';
        $valores_insert[] = $confirmado;
        debug_log("💾 CONFIRMADO inserindo: " . ($confirmado ? 'Sim' : 'Não'));
        
        // ✅ TIPO_ATENDIMENTO - Tipo de atendimento (sempre inserir)
        $campos_insert[] = 'TIPO_ATENDIMENTO';
        $valores_insert[] = $tipo_atendimento;
        debug_log("💾 TIPO_ATENDIMENTO inserindo: $tipo_atendimento");

        // ✅ PRECISA_SEDACAO - Se o paciente precisa de sedação/anestesia (sempre inserir)
        $campos_insert[] = 'PRECISA_SEDACAO';
        $valores_insert[] = $precisa_sedacao;
        debug_log("💉 PRECISA_SEDACAO inserindo: $precisa_sedacao");

        $campos_insert[] = 'OBSERVACOES';
        // Converter observações para Windows-1252
        $observacoes_convertidas = iconv('UTF-8', 'Windows-1252//IGNORE', $observacoes_finais);
        $valores_insert[] = $observacoes_convertidas;
        
        // ============================================================================
        // 6. EXECUTAR INSERÇÃO DO AGENDAMENTO
        // ============================================================================
        
        $placeholders = str_repeat('?,', count($valores_insert));
        $placeholders = rtrim($placeholders, ',');
        
        $query_insert = "INSERT INTO AGENDAMENTOS (" . implode(', ', $campos_insert) . ") 
                         VALUES ($placeholders) RETURNING ID";
        
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
        
        // Obter ID do agendamento criado
        $agendamento_data = ibase_fetch_assoc($result_insert);
        if (!$agendamento_data || !isset($agendamento_data['ID'])) {
            throw new Exception("Erro ao obter ID do agendamento criado");
        }
        
        $agendamento_id = $agendamento_data['ID'];
        debug_log("✅ Agendamento inserido com ID: $agendamento_id");
        
        // ============================================================================
        // REGISTRAR AUDITORIA DE CRIAÇÃO DE AGENDAMENTO
        // ============================================================================
        
        // Obter usuário atual
        $usuario_atual = $_COOKIE['log_usuario'] ?? 'SISTEMA_WEB';
        
        try {
            // Buscar dados completos do agendamento recém-criado para auditoria
            $dados_agendamento_criado = buscarDadosCompletosAgendamento($conn, $agendamento_id);
            
            if ($dados_agendamento_criado) {
                // Preparar observações detalhadas para auditoria
                $observacoes_auditoria = sprintf(
                    "Novo agendamento criado via formulário web. " .
                    "Paciente: %s (%s). " .
                    "Data/Hora: %s às %s. " .
                    "Convênio: %s. " .
                    "Tipo: %s. " .
                    "Total de exames: %d",
                    $dados_agendamento_criado['NOME_PACIENTE'] ?? $paciente_nome_final,
                    $tipo_operacao === 'novo_cadastro' ? 'NOVO CADASTRO' : 
                    ($tipo_operacao === 'paciente_existente' ? 'PACIENTE EXISTENTE' : 'SEM CADASTRO'),
                    date('d/m/Y', strtotime($data_agendamento)),
                    substr($hora_agendamento_final, 0, 5),
                    $dados_agendamento_criado['NOME_CONVENIO'] ?? 'N/A',
                    'NORMAL',
                    count($exames_ids)
                );
                
                // Adicionar informações sobre endereço se foi cadastrado
                if ($tipo_operacao === 'novo_cadastro' && $endereco_salvo) {
                    $observacoes_auditoria .= " | Endereço completo cadastrado";
                }
                
                // Registrar auditoria completa
                $resultado_auditoria = auditarAgendamentoCompleto(
                    $conn,
                    'CRIAR',
                    $usuario_atual,
                    $dados_agendamento_criado,
                    null, // Não há dados anteriores em uma criação
                    $observacoes_auditoria,
                    [
                        'metodo_criacao' => 'formulario_web',
                        'tipo_operacao' => $tipo_operacao,
                        'endereco_salvo' => $endereco_salvo,
                        'exames_quantidade' => count($exames_ids),
                        'exames_ids' => implode(',', $exames_ids),
                        'convenio_id' => $convenio_id,
                        'especialidade_id' => $especialidade_id,
                        'telefone_paciente' => $telefone_paciente,
                        'ip_criacao' => obterIPUsuario(),
                        'timestamp_criacao' => date('Y-m-d H:i:s')
                    ]
                );
                
                if ($resultado_auditoria) {
                    debug_log("✅ Auditoria de criação registrada com sucesso");
                } else {
                    debug_log("❌ AVISO: Falha ao registrar auditoria de criação para agendamento ID $agendamento_id");
                }
            } else {
                debug_log("❌ AVISO: Não foi possível buscar dados do agendamento para auditoria");
                $resultado_auditoria = false;
            }
        } catch (Exception $e_auditoria) {
            debug_log("❌ ERRO na auditoria (não crítico): " . $e_auditoria->getMessage());
            $resultado_auditoria = false;
            // Não interrompe o processo se auditoria falhar
        }
        
        // ============================================================================
        // 7. SALVAR EXAMES EM TABELA DE RELACIONAMENTO (se necessário)
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

                $debug_trace_exames['insercoes_bd'] = []; // 🔍 Rastrear inserções no banco

                foreach ($exames_ids as $exame_id) {
                    try {
                        $query_rel = "INSERT INTO AGENDAMENTO_EXAMES (NUMERO_AGENDAMENTO, EXAME_ID) VALUES (?, ?)";
                        $stmt_rel = ibase_prepare($trans, $query_rel);
                        $result_rel = ibase_execute($stmt_rel, $numero_agendamento, $exame_id);

                        if ($result_rel) {
                            debug_log("✅ Relacionamento inserido: {$numero_agendamento} <-> {$exame_id}");
                            $debug_trace_exames['insercoes_bd'][] = [  // 🔍 Registrar sucesso
                                'exame_id' => $exame_id,
                                'status' => 'SUCESSO'
                            ];
                        } else {
                            debug_log("❌ Erro ao inserir relacionamento: {$numero_agendamento} <-> {$exame_id}");
                            $debug_trace_exames['insercoes_bd'][] = [  // 🔍 Registrar erro
                                'exame_id' => $exame_id,
                                'status' => 'ERRO',
                                'motivo' => 'ibase_execute retornou false'
                            ];
                        }
                    } catch (Exception $e) {
                        debug_log("❌ Erro na inserção do relacionamento {$exame_id}: " . $e->getMessage());
                        $debug_trace_exames['insercoes_bd'][] = [  // 🔍 Registrar exceção
                            'exame_id' => $exame_id,
                            'status' => 'EXCECAO',
                            'motivo' => $e->getMessage()
                        ];
                    }
                }
            } else {
                debug_log("⚠️ Tabela AGENDAMENTO_EXAMES não existe - exames salvos apenas nas observações");
                $debug_trace_exames['insercoes_bd'] = 'TABELA_NAO_EXISTE'; // 🔍
            }
        } else if (count($exames_ids) > 0 && $tem_exames_ids) {
            debug_log("✅ Exames salvos no campo EXAMES_IDS - tabela de relacionamento não necessária");
        }
        
        // ============================================================================
        // 8. CONFIRMAR TRANSAÇÃO E RESPOSTA
        // ============================================================================
        
        ibase_commit($trans);
        debug_log('✅ Transação confirmada com sucesso');

        // 🔍 VERIFICAR O QUE FOI REALMENTE SALVO NO BANCO DE DADOS
        try {
            $query_verificar = "SELECT ae.EXAME_ID, ex.EXAME
                               FROM AGENDAMENTO_EXAMES ae
                               LEFT JOIN LAB_EXAMES ex ON ex.IDEXAME = ae.EXAME_ID
                               WHERE ae.NUMERO_AGENDAMENTO = ?
                               ORDER BY ae.EXAME_ID";
            $stmt_verificar = ibase_prepare($conn, $query_verificar);
            $result_verificar = ibase_execute($stmt_verificar, $numero_agendamento);

            $debug_trace_exames['exames_salvos_bd'] = [];
            while ($row_verif = ibase_fetch_assoc($result_verificar)) {
                $debug_trace_exames['exames_salvos_bd'][] = [
                    'exame_id' => (int)$row_verif['EXAME_ID'],
                    'exame_nome' => trim($row_verif['EXAME'] ?? 'NOME_NAO_ENCONTRADO')
                ];
            }

            $debug_trace_exames['total_salvo_bd'] = count($debug_trace_exames['exames_salvos_bd']);
            debug_log('🔍 Verificação BD: ' . count($debug_trace_exames['exames_salvos_bd']) . ' exames encontrados');
        } catch (Exception $e) {
            $debug_trace_exames['erro_verificacao_bd'] = $e->getMessage();
            debug_log('❌ Erro ao verificar exames salvos: ' . $e->getMessage());
        }

        // Preparar mensagem de sucesso
        $mensagens = [
            'paciente_existente' => 'Agendamento realizado com sucesso! Paciente vinculado ao cadastro existente.',
            'novo_cadastro' => 'Paciente cadastrado e agendamento realizado com sucesso!',
            'sem_cadastro' => 'Agendamento realizado com sucesso! Dados salvos no agendamento.'
        ];
        
        // ✅ ADICIONAR INFORMAÇÃO SOBRE ENDEREÇO NA RESPOSTA
        if ($tipo_operacao === 'novo_cadastro') {
            if ($endereco_salvo) {
                $mensagens['novo_cadastro'] = str_replace('cadastrado e', 'cadastrado com endereço completo e', $mensagens['novo_cadastro']);
            }
        }
        
        // Montar informações da agenda para resposta
        $info_agenda_response = [
            'agenda_id' => $agenda_id,
            'tipo_agenda' => $tipo_agenda
        ];

        if ($tipo_agenda === 'consulta') {
            $info_agenda_response['medico'] = $nome_medico;
            if ($nome_especialidade) {
                $info_agenda_response['especialidade'] = $nome_especialidade;
            }
        } elseif ($tipo_agenda === 'procedimento') {
            $info_agenda_response['procedimento'] = $nome_procedimento;
            if ($nome_medico) {
                $info_agenda_response['medico'] = $nome_medico;
            }
        }

        if ($nome_unidade) {
            $info_agenda_response['unidade'] = $nome_unidade;
        }

        $response = [
            'status' => 'sucesso',
            'mensagem' => $mensagens[$tipo_operacao],
            'agendamento_id' => $agendamento_id,
            'numero_agendamento' => $numero_agendamento,
            'agenda' => $info_agenda_response,
            'paciente_id' => $paciente_id_final,
            'paciente_nome' => $paciente_nome_final,
            'paciente_telefone' => $telefone_paciente,
            'tipo_operacao' => $tipo_operacao,
            'paciente_cadastrado' => ($tipo_operacao === 'novo_cadastro'),
            'paciente_existente_usado' => ($tipo_operacao === 'paciente_existente'),
            'agendamento_sem_cadastro' => ($tipo_operacao === 'sem_cadastro'),
            'endereco_salvo' => $endereco_salvo,
            'exames_ids' => $exames_ids,
            'exames_quantidade' => count($exames_ids),
            'exames_selecionados' => count($exames_ids) > 0,
            'horario_agendamento' => $hora_agendamento_final,
            'data_agendamento' => $data_agendamento,
            'tipo' => 'AGENDAMENTO',
            'data_criacao' => date('Y-m-d H:i:s'),
            'auditoria_registrada' => isset($resultado_auditoria) ? ($resultado_auditoria ? 'sim' : 'nao') : 'nao',
            'usuario_criacao' => $usuario_atual,
            'debug_exames_processamento' => $debug_trace_exames  // 🔍 DEBUG: Rastreamento completo do processamento dos exames
        ];
        
        debug_log('✅ Resposta de sucesso preparada');
        debug_log("Endereço foi salvo: " . ($endereco_salvo ? 'SIM' : 'NÃO'));
        debug_log("PACIENTE_ID final na resposta: " . ($paciente_id_final ?? 'NULL'));
        debug_log("HORÁRIO final na resposta: $hora_agendamento_final");
        debug_log("EXAMES processados: " . (count($exames_ids) > 0 ? count($exames_ids) . ' exames - IDs: ' . implode(',', $exames_ids) : 'NENHUM'));
        debug_log("Campo EXAMES_IDS disponível: " . ($tem_exames_ids ? 'SIM' : 'NÃO'));
        debug_log("Campo EXAME_ID disponível: " . ($tem_exame_id ? 'SIM' : 'NÃO'));
        
        // ============================================================================
        // INTEGRAÇÃO WHATSAPP - Criar confirmação automática (TEMPORARIAMENTE DESABILITADO)
        // ============================================================================
        /*
        try {
            include_once 'whatsapp_hooks.php';
            
            $agendamentoWhatsApp = [
                'id' => $agendamento_id,
                'numero' => $numero_agendamento,
                'data_agendamento' => $data_agendamento,
                'hora_agendamento' => $hora_agendamento_final,
                'telefone' => $telefone_paciente ?? ''
            ];
            
            $resultadoWhatsApp = processarHookAgendamento('criar', $agendamentoWhatsApp);
            debug_log("WhatsApp Hook resultado: $resultadoWhatsApp");
            
        } catch (Exception $e_whatsapp) {
            debug_log("Erro no hook WhatsApp (não crítico): " . $e_whatsapp->getMessage());
            // Não interrompe o processo se WhatsApp falhar
        }
        */
        debug_log("WhatsApp Hook: temporariamente desabilitado para debug");
        
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
            'linha_erro' => $e->getLine()
        ]
    ];
    
    ob_clean();
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
}

debug_log('=== FIM processar_agendamento.php - BASEADO EM ENCAIXE ===');

if (ob_get_level()) {
    ob_end_flush();
}
exit;
?>