<?php
// listar_cancelados.php
header('Content-Type: application/json');
include 'includes/connection.php';

// Suporte para linha de comando
if (php_sapi_name() === 'cli') {
    parse_str(getenv('QUERY_STRING') ?: '', $_GET);
}

$agenda_id = $_GET['agenda_id'] ?? 0;
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-d');
$data_fim = $_GET['data_fim'] ?? date('Y-m-d');
$limit = (int) ($_GET['limit'] ?? 50);

if (!$agenda_id) {
    echo json_encode([
        'erro' => 'agenda_id é obrigatório',
        'exemplo' => '?agenda_id=2&data_inicio=2025-08-01&data_fim=2025-08-31'
    ]);
    exit;
}

try {
    // Buscar agendamentos cancelados no período
    $query = "SELECT 
                a.ID,
                a.NUMERO_AGENDAMENTO,
                a.DATA_AGENDAMENTO,
                a.HORA_AGENDAMENTO,
                COALESCE(p.PACIENTE, a.NOME_PACIENTE) as PACIENTE_NOME,
                COALESCE(p.FONE1, a.TELEFONE_PACIENTE) as PACIENTE_TELEFONE,
                c.NOME as CONVENIO_NOME,
                a.STATUS,
                a.OBSERVACOES
              FROM AGENDAMENTOS a
              LEFT JOIN LAB_PACIENTES p ON a.PACIENTE_ID = p.IDPACIENTE
              JOIN CONVENIOS c ON a.CONVENIO_ID = c.ID
              WHERE a.AGENDA_ID = ?
              AND a.DATA_AGENDAMENTO BETWEEN ? AND ?
              AND a.STATUS = 'CANCELADO'
              ORDER BY a.DATA_AGENDAMENTO DESC, a.HORA_AGENDAMENTO DESC
              ROWS ?";
    
    $stmt = ibase_prepare($conn, $query);
    $result = ibase_execute($stmt, $agenda_id, $data_inicio, $data_fim, $limit);
    
    $cancelados = [];
    
    while ($row = ibase_fetch_assoc($result)) {
        $hora = substr($row['HORA_AGENDAMENTO'], 0, 5);
        $data_formatada = date('d/m/Y', strtotime($row['DATA_AGENDAMENTO']));
        
        // Extrair informações do cancelamento das observações
        $observacoes = $row['OBSERVACOES'] ? utf8_encode($row['OBSERVACOES']) : '';
        $info_cancelamento = '';
        
        if (preg_match('/\[CANCELADO em ([^\]]+)\] (.*)/', $observacoes, $matches)) {
            $info_cancelamento = [
                'data_cancelamento' => $matches[1],
                'motivo' => $matches[2]
            ];
        }
        
        $cancelados[] = [
            'id' => $row['ID'],
            'numero' => $row['NUMERO_AGENDAMENTO'],
            'data' => $row['DATA_AGENDAMENTO'],
            'data_formatada' => $data_formatada,
            'hora' => $hora,
            'paciente' => utf8_encode($row['PACIENTE_NOME']),
            'telefone' => $row['PACIENTE_TELEFONE'] ?? '',
            'convenio' => utf8_encode($row['CONVENIO_NOME']),
            'observacoes_completas' => $observacoes,
            'cancelamento' => $info_cancelamento
        ];
    }
    
    echo json_encode([
        'agenda_id' => $agenda_id,
        'periodo' => [
            'inicio' => $data_inicio,
            'fim' => $data_fim
        ],
        'total' => count($cancelados),
        'cancelados' => $cancelados
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'erro' => $e->getMessage()
    ]);
}
?>