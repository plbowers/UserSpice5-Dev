<?php
/* Feel free to customize this class */
class BaseForm extends US_BaseForm {
}
class Form extends US_Form {
    public function __construct($fields=[], $opts=[]) {
        global $user, $T;
        $editTutorDocs = (@$user->isAdmin() && configGet('debug_mode') && @$_REQUEST['editing']);
        $formName = basename($_SERVER['PHP_SELF']);
        $fields = [
            'docs' => new FormField_Textarea([
                'display' => lang('TUTORIAL_DOCUMENTATION', $formName),
                'readonly' => !$editTutorDocs,
                'hideable' => true, // allow hiding by clicking on label
                'hidden' => true,   // start hidden
                'autoresize' => true,
                'resizable' => 'both',
                'dbtable' => 'dev_docs',
            ]),
            'savedocs' => new FormField_ButtonSubmit([
                'display' => 'Save Tutorial Documentation',
                'keepif' => $editTutorDocs,
            ]),
            'source_code' => new FormField_Textarea([
                'isdbfield' => false,
                'display' => lang('TUTORIAL_SOURCE_CODE', $formName),
                'value' => highlight_file(pathFinder($formName, '', 'forms_path'), true),
                'readonly' => true,
                'hideable' => true, // allow hiding by clicking on label
                'hidden' => true,   // start hidden
                'autoresize' => true,
            ]),
        ] + $fields;
        $opts['subtables'] = [
                'dev_docs' => [
                    'sql' => "SELECT id FROM $T[dev_docs] WHERE category = ? AND subtopic = ?",
                    'bindvals' => ['tutorial', $formName],
                    'found0' => 'INSERT',
                    'buttons' => [
                        'save' => 'savedocs',
                    ],
                    'fields' => [
                        #'docs' => '{docs}',
                        'category' => 'tutorial',
                        'topic' => 'hmm',
                        'subtopic' => $formName,
                    ],
                ],
            ] + (array)@$opts['subtables'];
        parent::__construct($fields, $opts);
    }
}
