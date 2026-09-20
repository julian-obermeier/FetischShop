<main class="section">
<div class="admin-page-intro"><div><span class="eyebrow">Angebotsstruktur</span><h1><?=App\Core\View::e($category['name'])?></h1><p>Name, Status und zusätzliche Angaben, die bei der Artikelwahl abgefragt werden.</p></div><a class="btn ghost" href="/admin/kategorien">Zurück zu Kategorien</a></div>

<section class="card admin-form settings-section"><h2>Grundeinstellungen</h2><form method="post" action="/admin/kategorien/<?=$category['id']?>" class="form-grid"><?=App\Core\Csrf::field()?>
<label>Name<input name="name" value="<?=App\Core\View::e($category['name'])?>" required></label>
<label>Übergeordnete Kategorie<select name="parent_id"><option value="">Keine</option><?php foreach($parents as $p):?><option value="<?=$p['id']?>" <?=((string)($category['parent_id']??'')===(string)$p['id'])?'selected':''?>><?=App\Core\View::e($p['name'])?></option><?php endforeach;?></select></label>
<label>Sortierung<input name="sort_order" type="number" value="<?=$category['sort_order']?>"></label>
<label>Symbol / Kennzeichen<input name="icon" value="<?=App\Core\View::e($category['icon']??'')?>" placeholder="optional"></label>
<label class="check"><input type="checkbox" name="is_digital" value="1" <?=$category['is_digital']?'checked':''?>><span>Digitale Kategorie</span></label>
<label class="check"><input type="checkbox" name="is_active" value="1" <?=$category['is_active']?'checked':''?>><span>Kategorie aktiv</span></label>
<input type="hidden" name="slug" value="<?=App\Core\View::e($category['slug'])?>">
<button class="btn primary full">Kategorie speichern</button></form></section>

<section class="card admin-form settings-section"><h2>Zusätzliche Artikelangabe</h2><p class="muted">Lege ein weiteres Feld fest, das Verkäuferinnen bei der Artikelwahl ausfüllen sollen.</p>
<form method="post" action="/admin/kategorien/<?=$category['id']?>/felder" class="form-grid"><?=App\Core\Csrf::field()?>
<label class="full">Bezeichnung<input name="label" placeholder="z. B. Schuhgröße" required></label>
<label>Typ<select name="field_type"><option value="text">Text</option><option value="number">Zahl</option><option value="select">Auswahl</option><option value="multiselect">Mehrfachauswahl</option><option value="boolean">Ja / Nein</option><option value="date">Datum</option></select></label>
<label>Sortierung<input name="sort_order" type="number" value="0"></label>
<label class="full">Auswahlmöglichkeiten<textarea name="options" rows="4" placeholder="Nur bei Auswahlfeldern nötig. Eine Möglichkeit pro Zeile."></textarea></label>
<label class="check full"><input type="checkbox" name="is_required" value="1"><span>Dieses Feld ist Pflicht</span></label>
<button class="btn primary full">Feld anlegen</button></form></section>

<section><h2>Hinterlegte Artikelangaben</h2><div class="worklist">
<?php foreach($fields as $field):?>
<div class="card admin-form"><form method="post" action="/admin/kategorien/<?=$category['id']?>/felder/<?=$field['id']?>" class="form-grid"><?=App\Core\Csrf::field()?>
<label class="full">Bezeichnung<input name="label" value="<?=App\Core\View::e($field['label'])?>" required></label>
<label>Typ<select name="field_type"><?php foreach(['text'=>'Text','number'=>'Zahl','select'=>'Auswahl','multiselect'=>'Mehrfachauswahl','boolean'=>'Ja / Nein','date'=>'Datum'] as $v=>$l):?><option value="<?=$v?>" <?=$field['field_type']===$v?'selected':''?>><?=$l?></option><?php endforeach;?></select></label>
<label>Sortierung<input name="sort_order" type="number" value="<?=$field['sort_order']?>"></label>
<label class="check"><input type="checkbox" name="is_required" value="1" <?=$field['is_required']?'checked':''?>><span>Pflichtfeld</span></label>
<label class="check"><input type="checkbox" name="is_active" value="1" <?=$field['is_active']?'checked':''?>><span>Aktiv</span></label>
<label class="full">Auswahlmöglichkeiten<textarea name="options" rows="3" placeholder="Eine Möglichkeit pro Zeile"><?php foreach(json_decode($field['options_json']?:'[]',true)?:[] as $opt):?><?=App\Core\View::e($opt)."
"?><?php endforeach;?></textarea></label>
<button class="btn primary">Speichern</button></form>
<form method="post" action="/admin/kategorien/<?=$category['id']?>/felder/<?=$field['id']?>/loeschen"><?=App\Core\Csrf::field()?><button class="btn ghost" data-confirm="Feld wirklich löschen?">Feld löschen</button></form></div>
<?php endforeach;?><?php if(!$fields):?><div class="empty">Für diese Kategorie sind noch keine zusätzlichen Artikelfelder angelegt.</div><?php endif;?>
</div></section>
</main>