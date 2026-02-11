<?php
include 'includes/connection.php';
include 'includes/auditoria.php';
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Método inválido']);
    exit;
}

$_POST['horarios'] = json_decode($_POST['horarios'] ?? '[]', true);
$_POST['convenios'] = json_decode($_POST['convenios'] ?? '[]', true);
$_POST['preparos'] = json_decode($_POST['preparos'] ?? '[]', true);
$_POST['vagas'] = json_decode($_POST['vagas'] ?? '{}', true); // ✅ CORREÇÃO: Processar vagas por dia

// ✅ Processar vagas por dia (logs removidos após confirmação)

function post($campo) {
    $valor = $_POST[$campo] ?? null;
    // Converter de UTF-8 para Windows-1252 para textos
    if (is_string($valor) && !is_numeric($valor)) {
        return mb_convert_encoding($valor, 'Windows-1252', 'UTF-8');
    }
    return $valor;
}

// Função específica para processar tempo estimado
function processar_tempo_estimado($valor) {
    if (empty($valor)) return null;
    
    // Extrair apenas números do valor (ex: "5 minutos" -> 5)
    if (preg_match('/(\d+)/', $valor, $matches)) {
        return (int)$matches[1];
    }
    
    return null;
}

function post_preparo($valor) {
    // Função específica para converter preparos
    if (is_string($valor)) {
        return mb_convert_encoding($valor, 'Windows-1252', 'UTF-8');
    }
    return $valor;
}

// Função específica para converter dias da semana
function converterDiaSemana($dia) {
    // Remove espaços extras
    $dia = trim($dia);
    
    // Mapa de conversão UTF-8 para Windows-1252
    $diasMap = [
        'Segunda' => iconv('UTF-8', 'Windows-1252//TRANSLIT', 'Segunda'),
        'Terça' => iconv('UTF-8', 'Windows-1252//TRANSLIT', 'Terça'),
        'Quarta' => iconv('UTF-8', 'Windows-1252//TRANSLIT', 'Quarta'),
        'Quinta' => iconv('UTF-8', 'Windows-1252//TRANSLIT', 'Quinta'),
        'Sexta' => iconv('UTF-8', 'Windows-1252//TRANSLIT', 'Sexta'),
        'Sábado' => iconv('UTF-8', 'Windows-1252//TRANSLIT', 'Sábado'),
        'Domingo' => iconv('UTF-8', 'Windows-1252//TRANSLIT', 'Domingo'),
        // Variações com -feira
        'Segunda-feira' => iconv('UTF-8', 'Windows-1252//TRANSLIT', 'Segunda-feira'),
        'Terça-feira' => iconv('UTF-8', 'Windows-1252//TRANSLIT', 'Terça-feira'),
        'Quarta-feira' => iconv('UTF-8', 'Windows-1252//TRANSLIT', 'Quarta-feira'),
        'Quinta-feira' => iconv('UTF-8', 'Windows-1252//TRANSLIT', 'Quinta-feira'),
        'Sexta-feira' => iconv('UTF-8', 'Windows-1252//TRANSLIT', 'Sexta-feira'),
        // Variações sem acento
        'Terca' => iconv('UTF-8', 'Windows-1252//TRANSLIT', 'Terça'),
        'Sabado' => iconv('UTF-8', 'Windows-1252//TRANSLIT', 'Sábado')
    ];
    
    // Se encontrar no mapa, retorna a conversão, senão tenta converter diretamente
    if (isset($diasMap[$dia])) {
        return $diasMap[$dia];
    }
    
    // Tenta converter diretamente
    return iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $dia);
}

