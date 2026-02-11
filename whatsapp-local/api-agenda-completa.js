const axios = require('axios');

const API_CONFIG = {
  baseURL: 'http://sistema.clinicaoitavarosado.com.br/oitava/agenda',
  token: 'OWY2NGE0YTQtNGQ0MS00ZjVkLWI3ZTUtOGY2ZDZhNGE0YTQ0',
  timeout: 30000
};

// Headers padrão com autenticação
const getHeaders = () => ({
  'Authorization': `Bearer ${API_CONFIG.token}`,
  'Content-Type': 'application/json'
});

const getHeadersFormData = () => ({
  'Authorization': `Bearer ${API_CONFIG.token}`,
  'Content-Type': 'application/x-www-form-urlencoded'
});

// ========================================
// ESPECIALIDADES E MÉDICOS
// ========================================

async function buscarEspecialidades(termo = '') {
  try {
    const response = await axios.get(`${API_CONFIG.baseURL}/buscar_especialidades.php`, {
      params: { busca: termo },
      headers: getHeaders(),
      timeout: API_CONFIG.timeout
    });
    return { sucesso: true, dados: response.data.results || [] };
  } catch (error) {
    console.log('❌ Erro ao buscar especialidades:', error.message);
    return { sucesso: false, erro: error.message };
  }
}

async function buscarMedicos(termo = '') {
  try {
    const response = await axios.get(`${API_CONFIG.baseURL}/buscar_medicos.php`, {
      params: { busca: termo },
      headers: getHeaders(),
      timeout: API_CONFIG.timeout
    });
    return { sucesso: true, dados: response.data.results || [] };
  } catch (error) {
    console.log('❌ Erro ao buscar médicos:', error.message);
    return { sucesso: false, erro: error.message };
  }
}

// ========================================
// AGENDAS E HORÁRIOS
// ========================================

async function listarAgendasJSON(tipo, nome, dia = null, cidade = null) {
  try {
    const params = { tipo, nome };
    if (dia) params.dia = dia;
    if (cidade) params.cidade = cidade;

    const response = await axios.get(`${API_CONFIG.baseURL}/listar_agendas_json.php`, {
      params,
      headers: getHeaders(),
      timeout: API_CONFIG.timeout
    });
    return { sucesso: true, dados: response.data };
  } catch (error) {
    console.log('❌ Erro ao listar agendas:', error.message);
    return { sucesso: false, erro: error.message };
  }
}

async function buscarHorariosDisponiveis(agendaId, data) {
  try {
    const response = await axios.get(`${API_CONFIG.baseURL}/buscar_horarios.php`, {
      params: { agenda_id: agendaId, data },
      headers: getHeaders(),
      timeout: API_CONFIG.timeout
    });
    return { sucesso: true, dados: response.data };
  } catch (error) {
    console.log('❌ Erro ao buscar horários:', error.message);
    return { sucesso: false, erro: error.message };
  }
}

async function verificarVagas(agendaId, data, convenioId) {
  try {
    const response = await axios.get(`${API_CONFIG.baseURL}/verificar_vagas.php`, {
      params: { agenda_id: agendaId, data, convenio_id: convenioId },
      headers: getHeaders(),
      timeout: API_CONFIG.timeout
    });
    return { sucesso: true, dados: response.data };
  } catch (error) {
    console.log('❌ Erro ao verificar vagas:', error.message);
    return { sucesso: false, erro: error.message };
  }
}

// ========================================
// PACIENTES
// ========================================

async function buscarPaciente(termo) {
  try {
    const response = await axios.post(`${API_CONFIG.baseURL}/buscar_paciente.php`,
      `termo=${encodeURIComponent(termo)}`,
      {
        headers: getHeadersFormData(),
        timeout: API_CONFIG.timeout
      }
    );
    return { sucesso: true, dados: response.data };
  } catch (error) {
    console.log('❌ Erro ao buscar paciente:', error.message);
    return { sucesso: false, erro: error.message };
  }
}

async function cadastrarPaciente(dadosPaciente) {
  try {
    const response = await axios.post(`${API_CONFIG.baseURL}/cadastrar_paciente.php`,
      dadosPaciente,
      {
        headers: getHeaders(),
        timeout: API_CONFIG.timeout
      }
    );
    return { sucesso: true, dados: response.data };
  } catch (error) {
    console.log('❌ Erro ao cadastrar paciente:', error.message);
    return { sucesso: false, erro: error.message };
  }
}

