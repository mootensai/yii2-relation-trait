<?php

/**
 * RelationTrait
 *
 * @author Yohanes Candrajaya <moo.tensai@gmail.com>
 * @since 1.0
 */

namespace mootensai\relation;

use yii\db\ActiveQuery;
use \yii\db\ActiveRecord;
use \yii\db\Exception;
use \yii\helpers\Inflector;
use \yii\helpers\StringHelper;

trait RelationTrait
{
    public function loadAll($POST, $skippedRelations = [])
    {
        if ($this->load($POST)) {
            $shortName = StringHelper::basename(get_class($this));
            $relData = $this->getRelationData();
            foreach ($POST as $key => $value) {
                if ($key != $shortName && strpos($key, '_') === false) {
                    /* @var $AQ ActiveQuery */
                    /* @var $this ActiveRecord */
                    /* @var $relObj ActiveRecord */
                    $isHasMany = is_array($value) && is_array(current($value));
                    $relName = ($isHasMany) ? lcfirst(Inflector::pluralize($key)) : lcfirst($key);
                    
                    if (in_array($relName, $skippedRelations) || !array_key_exists($relName,$relData)){
                        continue;
                    }
                    
                    $AQ = $this->getRelation($relName);
                    $relModelClass = $AQ->modelClass;
                    $relPKAttr = $relModelClass::primaryKey();
                    $isManyMany = count($relPKAttr) > 1;

                    if ($isManyMany) {
                        $container = [];
                        foreach ($value as $relPost) {
                            if (array_filter($relPost)) {
                                $condition = [];
                                $condition[$relPKAttr[0]] = $this->primaryKey;
                                foreach ($relPost as $relAttr => $relAttrVal) {
                                    if (in_array($relAttr, $relPKAttr))
                                        $condition[$relAttr] = $relAttrVal;
                                }
                                $relObj = $relModelClass::findOne($condition);
                                if (is_null($relObj)) {
                                    $relObj = new $relModelClass;
                                }
                                $relObj->load($relPost, '');
                                $container[] = $relObj;
                            }
                        }
                        $this->populateRelation($relName, $container);
                    } else if ($isHasMany) {
                        $container = [];
                        foreach ($value as $relPost) {
                            if (array_filter($relPost)) {
                                /* @var $relObj ActiveRecord */
                                $relObj = (empty($relPost[$relPKAttr[0]])) ? new $relModelClass : $relModelClass::findOne($relPost[$relPKAttr[0]]);
                                $relObj->load($relPost, '');
                                $container[] = $relObj;
                            }
                        }
                        $this->populateRelation($relName, $container);
                    } else {
                        $relObj = (empty($value[$relPKAttr[0]])) ? new $relModelClass : $relModelClass::findOne($value[$relPKAttr[0]]);
                        $relObj->load($value, '');
                        $this->populateRelation($relName, $relObj);
                    }
                }
            }
            return true;
        } else {
            return false;
        }
    }

