/* ============================================================
   Stillwater - chat behaviour
   Everything the guide *says* comes from the server (data/rules.json).
   This file only decides layout, timing and which control was pressed.
   ============================================================ */
(function () {
  'use strict';

  var API = window.SW_API || 'api.php';

  var thread   = document.getElementById('thread');
  var scroll   = document.getElementById('scroller');
  var form     = document.getElementById('composer');
  var input    = document.getElementById('input');
  var sendBtn  = document.getElementById('send');
  var chipsBox = document.getElementById('chips');
  var aliasEl  = document.getElementById('alias');
  var live     = document.getElementById('live');

  var state = {
    sessionId: null,
    alias: '',
    busy: false,
    keepWords: false,
    helplines: [],
    ended: false
  };

  var REDUCED = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ------------------------------------------------------------ helpers */

  function api(action, payload) {
    return fetch(API, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(Object.assign({ action: action }, payload || {}))
    }).then(function (r) {
      if (!r.ok) { throw new Error('http ' + r.status); }
      return r.json();
    });
  }

  function scrollDown(smooth) {
    requestAnimationFrame(function () {
      scroll.scrollTo({ top: scroll.scrollHeight, behavior: smooth && !REDUCED ? 'smooth' : 'auto' });
    });
  }

  function wait(ms) {
    return new Promise(function (res) { setTimeout(res, REDUCED ? 40 : ms); });
  }

  function addBubble(role, text) {
    var wrap = document.createElement('div');
    wrap.className = 'msg msg-' + role;

    // Repeating "STILLWATER" above every bubble is noise, so the speaker is
    // named only when the speaker changes.
    var last = thread.lastElementChild;
    var sameSpeaker = last && last.classList && last.classList.contains('msg-' + role);
    if (!sameSpeaker) {
      var who = document.createElement('div');
      who.className = 'msg-who';
      who.textContent = role === 'user' ? state.alias : 'stillwater';
      wrap.appendChild(who);
    }

    var b = document.createElement('div');
    b.className = 'bubble';
    b.textContent = text;
    wrap.appendChild(b);

    thread.appendChild(wrap);
    scrollDown(true);
    return wrap;
  }

  function showTyping() {
    var wrap = document.createElement('div');
    wrap.className = 'msg msg-guide';
    wrap.id = 'typing';
    var b = document.createElement('div');
    b.className = 'bubble';
    b.innerHTML = '<span class="typing" aria-hidden="true"><span></span><span></span><span></span></span>';
    wrap.appendChild(b);
    thread.appendChild(wrap);
    scrollDown(true);
  }

  function hideTyping() {
    var t = document.getElementById('typing');
    if (t) { t.remove(); }
  }

  function setChips(list) {
    chipsBox.innerHTML = '';
    (list || []).forEach(function (label) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'chip';
      b.textContent = label;
      b.addEventListener('click', function () { onChip(label); });
      chipsBox.appendChild(b);
    });
  }

  function announce(text) {
    if (live) { live.textContent = text; }
  }

  /* ------------------------------------------------------- boot the chat */

  api('start').then(function (res) {
    state.sessionId = res.session.id;
    state.alias = res.session.alias;
    state.keepWords = res.session.keepWords;
    state.helplines = res.helplines || [];

    aliasEl.textContent = 'you are ' + state.alias;
    var dayStart = document.createElement('p');
    dayStart.className = 'day-start';
    dayStart.textContent = 'Nothing here is being kept about you unless you ask.';
    thread.appendChild(dayStart);

    fillGrounding();

    return (res.guide || []).reduce(function (p, g) {
      return p.then(function () { addBubble('guide', g.text); });
    }, Promise.resolve());
  }).then(function () {
    setChips(["I don't know what to say", "I'd like to breathe first", 'Show me who I can talk to']);
    scrollDown(false);
    if (input) { input.focus(); }
  }).catch(function () {
    addBubble('guide', "I can't reach the server right now. Nothing you have written was saved. Reloading this page will start a fresh, private conversation.");
  });

  /* ------------------------------------------------------------ sending */

  function send(text) {
    text = (text || '').trim();
    if (!text || state.busy || state.ended) { return; }

    state.busy = true;
    sendBtn.disabled = true;
    setChips([]);
    addBubble('user', text);
    input.value = '';
    input.style.height = 'auto';
    showTyping();

    api('message', { sessionId: state.sessionId, text: text })
      .then(function (res) {
        hideTyping();
        if (!res.ok) { throw new Error('bad'); }
        var replies = res.replies || [];
        return replies.reduce(function (p, r) {
          return p.then(function () {
            addBubble('guide', r.text);
            announce(r.text);
            return wait(replies.length > 1 ? 900 : 450);
          });
        }, Promise.resolve()).then(function () {
          if (res.escalate && res.offer) {
            return offerEscalation(res.offer, res.tier);
          }
          setChips(res.chips);
        });
      })
      .catch(function () {
        hideTyping();
        addBubble('guide', "Something on my side went quiet. Your words are still on your screen. You can try sending again, or stay with the breathing exercise.");
        setChips(["I'd like to breathe first", 'Show me who I can talk to']);
      })
      .then(function () {
        state.busy = false;
        sendBtn.disabled = input.value.trim() === '';
      });
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    send(input.value);
  });

  input.addEventListener('input', function () {
    sendBtn.disabled = input.value.trim() === '' || state.busy;
    input.style.height = 'auto';
    input.style.height = Math.min(input.scrollHeight, 168) + 'px';
  });

  input.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      send(input.value);
    }
  });

  /* -------------------------------------------------------- the "offer" */

  var inlineOffer = null;

  function offerEscalation(copy, tier) {
    if (inlineOffer) { inlineOffer.remove(); }
    inlineOffer = document.createElement('div');
    inlineOffer.className = 'msg msg-guide';
    inlineOffer.style.maxWidth = '96%';

    var wrap = document.createElement('div');
    wrap.style.cssText = 'background:var(--clay-soft);border:1px solid rgba(176,122,94,.22);border-radius:20px;border-bottom-left-radius:7px;padding:1rem 1.1rem';

    var p = document.createElement('p');
    p.style.margin = '0 0 .8rem';
    p.style.fontSize = '.96rem';
    p.textContent = copy;
    wrap.appendChild(p);

    var row = document.createElement('div');
    row.className = 'chips';
    row.style.margin = '0';

    function mk(label, cls, fn) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'chip' + (cls ? ' ' + cls : '');
      b.textContent = label;
      b.addEventListener('click', fn);
      row.appendChild(b);
      return b;
    }

    mk('Show me who I can talk to', '', function () { openHelplines(true); });
    mk('Stay here with me', '', function () {
      inlineOffer.remove();
      inlineOffer = null;
      api('escalate', { sessionId: state.sessionId, accepted: false }).catch(function () {});
      addBubble('guide', "We can stay here. I'm not going anywhere.");
    });
    mk('Not right now', '', function () {
      inlineOffer.remove();
      inlineOffer = null;
      api('escalate', { sessionId: state.sessionId, accepted: false }).catch(function () {});
      addBubble('guide', "That's completely fine. I'm still here.");
    });

    wrap.appendChild(row);
    inlineOffer.appendChild(wrap);
    thread.appendChild(inlineOffer);
    scrollDown(true);
    announce(copy);
  }

  /* ------------------------------------------------------------- sheets */

  function openSheet(id) {
    var sheet = document.getElementById(id);
    if (!sheet) { return; }
    sheet.classList.add('open');
    var focusable = sheet.querySelector('button, a, input');
    setTimeout(function () { if (focusable) { focusable.focus(); } }, 260);
    document.body.style.overflow = 'hidden';
  }

  function closeSheet(id) {
    var sheet = document.getElementById(id);
    if (!sheet) { return; }
    sheet.classList.remove('open');
    document.body.style.overflow = '';
  }

  document.querySelectorAll('[data-close]').forEach(function (btn) {
    btn.addEventListener('click', function () { closeSheet(btn.getAttribute('data-close')); });
  });

  document.querySelectorAll('.sheet-backdrop').forEach(function (bd) {
    bd.addEventListener('click', function (e) {
      if (e.target === bd) { closeSheet(bd.id); }
    });
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      document.querySelectorAll('.sheet-backdrop.open').forEach(function (s) { closeSheet(s.id); });
    }
  });

  /* --------------------------------------------------------- helplines */

  function renderHelplines(target) {
    target.innerHTML = '';
    (state.helplines || []).forEach(function (h) {
      var a = document.createElement('a');
      a.className = 'helpline';
      a.href = 'tel:' + (h.dial || h.number);

      var n = document.createElement('div');
      n.className = 'name';
      n.textContent = h.name;
      a.appendChild(n);

      var num = document.createElement('div');
      num.className = 'num';
      num.textContent = h.number;
      a.appendChild(num);

      var meta = document.createElement('div');
      meta.className = 'meta';
      meta.textContent = h.hours + ' - ' + h.note;
      a.appendChild(meta);

      target.appendChild(a);
    });
  }

  function openHelplines(markAccepted) {
    renderHelplines(document.getElementById('helpline-list'));
    openSheet('helpline-sheet');
    if (markAccepted) {
      api('escalate', { sessionId: state.sessionId, accepted: true }).catch(function () {});
    }
  }

  /* --------------------------------------------------------- grounding */

  function fillGrounding() {
    var list = document.getElementById('ground-steps');
    if (!list || list.children.length) { return; }
    api('meta').then(function (res) {
      (res.grounding.steps || []).forEach(function (step) {
        var li = document.createElement('li');
        li.textContent = step;
        list.appendChild(li);
      });
    }).catch(function () {});
  }

  var breathingTimer = null;

  function startBreathing() {
    var label = document.getElementById('pacer-label');
    if (!label) { return; }
    var cycle = [
      ['Breathe in', 4000],
      ['Hold, gently', 2000],
      ['Breathe out, slower', 6000]
    ];
    var i = 0;
    function step() {
      label.textContent = cycle[i][0];
      breathingTimer = setTimeout(function () {
        i = (i + 1) % cycle.length;
        step();
      }, cycle[i][1]);
    }
    clearTimeout(breathingTimer);
    step();
    api('grounding', { sessionId: state.sessionId, kind: 'breathing' }).catch(function () {});
  }

  function openGrounding() {
    openSheet('ground-sheet');
    startBreathing();
  }

  /* ---------------------------------------------------------- chip logic */

  function onChip(label) {
    var l = label.toLowerCase();
    if (l.indexOf('show me who') === 0 || l.indexOf('show me help') === 0) { openHelplines(true); return; }
    if (l.indexOf('not right now') === 0) {
      api('escalate', { sessionId: state.sessionId, accepted: false }).catch(function () {});
      addBubble('guide', "That's completely fine. I'm still here.");
      setChips(["I don't know what to say", "I'd like to breathe first"]);
      return;
    }
    if (l.indexOf('breathe') > -1) { openGrounding(); return; }
    send(label);
  }

  /* ------------------------------------------------------------- header */

  var human = document.getElementById('human');
  if (human) { human.addEventListener('click', function () { openHelplines(true); }); }

  var breatheBtn = document.getElementById('breathe');
  if (breatheBtn) { breatheBtn.addEventListener('click', openGrounding); }

  var groundDone = document.getElementById('ground-done');
  if (groundDone) {
    groundDone.addEventListener('click', function () {
      clearTimeout(breathingTimer);
      closeSheet('ground-sheet');
      api('grounding', { sessionId: state.sessionId, kind: 'breathing-complete' }).catch(function () {});
      addBubble('guide', "That's enough for now. Nothing else has to happen today.");
    });
  }

  var groundRestart = document.getElementById('ground-restart');
  if (groundRestart) { groundRestart.addEventListener('click', startBreathing); }

  /* --------------------------------------------------------------- pause */

  var pauseBtn = document.getElementById('pause');
  var pauseState = document.getElementById('pause-state');
  var chatState = document.getElementById('chat-state');

  if (pauseBtn) {
    pauseBtn.addEventListener('click', function () {
      pauseState.hidden = false;
      chatState.hidden = true;
    });
  }

  var resumeBtn = document.getElementById('resume');
  if (resumeBtn) {
    resumeBtn.addEventListener('click', function () {
      pauseState.hidden = true;
      chatState.hidden = false;
      input.focus();
    });
  }

  /* -------------------------------------------------------------- ending */

  var endBtn = document.getElementById('end-chat');
  var deleteBtn = document.getElementById('delete-chat');
  var exitState = document.getElementById('exit-state');
  var deleteState = document.getElementById('delete-state');

  if (endBtn) {
    endBtn.addEventListener('click', function () {
      state.ended = true;
      api('end', { sessionId: state.sessionId, delete: false }).catch(function () {});
      chatState.hidden = true;
      pauseState.hidden = true;
      exitState.hidden = false;
    });
  }

  if (deleteBtn) {
    deleteBtn.addEventListener('click', function () {
      state.ended = true;
      api('end', { sessionId: state.sessionId, delete: true }).catch(function () {});
      chatState.hidden = true;
      pauseState.hidden = true;
      exitState.hidden = true;
      deleteState.hidden = false;
    });
  }

  var keepToggle = document.getElementById('keep-words');
  if (keepToggle) {
    keepToggle.addEventListener('change', function () {
      api('keep_words', { sessionId: state.sessionId, keep: keepToggle.checked })
        .then(function (res) {
          state.keepWords = res.keepWords;
          addBubble('guide', res.keepWords
            ? 'Thank you. I will keep the words you write from now on, so you could come back to them. You can turn this off at any time.'
            : 'Done. From now on I keep nothing you write. Only the fact that someone talked to me today.');
        })
        .catch(function () {});
    });
  }
})();
