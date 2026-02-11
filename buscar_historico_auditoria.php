<?php
header('Content-Type: application/json; charset=UTF-8');
include 'includes/connection.php';

$agendamento_id = $_GET['agendamento_id'] ?? 0;

if (!$agendamento_id) {
    echo json_encode([]);
    exit;
}

try {
    // Buscar histórico de auditoria para este agendamento
    $query = "SELECT 
                a.ID,
                a.ACAO,
                a.USUARIO,
                a.DATA_ACAO,
                a.OBSERVACOES,
                a.CAMPOS_ALTERADOS,
                a.VALORES_ANTERIORES,
                a.VALORES_NOVOS,
                a.IP_USUARIO,
                a.METADATA
              FROM AUDITORIA_AGENDAMENTOS a
              WHERE a.AGENDAMENTO_ID = ?
              ORDER BY a.DATA_ACAO DESC, a.ID DESC";
    
    $stmt = ibase_prepare($conn, $query);
    $result = ibase_execute($stmt, $agendamento_id);
    
    $historico = [];
    while ($row = ibase_fetch_assoc($result)) {
        $item = [
            'id' => $row['ID'],
            'acao' => trim($row['ACAO']),
            'usuario' => trim($row['USUARIO']),
            'data_acao' => $row['DATA_ACAO'],
            'observacoes' => $row['OBSERVACOES'] ? mb_convert_encoding(trim($row['OBSERVACOES']), 'UTF-8', 'Windows-1252') : null,
            'campos_alterados' => $row['CAMPOS_ALTERADOS'] ? mb_convert_encoding(trim($row['CAMPOS_ALTERADOS']), 'UTF-8', 'Windows-1252') : null,
            'valores_anteriores' => $row['VALORES_ANTERIORES'] ? mb_convert_encoding(trim($row['VALORES_ANTERIORES']), 'UTF-8', 'Windows-1252') : null,
            'valores_novos' => $row['VALORES_NOVOS'] ? mb_convert_encoding(trim($row['VALORES_NOVOS']), 'UTF-8', 'Windows-1252') : null,
            'ip_usuario' => $row['IP_USUARIO'] ? trim($row['IP_USUARIO']) : null,
            'metadata' => $row['METADATA'] ? mb_convert_encoding(trim($row['METADATA']), 'UTF-8', 'Windows-1252') : null
        ];
        
        // Formatar data para exibição
        if ($item['data_acao']) {
            $item['data_acao_formatada'] = date('d/m/Y H:i:s', strtotime($item['data_acao']));
        }
        
        // Definir ícone e cor baseado na ação
        switch ($item['acao']) {
            case 'CRIAR':
                $item['icone'] = 'bi-plus-circle';
                $item['cor'] = 'text-green-600';
                $item['descricao'] = 'Agendamento criado';
                break;
            case 'ALTERAR':
                $item['icone'] = 'bi-pencil-square';
                $item['cor'] = 'text-blue-600';
                $item['descricao'] = 'Agendamento alterado';
                break;
            case 'CANCELAR':
                $item['icone'] = 'bi-x-circle';
                $item['cor'] = 'text-red-600';
                $item['descricao'] = 'Agendamento cancelado';
                break;
            case 'REAGENDAR':
                $item['icone'] = 'bi-arrow-clockwise';
                $item['cor'] = 'text-orange-600';
                $item['descricao'] = 'Agendamento reagendado';
                break;
            case 'CONFIRMAR':
                $item['icone'] = 'bi-check-circle';
                $item['cor'] = 'text-teal-600';
                $item['descricao'] = 'Agendamento confirmado';
                break;
            default:
                $item['icone'] = 'bi-info-circle';
                $item['cor'] = 'text-gray-600';
                $item['descricao'] = $item['acao'];
                break;
        }
        
        $historico[] = $item;
    }
    
    echo json_encode($historico, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log("Erro ao buscar histórico de auditoria: " . $e->getMessage());
    echo json_encode([]);
}
?>