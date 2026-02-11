<?php
header('Content-Type: application/json; charset=UTF-8');
require_once 'includes/connection.php';

// Suporte para linha de comando
if (php_sapi_name() === 'cli') {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    if (getenv('POST_DATA')) {
        parse_str(getenv('POST_DATA'), $_POST);
    }
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método não permitido');
    }
    
    $termo = trim($_POST['termo'] ?? '');
    
    if (empty($termo) || strlen($termo) < 2) {
        throw new Exception('Termo de busca deve ter pelo menos 2 caracteres');
    }
    
    // Processar termo de busca para diferentes tipos
    $termo_upper = strtoupper($termo);
    $termo_limpo_cpf = preg_replace('/[^0-9]/', '', $termo); // Remove formatação do CPF

    // ✅ NOVO: Separar múltiplas palavras para busca mais inteligente
    $palavras = array_filter(array_map('trim', explode(' ', $termo_upper)));
    $eh_busca_multipla = count($palavras) > 1;

    // Verificar se é uma busca por data (vários formatos)
    $eh_busca_data = false;
    $data_busca = '';
    
    // Formato com separadores: dd/mm/yyyy, dd-mm-yyyy, dd.mm.yyyy
    if (preg_match('/^(\d{1,2})[\-\.\/](\d{1,2})[\-\.\/](\d{4})$/', $termo, $matches_data)) {
        $eh_busca_data = true;
        $dia = sprintf('%02d', $matches_data[1]);
        $mes = sprintf('%02d', $matches_data[2]);
        $ano = $matches_data[3];
        $data_busca = $ano . '-' . $mes . '-' . $dia;
    }
    // Formato sem separadores: ddmmyyyy
    else if (preg_match('/^(\d{2})(\d{2})(\d{4})$/', $termo, $matches_data)) {
        $eh_busca_data = true;
        $dia = $matches_data[1];
        $mes = $matches_data[2];
        $ano = $matches_data[3];
        $data_busca = $ano . '-' . $mes . '-' . $dia;
    }
    // Formato curto sem separadores: ddmmyy
    else if (preg_match('/^(\d{2})(\d{2})(\d{2})$/', $termo, $matches_data)) {
        $eh_busca_data = true;
        $dia = $matches_data[1];
        $mes = $matches_data[2];
        $ano = '19' . $matches_data[3]; // Assume século 20 para anos de 2 dígitos
        $data_busca = $ano . '-' . $mes . '-' . $dia;
    }
    
    // ✅ OTIMIZAÇÃO: Query adaptável para busca única ou múltiplas palavras
    $sql = "SELECT FIRST 50
                p.IDPACIENTE as id,
                p.PACIENTE as nome,
                p.CPF as cpf,
                p.FONE1 as telefone,
                p.EMAIL as email,
                p.ANIVERSARIO as data_nascimento
            FROM LAB_PACIENTES p
            WHERE ";

    if ($eh_busca_multipla) {
        // ✅ BUSCA MÚLTIPLA: Nome deve conter TODAS as palavras (em qualquer ordem)
        $condicoes_palavras = [];
        foreach ($palavras as $palavra) {
            $condicoes_palavras[] = "UPPER(p.PACIENTE) CONTAINING UPPER(?)";
        }
        $sql .= "(" . implode(" AND ", $condicoes_palavras) . ")";
    } else {
        // ✅ BUSCA ÚNICA: Query otimizada original
        $sql .= "(
                UPPER(p.PACIENTE) STARTING WITH UPPER(?)         /* Começa com termo */
                OR UPPER(p.PACIENTE) CONTAINING (' ' || UPPER(?) || ' ')  /* Palavra completa no meio */
                OR UPPER(p.PACIENTE) CONTAINING (' ' || UPPER(?))         /* Palavra no final */
                OR UPPER(p.PACIENTE) CONTAINING UPPER(?)          /* Qualquer parte */
                OR p.CPF STARTING WITH ?                          /* CPF */
                OR REPLACE(REPLACE(REPLACE(p.CPF, '.', ''), '-', ''), '/', '') STARTING WITH ?
            )";
    }

    // Adicionar busca por data se identificada
    if ($eh_busca_data && $data_busca) {
        $sql .= " OR p.ANIVERSARIO = ?";
    }

    // ✅ ORDER BY adaptável
    if ($eh_busca_multipla) {
        // Para busca múltipla: ordenar por ordem alfabética (todos já matcheiam todas as palavras)
        $sql .= " ORDER BY p.PACIENTE";

        // Preparar parâmetros: apenas as palavras
        $params = $palavras;
    } else {
        // Para busca única: ORDER BY original com priorização
        $sql .= " ORDER BY
                    CASE
                        WHEN UPPER(p.PACIENTE) STARTING WITH UPPER(?) THEN 1
                        WHEN UPPER(p.PACIENTE) CONTAINING (' ' || UPPER(?) || ' ') THEN 2
                        WHEN UPPER(p.PACIENTE) CONTAINING (' ' || UPPER(?)) THEN 3
                        WHEN p.CPF STARTING WITH ? THEN 4
                        WHEN REPLACE(REPLACE(REPLACE(p.CPF, '.', ''), '-', ''), '/', '') STARTING WITH ? THEN 5
                        WHEN UPPER(p.PACIENTE) CONTAINING UPPER(?) THEN 6";

        if ($eh_busca_data && $data_busca) {
            $sql .= " WHEN p.ANIVERSARIO = ? THEN 7";
        }

        $sql .= " ELSE 99
                    END,
                    p.PACIENTE";

        // Preparar parâmetros para WHERE
        $params = [$termo_upper, $termo_upper, $termo_upper, $termo_upper, $termo, $termo_limpo_cpf];

        if ($eh_busca_data && $data_busca) {
            $params[] = $data_busca;
        }

        // Adicionar parâmetros para ORDER BY
        $params = array_merge($params, [$termo_upper, $termo_upper, $termo_upper, $termo, $termo_limpo_cpf, $termo_upper]);

        if ($eh_busca_data && $data_busca) {
            $params[] = $data_busca;
        }
    }
    
    $stmt = ibase_prepare($conn, $sql);
    $result = ibase_execute($stmt, ...$params);
    
    $pacientes = array();
    while ($row = ibase_fetch_assoc($result)) {
        $pacientes[] = array(
            'id' => (int)$row['ID'],
            'nome' => utf8_encode(trim($row['NOME'])),
            'cpf' => trim($row['CPF']),
            'telefone' => trim($row['TELEFONE']),
            'email' => utf8_encode(trim($row['EMAIL'])),
            'data_nascimento' => trim($row['DATA_NASCIMENTO'])
        );
    }
    
    ibase_free_result($result);
    
    $response = array(
        'status' => 'sucesso',
        'termo_busca' => $termo,
        'total_encontrados' => count($pacientes),
        'pacientes' => $pacientes
    );
    
    echo json_encode($response);
    
} catch (Exception $e) {
    $response = array(
        'status' => 'erro',
        'mensagem' => $e->getMessage(),
        'pacientes' => array()
    );
    
    echo json_encode($response);
}
?>