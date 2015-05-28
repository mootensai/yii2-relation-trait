# yii2-relation-trait
Yii 2 Models add functionality for load with relation, &amp; transactional save with relation

It takes a normal array of POST. This is the example

```php
// sample at controller
//$_POST['ParentClass'] = [''attr1' => 'value1','attr2' => 'value2'];
//$_POST['RelatedClass'][0] = ['attr1' => 'value1','attr2' => 'value2'];      
if($model->loadRelated(Yii:$app->request->post()) && $model->saveRelated()){
    return $this->redirect(['view', 'id' => $model->id, 'created' => $model->created]);
}
```

usage at model
```php
class MyModel extends ActiveRecord{
    use mootensai\relation\RelationTrait;
}
```

output 
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
