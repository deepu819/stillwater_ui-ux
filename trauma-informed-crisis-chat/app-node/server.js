/**
 * Stillwater - Node/Express build.
 *
 * Same interface, same rules.json, same API shape as the PHP/XAMPP build.
 * The PHP build is the one used for the college submission (it runs in XAMPP);
 * this one exists so the project can be shown on a free host without PHP.
 *
 * Storage: a single JSON file on disk (data/runtime.json), written on change.
 * No native modules, so `npm install` works on any machine without build tools.
 */
'use strict';

const express = require('express');
const fs = require('fs');
const path = require('path');
const crypto = require('crypto');
const engine = require('./lib/engine');

const PORT = process.env.PORT || 3000;
const OBSERVER_KEY = process.env.OBSERVER_KEY || 'stillwater-demo';
const STORE_PATH = path.join(__dirname, 'data', 'runtime.json');

const HELPLINES = [
  { name: 'Tele-MANAS (Govt. of India)', number: '14416', dial: '14416', hours: '24 x 7', note: 'Free. Available in multiple Indian languages.' },
  { name: 'KIRAN Mental Health Helpline', number: '1800-599-0019', dial: '18005990019', hours: '24 x 7', note: 'Free. 13 Indian languages.' },
  { name: 'AASRA', number: '+91 98204 66726', dial: '+919820466726', hours: '24 x 7', note: 'Confidential, English and Hindi.' },
  { name: 'iCall (TISS)', number: '+91 91529 87821', dial: '+919152987821', hours: 'Mon to Sat, 10am - 8pm', note: 'Free counselling by trained volunteers.' },
  { name: 'Emergency services', number: '112', dial: '112', hours: '24 x 7', note: 'If you or someone near you is in immediate danger.' },
];

/* --------------------------------------------------------------- storage */

let store = { sessions: {}, messages: [], riskEvents: [], escalations: [], grounding: [] };

function load() {
  try {
    store = JSON.parse(fs.readFileSync(STORE_PATH, 'utf8'));
  } catch (e) {
    store = { sessions: {}, messages: [], riskEvents: [], escalations: [], grounding: [] };
  }
}

let saveTimer = null;
function save() {
  clearTimeout(saveTimer);
  saveTimer = setTimeout(() => {
    try {
      fs.mkdirSync(path.dirname(STORE_PATH), { recursive: true });
      fs.writeFileSync(STORE_PATH, JSON.stringify(store));
    } catch (e) {
      console.error('could not persist store:', e.message);
    }
  }, 50);
}

const now = () => new Date().toISOString().slice(0, 19).replace('T', ' ');

/* ------------------------------------------------------------------- app */

const app = express();
app.use(express.json({ limit: '16kb' }));
app.use(express.static(path.join(__dirname, 'public'), { extensions: ['html'] }));

const ok = (res, payload) => res.json(Object.assign({ ok: true }, payload));

function hStart(req, res) {
  const id = crypto.randomBytes(16).toString('hex');
  const session = {
    id,
    alias: engine.alias(),
    consent: 1,
    keep_words: req.body && req.body.keepWords ? 1 : 0,
    tier_max: 0,
    msg_count: 0,
    recent: '[]',
    created_at: now(),
    last_seen: now(),
    ended_at: null,
    deleted_at: null,
  };
  store.sessions[id] = session;
  save();

  ok(res, {
    session: { id, alias: session.alias, keepWords: Boolean(session.keep_words), startedAt: session.created_at },
    guide: engine.render([engine.pickReply('first_contact', []).id]),
    helplines: HELPLINES,
  });
}

function hMessage(req, res) {
  const { sessionId, text } = req.body || {};
  const session = store.sessions[sessionId];
  if (!session || session.deleted_at) {
    return res.status(404).json({ ok: false, error: 'session_not_found' });
  }

  const clean = String(text || '').trim().slice(0, 2000);
  if (!clean) return ok(res, { replies: [], tier: 0 });

  const result = engine.respond(session, clean);
  const at = now();
  const body = Number(session.keep_words) === 1 ? clean : null;

  store.messages.push({ sessionId, role: 'user', body, category: result.category, tier: result.tier, at });
  for (const item of result.rendered) {
    store.messages.push({ sessionId, role: 'guide', body: body === null ? null : item.text, category: result.category, tier: result.tier, at });
  }
  if (result.tier > 0) {
    store.riskEvents.push({ sessionId, category: result.category, tier: result.tier, at });
  }

  session.tier_max = Math.max(Number(session.tier_max), result.tier);
  session.msg_count = Number(session.msg_count) + 1;
  session.recent = JSON.stringify(result.recent);
  session.last_seen = at;

  if (result.escalate) {
    store.escalations.push({ sessionId, tier: Math.max(1, result.tier), shown_at: at, accepted: null, resolved_at: null });
  }
  save();

  ok(res, {
    replies: result.rendered,
    tier: result.tier,
    tierLabel: result.tierLabel,
    escalate: result.escalate,
    grounding: result.grounding,
    chips: result.chips,
    offer: result.escalate
      ? (result.tier >= 3 ? engine.RULES.escalation.copy.offer_tier3 : engine.RULES.escalation.copy.offer_tier2)
      : null,
    helplines: result.escalate ? HELPLINES : null,
  });
}

