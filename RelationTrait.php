<?php

/**
 * RelationTrait
 *
 * @author Yohanes Candrajaya <moo.tensai@gmail.com>
 * @since 1.0
 */

namespace mootensai\relation;

use Yii;
use yii\db\ActiveQuery;
use \yii\db\ActiveRecord;
use \yii\db\Exception;
use yii\db\IntegrityException;
use \yii\helpers\Inflector;
use \yii\helpers\StringHelper;
use yii\helpers\ArrayHelper;

/*
 *  add this line to your Model to enable soft delete
 *
 * private $_rt_softdelete;
 *
 * function __construct(){
 *      $this->_rt_softdelete = [
 *          '<column>' => <undeleted row marker value>
 *          // multiple row marker column example
 *          'isdeleted' => 1,
 *          'deleted_by' => \Yii::$app->user->id,
 *          'deleted_at' => date('Y-m-d H:i:s')
 *      ];
 * }
 * add this line to your Model to enable soft restore
 * private $_rt_softrestore;
 *
 * function __construct(){
 *      $this->_rt_softrestore = [
 *          '<column>' => <undeleted row marker value>
 *          // multiple row marker column example
 *          'isdeleted' => 0,
 *          'deleted_by' => 0,
 *          'deleted_at' => 'NULL'
 *      ];
 * }
 */

trait RelationTrait
{

    /**
     * Load all attribute including related attribute
     * @param $POST
     * @param array $skippedRelations
     * @return bool
     */
    public function loadAll($POST, $skippedRelations = [])
    {
        if ($this->load($POST)) {
            $shortName = StringHelper::basename(get_class($this));
            $relData = $this->getRelationData();
            foreach ($POST as $model => $attr) {
                if (is_array($attr)) {
                    if ($model == $shortName) {
                        foreach ($attr as $relName => $relAttr) {
                            if (is_array($relAttr)) {
                                $isHasMany = !ArrayHelper::isAssociative($relAttr);
                                if (in_array($relName, $skippedRelations) || !array_key_exists($relName, $relData)) {
                                    continue;
                                }

                                $this->loadToRelation($isHasMany, $relName, $relAttr);
                            }
                        }
                    } else {
                        $isHasMany = is_array($attr) && is_array(current($attr));
                        $relName = ($isHasMany) ? lcfirst(Inflector::pluralize($model)) : lcfirst($model);
                        if (in_array($relName, $skippedRelations) || !array_key_exists($relName, $relData)) {
                            continue;
                        }

                        $this->loadToRelation($isHasMany, $relName, $attr);
                    }
                }
            }
            return true;
        }
        return false;
    }

