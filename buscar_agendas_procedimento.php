<?php
/**
 * API: Buscar Agendas de Procedimentos com Mapeamento Inteligente
 *
 * Busca agendas que realizam determinado procedimento/exame
 * Entende sinônimos, abreviações e variações de escrita
 *
 * Exemplos de uso:
 * - /buscar_agendas_procedimento.php?termo=RM
 * - /buscar_agendas_procedimento.php?termo=ressonancia
 * - /buscar_agendas_procedimento.php?termo=ultrassom&cidade=Mossoró
 *
 * @return JSON com agendas disponíveis para o procedimento
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once 'includes/connection.php';
require_once 'includes/auth_middleware.php';

// Verificar autenticação
$auth = verify_api_token();
if (!$auth['valid']) {
    http_response_code(401);
    echo json_encode([
        'error' => 'Unauthorized',
        'message' => $auth['message']
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

// ========================================
// FUNÇÕES AUXILIARES
// ========================================

/**
 * Mapeia termos populares/abreviações para nomes formais
 */
function mapearTermoProcedimento($termo) {
    $termo = strtolower(trim($termo));
    $termo = removerAcentos($termo);

    $mapeamento = [
        'rm' => 'Ressonância Magnética',
        'ressonancia' => 'Ressonância Magnética',
        'ressonancia magnetica' => 'Ressonância Magnética',
        'ressona' => 'Ressonância Magnética',

        'us' => 'Ultrassonografia',
        'ultrassom' => 'Ultrassonografia',
        'ultrasom' => 'Ultrassonografia',
        'ultra' => 'Ultrassonografia',
        'ecografia' => 'Ultrassonografia',

        'tc' => 'Tomografia',
        'tomo' => 'Tomografia',
        'tomografia' => 'Tomografia',

        'rx' => 'Raio-X',
        'raio-x' => 'Raio-X',
        'raio x' => 'Raio-X',
        'radiografia' => 'Raio-X',
        'radio' => 'Raio-X',

        'ecg' => 'Eletrocardiograma',
        'eletro' => 'Eletrocardiograma',
        'eletrocardiograma' => 'Eletrocardiograma',

        'eco' => 'Ecocardiograma',
        'ecocardiograma' => 'Ecocardiograma',

        'mamo' => 'Mamografia',
        'mamografia' => 'Mamografia',

        'endo' => 'Endoscopia',
        'endoscopia' => 'Endoscopia',

        'colono' => 'Colonoscopia',
        'colonoscopia' => 'Colonoscopia',

        'doppler' => 'Doppler',
        'holter' => 'Holter',

        'densito' => 'Densitometria',
        'densitometria' => 'Densitometria',

        'espiro' => 'Espirometria',
        'espirometria' => 'Espirometria',

        'eeg' => 'Eletroencefalograma',
        'eletroencefalograma' => 'Eletroencefalograma'
    ];

    if (isset($mapeamento[$termo])) {
        return $mapeamento[$termo];
    }

    foreach ($mapeamento as $chave => $nomeFormal) {
        if (strpos($termo, $chave) !== false || strpos($chave, $termo) !== false) {
            return $nomeFormal;
        }
    }

    return ucwords($termo);
}

function removerAcentos($string) {
    $acentos = [
        'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a',
        'é' => 'e', 'ê' => 'e',
        'í' => 'i',
        'ó' => 'o', 'õ' => 'o', 'ô' => 'o',
        'ú' => 'u', 'ü' => 'u',
        'ç' => 'c'
    ];
    return strtr($string, $acentos);
}

function corrigirCaracteres($texto) {
    if ($texto === null) return null;
    $substituicoes = [
        'º' => 'º', 'ª' => 'ª',
        'Ã§' => 'ç', 'Ã£' => 'ã', 'Ã¡' => 'á',
        'Ã©' => 'é', 'Ã­' => 'í', 'Ã³' => 'ó', 'Ãº' => 'ú',
        'Ã' => 'Ç', 'Ã‚' => 'Â', 'Ãª' => 'ê', 'Ã´' => 'ô'
    ];
    return strtr($texto, $substituicoes);
}

