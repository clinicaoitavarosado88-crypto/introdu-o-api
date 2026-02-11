<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

// Incluir configuração da conexão com o banco
include 'includes/connection.php';
include 'includes/auditoria.php';

// Suporte para linha de comando
if (php_sapi_name() === 'cli') {
    parse_str(getenv('QUERY_STRING') ?: '', $_GET);
}

// Função removida - dados de observações agora vêm diretamente das colunas OBSERVACOES_ANTERIORES/NOVAS

try {
    // Verificar parâmetros obrigatórios
    $agendamentoId = $_GET['agendamento_id'] ?? null;
    
    if (!$agendamentoId) {
        throw new Exception("ID do agendamento é obrigatório");
    }
    
    if (!$conn) {
        throw new Exception("Erro na conexão com o banco de dados");
    }
    
    // Usar função existente para buscar histórico
    $filtros = [
        'agendamento_id' => $agendamentoId,
        'limit' => 100, // Limitar a 100 registros
        'offset' => 0
    ];
    
    $historico = buscarHistoricoAuditoria($conn, $filtros);
    
    // Função para decodificar dados BLOB antigos
    function decodificarDadosBlob($dados_antigos, $dados_novos, $campo) {
        $valor_anterior = null;
        $valor_novo = null;
        
        // Tentar extrair do BLOB se disponível
        if ($dados_antigos) {
            try {
                // Se for um resource (stream), ler o conteúdo
                if (is_resource($dados_antigos)) {
                    $dados_antigos = stream_get_contents($dados_antigos);
                }
                // Tentar decodificar como JSON
                $dados_decodificados = json_decode($dados_antigos, true);
                if ($dados_decodificados && isset($dados_decodificados[$campo])) {
                    $valor_anterior = $dados_decodificados[$campo];
                }
            } catch (Exception $e) {
                // Ignorar erro de decodificação
            }
        }
        
        if ($dados_novos) {
            try {
                // Se for um resource (stream), ler o conteúdo
                if (is_resource($dados_novos)) {
                    $dados_novos = stream_get_contents($dados_novos);
                }
                // Tentar decodificar como JSON
                $dados_decodificados = json_decode($dados_novos, true);
                if ($dados_decodificados && isset($dados_decodificados[$campo])) {
                    $valor_novo = $dados_decodificados[$campo];
                }
            } catch (Exception $e) {
                // Ignorar erro de decodificação
            }
        }
        
        return [$valor_anterior, $valor_novo];
    }
    
    // Formatar os dados para melhor apresentação
    $historicoFormatado = array_map(function($registro) use ($conn) {
        
        // Interpretar campos alterados para linguagem mais amigável (APENAS campos editáveis)
        $camposAmigaveis = [
            'email' => 'E-mail',
            'status' => 'Status',
            'tipo_consulta' => 'Tipo de Consulta',
            'observacoes' => 'Observações',
            'data_nascimento' => 'Data de Nascimento'
        ];
        
        // Os detalhes das observações já vêm diretamente do banco agora
        
        $camposAlteradosTexto = '';
        if (!empty($registro['CAMPOS_ALTERADOS'])) {
            $campos = explode(',', $registro['CAMPOS_ALTERADOS']);
            $camposFormatados = [];
            foreach ($campos as $campo) {
                $campo = trim($campo);
                // FILTRAR apenas campos editáveis
                if (array_key_exists($campo, $camposAmigaveis)) {
                    $camposFormatados[] = $camposAmigaveis[$campo];
                }
            }
            $camposAlteradosTexto = implode(', ', $camposFormatados);
        }
        
        // Determinar ícone e cor baseado na ação
        $iconesAcoes = [
            'CRIAR' => ['icone' => 'bi-plus-circle', 'cor' => 'text-green-600', 'titulo' => 'Criado'],
            'EDITAR' => ['icone' => 'bi-pencil', 'cor' => 'text-blue-600', 'titulo' => 'Editado'],
            'CANCELAR' => ['icone' => 'bi-x-circle', 'cor' => 'text-red-600', 'titulo' => 'Cancelado'],
            'CONFIRMAR' => ['icone' => 'bi-check-circle', 'cor' => 'text-green-600', 'titulo' => 'Confirmado'],
            'MOVER' => ['icone' => 'bi-arrow-right-circle', 'cor' => 'text-orange-600', 'titulo' => 'Movido'],
            'REAGENDAR' => ['icone' => 'bi-calendar-check', 'cor' => 'text-purple-600', 'titulo' => 'Reagendado'],
            'BLOQUEAR' => ['icone' => 'bi-lock', 'cor' => 'text-red-600', 'titulo' => 'Bloqueado'],
            'DESBLOQUEAR' => ['icone' => 'bi-unlock', 'cor' => 'text-green-600', 'titulo' => 'Desbloqueado']
        ];
        
        $acaoInfo = $iconesAcoes[$registro['ACAO']] ?? ['icone' => 'bi-clock-history', 'cor' => 'text-gray-600', 'titulo' => $registro['ACAO']];
        
        // Processar observações (podem vir como BLOB)
        $observacoes_anteriores = $registro['OBSERVACOES_ANTERIORES'];
        $observacoes_novas = $registro['OBSERVACOES_NOVAS'];
        
        // Converter BLOB para texto se necessário
        if ($observacoes_anteriores && is_resource($observacoes_anteriores)) {
            $observacoes_anteriores = stream_get_contents($observacoes_anteriores);
        }
        if ($observacoes_novas && is_resource($observacoes_novas)) {
            $observacoes_novas = stream_get_contents($observacoes_novas);
        }
        
        // Se as colunas específicas estão vazias ou com dados inválidos, tentar extrair dos BLOBs JSON
        if (empty($observacoes_anteriores) || empty($observacoes_novas) || 
            (is_string($observacoes_anteriores) && strpos($observacoes_anteriores, '0x') === 0) ||
            (is_string($observacoes_novas) && strpos($observacoes_novas, '0x') === 0)) {
            
            list($obs_blob_anterior, $obs_blob_nova) = decodificarDadosBlob(
                $registro['DADOS_ANTIGOS'] ?? null,
                $registro['DADOS_NOVOS'] ?? null,
                'observacoes'
            );
            
            if ((empty($observacoes_anteriores) || strpos($observacoes_anteriores, '0x') === 0) && $obs_blob_anterior) {
                $observacoes_anteriores = $obs_blob_anterior;
            }
            if ((empty($observacoes_novas) || strpos($observacoes_novas, '0x') === 0) && $obs_blob_nova) {
                $observacoes_novas = $obs_blob_nova;
            }
        }
        
        // Se ainda temos valores hexadecimais, substituir por mensagem amigável
        if (is_string($observacoes_anteriores) && strpos($observacoes_anteriores, '0x') === 0) {
            $observacoes_anteriores = '(conteúdo anterior não disponível)';
        }
        if (is_string($observacoes_novas) && strpos($observacoes_novas, '0x') === 0) {
            $observacoes_novas = '(conteúdo não disponível)';
        }
        
        return [
            'id' => $registro['ID'],
            'acao' => $registro['ACAO'],
            'acao_titulo' => $acaoInfo['titulo'],
            'acao_icone' => $acaoInfo['icone'],
            'acao_cor' => $acaoInfo['cor'],
            'usuario' => trim($registro['USUARIO']),
            'data_acao' => $registro['DATA_ACAO'],
            'data_acao_formatada' => date('d/m/Y \à\s H:i:s', strtotime($registro['DATA_ACAO'])),
            'ip_usuario' => $registro['IP_USUARIO'],
            'observacoes' => $registro['OBSERVACOES'] ? trim($registro['OBSERVACOES']) : null,
            'tabela_afetada' => $registro['TABELA_AFETADA'],
            'campos_alterados' => $registro['CAMPOS_ALTERADOS'],
            'campos_alterados_texto' => $camposAlteradosTexto,
            'paciente_nome' => $registro['PACIENTE_NOME'],
            'data_agendamento' => $registro['DATA_AGENDAMENTO'],
            'hora_agendamento' => $registro['HORA_AGENDAMENTO'],
            'status_anterior' => $registro['STATUS_ANTERIOR'],
            'status_novo' => $registro['STATUS_NOVO'],
            'tipo_consulta_anterior' => $registro['TIPO_CONSULTA_ANTERIOR'] ?? null,
            'tipo_consulta_novo' => $registro['TIPO_CONSULTA_NOVO'] ?? null,
            'observacoes_anteriores' => $observacoes_anteriores,
            'observacoes_novas' => $observacoes_novas,
            'numero_agendamento' => $registro['NUMERO_AGENDAMENTO']
        ];
    }, $historico);
    
    // Retornar resposta
    echo json_encode([
        'status' => 'sucesso',
        'agendamento_id' => $agendamentoId,
        'total_registros' => count($historicoFormatado),
        'historico' => $historicoFormatado
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'erro',
        'mensagem' => $e->getMessage(),
        'agendamento_id' => $agendamentoId ?? null
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
?>