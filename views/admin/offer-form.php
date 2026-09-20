<?php
$decode = static function($raw, array $default=[]): array {
    if(!$raw) return $default;
    $data=json_decode((string)$raw,true);
    return is_array($data)?$data:$default;
};
$rulesCfg=$decode($version['rules_json']??null);
$evidenceCfg=$decode($version['evidence_json']??null);
$startCfg=$decode($version['start_control_json']??null);
$shippingCfg=$decode($version['shipping_json']??null);
$endCfg=$decode($version['end_workflow_json']??null);
$violationCfg=$decode($version['violation_json']??null);

$ruleText=implode("\n",$rulesCfg['items']??[]);
$windows=$evidenceCfg['windows']??[];
if(!$windows && !$offer)$windows=[
 ['name'=>'Morgen','start'=>'06:00','end'=>'10:00','required_count'=>1],
 ['name'=>'Mittag','start'=>'12:00','end'=>'16:00','required_count'=>1],
 ['name'=>'Abend','start'=>'18:00','end'=>'23:59','required_count'=>1],
];
$prechecks=$startCfg['requirements']??[];
$steps=$endCfg['steps']??[];
if(!$steps && !$offer)$steps=[
 ['title'=>'Nutzung beenden / ausziehen','instructions'=>'Bestätigen, dass die Nutzung beendet wurde.','type'=>'checkbox','required'=>true],
 ['title'=>'Artikel dokumentieren','instructions'=>'Aktuelles Foto des Artikels aufnehmen.','type'=>'photo','required'=>true],
 ['title'=>'Verpackung dokumentieren','instructions'=>'Verschlossene Verpackung fotografieren.','type'=>'photo','required'=>true],
 ['title'=>'Versand abschließen','instructions'=>'Trackingnummer oder Einlieferungsbeleg hinterlegen.','type'=>'tracking_or_receipt','required'=>true],
];
?>
<main class="section narrow">
<div class="admin-page-intro"><div><span class="eyebrow">Angebotsverwaltung</span><h1><?=$offer?'Angebot bearbeiten':'Neues Angebot'?></h1><p>Alle Einstellungen werden über normale Eingabefelder verwaltet. Beim Speichern entsteht eine unveränderliche Angebotsversion.</p></div><div class="actions"><a class="btn ghost" href="/admin/angebote">Zurück zu Angeboten</a></div></div>

<?php if($offer):?>
<div class="notice">Bereits angenommene Aufträge behalten immer die damals gültige Version. Änderungen wirken nur auf neue Annahmen.</div>
<div class="row-actions offer-actions"><form method="post" action="/admin/angebote/<?=$offer['id']?>/duplizieren"><?=App\Core\Csrf::field()?><button class="btn">Als neues Angebot duplizieren</button></form><form method="post" action="/admin/angebote/<?=$offer['id']?>/vorlage"><?=App\Core\Csrf::field()?><button class="btn">Als Vorlage speichern</button></form></div>
<?php endif;?>

<form method="post" class="admin-form form-grid"><?=App\Core\Csrf::field()?>

<section class="card full settings-section">
<h2>1. Grunddaten</h2>
<div class="form-grid">
<label class="full">Titel<input name="title" value="<?=App\Core\View::e($offer['title']??'')?>" required></label>
<label>Kategorie<select name="category_id" required><?php foreach($categories as $c):?><option value="<?=$c['id']?>" <?=((string)($offer['category_id']??'')===(string)$c['id'])?'selected':''?>><?=App\Core\View::e($c['name'])?><?=$c['is_digital']?' · digital':''?></option><?php endforeach;?></select></label>
<label>Status<select name="status"><option value="draft" <?=($offer['status']??'')==='draft'?'selected':''?>>Entwurf</option><option value="active" <?=($offer['status']??'')==='active'?'selected':''?>>Aktiv</option><option value="disabled" <?=($offer['status']??'')==='disabled'?'selected':''?>>Deaktiviert</option></select></label>
<label class="full">Beschreibung<textarea name="description" rows="6" required><?=App\Core\View::e($version['description']??'')?></textarea></label>
<label>Gesamt-Grundvergütung (€)<input name="compensation" type="number" step="0.01" min="0" value="<?=App\Core\View::e($version['compensation']??'0.00')?>" required></label>
<label>Erfüllungsmodell<select name="fulfillment_model"><?php foreach(['days'=>'Mehrere Tage','units'=>'Mehrere Einheiten','once'=>'Einmalig','digital'=>'Digital','combined'=>'Kombination'] as $v=>$l):?><option value="<?=$v?>" <?=($version['fulfillment_model']??'days')===$v?'selected':''?>><?=$l?></option><?php endforeach;?></select></label>
<label>Dauer / Anzahl<input name="duration_value" type="number" min="1" value="<?=App\Core\View::e($version['duration_value']??'')?>"></label>
<label>Einheit<input name="duration_unit" value="<?=App\Core\View::e($version['duration_unit']??'Tage')?>" placeholder="z. B. Tage"></label>
</div>
</section>

