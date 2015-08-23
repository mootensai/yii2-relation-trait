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


trait RelationTrait {

    public function loadAll($POST) {
        /* @var $this ActiveRecord */
        if ($this->load($POST)) {
            $shortName = StringHelper::basename(get_class($this));
            foreach ($POST as $key => $value) {
                if ($key != $shortName && strpos($key, '_') === false) {
                    $isHasMany = is_array($value);
                    $relName = ($isHasMany) ? lcfirst($key) . 's' : lcfirst($key);
                    $rel = $this->getRelation($relName);
                    $relModelClass = $rel->modelClass;
                    $relPKAttr = $relModelClass::primaryKey();
                    if ($isHasMany) {
                        $container = [];
                        foreach ($value as $relPost) {
                            $condition = [];
                            foreach ($relPKAttr as $pk) {
                                $condition[$pk] = isset($relPost[$pk]) ? $relPost[$pk] : null;
                            }
                            /* @var $relObj ActiveRecord */
                            $relObj = $relModelClass::findOne($condition);
                            if ($relObj === null) {
                                $relObj = new $rel->modelClass;
                            }
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

    public function saveAll() {
        /* @var $this ActiveRecord */
        $db = $this->getDb();
        $trans = $db->beginTransaction();
        try {
            if ($this->save()) {
                $error = 0;
                foreach ($this->relatedRecords as $name => $records) {
                    $AQ = $this->getRelation($name);
                    $relModelClass = $AQ->modelClass;
                    $relPKAttr = $relModelClass::primaryKey();

                    $link = $AQ->link;
                    $linkKeys = array_keys($link);
                    /* @var $relModel ActiveRecord */
                    $deleteFK = [];
                    $fk = key($link);
                    $pk = current($link);
                    $deleteFK[$fk] = $this->$pk;

                    $notDeletePK = [];
                    foreach ($records as $index => $relModel) {
                        foreach ($link as $key => $value) {
                            $relModel->$key = $this->$value;
                        }
                        foreach ($relPKAttr as $pk) {
                            if (in_array($pk, $linkKeys)) {
                                continue;
                            }
                            $notDeletePK[$pk][] = $relModel->$pk;
                        }
                        $relSave = $relModel->save();
                        if (!$relSave || !empty($relModel->errors)) {
                            $relModelWords = Inflector::camel2words(StringHelper::basename($AQ->modelClass));
                            $index++;
                            foreach ($relModel->errors as $validation) {
                                foreach ($validation as $errorMsg) {
                                    $this->addError($name, "$relModelWords #$index : $errorMsg");
                                }
                            }
                            $error = 1;
                        }
                    }
                    if (!$this->isNewRecord) {
                        //DELETE WITH 'NOT IN' PK MODEL & REL MODEL
                        $condition = $deleteFK;
                        foreach($notDeletePK as $pk => $values) {
                            $condition = ['and', $condition, ['not in', $pk, $values]];
                        }
                        $relModel->deleteAll($condition);
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

    public function deleteWithRelated() {
        /* @var $this ActiveRecord */
        $db = $this->getDb();
        $trans = $db->beginTransaction();
        try {
            $error = 0;
            $relData = $this->getRelationData();
            foreach ($relData as $data) {
                if($data['ismultiple']){
                    $AQ = $this->getRelation($data['name']);
                    $link = $AQ->link;
                    if(count($this->{$data['name']})){
                        $relPKAttr = $this->{$data['name']}[0]->primaryKey();
                        $isCompositePK = (count($relPKAttr) > 1);
                        foreach ($link as $key => $value) {
                            if(isset($this->$value)){
                                $array[$key] = $key . ' = ' . $this->$value;
                            }
                        }
                        $error = !$this->{$data['name']}[0]->deleteAll(implode(' AND ', $array));
                    }
                }
            }
            if ($error) {
                $trans->rollback();
                return false;
            }
            if($this->delete()){
                $trans->commit();
                return true;
            }
            $trans->rollBack();
        } catch (Exception $exc) {
            $trans->rollBack();
            throw $exc;
        }
    }

    public function getRelationData() {
        $ARMethods = get_class_methods('\yii\db\ActiveRecord');
        $modelMethods = get_class_methods('\yii\base\Model');
        $reflection = new \ReflectionClass($this);
        $i = 0;
        $stack = [];
        /* @var $method \ReflectionMethod */
        foreach ($reflection->getMethods() as $method) {
            if (in_array($method->name, $ARMethods) || in_array($method->name, $modelMethods)) {
                continue;
            }
            if($method->name === 'bindModels')  {continue;}
            if($method->name === 'attachBehaviorInternal')  {continue;}
            if($method->name === 'loadAll')  {continue;}
            if($method->name === 'saveAll')  {continue;}
            if($method->name === 'getRelationData')  {continue;}
            if($method->name === 'getAttributesWithRelatedAsPost')  {continue;}
            if($method->name === 'getAttributesWithRelated')  {continue;}
            if($method->name === 'deleteWithRelated')  {continue;}
            try {
                $rel = call_user_func(array($this,$method->name));
                if($rel instanceof \yii\db\ActiveQuery){
                    $stack[$i]['name'] = lcfirst(str_replace('get', '', $method->name));
                    $stack[$i]['method'] = $method->name;
                    $stack[$i]['ismultiple'] = $rel->multiple;
                    $i++;
                }
            } catch (\yii\base\ErrorException $exc) {}
        }
        return $stack;
    }

    /* this function is deprecated */

    public function getAttributesWithRelatedAsPost() {
        $return = [];
        $shortName = StringHelper::basename(get_class($this));
        $return[$shortName] = $this->attributes;
        foreach ($this->relatedRecords as $records) {
            foreach ($records as $index => $record) {
                $shortNameRel = StringHelper::basename(get_class($record));
                $return[$shortNameRel][$index] = $record->attributes;
            }
        }
        return $return;
    }

    public function getAttributesWithRelated() {
        $return = $this->attributes;
        foreach ($this->relatedRecords as $name => $records) {
            foreach ($records as $index => $record) {
                $return[$name][$index] = $record->attributes;
            }
        }
        return $return;
    }

}
