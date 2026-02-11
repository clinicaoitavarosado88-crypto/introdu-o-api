const axios = require('axios');

const API_BASE = 'http://sistema.clinicaoitavarosado.com.br/oitava/agenda';

// ========================================
// FUNÇÕES DE INTEGRAÇÃO COM API
// ========================================

// Buscar especialidades
async function buscarEspecialidades() {
  try {
    const response = await axios.get(`${API_BASE}/buscar_especialidades.php?busca=`, { timeout: 5000 });
    return response.data;
  } catch (error) {
    console.log('❌ Erro ao buscar especialidades:', error.message);
    return [];
  }
}

// Buscar agendas por especialidade
async function buscarAgendas(tipo = 'consulta') {
  try {
    const response = await axios.get(`${API_BASE}/listar_agendas_json.php?tipo=${tipo}`, { timeout: 5000 });
    return response.data;
  } catch (error) {
    console.log('❌ Erro ao buscar agendas:', error.message);
    return [];
  }
}

// Buscar horários disponíveis
async function buscarHorarios(agendaId, data) {
  try {
    const response = await axios.get(
      `${API_BASE}/buscar_horarios.php?agenda_id=${agendaId}&data=${data}`,
      { timeout: 5000 }
    );
    return response.data;
  } catch (error) {
    console.log('❌ Erro ao buscar horários:', error.message);
    return { horarios: [] };
  }
}

// Buscar paciente por termo (CPF, nome, etc)
async function buscarPaciente(termo) {
  try {
    const response = await axios.post(
      `${API_BASE}/buscar_paciente.php`,
      `termo=${encodeURIComponent(termo)}`,
      {
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        timeout: 5000
      }
    );
    return response.data;
  } catch (error) {
    console.log('❌ Erro ao buscar paciente:', error.message);
    return null;
  }
}

// Buscar convênios
async function buscarConvenios(termo = '') {
  try {
    const response = await axios.post(
      `${API_BASE}/buscar_convenio_ajax.php`,
      `busca=${termo}`,
      {
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        timeout: 5000
      }
    );
    return response.data;
  } catch (error) {
    console.log('❌ Erro ao buscar convênios:', error.message);
    return [];
  }
}

// Verificar vagas disponíveis
async function verificarVagas(agendaId, data, convenioId) {
  try {
    const response = await axios.get(
      `${API_BASE}/verificar_vagas.php?agenda_id=${agendaId}&data=${data}&convenio_id=${convenioId}`,
      { timeout: 5000 }
    );
    return response.data;
  } catch (error) {
    console.log('❌ Erro ao verificar vagas:', error.message);
    return { vagas_disponiveis: 0 };
  }
}

// Consultar agendamentos do paciente
async function consultarAgendamentosPaciente(pacienteId) {
  try {
    const response = await axios.get(
      `${API_BASE}/consultar_agendamentos_paciente.php?paciente_id=${pacienteId}`,
      { timeout: 5000 }
    );
    return response.data;
  } catch (error) {
    console.log('❌ Erro ao consultar agendamentos:', error.message);
    return [];
  }
}

// Criar agendamento
async function criarAgendamento(dados) {
  try {
    const response = await axios.post(
      `${API_BASE}/processar_agendamento.php`,
      dados,
      {
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        timeout: 10000
      }
    );
    return response.data;
  } catch (error) {
    console.log('❌ Erro ao criar agendamento:', error.message);
    return { sucesso: false, erro: error.message };
  }
}

module.exports = {
  buscarEspecialidades,
  buscarAgendas,
  buscarHorarios,
  buscarPaciente,
  buscarConvenios,
  verificarVagas,
  consultarAgendamentosPaciente,
  criarAgendamento
};
