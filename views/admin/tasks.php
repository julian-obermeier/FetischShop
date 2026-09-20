<main class="section">
<div class="admin-page-intro"><div><span class="eyebrow">Aufgabenvorlagen</span><h1>Aufgabenbibliothek</h1><p>Wiederverwendbare Zusatzaufgaben mit Frist, Vergütung, Eingabefeldern und Erforderliche Fotos.</p></div></div>

<section class="card admin-form settings-section"><h2>Neue Vorlage anlegen</h2>
<form method="post" class="form-grid"><?=App\Core\Csrf::field()?>
<label class="full">Titel<input name="title" required></label>
<label class="full">Beschreibung<textarea name="description" rows="3"></textarea></label>
<label>Frist nach Zuweisung (Minuten)<input name="deadline_minutes" type="number" min="1" value="60"></label>
<label>Zusatzvergütung (€)<input name="compensation" type="number" min="0" step="0.01" value="0"></label>
<label class="full">Wenn die Aufgabe nicht erledigt wird<select name="violation_missing"><option value="one_violation">Prüffall erzeugen</option><option value="none">Nur im Verlauf dokumentieren</option></select></label>

<div class="full">
<div class="repeater-head"><div><h3>Angaben der Verkäuferin</h3><p class="muted">Lege fest, welche Angaben bei der Aufgabe ausgefüllt werden müssen.</p></div><button type="button" class="btn" data-add-row="tpl-task-field" data-target="#task-fields-new">Feld hinzufügen</button></div>
<div class="structured-list" id="task-fields-new"></div>
</div>

<div class="full">
<div class="repeater-head"><div><h3>Pflichtfotos</h3><p class="muted">Lege fest, welche Fotos zusammen mit der Aufgabe eingereicht werden müssen.</p></div><button type="button" class="btn" data-add-row="tpl-task-photo" data-target="#task-photos-new">Fotoanforderung hinzufügen</button></div>
<div class="structured-list" id="task-photos-new"></div>
</div>
<button class="btn primary full">Vorlage speichern</button></form>
</section>

<section><h2>Vorhandene Vorlagen</h2><div class="worklist">
<?php foreach($templates as $t): $fields=json_decode($t['fields_json']?:'[]',true)?:[];$photos=json_decode($t['photos_json']?:'[]',true)?:[];$violation=json_decode($t['violation_json']?:'{}',true)?:[];?>
<details class="card admin-form">
<summary><strong><?=App\Core\View::e($t['title'])?></strong> · <?=number_format((float)$t['compensation'],2,',','.')?> € · <?= (int)$t['deadline_minutes'] ?> Min. · <?=$t['is_active']?'aktiv':'inaktiv'?></summary>
<form method="post" class="form-grid" style="margin-top:16px"><?=App\Core\Csrf::field()?><input type="hidden" name="id" value="<?=$t['id']?>">
<label class="full">Titel<input name="title" value="<?=App\Core\View::e($t['title'])?>" required></label>
<label class="full">Beschreibung<textarea name="description" rows="3"><?=App\Core\View::e($t['description']??'')?></textarea></label>
<label>Standardfrist (Minuten)<input name="deadline_minutes" type="number" min="1" value="<?=$t['deadline_minutes']?>"></label>
<label>Zusatzvergütung (€)<input name="compensation" type="number" min="0" step="0.01" value="<?=App\Core\View::e($t['compensation'])?>"></label>
<label>Bei Nichterfüllung<select name="violation_missing"><option value="one_violation" <?=($violation['missing']??'one_violation')==='one_violation'?'selected':''?>>Einen Verstoß erzeugen</option><option value="none" <?=($violation['missing']??'one_violation')==='none'?'selected':''?>>Nur dokumentieren</option></select></label>
<label class="check"><input type="checkbox" name="is_active" value="1" <?=$t['is_active']?'checked':''?>><span>Vorlage aktiv</span></label>

