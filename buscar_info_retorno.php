<?php
header('Content-Type: application/json');
require_once 'includes/connection.php';

// Suporte para linha de comando
if (php_sapi_name() === 'cli') {
    parse_str(getenv('QUERY_STRING') ?: '', $_GET);
}

$agenda_id = isset($_GET['agenda_id']) ? (int)$_GET['agenda_id'] : 0;

if (!$agenda_id) {
    echo '{"status":"erro","mensagem":"ID da agenda não fornecido"}';
    exit;
}

try {
    // Buscar convênios específicos desta agenda para retorno
    $sql = "SELECT DISTINCT c.ID as id, c.NOME as nome 
            FROM AGENDA_CONVENIOS ac 
            INNER JOIN CONVENIOS c ON c.ID = ac.CONVENIO_ID 
            WHERE ac.AGENDA_ID = " . $agenda_id . " 
            ORDER BY c.NOME";
    
    $result = ibase_query($conn, $sql);
    
    $convenios = array();
    while ($row = ibase_fetch_assoc($result)) {
        $convenios[] = array(
            'id' => (int)$row['ID'],
            'nome' => utf8_encode(trim($row['NOME']))
        );
    }
    
    ibase_free_result($result);
    
    $resposta = array(
        'status' => 'sucesso',
        'retorno' => array(
            'id' => $agenda_id,
            'nome' => 'Retorno ' . $agenda_id,
            'convenios' => $convenios
        )
    );
    
    echo json_encode($resposta);
    
} catch (Exception $e) {
    echo json_encode(array(
        'status' => 'erro',
        'mensagem' => 'Erro ao buscar convênios para retorno: ' . $e->getMessage()
    ));
}
?>