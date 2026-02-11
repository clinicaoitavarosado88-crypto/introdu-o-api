<?php
header('Content-Type: application/json; charset=UTF-8');
require_once 'includes/connection.php';

// Suporte para linha de comando
if (php_sapi_name() === 'cli') {
    parse_str(getenv('QUERY_STRING') ?: '', $_GET);
}

try {
    $agenda_id = isset($_GET['agenda_id']) ? (int)$_GET['agenda_id'] : (isset($_POST['agenda_id']) ? (int)$_POST['agenda_id'] : 0);
    
    if (!$agenda_id) {
        throw new Exception('ID da agenda não fornecido');
    }
    
    // Buscar informações da agenda e verificar se é do tipo procedimento
    $sql = "SELECT a.TIPO, a.PROCEDIMENTO_ID 
            FROM AGENDAS a 
            WHERE a.ID = ?";
    
    $stmt = ibase_prepare($conn, $sql);
    $result = ibase_execute($stmt, $agenda_id);
    $agenda = ibase_fetch_assoc($result);
    ibase_free_result($result);
    
    if (!$agenda) {
        throw new Exception('Agenda não encontrada');
    }
    
    $tipo = trim($agenda['TIPO']);
    $procedimento_id = $agenda['PROCEDIMENTO_ID'];
    
    // Se não for procedimento, retornar vazio
    if (strtoupper($tipo) !== 'PROCEDIMENTO' || !$procedimento_id) {
        echo json_encode(array(
            'status' => 'sucesso',
            'tipo_agenda' => $tipo,
            'exames' => array()
        ));
        exit;
    }
    
    // Buscar exames seguindo o relacionamento correto para retorno:
    // AGENDAS.PROCEDIMENTO_ID → LAB_UNIDADES.IDGRUPO_EXAME → LAB_UNIDADES.ID → LAB_EXAMES.IDUNIDADE
    $sql = "SELECT DISTINCT 
                le.IDEXAME as id, 
                le.EXAME as nome
            FROM LAB_EXAMES le
            INNER JOIN LAB_UNIDADES lu ON lu.ID = le.IDUNIDADE
            WHERE lu.IDGRUPO_EXAME = ?
            ORDER BY le.EXAME";
    
    $stmt = ibase_prepare($conn, $sql);
    $result = ibase_execute($stmt, $procedimento_id);
    
    $exames = array();
    while ($row = ibase_fetch_assoc($result)) {
        $exames[] = array(
            'id' => (int)$row['ID'],
            'nome' => utf8_encode(trim($row['NOME']))
        );
    }
    
    ibase_free_result($result);
    
    $response = array(
        'status' => 'sucesso',
        'tipo_agenda' => $tipo,
        'procedimento_id' => $procedimento_id,
        'total_exames' => count($exames),
        'exames' => $exames
    );
    
    echo json_encode($response);
    
} catch (Exception $e) {
    echo json_encode(array(
        'status' => 'erro',
        'mensagem' => $e->getMessage(),
        'exames' => array()
    ));
}
?>