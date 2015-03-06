ActiveRecord Inheritance
========================

ActiveRecord Inheritance is a util to provide the [Class Table Inheritance Pattern](http://martinfowler.com/eaaCatalog/classTableInheritance.html) to the Yii2 framework. Its motivation is to fake inheritance between two ActiveRecord classes.

## Installation

Include the package as dependency under the bower.json file.

To install, either run

```bash
$ php composer.phar require jlorente/yii2-activerecord-inheritance "*"
```

or add

```json
...
    "require": {
        ...
        "jlorente/yii2-activerecord-inheritance": "*"
    }
```

to the ```require``` section of your `composer.json` file.

## Usage

An example of usage could be:

Suppose you have the following schema.
```sql
CREATE TABLE `user` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `last_name` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `updated_at` int(11) NOT NULL,
  PRIMARY KEY (`id`)
);

CREATE TABLE `admin` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `level` INT NOT NULL,
  `banned_users` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  CONSTRAINT `FK_Admin_Id` FOREIGN KEY (`id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
);
```

To fake the inheritance between the two tables your code must look like this.

```php
use jlorente\db\ActiveRecordInheritanceTrait,
    jlorente\db\ActiveRecordInheritanceInterface;
use yii\db\ActiveRecord;

class User extends ActiveRecord {

    public function tableName() {
        return 'user';
    }

    public function doSomething() {
        echo 'User does something';
    }
}

class Admin extends ActiveRecord implements ActiveRecordInheritanceInterface {
    use ActiveRecordInheritanceTrait;

    public function tableName() {
        return 'admin';
    }

    public static function extendsFrom() {
        return User::className();
    }

    public function doSomething() {
        echo 'Admin does something';
    }
}
```

And you will be able to use Admin objects as if they were inheriting from User objects.

```php
$admin = new Admin();
$admin->username = 'al-acran';
$admin->email = 'al_acran@gmail.com';
$admin->name = 'Al';
$admin->last_name = 'Acran';
$admin->level = 1;
$admin->save();
```

You can call parent methods and properties by using the parent relation property.
```php
$admin->doSomething()           //Admin does something
$admin->parent->doSomething()   //User does something
```

This trait is very useful for faking inheritance, however query filters should be applied on the parent relation.
```php
$admin = Admin::find()
    ->joinWith('parent', function($query) {
        $query->andWhere(['username' => 'al-acran']);
        $query->andWhere(['name' => 'Al']);
    })
    ->andWhere(['level' => 1])
    ->one();
```

## Considerations
In order to use the trait properly, you must consider the following points

### General
* By default, the primary key of the supposed child class is used as foreign key of the parent model, if you want to use another attribute as foreign key, yoy should overwrite the parentAttribute() method.
* The trait won't work with multiple primary and foreign keys.

### ActiveRecord methods
* Methods overwriten from ActiveRecord in this trait like save, validate, etc... should not be overwriten on the class that uses the trait or functionality will be lost. 
* To overwrite this methods properly, you could extend the class that uses the trait and overwrite there the methods, making sure that all methods call its parent implementation.

### Inheritance call hierarchy
* The natural call hierarchy is preserved, so if a method or property exists on the called class or one of its ancestors, the method or property is called.
* If a method or property doesn't exist in the natural call hierarchy. The properly magic method is called (__get, __set, __isset, __unset, __call) and the yii2 call hierarchy is used. If the method or property isn't found in this call hierarchy the next step is executed.
* The previous process is repeteated for the faked parent object.
* The call hierarchy will stop when a method or property is found or when there are no more parents. In this case, an UnknownPropertyException or an UnknownMethodException will be raised.
* You can concatenate faked parent classes with the only limit of the php call stack.

## License 
Copyright &copy; 2015 José Lorente Martín <jose.lorente.martin@gmail.com>.
Licensed under the MIT license. See LICENSE.txt for details.
