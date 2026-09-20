<main class="section narrow">
<div class="seller-hero"><div><span class="eyebrow">Konto</span><h1>Mein Profil</h1><p>Halte deine Kontakt- und Adressdaten aktuell. Änderungen an der E-Mail-Adresse müssen erneut bestätigt werden.</p></div></div>

<div class="seller-kpi-grid">
<div class="seller-kpi"><span>E-Mail</span><strong style="font-size:1rem"><?=App\Core\View::e($seller['email'])?></strong><small><?=$seller['email_verified_at']?'Bestätigt':'Noch nicht bestätigt'?></small></div>
<div class="seller-kpi"><span>Telefon</span><strong style="font-size:1rem"><?=App\Core\View::e($seller['phone'])?></strong><small>Kontaktangabe</small></div>
<div class="seller-kpi"><span>Wohnort</span><strong style="font-size:1rem"><?=App\Core\View::e($seller['postal_code'].' '.$seller['city'])?></strong><small>Stammdaten</small></div>
<div class="seller-kpi"><span>Geburtsdatum</span><strong style="font-size:1rem"><?=date('d.m.Y',strtotime($seller['birth_date']))?></strong><small>nicht selbst änderbar</small></div>
</div>

<section class="card admin-form seller-section-card"><h2>Persönliche Daten</h2><form method="post" action="/konto/profil" class="form-grid"><?=App\Core\Csrf::field()?>
<label>Vorname<input name="first_name" value="<?=App\Core\View::e($seller['first_name'])?>" required></label>
<label>Nachname<input name="last_name" value="<?=App\Core\View::e($seller['last_name'])?>" required></label>
<label class="full">Straße und Hausnummer<input name="street" value="<?=App\Core\View::e($seller['street'])?>" required></label>
<label>PLZ<input name="postal_code" value="<?=App\Core\View::e($seller['postal_code'])?>" required></label>
<label>Ort<input name="city" value="<?=App\Core\View::e($seller['city'])?>" required></label>
<label class="full">Telefonnummer<input name="phone" value="<?=App\Core\View::e($seller['phone'])?>" required></label>
<button class="btn primary full">Persönliche Daten speichern</button></form></section>

<section class="card admin-form seller-section-card"><h2>E-Mail-Adresse</h2><p class="muted">Nach einer Änderung muss die neue Adresse erneut bestätigt werden. Bis zur Bestätigung kannst du keine neuen Aufträge annehmen.</p><form method="post" action="/konto/profil/email" class="form-grid"><?=App\Core\Csrf::field()?><label class="full">Neue E-Mail-Adresse<input name="email" type="email" value="<?=App\Core\View::e($seller['email'])?>" required></label><button class="btn primary full">E-Mail-Adresse ändern</button></form></section>

<div class="notice">Wenn du dein Konto löschen möchtest, wende dich über den Auftragschat oder die Kontaktseite an den Betreiber. Gesetzlich erforderliche Geschäfts- und Zahlungsdaten können nicht immer sofort gelöscht werden.</div>
</main>