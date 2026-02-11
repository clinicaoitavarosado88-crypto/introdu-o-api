const axios = require('axios');
const conhecimento = require('./conhecimento-ia');
const apiAgenda = require('./api-agenda-completa');

const CONFIG = {
  DO_AGENT_URL: 'https://luvswa5jnjcjhczbiiafhart.agents.do-ai.run/api/v1/chat/completions',
  DO_API_KEY: 'y1FQFR3t_S5i_NNV_nYDoeU_me9uA3l2',
  timeout: 30000
};

// Histórico de conversas por usuário
const historicoConversas = {};

// ========================================
// DETECÇÃO DE INTENÇÃO E CHAMADA DE API
// ========================================

async function detectarIntencaoEBuscarDados(mensagem) {
  const msg = mensagem.toLowerCase();

  console.log('🔍 Analisando intenção da mensagem...');

  // MÉDICOS POR ESPECIALIDADE (deve vir ANTES de consultar especialidades)
  const padroesMedicos = [
    /m[eé]dicos?\s+(de|que\s+fazem|especializados?\s+em|para)\s+(\w+)/i,
    /quais?\s+m[eé]dicos?\s+.*?(ginecolog|cardiolog|ortoped|endocrin|dermat|pediatr|urolog|neurolog)/i,
    /tem\s+m[eé]dico\s+(de|para)\s+(\w+)/i,
    /(ginecolog|cardiolog|ortoped|endocrin|dermat|pediatr|urolog|neurolog).*?m[eé]dicos?/i
  ];

  for (const padrao of padroesMedicos) {
    const match = mensagem.match(padrao);
    if (match) {
      console.log('✅ Intenção detectada: MÉDICOS POR ESPECIALIDADE');

      // Extrair especialidade da mensagem
      let especialidade = '';
      if (msg.includes('ginecolog')) especialidade = 'Ginecologista';
      else if (msg.includes('cardiolog')) especialidade = 'Cardiologista';
      else if (msg.includes('ortoped')) especialidade = 'Ortopedista';
      else if (msg.includes('endocrin')) especialidade = 'Endocrinologista';
      else if (msg.includes('dermat')) especialidade = 'Dermatologista';
      else if (msg.includes('pediatr')) especialidade = 'Pediatra';
      else if (msg.includes('urolog')) especialidade = 'Urologista';
      else if (msg.includes('neurolog')) especialidade = 'Neurologista';
      else if (msg.includes('oftalmolog')) especialidade = 'Oftalmologista';
      else if (msg.includes('psicolog')) especialidade = 'Psicologo';
      else if (msg.includes('psiquiatr')) especialidade = 'Psiquiatra';
      else if (msg.includes('nutri')) especialidade = 'Nutricionista';

      if (especialidade) {
        console.log('📋 Especialidade identificada:', especialidade);
        const resultado = await apiAgenda.listarAgendasJSON('consulta', especialidade);

        if (resultado.sucesso && resultado.dados && resultado.dados.agendas) {
          const agendas = resultado.dados.agendas;
          console.log('📊 Dados obtidos:', agendas.length, 'agendas de', especialidade);

          // Extrair lista única de médicos
          const medicos = [];
          const medicosUnicos = new Set();

          agendas.forEach(agenda => {
            if (agenda.medico && agenda.medico.nome) {
              const medicoNome = agenda.medico.nome;
              if (!medicosUnicos.has(medicoNome)) {
                medicosUnicos.add(medicoNome);
                medicos.push({
                  id: agenda.medico.id,
                  nome: medicoNome,
                  especialidade: especialidade,
                  unidade: agenda.localizacao?.unidade_nome || 'Não especificado',
                  telefone: agenda.localizacao?.telefone || '',
                  horarios: agenda.horarios_por_dia || {},
                  convenios: agenda.convenios || []
                });
              }
            }
          });

          return {
            tipo: 'medicos_especialidade',
            dados: medicos,
            especialidade: especialidade,
            contexto: `Médicos REAIS de ${especialidade}:\n${JSON.stringify(medicos, null, 2)}\n\n**IMPORTANTE: Estes são os médicos REAIS cadastrados no sistema. Use EXATAMENTE estes nomes. NÃO INVENTE médicos!**`
          };
        }
      }
    }
  }

  // CONSULTAR EXAMES/PROCEDIMENTOS
  const examesComuns = [
    'ressonancia', 'ressonância', 'ultrassom', 'ultrasom', 'raio-x', 'raio x', 'radiografia',
    'tomografia', 'eletrocardiograma', 'ecocardiograma', 'mamografia', 'densitometria',
    'endoscopia', 'colonoscopia', 'exame de sangue', 'hemograma', 'urina',
    'doppler', 'holter', 'mapa', 'espirometria', 'eletroencefalograma'
  ];

  // Verificar se mencionou algum exame específico
  const exameDetectado = examesComuns.find(exame => msg.includes(exame));

  if (exameDetectado || msg.includes('exame') || msg.includes('procedimento')) {
    console.log('✅ Intenção detectada: CONSULTAR EXAMES/PROCEDIMENTOS');

    // Mapear termos populares para nomes formais da API
    // IMPORTANTE: Usar os nomes EXATOS que estão cadastrados no banco
    let termoProcedimento = exameDetectado || '';

    if (msg.includes('ressonancia') || msg.includes('ressonância')) termoProcedimento = 'Ressonância Magnética';
    else if (msg.includes('ultrassom') || msg.includes('ultrasom')) termoProcedimento = 'Ultrassonografia';
    else if (msg.includes('raio-x') || msg.includes('raio x') || msg.includes('radiografia')) termoProcedimento = 'Raio-X';
    else if (msg.includes('tomografia')) termoProcedimento = 'Tomografia';
    else if (msg.includes('eletrocardiograma') || msg.includes('eletro')) termoProcedimento = 'Eletrocardiograma';
    else if (msg.includes('ecocardiograma')) termoProcedimento = 'Ecocardiograma';
    else if (msg.includes('mamografia')) termoProcedimento = 'Mamografia';
    else if (msg.includes('endoscopia')) termoProcedimento = 'Endoscopia';
    else if (msg.includes('colonoscopia')) termoProcedimento = 'Colonoscopia';
    else if (msg.includes('doppler')) termoProcedimento = 'Doppler';
    else if (msg.includes('holter')) termoProcedimento = 'Holter';
    else if (msg.includes('densitometria')) termoProcedimento = 'Densitometria';

    console.log('🔬 Procedimento identificado:', termoProcedimento);

    // Buscar agendas do tipo 'procedimento' (conforme documentação API)
    const resultado = await apiAgenda.listarAgendasJSON('procedimento', termoProcedimento);

    if (resultado.sucesso && resultado.dados && resultado.dados.agendas) {
      const agendas = resultado.dados.agendas;
      console.log('📊 Dados obtidos:', agendas.length, 'agendas para', termoProcedimento);

      // Estruturar informações dos procedimentos conforme estrutura da API
      const procedimentosDisponiveis = agendas.map(agenda => ({
        id: agenda.id,
        procedimento: agenda.procedimento?.nome || agenda.nome || termoProcedimento,
        medico: agenda.medico?.nome || 'Não especificado',
        unidade: agenda.localizacao?.unidade_nome || 'Não especificado',
        cidade: agenda.localizacao?.cidade || '',
        sala: agenda.localizacao?.sala || '',
        telefone: agenda.localizacao?.telefone || '(84) 3315-6900',
        convenios: agenda.convenios || [],
        horarios_disponiveis: agenda.horarios_por_dia || {},
        tempo_estimado: agenda.configuracoes?.tempo_estimado_minutos || null,
        observacoes: agenda.avisos?.observacoes || '',
        orientacoes: agenda.avisos?.orientacoes || ''
      }));

      return {
        tipo: 'procedimentos_exames',
        dados: procedimentosDisponiveis,
        procedimento_solicitado: termoProcedimento,
        contexto: `Procedimentos/Exames REAIS disponíveis para "${termoProcedimento}":\n${JSON.stringify(procedimentosDisponiveis, null, 2)}\n\n**IMPORTANTE: Estas são as agendas REAIS cadastradas no sistema. Use EXATAMENTE estas informações. NÃO INVENTE locais, médicos ou horários!**\n\n**FORMATO DE RESPOSTA:**\n"${termoProcedimento} disponível em:\n\n[Liste APENAS as unidades reais retornadas]\n\nPara agendar, ligue: [telefone da unidade]"`
      };
    } else {
      // Se não encontrou agendas específicas
      console.log('⚠️ Nenhuma agenda encontrada para:', termoProcedimento);
      return {
        tipo: 'procedimento_nao_encontrado',
        procedimento_solicitado: termoProcedimento,
        contexto: `O paciente solicitou informações sobre "${termoProcedimento}". Não foram encontradas agendas disponíveis no momento.\n\n**RESPONDA EXATAMENTE:**\n"Para verificar disponibilidade de ${termoProcedimento}, favor ligar: (84) 3315-6900"`
      };
    }
  }

  // CONSULTAR UNIDADES
  if (msg.includes('unidade') || msg.includes('unidades') || msg.includes('local') || msg.includes('locais')) {
    console.log('✅ Intenção detectada: CONSULTAR UNIDADES');
    const resultado = await apiAgenda.consultarUnidades();
    if (resultado.sucesso && resultado.dados) {
      console.log('📊 Dados obtidos:', resultado.dados);
      return {
        tipo: 'unidades',
        dados: resultado.dados,
        contexto: `Unidades disponíveis na clínica:\n${JSON.stringify(resultado.dados, null, 2)}`
      };
    }
  }

  // CONSULTAR ESPECIALIDADES
  if (msg.includes('especialidade') || msg.includes('especialidades') || msg.includes('médico') || msg.includes('medico') || msg.includes('doutor') || msg.includes('doutora')) {
    console.log('✅ Intenção detectada: CONSULTAR ESPECIALIDADES');
    const resultado = await apiAgenda.buscarEspecialidades('');
    if (resultado.sucesso && resultado.dados) {
      console.log('📊 Dados obtidos:', resultado.dados.length, 'especialidades');
      return {
        tipo: 'especialidades',
        dados: resultado.dados,
        contexto: `Especialidades disponíveis:\n${JSON.stringify(resultado.dados, null, 2)}`
      };
    }
  }

  // CONSULTAR CONVÊNIOS
  if (msg.includes('convenio') || msg.includes('convênio') || msg.includes('plano') || msg.includes('seguro')) {
    console.log('✅ Intenção detectada: CONSULTAR CONVÊNIOS');
    const resultado = await apiAgenda.buscarConvenios('');
    if (resultado.sucesso && resultado.dados) {
      console.log('📊 Dados obtidos:', resultado.dados.length, 'convênios');
      return {
        tipo: 'convenios',
        dados: resultado.dados,
        contexto: `Convênios aceitos:\n${JSON.stringify(resultado.dados, null, 2)}`
      };
    }
  }

  // CONSULTAR PREÇOS
  if (msg.includes('preço') || msg.includes('preco') || msg.includes('valor') || msg.includes('quanto custa') || msg.includes('quanto é')) {
    console.log('✅ Intenção detectada: CONSULTAR PREÇOS');
    const resultado = await apiAgenda.consultarPrecos({});
    if (resultado.sucesso && resultado.dados) {
      console.log('📊 Dados obtidos de preços');
      return {
        tipo: 'precos',
        dados: resultado.dados,
        contexto: `Tabela de preços:\n${JSON.stringify(resultado.dados, null, 2)}`
      };
    }
  }

  // CONSULTAR AGENDAS/HORÁRIOS
  if (msg.includes('horário') || msg.includes('horario') || msg.includes('agenda') || msg.includes('disponível') || msg.includes('disponivel') || msg.includes('vaga')) {
    console.log('✅ Intenção detectada: CONSULTAR AGENDAS');
    const resultado = await apiAgenda.listarAgendasJSON('consulta', '');
    if (resultado.sucesso && resultado.dados) {
      console.log('📊 Dados obtidos:', Array.isArray(resultado.dados) ? resultado.dados.length : 'objeto', 'agendas');
      return {
        tipo: 'agendas',
        dados: resultado.dados,
        contexto: `Agendas disponíveis:\n${JSON.stringify(resultado.dados, null, 2)}`
      };
    }
  }

  // HORÁRIOS DE MÉDICO ESPECÍFICO
  // Detectar se usuário está escolhendo um médico para ver horários
  const medicosComuns = ['hugo brasil', 'edna patricia', 'jailson', 'valeria luara', 'isabela maria', 'leonardo da vinci'];
  const mensionouMedico = medicosComuns.some(medico => msg.includes(medico));

  if (mensionouMedico && (msg.includes('horário') || msg.includes('horario') || msg.includes('data') || msg.includes('disponível') || msg.includes('disponivel') || msg.includes('agendar'))) {
    console.log('✅ Intenção detectada: CONSULTAR HORÁRIOS DE MÉDICO ESPECÍFICO');
    return {
      tipo: 'consultar_horarios_medico',
      contexto: 'O usuário quer ver horários de um médico específico. Você deve: 1) Identificar o nome do médico mencionado 2) Dizer que precisa consultar a agenda real 3) Pedir para ligar (84) 3315-6900 para confirmar horários em tempo real'
    };
  }

  // BUSCAR PACIENTE
  if (msg.includes('meu agendamento') || msg.includes('minha consulta') || msg.includes('meu cpf') || msg.includes('meus dados')) {
    console.log('✅ Intenção detectada: CONSULTA DE PACIENTE');
    // Não buscar sem CPF - apenas informar que precisa do CPF
    return {
      tipo: 'precisa_cpf',
      contexto: 'O usuário quer consultar seus agendamentos. Peça o CPF para buscar.'
    };
  }

  console.log('ℹ️  Nenhuma intenção de API detectada - resposta geral');
  return null;
}

