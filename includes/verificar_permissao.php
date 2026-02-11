<?php
// verificar_permissao.php
// Sistema de verificação de permissões baseado nas tabelas VERBOS e VERBOS_PERMISSAO

// Verificar se é uma requisição para obter o usuário atual
if (isset($_GET['acao']) && $_GET['acao'] === 'obter_usuario_atual') {
    header('Content-Type: application/json');
    include 'connection.php';
    
    $usuario_atual = getUsuarioAtual();
    echo json_encode([
        'usuario' => $usuario_atual,
        'fonte' => $usuario_atual ? (isset($_COOKIE['log_usuario']) ? 'cookie' : 'outro') : null
    ]);
    exit;
}

/**
 * Verifica se o usuário atual tem permissão para executar uma ação específica
 * 
 * @param resource $conn Conexão com o banco de dados Firebird
 * @param string $usuario ID do usuário (LOG_USUARI)
 * @param int $id_verbo ID do verbo/permissão a verificar
 * @return bool True se o usuário tem permissão, False caso contrário
 */
function verificarPermissaoUsuario($conn, $usuario, $id_verbo) {
    try {
        // Query para verificar se o usuário tem a permissão específica e ativa
        $query = "SELECT COUNT(*) as TEM_PERMISSAO 
                  FROM VERBOS_PERMISSAO vp 
                  JOIN VERBOS v ON v.ID = vp.IDVERBO 
                  WHERE vp.LOG_USUARI = ? 
                  AND vp.IDVERBO = ? 
                  AND vp.AI = 1";
        
        $stmt = ibase_prepare($conn, $query);
        $result = ibase_execute($stmt, $usuario, $id_verbo);
        
        if ($result) {
            $row = ibase_fetch_assoc($result);
            return (int)$row['TEM_PERMISSAO'] > 0;
        }
        
        return false;
        
    } catch (Exception $e) {
        error_log("Erro ao verificar permissão: " . $e->getMessage());
        return false;
    }
}

/**
 * Verifica se o usuário atual pode administrar agendas (ID 98)
 * 
 * @param resource $conn Conexão com o banco de dados
 * @param string $usuario ID do usuário
 * @return bool True se pode administrar agendas
 */
function podeAdministrarAgendas($conn, $usuario) {
    return verificarPermissaoUsuario($conn, $usuario, 98); // ID 98 = "Administrar agenda"
}

/**
 * Obtém o usuário atual da sessão ou de uma fonte configurável
 * Integrado com o sistema principal que usa cookie "log_usuario"
 * 
 * @return string|null ID do usuário logado ou null se não autenticado
 */
function getUsuarioAtual() {
    // PRIORIDADE 1: Cookie do sistema principal "log_usuario"
    if (isset($_COOKIE['log_usuario']) && !empty($_COOKIE['log_usuario'])) {
        return $_COOKIE['log_usuario'];
    }
    
    // PRIORIDADE 2: Via POST/GET (para desenvolvimento/debug)
    if (isset($_POST['usuario_atual']) && !empty($_POST['usuario_atual'])) {
        return $_POST['usuario_atual'];
    }
    
    if (isset($_GET['usuario_atual']) && !empty($_GET['usuario_atual'])) {
        return $_GET['usuario_atual'];
    }
    
    // PRIORIDADE 3: Via sessão PHP (se usado)
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (isset($_SESSION['usuario_id']) && !empty($_SESSION['usuario_id'])) {
        return $_SESSION['usuario_id'];
    }
    
    // PRIORIDADE 4: Outros cookies
    if (isset($_COOKIE['usuario_logado']) && !empty($_COOKIE['usuario_logado'])) {
        return $_COOKIE['usuario_logado'];
    }
    
    // FALLBACK: Usuário padrão para desenvolvimento (remover em produção)
    // return 'RENISON';
    
    // Se não encontrou usuário logado, retorna null
    return null;
}

/**
 * Middleware para verificar permissão de administrar agendas
 * Retorna JSON de erro se não tiver permissão
 * 
 * @param resource $conn Conexão com banco
 * @param string $acao Nome da ação sendo executada (para log)
 * @return bool True se tem permissão, False e envia resposta JSON se não tem
 */
function verificarPermissaoAdminAgenda($conn, $acao = 'ação') {
    $usuario_atual = getUsuarioAtual();
    
    // Se não conseguiu identificar o usuário, negar acesso
    if (!$usuario_atual) {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'erro',
            'mensagem' => 'Usuário não autenticado'
        ]);
        return false;
    }
    
    // Verificar se o usuário tem permissão
    if (!podeAdministrarAgendas($conn, $usuario_atual)) {
        error_log("Acesso negado para usuário '$usuario_atual' na ação: $acao");
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'erro', 
            'mensagem' => 'Você não tem permissão para executar esta ação'
        ]);
        return false;
    }
    
    // Log de acesso autorizado
    error_log("Acesso autorizado para usuário '$usuario_atual' na ação: $acao");
    return true;
}

/**
 * Lista todas as permissões de um usuário (útil para debug)
 */
function listarPermissoesUsuario($conn, $usuario) {
    $query = "SELECT v.ID, v.NOME 
              FROM VERBOS_PERMISSAO vp 
              JOIN VERBOS v ON v.ID = vp.IDVERBO 
              WHERE vp.LOG_USUARI = ? 
              AND vp.AI = 1 
              ORDER BY v.NOME";
    
    $stmt = ibase_prepare($conn, $query);
    $result = ibase_execute($stmt, $usuario);
    
    $permissoes = [];
    while ($row = ibase_fetch_assoc($result)) {
        $permissoes[] = [
            'id' => $row['ID'],
            'nome' => utf8_encode(trim($row['NOME']))
        ];
    }
    
    return $permissoes;
}
?>