<section class="card full settings-section">
<h2>2. Privatangebot</h2>
<div class="form-grid">
<label class="check full"><input type="checkbox" name="is_private" value="1" <?=!empty($offer['is_private'])?'checked':''?>><span>Dieses Angebot ist nur für eine bestimmte Verkäuferin bestimmt.</span></label>
<label class="full">Verkäuferin<select name="seller_id"><option value="">Keine feste Verkäuferin</option><?php foreach($sellers as $s):?><option value="<?=$s['id']?>" <?=((string)($offer['seller_id']??'')===(string)$s['id'])?'selected':''?>><?=App\Core\View::e($s['last_name'].', '.$s['first_name'].' · '.$s['email'])?></option><?php endforeach;?></select></label>
<label>Annahmefrist<input name="acceptance_deadline" type="datetime-local" value="<?=!empty($offer['acceptance_deadline'])?date('Y-m-d\TH:i',strtotime($offer['acceptance_deadline'])):''?>"></label>
</div>
</section>

<section class="card full settings-section">
<h2>3. Regeln</h2>
<label class="full">Regeln – eine Regel pro Zeile<textarea name="rules_text" rows="7" placeholder="Artikel darf während der Durchführung nicht gewechselt werden.&#10;Nachweise müssen innerhalb des jeweiligen Zeitfensters eingereicht werden."><?=App\Core\View::e($ruleText)?></textarea><small class="field-help">Du schreibst einfach normale Sätze. Jede Zeile wird als eigene Regel gespeichert.</small></label>
</section>

<section class="card full settings-section">
<div class="repeater-head"><div><h2>4. Nachweisfenster</h2><p class="muted">Wann und wie viele Pflichtaufnahmen pro Durchführungstag verlangt werden.</p></div><button type="button" class="btn" data-add-row="tpl-evidence-window" data-target="#evidence-windows">Nachweisfenster hinzufügen</button></div>
<div class="structured-list" id="evidence-windows">
<?php foreach($windows as $w):?>
<div class="structured-row" data-repeat-row>
<label class="span-3">Bezeichnung<input name="evidence_name[]" value="<?=App\Core\View::e($w['name']??'')?>"></label>
<label class="span-2">Beginn<input name="evidence_start[]" type="time" value="<?=App\Core\View::e($w['start']??'')?>"></label>
<label class="span-2">Ende<input name="evidence_end[]" type="time" value="<?=App\Core\View::e($w['end']??'')?>"></label>
<label class="span-2">Anzahl Fotos<input name="evidence_count[]" type="number" min="1" value="<?=max(1,(int)($w['required_count']??1))?>"></label>
<div class="span-3 remove-row"><button type="button" class="btn ghost" data-remove-row>Entfernen</button></div>
</div>
<?php endforeach;?>
</div>
</section>

<section class="card full settings-section">
<div class="repeater-head"><div><h2>5. Vorabkontrolle</h2><p class="muted">Pflichtperspektiven vor dem eigentlichen Start. Wenn du nichts einträgst, verwendet das System passende Standardperspektiven je Kategorie.</p></div><button type="button" class="btn" data-add-row="tpl-precheck" data-target="#precheck-list">Perspektive hinzufügen</button></div>
<div class="structured-list" id="precheck-list">
<?php foreach($prechecks as $pr):?>
<div class="structured-row" data-repeat-row>
<label class="span-4">Bezeichnung<input name="precheck_label[]" value="<?=App\Core\View::e($pr['label']??'')?>"></label>
<label class="span-5">Beschreibung<input name="precheck_description[]" value="<?=App\Core\View::e($pr['description']??'')?>"></label>
<label class="span-1">Anzahl<input name="precheck_count[]" type="number" min="1" value="<?=max(1,(int)($pr['required_count']??1))?>"></label>
<div class="span-2 remove-row"><button type="button" class="btn ghost" data-remove-row>Entfernen</button></div>
</div>
<?php endforeach;?>
</div>
</section>

