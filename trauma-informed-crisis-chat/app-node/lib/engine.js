/**
 * Node port of includes/engine.php.
 * Same rules.json, same decisions, same order of checks - the two backends are
 * meant to answer identically, which is itself a testable claim
 * (see docs/06-test-cases.md).
 */
'use strict';

const fs = require('fs');
const path = require('path');

const RULES = JSON.parse(fs.readFileSync(path.join(__dirname, '..', 'data', 'rules.json'), 'utf8'));

const TIER_LABELS = { 0: 'steady', 1: 'distress', 2: 'high concern', 3: 'imminent risk' };

function normalise(text) {
  return ' ' + String(text)
    .toLowerCase()
    .replace(/[\u2018\u2019]/g, "'")
    .replace(/[^\p{L}\p{N}' ]+/gu, ' ')
    .replace(/\s+/g, ' ')
    .trim() + ' ';
}

function isNegated(norm, index) {
  const before = norm.slice(0, index).trim();
  const words = before ? before.split(/\s+/) : [];
  const window = RULES.negation_window || 4;
  return words.slice(Math.max(0, words.length - window))
    .some((w) => RULES.negation_terms.includes(w));
}

function match(norm) {
  const hits = [];
  for (const cat of RULES.categories) {
    let best = null;
    for (const term of cat.terms) {
      const at = norm.indexOf(' ' + term + ' ');
      if (at === -1) continue;
      const negated = isNegated(norm, at);
      if (best === null || (best.negated && !negated)) best = { term, negated };
      if (!negated) break;
    }
    if (best) {
      hits.push({
        id: cat.id,
        tier: Number(cat.tier),
        label: cat.label,
        term: best.term,
        negated: best.negated,
        effective: best.negated ? 0 : Number(cat.tier),
      });
    }
  }
  return hits;
}

function primary(hits) {
  let chosen = null;
  for (const hit of hits) {
    if (chosen === null || hit.effective > chosen.effective) chosen = hit;
  }
  return chosen;
}

function pickReply(bank, recent) {
  const pool = RULES.replies[bank] || RULES.replies.reflective;
  const unused = pool.filter((r) => !recent.includes(r.id));
  return (unused.length ? unused : pool)[0];
}

function chips(tier, category) {
  if (tier >= 2 || category === 'help_seeking') return RULES.escalation.chips.slice(0, 4);
  if (['stuck', 'reflective', 'tired'].includes(category)) {
    return ["I don't know what to say", 'Just stay with me', "I'd like to breathe first"];
  }
  return ["I don't know what to say", "I'd like to breathe first", 'Show me who I can talk to'];
}

const INTENT_BANKS = ['meta', 'stuck', 'thanks', 'goodbye', 'feeling_better', 'help_seeking', 'stay'];

function respond(session, text) {
  const norm = normalise(text);
  const hits = match(norm);
  const top = primary(hits);
  const recent = JSON.parse(session.recent || '[]');

  let tier = top ? top.effective : 0;
  let category = top ? top.id : 'reflective';

  for (const hit of hits) {
    if (hit.id === 'help_seeking' && hit.effective === 0) category = 'help_seeking';
  }

  const replyIds = [];

  // The opening line is sent once by /api start, so a turn never replays it.

  const negatedCrisis = hits.some((h) => h.negated && h.tier >= 2);

  let bank;
  if (negatedCrisis && tier === 0) bank = 'negated_risk';
  else if (INTENT_BANKS.includes(category)) bank = category;
  else bank = category === 'reflective' ? 'reflective' : category;

  const pick = pickReply(bank, recent);
  replyIds.push(pick.id);

  if (['imminent_risk', 'self_harm_urge', 'hopelessness'].includes(bank)) {
    replyIds.push(pickReply('imminent_risk_follow', recent).id);
  }

  const alreadyOffered = Number(session.tier_max || 0) >= 2;
  let escalate = false;
  if (tier >= 3) escalate = !alreadyOffered;
  else if (tier === 2 && !alreadyOffered) escalate = true;
  if (category === 'help_seeking') escalate = true;

  const rendered = render(replyIds);
  const merged = [...new Set([...recent, ...replyIds])].slice(-12);

  return {
    replyIds,
    rendered,
    recent: merged,
    tier,
    tierLabel: TIER_LABELS[tier],
    category,
    escalate,
    grounding: tier >= 1,
    chips: chips(tier, category),
    negatedRisk: negatedCrisis,
  };
}

function render(ids) {
  const all = {};
  for (const bank of Object.keys(RULES.replies)) {
    for (const item of RULES.replies[bank]) all[item.id] = item.text;
  }
  return ids.filter((id) => all[id]).map((id) => ({ id, text: all[id] }));
}

function alias() {
  const adj = ['quiet', 'soft', 'slow', 'open', 'gentle', 'steady', 'clear', 'warm',
    'small', 'early', 'still', 'low', 'patient', 'kind', 'plain', 'green'];
  const noun = ['river', 'lantern', 'meadow', 'harbour', 'pebble', 'window', 'orchard',
    'cloud', 'pine', 'shore', 'field', 'kettle', 'corner', 'shelf', 'bench', 'path'];
  const n = (max) => Math.floor(Math.random() * max);
  return `${adj[n(adj.length)]}-${noun[n(noun.length)]}-${String(10 + n(90))}`;
}

module.exports = { RULES, respond, render, alias, normalise, match, pickReply };
