<?php
$statusLabels=['draft'=>'Entwurf','active'=>'Aktiv','disabled'=>'Deaktiviert'];
$privateLabels=['pending'=>'Offen','accepted'=>'Angenommen','declined'=>'Abgelehnt','expired'=>'Abgelaufen'];
?>
<main class="section">
<div class="admin-page-intro"><div><span class="eyebrow">Angebotsverwaltung</span><h1>Ankaufangebote</h1><p>Öffentliche Angebote, Privatangebote und ihre aktuelle Version zentral verwalten.</p></div><div class="actions"><a class="btn" href="/admin/angebotsvorlagen">Vorlagen</a><a class="btn primary" href="/admin/angebote/neu">Neues Angebot</a></div></div>

<div class="table-wrap"><table><thead><tr><th>Titel</th><th>Kategorie</th><th>Vergütung</th><th>Status</th><th>Privatangebot</th><th>Version</th><th></th></tr></thead><tbody>
<?php foreach($offers as $o):?><tr>
<td><strong><?=App\Core\View::e($o['title'])?></strong><?php if($o['is_private']):?><small>Privatangebot</small><?php else:?><small>Öffentliches Angebot</small><?php endif;?></td>
<td><?=App\Core\View::e($o['category_name'])?></td>
<td><strong><?=number_format((float)$o['compensation'],2,',','.')?> €</strong></td>
<td><strong><?=App\Core\View::e($statusLabels[$o['status']]??$o['status'])?></strong><?php if($o['acceptance_deadline']):?><small>Annahme bis <?=date('d.m.Y H:i',strtotime($o['acceptance_deadline']))?></small><?php endif;?></td>
<td><?php if($o['is_private']):?><strong><?=App\Core\View::e($privateLabels[$o['private_offer_status']]??$o['private_offer_status'])?></strong><small><?=App\Core\View::e(trim(($o['seller_first_name']??'').' '.($o['seller_last_name']??'')))?> · <?=App\Core\View::e($o['seller_email']??'')?></small><?php if($o['declined_reason']):?><small>Grund: <?=App\Core\View::e($o['declined_reason'])?></small><?php endif;?><?php else:?><span class="muted">—</span><?php endif;?></td>
<td>v<?= (int)$o['version_no'] ?></td>
<td><a class="btn" href="/admin/angebote/<?=$o['id']?>/bearbeiten">Bearbeiten</a></td>
</tr><?php endforeach;?>
</tbody></table><?php if(!$offers):?><div class="empty">Es wurden noch keine Angebote angelegt.</div><?php endif;?></div>
</main>