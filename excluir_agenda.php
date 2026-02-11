<?php
include 'includes/connection.php';
include 'includes/auditoria.php';
include 'includes/verificar_permissao.php';

$id = (int) ($_POST['id'] ?? 0);

// Verificar permissão usando o sistema padronizado
if (!verificarPermissaoAdminAgenda($conn, 'excluir agenda')) {
    exit; // A função já envia resposta JSON de erro
}

$usuario_atual = getUsuarioAtual();

// Buscar dados da agenda antes de excluir para auditoria
$query_buscar = "SELECT ag.*, m.NOME as MEDICO_NOME, u.NOME_UNIDADE as UNIDADE_NOME
                 FROM AGENDAS ag
                 LEFT JOIN LAB_MEDICOS_PRES m ON ag.MEDICO_ID = m.ID
                 LEFT JOIN LAB_CIDADES u ON ag.UNIDADE_ID = u.ID
                 WHERE ag.ID = $id";
$result_buscar = ibase_query($conn, $query_buscar);
$agenda_dados = ibase_fetch_assoc($result_buscar);

// Usuário já foi obtido no início do arquivo

// Executa exclusão
$res = ibase_query($conn, "DELETE FROM AGENDAS WHERE ID = $id");

if ($res && $agenda_dados) {
    // Registrar auditoria da exclusão
    $dados_agenda_excluida = [
        'id' => $agenda_dados['ID'],
        'tipo_agenda' => utf8_encode($agenda_dados['TIPO_AGENDA'] ?? ''),
        'medico_id' => $agenda_dados['MEDICO_ID'],
        'medico_nome' => utf8_encode($agenda_dados['MEDICO_NOME'] ?? ''),
        'unidade_id' => $agenda_dados['UNIDADE_ID'],
        'unidade_nome' => utf8_encode($agenda_dados['UNIDADE_NOME'] ?? ''),
        'sala' => $agenda_dados['SALA'],
        'status' => $agenda_dados['STATUS'] ?? 'ATIVO'
    ];
    
    $observacoes_auditoria = sprintf(
        "AGENDA EXCLUÍDA: %s - Médico: %s - Unidade: %s - Sala: %s",
        $dados_agenda_excluida['tipo_agenda'],
        $dados_agenda_excluida['medico_nome'],
        $dados_agenda_excluida['unidade_nome'],
        $dados_agenda_excluida['sala'] ?? 'N/A'
    );
    
    registrarAuditoria($conn, [
        'acao' => 'EXCLUIR_AGENDA',
        'usuario' => $usuario_atual,
        'tabela_afetada' => 'AGENDAS',
        'agendamento_id' => null,
        'agenda_id' => $id,
        'dados_antigos' => json_encode($dados_agenda_excluida, JSON_UNESCAPED_UNICODE),
        'dados_novos' => null,
        'campos_alterados' => 'AGENDA_COMPLETA',
        'observacoes' => $observacoes_auditoria,
        'paciente_nome' => null,
        'status_anterior' => $dados_agenda_excluida['status'],
        'status_novo' => 'EXCLUIDA'
    ]);
    
    echo 'Agenda excluída com sucesso.';
} else {
    echo 'Erro ao excluir agenda.';
}
