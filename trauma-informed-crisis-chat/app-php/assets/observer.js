/* Stillwater - observer dashboard. Reads aggregate counters only. */
(function () {
  'use strict';

  var API = window.SW_API || 'api.php';
  var key = '';

  function grab(id) { return document.getElementById(id); }

  function bar(label, value, max, cls) {
    var row = document.createElement('div');
    row.className = 'bar-row' + (cls ? ' ' + cls : '');
    var l = document.createElement('span'); l.textContent = label;
    var w = document.createElement('div'); w.className = 'bar';
    var i = document.createElement('i');
    i.style.width = (max > 0 ? Math.round((value / max) * 100) : 0) + '%';
    w.appendChild(i);
    var v = document.createElement('span'); v.textContent = value; v.style.textAlign = 'right';
    row.appendChild(l); row.appendChild(w); row.appendChild(v);
    return row;
  }

  function card(value, label) {
    var d = document.createElement('div');
    d.className = 'stat-card';
    d.innerHTML = '<div class="value">' + value + '</div><div class="label">' + label + '</div>';
    return d;
  }

  function load() {
    fetch(API + '?action=stats&key=' + encodeURIComponent(key))
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!res.ok) { throw new Error(res.error || 'denied'); }
        render(res.stats);
      })
      .catch(function () {
        grab('lock-msg').textContent = 'That passphrase was not accepted.';
      });
  }

  function render(s) {
    grab('lock').hidden = true;
    grab('dash').hidden = false;

    var accepted = s.escalations.accepted || 0;
    var offered = s.escalations.offered || 0;
    var rate = offered > 0 ? Math.round((accepted / offered) * 100) + '%' : '-';

    var cards = [
      [s.sessions, 'conversations opened'],
      [s.userMessages, 'messages written'],
      [offered, 'times a human was offered'],
      [rate, 'of those offers, taken'],
      [s.groundingRuns, 'grounding exercises used'],
      [s.deletedByUser, 'deleted by the person'],
      [s.keptWords, 'chose to keep their words']
    ];
    cards.forEach(function (c) {
      grab('cards').appendChild(card(c[0], c[1]));
    });

    var tiers = s.tierCounts || {};
    var labels = { steady: 'Tier 0 - steady', distress: 'Tier 1 - distress', high: 'Tier 2 - high concern', imminent: 'Tier 3 - imminent risk' };
    var maxT = Math.max.apply(null, Object.keys(labels).map(function (k) { return tiers[k] || 0; }).concat([1]));
    Object.keys(labels).forEach(function (k) {
      grab('tiers').appendChild(bar(labels[k], tiers[k] || 0, maxT, k === 'high' ? 't2' : (k === 'imminent' ? 't3' : '')));
    });

    var cats = s.topCategories || [];
    var maxC = cats.reduce(function (m, c) { return Math.max(m, c.n); }, 1);
    if (!cats.length) {
      grab('cats').innerHTML = '<p class="small">No conversations recorded yet.</p>';
    } else {
      cats.forEach(function (c) {
        grab('cats').appendChild(bar(c.category.replace(/_/g, ' '), c.n, maxC));
      });
    }

    var e = s.escalations;
    var maxE = Math.max(e.offered || 0, e.accepted || 0, e.declined || 0, e.open || 0, 1);
    grab('esc').appendChild(bar('offered', e.offered || 0, maxE));
    grab('esc').appendChild(bar('taken up', e.accepted || 0, maxE));
    grab('esc').appendChild(bar('declined, stayed in chat', e.declined || 0, maxE));
    grab('esc').appendChild(bar('still deciding', e.open || 0, maxE));
  }

  grab('unlock').addEventListener('click', function () {
    key = grab('key').value.trim();
    if (key) { load(); }
  });

  grab('key').addEventListener('keydown', function (e) {
    if (e.key === 'Enter') { grab('unlock').click(); }
  });
})();
