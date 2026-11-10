<?php
declare(strict_types=1);
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/engine.php';
require __DIR__ . '/includes/partials.php';

sw_head('Observer view', 'admin-page');
?>
<main class="admin" id="main">
  <div class="admin-head">
    <?php sw_wordmark('index.php'); ?>
    <span class="small">Aggregate only &middot; no message text is ever shown here</span>
  </div>

  <div id="lock" class="lock">
    <h1 style="font-size:1.6rem">Observer view</h1>
    <p class="small">For the evaluation section of the report. The passphrase is in <code>includes/config.php</code>.</p>
    <input type="password" id="key" placeholder="passphrase" autocomplete="off" aria-label="Passphrase">
    <button class="btn btn-primary btn-block" type="button" id="unlock">Open dashboard</button>
    <p class="small" id="lock-msg" style="margin-top:.8rem"></p>
  </div>

  <div id="dash" hidden>
    <div class="grid" id="cards"></div>

    <div class="panel">
      <h2>Highest level of concern reached in a conversation</h2>
      <div id="tiers"></div>
      <p class="small" style="margin-top:1rem">
        Tier 0 steady &middot; 1 distress &middot; 2 high concern &middot; 3 imminent risk.
        A conversation is counted at the highest tier it reached, not at its last message, because
        a crisis does not cancel itself out.
      </p>
    </div>

    <div class="panel">
      <h2>What people brought</h2>
      <div id="cats"></div>
      <p class="small" style="margin-top:1rem">
        Categories only. The words themselves are not stored unless the person switched
        on <em>keep my words</em>, and even then they never appear on this page.
      </p>
    </div>

    <div class="panel">
      <h2>Escalation handoffs</h2>
      <div id="esc"></div>
    </div>
  </div>
</main>

<script src="assets/observer.js"></script>
</body>
</html>
