# yii2-relation-trait
Yii 2 Models add functionality for load with relation, &amp; transactional save with relation

It takes a normal array of POST. This is the example

```php
$POST['ParentClass'] = [''attr1' => 'value1','attr2' => 'value2'];
$POST['RelatedClass'][0] = ['attr1' => 'value1','attr2' => 'value2'];        
```

This can be used by model.

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