<section class="card full settings-section">
<h2>6. Versandhinweise</h2>
<label class="full">Allgemeine Hinweise zum Versand<textarea name="shipping_notes" rows="4" placeholder="Zum Beispiel: neutral verpacken, keine zusätzliche Reinigung, Versand erst nach Freigabe."><?=App\Core\View::e($shippingCfg['instructions']??'')?></textarea></label>
<div class="repeater-head"><div><h3>Versandschritte</h3><p class="muted">Diese Schritte werden der Verkäuferin nacheinander angezeigt.</p></div><button type="button" class="btn" data-add-row="tpl-shipping-step" data-target="#shipping-steps">Versandschritt hinzufügen</button></div>
<div class="structured-list" id="shipping-steps">
<?php foreach($steps as $st):?>
<div class="structured-row" data-repeat-row>
<label class="span-3">Titel<input name="shipping_step_title[]" value="<?=App\Core\View::e($st['title']??'')?>"></label>
<label class="span-4">Anweisung<input name="shipping_step_instructions[]" value="<?=App\Core\View::e($st['instructions']??'')?>"></label>
<label class="span-2">Art<select name="shipping_step_type[]"><?php foreach(['checkbox'=>'Bestätigung','photo'=>'Foto','text'=>'Text','tracking_or_receipt'=>'Tracking oder Beleg'] as $k=>$v):?><option value="<?=$k?>" <?=($st['type']??'checkbox')===$k?'selected':''?>><?=$v?></option><?php endforeach;?></select></label>
<label class="span-1">Pflicht<select name="shipping_step_required[]"><option value="1" <?=!array_key_exists('required',$st)||!empty($st['required'])?'selected':''?>>Ja</option><option value="0" <?=array_key_exists('required',$st)&&empty($st['required'])?'selected':''?>>Nein</option></select></label>
<div class="span-2 remove-row"><button type="button" class="btn ghost" data-remove-row>Entfernen</button></div>
</div>
<?php endforeach;?>
</div>
</section>

<section class="card full settings-section">
<h2>7. Verstöße und Verlängerungen</h2>
<div class="form-grid">
<label>Digitale Fristverstöße<select name="digital_violation_mode"><option value="extension" <?=($violationCfg['digital_violation_mode']??'extension')==='extension'?'selected':''?>>Frist um einen Tag verlängern</option><option value="log_only" <?=($violationCfg['digital_violation_mode']??'extension')==='log_only'?'selected':''?>>Nur dokumentieren</option></select></label>
<label>Physische Verstöße<input value="+1 unbezahlter Tag je bestätigtem Verstoß" readonly><small class="field-help">Die Verlängerung wird nur dem betroffenen Bestandteil zugeordnet.</small></label>
<label class="full">Interne Hinweise zur Verstoßbehandlung<textarea name="violation_notes" rows="3"><?=App\Core\View::e($violationCfg['notes']??'')?></textarea></label>
</div>
</section>

<section class="card full settings-section">
<div class="repeater-head"><div><h2>8. Zusatzoptionen</h2><p class="muted">Frei wählbare Zusatzleistungen oder Bedingungen mit eigener Vergütung.</p></div><button type="button" class="btn" data-add-row="tpl-option" data-target="#option-list-admin">Option hinzufügen</button></div>
<div class="structured-list" id="option-list-admin">
<?php foreach($options as $op): $req=$decode($op['requirements_json']??null);?>
<div class="structured-row" data-repeat-row>
<label class="span-3">Name<input name="option_name[]" value="<?=App\Core\View::e($op['name'])?>"></label>
<label class="span-4">Beschreibung<input name="option_description[]" value="<?=App\Core\View::e($op['description']??'')?>"></label>
<label class="span-2">Vergütung (€)<input name="option_price[]" type="number" step="0.01" min="0" value="<?=App\Core\View::e($op['price'])?>"></label>
<label class="span-3">Anforderung<input name="option_requirements[]" value="<?=App\Core\View::e($req['text']??'')?>" placeholder="z. B. beim Sport durchführen"></label>
<input type="hidden" name="option_sort_order[]" value="<?= (int)($op['sort_order']??0) ?>">
<div class="span-12"><button type="button" class="btn ghost" data-remove-row>Option entfernen</button></div>
</div>
<?php endforeach;?>
</div>
</section>

