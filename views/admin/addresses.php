<main class="section">
<div class="section-head"><div><span class="eyebrow">Versand</span><h1>Empfängeradressen</h1></div><a class="btn ghost" href="/admin">Zurück</a></div>
<section class="card admin-form"><h2>Neue Adresse</h2><form method="post" action="/admin/empfaengeradressen" class="form-grid"><?=App\Core\Csrf::field()?>
<label>Bezeichnung<input name="label" required placeholder="z. B. Hauptadresse"></label>
<label>Empfänger<input name="recipient_name" required></label>
<label class="full">Straße / Hausnummer<input name="street" required></label>
<label>PLZ<input name="postal_code" required></label><label>Ort<input name="city" required></label>
<label>Land<input name="country_code" maxlength="2" value="DE" required></label>
<label class="check"><input type="checkbox" name="is_active" value="1" checked> Aktiv</label>
<button class="btn primary full">Adresse speichern</button></form></section>
<section><h2>Vorhandene Adressen</h2><div class="worklist"><?php foreach($addresses as $a):?><div class="card"><form method="post" action="/admin/empfaengeradressen" class="form-grid"><?=App\Core\Csrf::field()?><input type="hidden" name="id" value="<?=$a['id']?>">
<label>Bezeichnung<input name="label" value="<?=App\Core\View::e($a['label'])?>" required></label>
<label>Empfänger<input name="recipient_name" value="<?=App\Core\View::e($a['recipient_name'])?>" required></label>
<label class="full">Straße / Hausnummer<input name="street" value="<?=App\Core\View::e($a['street'])?>" required></label>
<label>PLZ<input name="postal_code" value="<?=App\Core\View::e($a['postal_code'])?>" required></label>
<label>Ort<input name="city" value="<?=App\Core\View::e($a['city'])?>" required></label>
<label>Land<input name="country_code" maxlength="2" value="<?=App\Core\View::e($a['country_code'])?>" required></label>
<label class="check"><input type="checkbox" name="is_active" value="1" <?=$a['is_active']?'checked':''?>> Aktiv</label>
<button class="btn full">Änderungen speichern</button></form></div><?php endforeach;?><?php if(!$addresses):?><div class="empty">Noch keine Empfängeradresse vorhanden.</div><?php endif;?></div></section>
</main>