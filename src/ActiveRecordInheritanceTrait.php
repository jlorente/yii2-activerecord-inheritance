<?php

/**
 * @author      José Lorente <jose.lorente.martin@gmail.com>
 * @license     The MIT License (MIT)
 * @copyright   José Lorente
 * @version     1.0
 */

namespace jlorente\db;

use yii\base\UnknownPropertyException;
use yii\base\UnknownMethodException;
use yii\db\Exception as DbException;
use yii\base\Exception as BaseException;
use Yii;
use Exception;
use yii\db\ActiveRecord;

/**
 * Trait to simulate inheritance between two ActiveRecordInterface classes.
 * Child classes MUST use the trait and MUST implement the 
 * ActiveRecordInheritanceInterface.
 * 
 * @see ActiveRecordInheritanceInterface
 * 
 * * Methods overwriten from ActiveRecord in this trait like save, validate, etc... 
 * should not be overwriten on the class that uses the trait or functionality 
 * will be lost. 
 * * To overwrite this methods properly, you could extend the class that uses 
 * the trait and overwrite there the methods, making sure that all methods call 
 * its parent implementation.
 * 
 * The call hierarchy is the following:
 * * The natural call hierarchy is preserved, so if a method or property exists 
 * on the called class or one of its ancestors, the method or property is called.
 * * If a method or property doesn't exist in the natural call hierarchy. The 
 * properly magic method is called (__get, __set, __isset, __unset, __call) and 
 * the yii2 call hierarchy is used. If the method or property isn't found in 
 * this call hierarchy the next step is executed.
 * * The previous process is repeteated for the faked parent object.
 * * The call hierarchy will stop when a method or property is found or when 
 * there are no more parents. In this case, an UnknownPropertyException or an 
 * UnknownMethodException will be raised.
 * * You can concatenate faked parent classes with the only limit of the php 
 * call stack.
 * 
 * i.e.:
 * ```php
 * namespace my\name\space;
 * 
 * use custom\db\traits\ActiveRecordInheritanceTrait;
 * 
 * class User extends ActiveRecord {
 * }
 * 
 * class Admin extends ActiveRecord {
 *     use ActiveRecordInheritanceTrait;
 * 
 *     public static function extendsFrom() {
 *         return User::className();
 *     }
 * }
 * ```
 * 
 * By default, the primary key of the supposed child class is used as 
 * foreign key of the parent model, if you want to use another attribute as 
 * foreign key, yoy should overwrite the parentAttribute() method.
 * 
 * This trait doesn't work with multiple primary and foreign keys.
 * 
 * @author José Lorente <jose.lorente.martin@gmail.com>
 */
trait ActiveRecordInheritanceTrait {

    /**
     *
     * @var \yii\db\ActiveRecordInterface
     */
    private $_parent;

    /**
     * @return \yii\db\ActiveRecordInterface
     */
    private function _parent() {
        if ($this->_parent === null) {
            if (($this instanceof ActiveRecordInheritanceInterface) === false) {
                throw new BaseException('Classes that use the \jlorente\db\ActiveRecordInheritanceTrait must implement \jlorente\db\ActiveRecordInheritanceInterface');
            }
            $pClass = static::extendsFrom();
            if ($this->getIsNewRecord() === false) {
                $this->_parent = $this->parent;
            } else {
                $this->_parent = new $pClass();
            }
        }
        return $this->_parent;
    }

    /**
     * @inheritdoc
     */
    public function __get($name) {
        try {
            return parent::__get($name);
        } catch (UnknownPropertyException $e) {
            return $this->_parent()->{$name};
        }
    }

