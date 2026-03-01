(() => {
    const ensureChatStyles = () => {
        if (document.getElementById('jth-chat-widget-styles')) return;
        const style = document.createElement('style');
        style.id = 'jth-chat-widget-styles';
        style.textContent = `
            .chat-fab {
                position: fixed;
                left: auto;
                right: 56px;
                bottom: 188px;
                transform: none;
                z-index: 130;
                width: 58px;
                height: 58px;
                border-radius: 999px;
                border: 1px solid #D1D5DB;
                background: #FFFFFF;
                color: #111111;
                display: grid;
                align-items: center;
                justify-content: center;
                box-shadow: 0 14px 30px -18px rgba(0,0,0,0.42);
                transition: transform .22s ease, box-shadow .22s ease, background .22s ease, color .22s ease, border-color .22s ease;
                cursor: pointer;
            }
            .chat-fab:hover {
                transform: translateY(-2px);
                box-shadow: 0 20px 34px -18px rgba(0,0,0,0.45);
                border-color: #9CA3AF;
            }
            .chat-fab-icon {
                transition: opacity .2s ease, transform .2s ease;
            }
            .chat-fab-dots {
                position: absolute;
                left: 50%;
                top: 50%;
                display: inline-flex;
                align-items: center;
                gap: 3px;
                opacity: 0;
                transform: translate(-50%, calc(-50% + 2px)) scale(0.95);
                transition: opacity .2s ease, transform .2s ease;
                pointer-events: none;
            }
            .chat-fab-dots span {
                width: 4px;
                height: 4px;
                border-radius: 999px;
                background: #111111;
                animation: chatTyping 1s ease-in-out infinite;
            }
            .chat-fab-dots span:nth-child(2) { animation-delay: .15s; }
            .chat-fab-dots span:nth-child(3) { animation-delay: .3s; }
            .chat-fab:hover .chat-fab-icon {
                opacity: 0;
                transform: scale(0.9);
            }
            .chat-fab:hover .chat-fab-dots {
                opacity: 1;
                transform: translate(-50%, -50%) scale(1);
            }
            @keyframes chatTyping {
                0%, 80%, 100% { opacity: 0.35; transform: translateY(0); }
                40% { opacity: 1; transform: translateY(-2px); }
            }

            .chat-panel {
                position: fixed;
                left: auto;
                right: 10px;
                bottom: 192px;
                transform: none;
                z-index: 130;
                width: min(380px, calc(100vw - 28px));
                height: min(520px, calc(100vh - 190px));
                background: #fff;
                border: 1px solid #E5E7EB;
                border-radius: 14px;
                box-shadow: 0 20px 40px rgba(0,0,0,.16);
                display: flex;
                flex-direction: column;
                opacity: 0;
                transform: translateY(12px) scale(.98);
                pointer-events: none;
                transition: opacity .2s ease, transform .2s ease;
            }
            .chat-panel.open {
                opacity: 1;
                transform: translateY(0) scale(1);
                pointer-events: auto;
            }
            .chat-head {
                padding: 12px 14px;
                border-bottom: 1px solid #EEF0F3;
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 10px;
            }
            .chat-title { font-size: 13px; font-weight: 700; color: #111827; }
            .chat-close {
                border: 0;
                background: transparent;
                color: #6B7280;
                font-size: 20px;
                line-height: 1;
                cursor: pointer;
            }
            .chat-messages {
                flex: 1;
                overflow-y: auto;
                padding: 12px;
                display: flex;
                flex-direction: column;
                gap: 8px;
                background: #FAFAFB;
            }
            .chat-bubble {
                max-width: 88%;
                padding: 8px 10px;
                border-radius: 10px;
                font-size: 12px;
                line-height: 1.45;
                white-space: pre-wrap;
                word-break: break-word;
            }
            .chat-bubble.bot {
                align-self: flex-start;
                background: #FFFFFF;
                border: 1px solid #E5E7EB;
                color: #111827;
            }
            .chat-bubble.user {
                align-self: flex-end;
                background: #111827;
                color: #fff;
            }
            .chat-foot { border-top: 1px solid #EEF0F3; padding: 10px; background: #fff; }
            .chat-suggest {
                display: flex;
                flex-wrap: wrap;
                gap: 6px;
                margin-bottom: 8px;
            }
            .chat-chip {
                height: 28px;
                padding: 0 10px;
                border-radius: 999px;
                border: 1px solid #E4E4E7;
                background: #FFFFFF;
                color: #3F3F46;
                font-size: 11px;
                line-height: 28px;
                cursor: pointer;
                transition: border-color .2s ease, background .2s ease, color .2s ease, transform .2s ease;
            }
            .chat-chip:hover {
                border-color: #111827;
                background: #111827;
                color: #FFFFFF;
                transform: translateY(-1px);
            }
            .chat-form { display: flex; align-items: center; gap: 8px; }
            .chat-input {
                flex: 1;
                height: 36px;
                border: 1px solid #D1D5DB;
                border-radius: 9px;
                padding: 0 10px;
                font-size: 12px;
                outline: none;
            }
            .chat-input:focus { border-color: #111827; }
            .chat-send {
                height: 36px;
                padding: 0 12px;
                border-radius: 9px;
                border: 1px solid #111827;
                background: #111827;
                color: #fff;
                font-size: 12px;
                font-weight: 600;
                cursor: pointer;
            }
            .chat-send:disabled { opacity: .6; cursor: default; }
            .chat-note {
                margin-top: 8px;
                color: #6B7280;
                font-size: 10px;
                line-height: 1.4;
            }

            @media (max-width: 767px) {
                .chat-fab {
                    left: auto;
                    right: 10px;
                    bottom: 176px;
                    width: 52px;
                    height: 52px;
                }
                .chat-panel {
                    left: auto;
                    right: 10px;
                    bottom: 210px;
                    width: calc(100vw - 20px);
                    height: min(500px, calc(100vh - 190px));
                }
            }
        `;
        document.head.appendChild(style);
    };

    ensureChatStyles();

    const mount = document.getElementById('chat-widget-root');
    if (!mount) return;

    mount.innerHTML = `
        <button id="chat-fab" class="chat-fab" aria-label="Open chat" title="Inquiries Assistant">
            <svg class="chat-fab-icon" width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M20 4H4a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h3v3l4-3h9a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <span class="chat-fab-dots" aria-hidden="true"><span></span><span></span><span></span></span>
        </button>
        <div id="chat-panel" class="chat-panel" aria-live="polite">
            <div class="chat-head">
                <div class="chat-title">Inquiry Assistant</div>
                <button id="chat-close" class="chat-close" type="button" aria-label="Close chat">×</button>
            </div>
            <div id="chat-messages" class="chat-messages"></div>
            <div class="chat-foot">
                <div class="chat-suggest" id="chat-suggest">
                    <button class="chat-chip" type="button" data-q="How do I request a quotation?">How do I request a quotation?</button>
                    <button class="chat-chip" type="button" data-q="When is the schedule confirmed?">When is the schedule confirmed?</button>
                    <button class="chat-chip" type="button" data-q="What details do you need for an estimate?">What details are needed?</button>
                    <button class="chat-chip" type="button" data-q="Ano ang services ninyo?">What services do you offer?</button>
                </div>
                <form id="chat-form" class="chat-form">
                    <input id="chat-input" class="chat-input" type="text" maxlength="500" placeholder="Ask about services, process, lead time..." autocomplete="off">
                    <button id="chat-send" class="chat-send" type="submit">Send</button>
                </form>
                <p class="chat-note">Instant Quotation provides an estimate; final pricing is confirmed after site measurement.</p>
            </div>
        </div>
    `;

    const panel = document.getElementById('chat-panel');
    const fab = document.getElementById('chat-fab');
    const closeBtn = document.getElementById('chat-close');
    const form = document.getElementById('chat-form');
    const input = document.getElementById('chat-input');
    const messages = document.getElementById('chat-messages');
    const sendBtn = document.getElementById('chat-send');
    const suggestWrap = document.getElementById('chat-suggest');

    if (!panel || !fab || !form || !input || !messages || !sendBtn) return;

    const sendMessage = async (text) => {
        const cleaned = String(text || '').trim();
        if (!cleaned) return;

        addMessage(cleaned, 'user');
        input.value = '';
        input.disabled = true;
        sendBtn.disabled = true;

        const thinkingId = `thinking-${Date.now()}`;
        const thinking = document.createElement('div');
        thinking.className = 'chat-bubble bot';
        thinking.id = thinkingId;
        thinking.textContent = 'Typing...';
        messages.appendChild(thinking);
        messages.scrollTop = messages.scrollHeight;

        try {
            const res = await fetch('backend/chat.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ message: cleaned })
            });
            const json = await res.json();
            const reply = String(json.reply || json.message || '').trim();
            const fallback = "I couldn't process that right now. Please try again or send your inquiry via the Contact section.";

            const existing = document.getElementById(thinkingId);
            if (existing) existing.remove();
            addMessage(reply || fallback, 'bot');
        } catch (_err) {
            const existing = document.getElementById(thinkingId);
            if (existing) existing.remove();
            addMessage("Connection error. Please try again in a moment.", 'bot');
        } finally {
            input.disabled = false;
            sendBtn.disabled = false;
            input.focus();
        }
    };

    const addMessage = (text, role) => {
        const bubble = document.createElement('div');
        bubble.className = `chat-bubble ${role === 'user' ? 'user' : 'bot'}`;
        bubble.textContent = text;
        messages.appendChild(bubble);
        messages.scrollTop = messages.scrollHeight;
    };

    const setOpen = (open) => {
        panel.classList.toggle('open', open);
        if (open) {
            setTimeout(() => input.focus(), 80);
        }
    };

    addMessage(
        "Hi! I can answer general questions about JTH services, quotation flow, schedule guidance, and contact details.",
        "bot"
    );

    fab.addEventListener('click', () => setOpen(!panel.classList.contains('open')));
    if (closeBtn) closeBtn.addEventListener('click', () => setOpen(false));

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && panel.classList.contains('open')) {
            setOpen(false);
        }
    });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        await sendMessage(input.value.trim());
    });

    if (suggestWrap) {
        suggestWrap.querySelectorAll('.chat-chip').forEach((chip) => {
            chip.addEventListener('click', async () => {
                if (input.disabled || sendBtn.disabled) return;
                const q = chip.getAttribute('data-q') || chip.textContent || '';
                await sendMessage(q);
            });
        });
    }
})();
