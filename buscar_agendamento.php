<?php
// buscar_agendamento.php

// Verificação de autenticação por token (exceto para CLI)
if (php_sapi_name() !== 'cli') {
    include 'includes/auth_middleware.php';
}

header('Content-Type: application/json');
include 'includes/connection.php';

// Função para ler BLOBs de observações
function lerBlobObservacoes($conn, $blobId) {
    if (!is_string($blobId) || empty($blobId) || $blobId === '0x0000000000000000') {
        return '';
    }
    try {
        $blob = ibase_blob_open($conn, $blobId);
        $conteudo = '';
        while ($segmento = ibase_blob_get($blob, 4096)) {
            $conteudo .= $segmento;
        }
        ibase_blob_close($blob);
        return utf8_encode(trim($conteudo));
    } catch (Exception $e) {
        return '';
    }
}

// Função para converter texto para UTF-8 seguro
function safe_utf8($str) {
    if (!$str) return '';
    // Se já está em UTF-8, retorna como está
    if (mb_check_encoding($str, 'UTF-8')) {
        return $str;
    }
    // Tenta converter de ISO-8859-1/Windows-1252 para UTF-8
    return mb_convert_encoding($str, 'UTF-8', 'ISO-8859-1,Windows-1252');
}

// Suporte para linha de comando
if (php_sapi_name() === 'cli') {
    parse_str(getenv('QUERY_STRING') ?: '', $_GET);
}

$agendamento_id = $_GET['id'] ?? '';
$agenda_id = $_GET['agenda_id'] ?? '';

// Se for solicitação apenas para buscar preparos
if ($agendamento_id == '0' && $agenda_id) {
    try {
        $query_preparos = "SELECT 
                              TITULO,
                              INSTRUCOES,
                              ORDEM
                           FROM AGENDA_PREPAROS
                           WHERE AGENDA_ID = ?
                           ORDER BY ORDEM";
        
        $stmt_preparos = ibase_prepare($conn, $query_preparos);
        $result_preparos = ibase_execute($stmt_preparos, $agenda_id);
        
        $preparos = [];
        while ($row_preparo = ibase_fetch_assoc($result_preparos)) {
            $preparos[] = [
                'titulo' => utf8_encode(trim($row_preparo['TITULO'])),
                'instrucoes' => lerBlobObservacoes($conn, $row_preparo['INSTRUCOES']),
                'ordem' => (int)$row_preparo['ORDEM']
            ];
        }
        
        echo json_encode(['preparos' => $preparos]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['erro' => 'Erro ao buscar preparos: ' . $e->getMessage()]);
        exit;
    }
}

if (!$agendamento_id) {
    echo json_encode(['erro' => 'ID do agendamento não fornecido']);
    exit;
}

