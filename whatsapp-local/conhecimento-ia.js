const fs = require('fs');
const path = require('path');

const ARQUIVO_CONHECIMENTO = '/opt/whatsapp-web-js/conhecimento.json';

// Estrutura inicial de conhecimento
const CONHECIMENTO_INICIAL = {
  informacoes_clinica: {
    nome: "Clínica Oitava Rosado",
    endereco: "Mossoró/RN",
    telefone: "(84) 3316-2960",
    whatsapp: "(84) 98818-6138",
    horario: "Segunda a Sexta: 7h às 18h, Sábado: 7h às 12h",
    email: "contato@clinicaoitavarosado.com.br"
  },
  especialidades: [
    { nome: "Cardiologia", descricao: "Cuidados com o coração" },
    { nome: "Endocrinologia", descricao: "Tratamento de diabetes e hormônios" },
    { nome: "Ginecologia", descricao: "Saúde da mulher" },
    { nome: "Ortopedia", descricao: "Ossos e articulações" },
    { nome: "Clínica Geral", descricao: "Atendimento geral" }
  ],
  convenios: [
    { nome: "SUS", tipo: "público" },
    { nome: "Unimed", tipo: "privado" },
    { nome: "Amil", tipo: "privado" },
    { nome: "Bradesco Saúde", tipo: "privado" },
    { nome: "Particular", tipo: "particular" }
  ],
  perguntas_frequentes: [
    {
      pergunta: "Como faço para agendar uma consulta?",
      resposta: "Para agendamento, informe a especialidade desejada que consultarei os horários disponíveis. Ou ligue: (84) 3315-6900"
    },
    {
      pergunta: "Quais convênios vocês aceitam?",
      resposta: "Aceitamos: SUS, Unimed, Amil, Bradesco Saúde e particular. Para confirmar seu plano específico, consulte pelo telefone."
    },
    {
      pergunta: "Qual o horário de funcionamento?",
      resposta: "Segunda a Sexta: 06:00 às 17:48 | Sábado: 07:00 às 11:00 | Domingo: Fechado"
    },
    {
      pergunta: "Preciso levar algo na consulta?",
      resposta: "Documentos necessários: RG ou CNH, carteirinha do convênio e exames anteriores. Chegue com 15 minutos de antecedência."
    },
    {
      pergunta: "Como faço para remarcar uma consulta?",
      resposta: "Para remarcar, entre em contato com antecedência mínima de 24h pelo WhatsApp ou telefone: (84) 3315-6900"
    },
    {
      pergunta: "Qual o endereço da clínica?",
      resposta: "Unidade principal: Rua Juvenal Lamartine, 119, Mossoró/RN. Temos outras unidades - informe sua cidade para detalhes."
    }
  ],
  procedimentos: [
    { nome: "Consulta Cardiologista", duracao: "30 minutos", preparo: "Nenhum preparo necessário" },
    { nome: "Eletrocardiograma", duracao: "15 minutos", preparo: "Nenhum preparo necessário" },
    { nome: "Ultrassom", duracao: "20-30 minutos", preparo: "Jejum de 6 horas para abdominal" }
  ],
  conhecimento_personalizado: []
};

// Inicializar arquivo de conhecimento
function inicializarConhecimento() {
  if (!fs.existsSync(ARQUIVO_CONHECIMENTO)) {
    fs.writeFileSync(
      ARQUIVO_CONHECIMENTO,
      JSON.stringify(CONHECIMENTO_INICIAL, null, 2),
      'utf8'
    );
    console.log('✅ Arquivo de conhecimento criado!');
  }
}

// Carregar conhecimento
function carregarConhecimento() {
  try {
    inicializarConhecimento();
    const dados = fs.readFileSync(ARQUIVO_CONHECIMENTO, 'utf8');
    return JSON.parse(dados);
  } catch (error) {
    console.log('❌ Erro ao carregar conhecimento:', error.message);
    return CONHECIMENTO_INICIAL;
  }
}

// Salvar conhecimento
function salvarConhecimento(conhecimento) {
  try {
    fs.writeFileSync(
      ARQUIVO_CONHECIMENTO,
      JSON.stringify(conhecimento, null, 2),
      'utf8'
    );
    return { sucesso: true };
  } catch (error) {
    console.log('❌ Erro ao salvar conhecimento:', error.message);
    return { sucesso: false, erro: error.message };
  }
}

// Adicionar pergunta frequente
function adicionarPerguntaFrequente(pergunta, resposta) {
  const conhecimento = carregarConhecimento();
  conhecimento.perguntas_frequentes.push({ pergunta, resposta });
  return salvarConhecimento(conhecimento);
}

// Adicionar conhecimento personalizado
function adicionarConhecimentoPersonalizado(topico, conteudo) {
  const conhecimento = carregarConhecimento();
  conhecimento.conhecimento_personalizado.push({
    id: Date.now(),
    topico,
    conteudo,
    data_criacao: new Date().toISOString()
  });
  return salvarConhecimento(conhecimento);
}

// Remover item de conhecimento
function removerConhecimento(tipo, id) {
  const conhecimento = carregarConhecimento();

  if (tipo === 'pergunta_frequente') {
    conhecimento.perguntas_frequentes = conhecimento.perguntas_frequentes.filter(
      (_, index) => index !== id
    );
  } else if (tipo === 'personalizado') {
    conhecimento.conhecimento_personalizado = conhecimento.conhecimento_personalizado.filter(
      item => item.id !== id
    );
  }

  return salvarConhecimento(conhecimento);
}

