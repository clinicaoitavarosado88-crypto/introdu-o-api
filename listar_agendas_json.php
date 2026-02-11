<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

include 'includes/connection.php';
include 'includes/auth_middleware.php';

try {
    // Verificar autenticação
    $auth_result = verify_api_token();
    if (!$auth_result['valid']) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized', 'message' => $auth_result['message']]);
        exit;
    }

    // Parâmetros de filtro
    $tipo = $_GET['tipo'] ?? '';
    $nome = trim($_GET['nome'] ?? '');
    $dia = isset($_GET['dia']) ? utf8_decode(trim($_GET['dia'])) : '';
    if ($dia === '' || strtolower($dia) === 'null' || strtolower($dia) === 'undefined' || strtolower($dia) === 'nan') {
        $dia = null;
    }

    // Filtro por cidade
    $cidade_id = $_GET['cidade'] ?? '';
    $whereCidade = '';
    if (!empty($cidade_id) && is_numeric($cidade_id)) {
        $whereCidade = " AND a.UNIDADE_ID = $cidade_id";
    }

    // Função para ler BLOBs
    $lerBlob = function($blob_id) use ($conn) {
        if (!is_string($blob_id) || empty($blob_id)) return '';
        try {
            $blob = ibase_blob_open($conn, $blob_id);
            $conteudo = '';
            while ($segmento = ibase_blob_get($blob, 4096)) {
                $conteudo .= $segmento;
            }
            ibase_blob_close($blob);
            return trim($conteudo);
        } catch (Exception $e) {
            return '';
        }
    };

    // 🔍 Construir query baseado no tipo
    if ($tipo === 'consulta') {
        // Buscar ID da especialidade
        $sql_todas = "SELECT ID, NOME FROM ESPECIALIDADES";
        $res_todas = ibase_query($conn, $sql_todas);
        $id = null;

        while ($row_esp = ibase_fetch_assoc($res_todas)) {
            $nome_banco_utf8 = mb_convert_encoding($row_esp['NOME'], 'UTF-8', 'Windows-1252');
            if (strtoupper(trim($nome_banco_utf8)) === strtoupper(trim($nome))) {
                $id = $row_esp['ID'];
                break;
            }
        }

        if (!$id) {
            http_response_code(404);
            echo json_encode([
                'erro' => true,
                'mensagem' => 'Especialidade não encontrada: ' . $nome
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $whereDia = '';
        if (isset($dia) && $dia !== '') {
            $whereDia = " AND EXISTS (
                SELECT 1 FROM AGENDA_HORARIOS d
                WHERE d.AGENDA_ID = a.ID
                AND TRIM(d.DIA_SEMANA) = '$dia'
            )";
        }

        $sql = "
            SELECT a.ID, a.TIPO, u.NOME_UNIDADE AS UNIDADE, m.NOME AS MEDICO, m.ID AS MEDICO_ID,
                   e.NOME AS ESPECIALIDADE, e.ID AS ESPECIALIDADE_ID,
                   a.TELEFONE, a.SALA, a.TEMPO_ESTIMADO_MINUTOS, me.IDADE_MINIMA,
                   a.OBSERVACOES, a.INFORMACOES_FIXAS, a.ORIENTACOES,
                   a.POSSUI_RETORNO, a.ATENDE_COMORBIDADE,
                   a.LIMITE_VAGAS_DIA, a.LIMITE_RETORNOS_DIA, a.LIMITE_ENCAIXES_DIA,
                   u.ID AS UNIDADE_ID
            FROM AGENDAS a
            JOIN LAB_MEDICOS_PRES m ON m.ID = a.MEDICO_ID
            JOIN LAB_MEDICOS_ESPECIALIDADES me ON me.MEDICO_ID = m.ID
            JOIN ESPECIALIDADES e ON e.ID = me.ESPECIALIDADE_ID
            JOIN LAB_CIDADES u ON u.ID = a.UNIDADE_ID
            WHERE a.TIPO = 'consulta' AND me.ESPECIALIDADE_ID = $id $whereDia $whereCidade
        ";

    } elseif ($tipo === 'procedimento') {
        // Buscar ID do procedimento
        $sql_todos = "SELECT ID, NOME FROM GRUPO_EXAMES";
        $res_todos = ibase_query($conn, $sql_todos);
        $id = null;

        while ($row_proc = ibase_fetch_assoc($res_todos)) {
            $nome_banco_utf8 = mb_convert_encoding($row_proc['NOME'], 'UTF-8', 'Windows-1252');
            if (strtoupper(trim($nome_banco_utf8)) === strtoupper(trim($nome))) {
                $id = $row_proc['ID'];
                break;
            }
        }

        if (!$id) {
            http_response_code(404);
            echo json_encode([
                'erro' => true,
                'mensagem' => 'Procedimento não encontrado: ' . $nome
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $whereDia = '';
        if (isset($dia) && $dia !== '') {
            $whereDia = " AND EXISTS (
                SELECT 1 FROM AGENDA_HORARIOS d
                WHERE d.AGENDA_ID = a.ID
                AND TRIM(d.DIA_SEMANA) = '$dia'
            )";
        }

        $sql = "
            SELECT a.ID, a.TIPO, u.NOME_UNIDADE AS UNIDADE, p.NOME AS PROCEDIMENTO,
                   p.ID AS PROCEDIMENTO_ID, m.NOME AS MEDICO, m.ID AS MEDICO_ID,
                   a.TELEFONE, a.SALA, a.TEMPO_ESTIMADO_MINUTOS,
                   a.OBSERVACOES, a.INFORMACOES_FIXAS, a.ORIENTACOES,
                   a.LIMITE_VAGAS_DIA, a.LIMITE_RETORNOS_DIA, a.LIMITE_ENCAIXES_DIA,
                   a.POSSUI_RETORNO, a.ATENDE_COMORBIDADE,
                   u.ID AS UNIDADE_ID
            FROM AGENDAS a
            JOIN GRUPO_EXAMES p ON p.ID = a.PROCEDIMENTO_ID
            JOIN LAB_CIDADES u ON u.ID = a.UNIDADE_ID
            LEFT JOIN LAB_MEDICOS_PRES m ON m.ID = a.MEDICO_ID
            WHERE a.TIPO = 'procedimento' AND p.ID = $id $whereDia $whereCidade
        ";

    } else {
        http_response_code(400);
        echo json_encode([
            'erro' => true,
            'mensagem' => 'Parâmetro "tipo" é obrigatório (consulta ou procedimento)'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Executar query principal
    $res_agendas = ibase_query($conn, $sql);
    if (!$res_agendas) {
        throw new Exception('Erro ao consultar agendas: ' . ibase_errmsg());
    }

    $agendas = [];

    while ($a = ibase_fetch_assoc($res_agendas)) {
        $id_agenda = $a['ID'];

        // 1. Buscar convênios
        $convenios = [];
        $res_conv = ibase_query($conn, "
            SELECT c.ID, c.NOME
            FROM AGENDA_CONVENIOS ac
            JOIN CONVENIOS c ON c.ID = ac.CONVENIO_ID
            WHERE ac.AGENDA_ID = $id_agenda
        ");
        while ($c = ibase_fetch_assoc($res_conv)) {
            $convenios[] = [
                'id' => (int)$c['ID'],
                'nome' => mb_convert_encoding(trim($c['NOME']), 'UTF-8', 'Windows-1252')
            ];
        }

        // 2. Buscar horários e vagas por dia
        $horarios_por_dia = [];
        $vagas_por_dia = [];
        $res_horarios = ibase_query($conn, "SELECT * FROM AGENDA_HORARIOS WHERE AGENDA_ID = $id_agenda");

        while ($h = ibase_fetch_assoc($res_horarios)) {
            $dia_semana = mb_convert_encoding(trim($h['DIA_SEMANA']), 'UTF-8', 'Windows-1252');

            // Verificar funcionamento contínuo
            $funcionamento_continuo = !empty($h['HORARIO_INICIO_MANHA']) &&
                                      empty($h['HORARIO_FIM_MANHA']) &&
                                      empty($h['HORARIO_INICIO_TARDE']) &&
                                      !empty($h['HORARIO_FIM_TARDE']);

            $horarios = [];
            if ($funcionamento_continuo) {
                $horarios[] = [
                    'periodo' => 'continuo',
                    'inicio' => date('H:i', strtotime($h['HORARIO_INICIO_MANHA'])),
                    'fim' => date('H:i', strtotime($h['HORARIO_FIM_TARDE']))
                ];
            } else {
                if (!empty($h['HORARIO_INICIO_MANHA']) && !empty($h['HORARIO_FIM_MANHA'])) {
                    $horarios[] = [
                        'periodo' => 'manha',
                        'inicio' => date('H:i', strtotime($h['HORARIO_INICIO_MANHA'])),
                        'fim' => date('H:i', strtotime($h['HORARIO_FIM_MANHA']))
                    ];
                }
                if (!empty($h['HORARIO_INICIO_TARDE']) && !empty($h['HORARIO_FIM_TARDE'])) {
                    $horarios[] = [
                        'periodo' => 'tarde',
                        'inicio' => date('H:i', strtotime($h['HORARIO_INICIO_TARDE'])),
                        'fim' => date('H:i', strtotime($h['HORARIO_FIM_TARDE']))
                    ];
                }
            }

            $horarios_por_dia[$dia_semana] = $horarios;

            if (isset($h['VAGAS_DIA']) && $h['VAGAS_DIA'] > 0) {
                $vagas_por_dia[$dia_semana] = (int)$h['VAGAS_DIA'];
            }
        }

        // 3. Ler BLOBs
        $observacoes = mb_convert_encoding($lerBlob($a['OBSERVACOES'] ?? ''), 'UTF-8', 'Windows-1252');
        $informacoes_fixas = mb_convert_encoding($lerBlob($a['INFORMACOES_FIXAS'] ?? ''), 'UTF-8', 'Windows-1252');
        $orientacoes = mb_convert_encoding($lerBlob($a['ORIENTACOES'] ?? ''), 'UTF-8', 'Windows-1252');

        // 4. Construir objeto da agenda
        $agenda_data = [
            'id' => (int)$id_agenda,
            'tipo' => mb_convert_encoding(trim($a['TIPO'] ?? ''), 'UTF-8', 'Windows-1252'),
        ];

        // Informações básicas (médico/procedimento)
        if ($tipo === 'consulta') {
            $agenda_data['medico'] = [
                'id' => (int)($a['MEDICO_ID'] ?? 0),
                'nome' => mb_convert_encoding(trim($a['MEDICO'] ?? ''), 'UTF-8', 'Windows-1252')
            ];
            $agenda_data['especialidade'] = [
                'id' => (int)($a['ESPECIALIDADE_ID'] ?? 0),
                'nome' => mb_convert_encoding(trim($a['ESPECIALIDADE'] ?? ''), 'UTF-8', 'Windows-1252')
            ];
        } else {
            $agenda_data['procedimento'] = [
                'id' => (int)($a['PROCEDIMENTO_ID'] ?? 0),
                'nome' => mb_convert_encoding(trim($a['PROCEDIMENTO'] ?? ''), 'UTF-8', 'Windows-1252')
            ];
            if (!empty($a['MEDICO'])) {
                $agenda_data['medico'] = [
                    'id' => (int)($a['MEDICO_ID'] ?? 0),
                    'nome' => mb_convert_encoding(trim($a['MEDICO']), 'UTF-8', 'Windows-1252')
                ];
            }
        }

        // Localização
        $agenda_data['localizacao'] = [
            'unidade_id' => (int)($a['UNIDADE_ID'] ?? 0),
            'unidade_nome' => mb_convert_encoding(trim($a['UNIDADE'] ?? ''), 'UTF-8', 'Windows-1252'),
            'sala' => !empty($a['SALA']) ? trim($a['SALA']) : null,
            'telefone' => !empty($a['TELEFONE']) ? trim($a['TELEFONE']) : null
        ];

        // Configurações
        $agenda_data['configuracoes'] = [
            'tempo_estimado_minutos' => !empty($a['TEMPO_ESTIMADO_MINUTOS']) ? (int)$a['TEMPO_ESTIMADO_MINUTOS'] : null,
            'idade_minima' => !empty($a['IDADE_MINIMA']) ? (int)$a['IDADE_MINIMA'] : null,
            'possui_retorno' => ($a['POSSUI_RETORNO'] === 'S' || $a['POSSUI_RETORNO'] === 1),
            'atende_comorbidade' => ($a['ATENDE_COMORBIDADE'] === 'S' || $a['ATENDE_COMORBIDADE'] === 1)
        ];

        // Limites
        $agenda_data['limites'] = [
            'vagas_dia' => !empty($a['LIMITE_VAGAS_DIA']) ? (int)$a['LIMITE_VAGAS_DIA'] : 0,
            'retornos_dia' => !empty($a['LIMITE_RETORNOS_DIA']) ? (int)$a['LIMITE_RETORNOS_DIA'] : 0,
            'encaixes_dia' => !empty($a['LIMITE_ENCAIXES_DIA']) ? (int)$a['LIMITE_ENCAIXES_DIA'] : 0
        ];

        // Horários e vagas
        $agenda_data['horarios_por_dia'] = $horarios_por_dia;
        $agenda_data['vagas_por_dia'] = $vagas_por_dia;

        // Convênios
        $agenda_data['convenios'] = $convenios;

        // Observações
        $agenda_data['avisos'] = [
            'observacoes' => !empty($observacoes) ? $observacoes : null,
            'informacoes_fixas' => !empty($informacoes_fixas) ? $informacoes_fixas : null,
            'orientacoes' => !empty($orientacoes) ? $orientacoes : null
        ];

        $agendas[] = $agenda_data;
    }

    // Resposta final
    $response = [
        'status' => 'sucesso',
        'total_agendas' => count($agendas),
        'filtros_aplicados' => [
            'tipo' => $tipo,
            'nome' => $nome ?: null,
            'dia_semana' => $dia ?: null,
            'cidade_id' => $cidade_id ?: null
        ],
        'agendas' => $agendas
    ];

    // Usar JSON_INVALID_UTF8_SUBSTITUTE para lidar com possíveis problemas de encoding
    $json = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($json === false) {
        http_response_code(500);
        echo json_encode(['erro' => 'Erro ao gerar JSON', 'detalhes' => json_last_error_msg()]);
    } else {
        echo $json;
    }

} catch (Exception $e) {
    error_log("Erro em listar_agendas_json.php: " . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'error' => 'Internal Server Error',
        'message' => 'Erro ao listar agendas: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

ibase_close($conn);
?>