<div class="full"><div class="repeater-head"><h3>Eingabefelder</h3><button type="button" class="btn" data-add-row="tpl-task-field" data-target="#task-fields-<?=$t['id']?>">Feld hinzufügen</button></div>
<div class="structured-list" id="task-fields-<?=$t['id']?>"><?php foreach($fields as $field):?>
<div class="structured-row" data-repeat-row>
<label class="span-2">Schlüssel<input name="field_key[]" value="<?=App\Core\View::e($field['key']??'')?>"></label>
<label class="span-3">Bezeichnung<input name="field_label[]" value="<?=App\Core\View::e($field['label']??'')?>"></label>
<label class="span-2">Typ<select name="field_type[]"><?php foreach(['text'=>'Text','textarea'=>'Längerer Text','number'=>'Zahl','scale'=>'Skala','select'=>'Auswahl','boolean'=>'Ja/Nein'] as $k=>$v):?><option value="<?=$k?>" <?=($field['type']??'text')===$k?'selected':''?>><?=$v?></option><?php endforeach;?></select></label>
<label class="span-1">Pflicht<select name="field_required[]"><option value="1" <?=!empty($field['required'])?'selected':''?>>Ja</option><option value="0" <?=empty($field['required'])?'selected':''?>>Nein</option></select></label>
<label class="span-1">Min<input name="field_min[]" type="number" value="<?=App\Core\View::e($field['min']??'')?>"></label>
<label class="span-1">Max<input name="field_max[]" type="number" value="<?=App\Core\View::e($field['max']??'')?>"></label>
<label class="span-2">Auswahlwerte<textarea name="field_options[]" rows="2"><?php foreach(($field['options']??[]) as $o):?><?=App\Core\View::e($o)."
"?><?php endforeach;?></textarea></label>
<div class="span-12"><button type="button" class="btn ghost" data-remove-row>Feld entfernen</button></div>
</div>
<?php endforeach;?></div></div>

<div class="full"><div class="repeater-head"><h3>Pflichtfotos</h3><button type="button" class="btn" data-add-row="tpl-task-photo" data-target="#task-photos-<?=$t['id']?>">Fotoanforderung hinzufügen</button></div>
<div class="structured-list" id="task-photos-<?=$t['id']?>"><?php foreach($photos as $photo):?><div class="structured-row" data-repeat-row><label class="span-8">Beschreibung<input name="photo_label[]" value="<?=App\Core\View::e($photo['label']??'')?>"></label><label class="span-2">Anzahl<input name="photo_count[]" type="number" min="1" value="<?=max(1,(int)($photo['required_count']??1))?>"></label><div class="span-2"><button type="button" class="btn ghost" data-remove-row>Entfernen</button></div></div><?php endforeach;?></div></div>

<button class="btn primary full">Änderungen speichern</button>
</form></details>
<?php endforeach;?><?php if(!$templates):?><div class="empty">Noch keine Aufgabenvorlagen vorhanden.</div><?php endif;?>
</div></section>

<template id="tpl-task-field"><div class="structured-row" data-repeat-row><label class="span-2">Schlüssel<input name="field_key[]" placeholder="z. B. zustand"></label><label class="span-3">Bezeichnung<input name="field_label[]" placeholder="z. B. Zustandsbewertung"></label><label class="span-2">Typ<select name="field_type[]"><option value="text">Text</option><option value="textarea">Längerer Text</option><option value="number">Zahl</option><option value="scale">Skala</option><option value="select">Auswahl</option><option value="boolean">Ja/Nein</option></select></label><label class="span-1">Pflicht<select name="field_required[]"><option value="1">Ja</option><option value="0">Nein</option></select></label><label class="span-1">Min<input name="field_min[]" type="number"></label><label class="span-1">Max<input name="field_max[]" type="number"></label><label class="span-2">Auswahlwerte<textarea name="field_options[]" rows="2" placeholder="Eine Option je Zeile"></textarea></label><div class="span-12"><button type="button" class="btn ghost" data-remove-row>Feld entfernen</button></div></div></template>
<template id="tpl-task-photo"><div class="structured-row" data-repeat-row><label class="span-8">Beschreibung<input name="photo_label[]" placeholder="Was soll auf dem Foto zu sehen sein?"></label><label class="span-2">Anzahl<input name="photo_count[]" type="number" min="1" value="1"></label><div class="span-2"><button type="button" class="btn ghost" data-remove-row>Entfernen</button></div></div></template>
</main>