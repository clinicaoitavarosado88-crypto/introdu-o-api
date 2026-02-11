# 🤝 Guia de Contribuição

## **API Sistema de Agendamento - Clínica Oitava Rosado**

Obrigado por considerar contribuir para este projeto!

---

## 📋 **Antes de Contribuir**

### **Estrutura do Banco de Dados Firebird**

Este projeto utiliza **Firebird** com uma estrutura específica de nomes de colunas. É **CRÍTICO** conhecer o mapeamento correto:

#### **Tabelas Principais:**

**LAB_PACIENTES:**
- ❌ `ID` → ✅ `IDPACIENTE`
- ❌ `NOME` → ✅ `PACIENTE`
- ❌ `DATA_NASCIMENTO` → ✅ `ANIVERSARIO`
- ❌ `TELEFONE` → ✅ `FONE1`
- ❌ `EMAIL` → ✅ `EMAIL` (correto)
- ❌ `ENDERECO` → ✅ `ENDERECO` (correto)

**LAB_CIDADES (Unidades):**
- ✅ `ID` (correto)
- ✅ `NOME_UNIDADE` (correto)
- ❌ `ATIVO` → ✅ `AGENDA_ATI`
- ✅ `ENDERECO` (correto)
- ✅ `CNPJ` (correto)

**LAB_CONVENIOS:**
- ❌ `ID` → ✅ `IDCONVENIO`
- ❌ `NOME_CONVENIO` → ✅ `CONVENIO`

**AGENDAMENTOS:**
- ✅ `ID` (correto)
- ❌ `NUMERO` → ✅ `NUMERO_AGENDAMENTO`
- ✅ `PACIENTE_ID` (correto)
- ✅ `CONVENIO_ID` (correto)
- ❌ `USUARIO_CRIACAO` → ✅ `CRIADO_POR`

---

## 🔧 **Regras para Contribuição**

### **1. Autenticação**
- ✅ **SEMPRE** incluir `includes/auth_middleware.php` em novos endpoints
- ✅ Usar `verify_api_token()` para validar autenticação
- ✅ Retornar `401 Unauthorized` para tokens inválidos

### **2. Transações Firebird**
```php
try {
    // Sua lógica aqui

    // SEMPRE fazer commit
    ibase_commit($conn);

} catch (Exception $e) {
    // SEMPRE fazer rollback em caso de erro
    if (isset($conn)) {
        ibase_rollback($conn);
    }

    // Log do erro
    error_log("Erro: " . $e->getMessage());
}
```

### **3. Encoding**
- ✅ **Input UTF-8** → **Banco Windows-1252**
```php
mb_convert_encoding($texto, 'Windows-1252', 'UTF-8')
```

- ✅ **Banco Windows-1252** → **Output UTF-8**
```php
mb_convert_encoding($texto, 'UTF-8', 'Windows-1252')
```

### **4. Headers CORS**
```php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
```

### **5. Validação de Dados**
```php
// SEMPRE validar campos obrigatórios
if (empty($campo_obrigatorio)) {
    http_response_code(400);
    echo json_encode(['error' => 'Bad Request', 'message' => 'Campo obrigatório']);
    exit;
}
```

### **6. Operador Null Coalescing**
```php
// Use ?? para campos que podem ser null
$valor = trim($row['CAMPO'] ?? '');
```

---

## 📝 **Checklist para Novos Endpoints**

- [ ] Incluir `auth_middleware.php`
- [ ] Validar token com `verify_api_token()`
- [ ] Headers CORS configurados
- [ ] Validação de parâmetros obrigatórios
- [ ] Mapeamento correto de colunas do banco
- [ ] Conversão de encoding (UTF-8 ↔ Windows-1252)
- [ ] `ibase_commit()` em caso de sucesso
- [ ] `ibase_rollback()` em caso de erro
- [ ] Tratamento de erros com try/catch
- [ ] Respostas JSON padronizadas
- [ ] Documentar no `API_DOCUMENTATION.md`
- [ ] Testar com Postman
- [ ] Atualizar `CHANGELOG` no `README.md`

---

## 🧪 **Testes**

### **Testar Localmente:**
```bash
# Via CLI
php -d display_errors=1 -r "
\$_SERVER['REQUEST_METHOD'] = 'GET';
\$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer TOKEN';
include 'seu_endpoint.php';
"

# Via cURL
curl -X GET "http://localhost/oitava/agenda/seu_endpoint.php" \
  -H "Authorization: Bearer TOKEN"
```

### **Token de Teste:**
```
4P2do9ksh2fQfLtiB10jN2blj5SBksOjGbIOTmQQu3M
```

---

## 📦 **Estrutura de Resposta Padrão**

### **Sucesso:**
```json
{
  "status": "sucesso",
  "message": "Operação realizada com sucesso",
  "data": {}
}
```

### **Erro:**
```json
{
  "error": "Tipo do Erro",
  "message": "Descrição detalhada do erro"
}
```

---

## 🚀 **Fluxo de Desenvolvimento**

1. **Fork** o repositório
2. **Clone** seu fork
3. **Crie uma branch** descritiva: `git checkout -b feature/nova-funcionalidade`
4. **Desenvolva** seguindo as regras acima
5. **Teste** localmente
6. **Commit** com mensagens claras: `git commit -m "feat: adiciona endpoint X"`
7. **Push** para seu fork: `git push origin feature/nova-funcionalidade`
8. **Abra um Pull Request** com descrição detalhada

---

## 📚 **Recursos Úteis**

- [Documentação Firebird](https://firebirdsql.org/en/documentation/)
- [PHP Firebird Functions](https://www.php.net/manual/en/book.ibase.php)
- [Postman Collection](Clinica_Oitava_API.postman_collection.json)

---

## ❓ **Dúvidas?**

- 📧 Email: suporte@clinicaoitavarosado.com.br
- 📱 WhatsApp: (84) 99999-9999
- 📚 Documentação: `API_DOCUMENTATION.md`

---

**Versão:** 2.3
**Última atualização:** 06 Outubro 2025