function obterDiaSemanaAbreviado($numero) {
    $dias = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sab'];
    return $dias[$numero] ?? '';
}

// ========================================
// PROCESSAR REQUISIÇÃO
// ========================================

try {
    // Conexão Firebird
    $host = 'localhost:/opt/banco/oitava_db.fdb';
    $username = 'SYSDBA';
    $password = 'masterkey';
    $conn = ibase_connect($host, $username, $password);

    if (!$conn) {
        throw new Exception('Erro ao conectar ao banco: ' . ibase_errmsg());
    }

    // Parâmetros
    $termoBusca = isset($_GET['termo']) ? trim($_GET['termo']) : '';
    $cidadeFiltro = isset($_GET['cidade']) ? trim($_GET['cidade']) : '';

    if (empty($termoBusca)) {
        http_response_code(400);
        echo json_encode([
            'error' => 'Bad Request',
            'message' => 'Parâmetro "termo" é obrigatório'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Mapear termo
    $termoMapeado = mapearTermoProcedimento($termoBusca);

    // Buscar agendas de procedimentos
    // Extrair primeiras letras para busca flexível (ignora acentos corrompidos)
    $primeirasLetras = substr(removerAcentos($termoMapeado), 0, 6); // "RESSON" para Ressonância

    $sql = "SELECT
                a.ID,
                a.SALA,
                a.TEMPO_ESTIMADO_MINUTOS,
                a.POSSUI_RETORNO,
                a.ATENDE_COMORBIDADE,
                a.LIMITE_VAGAS_DIA,
                a.LIMITE_RETORNOS_DIA,
                a.LIMITE_ENCAIXES_DIA,
                a.OBSERVACOES,
                a.INFORMACOES_FIXAS,
                a.ORIENTACOES,
                a.TELEFONE,

                ge.ID as PROCEDIMENTO_ID,
                ge.NOME as PROCEDIMENTO_NOME,

                u.ID as UNIDADE_ID,
                u.NOME_UNIDADE as CIDADE,
                u.ENDERECO as UNIDADE_ENDERECO,

                m.ID as MEDICO_ID,
                m.NOME as MEDICO_NOME,
                m.CRM

            FROM AGENDAS a
            INNER JOIN GRUPO_EXAMES ge ON a.PROCEDIMENTO_ID = ge.ID
            INNER JOIN LAB_CIDADES u ON a.UNIDADE_ID = u.ID
            LEFT JOIN LAB_MEDICOS_PRES m ON a.MEDICO_ID = m.ID
            WHERE a.TIPO = 'procedimento'
              AND UPPER(ge.NOME) LIKE '" . strtoupper($primeirasLetras) . "%'";

    // Filtro de cidade (busca flexível para ignorar acentos corrompidos)
    if (!empty($cidadeFiltro)) {
        // Pegar primeiras 5 letras da cidade (ex: "Mossor" de "Mossoró")
        $cidadeSemAcentos = removerAcentos($cidadeFiltro);
        $primeirasLetrasCidade = substr($cidadeSemAcentos, 0, 5);

        $sql .= " AND UPPER(u.NOME_UNIDADE) LIKE '" . strtoupper($primeirasLetrasCidade) . "%'";
    }

    $sql .= " ORDER BY u.NOME_UNIDADE, ge.NOME";

    $result = ibase_query($conn, $sql);

    if (!$result) {
        throw new Exception('Erro ao buscar agendas: ' . ibase_errmsg());
    }

    $agendas = [];
    while ($row = ibase_fetch_assoc($result)) {

        // Buscar horários
        $sqlHorarios = "SELECT DIA_SEMANA, INICIO, FIM
                        FROM AGENDA_HORARIOS
                        WHERE AGENDA_ID = " . $row['ID'] . "
                        ORDER BY DIA_SEMANA, INICIO";
        $resHorarios = ibase_query($conn, $sqlHorarios);

        $horariosPorDia = [];
        if ($resHorarios) {
            while ($horario = ibase_fetch_assoc($resHorarios)) {
                $dia = trim($horario['DIA_SEMANA']);
                if (!isset($horariosPorDia[$dia])) {
                    $horariosPorDia[$dia] = [];
                }
                $horariosPorDia[$dia][] = [
                    'inicio' => trim($horario['INICIO']),
                    'fim' => trim($horario['FIM'])
                ];
            }
            ibase_free_result($resHorarios);
        }

        // Buscar convênios
        $sqlConvenios = "SELECT c.ID, c.NOME
                         FROM AGENDA_CONVENIOS ac
                         INNER JOIN LAB_CONVENIOS c ON ac.CONVENIO_ID = c.ID
                         WHERE ac.AGENDA_ID = " . $row['ID'];
        $resConvenios = ibase_query($conn, $sqlConvenios);

        $convenios = [];
        if ($resConvenios) {
            while ($conv = ibase_fetch_assoc($resConvenios)) {
                $convenios[] = [
                    'id' => (int)$conv['ID'],
                    'nome' => corrigirCaracteres(trim($conv['NOME']))
                ];
            }
            ibase_free_result($resConvenios);
        }

        $agendas[] = [
            'id' => (int)$row['ID'],
            'tipo' => 'procedimento',
            'procedimento' => [
                'id' => (int)$row['PROCEDIMENTO_ID'],
                'nome' => corrigirCaracteres(trim($row['PROCEDIMENTO_NOME']))
            ],
            'medico' => [
                'id' => $row['MEDICO_ID'] ? (int)$row['MEDICO_ID'] : null,
                'nome' => $row['MEDICO_NOME'] ? corrigirCaracteres(trim($row['MEDICO_NOME'])) : null,
                'crm' => $row['CRM'] ? trim($row['CRM']) : null
            ],
            'localizacao' => [
                'unidade_id' => (int)$row['UNIDADE_ID'],
                'unidade_nome' => corrigirCaracteres(trim($row['CIDADE'] ?? 'Não especificado')),
                'endereco' => corrigirCaracteres(trim($row['UNIDADE_ENDERECO'] ?? '')),
                'sala' => corrigirCaracteres(trim($row['SALA'] ?? '')),
                'telefone' => trim($row['TELEFONE'] ?? '(84) 3315-6900')
            ],
            'configuracoes' => [
                'tempo_estimado_minutos' => (int)($row['TEMPO_ESTIMADO_MINUTOS'] ?? 0),
                'possui_retorno' => (bool)($row['POSSUI_RETORNO'] ?? false),
                'atende_comorbidade' => (bool)($row['ATENDE_COMORBIDADE'] ?? false)
            ],
            'limites' => [
                'vagas_dia' => (int)($row['LIMITE_VAGAS_DIA'] ?? 0),
                'retornos_dia' => (int)($row['LIMITE_RETORNOS_DIA'] ?? 0),
                'encaixes_dia' => (int)($row['LIMITE_ENCAIXES_DIA'] ?? 0)
            ],
            'horarios_por_dia' => $horariosPorDia,
            'convenios' => $convenios,
            'avisos' => [
                'observacoes' => corrigirCaracteres(trim($row['OBSERVACOES'] ?? '')),
                'informacoes_fixas' => corrigirCaracteres(trim($row['INFORMACOES_FIXAS'] ?? '')),
                'orientacoes' => corrigirCaracteres(trim($row['ORIENTACOES'] ?? ''))
            ]
        ];
    }

    ibase_free_result($result);
    ibase_commit($conn);
    ibase_close($conn);

    // Resposta
    $response = [
        'status' => 'sucesso',
        'termo_busca' => $termoBusca,
        'termo_mapeado' => $termoMapeado,
        'mapeamento_aplicado' => ($termoBusca !== $termoMapeado),
        'total_agendas' => count($agendas),
        'filtros' => [
            'cidade' => $cidadeFiltro ?: null
        ],
        'agendas' => $agendas
    ];

    if (empty($agendas)) {
        $response['message'] = "Nenhuma agenda encontrada para '$termoMapeado'";
        http_response_code(404);
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal Server Error',
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
}
?>