    /**
     * @inheritdoc
     */
    public function __set($name, $value) {
        try {
            parent::__set($name, $value);
        } catch (UnknownPropertyException $e) {
            if (method_exists($this, 'get' . $name)) {
                throw $e;
            } else {
                $this->_parent()->{$name} = $value;
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function setAttributes($values, $safeOnly = true) {
        $this->_parent()->setAttributes($values, $safeOnly);
        parent::setAttributes($values, $safeOnly);
    }

    /**
     * @inheritdoc
     */
    public function __isset($name) {
        try {
            if (parent::__get($name) !== null) {
                return true;
            }
        } catch (UnknownPropertyException $e) {
            return $this->_parent()->{$name};
        }

        if (parent::__isset($name) === false) {
            return isset($this->_parent()->{$name});
        } else {
            return true;
        }
    }

    /**
     * @inheritdoc
     */
    public function __unset($name) {
        try {
            if (parent::__get($name) !== null) {
                parent::__unset($name);
            }
        } catch (UnknownPropertyException $e) {
            unset($this->_parent()->{$name});
        }
    }

    /**
     * @inheritdoc
     */
    public function __call($name, $params) {
        try {
            return parent::__call($name, $params);
        } catch (UnknownMethodException $e) {
            return call_user_func_array([$this->_parent(), $name], $params);
        }
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels() {
        return array_merge($this->_parent()->attributeLabels(), parent::attributeLabels());
    }

    /**
     * Returns attribute values.
     * 
     * @param array $names list of attributes whose value needs to be returned.
     * Defaults to null, meaning all attributes listed in [[attributes()]] will be returned.
     * If it is an array, only the attributes in the array will be returned.
     * @param array $except list of attributes whose value should NOT be returned.
     * @return array attribute values (name => value).
     */
    public function getAttributes($names = null, $except = array()) {
        if ($names === null) {
            $names = array_merge($this->_parent()->attributes(), $this->attributes());
        }
        return parent::getAttributes($names, $except);
    }

    /**
     * Saves the parent model and the current model.
     * DO NOT OVERRIDE THIS METHOD ON TRAIT USER CLASS  or functionality of this 
     * trait will be lost.
     * 
     * @return boolean
     * @throws \Exception
     */
    public function save($runValidation = true, $attributeNames = null) {
        if ($runValidation === true && $this->validate($attributeNames) === false) {
            Yii::info('Model not inserted due to validation error.', __METHOD__);
            return false;
        }

        $trans = static::getDb()->beginTransaction();
        try {
            if ($this->_parent()->save(false, $attributeNames) === false) {
                throw new DbException('Unable to save parent model');
            }

            $this->{$this->parentAttribute()} = $this->_parent()->{$this->parentPrimaryKey()};
            if (parent::save(false, $attributeNames) === false) {
                throw new DbException('Unable to save current model');
            }
            $trans->commit();
            return true;
        } catch (Exception $e) {
            $trans->rollback();
            throw $e;
        }
    }

    /**
     * Deletes the table row corresponding to this active record.
     *
     * This method performs the following steps in order:
     *
     * 1. call [[beforeDelete()]]. If the method returns false, it will skip the
     *    rest of the steps;
     * 2. delete the record from the database;
     * 3. call [[afterDelete()]].
     *
     * In the above step 1 and 3, events named [[EVENT_BEFORE_DELETE]] and [[EVENT_AFTER_DELETE]]
     * will be raised by the corresponding methods.
     *
     * @return integer|false the number of rows deleted, or false if the deletion is unsuccessful for some reason.
     * Note that it is possible the number of rows deleted is 0, even though the deletion execution is successful.
     * @throws StaleObjectException if [[optimisticLock|optimistic locking]] is enabled and the data
     * being deleted is outdated.
     * @throws \Exception in case delete failed.
     */
    public function delete() {
        $trans = static::getDb()->beginTransaction();
        try {
            if (parent::delete() === false) {
                throw new DbException('Unable to delete current model');
            }
            $result = $this->_parent()->delete();
            if ($result === false) {
                throw new DbException('Unable to delete parent model');
            }
            $trans->commit();
            return $result;
        } catch (Exception $e) {
            $trans->rollback();
            throw $e;
        }
    }

    /**
     * Validates the parent and the current model.
     * DO NOT OVERRIDE THIS METHOD ON TRAIT USER CLASS  or functionality of this 
     * trait will be lost.
     * 
     * @return boolean
     * @throws Exception
     */
    public function validate($attributeNames = null, $clearErrors = true) {
        $r = parent::validate($attributeNames === null ?
                                array_diff($this->attributes(), [$this->parentAttribute()]) :
                                $attributeNames, $clearErrors);
        return $this->_parent()->validate($attributeNames, $clearErrors) && $r;
    }

    /**
     * Returns a value indicating whether there is any validation error.
     * DO NOT OVERRIDE THIS METHOD ON TRAIT USER CLASS  or functionality of this 
     * trait will be lost.
     * 
     * @param string|null $attribute attribute name. Use null to check all attributes.
     * @return boolean whether there is any error.
     */
    public function hasErrors($attribute = null) {
        return $this->_parent()->hasErrors($attribute) || parent::hasErrors($attribute);
    }

    /**
     * Returns the errors for all attribute or a single attribute.
     * DO NOT OVERRIDE THIS METHOD ON TRAIT USER CLASS  or functionality of this 
     * trait will be lost.
     * 
     * @param string $attribute attribute name. Use null to retrieve errors for all attributes.
     * @property array An array of errors for all attributes. Empty array is returned if no error.
     * The result is a two-dimensional array. See [[getErrors()]] for detailed description.
     * @return array errors for all attributes or the specified attribute. Empty array is returned if no error.
     * Note that when returning errors for all attributes, the result is a two-dimensional array, like the following:
     *
     * ~~~
     * [
     *     'username' => [
     *         'Username is required.',
     *         'Username must contain only word characters.',
     *     ],
     *     'email' => [
     *         'Email address is invalid.',
     *     ]
     * ]
     * ~~~
     *
     * @see getFirstErrors()
     * @see getFirstError()
     */
    public function getErrors($attribute = null) {
        return array_merge($this->_parent()->getErrors($attribute), parent::getErrors($attribute));
    }

    /**
     * Returns the first error of every attribute in the model.
     * DO NOT OVERRIDE THIS METHOD ON TRAIT USER CLASS  or functionality of this 
     * trait will be lost.
     * 
     * @return array the first errors. The array keys are the attribute names, and the array
     * values are the corresponding error messages. An empty array will be returned if there is no error.
     * @see getErrors()
     * @see getFirstError()
     */
    public function getFirstErrors() {
        $errs = $this->getErrors();
        if (empty($errs)) {
            return [];
        } else {
            $errors = [];
            foreach ($errs as $name => $es) {
                if (!empty($es)) {
                    $errors[$name] = reset($es);
                }
            }

            return $errors;
        }
    }

    /**
     * Returns the first error of the specified attribute.
     * DO NOT OVERRIDE THIS METHOD ON TRAIT USER CLASS  or functionality of this 
     * trait will be lost.
     * 
     * @param string $attribute attribute name.
     * @return string the error message. Null is returned if no error.
     * @see getErrors()
     * @see getFirstErrors()
     */
    public function getFirstError($attribute) {
        $errors = $this->getErrors($attribute);
        return count($errors) ? $errors[0] : null;
    }

    /**
     * @inheritdoc
     */
    public function refresh() {
        $r = parent::refresh();
        return $this->_parent()->refresh() && $r;
    }

    /**
     * @inheritdoc
     */
    public function fields() {
        return array_merge($this->_parent()->fields(), parent::fields());
    }

    /**
     * @inheritdoc
     */
    public function extraFields() {
        return array_merge($this->_parent()->extraFields(), parent::extraFields());
    }

    /**
     * Returns the relation with the parent class.
     * 
     * @return \yii\db\ActiveQueryInterface
     */
    public function getParent() {
        $class = static::extendsFrom();
        return $this->hasOne($class::className(), [$this->parentPrimaryKey() => $this->parentAttribute()]);
    }

    /**
     * Returns the name of the attribute that stablishes the relation between 
     * the child class and the parent class.
     * 
     * @return string
     */
    public function parentAttribute() {
        return static::primaryKey()[0];
    }

    /**
     * Returns the name of the parent primary key attribute.
     * 
     * @return string
     */
    public function parentPrimaryKey() {
        $pClass = static::extendsFrom();
        return $pClass::primaryKey()[0];
    }

}
