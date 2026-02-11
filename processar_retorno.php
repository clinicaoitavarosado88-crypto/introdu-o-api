<?php
// processar_retorno.php
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-cache, must-revalidate');

include 'includes/connection.php';
include 'includes/auditoria.php';

try {
    // Verificar se é POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método não permitido');
    }
    
    // Coletar dados do formulário
    $agenda_id = (int)($_POST['agenda_id'] ?? 0);
    $data_agendamento = $_POST['data_agendamento'] ?? '';
    $horario_agendamento = $_POST['horario_agendamento'] ?? '00:00:00';
    $nome_paciente = trim($_POST['nome_paciente'] ?? '');
    $telefone_paciente = trim($_POST['telefone_paciente'] ?? '');
    $convenio_id = (int)($_POST['convenio_id'] ?? 0);
    $observacoes = trim($_POST['observacoes'] ?? '');
    
    // ✅ Especialidade selecionada (para médicos com múltiplas especialidades)
    $especialidade_id = (int)($_POST['especialidade_id'] ?? 0);
    error_log('[RETORNO] Especialidade ID selecionada: ' . $especialidade_id);
    
    // Dados adicionais do paciente
    $cpf_paciente = trim($_POST['cpf_paciente'] ?? '');
    $rg_paciente = trim($_POST['rg_paciente'] ?? '');
    $sexo_paciente = trim($_POST['sexo_paciente'] ?? '');
    $nascimento_paciente = $_POST['nascimento_paciente'] ?? '';
    $email_paciente = trim($_POST['email_paciente'] ?? '');
    $cep_paciente = trim($_POST['cep_paciente'] ?? '');
    $endereco_paciente = trim($_POST['endereco_paciente'] ?? '');
    
    // Validação básica
    if (!$agenda_id) {
        throw new Exception('ID da agenda é obrigatório');
    }
    
    if (!$data_agendamento) {
        throw new Exception('Data do agendamento é obrigatória');
    }
    
    if (!$nome_paciente) {
        throw new Exception('Nome do paciente é obrigatório');
    }
    
    if (!$telefone_paciente) {
        throw new Exception('Telefone é obrigatório');
    }
    
    if (!$convenio_id) {
        throw new Exception('Convênio é obrigatório');
    }
    
    // Verificar se a agenda permite retornos
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
        throw new Exception('Agenda não encontrada');
    }
    
    if ($agenda['TIPO'] !== 'consulta') {
        throw new Exception('Retornos só são permitidos para agendas de consulta');
    }
    
    $limite_retornos = (int)$agenda['LIMITE_RETORNOS_DIA'];
    
    if ($limite_retornos <= 0) {
        throw new Exception('Limite de retornos não configurado (deve ser maior que 0)');
    }
    
    // Se não possui retorno configurado mas tem limite, permitir mesmo assim
    
    // Verificar disponibilidade
    $query_count = "SELECT COUNT(*) as TOTAL 
                    FROM AGENDAMENTOS 
                    WHERE AGENDA_ID = ? 
                    AND DATA_AGENDAMENTO = ? 
                    AND TIPO_AGENDAMENTO = 'RETORNO'
                    AND STATUS NOT IN ('CANCELADO', 'FALTOU')";
    
    $stmt_count = ibase_prepare($conn, $query_count);
    $result_count = ibase_execute($stmt_count, $agenda_id, $data_agendamento);
    $row_count = ibase_fetch_assoc($result_count);
    
    $retornos_ocupados = (int)$row_count['TOTAL'];
    
    if ($retornos_ocupados >= $limite_retornos) {
        throw new Exception('Limite de retornos atingido para esta data');
    }
    
    // Buscar ou criar paciente
    $paciente_id = null;
    
    // Tentar encontrar paciente existente pelo nome
    $query_paciente = "SELECT IDPACIENTE 
                       FROM LAB_PACIENTES 
                       WHERE UPPER(TRIM(PACIENTE)) = UPPER(TRIM(?))
                       ORDER BY IDPACIENTE DESC
                       ROWS 1";
    
    $stmt_paciente = ibase_prepare($conn, $query_paciente);
    $result_paciente = ibase_execute($stmt_paciente, $nome_paciente);
    
    if ($row_paciente = ibase_fetch_assoc($result_paciente)) {
        $paciente_id = $row_paciente['IDPACIENTE'];
    }
    
    // Gerar número único de agendamento
    $numero_agendamento = 'R' . date('YmdHis') . str_pad($agenda_id, 3, '0', STR_PAD_LEFT);
    
    // Inserir agendamento de retorno
    $campos_retorno = [
        'NUMERO_AGENDAMENTO',
        'AGENDA_ID', 
        'DATA_AGENDAMENTO',
        'HORA_AGENDAMENTO',
        'PACIENTE_ID',
        'NOME_PACIENTE',
        'TELEFONE_PACIENTE',
        'CONVENIO_ID',
        'STATUS',
        'TIPO_AGENDAMENTO',
        'TIPO_CONSULTA',
        'OBSERVACOES',
        'DATA_CRIACAO'
    ];
    
    $valores_retorno = [
        $numero_agendamento,
        $agenda_id,
        $data_agendamento,
        $horario_agendamento,
        $paciente_id,
        $nome_paciente,
        $telefone_paciente,
        $convenio_id,
        'AGENDADO',
        'RETORNO',
        'retorno',
        $observacoes,
        date('Y-m-d H:i:s')
    ];
    
    // ✅ Adicionar especialidade_id se fornecida (para consultas)
    if ($especialidade_id > 0) {
        $campos_retorno[] = 'ESPECIALIDADE_ID';
        $valores_retorno[] = $especialidade_id;
        error_log('[RETORNO] Adicionando ESPECIALIDADE_ID: ' . $especialidade_id);
    }
    
    $placeholders = str_repeat('?,', count($valores_retorno));
    $placeholders = rtrim($placeholders, ',');
    
    $query_insert = "INSERT INTO AGENDAMENTOS (" . implode(', ', $campos_retorno) . ") 
                     VALUES ($placeholders)";
    
    $stmt_insert = ibase_prepare($conn, $query_insert);
    $result_insert = ibase_execute($stmt_insert, ...$valores_retorno);
    
    if (!$result_insert) {
        throw new Exception('Erro ao salvar agendamento de retorno');
    }
    
    // ============================================================================
    // AUDITORIA COMPLETA DO RETORNO CRIADO
    // ============================================================================
    
    // Buscar informações do usuário atual
    $usuario_atual = isset($_COOKIE['log_usuario']) ? $_COOKIE['log_usuario'] : 'RENISON';
    
    // Buscar dados completos do retorno para auditoria
    $query_buscar_retorno = "SELECT 
                               a.*, 
                               COALESCE(a.NOME_PACIENTE) as NOME_COMPLETO_PACIENTE,
                               c.NOME as NOME_CONVENIO,
                               ag.TIPO_AGENDA as NOME_AGENDA,
                               m.NOME as NOME_MEDICO
                             FROM AGENDAMENTOS a
                             LEFT JOIN CONVENIOS c ON a.CONVENIO_ID = c.ID
                             LEFT JOIN AGENDAS ag ON a.AGENDA_ID = ag.ID
                             LEFT JOIN LAB_MEDICOS_PRES m ON ag.MEDICO_ID = m.ID
                             WHERE a.NUMERO_AGENDAMENTO = ?";
    
    $stmt_buscar_retorno = ibase_prepare($conn, $query_buscar_retorno);
    $result_buscar_retorno = ibase_execute($stmt_buscar_retorno, $numero_agendamento);
    
    if ($result_buscar_retorno && $row_retorno = ibase_fetch_assoc($result_buscar_retorno)) {
        // Preparar dados para auditoria
        $dados_retorno_criado = [
            'id' => $row_retorno['ID'],
            'numero_agendamento' => $numero_agendamento,
            'paciente_id' => null, // Retornos podem não ter paciente_id
            'paciente' => utf8_encode($row_retorno['NOME_COMPLETO_PACIENTE'] ?? $nome_paciente),
            'agenda_id' => $agenda_id,
            'nome_agenda' => utf8_encode($row_retorno['NOME_AGENDA'] ?? ''),
            'nome_medico' => utf8_encode($row_retorno['NOME_MEDICO'] ?? ''),
            'convenio_id' => $convenio_id,
            'nome_convenio' => utf8_encode($row_retorno['NOME_CONVENIO'] ?? ''),
            'data_agendamento' => $data_agendamento,
            'hora_agendamento' => $horario_agendamento,
            'tipo_agendamento' => 'RETORNO',
            'telefone' => $telefone_paciente,
            'cpf' => $cpf_paciente,
            'email' => $email_paciente,
            'observacoes' => utf8_encode($observacoes ?? ''),
            'especialidade_id' => $especialidade_id
        ];
        
        // Preparar observações para auditoria
        $observacoes_auditoria = sprintf(
            "RETORNO criado: %s agendado para %s às %s - %s - Especialidade: %d",
            $dados_retorno_criado['paciente'],
            date('d/m/Y', strtotime($data_agendamento)),
            substr($horario_agendamento, 0, 5),
            $dados_retorno_criado['nome_convenio'] ?? 'N/A',
            $especialidade_id
        );
        
        // Registrar auditoria completa
        $resultado_auditoria = auditarAgendamentoCompleto(
            $conn,
            'CRIAR_RETORNO',
            $usuario_atual,
            $dados_retorno_criado,
            null, // Não há dados anteriores em uma criação
            $observacoes_auditoria,
            [
                'metodo_criacao' => 'formulario_web_retorno',
                'especialidade_id' => $especialidade_id,
                'convenio_id' => $convenio_id,
                'telefone_paciente' => $telefone_paciente,
                'cpf_paciente' => $cpf_paciente,
                'email_paciente' => $email_paciente,
                'ip_criacao' => obterIPUsuario(),
                'timestamp_criacao' => date('Y-m-d H:i:s')
            ]
        );
        
        if ($resultado_auditoria) {
            error_log("✅ Auditoria de criação de RETORNO registrada com sucesso");
        } else {
            error_log("❌ AVISO: Falha ao registrar auditoria de criação de RETORNO para número $numero_agendamento");
        }
    } else {
        error_log("❌ AVISO: Não foi possível buscar dados do retorno para auditoria");
    }
    
    // Commit da transação
    ibase_commit($conn);
    
    // Resposta de sucesso
    echo json_encode([
        'status' => 'sucesso',
        'mensagem' => 'Retorno agendado com sucesso',
        'numero_agendamento' => $numero_agendamento,
        'auditoria_registrada' => isset($resultado_auditoria) ? ($resultado_auditoria ? 'sim' : 'nao') : 'nao',
        'data' => [
            'agenda_id' => $agenda_id,
            'data_agendamento' => $data_agendamento,
            'nome_paciente' => $nome_paciente,
            'telefone_paciente' => $telefone_paciente,
            'numero_agendamento' => $numero_agendamento
        ]
    ]);
    
} catch (Exception $e) {
    // Rollback em caso de erro
    if (isset($conn)) {
        ibase_rollback($conn);
    }
    
    echo json_encode([
        'status' => 'erro',
        'mensagem' => $e->getMessage()
    ]);
}
?>