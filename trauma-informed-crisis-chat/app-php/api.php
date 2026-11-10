<?php
/**
 * JSON API for the Stillwater chat.
 *
 * Deliberately cookie-free: the session id lives only in the browser tab's
 * sessionStorage, so closing the tab ends the session and a reload starts a
 * new, unlinked one. See docs/05-anonymity-policy.md.
 */

declare(strict_types=1);

require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/engine.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');

$cfg = sw_config();

function sw_out(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function sw_in(): array
{
    $raw = file_get_contents('php://input') ?: '';
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function sw_session_row(string $id): ?array
{
    $stmt = sw_db()->prepare('SELECT * FROM sessions WHERE id = ? AND deleted_at IS NULL');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

$action = $_GET['action'] ?? '';
$input  = sw_in();
if (isset($input['action'])) {
    $action = (string) $input['action'];
}

try {
    switch ($action) {

        // ---------------------------------------------------------- start
        case 'start':
            $id    = bin2hex(random_bytes(16));
            $alias = sw_alias();
            $now   = sw_now();
            $keep  = !empty($cfg['keep_words_default']) ? 1 : 0;

            $stmt = sw_db()->prepare(
                'INSERT INTO sessions (id, alias, consent, keep_words, tier_max, msg_count, recent, created_at, last_seen)
                 VALUES (?, ?, 1, ?, 0, 0, ?, ?, ?)'
            );
            $stmt->execute([$id, $alias, $keep, '[]', $now, $now]);

            sw_out([
                'ok'      => true,
                'session' => [
                    'id'        => $id,
                    'alias'     => $alias,
                    'keepWords' => (bool) $keep,
                    'startedAt' => $now,
                ],
                'guide'   => sw_render([sw_pick_reply('first_contact', [], sw_rules())['id']]),
                'helplines' => $cfg['helplines'],
            ]);
            break;

        // -------------------------------------------------------- message
        case 'message':
            $id   = (string) ($input['sessionId'] ?? '');
            $text = trim((string) ($input['text'] ?? ''));
            $session = sw_session_row($id);

            if (!$session) {
                sw_out(['ok' => false, 'error' => 'session_not_found'], 404);
            }
            if ($text === '') {
                sw_out(['ok' => true, 'replies' => [], 'tier' => 0,
                        'chips' => ["I don't know what to say", "I'd like to breathe first"]]);
            }
            $text = mb_substr($text, 0, 2000);

            $result = sw_respond($session, $text);
            $db     = sw_db();
            $now    = sw_now();

            // Data minimisation: text is stored only if the person asked us to.
            $body = ((int) $session['keep_words'] === 1) ? $text : null;

            $db->prepare('INSERT INTO messages (session_id, role, body, category, tier, created_at)
                          VALUES (?, ?, ?, ?, ?, ?)')
               ->execute([$id, 'user', $body, $result['category'], $result['tier'], $now]);

            foreach (sw_render($result['reply_ids']) as $reply) {
                $db->prepare('INSERT INTO messages (session_id, role, body, category, tier, created_at)
                              VALUES (?, ?, ?, ?, ?, ?)')
                   ->execute([$id, 'guide', $body === null ? null : $reply['text'], $result['category'], $result['tier'], $now]);
            }

            if ($result['tier'] > 0) {
                $db->prepare('INSERT INTO risk_events (session_id, category, tier, created_at) VALUES (?, ?, ?, ?)')
                   ->execute([$id, $result['category'], $result['tier'], $now]);
            }

            $tierMax = max((int) $session['tier_max'], $result['tier']);
            $db->prepare('UPDATE sessions SET msg_count = msg_count + 1, last_seen = ?, tier_max = ?, recent = ? WHERE id = ?')
               ->execute([$now, $tierMax, json_encode($result['recent']), $id]);

            if ($result['escalate']) {
                $db->prepare('INSERT INTO escalations (session_id, tier, shown_at) VALUES (?, ?, ?)')
                   ->execute([$id, max(1, $result['tier']), $now]);
            }

            sw_out([
                'ok'        => true,
                'replies'   => sw_render($result['reply_ids']),
                'tier'      => $result['tier'],
                'tierLabel' => $result['tier_label'],
                'escalate'  => $result['escalate'],
                'grounding' => $result['grounding'],
                'chips'     => $result['chips'],
                'offer'     => $result['escalate']
                    ? ($result['tier'] >= 3
                        ? sw_rules()['escalation']['copy']['offer_tier3']
                        : sw_rules()['escalation']['copy']['offer_tier2'])
                    : null,
                'helplines' => $result['escalate'] ? $cfg['helplines'] : null,
            ]);
            break;

        // ---------------------------------------------------- keep words
        case 'keep_words':
            $id   = (string) ($input['sessionId'] ?? '');
            $keep = !empty($input['keep']) ? 1 : 0;
            if (!sw_session_row($id)) {
                sw_out(['ok' => false, 'error' => 'session_not_found'], 404);
            }
            sw_db()->prepare('UPDATE sessions SET keep_words = ? WHERE id = ?')->execute([$keep, $id]);
            sw_out(['ok' => true, 'keepWords' => (bool) $keep]);
            break;

        // ------------------------------------------------------- escalate
        case 'escalate':
            $id       = (string) ($input['sessionId'] ?? '');
            $accepted = $input['accepted'] ?? null;
            if (!sw_session_row($id)) {
                sw_out(['ok' => false, 'error' => 'session_not_found'], 404);
            }
            $db  = sw_db();
            $now = sw_now();
            $row = $db->prepare('SELECT id FROM escalations WHERE session_id = ? AND accepted IS NULL ORDER BY id DESC LIMIT 1');
            $row->execute([$id]);
            $esc = $row->fetch();

            if ($esc) {
                $db->prepare('UPDATE escalations SET accepted = ?, resolved_at = ? WHERE id = ?')
                   ->execute([$accepted === true || $accepted === 'true' || $accepted === 1 ? 1 : 0, $now, $esc['id']]);
            } else {
                $db->prepare('INSERT INTO escalations (session_id, tier, shown_at, accepted, resolved_at) VALUES (?, ?, ?, ?, ?)')
                   ->execute([$id, 1, $now, $accepted ? 1 : 0, $now]);
            }

            $copy = sw_rules()['escalation']['copy'];
            $yes  = ($accepted === true || $accepted === 'true' || $accepted === 1);
            sw_out([
                'ok'       => true,
                'replies'  => [['id' => $yes ? 'esc-yes' : 'esc-no', 'text' => $yes ? $copy['accepted'] : $copy['declined']]],
                'helplines' => $yes ? $cfg['helplines'] : null,
            ]);
            break;

        // ------------------------------------------------------ grounding
        case 'grounding':
            $id   = (string) ($input['sessionId'] ?? '');
            $kind = (string) ($input['kind'] ?? 'breathing');
            if (sw_session_row($id)) {
                sw_db()->prepare('INSERT INTO grounding_runs (session_id, kind, rounds, created_at) VALUES (?, ?, ?, ?)')
                    ->execute([$id, mb_substr($kind, 0, 40), max(1, (int) ($input['rounds'] ?? 1)), sw_now()]);
            }
            sw_out(['ok' => true]);
            break;

        // ------------------------------------------------------------ end
        case 'end':
            $id     = (string) ($input['sessionId'] ?? '');
            $delete = !empty($input['delete']);
            $row    = sw_session_row($id);
            if (!$row) {
                sw_out(['ok' => true, 'deleted' => false]);
            }
            $db  = sw_db();
            $now = sw_now();

            if ($delete) {
                // Hard delete: no soft-delete, no backup copy, no "anonymous" id left behind.
                $db->prepare('DELETE FROM messages WHERE session_id = ?')->execute([$id]);
                $db->prepare('DELETE FROM risk_events WHERE session_id = ?')->execute([$id]);
                // Keep the counters (they are aggregate evidence for the report) but
                // break every link back to this conversation.
                $db->prepare("UPDATE escalations SET session_id = 'deleted' WHERE session_id = ?")->execute([$id]);
                $db->prepare("UPDATE grounding_runs SET session_id = 'deleted' WHERE session_id = ?")->execute([$id]);
                $db->prepare('UPDATE sessions SET deleted_at = ?, alias = ?, recent = ? WHERE id = ?')
                   ->execute([$now, 'deleted', '[]', $id]);
            } else {
                $db->prepare('UPDATE sessions SET ended_at = ? WHERE id = ?')->execute([$now, $id]);
            }

            sw_out(['ok' => true, 'deleted' => $delete,
                    'closing' => sw_rules()['escalation']['copy']['still_here']]);
            break;

        // ----------------------------------------------------------- meta
        case 'meta':
            sw_out([
                'ok'        => true,
                'app'       => ['name' => $cfg['app_name'], 'version' => $cfg['version']],
                'helplines' => $cfg['helplines'],
                'grounding' => sw_rules()['grounding'],
                'chips'     => sw_rules()['escalation']['chips'],
            ]);
            break;

        // ------------------------------------------------------ helplines
        case 'helplines':
            sw_out(['ok' => true, 'helplines' => $cfg['helplines']]);
            break;

        // ---------------------------------------------------------- stats
        case 'stats':
            if (($_GET['key'] ?? ($input['key'] ?? '')) !== $cfg['observer_key']) {
                sw_out(['ok' => false, 'error' => 'forbidden'], 403);
            }
            $db = sw_db();

            $sessions = (int) $db->query('SELECT COUNT(*) FROM sessions WHERE deleted_at IS NULL')->fetchColumn();
            $msgCount = (int) $db->query("SELECT COUNT(*) FROM messages WHERE role = 'user'")->fetchColumn();
            $deleted  = (int) $db->query('SELECT COUNT(*) FROM sessions WHERE deleted_at IS NOT NULL')->fetchColumn();
            $kept     = (int) $db->query('SELECT COUNT(*) FROM sessions WHERE keep_words = 1 AND deleted_at IS NULL')->fetchColumn();

            $tierNames = [0 => 'steady', 1 => 'distress', 2 => 'high', 3 => 'imminent'];
            $tiers = ['steady' => 0, 'distress' => 0, 'high' => 0, 'imminent' => 0];
            foreach ($db->query('SELECT tier_max, COUNT(*) AS n FROM sessions WHERE deleted_at IS NULL GROUP BY tier_max') as $r) {
                $tiers[$tierNames[(int) $r['tier_max']] ?? 'steady'] = (int) $r['n'];
            }

            $esc = ['offered' => 0, 'accepted' => 0, 'declined' => 0, 'open' => 0];
            foreach ($db->query('SELECT accepted, COUNT(*) AS n FROM escalations GROUP BY accepted') as $r) {
                if ($r['accepted'] === null) {
                    $esc['open'] = (int) $r['n'];
                } elseif ((int) $r['accepted'] === 1) {
                    $esc['accepted'] = (int) $r['n'];
                } else {
                    $esc['declined'] = (int) $r['n'];
                }
                $esc['offered'] += (int) $r['n'];
            }

            $grounding = (int) $db->query('SELECT COUNT(*) FROM grounding_runs')->fetchColumn();
            $cats = [];
            foreach ($db->query('SELECT category, COUNT(*) AS n FROM risk_events GROUP BY category ORDER BY n DESC LIMIT 8') as $r) {
                $cats[] = ['category' => $r['category'], 'n' => (int) $r['n']];
            }

            sw_out(['ok' => true, 'stats' => [
                'sessions'      => $sessions,
                'userMessages'  => $msgCount,
                'deletedByUser' => $deleted,
                'keptWords'     => $kept,
                'tierCounts'    => $tiers,
                'escalations'   => $esc,
                'groundingRuns' => $grounding,
                'topCategories' => $cats,
            ]]);
            break;

        default:
            sw_out(['ok' => false, 'error' => 'unknown_action'], 400);
    }
} catch (Throwable $e) {
    error_log('stillwater api: ' . $e->getMessage());
    sw_out(['ok' => false, 'error' => 'server_error'], 500);
}
