# 03 - Tone guide (and how the tone is enforced)

Tone is the first of the three core challenges, and it is the easiest one to fail while feeling good
about it. Almost every "support chatbot" phrase that feels kind to its author feels like pressure to
its reader.

## The rules

| # | Rule | Why |
|---|---|---|
| 1 | No question in the first reply | A question is a demand for an answer. The user may not have words yet. |
| 2 | No "why" questions, ever | "Why do you feel like that?" is an interrogation and implies the feeling needs justification. |
| 3 | No advice unless asked | Advice implies the person has not already thought of it. It shifts work onto them. |
| 4 | No exclamation marks | They manufacture cheer and urgency. |
| 5 | No comparison ("at least", "others have it worse") | It ranks the person's pain and finds it wanting. |
| 6 | No "just", "simply", "only" | They imply the problem is small. |
| 7 | No instruction ("you should", "you need to", "try to") | Removes choice. |
| 8 | One idea per bubble, under ~320 characters | Walls of text are a second burden. |
| 9 | Never name a feeling the person did not name | Being told what you feel is a judgement. |
| 10 | Every offer has an equal-weight way to decline | Consent that is hard to withhold is not consent. |

## The rewrite table

This table is the shortest possible summary of the whole project, and is worth putting in the report
as-is.

| Situation | What most projects write (pressure / judgement) | What Stillwater writes |
|---|---|---|
| Opening | "Hi! How are you feeling today?" | "I'm here. Nothing has to be explained properly, and there is no right way to start." |
| They are silent | "Please type something to continue." | "That's alright. We can just sit here. Nothing is expected of you in this conversation." |
| Low mood | "Don't worry, be strong! You have so much to live for." | "When nothing looks like it will change, even getting through today takes effort. I see that." |
| Self-blame | "You shouldn't think like that." | "You are allowed to have made mistakes and still be worth something. Both are true at once." |
| Panic | "Take a deep breath and calm down." | "That sounds frightening. Your body is reacting to danger it thinks is here right now, and it is very hard to think through that." |
| Risk detected | "ALERT: You may be at risk. Please contact a helpline immediately." | "I want you to have more than I can give. There are people trained for this, free, any hour. I can put their details on the screen, or we can stay here. Both are fine." |
| Escalation declined | "Okay, but you should really call them." | "That's completely fine. I'm still here." |
| Study pressure | "Have you tried making a timetable?" | "Marks and deadlines can start to feel like they are measuring your whole worth. They are not." |
| They ask if it is a bot | "I am an AI assistant here to support you!" | "I'm a simple text-based guide, not a person and not a therapist. I won't ask you to explain yourself, and I won't judge you." |
| Leaving | "Are you sure? Your session will be lost." | "Take care of yourself. This door stays open, no name needed." |

## How the tone is enforced, not just intended

Good intentions do not survive a deadline, so the tone is a **build check**:

```bash
php app-php/tools/tone-lint.php
```

It reads every one of the ~58 sentences the interface can produce and fails on:

- exclamation marks;
- the banned phrase list (`just`, `calm down`, `cheer up`, `at least`, `should`, `you need to`,
  `why don't you`, `get over`, `snap out`, `others have it worse`, `think positive`, ...);
- a question mark in any opening line;
- a bubble longer than 320 characters;
- a duplicate reply id.

Current status: `58 strings checked, 0 failed`, exit code 0. Any future wording change that breaks a
rule breaks the build.

## Structural safeguards that do not depend on wording

- **Reply banks, not a language model.** Every sentence is a finite, reviewable list in
  `app-php/data/rules.json`. The interface cannot improvise something cruel, and the same input always
  produces the same output - which is what makes the promises testable at all.
- **No repetition.** The engine remembers which lines it has already used in this conversation
  and picks an unused variant, so the guide does not parrot the same sentence back.
- **One offer, once.** An escalation offer is made a single time per conversation. Repeating it is
  the most common way a "helpful" system becomes a nagging one.
- **No unsolicited contact.** The interface never sends a message the user did not provoke. There is
  no "are you still there?".