<section class="card full settings-section">
<div class="repeater-head"><div><h2>9. Kombi-Bestandteile</h2><p class="muted">Nur nötig, wenn ein Angebot aus mehreren physischen oder digitalen Bestandteilen besteht. Ohne Eintrag verwendet das System die Hauptkategorie des Angebots.</p></div><button type="button" class="btn" data-add-row="tpl-component" data-target="#component-list">Bestandteil hinzufügen</button></div>
<div class="structured-list" id="component-list">
<?php foreach($components as $co): $cfg=$decode($co['config_json']??null);$digital=$cfg['digital']??[];?>
<div class="structured-row" data-repeat-row>
<label class="span-3">Titel<input name="component_title[]" value="<?=App\Core\View::e($co['title'])?>"></label>
<label class="span-3">Kategorie<select name="component_category_id[]"><?php foreach($categories as $cat):?><option value="<?=$cat['id']?>" <?=((int)$co['category_id']===(int)$cat['id'])?'selected':''?>><?=App\Core\View::e($cat['name'])?></option><?php endforeach;?></select></label>
<label class="span-2">Typ<select name="component_type[]"><option value="physical" <?=$co['component_type']==='physical'?'selected':''?>>Physisch</option><option value="digital" <?=$co['component_type']==='digital'?'selected':''?>>Digital</option></select></label>
<label class="span-2">Vergütung (€)<input name="component_compensation[]" type="number" step="0.01" min="0" value="<?=App\Core\View::e($co['compensation'])?>"></label>
<label class="span-2">Erfüllung<select name="component_fulfillment_model[]"><?php foreach(['days'=>'Tage','units'=>'Einheiten','once'=>'Einmalig','digital'=>'Digital'] as $k=>$v):?><option value="<?=$k?>" <?=($co['fulfillment_model']??'once')===$k?'selected':''?>><?=$v?></option><?php endforeach;?></select></label>
<label class="span-2">Dauer / Anzahl<input name="component_duration_value[]" type="number" min="1" value="<?=App\Core\View::e($co['duration_value']??'')?>"></label>
<label class="span-2">Einheit<input name="component_duration_unit[]" value="<?=App\Core\View::e($co['duration_unit']??'')?>"></label>
<label class="span-2">Digitalformat<select name="component_format_type[]"><?php foreach(['mixed'=>'Gemischt','text'=>'Text','audio'=>'Audio','video'=>'Video'] as $k=>$v):?><option value="<?=$k?>" <?=($digital['format_type']??'mixed')===$k?'selected':''?>><?=$v?></option><?php endforeach;?></select></label>
<label class="span-2">Min. Textzeichen<input name="component_min_length[]" type="number" min="0" value="<?= (int)($digital['min_length']??0) ?>"></label>
<label class="span-2">Max. Textzeichen<input name="component_max_length[]" type="number" min="0" value="<?= (int)($digital['max_length']??0) ?>"></label>
<label class="span-2">Max. Datei MB<input name="component_max_file_mb[]" type="number" min="1" max="500" value="<?= (int)($digital['max_file_mb']??150) ?>"></label>
<label class="span-2">Digitale Deadline<input name="component_deadline[]" type="datetime-local" value="<?=!empty($digital['deadline'])?date('Y-m-d\TH:i',strtotime($digital['deadline'])):''?>"></label>
<input type="hidden" name="component_sort_order[]" value="<?= (int)($co['sort_order']??0) ?>">
<div class="span-12"><button type="button" class="btn ghost" data-remove-row>Bestandteil entfernen</button></div>
</div>
<?php endforeach;?>
</div>
</section>

