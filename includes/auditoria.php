<?php
// auditoria.php
// Sistema de auditoria para agenda

/**
 * Registra uma ação de auditoria na base de dados
 * 
 * @param resource $conn Conexão com o banco de dados
 * @param array $params Parâmetros da auditoria
 * @return bool True se registrado com sucesso, False se erro
 */
function registrarAuditoria($conn, $params) {
    try {
        // Parâmetros obrigatórios
        $acao = $params['acao'] ?? '';
        $usuario = $params['usuario'] ?? '';
        $tabela_afetada = $params['tabela_afetada'] ?? 'AGENDAMENTOS';
        
        if (empty($acao) || empty($usuario)) {
            error_log("AUDITORIA ERROR: Ação e usuário são obrigatórios");
            return false;
        }
        
        // Parâmetros opcionais
        $agendamento_id = $params['agendamento_id'] ?? null;
        $numero_agendamento = $params['numero_agendamento'] ?? null;
        $ip_usuario = $params['ip_usuario'] ?? obterIPUsuario();
        $dados_antigos = $params['dados_antigos'] ?? null;
        $dados_novos = $params['dados_novos'] ?? null;
        $campos_alterados = $params['campos_alterados'] ?? null;
        $observacoes = $params['observacoes'] ?? null;
        $agenda_id = $params['agenda_id'] ?? null;
        $paciente_nome = $params['paciente_nome'] ?? null;
        $data_agendamento = $params['data_agendamento'] ?? null;
        $hora_agendamento = $params['hora_agendamento'] ?? null;
        $status_anterior = $params['status_anterior'] ?? null;
        $status_novo = $params['status_novo'] ?? null;
        
        // Converter arrays/objects para JSON
        if ($dados_antigos && !is_string($dados_antigos)) {
            $dados_antigos = json_encode($dados_antigos, JSON_UNESCAPED_UNICODE);
        }
        if ($dados_novos && !is_string($dados_novos)) {
            $dados_novos = json_encode($dados_novos, JSON_UNESCAPED_UNICODE);
        }
        if (is_array($campos_alterados)) {
            $campos_alterados = implode(', ', $campos_alterados);
        }
        
        // Limitar tamanhos dos campos
        $usuario = substr($usuario, 0, 100);
        $acao = substr($acao, 0, 50);
        $tabela_afetada = substr($tabela_afetada, 0, 50);
        $ip_usuario = substr($ip_usuario, 0, 45);
        $numero_agendamento = substr($numero_agendamento, 0, 50);
        $campos_alterados = substr($campos_alterados, 0, 500);
        $observacoes = substr($observacoes, 0, 1000);
        $paciente_nome = substr($paciente_nome, 0, 200);
        $status_anterior = substr($status_anterior, 0, 50);
        $status_novo = substr($status_novo, 0, 50);
        
        // Preparar query de inserção
        $query = "INSERT INTO AGENDA_AUDITORIA (
                    AGENDAMENTO_ID, NUMERO_AGENDAMENTO, ACAO, TABELA_AFETADA, 
                    USUARIO, IP_USUARIO, DADOS_ANTIGOS, DADOS_NOVOS, 
                    CAMPOS_ALTERADOS, OBSERVACOES, AGENDA_ID, PACIENTE_NOME,
                    DATA_AGENDAMENTO, HORA_AGENDAMENTO, STATUS_ANTERIOR, STATUS_NOVO
                  ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = ibase_prepare($conn, $query);
        $result = ibase_execute($stmt, 
            $agendamento_id,
            $numero_agendamento,
            $acao,
            $tabela_afetada,
            $usuario,
            $ip_usuario,
            $dados_antigos,
            $dados_novos,
            $campos_alterados,
            $observacoes,
            $agenda_id,
            $paciente_nome,
            $data_agendamento,
            $hora_agendamento,
            $status_anterior,
            $status_novo
        );
        
        if ($result) {
            error_log("AUDITORIA SUCCESS: {$acao} registrada para usuário {$usuario}");
            return true;
        } else {
            $error = ibase_errmsg();
            error_log("AUDITORIA ERROR: Falha ao registrar - {$error}");
            return false;
        }
        
    } catch (Exception $e) {
        error_log("AUDITORIA EXCEPTION: " . $e->getMessage());
        return false;
    }
}

