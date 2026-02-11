<?php
/**
 * Middleware de Autenticação por Token
 * Inclua este arquivo nos endpoints que precisam de autenticação
 */

function verify_api_token() {
    // Headers para CORS
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: Authorization, Content-Type');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');

    // Se for OPTIONS, retorna 200 para CORS
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }

    // Obter o token do header Authorization
    $auth_header = null;

    // Tentar pegar do $_SERVER primeiro (funciona em mais ambientes)
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $auth_header = $_SERVER['HTTP_AUTHORIZATION'];
    } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $auth_header = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    } elseif (function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        foreach ($headers as $key => $value) {
            if (strtolower($key) === 'authorization') {
                $auth_header = $value;
                break;
            }
        }
    } elseif (function_exists('getallheaders')) {
        $headers = getallheaders();
        foreach ($headers as $key => $value) {
            if (strtolower($key) === 'authorization') {
                $auth_header = $value;
                break;
            }
        }
    }

    if (!$auth_header) {
        return ['valid' => false, 'message' => 'Authorization header missing'];
    }

    // Verificar formato Bearer Token
    if (!preg_match('/^Bearer\s+(.+)$/i', $auth_header, $matches)) {
        return ['valid' => false, 'message' => 'Invalid authorization format. Use: Bearer <token>'];
    }

    $token = $matches[1];

    // Verificar token no banco
    try {
        include_once __DIR__ . '/connection.php';
        global $conn;

        $sql = "SELECT ID, CLIENT_NAME, CLIENT_EMAIL, EXPIRES_AT, IS_ACTIVE, REQUEST_COUNT
                FROM API_TOKENS
                WHERE TOKEN = ? AND IS_ACTIVE = 1";

        $stmt = ibase_prepare($conn, $sql);
        $result = ibase_execute($stmt, $token);
        $token_data = ibase_fetch_assoc($result);

        if (!$token_data) {
            return ['valid' => false, 'message' => 'Invalid token'];
        }

        // Verificar se não expirou
        $expires_at = new DateTime($token_data['EXPIRES_AT']);
        $now = new DateTime();

        if ($now > $expires_at) {
            return ['valid' => false, 'message' => 'Token expired'];
        }

        // Atualizar último uso e contador
        $update_sql = "UPDATE API_TOKENS SET LAST_USED = ?, REQUEST_COUNT = REQUEST_COUNT + 1 WHERE ID = ?";
        $update_stmt = ibase_prepare($conn, $update_sql);
        ibase_execute($update_stmt, date('Y-m-d H:i:s'), $token_data['ID']);

        // Definir informações do cliente para uso nos endpoints
        define('API_CLIENT_NAME', $token_data['CLIENT_NAME']);
        define('API_CLIENT_EMAIL', $token_data['CLIENT_EMAIL']);
        define('API_TOKEN_ID', $token_data['ID']);

        return ['valid' => true, 'client' => $token_data['CLIENT_NAME']];

    } catch (Exception $e) {
        error_log("Erro ao verificar token: " . $e->getMessage());
        return ['valid' => false, 'message' => 'Token verification failed: ' . $e->getMessage()];
    }
}

// Alias para manter compatibilidade
function verifyToken() {
    $result = verify_api_token();
    if (!$result['valid']) {
        sendUnauthorized($result['message']);
        return false;
    }
    return true;
}

function sendUnauthorized($message) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Unauthorized',
        'message' => $message
    ]);
    exit;
}
?>