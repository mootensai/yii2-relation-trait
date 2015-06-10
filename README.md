# yii2-relation-trait
Yii 2 Models add functionality for load with relation (loadAll($POST)), &amp; transactional save with relation (saveAll())

## Installation

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```bash
$ composer require mootensai/yii2-relation-trait
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

#Features

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

## Using Transaction
Your data will be atomic
(see : http://en.wikipedia.org/wiki/ACID)

## Use Normal Save
so your behavior still works

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

#To Do
Test it on another DB. I only test it on MySQL.

I'm open for any improvement
