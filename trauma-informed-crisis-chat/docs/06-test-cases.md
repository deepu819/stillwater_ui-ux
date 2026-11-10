# 06 - Test evidence

Two automated suites, plus the manual persona walk-through used for the screenshots. All commands
below were run against this code before submission.

## A. Behaviour suite (PHP, the version submitted to college)

```bash
php app-php/tools/test-cases.php     # 12 of 12 cases passed
npm test    --prefix app-node        # 12 of 12 cases passed (same promises, Node build)
```

| # | Case | Input | Expected | Result |
|---|---|---|---|---|
| 1 | Opening line carries no question | *"hi"* | no `?` in any opening sentence | PASS |
| 2 | Loneliness met with company, not advice | "i am alone and nobody talks to me" | tier 1, category `isolation` | PASS |
| 3 | Explicit wish to die escalates | "i want to die" | tier 3, offer shown | PASS |
| 4 | Indirect phrasing caught | "everyone would be better off dead" | tier >= 2 | PASS |
| 5 | Multilingual risk caught | "nanna jeevana waste, saaybeku anstide" | tier 3 | PASS |
| 6 | Negation is not risk | "i don't want to die, i just want the pain to stop" | tier 0, negation flag | PASS |
| 7 | Escalation is not repeated | "i want to die" after an earlier offer | tier 3 but no second offer | PASS |
| 8 | Asking for a human always works | "can i talk to a counsellor" | offer shown at any tier | PASS |
| 9 | Silence is valid | "i don't know what to say" | category `stuck` | PASS |
| 10 | Study pressure recognised | "my marks are bad and my parents keep shouting" | tier 1, `academic_pressure` | PASS |
| 11 | Neutral message is not a crisis | "okay" | tier 0, no offer | PASS |
| 12 | De-escalation accepted | "i feel a bit better now thanks" | tier 0, no offer | PASS |

## B. Tone suite

```bash
php app-php/tools/tone-lint.php      # 58 strings checked, 0 failed (exit code 0)
```

Checks every sentence the interface can produce for exclamation marks, pressure phrases, judgement
phrases, questions in opening lines, over-long bubbles and duplicate ids. Wiring it into CI means the
tone cannot quietly rot.

## C. Backend parity

Both builds read the same `data/rules.json`, so they must answer identically. Verified with the same
nine-message script against both servers:

| Message | PHP | Node |
|---|---|---|
| hi | tier 0 · `fc-1, rf-1` | tier 0 · `fc-1, rf-1` |
| i am so alone | tier 1 · `is-1` | tier 1 · `is-1` |
| i want to die | tier 3 · `im-1, imf-1` · offer | tier 3 · `im-1, imf-1` · offer |
| i cant go on anymore | tier 2 · `hp-1, imf-2` | tier 2 · `hp-1, imf-2` |
| i don't want to die, i just want the pain to stop | tier 0 · `nr-1` | tier 0 · `nr-1` |
| idk what to say | tier 0 · `st-1` | tier 0 · `st-1` |
| my marks are bad | tier 1 · `ac-1` | tier 1 · `ac-1` |
| nanna life waste aagide saaybeku anstide | tier 3 · `im-2, imf-1` | tier 3 · `im-2, imf-1` |
| thanks i feel better now | tier 0 · `tk-1` | tier 0 · `tk-1` |

Result: **identical**. This matters because it proves the tone and the risk logic live in the data
(`rules.json`), not in one particular programming language.

## D. Manual persona walk-through

Five personas were run end to end in the browser, and the screenshots in `docs/screenshots/` come from
this walk-through.

| Persona | Opening line typed | Observed behaviour | Judged on |
|---|---|---|---|
| The quiet one | "hi" (then nothing for 40 s) | No "are you still there?" pressure; composer stays open; pause and delete both one tap away | Does silence get punished? No |
| The panicking one | "i can't breathe, i think i am having a panic attack" | tier 1, acknowledgment first, grounding offered (not prescribed) | Is the first sentence an instruction? No |
| The hopeless one | "i have been feeling very low for weeks" → "i want to die" | tier 1 → tier 3, two holding bubbles, then a soft direct offer | Was the person left alone with the disclosure? No |
| The one who declines | "not right now" on the offer screen | "That's completely fine. I'm still here." - no repeat offer, header button remains | Is declining punished? No |
| The one who deletes | any conversation → menu → delete | Confirmation screen, rows removed from the database, alias erased | Is delete real? Yes |

## E. What was not tested

- Load and concurrency (single-user prototype; SQLite/JSON store is not tuned for either).
- Real helpline response times - the numbers are listed, the calls are the user's own.
- Screen-reader testing on a physical device. The markup uses `role="log"`, `aria-live="polite"`,
  focus-visible outlines and a skip link, but a real NVDA/VoiceOver pass is future work and is listed
  as such in the report rather than claimed here.
