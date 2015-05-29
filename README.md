# yii2-relation-trait
Yii 2 Models add functionality for load with relation, &amp; transactional save with relation

## Installation

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```bash
$ composer require mootensai/yii2-relation-trait
```

or add

```
"mootensai/yii2-relation-trait": "dev-master"
```

to the `require` section of your `composer.json` file.


##usage at model
```php
class MyModel extends ActiveRecord{
    use mootensai\relation\RelationTrait;
}
```

## Array Input & Usage at Controller
It takes a normal array of POST. This is the example

```php
// sample at controller
//$_POST['ParentClass'] = ['attr1' => 'value1','attr2' => 'value2'];
//$_POST['RelatedClass'][0] = ['attr1' => 'value1','attr2' => 'value2'];      
if($model->loadRelated(Yii:$app->request->post()) && $model->saveRelated()){
    return $this->redirect(['view', 'id' => $model->id, 'created' => $model->created]);
}
```

#Features

## array output  
```php
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

## Using transaction, so your data will be atomic
(see : http://en.wikipedia.org/wiki/ACID)

## Use normal save, so your behavior still works

## Validation
```php
$form->errorSummary($model);
```
will give you
```
<<Related Class Name>> #<<index + 1>> : <<error message>>
My Related Model #1 : Attribute is required
```
- it works on auto incremental PK or not (I have tried use UUID)

I'm open for any improvement
