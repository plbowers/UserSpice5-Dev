<?php
/*
 * insert_multi.php
 */

$myForm = new Form([
    new Form_Row([
        new Form_Col([
            '<h4>Select rows below to DELETE from FOO</h4>',
            'delFoo' => new FormField_Table([
                #'debug' => 4,
                'th_row' => ['Mark to Delete', 'Name'],
                'td_row' => ['{CHECKBOX_ID}', '{name}'],
                #'sql_cols' => ['id, foo'],
                #'sql_from' => 'foo',
                #'sql_where' => '1=1',
                'sql' => 'SELECT id, foo FROM foo',
            ], [
                'action' => 'delete',
                'button' => 'deleteMulti',
            ]),
        ], [
            'Col_Class' => 'col-xs-12 col-md-6',
        ]),
        new Form_Col([
            '<h4>Select rows below to INSERT these rows from BAR into FOO</h4>',
            'insFoo' => new FormField_Table([
                #'debug' => 4,
                'th_row' => ['Mark to Add to FOO', 'Name'],
                'td_row' => ['{CHECKBOX_ID}', '{name}'],
                #'sql_cols' => ['id, foo'],
                #'sql_from' => 'foo',
                #'sql_where' => '1=1',
                'sql' => 'SELECT id, bar FROM bar',
            ], [
                'action' => 'insert',
                'button' => 'insertMulti',
                'fields' => ['foo' => '{bar}', 'a'=>'{a}'],
                'msgtoken_success' => 'FOO',
            ]),
            'dataBar' => new FormField_MultiHidden([
                'sql' => 'SELECT id, bar, a FROM bar',
                'hidden_cols' => ['id', 'bar', 'a'],
            ])
        ], [
            'Col_Class' => 'col-xs-12 col-md-6',
        ]),
    ]),
    'deleteMulti' => new FormField_ButtonDelete([
        'display' => 'Delete from Foo'
    ]),
    'insertMulti' => new FormField_ButtonSubmit([
        'display' => 'Insert into Foo'
    ]),
], [
    'dbtable' => 'foo',
    'autoload'=>true,
    'autosave'=>true,
    'autoshow'=>true,
    #'autoredirect'=>false,
    'multirow' => true, // for redirect rules
    #'debug' => 4,
]);