try {
    // Buscar dados completos do agendamento - usando exatamente a mesma estrutura de buscar_agendamentos_dia.php
    $query = "SELECT ag.*,
                     COALESCE(p.PACIENTE, ag.NOME_PACIENTE) as PACIENTE_NOME,
                     COALESCE(p.FONE1, ag.TELEFONE_PACIENTE) as PACIENTE_TELEFONE,
                     p.CPF as PACIENTE_CPF,
                     p.ANIVERSARIO as PACIENTE_NASCIMENTO,
                     p.EMAIL as PACIENTE_EMAIL,
                     c.NOME as CONVENIO_NOME,
                     a.SALA as AGENDA_SALA,
                     a.TELEFONE as AGENDA_TELEFONE,
                     u.NOME_UNIDADE as UNIDADE_NOME,
                     med.NOME as MEDICO_NOME,
                     COALESCE(esp_salva.NOME, 
                              CASE WHEN a.TIPO = 'consulta' THEN 
                                   (SELECT FIRST 1 e2.NOME FROM LAB_MEDICOS_ESPECIALIDADES me2 
                                    JOIN ESPECIALIDADES e2 ON e2.ID = me2.ESPECIALIDADE_ID 
                                    WHERE me2.MEDICO_ID = med.ID)
                              ELSE NULL END) as ESPECIALIDADE_NOME,
                     ge.NOME as PROCEDIMENTO_NOME
              FROM AGENDAMENTOS ag
              LEFT JOIN LAB_PACIENTES p ON ag.PACIENTE_ID = p.IDPACIENTE
              JOIN CONVENIOS c ON ag.CONVENIO_ID = c.ID
              JOIN AGENDAS a ON ag.AGENDA_ID = a.ID
              LEFT JOIN LAB_MEDICOS_PRES med ON a.MEDICO_ID = med.ID
              LEFT JOIN ESPECIALIDADES esp_salva ON ag.ESPECIALIDADE_ID = esp_salva.ID
              LEFT JOIN LAB_CIDADES u ON a.UNIDADE_ID = u.ID
              LEFT JOIN GRUPO_EXAMES ge ON a.PROCEDIMENTO_ID = ge.ID
              WHERE ag.NUMERO_AGENDAMENTO = ? OR ag.ID = ?";

    $stmt = ibase_prepare($conn, $query);
    $result = ibase_execute($stmt, $agendamento_id, $agendamento_id);
    $agendamento = ibase_fetch_assoc($result);

    if (!$agendamento) {
        echo json_encode(['erro' => 'Agendamento não encontrado']);
        exit;
    }

    // Formatar dados para o modal
    $dados = [
        'sucesso' => true,
        'id' => $agendamento['ID'],
        'numero' => $agendamento['NUMERO_AGENDAMENTO'],
        
        // Dados do agendamento
        'data' => $agendamento['DATA_AGENDAMENTO'],
        'horario' => substr($agendamento['HORA_AGENDAMENTO'], 0, 5), // Remove segundos
        'status' => $agendamento['STATUS'],
        'tipo_consulta' => $agendamento['TIPO_CONSULTA'],
        'observacoes' => lerBlobObservacoes($conn, $agendamento['OBSERVACOES'] ?? ''),
        'idade' => $agendamento['IDADE'],
        
        // Novos campos de atendimento
        'confirmado' => (bool)$agendamento['CONFIRMADO'],
        'tipo_atendimento' => trim($agendamento['TIPO_ATENDIMENTO'] ?? 'NORMAL'),
        'ordem_chegada' => $agendamento['ORDEM_CHEGADA'],
        'hora_chegada' => $agendamento['HORA_CHEGADA'],
        
        // Dados do paciente
        'paciente' => [
            'id' => $agendamento['PACIENTE_ID'],
            'nome' => safe_utf8($agendamento['PACIENTE_NOME']),
            'cpf' => $agendamento['PACIENTE_CPF'],
            'data_nascimento' => $agendamento['PACIENTE_NASCIMENTO'],
            'telefone' => $agendamento['PACIENTE_TELEFONE'],
            'email' => safe_utf8($agendamento['PACIENTE_EMAIL'] ?? '')
        ],
        
        // Dados do convênio
        'convenio' => [
            'id' => $agendamento['CONVENIO_ID'],
            'nome' => safe_utf8($agendamento['CONVENIO_NOME'])
        ],
        
        // Dados da agenda
        'agenda' => [
            'id' => $agendamento['AGENDA_ID'],
            'sala' => safe_utf8($agendamento['AGENDA_SALA']),
            'telefone' => $agendamento['AGENDA_TELEFONE'],
            'unidade' => safe_utf8($agendamento['UNIDADE_NOME']),
            'medico' => safe_utf8($agendamento['MEDICO_NOME'] ?? ''),
            'especialidade' => safe_utf8($agendamento['ESPECIALIDADE_NOME'] ?? ''),
            'procedimento' => safe_utf8($agendamento['PROCEDIMENTO_NOME'] ?? '')
        ],
        
        // Dados para o formulário
        'form_data' => [
            'procedimento' => 'Consulta geral', // Padrão
            'tempo' => 30, // Padrão
            'forma' => 'Convênio',
            'valor' => 'R$ 99,99', // Padrão ou buscar de tabela de preços
            'local' => safe_utf8($agendamento['AGENDA_SALA']),
            'equipamento' => '',
            'repetir' => false
        ]
    ];

    // Determinar o nome do atendimento
    if ($agendamento['ESPECIALIDADE_NOME']) {
        $dados['nome_atendimento'] = "Dr(a). " . safe_utf8($agendamento['MEDICO_NOME']) . " - " . safe_utf8($agendamento['ESPECIALIDADE_NOME']);
    } else if ($agendamento['PROCEDIMENTO_NOME']) {
        $dados['nome_atendimento'] = safe_utf8($agendamento['PROCEDIMENTO_NOME']);
    } else {
        $dados['nome_atendimento'] = 'Agendamento';
    }

    // Buscar exames vinculados ao agendamento - usando a mesma estrutura de buscar_agendamentos_dia.php
    $query_exames = "SELECT 
                        ae.EXAME_ID,
                        le.EXAME as NOME_EXAME
                     FROM AGENDAMENTO_EXAMES ae
                     LEFT JOIN LAB_EXAMES le ON le.IDEXAME = ae.EXAME_ID
                     WHERE ae.NUMERO_AGENDAMENTO = ?
                     ORDER BY le.EXAME";
    
    $stmt_exames = ibase_prepare($conn, $query_exames);
    $result_exames = ibase_execute($stmt_exames, $agendamento['NUMERO_AGENDAMENTO']);
    
    $exames = [];
    while ($row_exame = ibase_fetch_assoc($result_exames)) {
        if ($row_exame['NOME_EXAME']) { // Só adicionar se o exame existir
            $exames[] = [
                'id' => (int)$row_exame['EXAME_ID'],
                'nome' => safe_utf8(trim($row_exame['NOME_EXAME']))
            ];
        }
    }
    $dados['exames'] = $exames;
    
    // Buscar preparos da agenda - usando a mesma estrutura dos exames
    $query_preparos = "SELECT 
                          ID,
                          TITULO,
                          INSTRUCOES,
                          ORDEM
                       FROM AGENDA_PREPAROS
                       WHERE AGENDA_ID = ?
                       ORDER BY ORDEM";
    
    $stmt_preparos = ibase_prepare($conn, $query_preparos);
    $result_preparos = ibase_execute($stmt_preparos, $agendamento['AGENDA_ID']);
    
    $preparos = [];
    while ($row_preparo = ibase_fetch_assoc($result_preparos)) {
        $preparoId = $row_preparo['ID'];
        
        // Buscar anexos deste preparo
        $anexos = [];
        $query_anexos = "SELECT ID, NOME_ORIGINAL, TIPO_ARQUIVO, TAMANHO_ARQUIVO 
                         FROM AGENDA_PREPAROS_ANEXOS 
                         WHERE AGENDA_ID = ? AND PREPARO_ID = ?
                         ORDER BY DATA_UPLOAD DESC";
        
        $stmt_anexos = ibase_prepare($conn, $query_anexos);
        $result_anexos = ibase_execute($stmt_anexos, $agendamento['AGENDA_ID'], $preparoId);
        
        while ($row_anexo = ibase_fetch_assoc($result_anexos)) {
            $anexos[] = [
                'id' => $row_anexo['ID'],
                'nome' => trim($row_anexo['NOME_ORIGINAL']),
                'tipo' => trim($row_anexo['TIPO_ARQUIVO']),
                'tamanho' => $row_anexo['TAMANHO_ARQUIVO']
            ];
        }
        
        $preparos[] = [
            'id' => $preparoId,
            'titulo' => safe_utf8(trim($row_preparo['TITULO'])),
            'instrucoes' => lerBlobObservacoes($conn, $row_preparo['INSTRUCOES']),
            'ordem' => (int)$row_preparo['ORDEM'],
            'anexos' => $anexos
        ];
    }
    $dados['preparos'] = $preparos;
    
    // Buscar data de criação e última modificação se disponível
    if (isset($agendamento['DATA_CRIACAO'])) {
        $dados['data_criacao'] = $agendamento['DATA_CRIACAO'];
    }
    if (isset($agendamento['DATA_MODIFICACAO'])) {
        $dados['data_modificacao'] = $agendamento['DATA_MODIFICACAO'];
    }

    echo json_encode($dados);

} catch (Exception $e) {
    error_log("Erro ao buscar agendamento: " . $e->getMessage());
    echo json_encode([
        'erro' => 'Erro interno do servidor',
        'detalhes' => $e->getMessage()
    ]);
}
?>