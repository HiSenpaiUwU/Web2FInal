document.addEventListener('DOMContentLoaded', () => {
  if (!location.pathname.endsWith('/messages.php')) return;
  const form = document.querySelector('.message-compose');
  const thread = document.querySelector('.thread-messages');
  if (!form || !thread) return;
  const input = form.querySelector('[name="message"]');
  const button = form.querySelector('button[type="submit"]');
  const feedback = form.querySelector('.message-feedback');
  const conversationList = document.querySelector('.conversation-list');
  const conversation = form.querySelector('[name="conversation_id"]')?.value;
  const csrf = form.querySelector('[name="csrf"]')?.value;
  if (!conversation || !input || !button) return;
  const endpoint = new URL('messages.php', location.href);
  endpoint.searchParams.set('ajax', '1');
  let busy = false;

  function appendMessage(message) {
    if (thread.querySelector(`[data-message-id="${CSS.escape(String(message.id))}"]`)) return;
    const nearBottom = thread.scrollHeight - thread.scrollTop - thread.clientHeight < 90;
    const row = document.createElement('div');
    row.className = `message${message.is_mine ? ' mine' : ''}`;
    row.dataset.messageId = message.id;
    const body = document.createElement('p'); body.textContent = message.message;
    const time = document.createElement('small'); time.textContent = new Date(String(message.created_at).replace(' ', 'T')).toLocaleString();
    row.append(body, time); thread.append(row);
    if (nearBottom) thread.scrollTop = thread.scrollHeight;
  }

  async function poll() {
    try {
      const url = new URL(endpoint); url.searchParams.set('conversation', conversation);
      const response = await fetch(url, { headers: { Accept: 'application/json' }, cache: 'no-store' });
      const data = await response.json();
      if (!response.ok || !data.ok) throw new Error(data.error || 'Messages could not be updated.');
      data.messages.forEach(appendMessage);
      if (conversationList && data.conversations) {
        conversationList.replaceChildren();
        data.conversations.forEach(item => {
          const link = document.createElement('a'); link.href = `?conversation=${encodeURIComponent(item.id)}`;
          if (String(item.id) === String(conversation)) link.classList.add('selected');
          const title = document.createElement('b'); title.textContent = item.label || 'Business conversation';
          const preview = document.createElement('span'); preview.textContent = item.latest || 'No messages yet';
          const meta = document.createElement('small'); meta.textContent = `${new Date(String(item.updated_at).replace(' ', 'T')).toLocaleString()}${Number(item.unread) ? ` · ${item.unread} new` : ''}`;
          link.append(title, preview, meta); conversationList.append(link);
        });
      }
      const navLink = document.querySelector('.message-nav, .owner-message-nav');
      const badge = navLink?.querySelector('span');
      if (badge) { if (data.unread) badge.textContent = data.unread; else badge.remove(); }
      else if (data.unread && navLink) { const count = document.createElement('span'); count.className = 'message-count'; count.textContent = data.unread; navLink.append(count); }
      if (feedback && !busy) feedback.textContent = '';
    } catch (error) { if (feedback && !busy) feedback.textContent = error.message; }
  }

  form.addEventListener('submit', async event => {
    event.preventDefault();
    const message = input.value.trim();
    if (!message || busy) return;
    busy = true; button.disabled = true; button.textContent = 'Sending…';
    if (feedback) feedback.textContent = 'Sending…';
    const payload = new FormData(form);
    try {
      const response = await fetch(endpoint, { method: 'POST', body: payload, headers: { Accept: 'application/json' } });
      const data = await response.json();
      if (!response.ok || !data.ok) throw new Error(data.error || 'Message failed to send. Please try again.');
      input.value = ''; data.messages.forEach(appendMessage);
      if (feedback) feedback.textContent = 'Message sent.';
    } catch (error) { if (feedback) feedback.textContent = error.message; }
    finally { busy = false; button.disabled = false; button.textContent = 'Send'; input.focus(); }
  });
  poll(); window.setInterval(poll, 4000);
});
