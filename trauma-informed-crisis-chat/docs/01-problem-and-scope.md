# 01 - Problem statement and scope

## The statement

> **Trauma-Informed Crisis Helpline Chat** - design a text-based support chat for people in emotional distress, where the interface itself must never add pressure, judgement or confusion.
> Core challenges: **tone**, **escalation paths**, **anonymity**.

## What that actually asks for

Most chat projects fail this brief in the same way: they build a chatbot and spend their time on the
quality of the *answers*. This brief is not about answer quality. It says **the interface itself**
must not add pressure, judgement or confusion - which makes the interface the deliverable and the
answer text only one part of it.

So the project is not "a chatbot that helps people". It is:

> a text window that a person in distress can open, use for thirty seconds, and leave without
> having been asked for anything, judged for anything, or made to feel confused about what happens next.

## Who the user is, precisely

A person in emotional distress, at an unknown hour, possibly:

- on a phone, on a slow connection, with one hand free;
- unable to form complete sentences, or unable to type at all;
- afraid that what they write will be read by someone they know (family, college, employer);
- afraid of being told they are overreacting, or of being locked up / "reported";
- already exhausted by forms, helpline IVR menus and "how are you feeling from 1 to 10" questions.

Every design decision in this project comes from that list, not from what is technically interesting.

## Scope: what is in, what is deliberately out

**In scope**

1. A landing screen with no form and no forced first question.
2. A transparency panel that says exactly what happens to what the user writes.
3. A chat screen with pause, leave, delete, and an always-visible human handoff.
4. A four-tier escalation ladder that offers help instead of redirecting to it.
5. A grounding exercise (breathing pacer and 5-4-3-2-1) available from any screen.
6. An anonymity model with data minimisation as the default.
7. An observer dashboard of aggregate counters, for the evaluation chapter of the report.

**Out of scope (and why)**

| Left out | Reason |
|---|---|
| Real counsellors, real queue, real call routing | Cannot be ethically provided by a student prototype; the project models the *handoff*, it does not pretend to staff it. |
| A generative AI model behind the replies | See `02` - an unpredictable model cannot guarantee "never adds pressure". Tone has to be *provable*, not hoped for. |
| Diagnosis, risk scores shown to the user, "severity meters" | Any visible label ("high risk") is a judgement printed onto a person. Risk is computed, but only used to change the interface, and only shown in aggregate. |
| Accounts, profiles, chat history | Every one of them is a reason for a distressed person not to use the service. |
| Notifications, emails, "are you still there?" pings | Pressure. The interface never initiates contact. |

## Success criteria used in this project

1. No sentence the interface can produce contains pressure, judgement or a demand (`tools/tone-lint.php`).
2. A user can complete a whole session without typing a name, an email or a phone number.
3. A user can reach a human in **one tap from any point**, without explaining themselves first.
4. A user can delete everything, and it is gone from the database, not flagged.
5. Every escalation offer has an equally weighted way to decline it.
6. Every behaviour above is covered by an automated test (`tools/test-cases.php`, `npm test`).

## Ethical position

This is an academic prototype and it says so on every screen. It never claims to be a clinical
service, it never discourages a person from calling a helpline, and it never acts as a gatekeeper
between a person and emergency help. The helpline numbers on the interface are real; the chat is
honest about being a small rule-based guide.