// Gerar prompt de contexto para o agente IA
function gerarContextoIA() {
  const conhecimento = carregarConhecimento();

  let contexto = `Você é o assistente virtual da ${conhecimento.informacoes_clinica.nome}.

## INFORMAÇÕES DA CLÍNICA
- Endereço: ${conhecimento.informacoes_clinica.endereco}
- Telefone: ${conhecimento.informacoes_clinica.telefone}
- WhatsApp: ${conhecimento.informacoes_clinica.whatsapp}
- Horário: ${conhecimento.informacoes_clinica.horario}
${conhecimento.informacoes_clinica.email ? `- Email: ${conhecimento.informacoes_clinica.email}` : ''}

## ESPECIALIDADES DISPONÍVEIS
${conhecimento.especialidades.map(esp => `- ${esp.nome}: ${esp.descricao}`).join('\n')}

## CONVÊNIOS ACEITOS
${conhecimento.convenios.map(conv => `- ${conv.nome}`).join('\n')}

## PROCEDIMENTOS
${conhecimento.procedimentos.map(proc =>
  `- ${proc.nome} (${proc.duracao})${proc.preparo ? ` - Preparo: ${proc.preparo}` : ''}`
).join('\n')}

## PERGUNTAS FREQUENTES
${conhecimento.perguntas_frequentes.map((pf, i) =>
  `${i + 1}. ${pf.pergunta}\n   Resposta: ${pf.resposta}`
).join('\n\n')}
`;

  if (conhecimento.conhecimento_personalizado.length > 0) {
    contexto += `\n\n## CONHECIMENTO ADICIONAL\n`;
    conhecimento.conhecimento_personalizado.forEach(cp => {
      contexto += `\n### ${cp.topico}\n${cp.conteudo}\n`;
    });
  }

  contexto += `\n\n## REGRAS DE COMPORTAMENTO - CRÍTICO

**TOM E POSTURA:**
- Seja PROFISSIONAL, objetivo e direto
- Use emojis com MODERAÇÃO (máximo 2 por mensagem)
- Não seja excessivamente informal ou casual
- Mantenha postura de atendimento profissional de clínica

**O QUE VOCÊ PODE FAZER:**
1. Informar sobre unidades, horários, especialidades, convênios
2. Consultar médicos disponíveis
3. Orientar sobre agendamento
4. Responder perguntas sobre procedimentos e preparos
5. Informar contatos e endereços

**O QUE VOCÊ NÃO PODE FAZER:**
1. NUNCA invente informações médicas ou diagnósticos
2. NUNCA dê conselhos de saúde ou orientações clínicas
3. NUNCA invente médicos, especialidades ou procedimentos
4. NUNCA fale sobre tratamentos específicos
5. NUNCA responda perguntas fora do escopo da clínica

**LIMITES CLAROS:**
- Se perguntarem sobre sintomas/doenças: "Para orientação médica, é necessário consulta. Posso ajudar a agendar?"
- Se não souber: "Para essa informação específica, favor ligar: ${conhecimento.informacoes_clinica.telefone}"
- Se for fora do escopo: "Atendo apenas questões sobre agendamentos e informações da clínica"

**FORMATO DE RESPOSTAS:**
- Máximo 4-5 linhas por resposta
- Seja CONCISO e DIRETO
- Liste informações de forma clara
- Sempre ofereça próximo passo (agendar, ligar, etc.)
- **NUNCA use asteriscos (*) ou underlines (_)** para formatar
- Use apenas texto simples e bullet points (•)

**FLUXO DE AGENDAMENTO:**
- MOSTRAR opções disponíveis (datas, horários, convênios)
- NUNCA pedir ao paciente para escolher data/hora sem mostrar opções
- Exemplo: "Datas disponíveis: 15/10, 17/10, 20/10" ✅
- Nunca: "Qual data você gostaria?" ❌`;

  return contexto;
}

// Atualizar informação específica
function atualizarInformacao(categoria, campo, valor) {
  const conhecimento = carregarConhecimento();

  if (categoria === 'informacoes_clinica' && conhecimento.informacoes_clinica[campo] !== undefined) {
    conhecimento.informacoes_clinica[campo] = valor;
    return salvarConhecimento(conhecimento);
  }

  return { sucesso: false, erro: 'Categoria ou campo inválido' };
}

// Adicionar especialidade
function adicionarEspecialidade(nome, descricao) {
  const conhecimento = carregarConhecimento();
  conhecimento.especialidades.push({ nome, descricao });
  return salvarConhecimento(conhecimento);
}

// Adicionar convênio
function adicionarConvenio(nome, tipo) {
  const conhecimento = carregarConhecimento();
  conhecimento.convenios.push({ nome, tipo });
  return salvarConhecimento(conhecimento);
}

// Adicionar procedimento
function adicionarProcedimento(nome, duracao, preparo) {
  const conhecimento = carregarConhecimento();
  conhecimento.procedimentos.push({ nome, duracao, preparo });
  return salvarConhecimento(conhecimento);
}

module.exports = {
  inicializarConhecimento,
  carregarConhecimento,
  salvarConhecimento,
  adicionarPerguntaFrequente,
  adicionarConhecimentoPersonalizado,
  removerConhecimento,
  gerarContextoIA,
  atualizarInformacao,
  adicionarEspecialidade,
  adicionarConvenio,
  adicionarProcedimento
};
