const api = require('./api-clinica');

// Armazenar contexto de cada usuário
const contextoUsuarios = {};

// Estados da conversa
const ESTADOS = {
  INICIO: 'inicio',
  ESCOLHENDO_ESPECIALIDADE: 'escolhendo_especialidade',
  ESCOLHENDO_DATA: 'escolhendo_data',
  ESCOLHENDO_HORARIO: 'escolhendo_horario',
  INFORMANDO_CPF: 'informando_cpf',
  INFORMANDO_CONVENIO: 'informando_convenio',
  CONFIRMANDO: 'confirmando',
  CONSULTANDO_AGENDAMENTOS: 'consultando_agendamentos'
};

// Obter ou criar contexto do usuário
function getContexto(usuarioId) {
  if (!contextoUsuarios[usuarioId]) {
    contextoUsuarios[usuarioId] = {
      estado: ESTADOS.INICIO,
      dados: {}
    };
  }
  return contextoUsuarios[usuarioId];
}

// Resetar contexto
function resetarContexto(usuarioId) {
  contextoUsuarios[usuarioId] = {
    estado: ESTADOS.INICIO,
    dados: {}
  };
}

// Processar mensagem do usuário
async function processarMensagem(usuarioId, mensagem) {
  const contexto = getContexto(usuarioId);
  const msg = mensagem.toLowerCase().trim();

  // Comandos globais
  if (/^(cancelar|sair|voltar|menu)/.test(msg)) {
    resetarContexto(usuarioId);
    return menuPrincipal();
  }

  // Processar baseado no estado
  switch (contexto.estado) {
    case ESTADOS.INICIO:
      return await processarInicio(usuarioId, msg);

    case ESTADOS.ESCOLHENDO_ESPECIALIDADE:
      return await processarEspecialidade(usuarioId, msg);

    case ESTADOS.ESCOLHENDO_DATA:
      return await processarData(usuarioId, msg);

    case ESTADOS.ESCOLHENDO_HORARIO:
      return await processarHorario(usuarioId, msg);

    case ESTADOS.INFORMANDO_CPF:
      return await processarCPF(usuarioId, msg);

    case ESTADOS.INFORMANDO_CONVENIO:
      return await processarConvenio(usuarioId, msg);

    case ESTADOS.CONFIRMANDO:
      return await processarConfirmacao(usuarioId, msg);

    case ESTADOS.CONSULTANDO_AGENDAMENTOS:
      return await processarConsultaAgendamentos(usuarioId, msg);

    default:
      return menuPrincipal();
  }
}

// ========================================
// PROCESSADORES DE ESTADO
// ========================================

async function processarInicio(usuarioId, msg) {
  // Saudações
  if (/^(oi|olá|ola|hello|hi|bom dia|boa tarde|boa noite)/.test(msg)) {
    return menuPrincipal();
  }

  // Agendar
  if (/agendar|consulta|marcar/.test(msg)) {
    const contexto = getContexto(usuarioId);
    contexto.estado = ESTADOS.ESCOLHENDO_ESPECIALIDADE;

    const especialidades = await api.buscarEspecialidades();

    if (especialidades && especialidades.length > 0) {
      let resposta = '📅 *Agendar Consulta*\n\nEscolha a especialidade:\n\n';
      especialidades.slice(0, 10).forEach((esp, i) => {
        resposta += `${i + 1}. ${esp.nome}\n`;
      });
      resposta += '\n💬 Digite o número ou nome da especialidade';
      return resposta;
    } else {
      return 'Desculpe, não consegui carregar as especialidades.\n\n📞 Ligue: (84) 3316-2960';
    }
  }

  // Consultar agendamentos
  if (/meus agendamentos|minhas consultas|ver agendamento/.test(msg)) {
    const contexto = getContexto(usuarioId);
    contexto.estado = ESTADOS.CONSULTANDO_AGENDAMENTOS;
    return '📋 *Consultar Agendamentos*\n\nPara consultar seus agendamentos, informe seu CPF:\n\nEx: 123.456.789-00';
  }

  // Preços
  if (/preço|preco|valor|custo|quanto/.test(msg)) {
    return '💰 *Consulta de Preços*\n\nOs valores variam por especialidade e convênio.\n\n📞 Ligue: (84) 3316-2960\n💬 WhatsApp: (84) 98818-6138\n\nOu digite *agendar* para iniciar!';
  }

  // Horários
  if (/funciona|atende|abre|fecha|horário/.test(msg)) {
    return '⏰ *Horário de Funcionamento*\n\n🗓️ Seg-Sex: 7h - 18h\n🗓️ Sábado: 7h - 12h\n❌ Domingo: Fechado\n\n📞 (84) 3316-2960';
  }

  // Localização
  if (/onde|endereço|endereco|local/.test(msg)) {
    return '📍 *Localização*\n\nAv. Exemplo, 123 - Centro\nMossoró/RN\n\n📞 (84) 3316-2960\n⏰ Seg-Sex: 7h-18h';
  }

  // Resposta padrão
  return menuPrincipal();
}