    public function saveAll($skippedRelations = [])
    {
        /* @var $this ActiveRecord */
        $db = $this->getDb();
        $trans = $db->beginTransaction();
        $isNewRecord = $this->isNewRecord;
        try {
            if ($this->save()) {
                $error = false;
                if (!empty($this->relatedRecords)) {
                    foreach ($this->relatedRecords as $name => $records) {

                        if (in_array($name, $skippedRelations))
                            continue;

                        if (!empty($records)) {
                            $AQ = $this->getRelation($name);
                            $link = $AQ->link;
                            $notDeletedPK = [];
                            $relPKAttr = ($AQ->multiple) ? $records[0]->primaryKey() : $records->primaryKey();
                            $isManyMany = (count($relPKAttr) > 1);
                            if ($AQ->multiple) {
                                /* @var $relModel ActiveRecord */
                                $i = 0;
                                foreach ($records as $index => $relModel) {
                                    $notDeletedFK = [];
                                    foreach ($link as $key => $value) {
                                        $relModel->$key = $this->$value;
                                        if ($isManyMany) $notDeletedFK[$key] = $this->$value;
                                        elseif ($AQ->multiple) $notDeletedFK[$key] = "$key = '{$this->$value}'";
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
                                        $error = true;
                                    } else {
                                        //GET PK OF REL MODEL
                                        if ($isManyMany) {
                                            foreach ($relModel->primaryKey as $attr => $value) {
                                                $notDeletedPK[$i][$attr] = "'$value'";
                                                $fields[$attr] = "";
                                            }
                                        } else {
                                            $notDeletedPK[] = "'$relModel->primaryKey'";
                                        }
                                    }
                                    $i++;
                                }
                                if (!$isNewRecord) {
                                    //DELETE WITH 'NOT IN' PK MODEL & REL MODEL
                                    if ($isManyMany) {
                                        $compiledFields = implode(", ", array_keys($fields));
                                        $compiledNotDeletedPK = ['and', $notDeletedFK];
                                        $notIn = ['not in', new \yii\db\Expression("($compiledFields)")];
                                        foreach ($notDeletedPK as $value) {
                                            $v = [];
                                            foreach ($fields as $key => $f) {
                                                $v[] = $value[$key];
                                            }
                                            $c = implode(',', $v);
                                            $content[] = new \yii\db\Expression("($c)");
                                        }
                                        array_push($notIn, $content);
                                        array_push($compiledNotDeletedPK, $notIn);
                                        $relModel->deleteAll($compiledNotDeletedPK);
                                        try {
                                            $relModel->deleteAll($compiledNotDeletedPK);
                                        } catch (\yii\db\IntegrityException $exc) {
                                            $this->addError($name, \Yii::t('mtrelt', "Data can't be deleted because it's still used by another data."));
                                            $error = true;
                                        }
                                    } else {
                                        $notDeletedFK = implode(' AND ', $notDeletedFK);
                                        $compiledNotDeletedPK = implode(',', $notDeletedPK);
                                        if (!empty($compiledNotDeletedPK)) {
                                            try {
                                                $relModel->deleteAll($notDeletedFK . ' AND ' . $relPKAttr[0] . " NOT IN ($compiledNotDeletedPK)");
                                            } catch (\yii\db\IntegrityException $exc) {
                                                $this->addError($name, \Yii::t('mtrelt', "Data can't be deleted because it's still used by another data."));
                                                $error = true;
                                            }
                                        }
                                    }
                                }
                            } else {
                                //Has One
                                foreach ($link as $key => $value) {
                                    $records->$key = $this->$value;
                                }
                                $relSave = $records->save();
                                if (!$relSave || !empty($records->errors)) {
                                    $recordsWords = Inflector::camel2words(StringHelper::basename($AQ->modelClass));
                                    foreach ($records->errors as $validation) {
                                        foreach ($validation as $errorMsg) {
                                            $this->addError($name, "$recordsWords : $errorMsg");
                                        }
                                    }
                                    $error = true;
                                }
                            }
                        }
                    }
                }
                //No Children left
                $relAvail = array_keys($this->relatedRecords);
                $relData = $this->getRelationData();
                $allRel = array_keys($relData);
                $noChildren = array_diff($allRel, $relAvail);

                foreach ($noChildren as $relName) {
                    if (in_array($relName, $skippedRelations)) {
                        continue;
                    }
                    /* @var $relModel ActiveRecord */
                    if (empty($relData[$relName]['via'])) {
                        $relModel = new $relData[$relName]['modelClass'];
                        $condition = [];
                        $isManyMany = count($relModel->primaryKey()) > 1;
                        if ($isManyMany) {
                            foreach ($relData[$relName]['link'] as $k => $v) {
                                $condition[] = "$k = '{$this->$v}'";
                            }
                            try {
                                $relModel->deleteAll(implode(" AND ", $condition));
                            } catch (\yii\db\IntegrityException $exc) {
                                $this->addError($relData[$relName]['name'], \Yii::t('mtrelt', "Data can't be deleted because it's still used by another data."));
                                $error = true;
                            }
                        } else {
                            if ($relData[$relName]['ismultiple']) {
                                foreach ($relData[$relName]['link'] as $k => $v) {
                                    $condition[] = "$k = '{$this->$v}'";
                                }
                                try {
                                    $relModel->deleteAll(implode(" AND ", $condition));
                                } catch (\yii\db\IntegrityException $exc) {
                                    $this->addError($relData[$relName]['name'], \Yii::t('mtrelt', "Data can't be deleted because it's still used by another data."));
                                    $error = true;
                                }
                            }
                        }
                    }
                }

                if ($error) {
                    $trans->rollback();
                    $this->isNewRecord = $isNewRecord;
                    return false;
                }
                $trans->commit();
                return true;
            } else {
                return false;
            }
        } catch (Exception $exc) {
            $trans->rollBack();
            $this->isNewRecord = $isNewRecord;
            throw $exc;
        }
    }

