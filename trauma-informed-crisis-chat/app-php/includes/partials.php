<?php
/** Shared page furniture: head, wordmark, footer. */

function sw_head(string $title, string $extraClass = ''): void
{
    $cfg = sw_config();
    ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="referrer" content="no-referrer">
<meta name="description" content="Stillwater - a text-based support chat designed so the interface never adds pressure, judgement or confusion.">
<title><?= htmlspecialchars($title) ?> - <?= htmlspecialchars($cfg['app_name']) ?></title>
<link rel="stylesheet" href="assets/style.css">
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Ccircle cx='16' cy='16' r='14' fill='%236e8b75'/%3E%3Ccircle cx='16' cy='16' r='7' fill='%23faf8f3'/%3E%3C/svg%3E">
</head>
<body class="<?= htmlspecialchars($extraClass) ?>">
<?php
}

function sw_wordmark(string $href = 'index.php'): void
{
    ?>
<a class="wordmark" href="<?= htmlspecialchars($href) ?>" style="text-decoration:none;color:inherit">
  <svg width="26" height="26" viewBox="0 0 32 32" aria-hidden="true">
    <circle cx="16" cy="16" r="15" fill="none" stroke="#6e8b75" stroke-width="1.2"/>
    <circle cx="16" cy="16" r="9"  fill="none" stroke="#6e8b75" stroke-width="1" opacity=".55"/>
    <circle cx="16" cy="16" r="3.5" fill="#6e8b75" opacity=".85"/>
  </svg>
  Stillwater
</a>
    <?php
}

function sw_foot_note(): void
{
    ?>
<p><strong>This is an academic prototype.</strong> It is not a clinical service and it does not replace a mental health professional.</p>
<p>If you or someone near you is in danger right now, call <a href="tel:112">112</a>.
   In India you can also call Tele-MANAS on <a href="tel:14416">14416</a> or KIRAN on <a href="tel:18005990019">1800-599-0019</a>. Both are free and open all night.</p>
    <?php
}
