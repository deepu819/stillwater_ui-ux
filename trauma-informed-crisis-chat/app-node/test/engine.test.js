/**
 * Behaviour tests for the Node build:  npm test
 * Mirrors app-php/tools/test-cases.php so both backends are held to the same
 * promises. No framework, no dependencies - node:assert and node:test.
 */
'use strict';

const test = require('node:test');
const assert = require('node:assert');
const engine = require('../lib/engine');

const session = (over = {}) => Object.assign(
  { id: 'test', alias: 'test-alias', msg_count: 1, tier_max: 0, recent: '[]' },
  over
);

const text = (res) => res.rendered.map((r) => r.text).join(' ');
const one = (input, over) => engine.respond(session(over), input);

test('the opening line carries no question', () => {
  engine.RULES.replies.first_contact.forEach((line) => {
    assert.ok(!line.text.includes('?'), 'a question in the opening line is a demand');
  });
});

test('loneliness is met with company, not advice', () => {
  const res = one('i am alone and nobody talks to me');
  assert.strictEqual(res.tier, 1);
  assert.strictEqual(res.category, 'isolation');
});

test('explicit wish to die escalates', () => {
  const res = one('i want to die');
  assert.strictEqual(res.tier, 3);
  assert.strictEqual(res.escalate, true);
});

test('indirect phrasing is caught', () => {
  assert.ok(one('everyone would be better off dead').tier >= 2);
});

test('kannada-transliterated risk is caught', () => {
  assert.strictEqual(one('nanna jeevana waste, saaybeku anstide').tier, 3);
});

test('negation is not read as risk', () => {
  const res = one("i don't want to die, i just want the pain to stop");
  assert.strictEqual(res.tier, 0);
  assert.strictEqual(res.negatedRisk, true);
});

test('escalation is offered once, not repeatedly', () => {
  assert.strictEqual(one('i want to die', { tier_max: 3 }).escalate, false);
});

test('asking for a human always works', () => {
  assert.strictEqual(one('can i talk to a counsellor').escalate, true);
});

test('not knowing what to say is a valid input', () => {
  assert.strictEqual(one("i don't know what to say").category, 'stuck');
});

test('study pressure is recognised', () => {
  const res = one('my marks are bad and my parents keep shouting');
  assert.strictEqual(res.tier, 1);
  assert.strictEqual(res.category, 'academic_pressure');
});

test('a neutral message is not a crisis', () => {
  const res = one('okay');
  assert.strictEqual(res.tier, 0);
  assert.strictEqual(res.escalate, false);
});

test('every reply id exists in rules.json', () => {
  const ids = new Set();
  Object.values(engine.RULES.replies).forEach((bank) => bank.forEach((r) => ids.add(r.id)));
  ['i want to die', 'hi', 'thanks', 'i am alone'].forEach((m) => {
    engine.respond(session(), m).replyIds.forEach((id) => assert.ok(ids.has(id), id));
  });
});
