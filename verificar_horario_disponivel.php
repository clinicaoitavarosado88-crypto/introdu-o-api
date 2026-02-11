<?php
// ============================================================================
// 🔧 ARQUIVO: verificar_horario_disponivel.php (CORRIGIDO)
// ============================================================================

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'includes/connection.php';

/**
 * ✅ FUNÇÃO CORRIGIDA: Validação local do horário (SEM validação de almoço)
 */
function validarHorarioLocal($horario, $data) {
    $resultado = ['valido' => true, 'mensagem' => ''];
    
    // Validar se não é data passada
    $data_agendamento = new DateTime($data);
    $hoje = new DateTime();
    
    if ($data_agendamento < $hoje->setTime(0, 0, 0)) {
        return [
            'valido' => false,
            'mensagem' => 'Não é possível agendar em data passada'
        ];
    }
    
    // Validar horário básico
    list($horas, $minutos) = explode(':', $horario);
    $horas = (int)$horas;
    $minutos = (int)$minutos;
    
    // Muito cedo ou muito tarde
    if ($horas < 6 || $horas > 22) {
        return [
            'valido' => false,
            'mensagem' => 'Horário fora do funcionamento básico (06:00 às 22:00)'
        ];
    }
    
    // ✅ REMOVIDO: Validação de horário de almoço
    // Agora qualquer horário entre 6h e 22h é considerado válido localmente
    
    return $resultado;
}

/**
 * ✅ FUNÇÃO CORRIGIDA: Verificar se horário está ocupado
 */
function verificarHorarioOcupado($conn, $agenda_id, $data, $horario) {
    try {
        $query = "SELECT COUNT(*) as TOTAL 
                  FROM AGENDAMENTOS 
                  WHERE AGENDA_ID = ? 
                  AND DATA_AGENDAMENTO = ? 
                  AND HORA_AGENDAMENTO = ? 
                  AND STATUS NOT IN ('CANCELADO', 'FALTOU')";
        
        $stmt = ibase_prepare($conn, $query);
        $horario_completo = $horario . ':00';
        $result = ibase_execute($stmt, $agenda_id, $data, $horario_completo);
        $row = ibase_fetch_assoc($result);
        
        $ocupado = ($row['TOTAL'] > 0);
        
        debug_log("Horário $horario " . ($ocupado ? 'OCUPADO' : 'DISPONÍVEL') . " (total: {$row['TOTAL']})");
        
        return $ocupado;
        
    } catch (Exception $e) {
        debug_log('Erro ao verificar ocupação: ' . $e->getMessage());
        return false; // Em caso de erro, considerar disponível
    }
}

/**
 * ✅ FUNÇÃO CORRIGIDA: Validar horário de funcionamento baseado em AGENDA_HORARIOS
 */
function validarHorarioFuncionamento($conn, $agenda_id, $data, $horario) {
    try {
        // Determinar dia da semana
        $data_obj = new DateTime($data);
        $dias_semana = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];
        $dia_semana = $dias_semana[$data_obj->format('w')];
        
        debug_log("Validando funcionamento para $dia_semana");
        
        // Buscar horários de funcionamento
        $query = "SELECT * FROM AGENDA_HORARIOS 
                  WHERE AGENDA_ID = ? 
                  AND TRIM(UPPER(DIA_SEMANA)) = TRIM(UPPER(?))";
        
        $stmt = ibase_prepare($conn, $query);
        $result = ibase_execute($stmt, $agenda_id, $dia_semana);
        $funcionamento = ibase_fetch_assoc($result);
        
        if (!$funcionamento) {
            // Tentar variações do nome do dia
            $variacoes = [
                'Segunda-feira' => ['Segunda', 'SEG'],
                'Terça-feira' => ['Terça', 'TER', 'Terca'],
                'Quarta-feira' => ['Quarta', 'QUA'],
                'Quinta-feira' => ['Quinta', 'QUI'],
                'Sexta-feira' => ['Sexta', 'SEX'],
                'Sábado' => ['Sabado', 'SAB'],
                'Domingo' => ['DOM']
            ];
            
            if (isset($variacoes[$dia_semana])) {
                foreach ($variacoes[$dia_semana] as $variacao) {
                    $stmt_var = ibase_prepare($conn, $query);
                    $result_var = ibase_execute($stmt_var, $agenda_id, $variacao);
                    $funcionamento = ibase_fetch_assoc($result_var);
                    
                    if ($funcionamento) {
                        debug_log("Encontrado funcionamento com variação: $variacao");
                        break;
                    }
                }
            }
        }
        
        if (!$funcionamento) {
            debug_log("Nenhum horário de funcionamento encontrado para $dia_semana");
            return [
                'valido' => false,
                'mensagem' => "Agenda não funciona em $dia_semana"
            ];
        }
        
        // Verificar se horário está dentro dos períodos de funcionamento
        list($horas, $minutos) = explode(':', $horario);
        $horario_minutos = (int)$horas * 60 + (int)$minutos;
        
        $dentro_funcionamento = false;
        $periodo_funcionamento = '';
        
        // Verificar manhã
        if ($funcionamento['HORARIO_INICIO_MANHA'] && $funcionamento['HORARIO_FIM_MANHA']) {
            $inicio_manha = $funcionamento['HORARIO_INICIO_MANHA'];
            $fim_manha = $funcionamento['HORARIO_FIM_MANHA'];
            
            list($ih, $im) = explode(':', substr($inicio_manha, 0, 5));
            list($fh, $fm) = explode(':', substr($fim_manha, 0, 5));
            
            $inicio_minutos = (int)$ih * 60 + (int)$im;
            $fim_minutos = (int)$fh * 60 + (int)$fm;
            
            if ($horario_minutos >= $inicio_minutos && $horario_minutos <= $fim_minutos) {
                $dentro_funcionamento = true;
                $periodo_funcionamento = 'manhã';
                debug_log("Horário $horario está no período da manhã ($inicio_manha - $fim_manha)");
            }
        }
        
        // Verificar tarde
        if (!$dentro_funcionamento && $funcionamento['HORARIO_INICIO_TARDE'] && $funcionamento['HORARIO_FIM_TARDE']) {
            $inicio_tarde = $funcionamento['HORARIO_INICIO_TARDE'];
            $fim_tarde = $funcionamento['HORARIO_FIM_TARDE'];
            
            list($ih, $im) = explode(':', substr($inicio_tarde, 0, 5));
            list($fh, $fm) = explode(':', substr($fim_tarde, 0, 5));
            
            $inicio_minutos = (int)$ih * 60 + (int)$im;
            $fim_minutos = (int)$fh * 60 + (int)$fm;
            
            if ($horario_minutos >= $inicio_minutos && $horario_minutos <= $fim_minutos) {
                $dentro_funcionamento = true;
                $periodo_funcionamento = 'tarde';
                debug_log("Horário $horario está no período da tarde ($inicio_tarde - $fim_tarde)");
            }
        }
        
        if ($dentro_funcionamento) {
            return [
                'valido' => true,
                'mensagem' => "Horário válido ($periodo_funcionamento)",
                'periodo' => $periodo_funcionamento
            ];
        } else {
            $horarios_texto = [];
            if ($funcionamento['HORARIO_INICIO_MANHA'] && $funcionamento['HORARIO_FIM_MANHA']) {
                $horarios_texto[] = "Manhã: " . substr($funcionamento['HORARIO_INICIO_MANHA'], 0, 5) . 
                                   " às " . substr($funcionamento['HORARIO_FIM_MANHA'], 0, 5);
            }
            if ($funcionamento['HORARIO_INICIO_TARDE'] && $funcionamento['HORARIO_FIM_TARDE']) {
                $horarios_texto[] = "Tarde: " . substr($funcionamento['HORARIO_INICIO_TARDE'], 0, 5) . 
                                   " às " . substr($funcionamento['HORARIO_FIM_TARDE'], 0, 5);
            }
            
            $mensagem = "Horário fora do funcionamento";
            if (!empty($horarios_texto)) {
                $mensagem .= " (" . implode(', ', $horarios_texto) . ")";
            }
            
            return [
                'valido' => false,
                'mensagem' => $mensagem
            ];
        }
        
    } catch (Exception $e) {
        debug_log('Erro ao validar funcionamento: ' . $e->getMessage());
        return [
            'valido' => true,
            'mensagem' => 'Horário aceito (erro na validação)'
        ];
    }
}