/**
 * Obtém informações completas do usuário e ambiente
 */
function obterInformacoesCompletas() {
    $info = [
        'ip' => obterIPUsuario(),
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'DESCONHECIDO',
        'navegador' => obterNomeNavegador(),
        'sistema_operacional' => obterSistemaOperacional(),
        'sessao_id' => session_id() ?: (isset($_COOKIE['PHPSESSID']) ? $_COOKIE['PHPSESSID'] : 'N/A'),
        'url_origem' => $_SERVER['HTTP_REFERER'] ?? ($_SERVER['REQUEST_URI'] ?? 'N/A'),
        'metodo_http' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
        'dados_post' => $_POST ? json_encode($_POST, JSON_UNESCAPED_UNICODE) : null,
        'dados_get' => $_GET ? json_encode($_GET, JSON_UNESCAPED_UNICODE) : null,
        'dados_sessao' => (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION)) ? json_encode($_SESSION, JSON_UNESCAPED_UNICODE) : null,
        'transacao_id' => uniqid('TXN_', true),
        'timestamp_inicio' => microtime(true)
    ];
    
    return $info;
}

/**
 * Obtém o IP real do usuário
 */
function obterIPUsuario() {
    $headers = [
        'HTTP_CLIENT_IP',
        'HTTP_X_FORWARDED_FOR', 
        'HTTP_X_FORWARDED',
        'HTTP_FORWARDED_FOR',
        'HTTP_FORWARDED',
        'REMOTE_ADDR'
    ];
    
    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ips = explode(',', $_SERVER[$header]);
            $ip = trim($ips[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
            return $ip;
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? 'DESCONHECIDO';
}

/**
 * Detecta o nome do navegador
 */
function obterNomeNavegador() {
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    if (strpos($user_agent, 'Chrome') !== false) return 'Chrome';
    if (strpos($user_agent, 'Firefox') !== false) return 'Firefox';
    if (strpos($user_agent, 'Safari') !== false) return 'Safari';
    if (strpos($user_agent, 'Edge') !== false) return 'Edge';
    if (strpos($user_agent, 'Opera') !== false) return 'Opera';
    if (strpos($user_agent, 'MSIE') !== false) return 'Internet Explorer';
    
    return 'Desconhecido';
}

/**
 * Detecta o sistema operacional
 */
function obterSistemaOperacional() {
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    if (strpos($user_agent, 'Windows NT 10') !== false) return 'Windows 10';
    if (strpos($user_agent, 'Windows NT 6.3') !== false) return 'Windows 8.1';
    if (strpos($user_agent, 'Windows NT 6.2') !== false) return 'Windows 8';
    if (strpos($user_agent, 'Windows NT 6.1') !== false) return 'Windows 7';
    if (strpos($user_agent, 'Windows') !== false) return 'Windows';
    if (strpos($user_agent, 'Mac OS X') !== false) return 'macOS';
    if (strpos($user_agent, 'Linux') !== false) return 'Linux';
    if (strpos($user_agent, 'Android') !== false) return 'Android';
    if (strpos($user_agent, 'iOS') !== false) return 'iOS';
    
    return 'Desconhecido';
}

/**
 * Busca dados completos do agendamento no banco
 */
function buscarDadosCompletosAgendamento($conn, $agendamento_id) {
    try {
        $query = "SELECT 
                    a.*, 
                    COALESCE(p.PACIENTE, a.NOME_PACIENTE) as NOME_COMPLETO_PACIENTE,
                    p.CPF, p.FONE1, p.EMAIL, p.ANIVERSARIO,
                    c.NOME as NOME_CONVENIO,
                    ag.TIPO_AGENDA as NOME_AGENDA,
                    m.NOME as NOME_MEDICO
                  FROM AGENDAMENTOS a
                  LEFT JOIN LAB_PACIENTES p ON a.PACIENTE_ID = p.IDPACIENTE
                  LEFT JOIN CONVENIOS c ON a.CONVENIO_ID = c.ID
                  LEFT JOIN AGENDAS ag ON a.AGENDA_ID = ag.ID
                  LEFT JOIN LAB_MEDICOS_PRES m ON ag.MEDICO_ID = m.ID
                  WHERE a.ID = ?";
        
        $stmt = ibase_prepare($conn, $query);
        $result = ibase_execute($stmt, $agendamento_id);
        
        if ($result && $row = ibase_fetch_assoc($result)) {
            // Buscar exames do agendamento
            $exames = [];
            if ($row['NUMERO_AGENDAMENTO']) {
                $query_exames = "SELECT ae.EXAME_ID, le.EXAME as NOME_EXAME
                                FROM AGENDAMENTO_EXAMES ae
                                LEFT JOIN LAB_EXAMES le ON le.IDEXAME = ae.EXAME_ID
                                WHERE ae.NUMERO_AGENDAMENTO = ?";
                
                $stmt_exames = ibase_prepare($conn, $query_exames);
                $result_exames = ibase_execute($stmt_exames, $row['NUMERO_AGENDAMENTO']);
                
                while ($exame = ibase_fetch_assoc($result_exames)) {
                    if ($exame['NOME_EXAME']) {
                        $exames[] = [
                            'id' => $exame['EXAME_ID'],
                            'nome' => utf8_encode(trim($exame['NOME_EXAME']))
                        ];
                    }
                }
            }
            
            // Preparar dados completos
            $dados = [
                'id' => $row['ID'],
                'numero_agendamento' => $row['NUMERO_AGENDAMENTO'],
                'paciente_id' => $row['PACIENTE_ID'],
                'nome_paciente' => utf8_encode($row['NOME_COMPLETO_PACIENTE'] ?? ''),
                'cpf' => $row['CPF'] ?? '',
                'telefone' => $row['FONE1'] ?? $row['TELEFONE_PACIENTE'] ?? '',
                'email' => $row['EMAIL'] ?? '',
                'data_nascimento' => $row['ANIVERSARIO'] ?? $row['DATA_NASCIMENTO'] ?? '',
                'convenio_id' => $row['CONVENIO_ID'],
                'nome_convenio' => utf8_encode($row['NOME_CONVENIO'] ?? ''),
                'agenda_id' => $row['AGENDA_ID'],
                'nome_agenda' => utf8_encode($row['NOME_AGENDA'] ?? ''),
                'nome_medico' => utf8_encode($row['NOME_MEDICO'] ?? ''),
                'data_agendamento' => $row['DATA_AGENDAMENTO'],
                'hora_agendamento' => $row['HORA_AGENDAMENTO'],
                'status' => $row['STATUS'],
                'tipo_consulta' => $row['TIPO_CONSULTA'],
                'tipo_agendamento' => $row['TIPO_AGENDAMENTO'],
                'observacoes' => $row['OBSERVACOES'] ? (is_resource($row['OBSERVACOES']) ? stream_get_contents($row['OBSERVACOES']) : utf8_encode($row['OBSERVACOES'])) : '',
                'exames' => $exames,
                'total_exames' => count($exames)
            ];
            
            return $dados;
        }
        
        return null;
        
    } catch (Exception $e) {
        error_log("ERRO ao buscar dados completos: " . $e->getMessage());
        return null;
    }
}

/**
 * Compara dados detalhadamente e extrai diferenças específicas
 */
function compararDadosDetalhados($dados_anteriores, $dados_novos) {
    $comparacao = [
        'campos_alterados' => [],
        'status_anterior' => null,
        'status_novo' => null,
        'tipo_consulta_anterior' => null,
        'tipo_consulta_novo' => null,
        'observacoes_anteriores' => null,
        'observacoes_novas' => null,
        'email_anterior' => null,
        'email_novo' => null,
        'exames_anteriores' => null,
        'exames_novos' => null
    ];
    
    if (!$dados_anteriores || !$dados_novos) {
        return $comparacao;
    }
    
    // Comparar campos específicos (APENAS campos editáveis)
    $campos_comparar = [
        'status' => ['status_anterior', 'status_novo'],
        'email' => ['email_anterior', 'email_novo'],
        'data_nascimento' => ['data_nasc_anterior', 'data_nasc_nova'],
        'tipo_consulta' => ['tipo_consulta_anterior', 'tipo_consulta_novo'],
        'observacoes' => ['observacoes_anteriores', 'observacoes_novas']
    ];
    
    foreach ($campos_comparar as $campo => $mapeamento) {
        $valor_anterior = $dados_anteriores[$campo] ?? null;
        $valor_novo = $dados_novos[$campo] ?? null;
        
        if ($valor_anterior !== $valor_novo) {
            $comparacao['campos_alterados'][] = $campo;
            if (isset($mapeamento[0])) $comparacao[$mapeamento[0]] = $valor_anterior;
            if (isset($mapeamento[1])) $comparacao[$mapeamento[1]] = $valor_novo;
        }
    }
    
    // Comparar exames especificamente
    $exames_anteriores = $dados_anteriores['exames'] ?? [];
    $exames_novos = $dados_novos['exames'] ?? [];
    
    if ($exames_anteriores !== $exames_novos) {
        $comparacao['campos_alterados'][] = 'exames';
        $comparacao['exames_anteriores'] = $exames_anteriores;
        $comparacao['exames_novos'] = $exames_novos;
    }
    
    return $comparacao;
}

/**
 * Registra auditoria expandida com todos os novos campos
 */
function registrarAuditoriaExpandida($conn, $params) {
    try {
        // Preparar query com todos os campos novos
        $query = "INSERT INTO AGENDA_AUDITORIA (
                    AGENDAMENTO_ID, NUMERO_AGENDAMENTO, ACAO, TABELA_AFETADA, 
                    USUARIO, IP_USUARIO, USER_AGENT, NAVEGADOR, SISTEMA_OPERACIONAL,
                    SESSAO_ID, URL_ORIGEM, METODO_HTTP, DADOS_POST, DADOS_GET, 
                    DADOS_SESSAO, TRANSACAO_ID, DADOS_ANTIGOS, DADOS_NOVOS, 
                    CAMPOS_ALTERADOS, OBSERVACOES, AGENDA_ID, PACIENTE_NOME,
                    DATA_AGENDAMENTO, HORA_AGENDAMENTO, STATUS_ANTERIOR, STATUS_NOVO,
                    TIPO_CONSULTA_ANTERIOR, TIPO_CONSULTA_NOVO,
                    OBSERVACOES_ANTERIORES, OBSERVACOES_NOVAS,
                    CONVENIO_ANTERIOR, CONVENIO_NOVO, TELEFONE_ANTERIOR, TELEFONE_NOVO,
                    CPF_ANTERIOR, CPF_NOVO, EMAIL_ANTERIOR, EMAIL_NOVO,
                    EXAMES_ANTERIORES, EXAMES_NOVOS, RESULTADO_ACAO, DURACAO_TOTAL_MS
                  ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = ibase_prepare($conn, $query);
        $result = ibase_execute($stmt, 
            $params['agendamento_id'] ?? null,
            $params['numero_agendamento'] ?? null,
            $params['acao'] ?? '',
            $params['tabela_afetada'] ?? 'AGENDAMENTOS',
            $params['usuario'] ?? '',
            $params['ip_usuario'] ?? '',
            $params['user_agent'] ?? '',
            $params['navegador'] ?? '',
            $params['sistema_operacional'] ?? '',
            $params['sessao_id'] ?? '',
            $params['url_origem'] ?? '',
            $params['metodo_http'] ?? '',
            $params['dados_post'] ?? null,
            $params['dados_get'] ?? null,
            $params['dados_sessao'] ?? null,
            $params['transacao_id'] ?? '',
            $params['dados_antigos'] ?? null,
            $params['dados_novos'] ?? null,
            $params['campos_alterados'] ?? '',
            $params['observacoes'] ?? '',
            $params['agenda_id'] ?? null,
            $params['paciente_nome'] ?? '',
            $params['data_agendamento'] ?? null,
            $params['hora_agendamento'] ?? null,
            $params['status_anterior'] ?? null,
            $params['status_novo'] ?? null,
            $params['tipo_consulta_anterior'] ?? null,
            $params['tipo_consulta_novo'] ?? null,
            $params['observacoes_anteriores'] ?? null,
            $params['observacoes_novas'] ?? null,
            $params['convenio_anterior'] ?? null,
            $params['convenio_novo'] ?? null,
            $params['telefone_anterior'] ?? null,
            $params['telefone_novo'] ?? null,
            $params['cpf_anterior'] ?? null,
            $params['cpf_novo'] ?? null,
            $params['email_anterior'] ?? null,
            $params['email_novo'] ?? null,
            $params['exames_anteriores'] ?? null,
            $params['exames_novos'] ?? null,
            $params['resultado_acao'] ?? 'SUCCESS',
            $params['duracao_total_ms'] ?? 0
        );
        
        if ($result) {
            error_log("AUDITORIA EXPANDIDA: {$params['acao']} registrada para {$params['usuario']}");
            return true;
        } else {
            error_log("ERRO AUDITORIA EXPANDIDA: " . ibase_errmsg());
            return false;
        }
        
    } catch (Exception $e) {
        error_log("EXCEÇÃO AUDITORIA EXPANDIDA: " . $e->getMessage());
        return false;
    }
}

/**
 * Compara dois arrays e retorna os campos que foram alterados
 * FILTRA apenas campos editáveis
 * 
 * @param array $dados_antigos Dados antes da alteração
 * @param array $dados_novos Dados após a alteração
 * @return array Lista de campos alterados
 */
function identificarCamposAlterados($dados_antigos, $dados_novos) {
    $campos_alterados = [];
    
    // APENAS campos editáveis podem aparecer na auditoria
    $campos_editaveis = ['email', 'status', 'tipo_consulta', 'observacoes', 'data_nascimento'];
    
    // Verificar campos novos ou alterados (apenas editáveis)
    foreach ($dados_novos as $campo => $valor_novo) {
        if (in_array($campo, $campos_editaveis)) {
            if (!isset($dados_antigos[$campo]) || $dados_antigos[$campo] !== $valor_novo) {
                $campos_alterados[] = $campo;
            }
        }
    }
    
    // Verificar campos removidos (apenas editáveis)
    foreach ($dados_antigos as $campo => $valor_antigo) {
        if (in_array($campo, $campos_editaveis)) {
            if (!isset($dados_novos[$campo])) {
                $campos_alterados[] = $campo . '_REMOVIDO';
            }
        }
    }
    
    return array_unique($campos_alterados);
}

/**
 * Registra auditoria COMPLETA para agendamentos com TODAS as informações possíveis
 * 
 * @param resource $conn Conexão com banco
 * @param string $acao Ação executada
 * @param string $usuario Usuário que executou
 * @param array $dados_agendamento Dados do agendamento
 * @param array $dados_antigos Dados antes da alteração (opcional)
 * @param string $observacoes Observações adicionais
 * @param array $info_extras Informações extras (opcional)
 */
function auditarAgendamentoCompleto($conn, $acao, $usuario, $dados_agendamento, $dados_antigos = null, $observacoes = '', $info_extras = []) {
    $timestamp_inicio = microtime(true);
    
    // Obter informações completas do ambiente
    $info_ambiente = obterInformacoesCompletas();
    
    // Buscar dados completos do agendamento se temos o ID
    $agendamento_id = $dados_agendamento['id'] ?? $dados_agendamento['agendamento_id'] ?? null;
    $dados_completos_atuais = null;
    $dados_completos_anteriores = null;
    
    if ($agendamento_id && $conn) {
        $dados_completos_atuais = buscarDadosCompletosAgendamento($conn, $agendamento_id);
        if ($dados_antigos && is_array($dados_antigos)) {
            $dados_completos_anteriores = $dados_antigos;
        }
    }
    
    // Extrair informações detalhadas
    $paciente_nome = $dados_agendamento['paciente'] ?? $dados_agendamento['nome_paciente'] ?? null;
    $agenda_id = $dados_agendamento['agenda_id'] ?? null;
    $data_agendamento = $dados_agendamento['data_agendamento'] ?? $dados_agendamento['data'] ?? null;
    $hora_agendamento = $dados_agendamento['hora_agendamento'] ?? $dados_agendamento['hora'] ?? null;
    
    // Comparar dados detalhadamente
    // Usar dados diretos em vez de busca no banco para comparação mais precisa
    $comparacao = compararDadosDetalhados($dados_antigos, $dados_agendamento);
    
    // Preparar todos os parâmetros para auditoria expandida
    $params = [
        'acao' => $acao,
        'usuario' => $usuario,
        'tabela_afetada' => 'AGENDAMENTOS',
        'agendamento_id' => $agendamento_id,
        'numero_agendamento' => $dados_agendamento['numero'] ?? $dados_agendamento['numero_agendamento'] ?? null,
        'ip_usuario' => $info_ambiente['ip'],
        'user_agent' => substr($info_ambiente['user_agent'], 0, 500),
        'navegador' => $info_ambiente['navegador'],
        'sistema_operacional' => $info_ambiente['sistema_operacional'],
        'sessao_id' => $info_ambiente['sessao_id'],
        'url_origem' => substr($info_ambiente['url_origem'], 0, 500),
        'metodo_http' => $info_ambiente['metodo_http'],
        'dados_post' => $info_ambiente['dados_post'],
        'dados_get' => $info_ambiente['dados_get'],
        'dados_sessao' => $info_ambiente['dados_sessao'],
        'transacao_id' => $info_ambiente['transacao_id'],
        'dados_antigos' => $dados_completos_anteriores ? json_encode($dados_completos_anteriores, JSON_UNESCAPED_UNICODE) : null,
        'dados_novos' => $dados_completos_atuais ? json_encode($dados_completos_atuais, JSON_UNESCAPED_UNICODE) : json_encode($dados_agendamento, JSON_UNESCAPED_UNICODE),
        'campos_alterados' => implode(', ', $comparacao['campos_alterados']),
        'observacoes' => $observacoes,
        'agenda_id' => $agenda_id,
        'paciente_nome' => $paciente_nome,
        'data_agendamento' => $data_agendamento,
        'hora_agendamento' => $hora_agendamento,
        'status_anterior' => $comparacao['status_anterior'],
        'status_novo' => $comparacao['status_novo'],
        'tipo_consulta_anterior' => $comparacao['tipo_consulta_anterior'],
        'tipo_consulta_novo' => $comparacao['tipo_consulta_novo'],
        'observacoes_anteriores' => $comparacao['observacoes_anteriores'],
        'observacoes_novas' => $comparacao['observacoes_novas'],
        'convenio_anterior' => $comparacao['convenio_anterior'],
        'convenio_novo' => $comparacao['convenio_novo'],
        'telefone_anterior' => $comparacao['telefone_anterior'],
        'telefone_novo' => $comparacao['telefone_novo'],
        'cpf_anterior' => $comparacao['cpf_anterior'],
        'cpf_novo' => $comparacao['cpf_novo'],
        'email_anterior' => $comparacao['email_anterior'],
        'email_novo' => $comparacao['email_novo'],
        'exames_anteriores' => $comparacao['exames_anteriores'] ? json_encode($comparacao['exames_anteriores'], JSON_UNESCAPED_UNICODE) : null,
        'exames_novos' => $comparacao['exames_novos'] ? json_encode($comparacao['exames_novos'], JSON_UNESCAPED_UNICODE) : null,
        'resultado_acao' => 'SUCCESS',
        'duracao_total_ms' => round((microtime(true) - $timestamp_inicio) * 1000)
    ];
    
    // Adicionar informações extras se fornecidas
    if (!empty($info_extras)) {
        $params['observacoes'] .= ' | EXTRAS: ' . json_encode($info_extras, JSON_UNESCAPED_UNICODE);
    }
    
    return registrarAuditoriaExpandida($conn, $params);
}

/**
 * Versão compatível com código existente
 */
function auditarAgendamento($conn, $acao, $usuario, $dados_agendamento, $dados_antigos = null, $observacoes = '') {
    return auditarAgendamentoCompleto($conn, $acao, $usuario, $dados_agendamento, $dados_antigos, $observacoes);
}

/**
 * Registra auditoria para ações de bloqueio/desbloqueio
 */
function auditarBloqueio($conn, $acao, $usuario, $agenda_id, $data, $horario, $observacoes = '') {
    $params = [
        'acao' => $acao,
        'usuario' => $usuario,
        'agenda_id' => $agenda_id,
        'data_agendamento' => $data,
        'hora_agendamento' => $horario,
        'observacoes' => $observacoes,
        'status_novo' => ($acao === 'BLOQUEAR') ? 'BLOQUEADO' : 'LIBERADO'
    ];
    
    return registrarAuditoria($conn, $params);
}

/**
 * Busca histórico de auditoria com filtros
 * 
 * @param resource $conn Conexão com banco
 * @param array $filtros Filtros para busca
 * @return array Lista de registros de auditoria
 */
function buscarHistoricoAuditoria($conn, $filtros = []) {
    try {
        $where_conditions = [];
        $params = [];
        
        // Construir condições WHERE baseadas nos filtros
        if (!empty($filtros['agendamento_id'])) {
            $where_conditions[] = "AGENDAMENTO_ID = ?";
            $params[] = $filtros['agendamento_id'];
        }
        
        if (!empty($filtros['usuario'])) {
            $where_conditions[] = "USUARIO = ?";
            $params[] = $filtros['usuario'];
        }
        
        if (!empty($filtros['acao'])) {
            $where_conditions[] = "ACAO = ?";
            $params[] = $filtros['acao'];
        }
        
        if (!empty($filtros['agenda_id'])) {
            $where_conditions[] = "AGENDA_ID = ?";
            $params[] = $filtros['agenda_id'];
        }
        
        if (!empty($filtros['data_inicio'])) {
            $where_conditions[] = "DATA_ACAO >= ?";
            $params[] = $filtros['data_inicio'] . ' 00:00:00';
        }
        
        if (!empty($filtros['data_fim'])) {
            $where_conditions[] = "DATA_ACAO <= ?";
            $params[] = $filtros['data_fim'] . ' 23:59:59';
        }
        
        if (!empty($filtros['paciente_nome'])) {
            $where_conditions[] = "PACIENTE_NOME CONTAINING ?";
            $params[] = $filtros['paciente_nome'];
        }
        
        $where_clause = empty($where_conditions) ? '' : 'WHERE ' . implode(' AND ', $where_conditions);
        
        $limit = (int)($filtros['limit'] ?? 100);
        $offset = (int)($filtros['offset'] ?? 0);
        
        $query = "SELECT 
                    FIRST {$limit} SKIP {$offset}
                    ID, AGENDAMENTO_ID, NUMERO_AGENDAMENTO, ACAO, TABELA_AFETADA,
                    USUARIO, DATA_ACAO, IP_USUARIO, DADOS_ANTIGOS, DADOS_NOVOS,
                    CAMPOS_ALTERADOS, OBSERVACOES, AGENDA_ID, PACIENTE_NOME,
                    DATA_AGENDAMENTO, HORA_AGENDAMENTO, STATUS_ANTERIOR, STATUS_NOVO,
                    TIPO_CONSULTA_ANTERIOR, TIPO_CONSULTA_NOVO,
                    OBSERVACOES_ANTERIORES, OBSERVACOES_NOVAS
                  FROM AGENDA_AUDITORIA 
                  {$where_clause}
                  ORDER BY DATA_ACAO DESC";
        
        $stmt = ibase_prepare($conn, $query);
        $result = ibase_execute($stmt, ...$params);
        
        $historico = [];
        while ($row = ibase_fetch_assoc($result)) {
            // Processar campos BLOB
            if ($row['DADOS_ANTIGOS']) {
                $row['DADOS_ANTIGOS'] = utf8_encode($row['DADOS_ANTIGOS']);
            }
            if ($row['DADOS_NOVOS']) {
                $row['DADOS_NOVOS'] = utf8_encode($row['DADOS_NOVOS']);
            }
            
            // Converter strings para UTF-8
            foreach (['ACAO', 'USUARIO', 'OBSERVACOES', 'PACIENTE_NOME', 'CAMPOS_ALTERADOS'] as $field) {
                if ($row[$field]) {
                    $row[$field] = utf8_encode($row[$field]);
                }
            }
            
            $historico[] = $row;
        }
        
        return $historico;
        
    } catch (Exception $e) {
        error_log("AUDITORIA BUSCA ERROR: " . $e->getMessage());
        return [];
    }
}
?>