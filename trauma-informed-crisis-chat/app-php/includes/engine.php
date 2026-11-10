<?php
/**
 * The "brain": a deliberately small, readable, rule-based engine.
 *
 * Why not a language model?
 *  - the tone of this product has to be provable, not hoped for;
 *  - an examinable system must give the same answer to the same sentence;
 *  - nothing a distressed person types should leave the machine.
 * Every sentence it can produce lives in data/rules.json and is checked by
 * tools/tone-lint.php.
 *
 * pipeline: normalise -> match lexicon -> negation guard -> pick tier -> pick reply
 */

function sw_rules(): array
{
    static $rules = null;
    if ($rules === null) {
        $raw   = file_get_contents(__DIR__ . '/../data/rules.json');
        $rules = json_decode($raw, true);
        if (!is_array($rules)) {
            throw new RuntimeException('rules.json could not be parsed');
        }
    }
    return $rules;
}

function sw_tier_label(int $tier): string
{
    $labels = [0 => 'steady', 1 => 'distress', 2 => 'high concern', 3 => 'imminent risk'];
    return $labels[$tier] ?? 'unknown';
}

/** Lower-case, straighten quotes, squash whitespace, pad with spaces for matching. */
function sw_normalise(string $text): string
{
    $text = mb_strtolower($text, 'UTF-8');
    $text = str_replace(["\u{2019}", "\u{2018}", "\u{201C}", "\u{201D}"], "'", $text);
    $text = preg_replace('/[^\p{L}\p{N}\' ]+/u', ' ', $text);
    $text = preg_replace('/\s+/u', ' ', (string) $text);
    return ' ' . trim((string) $text) . ' ';
}

/**
 * Match the distress lexicon.
 * Returns every category with at least one term hit, and whether the hit sat
 * behind a negation ("i do not want to die").
 */
function sw_match(string $norm, array $rules): array
{
    $hits = [];
    foreach ($rules['categories'] as $cat) {
        $best = null;
        foreach ($cat['terms'] as $term) {
            $needle = ' ' . $term . ' ';
            $pos    = mb_strpos($norm, $needle);
            if ($pos === false) {
                continue;
            }
            $negated = sw_is_negated($norm, $pos, $rules);
            if ($best === null || ($best['negated'] && !$negated)) {
                $best = ['term' => $term, 'negated' => $negated];
            }
            if (!$negated) {
                break; // a clear positive hit wins for this category
            }
        }
        if ($best !== null) {
            $hits[] = [
                'id'      => $cat['id'],
                'tier'    => (int) $cat['tier'],
                'label'   => $cat['label'],
                'term'    => $best['term'],
                'negated' => $best['negated'],
                'effective' => $best['negated'] ? 0 : (int) $cat['tier'],
            ];
        }
    }
    return $hits;
}

/** Look back a few words for a negation word before the matched term. */
function sw_is_negated(string $norm, int $bytePos, array $rules): bool
{
    $before = mb_substr($norm, 0, $bytePos);
    $words  = preg_split('/\s+/u', trim($before)) ?: [];
    $window = (int) ($rules['negation_window'] ?? 4);
    $tail   = array_slice($words, max(0, count($words) - $window));
    foreach ($tail as $word) {
        if (in_array($word, $rules['negation_terms'], true)) {
            return true;
        }
    }
    return false;
}

/** Highest-effective-tier hit, with the lexicon order as a tie-break. */
function sw_primary(array $hits): ?array
{
    $chosen = null;
    foreach ($hits as $hit) {
        if ($chosen === null || $hit['effective'] > $chosen['effective']) {
            $chosen = $hit;
        }
    }
    return $chosen;
}

function sw_pick_reply(string $bank, array $recent, array $rules): array
{
    $pool = $rules['replies'][$bank] ?? $rules['replies']['reflective'];
    $unused = [];
    foreach ($pool as $item) {
        if (!in_array($item['id'], $recent, true)) {
            $unused[] = $item;
        }
    }
    if ($unused === []) {
        $unused = $pool; // everything has been used once; start again calmly
    }
    return $unused[0];
}

/**
 * Main turn handler.
 *
 * @param array  $session row from sessions table
 * @param string $text    what the person typed
 * @return array  replies, tier, escalation offer, chips
 */