/**
 * ✅ FUNÇÃO AUXILIAR: Debug log
 */
function debug_log($mensagem) {
    error_log("verificar_horario_disponivel.php - $mensagem");
}

// ============================================================================
// EXECUÇÃO PRINCIPAL
// ============================================================================

try {
    debug_log('=== INÍCIO verificar_horario_disponivel.php ===');
    
    $agenda_id = $_GET['agenda_id'] ?? null;
    $data = $_GET['data'] ?? null;
    $horario = $_GET['horario'] ?? null;
    
    debug_log("Parâmetros recebidos: agenda_id=$agenda_id, data=$data, horario=$horario");
    
    if (!$agenda_id || !$data || !$horario) {
        echo json_encode([
            'disponivel' => false,
            'mensagem' => 'Parâmetros obrigatórios: agenda_id, data, horario'
        ]);
        exit;
    }
    
    // Validação local primeiro
    $validacao_local = validarHorarioLocal($horario, $data);
    if (!$validacao_local['valido']) {
        echo json_encode([
            'disponivel' => false,
            'mensagem' => $validacao_local['mensagem']
        ]);
        exit;
    }
    
    // Conectar ao banco
    $conn = conectarBanco();
    if (!$conn) {
        echo json_encode([
            'disponivel' => true,
            'mensagem' => 'Horário aceito (erro de conexão)'
        ]);
        exit;
    }
    
    try {
        // Validar horário de funcionamento baseado em AGENDA_HORARIOS
        $validacao_funcionamento = validarHorarioFuncionamento($conn, $agenda_id, $data, $horario);
        
        if (!$validacao_funcionamento['valido']) {
            echo json_encode([
                'disponivel' => false,
                'mensagem' => $validacao_funcionamento['mensagem']
            ]);
            exit;
        }
        
        // Verificar se horário está ocupado
        $ocupado = verificarHorarioOcupado($conn, $agenda_id, $data, $horario);
        
        if ($ocupado) {
            echo json_encode([
                'disponivel' => false,
                'mensagem' => 'Horário já está ocupado'
            ]);
        } else {
            echo json_encode([
                'disponivel' => true,
                'mensagem' => 'Horário disponível'
            ]);
        }
        
    } catch (Exception $e) {
        debug_log('Erro na verificação: ' . $e->getMessage());
        echo json_encode([
            'disponivel' => true,
            'mensagem' => 'Horário aceito (erro na verificação)'
        ]);
    } finally {
        if (isset($conn)) {
            ibase_close($conn);
        }
    }
    
} catch (Exception $e) {
    debug_log('Erro geral: ' . $e->getMessage());
    echo json_encode([
        'disponivel' => true,
        'mensagem' => 'Horário aceito (erro interno)'
    ]);
}

debug_log('=== FIM verificar_horario_disponivel.php ===');
?>