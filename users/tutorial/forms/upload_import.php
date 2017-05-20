<?php
/*
 * upload.php
 */

$myForm = new Form([
    'upFile' => new FormField_File([
        'display' => 'Upload a file',
        'maxuploadsize' => 10000000,
        #'debug' => 4,
        'upload_ext' => ['foo','bar','gif','jpg', 'csv'],
        'required' => !isset($_FILES['upFile']),
        'overwrite' => true,
    ]),
    'insList' => new FormField_Table([
        'th_row' => ['Foo', 'A Col', 'B Col'],
        'td_row' => ['{HIDDEN_SEQ}{foo(text-seq)}', '{a(text-seq)}', '{b(text-seq)}'],
        'import_field' => 'upFile',
    ], [
        'action' => 'insert',
        'button' => 'insertButton',
        'fields' => ['foo' => '{foo}', 'a'=>'{a}', 'b'=>'{b}'],
    ]),
    'uploadButton' => new FormField_ButtonSubmit([
        'display' => 'Upload',
    ]),
    'insertButton' => new FormField_ButtonSubmit([
        'display' => 'Insert into Foo'
    ]),
], [
    'dbtable' => 'foo',
    'autoload'=>true,
    'autosave'=>true,
    'autoupload'=>true,
    'autoshow'=>true,
    'autoredirect'=>false,
    #'debug' => 4,
]);
