<?php

namespace Modler\Tests;

use Modler\Model;

class OtherModel extends Model
{
    protected array $properties = array(
        'test' => array(
            'description' => 'Test Property'
        )
    );

    public function callMeMaybe($test): void
    {
        $this->setValue('test', 'foobarbaz - ' . $test);
    }

    public function callMeReturnValue($test): string
    {
        return 'this is a value: ' . $test;
    }
}
