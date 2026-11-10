# 05 - Anonymity and data policy

The third core challenge, and the one most projects fake. Writing "we value your privacy" while
logging an IP address, an email and a chat transcript is exactly the kind of betrayal that makes a
person stop using mental health services.

This project states a policy and then makes the **code** hold it.

## The policy, in the user's words

Shown on the landing page before anything is typed:

> - Your words are **not stored** by default. Only the fact that someone spoke to us today is counted.
> - If you turn on *keep my words* inside the chat, then and only then are your sentences saved - and
>   you can switch it off again.
> - No cookies, so nothing follows you to other sites. Closing the tab ends the conversation.
> - We never ask for your name, phone number, email or location, and we do not log your IP address.
> - The chat is a simple rule-based guide, not a person and not an AI model. A trained human is always
>   one tap away.

## What is collected

| Data | Collected? | Why |
|---|---|---|
| Name, email, phone, age, gender | **Never** | No feature of the interface needs it |
| IP address | **Never** (not in the schema, not logged by the app) | Nothing in the product requires it |
| Cookies | **None set by the app** | The session id lives only in the page's memory |
| Device / browser fingerprint | **Never** | Not needed |
| The words typed | **Only if the user switches on "keep my words"** (off by default) | Data minimisation is the default, not an option |
| Category + tier of a message | Yes, as an abstract label ("hopelessness", tier 2) | Needed for the safety logic and the aggregate evaluation |
| That an escalation was offered / taken | Yes | This is the evidence the report analyses |
| A random word-alias ("quiet-river-42") | Yes | So the interface can address the person without a name |

## The two mechanisms that make it real

### 1. Data minimisation in the code, not in the promise

```php
// api.php - the words are stored only when the person asked for that
$body = ((int) $session['keep_words'] === 1) ? $text : null;

$db->prepare('INSERT INTO messages (session_id, role, body, ...) VALUES (?, ?, ?, ...)')
   ->execute([$id, 'user', $body, $result['category'], $result['tier'], $now]);
```

So in a default session, the transcript table holds rows with `body = NULL` - the row exists (it is
counted, and it carries the tier for the aggregate analysis) but **the sentence is not there**. There
are no backups, no "anonymised" copies and no analytics pipeline to leak it from.

The toggle is honest in both directions and says so in the chat:

> "Thank you. I will keep the words you write from now on, so you could come back to them. You can
> turn this off at any time."
> "Done. From now on I keep nothing you write. Only the fact that someone talked to me today."

### 2. A delete button that deletes

```php
// api.php, action=end, delete=true
DELETE FROM messages     WHERE session_id = ?
DELETE FROM risk_events  WHERE session_id = ?
UPDATE escalations    SET session_id = 'deleted' WHERE session_id = ?
UPDATE grounding_runs SET session_id = 'deleted' WHERE session_id = ?
UPDATE sessions SET deleted_at = ?, alias = 'deleted' WHERE id = ?
```

- Rows that could contain words are **removed**, not flagged.
- Counters that must survive for the report (how many escalations were offered, how many grounding
  exercises were used) are kept, but their `session_id` is overwritten with `'deleted'`, so no row can
  be re-linked to the conversation.
- The alias is overwritten, so even the word-name is gone.
- The screen afterwards says: *"Everything from this conversation has been removed, including the
  word-name you were given. There is nothing left to find."*

## Session identity

The id is `bin2hex(random_bytes(16))` - 128 bits, generated per browser tab, held **only in memory**.
There is no cookie, so:

- a refresh starts a new, unlinked conversation with a new alias (a clean slate, never a "welcome back");
- closing the tab ends it;
- two tabs are two different people as far as the server knows, which is the honest model for a
  service that has no accounts.

## What anonymity cannot promise (and says so)

These limits are stated in the report rather than hidden:

1. **A helpline is not anonymous to itself.** Once someone calls, the service follows its own policy.
2. **A device can be shared.** If a family member uses the same browser, the screen is theirs to see -
   hence pause and delete.
3. **An observer can see aggregate numbers**, never text: the dashboard query never selects `body`.
4. **A prototype is not a guarantee.** This is a student system; the policy is enforced by this code, not
   by an audited infrastructure. The interface never claims more than the code can do.
