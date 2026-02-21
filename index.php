<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chatbot GPT-4 — Azure AI Foundry</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Segoe UI', sans-serif;
            background: #0f172a;
            color: #e2e8f0;
            display: flex;
            flex-direction: column;
            height: 100vh;
        }

        header {
            background: #1e293b;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #334155;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        header h1 { font-size: 1.1rem; font-weight: 600; }
        header span { font-size: 0.8rem; color: #64748b; }

        #chat-box {
            flex: 1;
            overflow-y: auto;
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .message {
            max-width: 75%;
            padding: 0.75rem 1rem;
            border-radius: 12px;
            line-height: 1.6;
            font-size: 0.95rem;
        }

        .message.user {
            background: #3b82f6;
            align-self: flex-end;
            border-bottom-right-radius: 2px;
        }

        .message.bot {
            background: #1e293b;
            border: 1px solid #334155;
            align-self: flex-start;
            border-bottom-left-radius: 2px;
        }

        .message.error {
            background: #7f1d1d;
            border: 1px solid #ef4444;
            align-self: flex-start;
        }

        .typing {
            background: #1e293b;
            border: 1px solid #334155;
            align-self: flex-start;
            padding: 0.75rem 1rem;
            border-radius: 12px;
            border-bottom-left-radius: 2px;
            color: #64748b;
            font-style: italic;
            font-size: 0.9rem;
        }

        #input-area {
            background: #1e293b;
            border-top: 1px solid #334155;
            padding: 1rem 1.5rem;
            display: flex;
            gap: 0.75rem;
        }

        #user-input {
            flex: 1;
            background: #0f172a;
            border: 1px solid #334155;
            color: #e2e8f0;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            font-size: 0.95rem;
            outline: none;
            resize: none;
            height: 48px;
        }

        #user-input:focus { border-color: #3b82f6; }

        button {
            background: #3b82f6;
            color: white;
            border: none;
            padding: 0.75rem 1.25rem;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.95rem;
            font-weight: 500;
            transition: background 0.2s;
        }

        button:hover { background: #2563eb; }
        button:disabled { background: #334155; cursor: not-allowed; }
    </style>
</head>
<body>

<header>
    <div>
        <h1>🤖 Chatbot GPT-4</h1>
        <span>Propulsé par Azure AI Foundry</span>
    </div>
</header>

<div id="chat-box">
    <div class="message bot">Bonjour ! Je suis votre assistant IA basé sur GPT-4. Comment puis-je vous aider ?</div>
</div>

<div id="input-area">
    <textarea id="user-input" placeholder="Écrivez votre message..." rows="1"></textarea>
    <button id="send-btn" onclick="sendMessage()">Envoyer</button>
</div>

<script>
    const chatBox = document.getElementById('chat-box');
    const userInput = document.getElementById('user-input');
    const sendBtn = document.getElementById('send-btn');

    // Historique de la conversation
    let conversationHistory = [];

    userInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });

    function appendMessage(content, role) {
        const div = document.createElement('div');
        div.classList.add('message', role);
        div.textContent = content;
        chatBox.appendChild(div);
        chatBox.scrollTop = chatBox.scrollHeight;
        return div;
    }

    async function sendMessage() {
        const message = userInput.value.trim();
        if (!message) return;

        userInput.value = '';
        sendBtn.disabled = true;

        appendMessage(message, 'user');
        conversationHistory.push({ role: 'user', content: message });

        const typingDiv = document.createElement('div');
        typingDiv.classList.add('typing');
        typingDiv.textContent = 'GPT-4 est en train d\'écrire...';
        chatBox.appendChild(typingDiv);
        chatBox.scrollTop = chatBox.scrollHeight;

        try {
            const response = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ messages: conversationHistory })
            });

            const data = await response.json();
            typingDiv.remove();

            if (data.error) {
                appendMessage('Erreur : ' + data.error, 'error');
            } else {
                appendMessage(data.reply, 'bot');
                conversationHistory.push({ role: 'assistant', content: data.reply });
            }
        } catch (err) {
            typingDiv.remove();
            appendMessage('Erreur de connexion à l\'API.', 'error');
        }

        sendBtn.disabled = false;
        userInput.focus();
    }
</script>

</body>
</html>
