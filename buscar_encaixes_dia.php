<?php
// ✅ ARQUIVO: buscar_encaixes_dia.php
// Busca todos os encaixes de um dia específico

// Limpar qualquer output anterior
ob_start();
ob_clean();

// Configurar headers
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-cache, must-revalidate');

// Suprimir erros PHP
error_reporting(0);
ini_set('display_errors', 0);

// Incluir conexão
try {
    include_once 'includes/connection.php';
} catch (Exception $e) {
    ob_clean();
    die('[]');
}

// Limpar buffer novamente
ob_clean();

// Validar parâmetros
$agenda_id = filter_input(INPUT_GET, 'agenda_id', FILTER_VALIDATE_INT);
$data = filter_input(INPUT_GET, 'data', FILTER_SANITIZE_STRING);

if (!$agenda_id || !$data) {
    die('[]');
}

try {
    // Buscar encaixes do dia - corrigido para incluir encaixes sem paciente_id
    $query = "SELECT 
                ag.ID,
                ag.NUMERO_AGENDAMENTO,
                ag.HORA_AGENDAMENTO,
                COALESCE(p.PACIENTE, ag.NOME_PACIENTE) as PACIENTE_NOME,
                COALESCE(p.FONE1, ag.TELEFONE_PACIENTE) as PACIENTE_TELEFONE,
                p.CPF as PACIENTE_CPF,
                COALESCE(c.NOME, 'Particular') as CONVENIO_NOME,
                ag.STATUS,
                ag.OBSERVACOES,
                ag.DATA_CRIACAO,
                ag.TIPO_AGENDAMENTO
              FROM AGENDAMENTOS ag
              LEFT JOIN LAB_PACIENTES p ON ag.PACIENTE_ID = p.IDPACIENTE
              LEFT JOIN CONVENIOS c ON ag.CONVENIO_ID = c.ID
              WHERE ag.AGENDA_ID = ? 
              AND ag.DATA_AGENDAMENTO = ?
              AND ag.TIPO_AGENDAMENTO = 'ENCAIXE'
              AND ag.STATUS NOT IN ('CANCELADO', 'FALTOU')
              ORDER BY ag.HORA_AGENDAMENTO, ag.DATA_CRIACAO";

    $stmt = ibase_prepare($conn, $query);
    if (!$stmt) {
        die('[]');
    }
    
    $result = ibase_execute($stmt, $agenda_id, $data);
    if (!$result) {
        die('[]');
    }

    $encaixes = [];
    while ($row = ibase_fetch_assoc($result)) {
        $encaixes[] = [
            'id' => (int)$row['ID'],
            'numero' => trim($row['NUMERO_AGENDAMENTO'] ?? ''),
            'hora' => substr($row['HORA_AGENDAMENTO'] ?? '', 0, 5),
            'paciente' => mb_convert_encoding(trim($row['PACIENTE_NOME'] ?? 'Paciente não informado'), 'UTF-8', 'Windows-1252'),
            'telefone' => trim($row['PACIENTE_TELEFONE'] ?? ''),
            'cpf' => trim($row['PACIENTE_CPF'] ?? ''),
            'convenio' => mb_convert_encoding(trim($row['CONVENIO_NOME'] ?? ''), 'UTF-8', 'Windows-1252'),
            'status' => trim($row['STATUS'] ?? 'AGENDADO'),
            'observacoes' => mb_convert_encoding(trim($row['OBSERVACOES'] ?? ''), 'UTF-8', 'Windows-1252'),
            'data_criacao' => $row['DATA_CRIACAO'] ?? '',
            'tipo_agendamento' => trim($row['TIPO_AGENDAMENTO'] ?? '')
        ];
    }

    // Retornar JSON limpo
    echo json_encode($encaixes, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    // Em caso de erro, retornar array vazio
    echo '[]';
}
?>