async function processarEspecialidade(usuarioId, msg) {
  const contexto = getContexto(usuarioId);
  const especialidades = await api.buscarEspecialidades();

  // Verificar se é número
  const numero = parseInt(msg);
  let especialidadeSelecionada = null;

  if (!isNaN(numero) && numero > 0 && numero <= especialidades.length) {
    especialidadeSelecionada = especialidades[numero - 1];
  } else {
    // Buscar por nome
    especialidadeSelecionada = especialidades.find(esp =>
      esp.nome.toLowerCase().includes(msg)
    );
  }

  if (especialidadeSelecionada) {
    contexto.dados.especialidade = especialidadeSelecionada;
    contexto.estado = ESTADOS.ESCOLHENDO_DATA;

    const hoje = new Date();
    const datas = [];
    for (let i = 1; i <= 7; i++) {
      const data = new Date(hoje);
      data.setDate(hoje.getDate() + i);
      datas.push(data);
    }

    let resposta = `✅ Especialidade: *${especialidadeSelecionada.nome}*\n\n📅 Escolha a data:\n\n`;
    datas.forEach((data, i) => {
      const dia = data.getDate().toString().padStart(2, '0');
      const mes = (data.getMonth() + 1).toString().padStart(2, '0');
      const ano = data.getFullYear();
      const diaSemana = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'][data.getDay()];
      resposta += `${i + 1}. ${diaSemana} ${dia}/${mes}/${ano}\n`;
    });
    resposta += '\n💬 Digite o número da data desejada';

    return resposta;
  } else {
    return '❌ Especialidade não encontrada.\n\nTente novamente ou digite *menu* para voltar.';
  }
}

async function processarData(usuarioId, msg) {
  const contexto = getContexto(usuarioId);
  const numero = parseInt(msg);

  if (!isNaN(numero) && numero >= 1 && numero <= 7) {
    const hoje = new Date();
    const dataSelecionada = new Date(hoje);
    dataSelecionada.setDate(hoje.getDate() + numero);

    const dia = dataSelecionada.getDate().toString().padStart(2, '0');
    const mes = (dataSelecionada.getMonth() + 1).toString().padStart(2, '0');
    const ano = dataSelecionada.getFullYear();
    const dataFormatada = `${ano}-${mes}-${dia}`;

    contexto.dados.data = dataFormatada;
    contexto.dados.dataExibicao = `${dia}/${mes}/${ano}`;

    // Buscar agendas da especialidade
    const agendas = await api.buscarAgendas('consulta');
    const agendasEspecialidade = agendas.filter(a =>
      a.especialidade && a.especialidade.toLowerCase().includes(
        contexto.dados.especialidade.nome.toLowerCase()
      )
    );

    if (agendasEspecialidade.length > 0) {
      const agenda = agendasEspecialidade[0];
      contexto.dados.agenda = agenda;

      // Buscar horários
      const horarios = await api.buscarHorarios(agenda.id, dataFormatada);

      if (horarios && horarios.horarios && horarios.horarios.length > 0) {
        contexto.dados.horariosDisponiveis = horarios.horarios;
        contexto.estado = ESTADOS.ESCOLHENDO_HORARIO;

        let resposta = `✅ Data: *${contexto.dados.dataExibicao}*\n\n⏰ Horários disponíveis:\n\n`;
        horarios.horarios.slice(0, 10).forEach((h, i) => {
          resposta += `${i + 1}. ${h.horario}\n`;
        });
        resposta += '\n💬 Digite o número do horário desejado';

        return resposta;
      } else {
        resetarContexto(usuarioId);
        return '❌ Não há horários disponíveis para esta data.\n\n📞 Ligue: (84) 3316-2960\n\nOu digite *agendar* para tentar outra data.';
      }
    } else {
      resetarContexto(usuarioId);
      return '❌ Não encontramos agenda para esta especialidade.\n\n📞 Ligue: (84) 3316-2960';
    }
  } else {
    return '❌ Data inválida.\n\nDigite um número de 1 a 7 ou *menu* para voltar.';
  }
}