async function consultarAgendamentosPaciente(pacienteId, filtros = {}) {
  try {
    const params = { paciente_id: pacienteId, ...filtros };
    const response = await axios.get(`${API_CONFIG.baseURL}/consultar_agendamentos_paciente.php`, {
      params,
      headers: getHeaders(),
      timeout: API_CONFIG.timeout
    });
    return { sucesso: true, dados: response.data };
  } catch (error) {
    console.log('❌ Erro ao consultar agendamentos:', error.message);
    return { sucesso: false, erro: error.message };
  }
}

// ========================================
// CONVÊNIOS
// ========================================

async function buscarConvenios(termo = '') {
  try {
    const response = await axios.get(`${API_CONFIG.baseURL}/buscar_convenios.php`, {
      params: { busca: termo },
      headers: getHeaders(),
      timeout: API_CONFIG.timeout
    });
    return { sucesso: true, dados: response.data.results || [] };
  } catch (error) {
    console.log('❌ Erro ao buscar convênios:', error.message);
    return { sucesso: false, erro: error.message };
  }
}

// ========================================
// PROCEDIMENTOS E EXAMES
// ========================================

async function buscarProcedimentos(termo = '') {
  try {
    const response = await axios.get(`${API_CONFIG.baseURL}/buscar_procedimentos.php`, {
      params: { busca: termo },
      headers: getHeaders(),
      timeout: API_CONFIG.timeout
    });
    return { sucesso: true, dados: response.data.results || [] };
  } catch (error) {
    console.log('❌ Erro ao buscar procedimentos:', error.message);
    return { sucesso: false, erro: error.message };
  }
}

async function buscarExamesAgenda(agendaId) {
  try {
    const response = await axios.get(`${API_CONFIG.baseURL}/buscar_exames_agenda.php`, {
      params: { agenda_id: agendaId },
      headers: getHeaders(),
      timeout: API_CONFIG.timeout
    });
    return { sucesso: true, dados: response.data };
  } catch (error) {
    console.log('❌ Erro ao buscar exames:', error.message);
    return { sucesso: false, erro: error.message };
  }
}

async function consultarPreparos(params = {}) {
  try {
    const response = await axios.get(`${API_CONFIG.baseURL}/consultar_preparos.php`, {
      params,
      headers: getHeaders(),
      timeout: API_CONFIG.timeout
    });
    return { sucesso: true, dados: response.data };
  } catch (error) {
    console.log('❌ Erro ao consultar preparos:', error.message);
    return { sucesso: false, erro: error.message };
  }
}

// ========================================
// AGENDAMENTOS
// ========================================

async function criarAgendamento(dadosAgendamento) {
  try {
    // Converter objeto para formato form-urlencoded
    const formData = new URLSearchParams();
    Object.keys(dadosAgendamento).forEach(key => {
      if (Array.isArray(dadosAgendamento[key])) {
        dadosAgendamento[key].forEach(val => formData.append(`${key}[]`, val));
      } else {
        formData.append(key, dadosAgendamento[key]);
      }
    });

    const response = await axios.post(`${API_CONFIG.baseURL}/processar_agendamento.php`,
      formData.toString(),
      {
        headers: getHeadersFormData(),
        timeout: API_CONFIG.timeout
      }
    );
    return { sucesso: true, dados: response.data };
  } catch (error) {
    console.log('❌ Erro ao criar agendamento:', error.message);
    return { sucesso: false, erro: error.message };
  }
}

async function buscarAgendamento(agendamentoId) {
  try {
    const response = await axios.get(`${API_CONFIG.baseURL}/buscar_agendamento.php`, {
      params: { id: agendamentoId },
      headers: getHeaders(),
      timeout: API_CONFIG.timeout
    });
    return { sucesso: true, dados: response.data };
  } catch (error) {
    console.log('❌ Erro ao buscar agendamento:', error.message);
    return { sucesso: false, erro: error.message };
  }
}

async function cancelarAgendamento(agendamentoId, motivo, usuario = 'WHATSAPP_BOT') {
  try {
    const formData = new URLSearchParams();
    formData.append('agendamento_id', agendamentoId);
    formData.append('motivo', motivo);
    formData.append('usuario', usuario);

    const response = await axios.post(`${API_CONFIG.baseURL}/cancelar_agendamento.php`,
      formData.toString(),
      {
        headers: getHeadersFormData(),
        timeout: API_CONFIG.timeout
      }
    );
    return { sucesso: true, dados: response.data };
  } catch (error) {
    console.log('❌ Erro ao cancelar agendamento:', error.message);
    return { sucesso: false, erro: error.message };
  }
}

