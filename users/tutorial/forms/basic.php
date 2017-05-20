<?php
$myForm = new Form([
    'foo' => new FormField_Text,
    'a' => new FormField_Text,
    'x' => new FormField_Select([
        'data' => [
            ['id' => '1', 'abc' => 'hello'],
            ['id' => '2', 'def' => 'good-bye'],
        ]
    ]),
    'b' => new FormField_Text,
    'bool' => new FormField_Checkbox,
    'save' => new FormField_ButtonSubmit,
], [
    'autoshow' => true,
]);