    /**
     * Refactored from loadAll() function
     * @param $isHasMany
     * @param $relName
     * @param $v
     * @return bool
     */
    private function loadToRelation($isHasMany, $relName, $v)
    {
        /* @var $AQ ActiveQuery */
        /* @var $this ActiveRecord */
        /* @var $relObj ActiveRecord */
        $AQ = $this->getRelation($relName);
        /* @var $relModelClass ActiveRecord */
        $relModelClass = $AQ->modelClass;
        $relPKAttr = $relModelClass::primaryKey();
        $isManyMany = count($relPKAttr) > 1;

        if ($isManyMany) {
            $container = [];
            foreach ($v as $relPost) {
                if (array_filter($relPost)) {
                    $condition = [];
                    $condition[$relPKAttr[0]] = $this->primaryKey;
                    foreach ($relPost as $relAttr => $relAttrVal) {
                        if (in_array($relAttr, $relPKAttr)) {
                            $condition[$relAttr] = $relAttrVal;
                        }
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
            foreach ($v as $relPost) {
                if (array_filter($relPost)) {
                    /* @var $relObj ActiveRecord */
                    $relObj = (empty($relPost[$relPKAttr[0]])) ? new $relModelClass() : $relModelClass::findOne($relPost[$relPKAttr[0]]);
                    if (is_null($relObj)) {
                        $relObj = new $relModelClass();
                    }
                    $relObj->load($relPost, '');
                    $container[] = $relObj;
                }
            }
            $this->populateRelation($relName, $container);
        } else {
            $relObj = (empty($v[$relPKAttr[0]])) ? new $relModelClass : $relModelClass::findOne($v[$relPKAttr[0]]);
            $relObj->load($v, '');
            $this->populateRelation($relName, $relObj);
        }
        return true;
    }

    /**
     * Save model including all related model already loaded
     * @param array $skippedRelations
     * @return bool
     * @throws Exception
     */
    public function saveAll($skippedRelations = [])
    {
        /* @var $this ActiveRecord */
        $db = $this->getDb();
        $trans = $db->beginTransaction();
        $isNewRecord = $this->isNewRecord;
        $isSoftDelete = isset($this->_rt_softdelete);
        try {
            if ($this->save()) {
                $error = false;
                if (!empty($this->relatedRecords)) {
                    /* @var $records ActiveRecord | ActiveRecord[] */
                    foreach ($this->relatedRecords as $name => $records) {
                        if (in_array($name, $skippedRelations))
                            continue;

                        $AQ = $this->getRelation($name);
                        $link = $AQ->link;
                        if (!empty($records)) {
                            $notDeletedPK = [];
                            $notDeletedFK = [];
                            $relPKAttr = ($AQ->multiple) ? $records[0]->primaryKey() : $records->primaryKey();
                            $isManyMany = (count($relPKAttr) > 1);
                            if ($AQ->multiple) {
                                /* @var $relModel ActiveRecord */
                                foreach ($records as $index => $relModel) {
                                    foreach ($link as $key => $value) {
                                        $relModel->$key = $this->$value;
                                        $notDeletedFK[$key] = $this->$value;
                                    }

                                    //GET PK OF REL MODEL
                                    if ($isManyMany) {
                                        $mainPK = array_keys($link)[0];
                                        foreach ($relModel->primaryKey as $attr => $value) {
                                            if ($attr != $mainPK) {
                                                $notDeletedPK[$attr][] = $value;
                                            }
                                        }
                                    } else {
                                        $notDeletedPK[] = $relModel->primaryKey;
                                    }

                                }

                                if (!$isNewRecord) {
                                    //DELETE WITH 'NOT IN' PK MODEL & REL MODEL
                                    if ($isManyMany) {
                                        // Many Many
                                        $query = ['and', $notDeletedFK];
                                        foreach ($notDeletedPK as $attr => $value) {
                                            $notIn = ['not in', $attr, $value];
                                            array_push($query, $notIn);
                                        }
                                        try {
                                            if ($isSoftDelete) {
                                                $relModel->updateAll($this->_rt_softdelete, $query);
                                            } else {
                                                $relModel->deleteAll($query);
                                            }
                                        } catch (IntegrityException $exc) {
                                            $this->addError($name, "Data can't be deleted because it's still used by another data.");
                                            $error = true;
                                        }
                                    } else {
                                        // Has Many
                                        $query = ['and', $notDeletedFK, ['not in', $relPKAttr[0], $notDeletedPK]];
                                        if (!empty($notDeletedPK)) {
                                            try {
                                                if ($isSoftDelete) {
                                                    $relModel->updateAll($this->_rt_softdelete, $query);
                                                } else {
                                                    $relModel->deleteAll($query);
                                                }
                                            } catch (IntegrityException $exc) {
                                                $this->addError($name, "Data can't be deleted because it's still used by another data.");
                                                $error = true;
                                            }
                                        }
                                    }
                                }

                                foreach ($records as $index => $relModel) {
                                    $relSave = $relModel->save();

                                    if (!$relSave || !empty($relModel->errors)) {
                                        $relModelWords = Yii::t('app', Inflector::camel2words(StringHelper::basename($AQ->modelClass)));
                                        $index++;
                                        foreach ($relModel->errors as $validation) {
                                            foreach ($validation as $errorMsg) {
                                                $this->addError($name, "$relModelWords #$index : $errorMsg");
                                            }
                                        }
                                        $error = true;
                                    }
                                }
                            } else {
                                //Has One
                                foreach ($link as $key => $value) {
                                    $records->$key = $this->$value;
                                }
                                $relSave = $records->save();
                                if (!$relSave || !empty($records->errors)) {
                                    $recordsWords = Yii::t('app', Inflector::camel2words(StringHelper::basename($AQ->modelClass)));
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
                    /* @var $relModel ActiveRecord */
                    if (empty($relData[$relName]['via']) && !in_array($relName, $skippedRelations)) {
                        $relModel = new $relData[$relName]['modelClass'];
                        $condition = [];
                        $isManyMany = count($relModel->primaryKey()) > 1;
                        if ($isManyMany) {
                            foreach ($relData[$relName]['link'] as $k => $v) {
                                $condition[$k] = $this->$v;
                            }
                            try {
                                if ($isSoftDelete) {
                                    $relModel->updateAll($this->_rt_softdelete, ['and', $condition]);
                                } else {
                                    $relModel->deleteAll(['and', $condition]);
                                }
                            } catch (IntegrityException $exc) {
                                $this->addError($relData[$relName]['name'], Yii::t('mtrelt', "Data can't be deleted because it's still used by another data."));
                                $error = true;
                            }
                        } else {
                            if ($relData[$relName]['ismultiple']) {
                                foreach ($relData[$relName]['link'] as $k => $v) {
                                    $condition[$k] = $this->$v;
                                }
                                try {
                                    if ($isSoftDelete) {
                                        $relModel->updateAll($this->_rt_softdelete, ['and', $condition]);
                                    } else {
                                        $relModel->deleteAll(['and', $condition]);
                                    }
                                } catch (IntegrityException $exc) {
                                    $this->addError($relData[$relName]['name'], Yii::t('mtrelt', "Data can't be deleted because it's still used by another data."));
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


    /**
     * Deleted model row with all related records
     * @param array $skippedRelations
     * @return bool
     * @throws Exception
     */
    public function deleteWithRelated($skippedRelations = [])
    {
        /* @var $this ActiveRecord */
        $db = $this->getDb();
        $trans = $db->beginTransaction();
        $isSoftDelete = isset($this->_rt_softdelete);
        try {
            $error = false;
            $relData = $this->getRelationData();
            foreach ($relData as $data) {
                $array = [];
                if ($data['ismultiple'] && !in_array($data['name'], $skippedRelations)) {
                    $link = $data['link'];
                    if (count($this->{$data['name']})) {
                        foreach ($link as $key => $value) {
                            if (isset($this->$value)) {
                                $array[$key] = $this->$value;
                            }
                        }
                        if ($isSoftDelete) {
                            $error = !$this->{$data['name']}[0]->updateAll($this->_rt_softdelete, ['and', $array]);
                        } else {
                            $error = !$this->{$data['name']}[0]->deleteAll(['and', $array]);
                        }
                    }
                }
            }
            if ($error) {
                $trans->rollback();
                return false;
            }
            if ($isSoftDelete) {
                $this->attributes = array_merge($this->attributes, $this->_rt_softdelete);
                if ($this->save(false)) {
                    $trans->commit();
                    return true;
                } else {
                    $trans->rollBack();
                }
            } else {
                if ($this->delete()) {
                    $trans->commit();
                    return true;
                } else {
                    $trans->rollBack();
                }
            }
        } catch (Exception $exc) {
            $trans->rollBack();
            throw $exc;
        }
    }

    /**
     * Restore soft deleted row including all related records
     * @param array $skippedRelations
     * @return bool
     * @throws Exception
     */
    public function restoreWithRelated($skippedRelations = [])
    {
        if (!isset($this->_rt_softrestore)) {
            return false;
        }

        /* @var $this ActiveRecord */
        $db = $this->getDb();
        $trans = $db->beginTransaction();
        try {
            $error = false;
            $relData = $this->getRelationData();
            foreach ($relData as $data) {
                $array = [];
                if ($data['ismultiple'] && !in_array($data['name'], $skippedRelations)) {
                    $link = $data['link'];
                    if (count($this->{$data['name']})) {
                        foreach ($link as $key => $value) {
                            if (isset($this->$value)) {
                                $array[$key] = $this->$value;
                            }
                        }
                        $error = !$this->{$data['name']}[0]->updateAll($this->_rt_softrestore, ['and', $array]);
                    }
                }
            }
            if ($error) {
                $trans->rollback();
                return false;
            }
            $this->attributes = array_merge($this->attributes, $this->_rt_softrestore);
            if ($this->save(false)) {
                $trans->commit();
                return true;
            } else {
                $trans->rollBack();
            }
        } catch (Exception $exc) {
            $trans->rollBack();
            throw $exc;
        }
    }

    public function getRelationData()
    {
        $stack = [];
        if (method_exists($this, 'relationNames')) {
            foreach ($this->relationNames() as $name) {
                /* @var $rel ActiveQuery */
                $rel = $this->getRelation($name);
                $stack[$name]['name'] = $name;
                $stack[$name]['method'] = 'get' . ucfirst($name);
                $stack[$name]['ismultiple'] = $rel->multiple;
                $stack[$name]['modelClass'] = $rel->modelClass;
                $stack[$name]['link'] = $rel->link;
                $stack[$name]['via'] = $rel->via;
            }
        } else {
            $ARMethods = get_class_methods('\yii\db\ActiveRecord');
            $modelMethods = get_class_methods('\yii\base\Model');
            $reflection = new \ReflectionClass($this);
            /* @var $method \ReflectionMethod */
            foreach ($reflection->getMethods() as $method) {
                if (in_array($method->name, $ARMethods) || in_array($method->name, $modelMethods)) {
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
                if (strpos($method->name, 'get') !== 0) {
                    continue;
                }
                if($method->getNumberOfParameters() > 0) {
                    continue;
                }
                try {
                    $rel = call_user_func(array($this, $method->name));
                    if ($rel instanceof ActiveQuery) {
                        $name = lcfirst(preg_replace('/^get/', '', $method->name));
                        $stack[$name]['name'] = lcfirst(preg_replace('/^get/', '', $method->name));
                        $stack[$name]['method'] = $method->name;
                        $stack[$name]['ismultiple'] = $rel->multiple;
                        $stack[$name]['modelClass'] = $rel->modelClass;
                        $stack[$name]['link'] = $rel->link;
                        $stack[$name]['via'] = $rel->via;
                    }
                } catch (\Exception $exc) {
                    //if method name can't be called,
                }
            }
        }
        return $stack;
    }

    /**
     * This function is deprecated!
     * Return array like this
     * Array
     * (
     *      [MainClass] => Array
     *          (
     *              [attr1] => value1
     *              [attr2] => value2
     *          )
     *
     *      [RelatedClass] => Array
     *          (
     *              [0] => Array
     *                  (
     *                      [attr1] => value1
     *                      [attr2] => value2
     *                  )
     *          )
     * )
     * @return array
     */
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

    /**
     * return array like this
     * Array
     * (
     *      [attr1] => value1
     *      [attr2] => value2
     *      [relationName] => Array
     *          (
     *              [0] => Array
     *                  (
     *                      [attr1] => value1
     *                      [attr2] => value2
     *                  )
     *          )
     *  )
     * @return array
     */
    public function getAttributesWithRelated()
    {
        /* @var $this ActiveRecord */
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

    /**
     * TranslationTrait manages methods for all translations used in Krajee extensions
     *
     * @author Kartik Visweswaran <kartikv2@gmail.com>
     * @since 1.8.8
     * Yii i18n messages configuration for generating translations
     * source : https://github.com/kartik-v/yii2-krajee-base/blob/master/TranslationTrait.php
     * Edited by : Yohanes Candrajaya <moo.tensai@gmail.com>
     *
     *
     * @return void
     */
    public function initI18N()
    {
        $reflector = new \ReflectionClass(get_class($this));
        $dir = dirname($reflector->getFileName());

        Yii::setAlias("@mtrelt", $dir);
        $config = [
            'class' => 'yii\i18n\PhpMessageSource',
            'basePath' => "@mtrelt/messages",
            'forceTranslation' => true
        ];
        $globalConfig = ArrayHelper::getValue(Yii::$app->i18n->translations, "mtrelt*", []);
        if (!empty($globalConfig)) {
            $config = array_merge($config, is_array($globalConfig) ? $globalConfig : (array)$globalConfig);
        }
        Yii::$app->i18n->translations["mtrelt*"] = $config;
    }
}
