<?php
namespace App\Http\Controllers;
use App\Core\Request;use App\Core\Response;use App\Core\Session;use App\Core\View;use PDO;
final class AdminCategoryController{
 public function __construct(private string $root,private PDO $db){}
 public function show(Request $r,array $p):void{
  $q=$this->db->prepare('SELECT * FROM categories WHERE id=?');$q->execute([(int)$p['id']]);$c=$q->fetch();if(!$c)Response::abort(404);
  $f=$this->db->prepare('SELECT * FROM category_fields WHERE category_id=? ORDER BY sort_order,id');$f->execute([$c['id']]);
  View::render($this->root,'admin/category',['pageTitle'=>'Kategorie '.$c['name'],'category'=>$c,'fields'=>$f->fetchAll()]);
 }
 public function config(Request $r,array $p):void{
  $json=trim((string)$r->input('config_json'));$decoded=json_decode($json,true);if(!is_array($decoded)){Session::flash('error','Kategorie-Konfiguration muss gültiges JSON sein.');Response::redirect('/admin/kategorien/'.$p['id']);}
  $this->db->prepare('UPDATE categories SET config_json=?,updated_at=NOW() WHERE id=?')->execute([json_encode($decoded,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),(int)$p['id']]);Session::flash('success','Kategorie-Konfiguration gespeichert.');Response::redirect('/admin/kategorien/'.$p['id']);
 }
 public function addField(Request $r,array $p):void{
  $type=(string)$r->input('field_type');if(!in_array($type,['text','number','select','multiselect','boolean','date'],true))Response::abort(422);
  $key=preg_replace('/[^a-z0-9_]/','_',mb_strtolower(trim((string)$r->input('field_key'))));$label=trim((string)$r->input('label'));if(!$key||!$label){Session::flash('error','Feldschlüssel und Bezeichnung sind erforderlich.');Response::redirect('/admin/kategorien/'.$p['id']);}
  $options=null;if(in_array($type,['select','multiselect'],true)){$parts=array_values(array_filter(array_map('trim',preg_split('/\r?\n/',(string)$r->input('options')))));$options=json_encode($parts,JSON_UNESCAPED_UNICODE);}
  $this->db->prepare('INSERT INTO category_fields(category_id,field_key,label,field_type,options_json,is_required,is_active,sort_order,created_at,updated_at) VALUES(?,?,?,?,?,?,1,?,NOW(),NOW())')->execute([(int)$p['id'],$key,$label,$type,$options,(int)!!$r->input('is_required'),(int)$r->input('sort_order',0)]);Session::flash('success','Kategorie-Feld angelegt.');Response::redirect('/admin/kategorien/'.$p['id']);
 }
 public function updateField(Request $r,array $p):void{
  $type=(string)$r->input('field_type');if(!in_array($type,['text','number','select','multiselect','boolean','date'],true))Response::abort(422);$options=null;if(in_array($type,['select','multiselect'],true)){$parts=array_values(array_filter(array_map('trim',preg_split('/\r?\n/',(string)$r->input('options')))));$options=json_encode($parts,JSON_UNESCAPED_UNICODE);}
  $this->db->prepare('UPDATE category_fields SET label=?,field_type=?,options_json=?,is_required=?,is_active=?,sort_order=?,updated_at=NOW() WHERE id=? AND category_id=?')->execute([trim((string)$r->input('label')),$type,$options,(int)!!$r->input('is_required'),(int)!!$r->input('is_active'),(int)$r->input('sort_order'),(int)$p['fieldId'],(int)$p['id']]);Session::flash('success','Kategorie-Feld aktualisiert.');Response::redirect('/admin/kategorien/'.$p['id']);
 }
 public function deleteField(Request $r,array $p):void{$this->db->prepare('DELETE FROM category_fields WHERE id=? AND category_id=?')->execute([(int)$p['fieldId'],(int)$p['id']]);Session::flash('success','Kategorie-Feld gelöscht.');Response::redirect('/admin/kategorien/'.$p['id']);}
}