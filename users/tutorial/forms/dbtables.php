<?php
$userId = Input::get('id');
$myForm = new Form([
    'users.username' => new FormField_Text([
        'valid' => [
            'action' => 'update',
            'update_id' => $userId,
        ],
    ]),
    'users.fname' => new FormField_Text,
    'bio' => new FormField_Textarea([ 'dbtable' => 'profiles', ]),
    'users.lname' => new FormField_Text,
    'footblpane' => new FormTab_Pane([
        'foo' => new FormField_Text([
            'dbtable' => 'foo'
        ]),
        'bar' => new FormField_Text,
    ], [ 'dbtable' => 'bar', ]),
    'save' => new FormField_ButtonSubmit,
    'delete' => new FormField_ButtonDelete,
    'delete_bar' => new FormField_ButtonDelete,
    'delete_bar_and_foo' => new FormField_ButtonDelete,
], [
    'dbtable' => 'users',
    'default' => 'process',
    'subtables' => [
        'profiles' => [
            'sql' => "SELECT id FROM $T[profiles] profiles WHERE user_id = ?",
            'bindvals' => [ $userId ],
            'found0' => 'INSERT',
            'fields' => [ 'user_id' => $userId ],
        ],
        'foo' => [
            #'mode' => 'INSERT',
            'sql' => "SELECT id FROM foo",
            'found2' => 'first',
            'buttons' => [
                'delete' => 'delete_bar_and_foo',
            ]
        ],
        'bar' => [
            #'mode' => 'INSERT',
            'buttons' => [
                'delete' => ['delete_bar', 'delete_bar_and_foo'],
                'save' => ['save_bar'],
            ],
            'sql' => "SELECT id FROM bar",
            'found2' => 'first',
        ],
    ]
]);
