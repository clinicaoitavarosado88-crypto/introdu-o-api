const express = require('express');
const conhecimento = require('./conhecimento-ia');
const agenteIA = require('./agente-ia');

const app = express();
const PORT = 3003;

// Middlewares
app.use(express.json());
app.use(express.static(__dirname));

// CORS
app.use((req, res, next) => {
  res.header('Access-Control-Allow-Origin', '*');
  res.header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
  res.header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
  next();
});

// Inicializar conhecimento
conhecimento.inicializarConhecimento();

// ========================================
// ROTAS DO PAINEL
// ========================================

// Servir painel
app.get('/', (req, res) => {
  res.sendFile(__dirname + '/painel-treinamento.html');
});

// Obter conhecimento completo
app.get('/conhecimento', (req, res) => {
  const dados = conhecimento.carregarConhecimento();
  res.json(dados);
});

// Atualizar informações básicas
app.post('/atualizar-info', (req, res) => {
  const { nome, endereco, telefone, whatsapp, horario, email } = req.body;
  const conhecimentoAtual = conhecimento.carregarConhecimento();

  conhecimentoAtual.informacoes_clinica = {
    nome: nome || conhecimentoAtual.informacoes_clinica.nome,
    endereco: endereco || conhecimentoAtual.informacoes_clinica.endereco,
    telefone: telefone || conhecimentoAtual.informacoes_clinica.telefone,
    whatsapp: whatsapp || conhecimentoAtual.informacoes_clinica.whatsapp,
    horario: horario || conhecimentoAtual.informacoes_clinica.horario,
    email: email || conhecimentoAtual.informacoes_clinica.email
  };

  const resultado = conhecimento.salvarConhecimento(conhecimentoAtual);
  res.json(resultado);
});

// Adicionar pergunta frequente
app.post('/adicionar-pergunta', (req, res) => {
  const { pergunta, resposta } = req.body;

  if (!pergunta || !resposta) {
    return res.status(400).json({ sucesso: false, erro: 'Pergunta e resposta são obrigatórios' });
  }

  const resultado = conhecimento.adicionarPerguntaFrequente(pergunta, resposta);
  res.json(resultado);
});

// Adicionar especialidade
app.post('/adicionar-especialidade', (req, res) => {
  const { nome, descricao } = req.body;

  if (!nome || !descricao) {
    return res.status(400).json({ sucesso: false, erro: 'Nome e descrição são obrigatórios' });
  }

  const resultado = conhecimento.adicionarEspecialidade(nome, descricao);
  res.json(resultado);
});

// Adicionar convênio
app.post('/adicionar-convenio', (req, res) => {
  const { nome, tipo } = req.body;

  if (!nome) {
    return res.status(400).json({ sucesso: false, erro: 'Nome é obrigatório' });
  }

  const resultado = conhecimento.adicionarConvenio(nome, tipo || 'privado');
  res.json(resultado);
});

// Adicionar procedimento
app.post('/adicionar-procedimento', (req, res) => {
  const { nome, duracao, preparo } = req.body;

  if (!nome || !duracao) {
    return res.status(400).json({ sucesso: false, erro: 'Nome e duração são obrigatórios' });
  }

  const resultado = conhecimento.adicionarProcedimento(nome, duracao, preparo || '');
  res.json(resultado);
});

// Adicionar conhecimento personalizado
app.post('/adicionar-conhecimento', (req, res) => {
  const { topico, conteudo } = req.body;

  if (!topico || !conteudo) {
    return res.status(400).json({ sucesso: false, erro: 'Tópico e conteúdo são obrigatórios' });
  }

  const resultado = conhecimento.adicionarConhecimentoPersonalizado(topico, conteudo);
  res.json(resultado);
});

// Remover item de conhecimento
app.delete('/remover-conhecimento/:tipo/:id', (req, res) => {
  const { tipo, id } = req.params;
  const resultado = conhecimento.removerConhecimento(tipo, parseInt(id));
  res.json(resultado);
});

// Obter contexto gerado para a IA
app.get('/contexto-ia', (req, res) => {
  const contexto = conhecimento.gerarContextoIA();
  res.json({ contexto });
});

// Testar bot
app.post('/testar-bot', async (req, res) => {
  const { mensagem, usuario } = req.body;

  if (!mensagem) {
    return res.status(400).json({ sucesso: false, erro: 'Mensagem é obrigatória' });
  }

  try {
    // Adicionar contexto personalizado
    const contextoPersonalizado = conhecimento.gerarContextoIA();
    const usuarioId = usuario || 'teste_painel';

    // Limpar histórico anterior e adicionar contexto atualizado
    agenteIA.limparHistorico(usuarioId);

    // Criar histórico com contexto personalizado
    const historicoConversas = {};
    historicoConversas[usuarioId] = [{
      role: 'system',
      content: contextoPersonalizado
    }];

    // Fazer requisição ao agente com contexto atualizado
    const axios = require('axios');

    historicoConversas[usuarioId].push({
      role: 'user',
      content: mensagem
    });

    const response = await axios.post(
      'https://luvswa5jnjcjhczbiiafhart.agents.do-ai.run/api/v1/chat/completions',
      {
        messages: historicoConversas[usuarioId],
        stream: false
      },
      {
        headers: {
          'Authorization': 'Bearer y1FQFR3t_S5i_NNV_nYDoeU_me9uA3l2',
          'Content-Type': 'application/json'
        },
        timeout: 30000
      }
    );

    const resposta = response.data.choices[0].message.content;

    res.json({
      sucesso: true,
      resposta: resposta
    });

  } catch (error) {
    console.log('❌ Erro ao testar bot:', error.message);
    res.status(500).json({
      sucesso: false,
      erro: error.message,
      resposta: 'Erro ao processar mensagem: ' + error.message
    });
  }
});

// Limpar histórico de teste
app.post('/limpar-historico', (req, res) => {
  const { usuario } = req.body;
  agenteIA.limparHistorico(usuario || 'teste_painel');
  res.json({ sucesso: true });
});

// ========================================
// INICIAR SERVIDOR
// ========================================

app.listen(PORT, () => {
  console.log('');
  console.log('🎓 ════════════════════════════════════════════════');
  console.log('   Servidor de Treinamento Iniciado!');
  console.log('════════════════════════════════════════════════════');
  console.log('');
  console.log(`🌐 Painel Web: http://138.197.29.54:${PORT}`);
  console.log('📚 Conhecimento inicializado!');
  console.log('');
  console.log('Acesse o painel para treinar seu bot! 🤖');
  console.log('');
});
