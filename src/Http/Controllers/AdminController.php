<?php
namespace App\Http\Controllers;
use App\Core\Auth;use App\Core\Request;use App\Core\Response;use App\Core\Session;use App\Core\View;use App\Services\OfferFormService;use PDO;
final class AdminController{
 public function __construct(private string $root,private PDO $db,private Auth $auth){}
 public function dashboard():void{
  $counts=[];
  foreach([
   'prechecks'=>"SELECT COUNT(*) FROM evidences WHERE evidence_type='precheck' AND review_status='pending'",
   'evidence'=>"SELECT COUNT(*) FROM evidences WHERE evidence_type<>'precheck' AND review_status='pending'",
   'violations'=>"SELECT COUNT(*) FROM violations WHERE status IN('open','reviewed')",
   'damage'=>"SELECT COUNT(*) FROM damage_cases WHERE status IN('reported','evidence_requested','under_review')",
   'revisions'=>"SELECT COUNT(*) FROM revision_rounds WHERE status='open'",
   'payouts'=>"SELECT COUNT(*) FROM payout_requests WHERE status IN('requested','in_review','approved')"
  ] as $k=>$sql)$counts[$k]=(int)$this->db->query($sql)->fetchColumn();
  $due=$this->db->query("SELECT event_type,title,starts_at,order_id FROM calendar_events WHERE starts_at<=DATE_ADD(NOW(),INTERVAL 1 DAY) AND status='scheduled' ORDER BY starts_at LIMIT 20")->fetchAll();
  View::render($this->root,'admin/dashboard',['pageTitle'=>'Admin Dashboard','counts'=>$counts,'due'=>$due]);
 }
 public function categories():void{$rows=$this->db->query("SELECT c.*,p.name parent_name,(SELECT COUNT(*) FROM offers o WHERE o.category_id=c.id) offer_count FROM categories c LEFT JOIN categories p ON p.id=c.parent_id ORDER BY c.sort_order,c.name")->fetchAll();View::render($this->root,'admin/categories',['pageTitle'=>'Kategorien','categories'=>$rows]);}
 public function createCategory(Request $r):void{
  $name=trim((string)$r->input('name'));if(!$name){Session::flash('error','Name ist erforderlich.');Response::redirect('/admin/kategorien');}
  $slug=$this->slug((string)($r->input('slug')?:$name));$q=$this->db->prepare('INSERT INTO categories(parent_id,name,slug,icon,is_system_template,is_digital,is_active,sort_order,created_at,updated_at) VALUES(?,?,?,?,0,?,?,?,NOW(),NOW())');
  $q->execute([$r->input('parent_id')?:null,$name,$slug,trim((string)$r->input('icon')),(int)!!$r->input('is_digital'),1,(int)$r->input('sort_order',0)]);
  Session::flash('success','Kategorie wurde angelegt.');Response::redirect('/admin/kategorien');
 }
 public function updateCategory(Request $r,array $p):void{
  $id=(int)$p['id'];$name=trim((string)$r->input('name'));$slug=$this->slug((string)($r->input('slug')?:$name));
  $q=$this->db->prepare('UPDATE categories SET parent_id=?,name=?,slug=?,icon=?,is_digital=?,is_active=?,sort_order=?,updated_at=NOW() WHERE id=?');
  $q->execute([$r->input('parent_id')?:null,$name,$slug,trim((string)$r->input('icon')),(int)!!$r->input('is_digital'),(int)!!$r->input('is_active'),(int)$r->input('sort_order',0),$id]);
  Session::flash('success','Kategorie aktualisiert.');Response::redirect('/admin/kategorien');
 }
 public function duplicateCategory(Request $r,array $p):void{
  $id=(int)$p['id'];$q=$this->db->prepare('SELECT * FROM categories WHERE id=?');$q->execute([$id]);$c=$q->fetch();if(!$c)Response::abort(404);
  $slug=$this->slug($c['slug'].'-kopie-'.substr(bin2hex(random_bytes(3)),0,6));$i=$this->db->prepare('INSERT INTO categories(parent_id,name,slug,icon,is_system_template,is_digital,is_active,sort_order,created_at,updated_at) VALUES(?,?,?,?,0,?,0,?,NOW(),NOW())');$i->execute([$c['parent_id'],$c['name'].' (Kopie)',$slug,$c['icon'],$c['is_digital'],$c['sort_order']]);$new=(int)$this->db->lastInsertId();
  $f=$this->db->prepare('SELECT * FROM category_fields WHERE category_id=? ORDER BY sort_order,id');$f->execute([$id]);foreach($f->fetchAll() as $x){$this->db->prepare('INSERT INTO category_fields(category_id,field_key,label,field_type,options_json,is_required,is_active,sort_order,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,NOW(),NOW())')->execute([$new,$x['field_key'],$x['label'],$x['field_type'],$x['options_json'],$x['is_required'],$x['is_active'],$x['sort_order']]);}
  Session::flash('success','Kategorie wurde dupliziert.');Response::redirect('/admin/kategorien');
 }
 public function deleteCategory(Request $r,array $p):void{$id=(int)$p['id'];$q=$this->db->prepare('SELECT COUNT(*) FROM offers WHERE category_id=?');$q->execute([$id]);if((int)$q->fetchColumn()>0){Session::flash('error','Kategorie kann wegen vorhandener Angebote/Aufträge nicht gelöscht werden.');Response::redirect('/admin/kategorien');}$this->db->prepare('DELETE FROM category_fields WHERE category_id=?')->execute([$id]);$this->db->prepare('DELETE FROM categories WHERE id=? AND is_system_template=0')->execute([$id]);Response::redirect('/admin/kategorien');}
 public function offers():void{$rows=$this->db->query("SELECT o.*,c.name category_name,ov.version_no,ov.compensation,s.first_name seller_first_name,s.last_name seller_last_name,s.email seller_email FROM offers o JOIN categories c ON c.id=o.category_id LEFT JOIN offer_versions ov ON ov.id=o.current_version_id LEFT JOIN sellers s ON s.id=o.seller_id ORDER BY o.updated_at DESC")->fetchAll();View::render($this->root,'admin/offers',['pageTitle'=>'Angebote','offers'=>$rows]);}
 public function offerCreateForm():void{$cats=$this->db->query("SELECT id,name,is_digital FROM categories WHERE is_active=1 ORDER BY sort_order,name")->fetchAll();$tasks=$this->db->query("SELECT id,title FROM task_templates WHERE is_active=1 ORDER BY title")->fetchAll();$sellers=$this->db->query("SELECT id,first_name,last_name,email FROM sellers WHERE deleted_at IS NULL ORDER BY last_name,first_name")->fetchAll();View::render($this->root,'admin/offer-form',['pageTitle'=>'Angebot anlegen','offer'=>null,'version'=>null,'categories'=>$cats,'options'=>[],'components'=>[],'offerTasks'=>[],'taskTemplates'=>$tasks,'sellers'=>$sellers]);}
 public function createOffer(Request $r):void{
  $this->db->beginTransaction();try{
   $title=trim((string)$r->input('title'));$category=(int)$r->input('category_id');$private=(int)!!$r->input('is_private');$seller=$private?(int)$r->input('seller_id'):null;
   $q=$this->db->prepare("INSERT INTO offers(category_id,seller_id,title,status,is_private,acceptance_deadline,private_offer_status,created_at,updated_at) VALUES(?,?,?,?,?,?,?,NOW(),NOW())");$q->execute([$category,$seller,$title,(string)$r->input('status','draft'),$private,$r->input('acceptance_deadline')?:null,$private?'pending':null]);$offerId=(int)$this->db->lastInsertId();
   $v=$this->insertOfferVersion($offerId,1,$r);$this->db->prepare('UPDATE offers SET current_version_id=? WHERE id=?')->execute([$v,$offerId]);$this->db->commit();Session::flash('success','Angebot wurde angelegt.');Response::redirect('/admin/angebote');
  }catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
 }
 public function offerEditForm(Request $r,array $p):void{$id=(int)$p['id'];$q=$this->db->prepare('SELECT * FROM offers WHERE id=?');$q->execute([$id]);$o=$q->fetch();if(!$o)Response::abort(404);$v=$this->db->prepare('SELECT * FROM offer_versions WHERE id=?');$v->execute([$o['current_version_id']]);$version=$v->fetch();$cats=$this->db->query("SELECT id,name,is_digital FROM categories ORDER BY sort_order,name")->fetchAll();$tasks=$this->db->query("SELECT id,title FROM task_templates WHERE is_active=1 ORDER BY title")->fetchAll();$sellers=$this->db->query("SELECT id,first_name,last_name,email FROM sellers WHERE deleted_at IS NULL ORDER BY last_name,first_name")->fetchAll();$op=$this->db->prepare('SELECT name,description,price,requirements_json,sort_order FROM offer_options WHERE offer_version_id=? ORDER BY sort_order,id');$op->execute([$o['current_version_id']]);$co=$this->db->prepare('SELECT category_id,component_type,title,compensation,fulfillment_model,duration_value,duration_unit,config_json,sort_order FROM offer_components WHERE offer_version_id=? ORDER BY sort_order,id');$co->execute([$o['current_version_id']]);$ta=$this->db->prepare('SELECT task_template_id,title,config_json,sort_order FROM offer_tasks WHERE offer_version_id=? ORDER BY sort_order,id');$ta->execute([$o['current_version_id']]);View::render($this->root,'admin/offer-form',['pageTitle'=>'Angebot bearbeiten','offer'=>$o,'version'=>$version,'categories'=>$cats,'options'=>$op->fetchAll(),'components'=>$co->fetchAll(),'offerTasks'=>$ta->fetchAll(),'taskTemplates'=>$tasks,'sellers'=>$sellers]);}
 public function updateOffer(Request $r,array $p):void{
  $id=(int)$p['id'];$this->db->beginTransaction();try{$q=$this->db->prepare('SELECT COALESCE(MAX(version_no),0) FROM offer_versions WHERE offer_id=? FOR UPDATE');$q->execute([$id]);$no=(int)$q->fetchColumn()+1;$v=$this->insertOfferVersion($id,$no,$r);$u=$this->db->prepare("UPDATE offers SET category_id=?,seller_id=?,title=?,status=?,is_private=?,acceptance_deadline=?,private_offer_status=CASE WHEN ?=1 THEN COALESCE(private_offer_status,'pending') ELSE NULL END,current_version_id=?,updated_at=NOW() WHERE id=?");$private=(int)!!$r->input('is_private');$u->execute([(int)$r->input('category_id'),$private?(int)$r->input('seller_id'):null,trim((string)$r->input('title')),(string)$r->input('status'),$private,$r->input('acceptance_deadline')?:null,$private,$v,$id]);$this->db->commit();Session::flash('success','Neue Angebotsversion '.$no.' wurde veröffentlicht/gespeichert.');Response::redirect('/admin/angebote');}catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
 }
 public function reopenPrivateOffer(Request $r,array $p):void{
  $id=(int)$p['id'];
  $q=$this->db->prepare("SELECT * FROM offers WHERE id=? AND is_private=1");$q->execute([$id]);$offer=$q->fetch();
  if(!$offer){Session::flash('error','Privatangebot nicht gefunden.');Response::redirect('/admin/angebote');}
  if(empty($offer['seller_id'])){Session::flash('error','Dem Privatangebot ist keine Verkäuferin zugeordnet.');Response::redirect('/admin/angebote/'.$id.'/bearbeiten');}
  $deadline=trim((string)$r->input('acceptance_deadline'));
  if($deadline===''){Session::flash('error','Bitte eine neue Annahmefrist angeben.');Response::redirect('/admin/angebote/'.$id.'/bearbeiten');}
  try{$d=new \DateTimeImmutable($deadline);if($d<=new \DateTimeImmutable('now'))throw new \RuntimeException('Die neue Annahmefrist muss in der Zukunft liegen.');
   $this->db->prepare("UPDATE offers SET private_offer_status='pending',declined_reason=NULL,acceptance_deadline=?,status='active',updated_at=NOW() WHERE id=?")->execute([$d->format('Y-m-d H:i:s'),$id]);
   $this->db->prepare("INSERT INTO system_events(seller_id,event_type,actor_type,actor_id,payload_json,created_at) VALUES(?,'private_offer_reopened','admin',?,?,NOW())")->execute([$offer['seller_id'],$this->auth->admin()['id'],json_encode(['offer_id'=>$id,'acceptance_deadline'=>$d->format('Y-m-d H:i:s')],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
   Session::flash('success','Privatangebot wurde erneut freigegeben.');
  }catch(\Throwable $e){Session::flash('error',$e->getMessage());}
  Response::redirect('/admin/angebote/'.$id.'/bearbeiten');
 }
 private function insertOfferVersion(int $offerId,int $no,Request $r):int{
  $configuration=OfferFormService::configuration($r);
  $q=$this->db->prepare('INSERT INTO offer_versions(offer_id,version_no,title,description,compensation,fulfillment_model,duration_value,duration_unit,rules_json,evidence_json,start_control_json,shipping_json,end_workflow_json,violation_json,settings_json,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())');
  $q->execute([
   $offerId,$no,trim((string)$r->input('title')),trim((string)$r->input('description')),
   round((float)str_replace(',','.',(string)$r->input('compensation')),2),(string)$r->input('fulfillment_model'),
   $r->input('duration_value')!==''?(int)$r->input('duration_value'):null,$r->input('duration_unit')?:null,
   json_encode($configuration['rules'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
   json_encode($configuration['evidence'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
   json_encode($configuration['start_control'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
   json_encode($configuration['shipping'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
   json_encode($configuration['end_workflow'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
   json_encode($configuration['violation'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
   json_encode($configuration['settings'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)
  ]);
  $versionId=(int)$this->db->lastInsertId();

  foreach(OfferFormService::options($r) as $x){
   $this->db->prepare('INSERT INTO offer_options(offer_version_id,name,description,price,requirements_json,sort_order,is_active,created_at) VALUES(?,?,?,?,?,?,1,NOW())')
    ->execute([$versionId,$x['name'],$x['description']?:null,$x['price'],json_encode($x['requirements'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$x['sort_order']]);
  }

  foreach(OfferFormService::components($r,$configuration) as $x){
   $this->db->prepare('INSERT INTO offer_components(offer_version_id,category_id,component_type,title,compensation,fulfillment_model,duration_value,duration_unit,config_json,sort_order,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,NOW())')
    ->execute([$versionId,$x['category_id'],$x['component_type'],$x['title'],$x['compensation'],$x['fulfillment_model'],$x['duration_value'],$x['duration_unit'],json_encode($x['config'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$x['sort_order']]);
  }

  foreach(OfferFormService::tasks($r) as $x){
   $this->db->prepare('INSERT INTO offer_tasks(offer_version_id,task_template_id,title,config_json,sort_order,created_at) VALUES(?,?,?,?,?,NOW())')
    ->execute([$versionId,$x['task_template_id'],$x['title'],json_encode($x['config'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$x['sort_order']]);
  }

  return $versionId;
 }
 public function duplicateOffer(Request $r,array $p):void{
  $id=(int)$p['id'];$o=$this->db->prepare('SELECT * FROM offers WHERE id=?');$o->execute([$id]);$offer=$o->fetch();if(!$offer)Response::abort(404);$v=$this->db->prepare('SELECT * FROM offer_versions WHERE id=?');$v->execute([$offer['current_version_id']]);$version=$v->fetch();
  $this->db->beginTransaction();try{$this->db->prepare("INSERT INTO offers(category_id,title,status,is_private,created_at,updated_at) VALUES(?,?,'draft',0,NOW(),NOW())")->execute([$offer['category_id'],$offer['title'].' (Kopie)']);$newOffer=(int)$this->db->lastInsertId();$cols=['title','description','compensation','fulfillment_model','duration_value','duration_unit','rules_json','evidence_json','start_control_json','shipping_json','end_workflow_json','violation_json','settings_json'];$vals=[];foreach($cols as $col)$vals[]=$version[$col];$this->db->prepare('INSERT INTO offer_versions(offer_id,version_no,'.implode(',',$cols).',created_at) VALUES(?,1,'.implode(',',array_fill(0,count($cols),'?')).',NOW())')->execute(array_merge([$newOffer],$vals));$newVersion=(int)$this->db->lastInsertId();$this->cloneVersionChildren((int)$offer['current_version_id'],$newVersion);$this->db->prepare('UPDATE offers SET current_version_id=? WHERE id=?')->execute([$newVersion,$newOffer]);$this->db->commit();Session::flash('success','Angebot wurde als Entwurf dupliziert.');}catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}Response::redirect('/admin/angebote/'.$newOffer.'/bearbeiten');
 }
 public function saveOfferTemplate(Request $r,array $p):void{
  $id=(int)$p['id'];$q=$this->db->prepare('SELECT o.*,ov.* FROM offers o JOIN offer_versions ov ON ov.id=o.current_version_id WHERE o.id=?');$q->execute([$id]);$x=$q->fetch();if(!$x)Response::abort(404);$payload=['category_id'=>(int)$x['category_id'],'version'=>$x];foreach(['offer_options','offer_components','offer_tasks'] as $table){$s=$this->db->prepare("SELECT * FROM $table WHERE offer_version_id=? ORDER BY id");$s->execute([$x['current_version_id']]);$payload[$table]=$s->fetchAll();}$this->db->prepare('INSERT INTO offer_templates(title,category_id,template_json,is_active,created_at,updated_at) VALUES(?,?,?,1,NOW(),NOW())')->execute([$x['title'],(int)$x['category_id'],json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);Session::flash('success','Angebot wurde als Vorlage gespeichert.');Response::redirect('/admin/angebote');
 }
 public function offerTemplates():void{
  $rows=$this->db->query("SELECT ot.*,c.name category_name FROM offer_templates ot LEFT JOIN categories c ON c.id=ot.category_id WHERE ot.is_active=1 ORDER BY ot.updated_at DESC,ot.id DESC")->fetchAll();
  View::render($this->root,'admin/offer-templates',['pageTitle'=>'Angebotsvorlagen','templates'=>$rows]);
 }
 public function useOfferTemplate(Request $r,array $p):void{
  $id=(int)$p['id'];$q=$this->db->prepare('SELECT * FROM offer_templates WHERE id=? AND is_active=1');$q->execute([$id]);$tpl=$q->fetch();if(!$tpl)Response::abort(404);
  $payload=json_decode($tpl['template_json']?:'{}',true)?:[];$v=$payload['version']??[];if(!$v)throw new \RuntimeException('Vorlage enthält keine Angebotsversion.');
  $this->db->beginTransaction();try{
   $title=trim((string)($v['title']??$tpl['title']));$category=(int)($payload['category_id']??$tpl['category_id']);
   $this->db->prepare("INSERT INTO offers(category_id,title,status,is_private,created_at,updated_at) VALUES(?,?,'draft',0,NOW(),NOW())")->execute([$category,$title.' (Vorlage)']);$offerId=(int)$this->db->lastInsertId();
   $cols=['title','description','compensation','fulfillment_model','duration_value','duration_unit','rules_json','evidence_json','start_control_json','shipping_json','end_workflow_json','violation_json','settings_json'];$vals=[];foreach($cols as $col)$vals[]=$v[$col]??null;
   $this->db->prepare('INSERT INTO offer_versions(offer_id,version_no,'.implode(',',$cols).',created_at) VALUES(?,1,'.implode(',',array_fill(0,count($cols),'?')).',NOW())')->execute(array_merge([$offerId],$vals));$versionId=(int)$this->db->lastInsertId();
   foreach(($payload['offer_options']??[]) as $x)$this->db->prepare('INSERT INTO offer_options(offer_version_id,name,description,price,requirements_json,sort_order,is_active,created_at) VALUES(?,?,?,?,?,?,?,NOW())')->execute([$versionId,$x['name'],$x['description']??null,(float)($x['price']??0),$x['requirements_json']??'[]',(int)($x['sort_order']??0),(int)($x['is_active']??1)]);
   foreach(($payload['offer_components']??[]) as $x)$this->db->prepare('INSERT INTO offer_components(offer_version_id,category_id,component_type,title,compensation,fulfillment_model,duration_value,duration_unit,config_json,sort_order,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,NOW())')->execute([$versionId,(int)$x['category_id'],$x['component_type'],$x['title'],(float)$x['compensation'],$x['fulfillment_model'],$x['duration_value']!==null?(int)$x['duration_value']:null,$x['duration_unit']??null,$x['config_json']??'[]',(int)($x['sort_order']??0)]);
   foreach(($payload['offer_tasks']??[]) as $x)$this->db->prepare('INSERT INTO offer_tasks(offer_version_id,task_template_id,title,config_json,sort_order,created_at) VALUES(?,?,?,?,?,NOW())')->execute([$versionId,$x['task_template_id']!==null?(int)$x['task_template_id']:null,$x['title'],$x['config_json']??'[]',(int)($x['sort_order']??0)]);
   $this->db->prepare('UPDATE offers SET current_version_id=? WHERE id=?')->execute([$versionId,$offerId]);$this->db->commit();Session::flash('success','Neues Angebots-Entwurf aus Vorlage erstellt.');
  }catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
  Response::redirect('/admin/angebote/'.$offerId.'/bearbeiten');
 }
 private function cloneVersionChildren(int $from,int $to):void{
  $maps=['offer_options'=>['name','description','price','requirements_json','sort_order','is_active'],'offer_components'=>['category_id','component_type','title','compensation','fulfillment_model','duration_value','duration_unit','config_json','sort_order'],'offer_tasks'=>['task_template_id','title','config_json','sort_order']];
  foreach($maps as $table=>$cols){$q=$this->db->prepare("SELECT ".implode(',',$cols)." FROM $table WHERE offer_version_id=? ORDER BY id");$q->execute([$from]);foreach($q->fetchAll() as $row){$sql="INSERT INTO $table(offer_version_id,".implode(',',$cols).",created_at) VALUES(?,".implode(',',array_fill(0,count($cols),'?')).",NOW())";$this->db->prepare($sql)->execute(array_merge([$to],array_values($row)));}}
 }
 private function slug(string $s):string{$s=mb_strtolower(trim($s));$s=strtr($s,['ä'=>'ae','ö'=>'oe','ü'=>'ue','ß'=>'ss']);$s=preg_replace('/[^a-z0-9]+/','-',$s);return trim((string)$s,'-');}
}