<?php
// listar_chegadas.php - Listar ordem de chegadas do dia
header('Content-Type: application/json');
require_once 'includes/connection.php';

// Suporte para linha de comando
if (php_sapi_name() === 'cli') {
    parse_str(getenv('QUERY_STRING') ?: '', $_GET);
}

try {
    $agenda_id = (int)($_GET['agenda_id'] ?? 0);
    $data = $_GET['data'] ?? date('Y-m-d');
    
    if (!$agenda_id) {
        throw new Exception('ID da agenda não fornecido');
    }
    
    // Buscar todos os agendamentos do dia com informações de chegada
    $query = "SELECT 
                a.ID,
                a.NUMERO_AGENDAMENTO,
                a.NOME_PACIENTE,
                a.TELEFONE_PACIENTE,
                a.HORA_AGENDAMENTO,
                a.STATUS,
                a.CONFIRMADO,
                a.TIPO_ATENDIMENTO,
                a.ORDEM_CHEGADA,
                a.HORA_CHEGADA,
                a.TIPO_CONSULTA,
                a.OBSERVACOES,
                c.NOME as CONVENIO_NOME
              FROM AGENDAMENTOS a
              LEFT JOIN CONVENIOS c ON c.ID = a.CONVENIO_ID
              WHERE a.AGENDA_ID = ? AND a.DATA_AGENDAMENTO = ?
              AND a.STATUS NOT IN ('CANCELADO', 'FALTOU')
              ORDER BY 
                CASE 
                    WHEN a.ORDEM_CHEGADA IS NOT NULL THEN 1
                    ELSE 2
                END,
                a.ORDEM_CHEGADA ASC,
                a.TIPO_ATENDIMENTO DESC,
                a.HORA_AGENDAMENTO ASC";
    
    $stmt = ibase_prepare($conn, $query);
    $result = ibase_execute($stmt, $agenda_id, $data);
    
    $agendamentos = [];
    $stats = [
        'total' => 0,
        'chegaram' => 0,
        'confirmados' => 0,
        'prioridade' => 0,
        'normal' => 0
    ];
    
    while ($row = ibase_fetch_assoc($result)) {
        $stats['total']++;
        
        if ($row['ORDEM_CHEGADA']) {
            $stats['chegaram']++;
        }
        
        if ($row['CONFIRMADO']) {
            $stats['confirmados']++;
        }
        
        if ($row['TIPO_ATENDIMENTO'] === 'PRIORIDADE') {
            $stats['prioridade']++;
        } else {
            $stats['normal']++;
        }
        
        $agendamentos[] = [
            'id' => (int)$row['ID'],
            'numero' => $row['NUMERO_AGENDAMENTO'],
            'nome_paciente' => utf8_encode(trim($row['NOME_PACIENTE'])),
            'telefone' => $row['TELEFONE_PACIENTE'],
            'hora_agendamento' => substr($row['HORA_AGENDAMENTO'], 0, 5),
            'status' => trim($row['STATUS']),
            'confirmado' => (bool)$row['CONFIRMADO'],
            'tipo_atendimento' => trim($row['TIPO_ATENDIMENTO']),
            'ordem_chegada' => $row['ORDEM_CHEGADA'] ? (int)$row['ORDEM_CHEGADA'] : null,
            'hora_chegada' => $row['HORA_CHEGADA'],
            'tipo_consulta' => trim($row['TIPO_CONSULTA']),
            'convenio' => utf8_encode(trim($row['CONVENIO_NOME'] ?? '')),
            'observacoes' => utf8_encode(trim($row['OBSERVACOES'] ?? ''))
        ];
    }
    
    echo json_encode([
        'sucesso' => true,
        'data' => $data,
        'agenda_id' => $agenda_id,
        'agendamentos' => $agendamentos,
        'estatisticas' => $stats
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'sucesso' => false,
        'erro' => $e->getMessage()
    ]);
}
?>