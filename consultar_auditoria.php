<?php
// consultar_auditoria.php
header('Content-Type: application/json');
include 'includes/connection.php';
include 'includes/auditoria.php';

// Suporte para linha de comando
if (php_sapi_name() === 'cli') {
    parse_str(getenv('QUERY_STRING') ?: '', $_GET);
}

try {
    // Parâmetros de filtro
    $filtros = [
        'agendamento_id' => $_GET['agendamento_id'] ?? null,
        'usuario' => $_GET['usuario'] ?? null,
        'acao' => $_GET['acao'] ?? null,
        'agenda_id' => $_GET['agenda_id'] ?? null,
        'data_inicio' => $_GET['data_inicio'] ?? null,
        'data_fim' => $_GET['data_fim'] ?? null,
        'paciente_nome' => $_GET['paciente_nome'] ?? null,
        'limit' => (int)($_GET['limit'] ?? 50),
        'offset' => (int)($_GET['offset'] ?? 0)
    ];
    
    // Remover filtros vazios
    $filtros = array_filter($filtros, function($valor) {
        return $valor !== null && $valor !== '';
    });
    
    // Buscar histórico
    $historico = buscarHistoricoAuditoria($conn, $filtros);
    
    // Estatísticas do período
    $estatisticas = [];
    if (!empty($historico)) {
        $acoes = array_column($historico, 'ACAO');
        $usuarios = array_column($historico, 'USUARIO');
        
        $estatisticas = [
            'total_registros' => count($historico),
            'acoes_mais_frequentes' => array_count_values($acoes),
            'usuarios_mais_ativos' => array_count_values($usuarios),
            'periodo_consulta' => [
                'inicio' => $filtros['data_inicio'] ?? 'Não especificado',
                'fim' => $filtros['data_fim'] ?? 'Não especificado'
            ]
        ];
    }
    
    // Formatar resposta
    $resposta = [
        'status' => 'sucesso',
        'filtros_aplicados' => $filtros,
        'estatisticas' => $estatisticas,
        'total_registros' => count($historico),
        'historico' => $historico
    ];
    
    // Se solicitado apenas estatísticas
    if (isset($_GET['apenas_estatisticas']) && $_GET['apenas_estatisticas'] === 'true') {
        $resposta['historico'] = [];
        $resposta['mensagem'] = 'Apenas estatísticas solicitadas';
    }
    
    echo json_encode($resposta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => $e->getMessage(),
        'codigo_erro' => $e->getCode()
    ]);
}

// Documentação da API
if (isset($_GET['help']) || isset($_GET['doc'])) {
    echo "\n\n=== DOCUMENTAÇÃO DA API DE AUDITORIA ===\n\n";
    echo "ENDPOINT: consultar_auditoria.php\n";
    echo "MÉTODO: GET\n\n";
    echo "PARÂMETROS DISPONÍVEIS:\n";
    echo "- agendamento_id: ID específico do agendamento\n";
    echo "- usuario: Filtrar por usuário\n";
    echo "- acao: Filtrar por ação (CRIAR, EDITAR, CANCELAR, BLOQUEAR, DESBLOQUEAR)\n";
    echo "- agenda_id: Filtrar por ID da agenda\n";
    echo "- data_inicio: Data inicial (YYYY-MM-DD)\n";
    echo "- data_fim: Data final (YYYY-MM-DD)\n";
    echo "- paciente_nome: Buscar por nome do paciente\n";
    echo "- limit: Limite de registros (padrão: 50)\n";
    echo "- offset: Pular registros (paginação)\n";
    echo "- apenas_estatisticas: true para retornar só estatísticas\n";
    echo "- help ou doc: Mostrar esta documentação\n\n";
    echo "EXEMPLOS:\n";
    echo "- Tudo de hoje: ?data_inicio=2025-08-14&data_fim=2025-08-14\n";
    echo "- Cancelamentos do usuário RENISON: ?acao=CANCELAR&usuario=RENISON\n";
    echo "- Histórico de um agendamento: ?agendamento_id=150\n";
    echo "- Agenda específica: ?agenda_id=2&data_inicio=2025-08-01\n";
}
?>