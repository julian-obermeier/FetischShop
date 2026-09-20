<main class="section">
<div class="seller-hero"><div><span class="eyebrow">Ankauf</span><h1>Verfügbare Angebote</h1><p>Wähle ein Angebot aus und prüfe Vergütung, Dauer, Nachweise und Zusatzoptionen vor der Annahme.</p></div></div>

<form class="card filters" method="get">
<input name="q" value="<?=App\Core\View::e($_GET['q']??'')?>" placeholder="Angebote durchsuchen">
<select name="category"><option value="">Alle Kategorien</option><?php foreach($categories as $c):?><option value="<?=$c['id']?>" <?=((string)($_GET['category']??'')===(string)$c['id'])?'selected':''?>><?=App\Core\View::e($c['name'])?></option><?php endforeach;?></select>
<input name="min" type="number" step="0.01" placeholder="Min. €" value="<?=App\Core\View::e($_GET['min']??'')?>">
<input name="max" type="number" step="0.01" placeholder="Max. €" value="<?=App\Core\View::e($_GET['max']??'')?>">
<button class="btn primary">Filtern</button>
</form>

<div class="offer-grid">
<?php foreach($offers as $o):?><a class="offer-card" href="/angebote/<?=$o['id']?>">
<div class="row-actions"><span class="pill"><?=App\Core\View::e($o['category_name'])?></span><?php if($o['duration_value']):?><span class="seller-status"><?= (int)$o['duration_value'] ?> <?=App\Core\View::e($o['duration_unit']??'')?></span><?php endif;?></div>
<h3><?=App\Core\View::e($o['title'])?></h3>
<p><?=App\Core\View::e(mb_strimwidth(strip_tags($o['description']),0,150,'…'))?></p>
<div class="row-actions"><div class="price"><?=number_format((float)$o['compensation'],2,',','.')?> €</div><span>Details ansehen →</span></div>
</a><?php endforeach;?>
<?php if(!$offers):?><div class="empty">Keine passenden aktiven Angebote gefunden.</div><?php endif;?>
</div>
</main>