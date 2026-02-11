<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

include '../includes/connection.php';

// Só aceita POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    // Lê o JSON do body
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['client_name']) || !isset($input['client_email'])) {
        http_response_code(400);
        echo json_encode([
            'error' => 'Bad Request',
            'message' => 'client_name and client_email are required'
        ]);
        exit;
    }
    
    $client_name = trim($input['client_name']);
    $client_email = trim($input['client_email']);
    
    // Validar email
    if (!filter_var($client_email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode([
            'error' => 'Bad Request',
            'message' => 'Invalid email format'
        ]);
        exit;
    }
    
    // Gerar token único
    $token = base64_encode(openssl_random_pseudo_bytes(32));
    $token = str_replace(['+', '/', '='], ['-', '_', ''], $token);
    
    // Data de expiração (1 ano)
    $expires_at = date('Y-m-d H:i:s', strtotime('+1 year'));
    $created_at = date('Y-m-d H:i:s');
    
    // Verificar se a tabela existe, se não, criar
    try {
        $check_table = "SELECT FIRST 1 * FROM API_TOKENS";
        ibase_query($conn, $check_table);
    } catch (Exception $e) {
        // Criar tabela se não existir
        $create_table = "
            CREATE TABLE API_TOKENS (
                ID INTEGER NOT NULL,
                TOKEN VARCHAR(255) NOT NULL,
                CLIENT_NAME VARCHAR(255) NOT NULL,
                CLIENT_EMAIL VARCHAR(255) NOT NULL,
                CREATED_AT TIMESTAMP NOT NULL,
                EXPIRES_AT TIMESTAMP NOT NULL,
                IS_ACTIVE SMALLINT DEFAULT 1,
                LAST_USED TIMESTAMP,
                REQUEST_COUNT INTEGER DEFAULT 0,
                CONSTRAINT PK_API_TOKENS PRIMARY KEY (ID)
            )
        ";
        ibase_query($conn, $create_table);
        
        // Criar sequence
        $create_seq = "CREATE SEQUENCE API_TOKENS_SEQ";
        ibase_query($conn, $create_seq);
        
        // Criar trigger para auto increment
        $create_trigger = "
            CREATE TRIGGER API_TOKENS_BI FOR API_TOKENS
            ACTIVE BEFORE INSERT POSITION 0
            AS
            BEGIN
              IF (NEW.ID IS NULL) THEN
                NEW.ID = GEN_ID(API_TOKENS_SEQ, 1);
            END
        ";
        ibase_query($conn, $create_trigger);
    }
    
    // Inserir o token
    $sql = "INSERT INTO API_TOKENS (TOKEN, CLIENT_NAME, CLIENT_EMAIL, CREATED_AT, EXPIRES_AT, IS_ACTIVE) 
            VALUES (?, ?, ?, ?, ?, 1)";
    
    $stmt = ibase_prepare($conn, $sql);
    ibase_execute($stmt, $token, $client_name, $client_email, $created_at, $expires_at);
    
    // Resposta de sucesso
    $response = [
        'access_token' => $token,
        'token_type' => 'Bearer',
        'expires_in' => 31536000, // 1 ano em segundos
        'created_at' => $created_at,
        'client_name' => $client_name
    ];
    
    http_response_code(201);
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Erro ao gerar token: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal Server Error',
        'message' => 'Failed to generate token'
    ]);
}
?>