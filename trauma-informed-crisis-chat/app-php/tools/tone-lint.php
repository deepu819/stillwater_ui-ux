<?php
/**
 * tone-lint:  php tools/tone-lint.php
 *
 * The problem statement says the interface must never add pressure, judgement or
 * confusion. That is a requirement, so it is testable. This script reads every
 * sentence in data/rules.json and fails the build on:
 *
 *   1. exclamation marks            (urgency, false cheer)
 *   2. pressure phrases             ("just", "calm down", "you should")
 *   3. judgement phrases            ("at least", "others have it worse")
 *   4. a question in a first reply  (a question is a demand)
 *   5. walls of text                (over 320 characters in one bubble)
 *   6. duplicate reply ids
 *
 * Exit code 0 = clean. Run it after any wording change.
 */

$rules = json_decode(file_get_contents(__DIR__ . '/../data/rules.json'), true);

$banned = [
    '!'            => 'exclamation mark - creates urgency or false cheer',
    'calm down'    => 'commands the feeling instead of accepting it',
    'cheer up'     => 'tells the person their mood is wrong',
    'just relax'   => 'minimises the distress',
    'at least'     => 'compares their pain to something "worse"',
    'others have it worse' => 'judgement by comparison',
    'think positive'       => 'advice nobody asked for',
    'everything happens for a reason' => 'judgement dressed as comfort',
    'should'       => 'moral instruction',
    'you must'     => 'removes choice',
    'why don\'t you' => 'advice framed as a question',
    'why not'      => 'pressure disguised as curiosity',
    'get over'     => 'judgement',
    'snap out'     => 'judgement',
    'you need to'  => 'removes choice',
];

$rows   = [];
$failed = 0;

$firstContact = $rules['replies']['first_contact'] ?? [];
$ids = [];

foreach ($rules['replies'] as $bank => $items) {
    foreach ($items as $item) {
        $id   = $item['id'];
        $text = $item['text'];
        $why  = [];

        if (isset($ids[$id])) {
            $why[] = 'duplicate id';
        }
        $ids[$id] = true;

        $lower = mb_strtolower($text);
        $stripped = str_replace('!', '', $text); // handled separately, but keep scan honest
        foreach ($banned as $needle => $reason) {
            if (mb_strpos($lower, (string) $needle) !== false) {
                $why[] = $reason;
            }
        }
        if (mb_strlen($text) > 320) {
            $why[] = 'too long for one bubble (wall of text)';
        }
        if (mb_strpos($text, '?') !== false && in_array($item, $firstContact, true)) {
            $why[] = 'question in the first reply';
        }

        $ok = $why === [];
        if (!$ok) {
            $failed++;
        }
        $rows[] = [$ok ? 'PASS' : 'FAIL', $bank, $id, implode('; ', array_unique($why)), $text];
    }
}

// chips are interface copy too
foreach ($rules['escalation']['chips'] as $chip) {
    $ok = mb_strpos($chip, '!') === false;
    if (!$ok) { $failed++; }
    $rows[] = [$ok ? 'PASS' : 'FAIL', 'chip', '-', $ok ? '' : 'exclamation mark', $chip];
}

printf("%-6s %-22s %-8s %-46s %s\n", 'state', 'bank', 'id', 'why it failed', 'text');
echo str_repeat('-', 160), "\n";
foreach ($rows as $r) {
    printf("%-6s %-22s %-8s %-46s %s\n", $r[0], $r[1], $r[2], $r[3], mb_substr($r[4], 0, 46));
}
echo str_repeat('-', 160), "\n";
printf("%d strings checked, %d failed\n", count($rows), $failed);

exit($failed === 0 ? 0 : 1);
