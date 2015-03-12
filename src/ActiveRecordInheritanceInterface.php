<?php

/**
 * @author      José Lorente <jose.lorente.martin@gmail.com>
 * @license     The MIT License (MIT)
 * @copyright   José Lorente
 * @version     1.0
 */

namespace jlorente\db;

/**
 * The interface MUST be used in classes that use the 
 * ActiveRecordInheritanceTrait for polymorphism considerations.
 * 
 * @author José Lorente <jose.lorente.martin@gmail.com>
 */
interface ActiveRecordInheritanceInterface {
    
    /**
     * Returns the fully qualified parent class name.
     * 
     * @return string
     */
    public static function extendsFrom();
}