async function processarHorario(usuarioId, msg) {
  const contexto = getContexto(usuarioId);
  const numero = parseInt(msg);
  const horarios = contexto.dados.horariosDisponiveis;

  if (!isNaN(numero) && numero >= 1 && numero <= horarios.length) {
    const horarioSelecionado = horarios[numero - 1];
    contexto.dados.horario = horarioSelecionado;
    contexto.estado = ESTADOS.INFORMANDO_CPF;

    return `✅ Horário: *${horarioSelecionado.horario}*\n\n📋 Agora preciso de seus dados:\n\n*Informe seu CPF:*\n\nEx: 123.456.789-00`;
  } else {
    return '❌ Horário inválido.\n\nDigite um número válido ou *menu* para voltar.';
  }
}

async function processarCPF(usuarioId, msg) {
  const contexto = getContexto(usuarioId);

  // Limpar CPF (remover pontos e traços)
  const cpf = msg.replace(/[^0-9]/g, '');

  if (cpf.length === 11) {
    // Buscar paciente
    const paciente = await api.buscarPaciente(cpf);

    if (paciente && paciente.id) {
      contexto.dados.paciente = paciente;
      contexto.estado = ESTADOS.INFORMANDO_CONVENIO;

      const convenios = await api.buscarConvenios('');

      let resposta = `✅ Paciente: *${paciente.nome}*\n\n💳 Escolha o convênio:\n\n`;
      if (convenios && convenios.length > 0) {
        convenios.slice(0, 10).forEach((conv, i) => {
          resposta += `${i + 1}. ${conv.nome}\n`;
        });
        resposta += '\n💬 Digite o número do convênio';
      } else {
        resposta += 'Particular\n\n💬 Digite *1* para confirmar';
      }

      return resposta;
    } else {
      resetarContexto(usuarioId);
      return '❌ Paciente não encontrado.\n\n📞 Cadastre-se ligando: (84) 3316-2960\n\nOu digite *menu* para voltar.';
    }
  } else {
    return '❌ CPF inválido.\n\nInforme um CPF válido (11 dígitos) ou digite *menu* para voltar.';
  }
}

async function processarConvenio(usuarioId, msg) {
  const contexto = getContexto(usuarioId);
  const numero = parseInt(msg);
  const convenios = await api.buscarConvenios('');

  if (!isNaN(numero) && numero >= 1 && numero <= convenios.length) {
    const convenioSelecionado = convenios[numero - 1];
    contexto.dados.convenio = convenioSelecionado;
    contexto.estado = ESTADOS.CONFIRMANDO;

    const resumo = '📋 *CONFIRME SEU AGENDAMENTO*\n\n' +
      `👤 Paciente: ${contexto.dados.paciente.nome}\n` +
      `🏥 Especialidade: ${contexto.dados.especialidade.nome}\n` +
      `📅 Data: ${contexto.dados.dataExibicao}\n` +
      `⏰ Horário: ${contexto.dados.horario.horario}\n` +
      `💳 Convênio: ${convenioSelecionado.nome}\n\n` +
      '✅ Digite *CONFIRMAR* para agendar\n' +
      '❌ Digite *CANCELAR* para desistir';

    return resumo;
  } else {
    return '❌ Convênio inválido.\n\nDigite um número válido ou *menu* para voltar.';
  }
}