// ========================================
// GERAR CONTEXTO ENRIQUECIDO
// ========================================

function gerarContextoEnriquecido(dadosAPI = null) {
  // Contexto base do conhecimento
  const contextoBase = conhecimento.gerarContextoIA();

  // Capacidades da API
  const capacidadesAPI = `

## 🤖 CAPACIDADES COM API INTEGRADA

Você tem acesso DIRETO ao sistema da clínica através de APIs. Quando o usuário perguntar sobre:

✅ **UNIDADES**: Liste as unidades reais retornadas pela API
✅ **ESPECIALIDADES**: Liste as especialidades reais do sistema
✅ **CONVÊNIOS**: Informe os convênios que realmente aceitamos
✅ **EXAMES/PROCEDIMENTOS**: Consulte agendas reais que realizam o exame solicitado
✅ **PREÇOS**: Consulte valores reais do sistema
✅ **HORÁRIOS**: Verifique disponibilidade real de agendas
✅ **AGENDAMENTOS**: Busque dados reais de pacientes (com CPF)

**IMPORTANTE - REGRAS CRÍTICAS - PROIBIÇÕES ABSOLUTAS:**

🚫 **PROIBIDO INVENTAR:**
1. NUNCA invente experiência de médicos (anos, formação, etc.)
2. NUNCA invente procedimentos que médicos fazem
3. NUNCA invente datas disponíveis
4. NUNCA invente horários
5. NUNCA fale sobre procedimentos médicos (cirurgias, tratamentos)
6. NUNCA crie biografias de médicos
7. NUNCA use asteriscos (*) ou underlines (_) para formatar

✅ **O QUE VOCÊ PODE FAZER:**
1. Listar APENAS nomes de médicos que vieram da API
2. Informar APENAS unidade onde atendem (se vier da API)
3. Se não tiver dados da API: "Consulte pelo telefone: (84) 3315-6900"

🎯 **RESPOSTA CORRETA PARA MÉDICOS:**
"Médicos de Ginecologia disponíveis:

• EDNA PATRICIA DIAS ALVES - Parnamirim
• JAILSON R. NOGUEIRA FILHO - Mossoró
• HUGO BRASIL - Mossoró

Para verificar horários disponíveis, informe qual médico prefere."

🎯 **RESPOSTA CORRETA PARA EXAMES:**
"Ressonância disponível em:

• Mossoró - Unidade Centro
• Parnamirim - Unidade Shopping
Telefone: (84) 3315-6900

Qual cidade prefere?"

❌ **NUNCA FAÇA ISSO:**
"Dr. Hugo Brasil: Com mais de 10 anos de experiência..."
"Especializado em histerectomia..."
"Datas disponíveis: 15/10, 17/10..."
"O exame demora 30 minutos..." (se não vier da API)

## 📋 FORMATO DE RESPOSTA - PROFISSIONAL

**ESTILO PROFISSIONAL - LIMITE CRÍTICO:**
- Tom formal mas acessível
- **MÁXIMO 5 LINHAS POR RESPOSTA** (suas respostas estão sendo cortadas!)
- Máximo 2 emojis por mensagem
- **NUNCA use asteriscos (*) para negrito** - use texto simples
- **NUNCA formate com markdown** no WhatsApp

⚠️ **PROBLEMA CRÍTICO - RESPOSTAS CORTADAS:**
WhatsApp e APIs têm limite de caracteres. Se você escrever muito, a mensagem será CORTADA NO MEIO!

**QUANDO LISTAR MUITOS ITENS:**
❌ ERRADO: Listar todas as 11 unidades completas (mensagem cortada!)
✅ CORRETO: "Principais unidades: Mossoró, Parnamirim, Assú. (+ 8 outras). Qual cidade prefere?"

**SEMPRE:**
- Se tiver mais de 5 itens: liste só 3-4 e diga "(+ X outros)"
- Seja EXTREMAMENTE conciso
- Nunca escreva mais de 5 linhas

**ESTRUTURA:**
1. Confirmação breve (sem "Claro!", "Com certeza!")
2. Informação DIRETA e REAL da API
3. Pergunta objetiva para próximo passo

**EXEMPLO DE RESPOSTA CORRETA:**

Pergunta: "Quais médicos de ginecologia?"

Resposta CORRETA (SIMPLES E SEM INVENTAR):
"Médicos de Ginecologia:

• EDNA PATRICIA DIAS ALVES - Parnamirim
• JAILSON R. NOGUEIRA FILHO - Mossoró
• HUGO BRASIL - Mossoró
• VALERIA LUARA GADELHA - Parnamirim
• (+ 3 outros)

Qual médico prefere?"

❌❌❌ RESPOSTA ERRADA (NUNCA FAZER):
"Dr. Hugo Brasil: Com mais de 10 anos de experiência em ginecologia, especializado em histerectomia, tratamento de infertilidade e menopausa."

🚫 PROIBIDO: Inventar experiência, especialidades, procedimentos!

**EVITE:**
❌ Textos longos com muitos detalhes
❌ Múltiplos emojis (👨‍⚕️ 😊 🏥 💉)
❌ Linguagem muito casual ("Oi!", "Tudo bem?", "Vamos lá!")
❌ Informações que não vieram da API

**USE:**
✅ Bullet points simples (•)
✅ Nomes EXATOS da API
✅ Respostas diretas e curtas
✅ Tom profissional cordial

## 🗓️ FLUXO CORRETO DE AGENDAMENTO

**IMPORTANTE - O BOT DEVE MOSTRAR OPÇÕES, NÃO PEDIR!**

**ERRADO** ❌:
"Qual data você gostaria de marcar?"
"Qual hora você prefere?"
"Você tem convênio?"

**CORRETO** ✅:
"Datas disponíveis com Dr. [Nome]:
• 15/10 (Segunda)
• 17/10 (Quarta)
• 20/10 (Sexta)
Qual data prefere?"

**FLUXO COMPLETO:**

1. Paciente quer agendar → Mostrar especialidades
2. Paciente escolhe especialidade → Mostrar médicos disponíveis
3. Paciente escolhe médico → **MOSTRAR datas disponíveis** (consultar API)
4. Paciente escolhe data → **MOSTRAR horários disponíveis** naquela data
5. Paciente escolhe horário → **MOSTRAR convênios aceitos + valores**
6. Paciente escolhe convênio → Coletar dados (nome, CPF, telefone)
7. Confirmar agendamento

**EXEMPLO PRÁTICO:**

Usuário: "Quero agendar com Dr. Hugo Brasil"

Bot resposta:
"Dr. HUGO BRASIL - Ginecologia

Datas disponíveis:
• 15/10 (Seg) 14:00-16:00
• 17/10 (Qua) 08:00-11:00

Convênios aceitos: Particular, Amil
Valor: R$ 150,00 (consulta) / R$ 80,00 (retorno)

Qual data e horário prefere?"

**SEM ASTERISCOS! SEM NEGRITO! TEXTO SIMPLES!**
`;

  let contextoCompleto = contextoBase + capacidadesAPI;

  // Se houver dados de API, adicionar ao contexto
  if (dadosAPI && dadosAPI.contexto) {
    contextoCompleto += `\n\n## 📊 DADOS ATUALIZADOS DA API\n\n${dadosAPI.contexto}\n\n**USE ESTES DADOS REAIS para responder a pergunta do usuário!**\n`;
  }

  return contextoCompleto;
}

