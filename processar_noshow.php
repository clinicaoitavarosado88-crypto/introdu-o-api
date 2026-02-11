<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

include 'includes/connection.php';
include 'includes/auth_middleware.php';

try {
    // Verificar autenticação
    $auth_result = verify_api_token();
    if (!$auth_result['valid']) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized', 'message' => $auth_result['message']]);
        exit;
    }

    // Lê os dados do POST
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }

    $agendamento_id = $input['agendamento_id'] ?? '';
    $usuario = $input['usuario'] ?? 'API_AGENT';
    $observacao = trim($input['observacao'] ?? '');
    $enviar_notificacao = $input['enviar_notificacao'] ?? true;

    if (empty($agendamento_id)) {
        http_response_code(400);
        echo json_encode(['error' => 'Bad Request', 'message' => 'agendamento_id é obrigatório']);
        exit;
    }

    // Buscar dados do agendamento
    $sql_agendamento = "
        SELECT
            ag.ID,
            ag.NUMERO,
            ag.PACIENTE_ID,
            ag.AGENDA_ID,
            ag.DATA_AGENDAMENTO,
            ag.HORA_AGENDAMENTO,
            ag.STATUS,
            ag.CONVENIO_ID,
            p.NOME as PACIENTE_NOME,
            p.CPF as PACIENTE_CPF,
            p.TELEFONE as PACIENTE_TELEFONE,
            p.EMAIL as PACIENTE_EMAIL,
            a.SALA,
            a.TELEFONE as AGENDA_TELEFONE,
            u.NOME_UNIDADE,
            m.NOME as MEDICO_NOME,
            e.NOME as ESPECIALIDADE_NOME,
            ge.NOME as PROCEDIMENTO_NOME,
            c.NOME_CONVENIO
        FROM AGENDAMENTOS ag
        LEFT JOIN LAB_PACIENTES p ON p.ID = ag.PACIENTE_ID
        LEFT JOIN AGENDAS a ON a.ID = ag.AGENDA_ID
        LEFT JOIN LAB_CIDADES u ON u.ID = a.UNIDADE_ID
        LEFT JOIN LAB_MEDICOS_PRES m ON m.ID = a.MEDICO_ID
        LEFT JOIN LAB_MEDICOS_ESPECIALIDADES me ON me.MEDICO_ID = m.ID
        LEFT JOIN ESPECIALIDADES e ON e.ID = me.ESPECIALIDADE_ID
        LEFT JOIN GRUPO_EXAMES ge ON ge.ID = a.PROCEDIMENTO_ID
        LEFT JOIN LAB_CONVENIOS c ON c.ID = ag.CONVENIO_ID
        WHERE ag.ID = ?
    ";

    $stmt = ibase_prepare($conn, $sql_agendamento);
    $result = ibase_execute($stmt, $agendamento_id);
    $agendamento = ibase_fetch_assoc($result);

    if (!$agendamento) {
        http_response_code(404);
        echo json_encode(['error' => 'Not Found', 'message' => 'Agendamento não encontrado']);
        exit;
    }

    // Verificar se pode marcar como faltou
    if (!in_array($agendamento['STATUS'], ['AGENDADO', 'CONFIRMADO', 'CHEGOU'])) {
        http_response_code(400);
        echo json_encode([
            'error' => 'Bad Request',
            'message' => 'Agendamento não pode ser marcado como faltou. Status atual: ' . $agendamento['STATUS']
        ]);
        exit;
    }

    // Atualizar status para FALTOU
    $data_noshow = date('Y-m-d H:i:s');
    $observacao_completa = !empty($observacao) ? $observacao : 'Paciente não compareceu';

    $sql_update = "
        UPDATE AGENDAMENTOS SET
            STATUS = 'FALTOU',
            DATA_NOSHOW = ?,
            OBSERVACOES_NOSHOW = ?,
            USUARIO_NOSHOW = ?
        WHERE ID = ?
    ";

    $stmt_update = ibase_prepare($conn, $sql_update);
    $success = ibase_execute($stmt_update, $data_noshow, $observacao_completa, $usuario, $agendamento_id);

    if (!$success) {
        throw new Exception('Erro ao atualizar status do agendamento');
    }

    // Registrar auditoria
    $auditoria_data = [
        'acao' => 'NOSHOW',
        'tabela' => 'AGENDAMENTOS',
        'registro_id' => $agendamento_id,
        'dados_anteriores' => json_encode(['status' => $agendamento['STATUS']]),
        'dados_novos' => json_encode([
            'status' => 'FALTOU',
            'data_noshow' => $data_noshow,
            'observacao' => $observacao_completa
        ]),
        'usuario' => $usuario,
        'data_acao' => $data_noshow
    ];

    try {
        include_once 'includes/auditoria_helper.php';
        registrar_auditoria($conn, $auditoria_data);
    } catch (Exception $e) {
        error_log("Erro ao registrar auditoria de no-show: " . $e->getMessage());
    }

    // Notificações automáticas
    $notificacoes_enviadas = [];

    if ($enviar_notificacao) {
        // 1. Notificar equipe via WhatsApp (se configurado)
        try {
            include_once 'whatsapp_api.php';

            $mensagem_equipe = "🚨 *NO-SHOW REGISTRADO*\n\n";
            $mensagem_equipe .= "📋 *Agendamento:* #{$agendamento['NUMERO']}\n";
            $mensagem_equipe .= "👤 *Paciente:* " . mb_convert_encoding($agendamento['PACIENTE_NOME'], 'UTF-8', 'Windows-1252') . "\n";
            $mensagem_equipe .= "📅 *Data/Hora:* {$agendamento['DATA_AGENDAMENTO']} às {$agendamento['HORA_AGENDAMENTO']}\n";
            $mensagem_equipe .= "🏥 *Unidade:* " . mb_convert_encoding($agendamento['NOME_UNIDADE'], 'UTF-8', 'Windows-1252') . "\n";

            if ($agendamento['MEDICO_NOME']) {
                $mensagem_equipe .= "👨‍⚕️ *Médico:* " . mb_convert_encoding($agendamento['MEDICO_NOME'], 'UTF-8', 'Windows-1252') . "\n";
                $mensagem_equipe .= "🩺 *Especialidade:* " . mb_convert_encoding($agendamento['ESPECIALIDADE_NOME'], 'UTF-8', 'Windows-1252') . "\n";
            } else {
                $mensagem_equipe .= "🔬 *Procedimento:* " . mb_convert_encoding($agendamento['PROCEDIMENTO_NOME'], 'UTF-8', 'Windows-1252') . "\n";
            }

            $mensagem_equipe .= "💳 *Convênio:* " . mb_convert_encoding($agendamento['NOME_CONVENIO'], 'UTF-8', 'Windows-1252') . "\n";
            $mensagem_equipe .= "📝 *Observação:* $observacao_completa\n";
            $mensagem_equipe .= "⏰ *Registrado em:* " . date('d/m/Y H:i:s');

            // Enviar para números configurados da equipe
            $sql_equipe = "
                SELECT TELEFONE, NOME
                FROM NOTIFICACOES_EQUIPE
                WHERE ATIVO = 1
                AND RECEBER_NOSHOW = 1
            ";

            $result_equipe = ibase_query($conn, $sql_equipe);
            while ($equipe_row = ibase_fetch_assoc($result_equipe)) {
                $telefone_equipe = preg_replace('/[^0-9]/', '', $equipe_row['TELEFONE']);
                $nome_membro = mb_convert_encoding($equipe_row['NOME'], 'UTF-8', 'Windows-1252');

                $resultado_envio = enviar_whatsapp($telefone_equipe, $mensagem_equipe);
                if ($resultado_envio['sucesso']) {
                    $notificacoes_enviadas[] = [
                        'tipo' => 'whatsapp_equipe',
                        'destinatario' => $nome_membro,
                        'telefone' => $telefone_equipe,
                        'status' => 'enviado'
                    ];
                }
            }

        } catch (Exception $e) {
            error_log("Erro ao enviar notificação WhatsApp equipe: " . $e->getMessage());
        }

        // 2. Enviar email para equipe (se configurado)
        try {
            $sql_emails_equipe = "
                SELECT EMAIL, NOME
                FROM NOTIFICACOES_EQUIPE
                WHERE ATIVO = 1
                AND RECEBER_NOSHOW = 1
                AND EMAIL IS NOT NULL
                AND EMAIL != ''
            ";

            $result_emails = ibase_query($conn, $sql_emails_equipe);
            while ($email_row = ibase_fetch_assoc($result_emails)) {
                $email_destino = trim($email_row['EMAIL']);
                $nome_destino = mb_convert_encoding($email_row['NOME'], 'UTF-8', 'Windows-1252');

                $assunto = "No-Show Registrado - Agendamento #{$agendamento['NUMERO']}";

                $corpo_email = "
                <h2>No-Show Registrado</h2>
                <p><strong>Agendamento:</strong> #{$agendamento['NUMERO']}</p>
                <p><strong>Paciente:</strong> " . mb_convert_encoding($agendamento['PACIENTE_NOME'], 'UTF-8', 'Windows-1252') . "</p>
                <p><strong>CPF:</strong> {$agendamento['PACIENTE_CPF']}</p>
                <p><strong>Telefone:</strong> {$agendamento['PACIENTE_TELEFONE']}</p>
                <p><strong>Data/Hora:</strong> {$agendamento['DATA_AGENDAMENTO']} às {$agendamento['HORA_AGENDAMENTO']}</p>
                <p><strong>Unidade:</strong> " . mb_convert_encoding($agendamento['NOME_UNIDADE'], 'UTF-8', 'Windows-1252') . "</p>
                <p><strong>Observação:</strong> $observacao_completa</p>
                <p><strong>Registrado por:</strong> $usuario em " . date('d/m/Y H:i:s') . "</p>
                ";

                $headers = "MIME-Version: 1.0\r\n";
                $headers .= "Content-type: text/html; charset=UTF-8\r\n";
                $headers .= "From: Sistema Agenda <sistema@clinicaoitavarosado.com.br>\r\n";

                if (mail($email_destino, $assunto, $corpo_email, $headers)) {
                    $notificacoes_enviadas[] = [
                        'tipo' => 'email_equipe',
                        'destinatario' => $nome_destino,
                        'email' => $email_destino,
                        'status' => 'enviado'
                    ];
                }
            }

        } catch (Exception $e) {
            error_log("Erro ao enviar email para equipe: " . $e->getMessage());
        }

        // 3. Registrar histórico de no-show do paciente
        try {
            $sql_historico = "
                INSERT INTO HISTORICO_NOSHOW (
                    PACIENTE_ID,
                    AGENDAMENTO_ID,
                    DATA_AGENDAMENTO,
                    DATA_NOSHOW,
                    OBSERVACAO,
                    USUARIO,
                    DATA_REGISTRO
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ";

            $stmt_historico = ibase_prepare($conn, $sql_historico);
            ibase_execute($stmt_historico,
                $agendamento['PACIENTE_ID'],
                $agendamento_id,
                $agendamento['DATA_AGENDAMENTO'],
                $data_noshow,
                $observacao_completa,
                $usuario,
                $data_noshow
            );

        } catch (Exception $e) {
            error_log("Erro ao registrar histórico de no-show: " . $e->getMessage());
        }
    }

    // Resposta de sucesso
    $response = [
        'status' => 'sucesso',
        'message' => 'No-show registrado com sucesso',
        'agendamento' => [
            'id' => (int)$agendamento_id,
            'numero' => (int)$agendamento['NUMERO'],
            'paciente' => mb_convert_encoding($agendamento['PACIENTE_NOME'], 'UTF-8', 'Windows-1252'),
            'data_agendamento' => $agendamento['DATA_AGENDAMENTO'],
            'hora_agendamento' => $agendamento['HORA_AGENDAMENTO'],
            'status_anterior' => $agendamento['STATUS'],
            'status_atual' => 'FALTOU'
        ],
        'noshow' => [
            'data_registro' => $data_noshow,
            'observacao' => $observacao_completa,
            'usuario_registro' => $usuario
        ],
        'notificacoes' => [
            'enviadas' => $notificacoes_enviadas,
            'total_enviadas' => count($notificacoes_enviadas)
        ]
    ];

    http_response_code(200);
    echo json_encode($response, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("Erro ao processar no-show: " . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'error' => 'Internal Server Error',
        'message' => 'Erro ao processar no-show'
    ]);
}
?>