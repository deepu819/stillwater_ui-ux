# 07 - Data model and ER diagram

Five tables, created automatically on first run (`includes/db.php`). The same schema is written out for
both drivers: `app-php/database/schema-sqlite.sql` and `app-php/database/schema-mysql.sql`.

```mermaid
erDiagram
    SESSIONS ||--o{ MESSAGES     : "has"
    SESSIONS ||--o{ RISK_EVENTS  : "raises"
    SESSIONS ||--o{ ESCALATIONS  : "is offered"
    SESSIONS ||--o{ GROUNDING_RUNS : "uses"

    SESSIONS {
        text     id PK "random 128-bit hex, per tab"
        text     alias "quiet-river-42 (generated)"
        int      consent "transparency acknowledged"
        int      keep_words "0 = never store the words"
        int      tier_max "highest concern reached"
        int      msg_count
        text     recent "reply ids already used"
        datetime created_at
        datetime last_seen
        datetime ended_at
        datetime deleted_at "set when the person deletes"
    }

    MESSAGES {
        int      id PK
        text     session_id FK
        text     role "user | guide"
        text     body "NULL unless keep_words = 1"
        text     category "abstract label, never the phrase"
        int      tier
        datetime created_at
    }

    RISK_EVENTS {
        int      id PK
        text     session_id FK
        text     category
        int      tier
        datetime created_at
    }

    ESCALATIONS {
        int      id PK
        text     session_id FK
        int      tier
        datetime shown_at
        int      accepted "1 taken, 0 declined, NULL undecided"
        datetime resolved_at
    }

    GROUNDING_RUNS {
        int      id PK
        text     session_id FK
        text     kind "breathing | breathing-complete"
        int      rounds
        datetime created_at
    }
```

## Design notes worth defending in the viva

1. **`messages.body` is nullable on purpose.** Nullability is the data-minimisation policy expressed in
   the schema. An examiner can see the anonymity rule in the DDL, not just in a paragraph of the report.
2. **`category` stores an abstract label, never the matched phrase.** Storing "the user typed 'saaybeku'"
   would be the same as storing the sentence. The lexicon match is used and discarded.
3. **`sessions.tier_max` is a maximum, not a current value.** A conversation that reached tier 3 is
   still a tier-3 conversation after the user calms down - otherwise the statistics would flatter the
   system by counting only final states.
4. **`sessions.deleted_at` is kept, but everything else is destroyed.** The flag exists so the observer
   dashboard can count "how many people exercised their right to delete", which is itself a design
   metric. It cannot be used to recover anything.
5. **No `users` table, no `user_id` anywhere.** Anonymity is structural, not a setting.
6. **`recent` is a small JSON array of reply ids** used only to avoid repeating the same sentence inside
   one conversation. It contains no user content.

## Where the rules live

`app-php/data/rules.json` (mirrored at `app-node/data/rules.json`) holds:

- `categories` - the distress lexicon: 20 categories, each with a tier and a term list in English plus
  transliterated Hindi / Kannada / Tamil;
- `negation_terms` and `negation_window` - so "i do **not** want to die" is not scored as risk;
- `replies` - every sentence the interface can say, in banks, with ids;
- `escalation` - the offer copy and the chips;
- `grounding` - the exercise text.

Because the rules are data, the tone can be linted, translated and reviewed by someone who does not
read PHP.
