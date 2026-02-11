<?php
// atualizar_situacao_agenda.php
header('Content-Type: application/json');
include 'includes/connection.php';
include 'includes/verificar_permissao.php';

try {
    // Verificar permissão antes de executar qualquer ação
    if (!verificarPermissaoAdminAgenda($conn, 'atualizar situação de agenda')) {
        exit; // A função já envia a resposta JSON de erro
    }
    
    // Validar parâmetros
    $agenda_id = $_POST['agenda_id'] ?? null;
    $nova_situacao = $_POST['nova_situacao'] ?? null;
    
    if (!$agenda_id || !is_numeric($agenda_id)) {
        throw new Exception('ID da agenda não fornecido ou inválido');
    }
    
    if ($nova_situacao === null || !in_array($nova_situacao, ['0', '1'])) {
        throw new Exception('Nova situação deve ser 0 (inativa) ou 1 (ativa)');
    }
    
    $agenda_id = (int) $agenda_id;
    $nova_situacao = (int) $nova_situacao;
    
    // Verificar se a agenda existe
    $query_verificar = "SELECT ID, SITUACAO FROM AGENDAS WHERE ID = ?";
    $stmt_verificar = ibase_prepare($conn, $query_verificar);
    $result_verificar = ibase_execute($stmt_verificar, $agenda_id);
    $agenda_atual = ibase_fetch_assoc($result_verificar);
    
    if (!$agenda_atual) {
        throw new Exception('Agenda não encontrada');
    }
    
    // Verificar se a situação já é a desejada
    if ((int) $agenda_atual['SITUACAO'] === $nova_situacao) {
        $status_texto = $nova_situacao ? 'ativa' : 'inativa';
        throw new Exception("A agenda já está $status_texto");
    }
    
    // Atualizar a situação da agenda
    $query_update = "UPDATE AGENDAS SET SITUACAO = ? WHERE ID = ?";
    $stmt_update = ibase_prepare($conn, $query_update);
    $result_update = ibase_execute($stmt_update, $nova_situacao, $agenda_id);
    
    if (!$result_update) {
        $error_info = ibase_errmsg();
        throw new Exception('Erro ao atualizar agenda: ' . $error_info);
    }
    
    // Log da ação
    $usuario_atual = getUsuarioAtual();
    $acao = $nova_situacao ? 'ativou' : 'desativou';
    error_log("Usuário '$usuario_atual' $acao a agenda ID $agenda_id");
    
    // Resposta de sucesso
    $status_texto = $nova_situacao ? 'ativada' : 'desativada';
    echo json_encode([
        'status' => 'sucesso',
        'mensagem' => "Agenda $status_texto com sucesso",
        'nova_situacao' => $nova_situacao,
        'agenda_id' => $agenda_id
    ]);
    
} catch (Exception $e) {
    error_log("Erro ao atualizar situação da agenda: " . $e->getMessage());
    echo json_encode([
        'status' => 'erro',
        'mensagem' => $e->getMessage()
    ]);
}
?>