function hKeepWords(req, res) {
  const session = store.sessions[(req.body || {}).sessionId];
  if (!session) return res.status(404).json({ ok: false, error: 'session_not_found' });
  session.keep_words = req.body.keep ? 1 : 0;
  save();
  ok(res, { keepWords: Boolean(session.keep_words) });
}

function hEscalate(req, res) {
  const session = store.sessions[(req.body || {}).sessionId];
  if (!session) return res.status(404).json({ ok: false, error: 'session_not_found' });

  const accepted = Boolean((req.body || {}).accepted);
  const open = store.escalations.filter((e) => e.sessionId === session.id && e.accepted === null).pop();
  if (open) {
    open.accepted = accepted ? 1 : 0;
    open.resolved_at = now();
  } else {
    store.escalations.push({ sessionId: session.id, tier: 1, shown_at: now(), accepted: accepted ? 1 : 0, resolved_at: now() });
  }
  save();

  const copy = engine.RULES.escalation.copy;
  ok(res, {
    replies: [{ id: accepted ? 'esc-yes' : 'esc-no', text: accepted ? copy.accepted : copy.declined }],
    helplines: accepted ? HELPLINES : null,
  });
}

function hGrounding(req, res) {
  const session = store.sessions[(req.body || {}).sessionId];
  if (session) {
    store.grounding.push({ sessionId: session.id, kind: String((req.body || {}).kind || 'breathing').slice(0, 40), at: now() });
    save();
  }
  ok(res, {});
}

function hEnd(req, res) {
  const { sessionId, delete: del } = req.body || {};
  const session = store.sessions[sessionId];
  if (!session) return ok(res, { deleted: false });

  if (del) {
    store.messages = store.messages.filter((m) => m.sessionId !== sessionId);
    store.riskEvents = store.riskEvents.filter((r) => r.sessionId !== sessionId);
    store.escalations = store.escalations.map((e) => (e.sessionId === sessionId ? Object.assign({}, e, { sessionId: 'deleted' }) : e));
    store.grounding = store.grounding.map((g) => (g.sessionId === sessionId ? Object.assign({}, g, { sessionId: 'deleted' }) : g));
    session.deleted_at = now();
    session.alias = 'deleted';
    session.recent = '[]';
  } else {
    session.ended_at = now();
  }
  save();
  ok(res, { deleted: Boolean(del), closing: engine.RULES.escalation.copy.still_here });
}

function hMeta(req, res) {
  ok(res, {
    app: { name: 'Stillwater', version: '1.0.0' },
    helplines: HELPLINES,
    grounding: engine.RULES.grounding,
    chips: engine.RULES.escalation.chips,
  });
}

function hHelplines(req, res) {
  ok(res, { helplines: HELPLINES });
}

function hStats(req, res) {
  if ((req.query.key || '') !== OBSERVER_KEY) {
    return res.status(403).json({ ok: false, error: 'forbidden' });
  }

  const live = Object.values(store.sessions).filter((s) => !s.deleted_at);
  const tierCounts = { steady: 0, distress: 0, high: 0, imminent: 0 };
  const names = ['steady', 'distress', 'high', 'imminent'];
  for (const s of live) {
    tierCounts[names[Number(s.tier_max) || 0]] += 1;
  }

  const esc = { offered: store.escalations.length, accepted: 0, declined: 0, open: 0 };
  for (const e of store.escalations) {
    if (e.accepted === null) esc.open += 1;
    else if (Number(e.accepted) === 1) esc.accepted += 1;
    else esc.declined += 1;
  }

  const counts = {};
  for (const r of store.riskEvents) counts[r.category] = (counts[r.category] || 0) + 1;
  const topCategories = Object.keys(counts)
    .map((k) => ({ category: k, n: counts[k] }))
    .sort((a, b) => b.n - a.n)
    .slice(0, 8);

  ok(res, {
    stats: {
      sessions: live.length,
      userMessages: store.messages.filter((m) => m.role === 'user').length,
      deletedByUser: Object.values(store.sessions).filter((s) => s.deleted_at).length,
      keptWords: live.filter((s) => Number(s.keep_words) === 1).length,
      tierCounts,
      escalations: esc,
      groundingRuns: store.grounding.length,
      topCategories,
    },
  });
}

/* One endpoint, one contract:  POST /api  with { action: '...' }  - identical to
   api.php, so the front-end does not know or care which backend it is talking to. */
app.post('/api', (req, res) => {
  const action = String(req.query.action || (req.body && req.body.action) || '');
  const routes = {
    start: hStart,
    message: hMessage,
    keep_words: hKeepWords,
    escalate: hEscalate,
    grounding: hGrounding,
    end: hEnd,
    meta: hMeta,
    helplines: hHelplines,
  };
  const handler = routes[action];
  if (!handler) return res.status(400).json({ ok: false, error: 'unknown_action' });
  handler(req, res);
});

app.get('/api', (req, res) => {
  const action = String(req.query.action || 'meta');
  if (action === 'stats') return hStats(req, res);
  if (action === 'helplines') return hHelplines(req, res);
  return hMeta(req, res);
});

load();
app.listen(PORT, () => {
  console.log(`Stillwater (Node build) listening on http://localhost:${PORT}`);
});
