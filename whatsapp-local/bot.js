const { Client, LocalAuth } = require('whatsapp-web.js');
const qrcode = require('qrcode-terminal');
const express = require('express');
const agenteIA = require('./agente-ia');

const app = express();
app.use(express.json());

console.log('🚀 Iniciando Bot WhatsApp - Clínica Oitava Rosado');
console.log('════════════════════════════════════════════════');
console.log('');

// Cliente WhatsApp
const client = new Client({
  authStrategy: new LocalAuth(),
  puppeteer: {
    headless: true, // MODO HEADLESS - sem interface gráfica (para servidor)
    args: [
      '--no-sandbox',
      '--disable-setuid-sandbox',
      '--disable-dev-shm-usage',
      '--disable-accelerated-2d-canvas',
      '--disable-gpu'
    ]
  }
});

// QR Code
client.on('qr', (qr) => {
  console.log('');
  console.log('📱 QR CODE GERADO!');
  console.log('════════════════════════════════════════════════');
  console.log('');
  qrcode.generate(qr, { small: true });
  console.log('');
  console.log('════════════════════════════════════════════════');
  console.log('✅ Escaneie o QR Code acima com seu WhatsApp!');
  console.log('');
});

// Autenticando
client.on('authenticated', () => {
  console.log('✅ Autenticado com sucesso!');
});

// Carregando
client.on('loading_screen', (percent, message) => {
  console.log(`⏳ Carregando... ${percent}%`);
});

// Pronto
client.on('ready', () => {
  console.log('');
  console.log('🎉 ════════════════════════════════════════════════');
  console.log('   WHATSAPP CONECTADO COM SUCESSO!');
  console.log('════════════════════════════════════════════════════');
  console.log('');
  console.log('📱 Bot está pronto para receber mensagens!');
  console.log('');
  console.log('Para testar, envie uma mensagem para o número conectado.');
  console.log('');
});

// Mensagens recebidas
client.on('message', async (message) => {
  try {
    const from = message.from;
    const text = message.body;

    // Ignorar mensagens de grupos
    if (from.endsWith('@g.us')) {
      return;
    }

    console.log('');
    console.log('💬 ─────────────────────────────────────────────');
    console.log(`📱 De: ${from}`);
    console.log(`📝 Mensagem: ${text}`);
    console.log('─────────────────────────────────────────────');

    // Consultar agente IA com APIs integradas
    try {
      console.log('🤖 Consultando agente inteligente...');

      const resultado = await agenteIA.consultarAgente(from, text);

      if (resultado.sucesso) {
        const resposta = resultado.resposta;

        console.log('✅ Resposta do agente gerada!');

        if (resultado.usouAPI) {
          console.log(`📊 API utilizada: ${resultado.tipoAPI}`);
        }

        console.log(`📤 Enviando: ${resposta.substring(0, 100)}...`);

        await message.reply(resposta);

        console.log('✅ Resposta enviada com sucesso!');
        console.log('');

      } else {
        console.log('❌ Erro no agente:', resultado.erro);
        console.log('⚠️  Enviando resposta de erro...');

        await message.reply(resultado.resposta);
        console.log('✅ Resposta de erro enviada!');
        console.log('');
      }

    } catch (error) {
      console.log('❌ Erro ao processar com agente:', error.message);
      console.log('⚠️  Enviando resposta padrão...');

      const respostaPadrao = `Olá! 👋

Bem-vindo à *Clínica Oitava Rosado*!

Sou o assistente virtual e posso ajudar você com:
📅 Agendar consultas
📋 Ver horários disponíveis
💰 Consultar preços
❓ Tirar dúvidas

Como posso ajudar?`;

      await message.reply(respostaPadrao);
      console.log('✅ Resposta padrão enviada!');
      console.log('');
    }

  } catch (error) {
    console.log('❌ Erro ao processar mensagem:', error.message);
  }
});

// Desconectado
client.on('disconnected', (reason) => {
  console.log('');
  console.log('❌ ════════════════════════════════════════════════');
  console.log('   WhatsApp Desconectado');
  console.log('════════════════════════════════════════════════════');
  console.log('Motivo:', reason);
  console.log('');
});

// Iniciar cliente
console.log('⏳ Inicializando cliente WhatsApp...');
console.log('');
client.initialize();

// API de status (opcional)
app.get('/status', (req, res) => {
  res.json({
    status: 'running',
    whatsapp_connected: client.info ? true : false,
    timestamp: new Date().toISOString()
  });
});

app.listen(3000, () => {
  console.log('🌐 API local rodando em http://localhost:3000/status');
  console.log('');
});