// ========================================
// VALIDAR E AJUSTAR TAMANHO DA RESPOSTA
// ========================================

function validarEAjustarTamanhoResposta(resposta, perguntaOriginal) {
  // Remover asteriscos e formatação markdown
  let respostaLimpa = resposta
    .replace(/\*\*/g, '')  // Remove negrito markdown
    .replace(/\*/g, '')     // Remove asteriscos
    .replace(/__/g, '')     // Remove underline markdown
    .replace(/_/g, '')      // Remove underlines
    .trim();

  // Contar linhas para log
  const linhas = respostaLimpa.split('\n').filter(l => l.trim().length > 0);
  const numLinhas = linhas.length;
  const numCaracteres = respostaLimpa.length;

  console.log(`📏 Validação: ${numLinhas} linhas, ${numCaracteres} caracteres`);

  // Apenas remove asteriscos - NÃO RESUME MAIS!
  // O usuário quer ver tudo listado completo

  if (respostaLimpa.includes('*')) {
    console.log('⚠️ Removendo asteriscos remanescentes...');
    respostaLimpa = respostaLimpa.replace(/\*/g, '');
  }

  return respostaLimpa;
}

// ========================================
// CONSULTAR AGENTE COM IA
// ========================================

async function consultarAgente(usuarioId, mensagem) {
  try {
    // Inicializar histórico do usuário se não existir
    if (!historicoConversas[usuarioId]) {
      historicoConversas[usuarioId] = [];
    }

    // 1. DETECTAR INTENÇÃO E BUSCAR DADOS DA API
    const dadosAPI = await detectarIntencaoEBuscarDados(mensagem);

    // 2. GERAR CONTEXTO (com ou sem dados da API)
    const contextoEnriquecido = gerarContextoEnriquecido(dadosAPI);

    // 3. MONTAR HISTÓRICO DE MENSAGENS
    const mensagens = [];

    // Sistema com contexto enriquecido (sempre no início)
    mensagens.push({
      role: 'system',
      content: contextoEnriquecido
    });

    // Adicionar histórico anterior (últimas 10 mensagens)
    const historicoRecente = historicoConversas[usuarioId].slice(-10);
    mensagens.push(...historicoRecente);

    // Adicionar mensagem atual do usuário
    mensagens.push({
      role: 'user',
      content: mensagem
    });

    console.log('🤖 Enviando para agente IA...');
    console.log('📊 Total de mensagens:', mensagens.length);
    if (dadosAPI) {
      console.log('✅ Incluindo dados da API:', dadosAPI.tipo);
    }

    // 4. CHAMAR AGENTE DIGITAL OCEAN
    const response = await axios.post(
      CONFIG.DO_AGENT_URL,
      {
        messages: mensagens,
        stream: false
      },
      {
        headers: {
          'Authorization': `Bearer ${CONFIG.DO_API_KEY}`,
          'Content-Type': 'application/json'
        },
        timeout: CONFIG.timeout
      }
    );

    let resposta = response.data.choices[0].message.content;

    console.log('✅ Resposta recebida do agente!');

    // VALIDAÇÃO DE TAMANHO - CRÍTICA!
    resposta = validarEAjustarTamanhoResposta(resposta, mensagem);

    // 5. SALVAR NO HISTÓRICO
    historicoConversas[usuarioId].push({
      role: 'user',
      content: mensagem
    });

    historicoConversas[usuarioId].push({
      role: 'assistant',
      content: resposta
    });

    // Limitar histórico a 20 mensagens (10 trocas)
    if (historicoConversas[usuarioId].length > 20) {
      historicoConversas[usuarioId] = historicoConversas[usuarioId].slice(-20);
    }

    return {
      sucesso: true,
      resposta: resposta,
      usouAPI: dadosAPI !== null,
      tipoAPI: dadosAPI ? dadosAPI.tipo : null
    };

  } catch (error) {
    console.log('❌ Erro ao consultar agente:', error.message);

    return {
      sucesso: false,
      erro: error.message,
      resposta: 'Desculpe, estou com dificuldades técnicas no momento. Tente novamente em alguns instantes ou ligue: (84) 3316-2960'
    };
  }
}

// ========================================
// FUNÇÕES AUXILIARES
// ========================================

function limparHistorico(usuarioId) {
  if (historicoConversas[usuarioId]) {
    delete historicoConversas[usuarioId];
    console.log(`🗑️  Histórico limpo para usuário: ${usuarioId}`);
  }
}

function obterHistorico(usuarioId) {
  return historicoConversas[usuarioId] || [];
}

module.exports = {
  consultarAgente,
  limparHistorico,
  obterHistorico
};
