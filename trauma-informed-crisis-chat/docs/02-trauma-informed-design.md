# 02 - Trauma-informed design: principle to pixel

"Trauma-informed" is a specific framework (SAMHSA, 2014). It assumes that **any** user may already
have experienced harm - including harm from services that were supposed to help - so the system is
designed to avoid repeating that harm.

This document is the mapping the report needs: each principle, the risk it addresses, and the exact
element in this project that implements it.

## The six principles

### 1. Safety
*Risk it addresses:* sensory and emotional overload. Bright red banners, alarms, sounds and flashing
indicators recreate the feeling of an emergency.

**Implemented as**
- A single warm palette (paper, sage, clay). There is **no red anywhere in the interface**, including
  at the highest risk tier - the escalation card is soft clay, not an alarm.
- No sounds, no vibration, no auto-focus stealing, no modal that traps the cursor.
- Motion is slow (320-600 ms) and fully disabled under `prefers-reduced-motion`.
- The escalation message is phrased as company, not as an alert: *"I want you to have more than I can give."*

### 2. Trustworthiness and transparency
*Risk it addresses:* betrayal. A person who has been let down by a service expects to be lied to.

**Implemented as**
- The landing page has a "What happens to what I write?" panel that lists, in plain language, what is
  stored and what is not - before the user types anything.
- The chat's first line on screen reads: *"Nothing here is being kept about you unless you ask."*
- Data minimisation is real, not claimed: `messages.body` is `NULL` unless the person turns on
  *keep my words* (see `05-anonymity-policy.md`).
- The bot never pretends to be a human. Asking "are you a bot" returns: *"I'm a simple text-based
  guide, not a person and not a therapist."*

### 3. Peer support and mutuality
*Risk it addresses:* the coldness of clinical language, which signals "you are a case".

**Implemented as**
- Every reply is written in first person, conversational register: *"I'm here."*, *"I'm not going anywhere."*
- Length limit of about 320 characters per bubble (enforced by the tone linter) - long clinical
  paragraphs read as a lecture.

### 4. Collaboration and mutuality (choice)
*Risk it addresses:* powerlessness. Being processed by a system is the same feeling as being harmed by one.

**Implemented as**
- No flow is mandatory. No field is required. No question must be answered - in fact the **first reply
  contains no question at all**, because a question is a demand for an answer.
- Three equal-weight chips sit under every offer: *Show me who I can talk to* / *Stay here with me* /
  *Not right now*.
- **Pause** stops the conversation without ending it. Nothing times out while paused.
- **Delete** is one tap, present from the first second, and it is a hard delete.

### 5. Empowerment, voice and choice
*Risk it addresses:* helplessness. The person must stay the author of what happens.

**Implemented as**
- The user's pace is never accelerated. The typing indicator is the guide's, never a countdown.
- "I don't know what to say" is a first-class input with its own reply: *"That's alright. We can just sit here."*
- De-escalation is possible: if the person says they feel steadier, the interface accepts it immediately
  and does not keep them in "crisis mode".
- Escalation is **offered once**, never repeated. Repetition is pressure (`04-escalation-ladder.md`).

### 6. Cultural, historical and gender issues
*Risk it addresses:* services that only work for a fluent English speaker in a city.

**Implemented as**
- The distress lexicon accepts **transliterated Hindi, Kannada and Tamil** alongside English, plus a
  few Devanagari/Kannada/Tamil script terms - e.g. *"nanna life waste aagide, saaybeku anstide"* is
  correctly read as imminent risk (`06-test-cases.md`, case 5).
- No assumptions about family, religion, caste, gender or sexuality anywhere in the copy: replies
  refer to "your parents", "he", "she" only when the user used those words.
- Helplines listed include a free national line available in multiple Indian languages.

## The two rules that do the most work

1. **Never ask in the first turn.** The opening line is a statement, not a question. Everything else
   follows from this: if the interface never demands an answer, the user can never fail to give one.
2. **Every offer carries a real way out.** If "show me helplines" is bold and "not right now" is a
   grey afterthought, the interface is pressuring. Both are the same size, same weight, same row.

## What this project deliberately refuses to do

- Refuses to display a risk score, tier label, or "severity" to the user. Labels are judgements.
- Refuses to require a name, email, phone, OTP, or "continue with Google".
- Refuses to say "just", "calm down", "at least", "others have it worse", "think positive", or use
  an exclamation mark - all banned by the tone linter.
- Refuses to send the first message back after a pause ("are you still there?") - silence is respect.
