document.addEventListener('DOMContentLoaded', () => {
  if (document.querySelector('.chat-widget')) return;

  const widget = document.createElement('div');
  widget.className = 'chat-widget';
  widget.innerHTML = `
    <button class="chat-toggle" type="button" aria-expanded="false" aria-controls="support-chat">
      <span class="chat-toggle-dot" aria-hidden="true"></span> Chat support
    </button>
    <section class="chat-box" id="support-chat" aria-label="Davao Local help" aria-hidden="true">
      <header class="chat-head">
        <span class="chat-avatar" aria-hidden="true">D</span>
        <span class="chat-heading"><strong>Davao Local Help</strong><small>Directory and account support</small></span>
        <button class="chat-history-toggle" type="button">History</button>
        <button class="chat-new" type="button">New chat</button>
        <button class="chat-close" type="button" aria-label="Close chat">×</button>
      </header>
      <div class="chat-layout">
        <aside class="chat-sidebar" aria-label="Support conversations">
          <label class="chat-search-label"><span>Search chats</span><input class="chat-search" type="search" placeholder="Find a conversation"></label>
          <nav class="chat-conversation-list" aria-label="Conversation history"></nav>
        </aside>
        <main class="chat-main">
          <div class="chat-log" aria-live="polite" aria-relevant="additions text"></div>
          <form class="chat-form">
            <textarea required maxlength="500" rows="1" aria-label="Your message" placeholder="Ask about Davao Local"></textarea>
            <button type="submit" aria-label="Send message" disabled><span aria-hidden="true">↑</span></button>
          </form>
          <p class="chat-note">Support replies are based on the Davao Local help guide.</p>
        </main>
      </div>
    </section>`;
  document.body.append(widget);

  const box = widget.querySelector('.chat-box');
  const toggle = widget.querySelector('.chat-toggle');
  const close = widget.querySelector('.chat-close');
  const newChat = widget.querySelector('.chat-new');
  const historyToggle = widget.querySelector('.chat-history-toggle');
  const historyList = widget.querySelector('.chat-conversation-list');
  const search = widget.querySelector('.chat-search');
  const log = widget.querySelector('.chat-log');
  const form = widget.querySelector('.chat-form');
  const input = form.querySelector('textarea');
  const send = form.querySelector('button');
  let loading = false;
  let activeConversation = null;
  let conversations = [];
  let historyRequest = 0;
  let csrfToken = '';

  const scrollLatest = () => { log.scrollTop = log.scrollHeight; };
  const openChat = (open) => {
    box.classList.toggle('open', open);
    box.setAttribute('aria-hidden', String(!open));
    toggle.setAttribute('aria-expanded', String(open));
    if (open) input.focus();
  };
  const appendText = (parent, text) => {
    // Render a small safe Markdown subset; all ordinary content remains text.
    const pattern = /(`[^`]+`|\*\*[^*]+\*\*|\*[^*]+\*|https?:\/\/[^\s<]+)/g;
    let cursor = 0;
    for (const match of text.matchAll(pattern)) {
      parent.append(document.createTextNode(text.slice(cursor, match.index)));
      const token = match[0];
      if (token.startsWith('`')) {
        const code = document.createElement('code'); code.textContent = token.slice(1, -1); parent.append(code);
      } else if (token.startsWith('**')) {
        const strong = document.createElement('strong'); strong.textContent = token.slice(2, -2); parent.append(strong);
      } else if (token.startsWith('*')) {
        const em = document.createElement('em'); em.textContent = token.slice(1, -1); parent.append(em);
      } else {
        try {
          const url = new URL(token);
          if (url.protocol === 'http:' || url.protocol === 'https:') {
            const link = document.createElement('a'); link.href = url.href; link.target = '_blank'; link.rel = 'noopener noreferrer'; link.textContent = token; parent.append(link);
          } else parent.append(document.createTextNode(token));
        } catch { parent.append(document.createTextNode(token)); }
      }
      cursor = match.index + token.length;
    }
    parent.append(document.createTextNode(text.slice(cursor)));
  };
  const addMessage = (text, sender, time = '', options = {}) => {
    const article = document.createElement('article');
    article.className = `chat-message ${sender === 'user' ? 'from-user' : 'from-assistant'}${options.error ? ' is-error' : ''}${options.pending ? ' is-pending' : ''}`;
    if (sender !== 'user') {
      const avatar = document.createElement('span'); avatar.className = 'chat-message-avatar'; avatar.setAttribute('aria-hidden', 'true'); avatar.textContent = 'D'; article.append(avatar);
    }
    const body = document.createElement('div'); body.className = 'chat-message-body';
    for (const [index, paragraph] of text.split(/\n{2,}/).entries()) {
      if (!paragraph) continue;
      const p = document.createElement('p');
      if (/^```[\s\S]*```$/.test(paragraph)) { const pre = document.createElement('pre'); const code = document.createElement('code'); code.textContent = paragraph.replace(/^```[^\n]*\n?/, '').replace(/```$/, ''); pre.append(code); body.append(pre); }
      else if (/^#{1,3}\s/.test(paragraph)) { const h = document.createElement(`h${Math.min(3, (paragraph.match(/^#+/) || [''])[0].length + 2)}`); appendText(h, paragraph.replace(/^#{1,3}\s/, '')); body.append(h); }
      else if (/^(?:[-*]\s|\d+\.\s)/m.test(paragraph)) {
        const lines = paragraph.split('\n'); const list = document.createElement(lines.every(line => /^\d+\.\s/.test(line)) ? 'ol' : 'ul');
        lines.forEach(line => { const li = document.createElement('li'); appendText(li, line.replace(/^(?:[-*]|\d+\.)\s/, '')); list.append(li); }); body.append(list);
      } else { appendText(p, paragraph); body.append(p); }
      if (index > 8) break;
    }
    if (time) { const stamp = document.createElement('small'); stamp.className = 'chat-message-time'; stamp.textContent = time; body.append(stamp); }
    article.append(body);
    if (sender !== 'user' && !options.pending && !options.error) {
      const copy = document.createElement('button'); copy.type = 'button'; copy.className = 'chat-copy'; copy.textContent = 'Copy'; copy.setAttribute('aria-label', 'Copy support reply');
      copy.addEventListener('click', async () => {
        try { await navigator.clipboard.writeText(text); copy.textContent = 'Copied'; setTimeout(() => { copy.textContent = 'Copy'; }, 1400); }
        catch { copy.textContent = 'Copy unavailable'; setTimeout(() => { copy.textContent = 'Copy'; }, 1800); }
      }); body.append(copy);
    }
    log.append(article); scrollLatest(); return article;
  };
  const setBusy = (busy) => { loading = busy; send.disabled = busy || !input.value.trim(); input.disabled = busy; search.disabled = busy; historyToggle.disabled = busy; newChat.disabled = busy; renderConversations(); form.classList.toggle('is-loading', busy); };
  const setEmpty = () => {
    if (log.querySelector('.chat-empty')) return;
    const empty = document.createElement('div'); empty.className = 'chat-empty';
    const title = document.createElement('h2'); title.textContent = 'How can I help?';
    const description = document.createElement('p'); description.textContent = 'Ask a question about finding businesses, accounts, or inquiries.';
    empty.append(title, description);
    const prompts = ['Find a business', 'How do inquiries work?', 'Help with my account'];
    const suggestions = document.createElement('div'); suggestions.className = 'chat-suggestions';
    prompts.forEach(prompt => { const button = document.createElement('button'); button.type = 'button'; button.textContent = prompt; button.addEventListener('click', () => { input.value = prompt; input.dispatchEvent(new Event('input')); input.focus(); }); suggestions.append(button); });
    empty.append(suggestions); log.append(empty);
  };
  const renderConversations = () => {
    historyList.replaceChildren();
    const needle = search.value.trim().toLocaleLowerCase();
    const matches = conversations.filter(conversation => String(conversation.title).toLocaleLowerCase().includes(needle));
    if (!matches.length) {
      const empty = document.createElement('p'); empty.className = 'chat-history-empty'; empty.textContent = needle ? 'No matching chats.' : 'Your chats will appear here.'; historyList.append(empty); return;
    }
    matches.forEach(conversation => {
      const item = document.createElement('button'); item.type = 'button'; item.className = 'chat-conversation';
      if (Number(conversation.id) === Number(activeConversation)) item.classList.add('active');
      item.title = conversation.title; item.textContent = conversation.title || 'New chat'; item.disabled = loading;
      item.addEventListener('click', async () => { await loadHistory(Number(conversation.id)); box.classList.remove('show-history'); });
      historyList.append(item);
    });
  };
  const loadHistory = async (conversationId = null) => {
    const requestId = ++historyRequest;
    log.setAttribute('aria-busy', 'true');
    try {
      const url = new URL('chat.php', location.href);
      if (conversationId) url.searchParams.set('conversation', String(conversationId));
      const response = await fetch(url, { headers: { Accept: 'application/json' } });
      const result = await response.json();
      if (requestId !== historyRequest) return;
      if (!response.ok || !result.ok) throw new Error(result.reply || 'Support history could not load.');
      csrfToken = result.csrf || csrfToken;
      activeConversation = result.conversation ? Number(result.conversation.id) : null;
      conversations = result.conversations || conversations;
      renderConversations();
      log.replaceChildren();
      if (result.messages?.length) result.messages.forEach(message => addMessage(message.message, message.sender, new Date(message.created_at).toLocaleString()));
      else setEmpty();
    } catch (error) {
      if (requestId !== historyRequest) return;
      log.replaceChildren(); setEmpty(); addMessage(error.message || 'Chat history could not load. Try again later.', 'assistant', '', { error: true });
    } finally { if (requestId === historyRequest) { log.removeAttribute('aria-busy'); scrollLatest(); } }
  };

  toggle.addEventListener('click', () => openChat(!box.classList.contains('open')));
  close.addEventListener('click', () => openChat(false));
  newChat.addEventListener('click', async () => {
    if (loading) return;
    newChat.disabled = true;
    const data = new FormData(); data.append('action', 'new'); data.append('csrf', csrfToken);
    try {
      const response = await fetch('chat.php', { method: 'POST', body: data, headers: { Accept: 'application/json' } });
      const result = await response.json();
      if (!response.ok || !result.ok) throw new Error(result.reply || 'A new conversation could not be started.');
      csrfToken = result.csrf || csrfToken;
      await loadHistory(Number(result.conversation.id)); box.classList.remove('show-history'); input.focus();
    } catch (error) { addMessage(error.message || 'A new conversation could not be started.', 'assistant', '', { error: true }); }
    finally { newChat.disabled = false; }
  });
  historyToggle.addEventListener('click', () => { if (!loading) box.classList.toggle('show-history'); });
  search.addEventListener('input', renderConversations);
  input.addEventListener('input', () => { send.disabled = loading || !input.value.trim(); input.style.height = 'auto'; input.style.height = `${Math.min(input.scrollHeight, 112)}px`; });
  input.addEventListener('keydown', event => { if (event.key === 'Enter' && !event.shiftKey) { event.preventDefault(); if (!send.disabled) form.requestSubmit(); } });
  form.addEventListener('submit', async event => {
    event.preventDefault(); const message = input.value.trim(); if (!message || loading) return;
    log.querySelector('.chat-empty')?.remove(); addMessage(message, 'user', 'You'); input.value = ''; input.style.height = 'auto';
    const pending = addMessage('Finding the right help information…', 'assistant', '', { pending: true }); setBusy(true); newChat.disabled = true;
    const data = new FormData(); data.append('csrf', csrfToken); data.append('message', message); if (activeConversation) data.append('conversation', String(activeConversation));
    try {
      const response = await fetch('chat.php', { method: 'POST', body: data, headers: { Accept: 'application/json' } });
      const result = await response.json(); pending.remove();
      if (!response.ok || !result.ok) throw new Error(result.reply || 'Your message could not be sent. Please try again.');
      activeConversation = Number(result.conversation.id);
      await loadHistory(activeConversation);
    } catch (error) { pending.remove(); addMessage(error.message || 'Your message could not be sent. Please try again.', 'assistant', '', { error: true }); }
    finally { setBusy(false); newChat.disabled = false; input.focus(); }
  });

  loadHistory();
});
