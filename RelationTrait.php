<?php

/**
 * RelationTrait
 *
 * @author Yohanes Candrajaya <moo.tensai@gmail.com>
 * @since 1.0
 */
namespace mootensai\relation;

use \yii\db\ActiveRecord;
use \yii\db\Exception;
use \yii\helpers\Inflector;
use \yii\helpers\StringHelper;

trait RelationTrait{
    
    public function loadRelated($POST) {
        if ($this->load($POST)) {
            $shortName = StringHelper::basename(get_class($this));
            foreach ($POST as $key => $value) {
                if ($key != $shortName && strpos($key, '_') === false) {
                    $isHasMany = is_array($value);
                    $relName = ($isHasMany) ? lcfirst($key) . 's' : lcfirst($key);
                    $rel = $this->getRelation($relName);
                    if ($isHasMany) {
                        $container = [];
                        foreach ($value as $relPost) {
                            /* @var $relObj ActiveRecord */
                            $relObj = new $rel->modelClass;
                            $relObj->load($relPost, '');
                            $container[] = $relObj;
                        }
                        $this->populateRelation($relName, $container);
                    } else {
                        $relObj = new $rel->modelClass;
                        $relObj->load($value);
                        $this->populateRelation($relName, $value);
                    }
                }
            }
            return true;
        } else {
            return false;
        }
    }
    
    public function saveRelated() {
        /* @var $this ActiveRecord */
        $db = $this->getDb();
        $trans = $db->beginTransaction();
        try {
            if ($this->save()) {
                $error = 0;
                foreach ($this->relatedRecords as $name => $records) {
                    $AQ = $this->getRelation($name);
                    $link = $AQ->link;
                    $notDeletedPK = [];
                    $relPKAttr = $records[0]->primaryKey();
                    $isCompositePK = (count($relPKAttr) > 1);
                    /* @var $relModel ActiveRecord */
                    foreach($records as $index => $relModel){
                        foreach ($link as $key => $value){
                            $relModel->$key = $this->$value;
                            $notDeletedFK[$key] = "$key = '{$this->$value}'";
                        }
                        if(!$relModel->save()){
                            $relModelWords = Inflector::camel2words(StringHelper::basename($AQ->modelClass));
                            $index++;
                            foreach ($relModel->errors as $validation){
                                foreach($validation as $errorMsg){
                                    $this->addError($name,"$relModelWords #$index : $errorMsg");
                                }
                            }
                            $error = 1;
                        }else{
                            //GET PK OF REL MODEL
                            if($isCompositePK){
                                foreach($relModel->primaryKey as $attr => $value){
                                    $notDeletedPK[$attr][] = "'$value'";
                                }
                            }  else {
                                $notDeletedPK[] = $relModel->primaryKey;
                            }
                        }
                    }
                    //DELETE WITH 'NOT IN' PK MODEL & REL MODEL
                    $notDeletedFK = implode(' AND ', $notDeletedFK);
                    if($isCompositePK){
                        $compiledNotDeletedPK = [];
                        foreach($notDeletedPK as $attr => $pks){
                            $compiledNotDeletedPK[$attr] = "$attr NOT IN(".implode(', ', $pks).")";
//                            echo "$notDeletedFK AND ".implode(' AND ', $compiledNotDeletedPK);
                            $relModel->deleteAll("$notDeletedFK AND ".implode(' AND ', $compiledNotDeletedPK));
                        }
                    }else{
                        $compiledNotDeletedPK = implode(',', $notDeletedPK);
                        $relModel->deleteAll($notDeletedFK.' AND '.$relPKAttr[0]." NOT IN ($compiledNotDeletedPK)");
                    }
                }
                if ($error) {
                    $trans->rollback();
                    return false;
                }
                $trans->commit();
                return true;
            } else {
                return false;
            }
        } catch (Exception $exc) {
            $trans->rollBack();
            throw $exc;
        }
    }
    
    public function getAttributesWithRelatedAsPost(){
        $return = [];
        $shortName = StringHelper::basename(get_class($this));
        $return[$shortName] = $this->attributes;
        foreach($this->relatedRecords as $records){
            foreach($records as $index => $record){
                $shortNameRel = StringHelper::basename(get_class($record));
                $return[$shortNameRel][$index] = $record->attributes;
            }
        }
        return $return;
    }
    
    public function getAttributesWithRelated(){
        $return = $this->attributes;
        foreach($this->relatedRecords as $name => $records){
            foreach($records as $index => $record){
                $return[$name][$index] = $record->attributes;
            }
        }
        return $return;
    }
}