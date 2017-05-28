<?php
/*
 * subtables1.php
 * Demonstrating the use of subtables to update 2 separate rows within the
 * same db table. Note the use of foo1 and foo2 as aliases for the actual
 * column name `foo`. Note the use of 'x' and 'y' as aliases for the actual
 * dbtable `foo`.
 */
$myForm = new Form([
    'foo1' => new FormField_Text([
        'dbtable' => 'x',
        'dbfield' => 'foo',
    ]),
    'foo2' => new FormField_Text([
        'dbtable' => 'y',
        'dbfield' => 'foo',
    ]),
    'save' => new FormField_ButtonSubmit,
], [
    'default' => 'process',
    'subtables' => [
        'x' => [
            'realdbtable' => 'foo',
            'id' => 14,
        ],
        'y' => [
            'realdbtable' => 'foo',
            'id' => 15,
        ],
    ],
    'dbtable' => 'foo',
]);