try {
    $trans = ibase_trans(IBASE_DEFAULT, $conn);
    $idAgenda = (int) ($_POST['id'] ?? 0);

    // Verifica se já existe agenda do tipo consulta para o mesmo médico na mesma unidade, somente ao criar
    if ($idAgenda === 0 && post('tipo') === 'consulta' && post('medico_id')) {
        $tipo = post('tipo');
        $medico_id = (int) post('medico_id');
        $unidade_id = (int) post('unidade_id');

        $sqlVerifica = "SELECT ID FROM AGENDAS WHERE MEDICO_ID = ? AND TIPO = ? AND UNIDADE_ID = ?";
        $stmtVerifica = ibase_prepare($trans, $sqlVerifica);
        $resVerifica = ibase_execute($stmtVerifica, $medico_id, $tipo, $unidade_id);

        if (ibase_fetch_row($resVerifica)) {
            ibase_rollback($trans);
            echo json_encode([
                'status' => 'erro',
                'mensagem' => 'Este médico já possui uma agenda do tipo consulta nesta unidade.'
            ]);
            exit;
        }
    }

    if ($idAgenda > 0) {
        // === UPDATE AGENDA EXISTENTE ===
        $sql = "UPDATE AGENDAS SET
            UNIDADE_ID = ?, PROCEDIMENTO_ID = ?, MEDICO_ID = ?, TIPO = ?, SALA = ?, TEMPO_ESTIMADO_MINUTOS = ?,
            TELEFONE = ?, OBSERVACOES = ?, INFORMACOES_FIXAS = ?, ORIENTACOES = ?,
            IDADE_MINIMA_ATENDIMENTO = ?, POSSUI_RETORNO = ?, ATENDE_COMORBIDADE = ?, TIPO_AGENDA = ?, LIMITE_VAGAS_DIA = ?,
            LIMITE_ENCAIXES_DIA = ?, LIMITE_RETORNOS_DIA = ?
            WHERE ID = ?";
        $stmt = ibase_prepare($trans, $sql);

        $valores = [
            post('unidade_id') !== '' ? (int) post('unidade_id') : null,
            post('procedimento_id') !== '' ? (int) post('procedimento_id') : null,
            post('medico_id') !== '' ? (int) post('medico_id') : null,
            post('tipo'),
            post('sala'),
            processar_tempo_estimado(post('tempo_estimado_minutos')),
            post('telefone'),
            post('observacoes'),
            post('informacoes_fixas'),
            post('orientacoes'),
            post('idade_minima_atendimento') !== '' ? post('idade_minima_atendimento') : null,
            post('possui_retorno') !== '' ? post('possui_retorno') : null,
            post('atende_comorbidade') !== '' ? post('atende_comorbidade') : null,
            post('tipo_agenda'),
            post('limite_vagas_dia') !== '' ? (int) post('limite_vagas_dia') : null,
            post('limite_encaixes_dia') !== '' ? (int) post('limite_encaixes_dia') : 0,
            post('limite_retornos_dia') !== '' ? (int) post('limite_retornos_dia') : 0,
            $idAgenda
        ];
        if (!ibase_execute($stmt, ...$valores)) {
            throw new Exception('Erro ao atualizar a agenda.');
        }

        $agenda_id = $idAgenda;

        // Limpa antigos horários, convênios e preparos
        ibase_query($trans, "DELETE FROM AGENDA_HORARIOS WHERE AGENDA_ID = $agenda_id");
        ibase_query($trans, "DELETE FROM AGENDA_CONVENIOS WHERE AGENDA_ID = $agenda_id");
        ibase_query($trans, "DELETE FROM AGENDA_PREPAROS WHERE AGENDA_ID = $agenda_id");

    } else {
        // === INSERE NOVA AGENDA ===
        $sql = "INSERT INTO AGENDAS (
            UNIDADE_ID, PROCEDIMENTO_ID, MEDICO_ID, TIPO, SALA, TEMPO_ESTIMADO_MINUTOS,
            TELEFONE, OBSERVACOES, INFORMACOES_FIXAS, ORIENTACOES,
            IDADE_MINIMA_ATENDIMENTO, POSSUI_RETORNO, ATENDE_COMORBIDADE, TIPO_AGENDA, LIMITE_VAGAS_DIA,
            LIMITE_ENCAIXES_DIA, LIMITE_RETORNOS_DIA
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) RETURNING ID";
        $stmt = ibase_prepare($trans, $sql);

        $valores = [
            post('unidade_id') !== '' ? (int) post('unidade_id') : null,
            post('procedimento_id') !== '' ? (int) post('procedimento_id') : null,
            post('medico_id') !== '' ? (int) post('medico_id') : null,
            post('tipo'),
            post('sala'),
            processar_tempo_estimado(post('tempo_estimado_minutos')),
            post('telefone'),
            post('observacoes'),
            post('informacoes_fixas'),
            post('orientacoes'),
            post('idade_minima_atendimento') !== '' ? post('idade_minima_atendimento') : null,
            post('possui_retorno') !== '' ? post('possui_retorno') : null,
            post('atende_comorbidade') !== '' ? post('atende_comorbidade') : null,
            post('tipo_agenda'),
            post('limite_vagas_dia') !== '' ? (int) post('limite_vagas_dia') : null,
            post('limite_encaixes_dia') !== '' ? (int) post('limite_encaixes_dia') : 0,
            post('limite_retornos_dia') !== '' ? (int) post('limite_retornos_dia') : 0
        ];

        $res = ibase_execute($stmt, ...$valores);
        if (!$res) throw new Exception("Erro ao inserir nova agenda.");

        $agenda_id = ibase_fetch_row($res)[0];
    }

    // === INSERE HORÁRIOS ===
    foreach ($_POST['horarios'] as $h) {
        $sqlH = "INSERT INTO AGENDA_HORARIOS (
            AGENDA_ID, DIA_SEMANA, HORARIO_INICIO_MANHA, HORARIO_FIM_MANHA,
            HORARIO_INICIO_TARDE, HORARIO_FIM_TARDE, VAGAS_DIA
        ) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmtH = ibase_prepare($trans, $sqlH);
        
        // Converter dia para Windows-1252 usando a função específica
        $dia_convertido = converterDiaSemana($h['dia']);
        
        // ✅ CORREÇÃO: Obter vagas para este dia
        $slugDia = strtolower(substr($h['dia'], 0, 3)); // seg, ter, qua, etc.
        $vagasDia = $_POST['vagas'][$slugDia] ?? null;
        
        // Log para debug
        error_log("Salvando dia: " . $h['dia'] . " -> " . bin2hex($dia_convertido) . " | Vagas: " . $vagasDia);

        ibase_execute($stmtH,
            $agenda_id,
            $dia_convertido,
            $h['manha_ini'],
            $h['manha_fim'],
            $h['tarde_ini'],
            $h['tarde_fim'],
            $vagasDia
        );
    }

    // === INSERE CONVÊNIOS ===
    foreach ($_POST['convenios'] as $conv) {
        $sqlC = "INSERT INTO AGENDA_CONVENIOS (
            AGENDA_ID, CONVENIO_ID, LIMITE_ATENDIMENTOS, QTD_RETORNOS
        ) VALUES (?, ?, ?, ?)";
        $stmtC = ibase_prepare($trans, $sqlC);
        ibase_execute($stmtC,
            $agenda_id,
            $conv['id'],
            $conv['limite'] ?: null,
            $conv['retornos'] ?: null
        );
    }

    // === INSERE PREPAROS ===
    $preparos_mapeados = []; // ✅ CORREÇÃO: Mapear IDs temporários para reais
    foreach ($_POST['preparos'] as $index => $preparo) {
        if (!empty($preparo['titulo']) && !empty($preparo['instrucoes'])) {
            $sqlP = "INSERT INTO AGENDA_PREPAROS (
                AGENDA_ID, TITULO, INSTRUCOES, ORDEM
            ) VALUES (?, ?, ?, ?) RETURNING ID";
            $stmtP = ibase_prepare($trans, $sqlP);
            $resP = ibase_execute($stmtP,
                $agenda_id,
                post_preparo($preparo['titulo']),
                post_preparo($preparo['instrucoes']),
                $index + 1  // Ordem baseada no índice
            );
            
            // ✅ CORREÇÃO: Mapear ID temporário para real
            if ($resP && $rowP = ibase_fetch_row($resP)) {
                $idReal = $rowP[0];
                $idTemporario = $preparo['id'] ?? ($index + 1);
                $preparos_mapeados[] = [
                    'id_temporario' => $idTemporario,
                    'id_real' => $idReal,
                    'titulo' => $preparo['titulo']
                ];
                error_log("📝 Preparo mapeado: ID temp $idTemporario -> ID real $idReal");
            }
        }
    }

    // ============================================================================
    // AUDITORIA COMPLETA DA AGENDA SALVA/CRIADA
    // ============================================================================
    
    // Buscar informações do usuário atual
    $usuario_atual = isset($_COOKIE['log_usuario']) ? $_COOKIE['log_usuario'] : 'RENISON';
    
    // Buscar dados completos da agenda para auditoria
    $query_buscar_agenda = "SELECT 
                              ag.*, 
                              m.NOME as MEDICO_NOME,
                              u.NOME_UNIDADE as UNIDADE_NOME,
                              p.NOME as PROCEDIMENTO_NOME
                            FROM AGENDAS ag
                            LEFT JOIN LAB_MEDICOS_PRES m ON ag.MEDICO_ID = m.ID
                            LEFT JOIN LAB_CIDADES u ON ag.UNIDADE_ID = u.ID  
                            LEFT JOIN GRUPO_EXAMES p ON ag.PROCEDIMENTO_ID = p.ID
                            WHERE ag.ID = ?";
    
    $stmt_buscar_agenda = ibase_prepare($trans, $query_buscar_agenda);
    $result_buscar_agenda = ibase_execute($stmt_buscar_agenda, $agenda_id);
    
    if ($result_buscar_agenda && $row_agenda = ibase_fetch_assoc($result_buscar_agenda)) {
        // Preparar dados para auditoria
        $dados_agenda = [
            'id' => $agenda_id,
            'unidade_id' => $row_agenda['UNIDADE_ID'],
            'unidade_nome' => utf8_encode($row_agenda['UNIDADE_NOME'] ?? ''),
            'medico_id' => $row_agenda['MEDICO_ID'],
            'medico_nome' => utf8_encode($row_agenda['MEDICO_NOME'] ?? ''),
            'procedimento_id' => $row_agenda['PROCEDIMENTO_ID'],
            'procedimento_nome' => utf8_encode($row_agenda['PROCEDIMENTO_NOME'] ?? ''),
            'tipo_agenda' => utf8_encode($row_agenda['TIPO_AGENDA'] ?? ''),
            'sala' => $row_agenda['SALA'],
            'telefone' => $row_agenda['TELEFONE'],
            'tempo_estimado' => $row_agenda['TEMPO_ESTIMADO_MINUTOS'],
            'possui_retorno' => $row_agenda['POSSUI_RETORNO'],
            'limite_vagas_dia' => $row_agenda['LIMITE_VAGAS_DIA'],
            'limite_encaixes_dia' => $row_agenda['LIMITE_ENCAIXES_DIA'],
            'limite_retornos_dia' => $row_agenda['LIMITE_RETORNOS_DIA']
        ];
        
        $acao_auditoria = ($idAgenda > 0) ? 'EDITAR_AGENDA' : 'CRIAR_AGENDA';
        
        // Preparar observações para auditoria
        $observacoes_auditoria = sprintf(
            "AGENDA %s: %s - Médico: %s - Unidade: %s - Sala: %s",
            ($idAgenda > 0) ? 'EDITADA' : 'CRIADA',
            $dados_agenda['tipo_agenda'],
            $dados_agenda['medico_nome'],
            $dados_agenda['unidade_nome'],
            $dados_agenda['sala'] ?? 'N/A'
        );
        
        // Registrar auditoria
        registrarAuditoria($trans, [
            'acao' => $acao_auditoria,
            'usuario' => $usuario_atual,
            'tabela_afetada' => 'AGENDAS',
            'agendamento_id' => null,
            'agenda_id' => $agenda_id,
            'dados_novos' => json_encode($dados_agenda, JSON_UNESCAPED_UNICODE),
            'dados_antigos' => null, // Para simplificar, não vamos buscar dados anteriores no UPDATE
            'campos_alterados' => 'CONFIGURACAO_AGENDA_COMPLETA',
            'observacoes' => $observacoes_auditoria,
            'paciente_nome' => null,
            'status_anterior' => null,
            'status_novo' => 'ATIVA'
        ]);
        
        error_log("✅ Auditoria de $acao_auditoria registrada para agenda ID $agenda_id");
    }

    ibase_commit($trans);
    echo json_encode([
        'status' => 'sucesso', 
        'mensagem' => 'Agenda salva com sucesso',
        'agenda_id' => $agenda_id, // ✅ CORREÇÃO: Retornar ID da agenda para uploads
        'preparos_mapeados' => $preparos_mapeados // ✅ CORREÇÃO: Mapear IDs para uploads
    ]);
} catch (Exception $e) {
    ibase_rollback($trans);
    echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
}
?>