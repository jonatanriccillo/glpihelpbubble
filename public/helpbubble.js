(function () {
  const ENDPOINT = "/plugins/helpbubble/ajax/ask.php";

  if (window.__helpbubble_loaded) return;
  window.__helpbubble_loaded = true;

  if (document.body && document.body.classList.contains('not-logged')) return;

  const sessionId = (crypto.randomUUID && crypto.randomUUID()) ||
                    String(Date.now()) + Math.random();

  function getCsrfToken() {
    if (window.CFG_GLPI && CFG_GLPI.csrf_token) return CFG_GLPI.csrf_token;
    const meta = document.querySelector('meta[property="glpi:csrf_token"]')
              || document.querySelector('meta[name="csrf-token"]');
    if (meta && meta.content) return meta.content;
    const inp = document.querySelector('input[name="_glpi_csrf_token"]');
    if (inp && inp.value) return inp.value;
    return '';
  }

  function build() {
    const fab = document.createElement('button');
    fab.id = 'hb-fab';
    fab.title = 'Ayuda';
    fab.textContent = '?';
    document.body.appendChild(fab);

    const panel = document.createElement('div');
    panel.id = 'hb-panel';
    panel.innerHTML =
      '<div id="hb-header">' +
        '<span>Asistente IT</span>' +
        '<span id="hb-close" title="Cerrar">x</span>' +
      '</div>' +
      '<div id="hb-log"></div>' +
      '<form id="hb-form">' +
        '<input id="hb-input" type="text" placeholder="¿En qué te ayudo?" autocomplete="off" maxlength="500"/>' +
        '<button id="hb-send" type="submit" title="Enviar">&gt;</button>' +
      '</form>';
    document.body.appendChild(panel);

    const log    = panel.querySelector('#hb-log');
    const form   = panel.querySelector('#hb-form');
    const input  = panel.querySelector('#hb-input');
    const send   = panel.querySelector('#hb-send');
    const closeB = panel.querySelector('#hb-close');

    function escapeHtml(s) {
      return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
              .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function renderMd(text) {
      // Normalizar: colapsar 3+ saltos a 2, quitar espacios al inicio de línea
      let s = text.replace(/\n{3,}/g, '\n\n').replace(/[ \t]+\n/g, '\n').trim();
      s = escapeHtml(s);
      // links [text](url) - solo http/https para evitar javascript:
      s = s.replace(/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g,
        '<a href="$2" target="_blank" rel="noopener">$1</a>');
      // bold
      s = s.replace(/\*\*([^*\n]+)\*\*/g, '<strong>$1</strong>');
      // inline code
      s = s.replace(/`([^`\n]+)`/g, '<code>$1</code>');
      // bullets: líneas que empiezan con • o - o *
      s = s.replace(/(^|\n)[•\-\*]\s+([^\n]+)/g, '$1<li>$2</li>');
      s = s.replace(/(<li>[^<]+<\/li>(?:\n<li>[^<]+<\/li>)*)/g, '<ul>$1</ul>');
      // limpiar saltos sueltos pegados a <ul>/<li>
      s = s.replace(/\n?<ul>/g, '<ul>').replace(/<\/ul>\n?/g, '</ul>');
      s = s.replace(/<\/li>\n<li>/g, '</li><li>');
      // saltos: doble = párrafo (espacio chico), simple = <br>
      s = s.replace(/\n\n/g, '<br>');
      s = s.replace(/\n/g, '<br>');
      // colapsar <br> repetidos
      s = s.replace(/(<br>\s*){2,}/g, '<br>');
      return s;
    }

    function addMsg(role, text, sources) {
      const div = document.createElement('div');
      div.className = 'hb-msg ' + role;
      const b = document.createElement('div');
      b.className = 'bubble';
      if (role === 'bot') b.innerHTML = renderMd(text);
      else b.textContent = text;
      div.appendChild(b);
      if (sources && sources.length) {
        const s = document.createElement('div');
        s.className = 'hb-sources';
        sources.forEach(src => {
          if (!src || !src.url) return;
          const a = document.createElement('a');
          a.href = src.url;
          a.target = '_blank';
          a.rel = 'noopener';
          a.textContent = '↗ ' + (src.title || src.url);
          s.appendChild(a);
        });
        div.appendChild(s);
      }
      log.appendChild(div);
      log.scrollTop = log.scrollHeight;
      return div;
    }

    addMsg('bot', 'Hola, soy el asistente. Preguntame cómo configurar algo, instalar una impresora, etc.');

    fab.addEventListener('click', () => {
      panel.classList.toggle('open');
      if (panel.classList.contains('open')) input.focus();
    });
    closeB.addEventListener('click', () => panel.classList.remove('open'));

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const q = input.value.trim();
      if (!q) return;

      addMsg('user', q);
      input.value = '';
      input.disabled = true;
      send.disabled = true;

      const typing = addMsg('bot', 'Pensando…');
      typing.querySelector('.bubble').classList.add('hb-typing');

      try {
        const headers = { 'Content-Type': 'application/json' };
        const csrf = getCsrfToken();
        if (csrf) headers['X-Glpi-Csrf-Token'] = csrf;
        const res = await fetch(ENDPOINT, {
          method: 'POST',
          credentials: 'same-origin',
          headers: headers,
          body: JSON.stringify({
            question: q,
            session_id: sessionId,
            user_id:    (window.CFG_GLPI && (CFG_GLPI.glpiID || CFG_GLPI.glpi_id)) || null,
            user_name:  (window.CFG_GLPI && (CFG_GLPI.glpiname || CFG_GLPI.glpi_name)) || null,
            entity_id:  (window.CFG_GLPI && (CFG_GLPI.glpiactive_entity || CFG_GLPI.glpi_active_entity)) ?? null,
            profile_id: (window.CFG_GLPI && (CFG_GLPI.glpiactiveprofile && CFG_GLPI.glpiactiveprofile.id)) || null,
            page: location.pathname
          })
        });

        if (!res.ok) throw new Error('HTTP ' + res.status);
        const data = await res.json();

        typing.remove();
        addMsg('bot', data.answer || 'Sin respuesta.', data.sources || []);
      } catch (err) {
        typing.remove();
        addMsg('bot', 'Error: ' + err.message);
        console.error('[HelpBubble]', err);
      } finally {
        input.disabled = false;
        send.disabled = false;
        input.focus();
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', build);
  } else {
    build();
  }
})();