async function processarConfirmacao(usuarioId, msg) {
  const contexto = getContexto(usuarioId);

  if (/confirmar|sim|ok/.test(msg)) {
    // Criar agendamento
    const dados = `agenda_id=${contexto.dados.agenda.id}` +
      `&paciente_id=${contexto.dados.paciente.id}` +
      `&data=${contexto.dados.data}` +
      `&horario=${contexto.dados.horario.horario}` +
      `&convenio_id=${contexto.dados.convenio.id}` +
      `&usuario=WHATSAPP_BOT`;

    const resultado = await api.criarAgendamento(dados);

    const dadosAgendamento = {
      dataExibicao: contexto.dados.dataExibicao,
      horario: contexto.dados.horario.horario,
      especialidade: contexto.dados.especialidade.nome
    };

    resetarContexto(usuarioId);

    if (resultado && resultado.sucesso) {
      return '🎉 *AGENDAMENTO CONFIRMADO!*\n\n' +
        `📅 ${dadosAgendamento.dataExibicao} às ${dadosAgendamento.horario}\n` +
        `🏥 ${dadosAgendamento.especialidade}\n\n` +
        '✅ Você receberá um lembrete 1 dia antes!\n\n' +
        '📞 Dúvidas: (84) 3316-2960';
    } else {
      return '❌ Erro ao criar agendamento.\n\n📞 Ligue: (84) 3316-2960\n\nOu digite *menu* para tentar novamente.';
    }
  } else if (/cancelar|não|nao/.test(msg)) {
    resetarContexto(usuarioId);
    return '❌ Agendamento cancelado.\n\nDigite *menu* para voltar ao início.';
  } else {
    return '❌ Resposta inválida.\n\nDigite *CONFIRMAR* ou *CANCELAR*';
  }
}

async function processarConsultaAgendamentos(usuarioId, msg) {
  const cpf = msg.replace(/[^0-9]/g, '');

  if (cpf.length === 11) {
    const paciente = await api.buscarPaciente(cpf);

    if (paciente && paciente.id) {
      const agendamentos = await api.consultarAgendamentosPaciente(paciente.id);

      resetarContexto(usuarioId);

      if (agendamentos && agendamentos.length > 0) {
        let resposta = '📋 *SEUS AGENDAMENTOS*\n\n';
        agendamentos.slice(0, 5).forEach((ag, i) => {
          resposta += `${i + 1}. ${ag.data_formatada} - ${ag.horario}\n`;
          resposta += `   ${ag.especialidade || 'Consulta'}\n`;
          resposta += `   Status: ${ag.status}\n\n`;
        });
        return resposta;
      } else {
        return '📋 Você não possui agendamentos.\n\nDigite *agendar* para marcar uma consulta!';
      }
    } else {
      resetarContexto(usuarioId);
      return '❌ Paciente não encontrado.\n\nVerifique o CPF ou digite *menu*';
    }
  } else {
    return '❌ CPF inválido.\n\nInforme um CPF válido (11 dígitos)';
  }
}

function menuPrincipal() {
  return 'Olá! 👋\n\n' +
    'Bem-vindo(a) à *Clínica Oitava Rosado*!\n\n' +
    'Escolha uma opção:\n\n' +
    '📅 *AGENDAR* - Marcar consulta\n' +
    '📋 *MEUS AGENDAMENTOS* - Ver suas consultas\n' +
    '💰 *PREÇOS* - Consultar valores\n' +
    '⏰ *HORÁRIOS* - Funcionamento\n' +
    '📍 *LOCALIZAÇÃO* - Onde ficamos\n\n' +
    '💬 Digite a opção desejada';
}

module.exports = {
  processarMensagem,
  resetarContexto
};
