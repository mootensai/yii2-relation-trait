# yii2-relation-trait
Yii 2 Models add functionality for load with relation (loadAll($POST)), &amp; transactional save with relation (saveAll())

PLUS soft delete/restore feature!

Best work with [mootensai/yii2-enhanced-gii](https://github.com/mootensai/yii2-enhanced-gii)

[![Latest Stable Version](https://poser.pugx.org/mootensai/yii2-relation-trait/v/stable)](https://packagist.org/packages/mootensai/yii2-relation-trait)
[![License](https://poser.pugx.org/mootensai/yii2-relation-trait/license)](https://packagist.org/packages/mootensai/yii2-relation-trait)
[![Total Downloads](https://img.shields.io/packagist/dt/mootensai/yii2-relation-trait.svg?style=flat-square)](https://packagist.org/packages/mootensai/yii2-relation-trait)
[![Monthly Downloads](https://poser.pugx.org/mootensai/yii2-relation-trait/d/monthly)](https://packagist.org/packages/mootensai/yii2-relation-trait)
[![Daily Downloads](https://poser.pugx.org/mootensai/yii2-relation-trait/d/daily)](https://packagist.org/packages/mootensai/yii2-relation-trait)
[![Join the chat at https://gitter.im/mootensai/yii2-relation-trait](https://badges.gitter.im/Join%20Chat.svg)](https://gitter.im/mootensai/yii2-relation-trait?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge&utm_content=badge)

## Support

[![Support via Gratipay](https://cdn.rawgit.com/gratipay/gratipay-badge/2.3.0/dist/gratipay.svg)](https://gratipay.com/mootensai/)

https://www.paypal.me/yohanesc


## Installation

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```bash
$ composer require 'mootensai/yii2-relation-trait:dev-master'
```

or add

```
"mootensai/yii2-relation-trait": "*"
```

to the `require` section of your `composer.json` file.


## Usage At Model

```php
class MyModel extends ActiveRecord{
    use \mootensai\relation\RelationTrait;
}
```

## Array Input & Usage At Controller

It takes a normal array of POST. This is the example

```php
// sample at controller
//$_POST['ParentClass'] = ['attr1' => 'value1','attr2' => 'value2'];
//$_POST['RelatedClass'][0] = ['attr1' => 'value1','attr2' => 'value2'];      
if($model->loadAll(Yii:$app->request->post()) && $model->saveAll()){
    return $this->redirect(['view', 'id' => $model->id, 'created' => $model->created]);
}
```

# Features

## Array Output  

```php
// I use this to send model & related through JSON / Serialize
print_r($model->getAttributesWithRelatedAsPost());
```

```
Array
(
    [MainClass] => Array
        (
            [attr1] => value1
            [attr2] => value2
        )

    [RelatedClass] => Array
        (
            [0] => Array
                (
                    [attr1] => value1
                    [attr2] => value2
                )
        )

)
```

```php
print_r($model->getAttributesWithRelated());
```

```
Array
(
    [attr1] => value1
    [attr2] => value2
    [relationName] => Array
        (
            [0] => Array
                (
                    [attr1] => value1
                    [attr2] => value2
                )
        )
)
```

## Use Transaction

So your data will be atomic
(see : http://en.wikipedia.org/wiki/ACID)

## Use Normal Save

So your behaviors still works

## Add Validation At Main Model

```php
$form->errorSummary($model);
```

will give you

```
<<Related Class Name>> #<<index + 1>> : <<error message>>
My Related Model #1 : Attribute is required
```

## It Works On Auto Incremental PK Or Not (I Have Tried Use UUID)

See here if you want to use my behavior :

https://github.com/mootensai/yii2-uuid-behavior

## Soft Delete

Add this line to your Model to enable soft delete

`private $_rt_softdelete = ['<column>' => <deleted row marker value>];`

Example : 

`private $_rt_softdelete = ['isdeleted' => 1];`

Or :

`private $_rt_softdelete = ['deleted_by' => Yii::app()->user->id]`

And add this line to your Model to enable soft restore

`private $_rt_softrestore = ['<column>' => <undeleted row marker value];` 

example :

`private $_rt_softrestore = ['isdeleted' => 0];`

or :

`private $_rt_softdelete = ['deleted_by' => 0];`

## Should work on Yii's supported DB

As it used all Yii's Active Query or Active Record to execute DB command
    

### I'm open for any improvement
Please create issue if you got a problem or an idea for enhancement

#### ~ SDG ~