function sw_respond(array $session, string $text): array
{
    $rules = sw_rules();
    $norm  = sw_normalise($text);

    $hits    = sw_match($norm, $rules);
    $primary = sw_primary($hits);
    $recent  = json_decode($session['recent'] ?? '[]', true) ?: [];

    $tier     = $primary['effective'] ?? 0;
    $category = $primary['id'] ?? 'reflective';

    // A person asking for a human always gets one, whatever the tier says.
    foreach ($hits as $hit) {
        if ($hit['id'] === 'help_seeking' && $hit['effective'] === 0) {
            $category = 'help_seeking';
        }
    }

    $bubbles = [];
    $extra   = [];

    // The opening line is sent once by api.php 'start', so a turn never replays it.

    // A negated crisis sentence still deserves a gentle acknowledgement of the
    // relief, never a cold "okay".
    $negatedCrisis = false;
    foreach ($hits as $hit) {
        if ($hit['negated'] && $hit['tier'] >= 2) {
            $negatedCrisis = true;
        }
    }

    if ($negatedCrisis && $tier === 0) {
        $bank = 'negated_risk';
    } elseif ($category === 'meta' || $category === 'stuck' || $category === 'thanks'
        || $category === 'goodbye' || $category === 'feeling_better' || $category === 'help_seeking') {
        $bank = $category;
    } elseif ($category !== 'reflective') {
        $bank = $category;
    } else {
        $bank = 'reflective';
    }

    $pick = sw_pick_reply($bank, $recent, $rules);
    $bubbles[] = $pick['id'];
    $extra[]   = $pick['id'];

    // Short, calm follow-up only where it adds company rather than a demand.
    $antiRepeat = false;
    if (in_array($bank, ['imminent_risk', 'self_harm_urge', 'hopelessness', 'imminent_risk_follow'], true)) {
        $follow = sw_pick_reply('imminent_risk_follow', $recent, $rules);
        $bubbles[] = $follow['id'];
        $extra[]   = $follow['id'];
        $antiRepeat = true;
    }

    // Escalation offer. Offered once, then only if the person asks again:
    // repeating an offer is pressure.
    $alreadyOffered = (int) ($session['tier_max'] ?? 0) >= 2;
    $escalate = false;
    if ($tier >= 3) {
        $escalate = !$alreadyOffered;
    } elseif ($tier === 2 && !$alreadyOffered) {
        $escalate = true;
    }
    if ($category === 'help_seeking') {
        $escalate = true;
    }

    return [
        'reply_ids'    => $bubbles,
        'recent'       => array_slice(array_values(array_unique(array_merge($recent, $bubbles))), -12),
        'tier'         => $tier,
        'tier_label'   => sw_tier_label($tier),
        'category'     => $category,
        'categories'   => array_map(static fn ($h) => ['id' => $h['id'], 'tier' => $h['effective']], $hits),
        'escalate'     => $escalate,
        'grounding'    => $tier >= 1,
        'chips'        => sw_chips($tier, $category, $rules),
        'negated_risk' => $negatedCrisis,
        'anti_repeat'  => $antiRepeat,
    ];
}

function sw_chips(int $tier, string $category, array $rules): array
{
    if ($tier >= 2 || $category === 'help_seeking') {
        return array_slice($rules['escalation']['chips'], 0, 4);
    }
    if (in_array($category, ['stuck', 'reflective', 'tired'], true)) {
        return ["I don't know what to say", 'Just stay with me', "I'd like to breathe first"];
    }
    return ["I don't know what to say", "I'd like to breathe first", 'Show me who I can talk to'];
}

/** Resolve reply ids to text, in order, so nothing is ever sent that is not in rules.json. */
function sw_render(array $ids): array
{
    $rules = sw_rules();
    $all   = [];
    foreach ($rules['replies'] as $bank => $items) {
        foreach ($items as $item) {
            $all[$item['id']] = $item['text'];
        }
    }
    $out = [];
    foreach ($ids as $id) {
        if (isset($all[$id])) {
            $out[] = ['id' => $id, 'text' => $all[$id]];
        }
    }
    return $out;
}

/** Anonymous alias: "quiet-river-42". No name, no email, nothing reused. */
function sw_alias(): string
{
    $adj = ['quiet', 'soft', 'slow', 'open', 'gentle', 'steady', 'clear', 'warm',
            'small', 'early', 'still', 'low', 'patient', 'kind', 'plain', 'green'];
    $noun = ['river', 'lantern', 'meadow', 'harbour', 'pebble', 'window', 'orchard',
             'cloud', 'pine', 'shore', 'field', 'kettle', 'corner', 'shelf', 'bench', 'path'];

    return sprintf('%s-%s-%02d', $adj[random_int(0, count($adj) - 1)], $noun[random_int(0, count($noun) - 1)], random_int(10, 99));
}
