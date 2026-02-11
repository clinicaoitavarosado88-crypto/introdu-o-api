<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
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

    // Lê os dados do POST
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        // Fallback para form data
        $input = $_POST;
    }

    // Validar campos obrigatórios
    $nome = trim($input['nome'] ?? '');
    $cpf = trim($input['cpf'] ?? '');
    $data_nascimento = trim($input['data_nascimento'] ?? '');
    $telefone = trim($input['telefone'] ?? '');

    // Campos opcionais
    $email = trim($input['email'] ?? '');
    $endereco = trim($input['endereco'] ?? '');
    $cep = trim($input['cep'] ?? '');
    $cidade = trim($input['cidade'] ?? '');
    $estado = trim($input['estado'] ?? '');
    $nome_mae = trim($input['nome_mae'] ?? '');
    $rg = trim($input['rg'] ?? '');
    $profissao = trim($input['profissao'] ?? '');
    $sexo = trim($input['sexo'] ?? '');
    $estado_civil = trim($input['estado_civil'] ?? '');

    // Validações
    if (empty($nome)) {
        http_response_code(400);
        echo json_encode(['error' => 'Bad Request', 'message' => 'Nome é obrigatório']);
        exit;
    }

    if (empty($data_nascimento)) {
        http_response_code(400);
        echo json_encode(['error' => 'Bad Request', 'message' => 'Data de nascimento é obrigatória']);
        exit;
    }

    // Validar formato da data
    $data_nascimento_obj = DateTime::createFromFormat('Y-m-d', $data_nascimento);
    if (!$data_nascimento_obj || $data_nascimento_obj->format('Y-m-d') !== $data_nascimento) {
        http_response_code(400);
        echo json_encode(['error' => 'Bad Request', 'message' => 'Formato de data inválido. Use YYYY-MM-DD']);
        exit;
    }

    if (empty($telefone)) {
        http_response_code(400);
        echo json_encode(['error' => 'Bad Request', 'message' => 'Telefone é obrigatório']);
        exit;
    }

    // Validar email se fornecido
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['error' => 'Bad Request', 'message' => 'Formato de email inválido']);
        exit;
    }

    // Verificar se CPF já existe (se fornecido)
    if (!empty($cpf)) {
        $cpf_limpo = preg_replace('/[^0-9]/', '', $cpf);
        $sql_check_cpf = "SELECT IDPACIENTE FROM LAB_PACIENTES WHERE CPF LIKE '%$cpf_limpo%' AND IDPACIENTE > 0";
        $result_check = ibase_query($conn, $sql_check_cpf);
        if ($existing = ibase_fetch_assoc($result_check)) {
            http_response_code(409);
            echo json_encode([
                'error' => 'Conflict',
                'message' => 'CPF já cadastrado',
                'paciente_existente_id' => (int)$existing['IDPACIENTE']
            ]);
            exit;
        }
    }

    // Obter próximo ID
    $sql_next_id = "SELECT MAX(IDPACIENTE) + 1 as NEXT_ID FROM LAB_PACIENTES";
    $result_id = ibase_query($conn, $sql_next_id);
    $next_id_row = ibase_fetch_assoc($result_id);
    $next_id = $next_id_row['NEXT_ID'] ?: 1;

    // Preparar dados para inserção
    $nome_upper = mb_convert_encoding(strtoupper($nome), 'Windows-1252', 'UTF-8');
    $email_lower = strtolower($email);
    $data_cadastro = date('Y-m-d H:i:s');

    // SQL para inserir paciente - usando colunas que existem na tabela
    $sql_insert = "
        INSERT INTO LAB_PACIENTES (
            IDPACIENTE, PACIENTE, CPF, ANIVERSARIO, FONE1, EMAIL,
            ENDERECO, CEP, CIDADE, UF, MAE, RG,
            PROFISSAO, SEXO, DAT_INSERT, USU_INSERT
        ) VALUES (
            ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?
        )
    ";

    $stmt = ibase_prepare($conn, $sql_insert);
    $success = ibase_execute(
        $stmt,
        $next_id,
        $nome_upper,
        $cpf,
        $data_nascimento,
        $telefone,
        $email_lower,
        mb_convert_encoding($endereco, 'Windows-1252', 'UTF-8'),
        $cep,
        mb_convert_encoding($cidade, 'Windows-1252', 'UTF-8'),
        mb_convert_encoding($estado, 'Windows-1252', 'UTF-8'),
        mb_convert_encoding($nome_mae, 'Windows-1252', 'UTF-8'),
        $rg,
        mb_convert_encoding($profissao, 'Windows-1252', 'UTF-8'),
        $sexo,
        $data_cadastro,
        'API'
    );

    if (!$success) {
        throw new Exception('Falha ao inserir paciente no banco de dados');
    }

    // Commit da transação
    ibase_commit($conn);

    // Resposta de sucesso
    $response = [
        'status' => 'sucesso',
        'message' => 'Paciente cadastrado com sucesso',
        'paciente' => [
            'id' => $next_id,
            'nome' => $nome,
            'cpf' => $cpf,
            'data_nascimento' => $data_nascimento,
            'telefone' => $telefone,
            'email' => $email,
            'data_cadastro' => $data_cadastro
        ]
    ];

    http_response_code(201);
    echo json_encode($response, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    // Rollback em caso de erro
    if (isset($conn)) {
        ibase_rollback($conn);
    }

    error_log("Erro ao cadastrar paciente: " . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'error' => 'Internal Server Error',
        'message' => 'Erro ao cadastrar paciente'
    ]);
}
?>