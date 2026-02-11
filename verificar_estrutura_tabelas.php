<?php
header('Content-Type: application/json; charset=UTF-8');

include 'includes/connection.php';

$response = [
    'status' => 'sucesso',
    'tabelas' => []
];

// Lista de tabelas para verificar
$tabelas_verificar = [
    'AGENDAS',
    'CONVENIOS',
    'LAB_MEDICOS_PRES',
    'AGENDAMENTOS',
    'LAB_PACIENTES'
];

foreach ($tabelas_verificar as $nome_tabela) {
    try {
        $query = "SELECT RDB\$FIELD_NAME FROM RDB\$RELATION_FIELDS
                  WHERE RDB\$RELATION_NAME = '$nome_tabela'
                  ORDER BY RDB\$FIELD_POSITION";
        $result = ibase_query($conn, $query);

        $campos = [];
        while ($row = ibase_fetch_assoc($result)) {
            $campos[] = trim($row['RDB$FIELD_NAME']);
        }

        $response['tabelas'][$nome_tabela] = [
            'status' => 'sucesso',
            'total_campos' => count($campos),
            'campos' => $campos
        ];
    } catch (Exception $e) {
        $response['tabelas'][$nome_tabela] = [
            'status' => 'erro',
            'mensagem' => $e->getMessage()
        ];
    }
}

// Testar dados reais (opcional)
$response['dados_teste'] = [];

// Agenda ID 1
try {
    $result = ibase_query($conn, "SELECT * FROM AGENDAS WHERE ID = 1");
    if ($result && $row = ibase_fetch_assoc($result)) {
        $response['dados_teste']['AGENDAS_ID_1'] = [
            'status' => 'sucesso',
            'dados' => $row
        ];
    } else {
        $response['dados_teste']['AGENDAS_ID_1'] = [
            'status' => 'nao_encontrado'
        ];
    }
} catch (Exception $e) {
    $response['dados_teste']['AGENDAS_ID_1'] = [
        'status' => 'erro',
        'mensagem' => $e->getMessage()
    ];
}

// Convênio ID 3
try {
    $result = ibase_query($conn, "SELECT * FROM CONVENIOS WHERE ID = 3");
    if ($result && $row = ibase_fetch_assoc($result)) {
        $response['dados_teste']['CONVENIOS_ID_3'] = [
            'status' => 'sucesso',
            'dados' => $row
        ];
    } else {
        $response['dados_teste']['CONVENIOS_ID_3'] = [
            'status' => 'nao_encontrado'
        ];
    }
} catch (Exception $e) {
    $response['dados_teste']['CONVENIOS_ID_3'] = [
        'status' => 'erro',
        'mensagem' => $e->getMessage()
    ];
}

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>
