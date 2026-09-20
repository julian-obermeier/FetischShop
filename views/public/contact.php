<main class="section narrow">
<div class="seller-hero"><div><span class="eyebrow">Support</span><h1>Wie können wir helfen?</h1><p>Für allgemeine Fragen erstellst du hier ein Supportticket. Geht es um einen konkreten Auftrag, nutze bitte den Chat direkt im Auftrag.</p></div></div>
<section class="card admin-form">
<form method="post" action="/kontakt" class="form-grid"><?=App\Core\Csrf::field()?>
<?php if(empty($seller)):?>
<label>Name<input name="name" value="<?=App\Core\View::e($old['name']??'')?>" autocomplete="name" required></label>
<label>E-Mail-Adresse<input name="email" type="email" value="<?=App\Core\View::e($old['email']??'')?>" autocomplete="email" required></label>
<?php else:?>
<div class="full notice"><strong>Anfrage als <?=App\Core\View::e(trim($seller['first_name'].' '.$seller['last_name']))?></strong><br>Dein Ticket wird automatisch deinem Konto zugeordnet. Bestätigungen und Antworten senden wir an <strong><?=App\Core\View::e($seller['email'])?></strong>.</div>
<?php endif;?>
<label class="full">Betreff<input name="subject" value="<?=App\Core\View::e($old['subject']??'')?>" required placeholder="Kurzer Betreff deiner Anfrage"></label>
<label class="full">Nachricht<textarea name="message" rows="7" minlength="10" required placeholder="Beschreibe kurz, was passiert ist oder wobei du Hilfe brauchst."><?=App\Core\View::e($old['message']??'')?></textarea><small class="field-help">Mindestens 10 Zeichen.</small></label>
<button class="btn primary full">Ticket erstellen</button>
</form>
</section>
<?php if(!empty($seller)):?><p class="muted">Bereits erstellte Tickets findest du jederzeit unter <a href="/konto/support"><strong>Support</strong></a>.</p><?php endif;?>
</main>