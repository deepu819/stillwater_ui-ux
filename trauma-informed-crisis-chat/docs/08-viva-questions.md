# 08 - Likely viva questions, with answers

Short answers that are true of this code. Useful to rehearse before the review.

**"So you made a chatbot?"**
Not quite. There is no language model and no generated text. Every sentence the interface can say is a
pre-written, tone-checked line in `rules.json`. The project is an interface design - tone, escalation,
anonymity - and the engine is deliberately small so those properties can be proven rather than hoped for.

**"Why not use ChatGPT / an LLM? It would sound more natural."**
Three reasons. It cannot be audited for tone: the same sentence can produce a kind reply or a harmful
one. It cannot be tested: the same input gives different outputs. And it means the most private thing a
person has typed leaves the machine. For a crisis interface, predictability and privacy beat fluency.

**"What happens if someone types 'I want to die'?"**
Tier 3. Two short holding sentences first, so the person is not answered with a phone number and left
alone, then one offer: helplines, or stay. It is offered once. If they decline, the interface says
"That's completely fine. I'm still here," and never raises it again - the header button stays.

**"Why offer instead of just showing the helpline?"**
Because a redirect abandons the person at the moment they took the largest risk. The offer keeps the
conversation alive either way. And the decline is the same size as the accept, or consent is theatre.

**"Where is the emotional intelligence? You only match keywords."**
Correct - it is a lexicon with a negation guard, not sentiment analysis. Two honest limits: it will miss
sarcasm and unusual phrasing, and it will occasionally over-trigger. Over-triggering is the safer
failure for this product, and the design absorbs it: a false positive produces one gentle offer, not a
label or a lockout. Multilingual coverage (Hindi/Kannada/Tamil transliteration) is included because the
students this is built for do not all type in English.

**"How is the identity anonymous?"**
No name, email, phone, OTP or cookie. The session id is 128 random bits held in the page's memory, so a
refresh is a new person and closing the tab ends it. The alias is generated from word lists. There is no
IP column in the schema and the app does not log it.

**"But you do store the messages, in `messages`."**
Only if the person turns on "keep my words". By default `body` is `NULL` - the row exists for counting
and for the tier, the sentence does not exist. You can check it in phpMyAdmin: open `messages` after a
default session and `body` is empty.

**"What does the delete button actually do?"**
Hard delete. `DELETE FROM messages`, `DELETE FROM risk_events`, and the escalation/grounding rows keep
only an aggregate count with `session_id` overwritten to `'deleted'`. The alias is overwritten too. It is
not a soft-delete flag - except for `sessions.deleted_at`, which exists purely to count how many people
used the delete button.

**"How is escalation tested?"**
`escalations.accepted` records 1 / 0 / NULL per offer, and the observer dashboard reports offered, taken,
declined and undecided. So statements like "most people declined and stayed in the chat" are measured,
not asserted.

**"What if the person is in immediate danger?"**
Tier 3, and the helpline list includes 112 for the person or someone near them. The prototype never
claims to dispatch help and never says a call is being made on the user's behalf - that would be a lie
with a real cost.

**"Why is there no red anywhere? Isn't risk supposed to look urgent?"**
Red is a stress signal. The people using this are already stressed; adding alarm colours raises arousal
and narrows thinking, which is the opposite of what helps. The escalation card is warm clay, and the
wording carries the seriousness instead of the colour.

**"What is the biggest weakness of the project?"**
Two. The lexicon does not understand context - a message like "i'm fine, my friend is the one who wants
to die" is scored as the user's own risk. And there is no real human at the other end of the handoff; the
project models escalation honestly instead of faking a call centre. Both are stated as limitations in
the report rather than papered over.

**"What would you do with two more weeks?"**
A human-reviewed handoff queue with a real counsellor account, a proper screen-reader audit, and
extending the lexicon with a native speaker for each language rather than transliteration.
