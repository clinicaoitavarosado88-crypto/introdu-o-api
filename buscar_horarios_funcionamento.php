<?php
// ============================================================================
// 🔧 ARQUIVO: buscar_horarios_funcionamento.php
// ============================================================================

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'db_config.php';

/**
 * ✅ FUNÇÃO: Buscar horários de funcionamento da agenda
 */
function buscarHorariosFuncionamento($agenda_id, $dia_semana) {
    $conn = conectarBanco();
    
    if (!$conn) {
        return [
            'sucesso' => false,
            'erro' => 'Erro de conexão com banco de dados'
        ];
    }
    
    try {
        error_log("buscar_horarios_funcionamento.php - Buscando agenda $agenda_id para $dia_semana");
        
        // Buscar horário exato primeiro
        $query_exato = "SELECT * FROM AGENDA_HORARIOS 
                        WHERE AGENDA_ID = ? 
                        AND TRIM(UPPER(DIA_SEMANA)) = TRIM(UPPER(?))";
        
        $stmt_exato = ibase_prepare($conn, $query_exato);
        $result_exato = ibase_execute($stmt_exato, $agenda_id, $dia_semana);
        $horario_funcionamento = ibase_fetch_assoc($result_exato);
        
        // Se não encontrou, tentar variações do dia da semana
        if (!$horario_funcionamento) {
            $variacoes_dias = [
                'Segunda-feira' => ['Segunda', 'SEG'],
                'Terça-feira' => ['Terça', 'TER', 'Terca'],
                'Quarta-feira' => ['Quarta', 'QUA'],
                'Quinta-feira' => ['Quinta', 'QUI'],
                'Sexta-feira' => ['Sexta', 'SEX'],
                'Sábado' => ['Sabado', 'SAB'],
                'Domingo' => ['DOM']
            ];
            
            if (isset($variacoes_dias[$dia_semana])) {
                foreach ($variacoes_dias[$dia_semana] as $variacao) {
                    $stmt_variacao = ibase_prepare($conn, $query_exato);
                    $result_variacao = ibase_execute($stmt_variacao, $agenda_id, $variacao);
                    $horario_funcionamento = ibase_fetch_assoc($result_variacao);
                    
                    if ($horario_funcionamento) {
                        error_log("buscar_horarios_funcionamento.php - Encontrado com variação: $variacao");
                        break;
                    }
                }
            }
        }
        
        // Se ainda não encontrou, buscar com LIKE
        if (!$horario_funcionamento) {
            $query_like = "SELECT * FROM AGENDA_HORARIOS 
                          WHERE AGENDA_ID = ? 
                          AND UPPER(TRIM(DIA_SEMANA)) LIKE ?";
            
            $stmt_like = ibase_prepare($conn, $query_like);
            $dia_like = strtoupper(substr($dia_semana, 0, 3)) . '%';
            $result_like = ibase_execute($stmt_like, $agenda_id, $dia_like);
            $horario_funcionamento = ibase_fetch_assoc($result_like);
            
            if ($horario_funcionamento) {
                error_log("buscar_horarios_funcionamento.php - Encontrado com LIKE: $dia_like");
            }
        }
        
        if ($horario_funcionamento) {
            return [
                'sucesso' => true,
                'horarios' => [
                    'dia_semana' => trim($horario_funcionamento['DIA_SEMANA']),
                    'manha_inicio' => $horario_funcionamento['HORARIO_INICIO_MANHA'] ? 
                        substr($horario_funcionamento['HORARIO_INICIO_MANHA'], 0, 5) : null,
                    'manha_fim' => $horario_funcionamento['HORARIO_FIM_MANHA'] ? 
                        substr($horario_funcionamento['HORARIO_FIM_MANHA'], 0, 5) : null,
                    'tarde_inicio' => $horario_funcionamento['HORARIO_INICIO_TARDE'] ? 
                        substr($horario_funcionamento['HORARIO_INICIO_TARDE'], 0, 5) : null,
                    'tarde_fim' => $horario_funcionamento['HORARIO_FIM_TARDE'] ? 
                        substr($horario_funcionamento['HORARIO_FIM_TARDE'], 0, 5) : null
                ]
            ];
        } else {
            error_log("buscar_horarios_funcionamento.php - Nenhum horário encontrado para agenda $agenda_id no dia $dia_semana");
            
            return [
                'sucesso' => false,
                'erro' => 'Horários de funcionamento não configurados para este dia',
                'agenda_id' => $agenda_id,
                'dia_solicitado' => $dia_semana
            ];
        }
        
    } catch (Exception $e) {
        error_log("buscar_horarios_funcionamento.php - Erro: " . $e->getMessage());
        
        return [
            'sucesso' => false,
            'erro' => 'Erro ao buscar horários: ' . $e->getMessage()
        ];
    } finally {
        if (isset($conn)) {
            ibase_close($conn);
        }
    }
}

// ============================================================================
// EXECUÇÃO PRINCIPAL
// ============================================================================

try {
    $agenda_id = $_GET['agenda_id'] ?? null;
    $dia_semana = $_GET['dia_semana'] ?? null;
    
    if (!$agenda_id || !$dia_semana) {
        echo json_encode([
            'sucesso' => false,
            'erro' => 'Parâmetros agenda_id e dia_semana são obrigatórios'
        ]);
        exit;
    }
    
    $resultado = buscarHorariosFuncionamento($agenda_id, $dia_semana);
    echo json_encode($resultado);
    
} catch (Exception $e) {
    error_log("buscar_horarios_funcionamento.php - Erro geral: " . $e->getMessage());
    
    echo json_encode([
        'sucesso' => false,
        'erro' => 'Erro interno do servidor'
    ]);
}
?>