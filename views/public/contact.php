<main class="section narrow">
<div class="seller-hero"><div><span class="eyebrow">Kontakt & Hilfe</span><h1>Support kontaktieren</h1><p>Für allgemeine Fragen kannst du hier direkt eine Supportanfrage erstellen. Auftragsbezogene Fragen gehören weiterhin in den jeweiligen Auftragschat.</p></div></div>
<section class="card admin-form">
<form method="post" action="/kontakt" class="form-grid"><?=App\Core\Csrf::field()?>
<?php if(empty($seller)):?>
<label>Name<input name="name" value="<?=App\Core\View::e($old['name']??'')?>" autocomplete="name" required></label>
<label>E-Mail-Adresse<input name="email" type="email" value="<?=App\Core\View::e($old['email']??'')?>" autocomplete="email" required></label>
<?php else:?>
<div class="full notice">Die Anfrage wird deinem Verkäuferinnen-Konto zugeordnet und an deine hinterlegte E-Mail-Adresse gesendet.</div>
<?php endif;?>
<label class="full">Betreff<input name="subject" value="<?=App\Core\View::e($old['subject']??'')?>" required placeholder="Worum geht es?"></label>
<label class="full">Nachricht<textarea name="message" rows="7" minlength="10" required placeholder="Beschreibe dein Anliegen möglichst genau."><?=App\Core\View::e($old['message']??'')?></textarea><small class="field-help">Mindestens 10 Zeichen.</small></label>
<button class="btn primary full">Supportanfrage absenden</button>
</form>
</section>
<?php if(!empty($seller)):?><p class="muted">Deine bisherigen Anfragen findest du unter <a href="/konto/support"><strong>Mein Support</strong></a>.</p><?php endif;?>
</main>