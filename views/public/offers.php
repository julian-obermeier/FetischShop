<main class="section">
<div class="public-page-intro">
  <div><span class="eyebrow">Ankaufangebote</span><h1>Finde den Auftrag, der zu dir passt.</h1><p>Filtere nach Kategorie und Vergütung. Alle Details, Nachweise und Zusatzoptionen siehst du vor der Annahme.</p></div>
</div>

<form class="card filters public-offer-filters" method="get">
  <input name="q" value="<?=App\Core\View::e($_GET['q']??'')?>" placeholder="Nach Titel oder Kategorie suchen">
  <select name="category"><option value="">Alle Kategorien</option><?php foreach($categories as $c):?><option value="<?=$c['id']?>" <?=((string)($_GET['category']??'')===(string)$c['id'])?'selected':''?>><?=App\Core\View::e($c['name'])?></option><?php endforeach;?></select>
  <input name="min" type="number" step="0.01" placeholder="Vergütung ab €" value="<?=App\Core\View::e($_GET['min']??'')?>">
  <input name="max" type="number" step="0.01" placeholder="Vergütung bis €" value="<?=App\Core\View::e($_GET['max']??'')?>">
  <button class="btn primary">Angebote filtern</button>
</form>

<div class="section-head compact"><div><h2>Verfügbare Angebote</h2><p class="muted"><?=count($offers)?> Angebot<?=count($offers)===1?'':'e'?> gefunden.</p></div></div>

<div class="offer-grid">
<?php foreach($offers as $o):?>
<a class="offer-card" href="/angebote/<?=$o['id']?>">
  <div class="row-actions">
    <span class="pill"><?=App\Core\View::e($o['category_name'])?></span>
    <?php if($o['duration_value']):?><span class="seller-status"><?= (int)$o['duration_value'] ?> <?=App\Core\View::e($o['duration_unit']??'')?></span><?php endif;?>
  </div>
  <h3><?=App\Core\View::e($o['title'])?></h3>
  <p><?=App\Core\View::e(mb_strimwidth(strip_tags($o['description']),0,150,'…'))?></p>
  <div class="offer-card-bottom"><div class="price"><?=number_format((float)$o['compensation'],2,',','.')?> €</div><span>Details öffnen →</span></div>
</a>
<?php endforeach;?>
<?php if(!$offers):?><div class="empty">Für diese Filter gibt es aktuell keine passenden Angebote.</div><?php endif;?>
</div>
</main>