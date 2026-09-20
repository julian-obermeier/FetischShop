<?php
namespace App\Services;

use PDO;
use RuntimeException;

final class CategoryFieldService
{
    public function __construct(private PDO $db) {}

    public function fieldsForCategory(int $categoryId): array
    {
        $q=$this->db->prepare("SELECT * FROM category_fields WHERE category_id=? AND is_active=1 ORDER BY sort_order,id");
        $q->execute([$categoryId]);
        $rows=$q->fetchAll();
        foreach($rows as &$row){
            $options=json_decode($row['options_json']?:'[]',true);
            $row['options']=is_array($options)?array_values($options):[];
        }
        unset($row);
        return $rows;
    }

    public function groupedForCategories(array $categoryIds): array
    {
        $ids=array_values(array_unique(array_filter(array_map('intval',$categoryIds),fn($id)=>$id>0)));
        if(!$ids)return [];
        $marks=implode(',',array_fill(0,count($ids),'?'));
        $q=$this->db->prepare("SELECT * FROM category_fields WHERE is_active=1 AND category_id IN($marks) ORDER BY category_id,sort_order,id");
        $q->execute($ids);
        $grouped=[];
        foreach($q->fetchAll() as $row){
            $options=json_decode($row['options_json']?:'[]',true);
            $row['options']=is_array($options)?array_values($options):[];
            $grouped[(int)$row['category_id']][]=$row;
        }
        return $grouped;
    }

    public function normalize(int $categoryId,mixed $input): array
    {
        $values=is_array($input)?$input:[];
        $out=[];

        foreach($this->fieldsForCategory($categoryId) as $field){
            $key=(string)$field['field_key'];
            $label=(string)$field['label'];
            $type=(string)$field['field_type'];
            $value=$values[$key]??null;
            $missing=$value===null||$value===''||(is_array($value)&&count($value)===0);

            if($missing){
                if((int)$field['is_required']===1){
                    throw new RuntimeException('Bitte das Pflichtfeld „'.$label.'“ ausfüllen.');
                }
                continue;
            }

            if($type==='multiselect'){
                if(!is_array($value))$value=[$value];
                $value=array_values(array_unique(array_filter(array_map(fn($v)=>trim((string)$v),$value),fn($v)=>$v!=='')));
                $allowed=$field['options'];
                foreach($value as $selected){
                    if($allowed && !in_array($selected,$allowed,true)){
                        throw new RuntimeException('Ungültige Auswahl bei „'.$label.'“.');
                    }
                }
                $out[$key]=$value;
                continue;
            }

            $value=trim((string)$value);

            if($type==='number'){
                if(!is_numeric(str_replace(',','.',$value)))throw new RuntimeException('„'.$label.'“ muss eine Zahl sein.');
                $out[$key]=(float)str_replace(',','.',$value);
            }elseif($type==='select'){
                if($field['options']&&!in_array($value,$field['options'],true))throw new RuntimeException('Ungültige Auswahl bei „'.$label.'“.');
                $out[$key]=$value;
            }elseif($type==='boolean'){
                if(!in_array($value,['0','1'],true))throw new RuntimeException('Ungültige Ja/Nein-Auswahl bei „'.$label.'“.');
                $out[$key]=$value==='1';
            }elseif($type==='date'){
                $date=\DateTimeImmutable::createFromFormat('!Y-m-d',$value);
                if(!$date||$date->format('Y-m-d')!==$value)throw new RuntimeException('„'.$label.'“ enthält kein gültiges Datum.');
                $out[$key]=$value;
            }else{
                $out[$key]=$value;
            }
        }

        return $out;
    }
}
