<?php
declare(strict_types=1);
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/engine.php';
require __DIR__ . '/includes/partials.php';

$cfg = sw_config();
sw_head('A quiet place to be heard', 'landing');
?>
<a class="skip-link" href="#main">Skip to the main content</a>

<div class="landing">
  <header class="landing-top">
    <?php sw_wordmark('index.php'); ?>
    <a class="btn btn-quiet" href="index.php#helplines">Helplines</a>
  </header>

  <main class="landing-main" id="main">
    <p class="eyebrow">Someone is here</p>
    <h1>You don't have to explain it well.</h1>
    <p class="lede">
      This is a quiet text window. No name, no form, no questions you must answer.
      Type as little as you want, or nothing at all.
    </p>

    <div class="actions">
      <a class="btn btn-primary" href="chat.php">Start quietly</a>
      <a class="btn btn-ghost" href="#helplines">Just show me someone to call</a>
    </div>

    <ul class="promise">
      <li><strong>Nothing is required</strong>You can pause, leave, or delete this conversation at any moment.</li>
      <li><strong>No name, no account</strong>You get a random word-name for today. Nothing links back to you.</li>
      <li><strong>No judgement</strong>There is no correct way to use this, and no wrong thing to say.</li>
    </ul>

    <div style="margin-top:2rem">
      <details class="transparency">
        <summary>What happens to what I write?</summary>
        <ul>
          <li>Your words are <strong>not stored</strong> by default. Only the fact that someone spoke to us today is counted.</li>
          <li>If you turn on <em>keep my words</em> inside the chat, then and only then are your sentences saved - and you can switch it off again.</li>
          <li>No cookies, so nothing follows you to other sites. Closing the tab ends the conversation.</li>
          <li>We never ask for your name, phone number, email or location, and we do not log your IP address.</li>
          <li>The chat is a simple rule-based guide, not a person and not an AI model. A trained human is always one tap away.</li>
        </ul>
      </details>
    </div>
  </main>

  <section id="helplines" style="max-width:640px;margin:0 auto;padding:0 1.5rem 1rem;width:100%">
    <h2 style="font-size:1.2rem">People who answer, any hour</h2>
    <?php foreach ($cfg['helplines'] as $h): ?>
      <a class="helpline" href="tel:<?= htmlspecialchars($h['dial']) ?>">
        <div class="name"><?= htmlspecialchars($h['name']) ?></div>
        <div class="num"><?= htmlspecialchars($h['number']) ?></div>
        <div class="meta"><?= htmlspecialchars($h['hours']) ?> &middot; <?= htmlspecialchars($h['note']) ?></div>
      </a>
    <?php endforeach; ?>
    <p class="small" style="margin-top:1rem">You can also simply say "show me who I can talk to" inside the chat and the same list appears there.</p>
  </section>

  <footer class="landing-foot">
    <?php sw_foot_note(); ?>
    <p style="margin-top:1rem"><a href="observer.php">Observer view</a> &middot; for the evaluation dashboard used in this project's report.</p>
  </footer>
</div>
</body>
</html>
