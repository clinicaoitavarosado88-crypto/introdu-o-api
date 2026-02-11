<?php
// Script para verificar API semelhante ao usado na frmpaciente_t2.php
header('Content-Type: application/json');
include 'includes/connection.php';

// Suporte para linha de comando
if (php_sapi_name() === 'cli') {
    parse_str(getenv('QUERY_STRING') ?: '', $_GET);
    parse_str(getenv('POST_DATA') ?: '', $_POST);
}

$cpf = $_GET['cpf'] ?? $_POST['cpf'] ?? '';
$convenio_id = $_GET['convenio_id'] ?? $_POST['convenio_id'] ?? '';

if (!$cpf) {
    echo json_encode([
        'erro' => 'CPF é obrigatório',
        'status' => 'erro'
    ]);
    exit;
}

try {
    // Simular verificação da API (similar ao proxy.php)
    // Em produção, aqui seria feita a chamada para a API externa
    
    // Buscar dados do convênio se fornecido
    $convenio_info = null;
    if ($convenio_id) {
        $query = "SELECT CONVENIO, SINDICATO, SUSPENSO, PEDE_TOKEN FROM LAB_CONVENIOS WHERE IDCONVENIO = ?";
        $stmt = ibase_prepare($conn, $query);
        $result = ibase_execute($stmt, $convenio_id);
        $convenio_info = ibase_fetch_assoc($result);
    }
    
    // Simular resposta da API baseada no CPF
    $cpf_limpo = preg_replace('/[^0-9]/', '', $cpf);
    $ultimo_digito = substr($cpf_limpo, -1);
    
    // Lógica de simulação baseada no último dígito do CPF
    switch ($ultimo_digito) {
        case '0':
        case '1':
        case '2':
            $status = 'adimplente';
            $plano = 'Plano Básico';
            $tipo_beneficiario = 'Titular';
            $empresa = 'EMPRESA TESTE';
            $sindicato = 'SINDICATO TESTE';
            break;
            
        case '3':
        case '4':
            $status = 'inadimplente';
            $plano = 'Plano Básico';
            $tipo_beneficiario = 'Dependente';
            $empresa = 'EMPRESA BLOQUEADA';
            $sindicato = '';
            break;
            
        case '5':
        case '6':
            $status = 'liberado';
            $plano = 'Benefício TOP';
            $tipo_beneficiario = 'Titular';
            $empresa = $convenio_info ? explode(' -', $convenio_info['CONVENIO'])[0] : 'EMPRESA LIBERADA';
            $sindicato = $convenio_info ? trim($convenio_info['SINDICATO']) : 'SECOM';
            break;
            
        case '7':
        case '8':
            $status = 'pendente';
            $plano = '';
            $tipo_beneficiario = '';
            $empresa = '';
            $sindicato = '';
            break;
            
        default:
            $status = 'nao_encontrado';
            $plano = '';
            $tipo_beneficiario = '';
            $empresa = '';
            $sindicato = '';
    }
    
    // Montar resposta similar à API real
    $response = [
        'status' => $status,
        'plano' => $plano,
        'tipo_beneficiario' => $tipo_beneficiario,
        'empresa' => $empresa,
        'sindicato' => $sindicato,
        'cpf' => $cpf,
        'timestamp' => date('Y-m-d H:i:s'),
        'convenio_info' => $convenio_info
    ];
    
    // Log da verificação (opcional)
    $log_data = [
        'cpf' => $cpf,
        'convenio_id' => $convenio_id,
        'status' => $status,
        'timestamp' => date('Y-m-d H:i:s'),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'CLI'
    ];
    
    error_log('API_OS_VERIFICACAO: ' . json_encode($log_data));
    
    echo json_encode($response);
    
} catch (Exception $e) {
    echo json_encode([
        'erro' => 'Erro interno: ' . $e->getMessage(),
        'status' => 'erro'
    ]);
}
?>