# 🚀 Instalar e Rodar Bot WhatsApp Localmente

## ✅ PASSO A PASSO COMPLETO

---

## 1️⃣ Instalar Node.js

### Windows:
1. Acesse: https://nodejs.org/
2. Baixe a versão **LTS** (recomendada)
3. Execute o instalador
4. Clique em "Next" até finalizar
5. Marque a opção "Automatically install necessary tools"

### Mac:
1. Acesse: https://nodejs.org/
2. Baixe a versão **LTS**
3. Execute o instalador .pkg
4. Siga as instruções na tela

### Linux:
```bash
# Ubuntu/Debian
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt-get install -y nodejs

# Fedora/RedHat
sudo dnf install nodejs
```

### ✅ Verificar Instalação:
Abra o **Terminal** (Windows: CMD ou PowerShell) e digite:
```bash
node --version
npm --version
```

Deve aparecer algo como: `v20.x.x` e `10.x.x`

---

## 2️⃣ Baixar os Arquivos

### Opção A - Via Git (se tiver):
```bash
cd Desktop
git clone [repositório] whatsapp-bot
cd whatsapp-bot
```

### Opção B - Copiar Manualmente:
1. Crie uma pasta no Desktop chamada `whatsapp-bot`
2. Copie os 3 arquivos para dentro:
   - `package.json`
   - `bot.js`
   - `INSTRUCOES.md` (este arquivo)

---

## 3️⃣ Instalar Dependências

Abra o **Terminal/CMD** na pasta do projeto:

### Windows:
1. Abra a pasta `whatsapp-bot`
2. Clique na barra de endereço
3. Digite `cmd` e pressione Enter
4. Digite:
```bash
npm install
```

### Mac/Linux:
```bash
cd Desktop/whatsapp-bot
npm install
```

⏳ **Aguarde 1-2 minutos** - vai baixar todas as bibliotecas necessárias.

---

## 4️⃣ Rodar o Bot

No mesmo terminal, digite:

```bash
npm start
```

**OU:**

```bash
node bot.js
```

---

## 5️⃣ Escanear QR Code

Após rodar, você verá:

```
🚀 Iniciando Bot WhatsApp - Clínica Oitava Rosado
════════════════════════════════════════════════

⏳ Inicializando cliente WhatsApp...

📱 QR CODE GERADO!
════════════════════════════════════════════════

█████████████████████████████
█████████████████████████████
████ ▄▄▄▄▄ █ ▀▀▀█ ▄▄▄▄▄ ████
████ █   █ ██  ▀█ █   █ ████
...
```

### ✅ Escanear:
1. Abra WhatsApp no celular
2. Vá em **Mais opções** (⋮) > **Aparelhos conectados**
3. Toque em **Conectar um aparelho**
4. Escaneie o QR Code que apareceu no terminal

---

## 6️⃣ Testar o Bot

Quando conectar, verá:

```
🎉 ════════════════════════════════════════════════
   WHATSAPP CONECTADO COM SUCESSO!
════════════════════════════════════════════════════

📱 Bot está pronto para receber mensagens!
```

### 🧪 Para testar:
1. Envie uma mensagem para o número conectado (de outro celular)
2. O bot vai responder automaticamente
3. Você verá os logs no terminal

---

## 🐛 Problemas Comuns

### ❌ "node não é reconhecido como comando"
**Solução:** Reinicie o computador após instalar Node.js

### ❌ "npm install" dá erro
**Solução:**
```bash
npm cache clean --force
npm install
```

### ❌ Chrome não abre automaticamente
**Solução:** O bot está configurado para abrir Chrome visualmente. Se não abrir:
1. Verifique se Chrome está instalado
2. Tente rodar como administrador

### ❌ Erro ao escanear QR Code
**Solução:**
- Aguarde alguns segundos após o QR aparecer
- Tente escanear rapidamente
- Se der erro, feche o bot (Ctrl+C) e rode de novo

---

## 🛑 Parar o Bot

No terminal, pressione: **Ctrl + C**

---

## 📊 O Que Está Acontecendo?

1. **Node.js** está rodando JavaScript no seu computador
2. **whatsapp-web.js** abre uma versão automatizada do WhatsApp Web
3. **Puppeteer** controla um navegador Chrome
4. O bot conecta ao **Digital Ocean Agent** para processar mensagens
5. Respostas são enviadas automaticamente via WhatsApp

---

## 🎯 Se Funcionar Localmente

Significa que:
- ✅ O código está correto
- ✅ Seu WhatsApp não tem restrições
- ❌ O problema era realmente os servidores (Digital Ocean)

**Próximo passo:** Usar outro provedor de servidor (AWS, Contabo, etc)

---

## 🎯 Se NÃO Funcionar Localmente

Significa que:
- ❌ Seu WhatsApp tem restrições de segurança
- ❌ WhatsApp está bloqueando todas conexões via API

**Próximo passo:** API oficial paga (Twilio, 360Dialog)

---

## 📞 Dúvidas?

Se tiver qualquer problema, me envie:
1. Qual erro apareceu
2. Screenshot do terminal
3. Sistema operacional (Windows/Mac/Linux)

---

**Boa sorte! 🚀**
