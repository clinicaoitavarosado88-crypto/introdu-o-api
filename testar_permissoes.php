<?php
// testar_permissoes.php - Arquivo para testar o sistema de permissões
header('Content-Type: application/json');
include 'includes/connection.php';
include 'includes/verificar_permissao.php';

// Suporte para linha de comando
if (php_sapi_name() === 'cli') {
    parse_str(getenv('QUERY_STRING') ?: '', $_GET);
}

$usuario_teste = $_GET['usuario'] ?? $_POST['usuario'] ?? null;

if (!$usuario_teste) {
    echo json_encode([
        'erro' => 'Parâmetro usuario é obrigatório',
        'exemplo' => '?usuario=SEU_USUARIO_AQUI'
    ]);
    exit;
}

try {
    // Listar todas as permissões do usuário
    $permissoes = listarPermissoesUsuario($conn, $usuario_teste);
    
    // Verificar especificamente a permissão de administrar agendas
    $pode_admin_agenda = podeAdministrarAgendas($conn, $usuario_teste);
    
    // Informações do usuário atual detectado pelo sistema
    $usuario_atual_detectado = getUsuarioAtual();
    
    $resultado = [
        'usuario_testado' => $usuario_teste,
        'usuario_atual_detectado' => $usuario_atual_detectado,
        'pode_administrar_agendas' => $pode_admin_agenda,
        'total_permissoes' => count($permissoes),
        'permissoes' => $permissoes,
        'sistema_funcionando' => true
    ];
    
    echo json_encode($resultado, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'erro' => $e->getMessage(),
        'usuario_testado' => $usuario_teste
    ]);
}
?>