const axios = require('axios');

const API_BASE = 'http://sistema.clinicaoitavarosado.com.br/oitava/agenda';

// Buscar agendamentos para lembretes (amanhã)
async function buscarAgendamentosParaLembrete() {
  try {
    const hoje = new Date();
    const amanha = new Date(hoje);
    amanha.setDate(hoje.getDate() + 1);

    const dia = amanha.getDate().toString().padStart(2, '0');
    const mes = (amanha.getMonth() + 1).toString().padStart(2, '0');
    const ano = amanha.getFullYear();
    const dataAmanha = `${ano}-${mes}-${dia}`;

    // Buscar todas as agendas
    const response = await axios.get(`${API_BASE}/listar_agendas_json.php`, { timeout: 5000 });
    const agendas = response.data;

    const agendamentosParaLembrete = [];

    // Para cada agenda, buscar agendamentos de amanhã
    for (const agenda of agendas) {
      try {
        const respAgendamentos = await axios.get(
          `${API_BASE}/buscar_agendamentos_dia.php?agenda_id=${agenda.id}&data=${dataAmanha}`,
          { timeout: 5000 }
        );

        if (respAgendamentos.data && Array.isArray(respAgendamentos.data)) {
          respAgendamentos.data.forEach(ag => {
            if (ag.paciente_telefone && ag.status !== 'CANCELADO') {
              agendamentosParaLembrete.push({
                id: ag.id,
                paciente: ag.paciente_nome,
                telefone: ag.paciente_telefone,
                data: `${dia}/${mes}/${ano}`,
                horario: ag.horario,
                especialidade: agenda.especialidade || 'Consulta',
                medico: agenda.medico || ''
              });
            }
          });
        }
      } catch (error) {
        console.log(`Erro ao buscar agendamentos da agenda ${agenda.id}:`, error.message);
      }
    }

    return agendamentosParaLembrete;
  } catch (error) {
    console.log('❌ Erro ao buscar agendamentos para lembrete:', error.message);
    return [];
  }
}

// Formatar telefone para WhatsApp (adicionar código do país)
function formatarTelefoneWhatsApp(telefone) {
  // Remover caracteres não numéricos
  const numeros = telefone.replace(/\D/g, '');

  // Se já tem código do país, retornar
  if (numeros.length >= 12) {
    return `${numeros}@c.us`;
  }

  // Se tem 11 dígitos (celular com DDD)
  if (numeros.length === 11) {
    return `55${numeros}@c.us`;
  }

  // Se tem 10 dígitos (fixo com DDD)
  if (numeros.length === 10) {
    return `55${numeros}@c.us`;
  }

  // Se tem 9 dígitos (celular sem DDD)
  if (numeros.length === 9) {
    return `5584${numeros}@c.us`; // Assume DDD 84 (Mossoró/RN)
  }

  // Se tem 8 dígitos (fixo sem DDD)
  if (numeros.length === 8) {
    return `5584${numeros}@c.us`; // Assume DDD 84
  }

  return null;
}

// Gerar mensagem de lembrete
function gerarMensagemLembrete(agendamento) {
  return `🏥 *Lembrete de Consulta*\n\n` +
    `Olá, ${agendamento.paciente}!\n\n` +
    `Este é um lembrete da sua consulta:\n\n` +
    `📅 Data: *${agendamento.data}*\n` +
    `⏰ Horário: *${agendamento.horario}*\n` +
    `🏥 ${agendamento.especialidade}\n` +
    (agendamento.medico ? `👨‍⚕️ ${agendamento.medico}\n\n` : '\n') +
    `📍 Clínica Oitava Rosado\n` +
    `Av. Exemplo, 123 - Centro, Mossoró/RN\n\n` +
    `⚠️ Chegue com 15 minutos de antecedência\n` +
    `📋 Traga documento e carteirinha\n\n` +
    `📞 Precisa remarcar? Ligue: (84) 3316-2960\n\n` +
    `_Mensagem automática - Não responda_`;
}

// Enviar lembretes via WhatsApp
async function enviarLembretes(clientWhatsApp) {
  try {
    console.log('📅 Iniciando envio de lembretes...');

    const agendamentos = await buscarAgendamentosParaLembrete();

    if (agendamentos.length === 0) {
      console.log('✅ Nenhum agendamento para lembrete hoje');
      return { enviados: 0, erros: 0 };
    }

    console.log(`📋 Encontrados ${agendamentos.length} agendamentos para lembrete`);

    let enviados = 0;
    let erros = 0;

    for (const ag of agendamentos) {
      try {
        const telefoneWhatsApp = formatarTelefoneWhatsApp(ag.telefone);

        if (!telefoneWhatsApp) {
          console.log(`⚠️ Telefone inválido para ${ag.paciente}: ${ag.telefone}`);
          erros++;
          continue;
        }

        const mensagem = gerarMensagemLembrete(ag);

        await clientWhatsApp.sendMessage(telefoneWhatsApp, mensagem);

        console.log(`✅ Lembrete enviado para ${ag.paciente} (${ag.telefone})`);
        enviados++;

        // Aguardar 2 segundos entre envios para não ser bloqueado
        await new Promise(resolve => setTimeout(resolve, 2000));

      } catch (error) {
        console.log(`❌ Erro ao enviar lembrete para ${ag.paciente}:`, error.message);
        erros++;
      }
    }

    console.log(`\n📊 Resumo: ${enviados} enviados, ${erros} erros`);

    return { enviados, erros };
  } catch (error) {
    console.log('❌ Erro geral ao enviar lembretes:', error.message);
    return { enviados: 0, erros: 0 };
  }
}

// Agendar envio diário de lembretes
function agendarLembretesAutomaticos(clientWhatsApp) {
  // Executar todos os dias às 18h
  const executarLembretes = async () => {
    const agora = new Date();
    const hora = agora.getHours();
    const minuto = agora.getMinutes();

    // Executar às 18:00
    if (hora === 18 && minuto === 0) {
      console.log('🕐 Hora de enviar lembretes!');
      await enviarLembretes(clientWhatsApp);
    }
  };

  // Verificar a cada minuto
  setInterval(executarLembretes, 60000);

  console.log('⏰ Lembretes automáticos agendados para 18:00 diariamente');
}

module.exports = {
  buscarAgendamentosParaLembrete,
  enviarLembretes,
  agendarLembretesAutomaticos
};
