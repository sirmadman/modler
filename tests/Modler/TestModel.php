<?php

namespace Modler;

use Modler\Model;

class TestModel extends Model
{
    protected array $properties = array(
        'id' => array(
            'description' => 'ID',
            'type' => 'integer'
        ),
        'test' => array(
            'description' => 'Test Property'
        ),
        'imRequired' => array(
            'description' => 'Required Property #1',
            'required' => true
        ),
        'relateToMe' => array(
            'type' => 'relation',
            'relation' => array(
                'model' => OtherModel::class,
                'method' => 'callMeMaybe',
                'local' => 'test'
            )
        ),
        'relateToMeValue' => array(
            'type' => 'relation',
            'relation' => array(
                'model' => OtherModel::class,
                'method' => 'callMeReturnValue',
                'local' => 'test',
                'return' => 'value'
            )
        ),
        'badModel' => array(
            'type' => 'relation',
            'relation' => array(
                'model' => '\\Foo\\Model',
                'method' => 'badMethod',
                'local' => 'badProperty'
            )
        ),
        'badMethod' => array(
            'type' => 'relation',
            'relation' => array(
                'model' => OtherModel::class,
                'method' => 'badMethod',
                'local' => 'badProperty'
            )
        ),
        'testValidate' => array(
            'type' => 'string',
            'description' => 'Checking for validation method'
        ),
        'guarded' => array(
            'type' => 'string',
            'guarded' => true
        )
    );

    public function validateTestvalidate($value): bool
    {
        return ($value === 'test1234');
    }
}
