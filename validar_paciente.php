<?php
// validar_paciente.php - Verificar se paciente tem cadastro no sistema
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-cache, must-revalidate');

error_reporting(0);
ini_set('display_errors', 0);

if (ob_get_level()) {
    ob_end_clean();
}
ob_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_clean();
    die('{"status":"erro","mensagem":"Método não permitido"}');
}

try {
    include_once 'includes/connection.php';
    
    if (!isset($conn)) {
        throw new Exception('Conexão não estabelecida');
    }
    
    // Dados recebidos
    $nome = trim($_POST['nome'] ?? '');
    $cpf = trim($_POST['cpf'] ?? '');
    $data_nascimento = trim($_POST['data_nascimento'] ?? '');
    
    if (empty($nome)) {
        throw new Exception('Nome é obrigatório para validação');
    }
    
    $paciente_encontrado = null;
    $motivo_busca = '';
    
    // 1. Busca exata por CPF (se informado)
    if (!empty($cpf)) {
        $cpf_limpo = preg_replace('/[^0-9]/', '', $cpf);
        
        if (strlen($cpf_limpo) === 11) {
            $query_cpf = "SELECT 
                            IDPACIENTE,
                            PACIENTE as NOME,
                            CPF,
                            FONE1 as TELEFONE,
                            EMAIL,
                            ANIVERSARIO as DATA_NASCIMENTO,
                            SEXO,
                            RG
                          FROM LAB_PACIENTES 
                          WHERE CPF = ?";
            
            $stmt_cpf = ibase_prepare($conn, $query_cpf);
            $result_cpf = ibase_execute($stmt_cpf, $cpf_limpo);
            $row = ibase_fetch_assoc($result_cpf);
            
            if ($row) {
                $paciente_encontrado = [
                    'id' => $row['IDPACIENTE'],
                    'nome' => trim($row['NOME']),
                    'cpf' => $cpf_limpo,
                    'cpf_formatado' => substr($cpf_limpo, 0, 3) . '.' . 
                                      substr($cpf_limpo, 3, 3) . '.' . 
                                      substr($cpf_limpo, 6, 3) . '-' . 
                                      substr($cpf_limpo, 9, 2),
                    'telefone' => trim($row['TELEFONE']),
                    'email' => trim($row['EMAIL']),
                    'data_nascimento' => $row['DATA_NASCIMENTO'],
                    'sexo' => trim($row['SEXO']),
                    'rg' => trim($row['RG'])
                ];
                $motivo_busca = 'cpf_exato';
            }
        }
    }
    
    // 2. Busca por nome + data de nascimento (se CPF não encontrou)
    if (!$paciente_encontrado && !empty($data_nascimento)) {
        $query_nome_data = "SELECT 
                              IDPACIENTE,
                              PACIENTE as NOME,
                              CPF,
                              FONE1 as TELEFONE,
                              EMAIL,
                              ANIVERSARIO as DATA_NASCIMENTO,
                              SEXO,
                              RG
                            FROM LAB_PACIENTES 
                            WHERE UPPER(PACIENTE) = UPPER(?) 
                            AND ANIVERSARIO = ?";
        
        $stmt_nome_data = ibase_prepare($conn, $query_nome_data);
        $result_nome_data = ibase_execute($stmt_nome_data, $nome, $data_nascimento);
        $row = ibase_fetch_assoc($result_nome_data);
        
        if ($row) {
            $cpf_limpo = trim($row['CPF']);
            $paciente_encontrado = [
                'id' => $row['IDPACIENTE'],
                'nome' => trim($row['NOME']),
                'cpf' => $cpf_limpo,
                'cpf_formatado' => strlen($cpf_limpo) === 11 ? 
                                  substr($cpf_limpo, 0, 3) . '.' . 
                                  substr($cpf_limpo, 3, 3) . '.' . 
                                  substr($cpf_limpo, 6, 3) . '-' . 
                                  substr($cpf_limpo, 9, 2) : $cpf_limpo,
                'telefone' => trim($row['TELEFONE']),
                'email' => trim($row['EMAIL']),
                'data_nascimento' => $row['DATA_NASCIMENTO'],
                'sexo' => trim($row['SEXO']),
                'rg' => trim($row['RG'])
            ];
            $motivo_busca = 'nome_data_nascimento';
        }
    }
    
    // 3. Busca similar por nome (se ainda não encontrou)
    if (!$paciente_encontrado) {
        $query_nome_similar = "SELECT 
                                 IDPACIENTE,
                                 PACIENTE as NOME,
                                 CPF,
                                 FONE1 as TELEFONE,
                                 EMAIL,
                                 ANIVERSARIO as DATA_NASCIMENTO,
                                 SEXO,
                                 RG
                               FROM LAB_PACIENTES 
                               WHERE UPPER(PACIENTE) LIKE UPPER(?) 
                               ORDER BY PACIENTE
                               ROWS 5";
        
        $stmt_nome_similar = ibase_prepare($conn, $query_nome_similar);
        $result_nome_similar = ibase_execute($stmt_nome_similar, '%' . $nome . '%');
        
        $pacientes_similares = [];
        while ($row = ibase_fetch_assoc($result_nome_similar)) {
            $cpf_limpo = trim($row['CPF']);
            $pacientes_similares[] = [
                'id' => $row['IDPACIENTE'],
                'nome' => trim($row['NOME']),
                'cpf' => $cpf_limpo,
                'cpf_formatado' => strlen($cpf_limpo) === 11 ? 
                                  substr($cpf_limpo, 0, 3) . '.' . 
                                  substr($cpf_limpo, 3, 3) . '.' . 
                                  substr($cpf_limpo, 6, 3) . '-' . 
                                  substr($cpf_limpo, 9, 2) : $cpf_limpo,
                'telefone' => trim($row['TELEFONE']),
                'email' => trim($row['EMAIL']),
                'data_nascimento' => $row['DATA_NASCIMENTO'],
                'data_nascimento_formatada' => $row['DATA_NASCIMENTO'] ? 
                                              (new DateTime($row['DATA_NASCIMENTO']))->format('d/m/Y') : '',
                'sexo' => trim($row['SEXO']),
                'rg' => trim($row['RG'])
            ];
        }
        
        if (count($pacientes_similares) > 0) {
            $motivo_busca = 'nome_similar';
        }
    }
    
    // Resultado da validação
    if ($paciente_encontrado) {
        // Paciente encontrado com certeza
        ob_clean();
        echo json_encode([
            'status' => 'encontrado',
            'paciente' => $paciente_encontrado,
            'motivo_busca' => $motivo_busca,
            'mensagem' => 'Paciente encontrado no sistema'
        ]);
        
    } elseif (isset($pacientes_similares) && count($pacientes_similares) > 0) {
        // Pacientes similares encontrados - usuário deve escolher
        ob_clean();
        echo json_encode([
            'status' => 'similares_encontrados',
            'pacientes_similares' => $pacientes_similares,
            'motivo_busca' => $motivo_busca,
            'mensagem' => 'Encontrados pacientes com nomes similares. Selecione o correto ou cadastre novo.'
        ]);
        
    } else {
        // Nenhum paciente encontrado - pode cadastrar novo
        ob_clean();
        echo json_encode([
            'status' => 'nao_encontrado',
            'motivo_busca' => 'nenhum_resultado',
            'mensagem' => 'Paciente não encontrado. Você pode cadastrá-lo como novo.'
        ]);
    }
    
} catch (Exception $e) {
    ob_clean();
    echo json_encode([
        'status' => 'erro',
        'mensagem' => $e->getMessage()
    ]);
}
?>