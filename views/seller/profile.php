<main class="section narrow">
<div class="seller-hero"><div><span class="eyebrow">Profil & Konto</span><h1>Meine Daten</h1><p>Hier verwaltest du deine persönlichen Angaben und die E-Mail-Adresse für dein Konto.</p></div></div>

<div class="seller-kpi-grid">
<div class="seller-kpi"><span>E-Mail</span><strong style="font-size:1rem"><?=App\Core\View::e($seller['email'])?></strong><small><?=$seller['email_verified_at']?'Bestätigt':'Noch nicht bestätigt'?></small></div>
<div class="seller-kpi"><span>Telefon</span><strong style="font-size:1rem"><?=App\Core\View::e($seller['phone'])?></strong><small>für Rückfragen</small></div>
<div class="seller-kpi"><span>Wohnort</span><strong style="font-size:1rem"><?=App\Core\View::e($seller['postal_code'].' '.$seller['city'])?></strong><small>Adresse</small></div>
<div class="seller-kpi"><span>Geburtsdatum</span><strong style="font-size:1rem"><?=date('d.m.Y',strtotime($seller['birth_date']))?></strong><small>fest hinterlegt</small></div>
</div>

<section class="card admin-form seller-section-card"><h2>Kontakt- und Adressdaten</h2><form method="post" action="/konto/profil" class="form-grid"><?=App\Core\Csrf::field()?>
<label>Vorname<input name="first_name" value="<?=App\Core\View::e($seller['first_name'])?>" required></label>
<label>Nachname<input name="last_name" value="<?=App\Core\View::e($seller['last_name'])?>" required></label>
<label class="full">Straße und Hausnummer<input name="street" value="<?=App\Core\View::e($seller['street'])?>" required></label>
<label>PLZ<input name="postal_code" value="<?=App\Core\View::e($seller['postal_code'])?>" required></label>
<label>Ort<input name="city" value="<?=App\Core\View::e($seller['city'])?>" required></label>
<label class="full">Telefonnummer<input name="phone" value="<?=App\Core\View::e($seller['phone'])?>" required></label>
<button class="btn primary full">Änderungen speichern</button></form></section>

<section class="card admin-form seller-section-card"><h2>E-Mail-Adresse ändern</h2><p class="muted">Nach einer Änderung senden wir dir einen neuen Bestätigungslink. Bis zur Bestätigung bleibt die Annahme neuer Aufträge gesperrt.</p><form method="post" action="/konto/profil/email" class="form-grid"><?=App\Core\Csrf::field()?><label class="full">Neue E-Mail-Adresse<input name="email" type="email" value="<?=App\Core\View::e($seller['email'])?>" required></label><button class="btn primary full">E-Mail-Adresse ändern</button></form></section>

<div class="notice">Du möchtest dein Konto schließen oder hast Fragen zu gespeicherten Daten? Nutze dafür den Supportbereich. Auftrags- und Zahlungsdaten können aus gesetzlichen oder betrieblichen Gründen teilweise weiterhin aufbewahrt werden.</div>
</main>