async function atualizarStatusAgendamento(agendamentoId, status, usuario = 'WHATSAPP_BOT') {
  try {
    const formData = new URLSearchParams();
    formData.append('agendamento_id', agendamentoId);
    formData.append('status', status);
    formData.append('usuario', usuario);

    const response = await axios.post(`${API_CONFIG.baseURL}/atualizar_status_agendamento.php`,
      formData.toString(),
      {
        headers: getHeadersFormData(),
        timeout: API_CONFIG.timeout
      }
    );
    return { sucesso: true, dados: response.data };
  } catch (error) {
    console.log('❌ Erro ao atualizar status:', error.message);
    return { sucesso: false, erro: error.message };
  }
}

// ========================================
// PREÇOS E UNIDADES
// ========================================

async function consultarPrecos(params = {}) {
  try {
    const response = await axios.get(`${API_CONFIG.baseURL}/consultar_precos.php`, {
      params,
      headers: getHeaders(),
      timeout: API_CONFIG.timeout
    });
    return { sucesso: true, dados: response.data };
  } catch (error) {
    console.log('❌ Erro ao consultar preços:', error.message);
    return { sucesso: false, erro: error.message };
  }
}

async function consultarUnidades(params = {}) {
  try {
    const response = await axios.get(`${API_CONFIG.baseURL}/consultar_unidades.php`, {
      params,
      headers: getHeaders(),
      timeout: API_CONFIG.timeout
    });
    return { sucesso: true, dados: response.data };
  } catch (error) {
    console.log('❌ Erro ao consultar unidades:', error.message);
    return { sucesso: false, erro: error.message };
  }
}

// ========================================
// FUNÇÕES AUXILIARES
// ========================================

// Formatar data para padrão da API (YYYY-MM-DD)
function formatarDataAPI(data) {
  if (data instanceof Date) {
    const ano = data.getFullYear();
    const mes = String(data.getMonth() + 1).padStart(2, '0');
    const dia = String(data.getDate()).padStart(2, '0');
    return `${ano}-${mes}-${dia}`;
  }
  return data;
}

// Formatar data para exibição (DD/MM/YYYY)
function formatarDataExibicao(data) {
  if (typeof data === 'string' && data.includes('-')) {
    const [ano, mes, dia] = data.split('-');
    return `${dia}/${mes}/${ano}`;
  }
  return data;
}

// Obter dia da semana em português
function obterDiaSemana(data) {
  const dias = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
  const dataObj = typeof data === 'string' ? new Date(data + 'T00:00:00') : data;
  return dias[dataObj.getDay()];
}

// Validar CPF
function validarCPF(cpf) {
  cpf = cpf.replace(/[^\d]/g, '');
  if (cpf.length !== 11) return false;
  if (/^(\d)\1{10}$/.test(cpf)) return false;
  return true;
}

// Formatar telefone
function formatarTelefone(telefone) {
  const numeros = telefone.replace(/\D/g, '');
  if (numeros.length === 11) {
    return `(${numeros.substr(0, 2)}) ${numeros.substr(2, 5)}-${numeros.substr(7)}`;
  }
  if (numeros.length === 10) {
    return `(${numeros.substr(0, 2)}) ${numeros.substr(2, 4)}-${numeros.substr(6)}`;
  }
  return telefone;
}

module.exports = {
  // Especialidades e Médicos
  buscarEspecialidades,
  buscarMedicos,

  // Agendas e Horários
  listarAgendasJSON,
  buscarHorariosDisponiveis,
  verificarVagas,

  // Pacientes
  buscarPaciente,
  cadastrarPaciente,
  consultarAgendamentosPaciente,

  // Convênios
  buscarConvenios,

  // Procedimentos e Exames
  buscarProcedimentos,
  buscarExamesAgenda,
  consultarPreparos,

  // Agendamentos
  criarAgendamento,
  buscarAgendamento,
  cancelarAgendamento,
  atualizarStatusAgendamento,

  // Preços e Unidades
  consultarPrecos,
  consultarUnidades,

  // Auxiliares
  formatarDataAPI,
  formatarDataExibicao,
  obterDiaSemana,
  validarCPF,
  formatarTelefone
};
