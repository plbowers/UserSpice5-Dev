<?php
/*
 * upload.php
 */

$myForm = new Form([
    'upFile' => new FormField_File([
        'display' => 'Upload a file',
        'maxuploadsize' => 1000000,
        #'debug' => 4,
        'upload_ext' => ['foo','bar','gif','jpg'],
        'required' => true,
        'overwrite' => true,
    ]),
    'up2File' => new FormField_File([
        'display' => 'Upload another file',
        'maxuploadsize' => 270000,
        'overwrite' => true,
        #'debug' => 4,
    ]),
    'uploadButton' => new FormField_ButtonSubmit([
        'display' => 'Upload',
    ]),
    'insertButton' => new FormField_ButtonSubmit([
        'display' => 'Insert',
    ]),
], [
    'dbtable' => 'foo',
    'autoload'=>true,
    #'autosave'=>true,
    'autoupload'=>true,
    'autoshow'=>true,
    'autoredirect'=>false,
    #'debug' => 4,
]);
