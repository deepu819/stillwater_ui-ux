<?php
declare(strict_types=1);
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/engine.php';
require __DIR__ . '/includes/partials.php';

$cfg    = sw_config();
$rules  = sw_rules();
$ground = $rules['grounding'];

sw_head('Chat', 'chat-page');
?>
<a class="skip-link" href="#main">Skip to the conversation</a>

<div id="chat-state" class="chat-state">
  <header class="chat-head">
    <?php sw_wordmark('index.php'); ?>
    <span class="alias-chip"><span class="dot"></span><span id="alias">connecting</span></span>
    <div class="spacer"></div>

    <button class="human-link" id="human" type="button" aria-label="Talk to a human">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
        <path d="M4 5a2 2 0 0 1 2-2h2l2 5-2 1a11 11 0 0 0 6 6l1-2 5 2v2a2 2 0 0 1-2 2A16 16 0 0 1 4 5Z" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
      <span class="human-label">Talk to a human</span>
    </button>

    <details class="menu">
      <summary aria-label="More options">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
          <circle cx="5" cy="12" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="19" cy="12" r="1.6"/>
        </svg>
      </summary>
      <div class="menu-sheet">
        <button type="button" id="breathe">Breathe with me for a minute</button>
        <button type="button" id="pause">Pause this conversation</button>
        <button type="button" id="end-chat">Leave gently</button>
        <button type="button" id="delete-chat">Delete this conversation</button>
        <div class="menu-note">
          <label style="display:flex;gap:.5rem;align-items:flex-start;cursor:pointer">
            <input type="checkbox" id="keep-words" style="margin-top:.25rem">
            <span>Keep my words so I could read them later</span>
          </label>
        </div>
      </div>
    </details>
  </header>

  <main class="chat-scroll" id="scroller">
    <div class="chat-thread" id="thread" role="log" aria-live="polite" aria-relevant="additions text"></div>
    <div class="chips" id="chips" style="max-width:660px;margin:0.4rem auto 1.2rem;padding:0 clamp(0.9rem,3vw,1.5rem)"></div>
  </main>

  <div class="composer-wrap">
    <form class="composer" id="composer">
      <label class="sr-only" for="input">What would you like to say</label>
      <textarea id="input" rows="1" maxlength="2000"
                placeholder="Type whatever is there. Short is fine."
                autocomplete="off" spellcheck="false"></textarea>
      <button class="send" id="send" type="submit" aria-label="Send" disabled>
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
          <path d="M5 12h13M12 5l7 7-7 7" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </button>
    </form>
    <div class="composer-foot">
      <span class="small">Enter sends. Shift + Enter starts a new line.</span>
      <span class="small">Nothing is saved unless you ask.</span>
    </div>
  </div>
</div>

<!-- ---------------------------------------------------------- paused -->
<section id="pause-state" class="state-screen" hidden>
  <div class="state-card">
    <h1>Paused.</h1>
    <p>The conversation is exactly where you left it. Nothing is counting down, and nothing is expected of you while you are away.</p>
    <div class="actions">
      <button class="btn btn-primary" type="button" id="resume">Come back to it</button>
      <a class="btn btn-ghost" href="index.php">Close the window</a>
    </div>
  </div>
</section>

<!-- ---------------------------------------------------------- leaving -->
<section id="exit-state" class="state-screen" hidden>
  <div class="state-card">
    <h1>You showed up today.</h1>
    <p>That took something, even if it does not feel like it. The door stays open and no name is needed to come back.</p>
    <p class="small">Before you go - if anything feels heavier than it did when you started, Tele-MANAS on <a href="tel:14416">14416</a> is free and answers all night.</p>
    <div class="actions">
      <a class="btn btn-primary" href="index.php">Leave quietly</a>
      <a class="btn btn-ghost" href="chat.php">Start again</a>
    </div>
  </div>
</section>

<!-- ---------------------------------------------------------- deleted -->
<section id="delete-state" class="state-screen" hidden>
  <div class="state-card">
    <h1>Deleted.</h1>
    <p>Everything from this conversation has been removed, including the word-name you were given. There is nothing left to find.</p>
    <div class="actions">
      <a class="btn btn-primary" href="index.php">Close</a>
    </div>
  </div>
</section>

<!-- -------------------------------------------------------- helplines -->
<div class="sheet-backdrop" id="helpline-sheet" role="dialog" aria-modal="true" aria-labelledby="helpline-title">
  <div class="sheet">
    <div class="sheet-handle"></div>
    <h2 id="helpline-title">People who answer, any hour</h2>
    <p>These calls are free and confidential. Nobody will be sent to you, and nothing you say here is passed on. You can also just keep this list and not call.</p>
    <div id="helpline-list"></div>
    <div class="sheet-actions">
      <button class="btn btn-primary btn-block" type="button" data-close="helpline-sheet">I have the numbers</button>
      <button class="btn btn-ghost btn-block" type="button" data-close="helpline-sheet">Stay here with me instead</button>
    </div>
  </div>
</div>

<!-- --------------------------------------------------------- grounding -->
<div class="sheet-backdrop" id="ground-sheet" role="dialog" aria-modal="true" aria-labelledby="ground-title">
  <div class="sheet">
    <div class="sheet-handle"></div>
    <h2 id="ground-title">A minute of something simple</h2>
    <p><?= htmlspecialchars($ground['intro']) ?></p>

    <div class="breathe-stage">
      <div class="pacer"><span class="pacer-label" id="pacer-label">Breathe in</span></div>
    </div>

    <ul class="ground-list" id="ground-steps"></ul>

    <div class="sheet-actions">
      <button class="btn btn-primary btn-block" type="button" id="ground-done">That's enough for now</button>
      <button class="btn btn-ghost btn-block" type="button" id="ground-restart">Start the breathing again</button>
      <button class="btn btn-quiet btn-block" type="button" data-close="ground-sheet">Go back to the conversation</button>
    </div>
  </div>
</div>

<p class="sr-only" id="live" aria-live="polite"></p>
<script>window.SW_API = 'api.php';</script>
<script src="assets/chat.js"></script>
</body>
</html>