    public function deleteWithRelated()
    {
        /* @var $this ActiveRecord */
        $db = $this->getDb();
        $trans = $db->beginTransaction();
        try {
            $error = false;
            $relData = $this->getRelationData();
            foreach ($relData as $data) {
                if ($data['ismultiple']) {
                    $link = $data['link'];
                    if (count($this->{$data['name']})) {
                        $relPKAttr = $this->{$data['name']}[0]->primaryKey();
//                        $isCompositePK = (count($relPKAttr) > 1); // unused
                        foreach ($link as $key => $value) {
                            if (isset($this->$value)) {
                                $array[$key] = "$key = '{$this->$value}'";
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
            if ($this->delete()) {
                $trans->commit();
                return true;
            }
            $trans->rollBack();
        } catch (Exception $exc) {
            $trans->rollBack();
            throw $exc;
        }
    }


    public function getRelationData()
    {
        $ARMethods = get_class_methods('\yii\db\ActiveRecord');
        $modelMethods = get_class_methods('\yii\base\Model');
        $reflection = new \ReflectionClass($this);
        $stack = [];
        /* @var $method \ReflectionMethod */
        foreach ($reflection->getMethods() as $method) {
            if (in_array($method->name, $ARMethods) || in_array($method->name, $modelMethods)) {
                continue;
            }
            if ($method->name === 'bindModels') {
                continue;
            }
            if ($method->name === 'attachBehaviorInternal') {
                continue;
            }
            if ($method->name === 'loadAll') {
                continue;
            }
            if ($method->name === 'saveAll') {
                continue;
            }
            if ($method->name === 'getRelationData') {
                continue;
            }
            if ($method->name === 'getAttributesWithRelatedAsPost') {
                continue;
            }
            if ($method->name === 'getAttributesWithRelated') {
                continue;
            }
            if ($method->name === 'deleteWithRelated') {
                continue;
            }
            if (strpos($method->name, 'get') === false) {
                continue;
            }
            try {
                $rel = call_user_func(array($this, $method->name));
                if ($rel instanceof \yii\db\ActiveQuery) {
                    $name = lcfirst(str_replace('get', '', $method->name));
                    $stack[$name]['name'] = lcfirst(str_replace('get', '', $method->name));
                    $stack[$name]['method'] = $method->name;
                    $stack[$name]['ismultiple'] = $rel->multiple;
                    $stack[$name]['modelClass'] = $rel->modelClass;
                    $stack[$name]['link'] = $rel->link;
                    $stack[$name]['via'] = $rel->via;
                }
            } catch (\yii\base\ErrorException $exc) {
                //if method name can't be called,
            }
        }
        return $stack;
    }

    /* this function is deprecated */

    public function getAttributesWithRelatedAsPost()
    {
        $return = [];
        $shortName = StringHelper::basename(get_class($this));
        $return[$shortName] = $this->attributes;
        foreach ($this->relatedRecords as $name => $records) {
            $AQ = $this->getRelation($name);
            if ($AQ->multiple) {
                foreach ($records as $index => $record) {
                    $return[$name][$index] = $record->attributes;
                }
            } else {
                $return[$name] = $records->attributes;
            }

        }
        return $return;
    }

    public function getAttributesWithRelated()
    {
        $return = $this->attributes;
        foreach ($this->relatedRecords as $name => $records) {
            $AQ = $this->getRelation($name);
            if ($AQ->multiple) {
                foreach ($records as $index => $record) {
                    $return[$name][$index] = $record->attributes;
                }
            } else {
                $return[$name] = $records->attributes;
            }
        }
        return $return;
    }
}