<section class="card full settings-section">
<div class="repeater-head"><div><h2>10. Vorgeplante Zusatzaufgaben</h2><p class="muted">Aufgaben, die beim Start des Auftrags automatisch eingeplant werden.</p></div><button type="button" class="btn" data-add-row="tpl-offer-task" data-target="#offer-task-list">Aufgabe hinzufügen</button></div>
<div class="structured-list" id="offer-task-list">
<?php foreach($offerTasks as $task): $cfg=$decode($task['config_json']??null);?>
<div class="structured-row" data-repeat-row>
<label class="span-3">Titel<input name="offer_task_title[]" value="<?=App\Core\View::e($task['title'])?>"></label>
<label class="span-3">Vorlage<select name="offer_task_template_id[]"><option value="">Keine Vorlage</option><?php foreach($taskTemplates as $tpl):?><option value="<?=$tpl['id']?>" <?=((string)($task['task_template_id']??'')===(string)$tpl['id'])?'selected':''?>><?=App\Core\View::e($tpl['title'])?></option><?php endforeach;?></select></label>
<label class="span-3">Bezug auf Kategorie<select name="offer_task_category_id[]"><option value="">Allgemeiner Auftrag</option><?php foreach($categories as $cat):?><option value="<?=$cat['id']?>" <?=((string)($cfg['category_id']??'')===(string)$cat['id'])?'selected':''?>><?=App\Core\View::e($cat['name'])?></option><?php endforeach;?></select></label>
<label class="span-3">Zeitplan<select name="offer_task_schedule_type[]"><option value="once" <?=($cfg['schedule_type']??'once')==='once'?'selected':''?>>Einmalig</option><option value="recurring" <?=($cfg['schedule_type']??'once')==='recurring'?'selected':''?>>Wiederkehrend</option><option value="interval" <?=($cfg['schedule_type']??'once')==='interval'?'selected':''?>>Intervall</option></select></label>
<label class="span-6">Beschreibung<input name="offer_task_description[]" value="<?=App\Core\View::e($cfg['description']??'')?>"></label>
<label class="span-2">Erstmals nach Min.<input name="offer_task_offset_minutes[]" type="number" min="0" value="<?= (int)($cfg['offset_minutes']??60) ?>"></label>
<label class="span-2">Wiederholen alle Min.<input name="offer_task_repeat_every_minutes[]" type="number" min="0" value="<?= (int)($cfg['repeat_every_minutes']??0) ?>"></label>
<label class="span-2">Wiederholungen<input name="offer_task_repeat_count[]" type="number" min="0" max="100" value="<?= (int)($cfg['repeat_count']??0) ?>"></label>
<input type="hidden" name="offer_task_sort_order[]" value="<?= (int)($task['sort_order']??0) ?>">
<div class="span-12"><button type="button" class="btn ghost" data-remove-row>Aufgabe entfernen</button></div>
</div>
<?php endforeach;?>
</div>
</section>

<div class="full actions"><button class="btn primary"><?=$offer?'Neue Angebotsversion speichern':'Angebot anlegen'?></button><a class="btn ghost" href="/admin/angebote">Abbrechen</a></div>
</form>

