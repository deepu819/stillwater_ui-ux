<?php
/**
 * Automated behaviour tests:  php tools/test-cases.php
 *
 * Each case is a persona and the single thing that must be true of the reply.
 * No database needed - sw_respond() is pure.
 */

require __DIR__ . '/../includes/engine.php';

function session(array $over = []): array
{
    return array_merge([
        'id'        => 'test',
        'alias'     => 'test-alias',
        'msg_count' => 1,
        'tier_max'  => 0,
        'recent'    => '[]',
    ], $over);
}

$cases = [
    [
        'name'  => 'The opening line carries no question',
        'text'  => 'hi',
        'check' => function ($r, $t) {
            foreach (sw_rules()['replies']['first_contact'] as $line) {
                if (mb_strpos($line['text'], '?') !== false) {
                    return false;
                }
            }
            return true;
        },
    ],
    [
        'name'  => 'Loneliness is met with company, not advice',
        'text'  => 'i am alone and nobody talks to me',
        'check' => fn ($r) => $r['tier'] === 1 && $r['category'] === 'isolation',
    ],
    [
        'name'  => 'Explicit wish to die escalates to tier 3',
        'text'  => 'i want to die',
        'check' => fn ($r) => $r['tier'] === 3 && $r['escalate'] === true,
    ],
    [
        'name'  => 'Indirect phrasing is caught too',
        'text'  => 'everyone would be better off dead',
        'check' => fn ($r) => $r['tier'] >= 2,
    ],
    [
        'name'  => 'Kannada-transliterated risk is caught (multilingual lexicon)',
        'text'  => 'nanna jeevana waste, saaybeku anstide',
        'check' => fn ($r) => $r['tier'] === 3,
    ],
    [
        'name'  => 'Negation is not read as risk',
        'text'  => "i don't want to die, i just want the pain to stop",
        'check' => fn ($r) => $r['tier'] === 0 && $r['negated_risk'] === true,
    ],
    [
        'name'  => 'Escalation is offered once, not repeatedly (repetition is pressure)',
        'text'  => 'i want to die',
        'ctx'   => ['tier_max' => 3],
        'check' => fn ($r) => $r['escalate'] === false,
    ],
    [
        'name'  => 'Asking for a human always works, whatever the tier',
        'text'  => 'can i talk to a counsellor',
        'check' => fn ($r) => $r['escalate'] === true,
    ],
    [
        'name'  => 'Silence is allowed',
        'text'  => "i don't know what to say",
        'check' => fn ($r) => $r['category'] === 'stuck',
    ],
    [
        'name'  => 'Study pressure is recognised',
        'text'  => 'my marks are bad and my parents keep shouting',
        'check' => fn ($r) => $r['tier'] === 1 && $r['category'] === 'academic_pressure',
    ],
    [
        'name'  => 'Waiting to be told to stop is not a crisis',
        'text'  => 'okay',
        'check' => fn ($r) => $r['tier'] === 0 && $r['escalate'] === false,
    ],
    [
        'name'  => 'De-escalation keeps the door open',
        'text'  => 'i feel a bit better now thanks',
        'check' => fn ($r) => $r['tier'] === 0 && $r['escalate'] === false,
    ],
];

$pass = 0;
foreach ($cases as $i => $case) {
    $ctx = session($case['ctx'] ?? []);
    $res = sw_respond($ctx, $case['text']);
    $text = implode(' ', array_column(sw_render($res['reply_ids']), 'text'));
    $ok = (bool) ($case['check'])($res, $text);

    printf("%s  %2d. %s\n", $ok ? 'PASS' : 'FAIL', $i + 1, $case['name']);
    if (!$ok) {
        printf("        input : %s\n        reply : %s\n        tier  : %d (%s)\n", $case['text'], $text, $res['tier'], $res['category']);
    }
    if ($ok) { $pass++; }
}

printf("\n%d of %d cases passed\n", $pass, count($cases));
exit($pass === count($cases) ? 0 : 1);