<template id="tpl-evidence-window"><div class="structured-row" data-repeat-row><label class="span-3">Bezeichnung<input name="evidence_name[]" placeholder="z. B. Abend"></label><label class="span-2">Beginn<input name="evidence_start[]" type="time"></label><label class="span-2">Ende<input name="evidence_end[]" type="time"></label><label class="span-2">Anzahl Fotos<input name="evidence_count[]" type="number" min="1" value="1"></label><div class="span-3 remove-row"><button type="button" class="btn ghost" data-remove-row>Entfernen</button></div></div></template>
<template id="tpl-precheck"><div class="structured-row" data-repeat-row><label class="span-4">Bezeichnung<input name="precheck_label[]" placeholder="z. B. Artikel Vorderseite"></label><label class="span-5">Beschreibung<input name="precheck_description[]" placeholder="Was muss sichtbar sein?"></label><label class="span-1">Anzahl<input name="precheck_count[]" type="number" min="1" value="1"></label><div class="span-2 remove-row"><button type="button" class="btn ghost" data-remove-row>Entfernen</button></div></div></template>
<template id="tpl-shipping-step"><div class="structured-row" data-repeat-row><label class="span-3">Titel<input name="shipping_step_title[]"></label><label class="span-4">Anweisung<input name="shipping_step_instructions[]"></label><label class="span-2">Art<select name="shipping_step_type[]"><option value="checkbox">Bestätigung</option><option value="photo">Foto</option><option value="text">Text</option><option value="tracking_or_receipt">Tracking oder Beleg</option></select></label><label class="span-1">Pflicht<select name="shipping_step_required[]"><option value="1">Ja</option><option value="0">Nein</option></select></label><div class="span-2 remove-row"><button type="button" class="btn ghost" data-remove-row>Entfernen</button></div></div></template>
<template id="tpl-option"><div class="structured-row" data-repeat-row><label class="span-3">Name<input name="option_name[]"></label><label class="span-4">Beschreibung<input name="option_description[]"></label><label class="span-2">Vergütung (€)<input name="option_price[]" type="number" step="0.01" min="0" value="0"></label><label class="span-3">Anforderung<input name="option_requirements[]"></label><input type="hidden" name="option_sort_order[]" value="0"><div class="span-12"><button type="button" class="btn ghost" data-remove-row>Option entfernen</button></div></div></template>
<template id="tpl-component"><div class="structured-row" data-repeat-row><label class="span-3">Titel<input name="component_title[]"></label><label class="span-3">Kategorie<select name="component_category_id[]"><?php foreach($categories as $cat):?><option value="<?=$cat['id']?>"><?=App\Core\View::e($cat['name'])?></option><?php endforeach;?></select></label><label class="span-2">Typ<select name="component_type[]"><option value="physical">Physisch</option><option value="digital">Digital</option></select></label><label class="span-2">Vergütung (€)<input name="component_compensation[]" type="number" step="0.01" min="0" value="0"></label><label class="span-2">Erfüllung<select name="component_fulfillment_model[]"><option value="days">Tage</option><option value="units">Einheiten</option><option value="once">Einmalig</option><option value="digital">Digital</option></select></label><label class="span-2">Dauer / Anzahl<input name="component_duration_value[]" type="number" min="1"></label><label class="span-2">Einheit<input name="component_duration_unit[]" value="Tage"></label><label class="span-2">Digitalformat<select name="component_format_type[]"><option value="mixed">Gemischt</option><option value="text">Text</option><option value="audio">Audio</option><option value="video">Video</option></select></label><label class="span-2">Min. Textzeichen<input name="component_min_length[]" type="number" min="0" value="0"></label><label class="span-2">Max. Textzeichen<input name="component_max_length[]" type="number" min="0" value="0"></label><label class="span-2">Max. Datei MB<input name="component_max_file_mb[]" type="number" min="1" max="500" value="150"></label><label class="span-2">Digitale Deadline<input name="component_deadline[]" type="datetime-local"></label><input type="hidden" name="component_sort_order[]" value="0"><div class="span-12"><button type="button" class="btn ghost" data-remove-row>Bestandteil entfernen</button></div></div></template>
<template id="tpl-offer-task"><div class="structured-row" data-repeat-row><label class="span-3">Titel<input name="offer_task_title[]"></label><label class="span-3">Vorlage<select name="offer_task_template_id[]"><option value="">Keine Vorlage</option><?php foreach($taskTemplates as $tpl):?><option value="<?=$tpl['id']?>"><?=App\Core\View::e($tpl['title'])?></option><?php endforeach;?></select></label><label class="span-3">Bezug auf Kategorie<select name="offer_task_category_id[]"><option value="">Allgemeiner Auftrag</option><?php foreach($categories as $cat):?><option value="<?=$cat['id']?>"><?=App\Core\View::e($cat['name'])?></option><?php endforeach;?></select></label><label class="span-3">Zeitplan<select name="offer_task_schedule_type[]"><option value="once">Einmalig</option><option value="recurring">Wiederkehrend</option><option value="interval">Intervall</option></select></label><label class="span-6">Beschreibung<input name="offer_task_description[]"></label><label class="span-2">Erstmals nach Min.<input name="offer_task_offset_minutes[]" type="number" min="0" value="60"></label><label class="span-2">Wiederholen alle Min.<input name="offer_task_repeat_every_minutes[]" type="number" min="0" value="0"></label><label class="span-2">Wiederholungen<input name="offer_task_repeat_count[]" type="number" min="0" max="100" value="0"></label><input type="hidden" name="offer_task_sort_order[]" value="0"><div class="span-12"><button type="button" class="btn ghost" data-remove-row>Aufgabe entfernen</button></div></div></template>
</main>