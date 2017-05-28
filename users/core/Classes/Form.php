<?php
/*
UserSpice
An Open Source PHP User Management System
by the UserSpice Team at http://UserSpice.com

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

class US_BaseForm extends Element {
    const UPDATE_ERROR = 0;
    const UPDATE_SUCCESS = 1;
    const UPDATE_NO_CHANGE = 2;
	protected $_formName,
        $_fields=[],
        $_initFields=[],
        $_initOpts=[],
        $_validateObject=null,
        $_validatePassed=false,
        $_validationFunc=null,
        $_validationFuncArgs=null,
        $_saveFunc=null,
        $_saveFuncArgs=null,
        $_defaultFields = [],
        $_keepAdminDashBoard=false, // true for admin pages
        $_hasFieldValues=false, // flag that setFieldValues() was called
        $_subDbTables=[], // This is for sub-tables
        $_allDbTables=[], // This is for sub-tables
        $_dbTableId=null, // which row we load and update in the primary table
        $_formMode=null, // normally "UPDATE" if isset($_GET['id']) and 'INSERT' otherwise, but can be set manually
        $_headerSnippets=[], // used for CSS styles, etc. (anything you need to put in the header)
        $_footerSnippets=[], // used for JS scripts, etc. (anything you need to put in the footer)
        $_dbAutoLoad=false, // whether we autoload from $_dbTable and $_dbTableId
        $_autoShow=false, // whether we echo $this->getHTML() at the end of __construct()
        $_autoSaveUploads=false, // whether we save uploads at the end of __construct()
        $_autoToC='toc', // whether we fix Table of Contents for tabs and what is name of toc field
        $_activeTab=false, // which tab will be active
        $_autoRedirect=true, // whether we automatically redirect according to page/settings
        $_redirectAfterSave=null, // set while saving, based on tables "pages" and "settings"
        $_dbAutoLoadPosted=null, // source (usually $_POST) from which we autoload new values
        $_dbAutoSave=false, // whether we autosave from $_dbTable and $_dbTableId
        $_idsToDelete=null, // which IDs to delete if autosave is active and data is posted
        $_excludeFields=['id'], // with default=all which fields do not show
        $_isMultiRow=false, // default to being a form editing a single row (used esp with post-save actions & redirects)
        $_processors=[],
        $_saveStatus=self::UPDATE_NO_CHANGE,
        # These lang(x) tokens are used in autosave
        $_msgStrings = [],
        $_msgTokens = [
            'single' => [
                'insert' => [
                    'success' => 'RECORD_INSERT_SUCCESS',
                    'fail' => 'RECORD_INSERT_FAILED',
                    'nothing' => 'RECORD_NOTHING_TO_INSERT',
                ],
                'update' => [
                    'success' => 'RECORD_UPDATE_SUCCESS',
                    'fail' => 'RECORD_UPDATE_FAILED',
                    'nothing' => 'RECORD_NOTHING_TO_UPDATE',
                ],
                'delete' => [
                    'success' => 'RECORD_DELETE_SUCCESS',
                    'fail' => 'RECORD_DELETE_FAILED',
                    'nothing' => 'RECORD_NOTHING_TO_DELETE',
                ],
            ],
            'multi' => [
                'insert' => [
                    'success' => 'RECORD_MULTI_INSERT_SUCCESS',
                    'fail' => 'RECORD_MULTI_INSERT_FAILED',
                    'nothing' => 'RECORD_NOTHING_TO_INSERT',
                ],
                'update' => [
                    'success' => 'RECORD_MULTI_UPDATE_SUCCESS',
                    'fail' => 'RECORD_MULTI_UPDATE_FAILED',
                    'nothing' => 'RECORD_NOTHING_TO_UPDATE',
                ],
                'delete' => [
                    'success' => 'RECORD_MULTI_DELETE_SUCCESS',
                    'fail' => 'RECORD_MULTI_DELETE_FAILED',
                    'nothing' => 'RECORD_NOTHING_TO_DELETE',
                ],
            ],
        ],
        $_deleteSingleRowButton=['delete', 'delete_button'],
        $_deleteMultiRowsButton=['delete', 'delete_button'],
        $_deleteKey='id', // default to delete the current ID
        $_saveSingleRowButton=['save', 'save_button'],
        $_saveMultiRowsButton=['save', 'save_button'],
        $_actionButton=null; // which button was pressed to process the form
    # These are the elements (each corresponding to $HTML_*) which
    # will be output in getHTML().
    public $elementList = [
        'Header', 'openContainer', 'AdminDashboard',
        'TitleAndResults', 'openForm', 'CSRF', 'openRow', 'openCol',
        'Fields',
        'closeCol', 'closeRow', 'closeForm',
        'PageFooter', 'Footer',
    ];
    public $repElement = 'Fields',
        $multiOpts=[];
    protected $HTML_openContainer = '
        <div class="container {CONTAINER_CLASS}">'."\n";
    protected $HTML_openRow = '
        <div class="row {ROW_CLASS}">'."\n";
    protected $HTML_openCol = '
        <div class="{COL_CLASS}">'."\n";
    protected $HTML_openForm = '
        <form name="{FORM_NAME}" action="{FORM_ACTION}" method="{FORM_METHOD}" {FORM_ENCTYPE}>'."\n";
    protected $HTML_CSRF = '
        <input type="hidden" name="csrf" value="{GENERATE_CSRF_TOKEN}">'."\n";
    protected $HTML_closeForm = '
        </form>'."\n";
    protected $HTML_closeContainer = '
        </div> <!-- container {CONTAINER_CLASS} -->'."\n";
    protected $HTML_closeRow = '
        </div> <!-- row {ROW_CLASS} -->'."\n";
    protected $HTML_closeCol = '
        </div> <!-- col {COL_CLASS} -->'."\n";
    protected $HTML_Title = '
        <div class="{COL_CLASS}"> <!-- title col -->
            <h2>{FORM_TITLE}</h2>
        </div> <!-- title col -->
        ';
    protected $HTML_Well_Title = '
        <div class="{COL_CLASS}"> <!-- well title col -->
            <h2>{FORM_TITLE}</h2>
        </div> <!-- well title col -->
        ';
    protected $HTML_TitleAndResults = '
        <div class="{COL_CLASS}"> <!-- title & results col -->
            <h2>{FORM_TITLE}</h2>
    		{RESULT_BLOCK}
        </div> <!-- title & result col -->
        ';
    protected $HTML_AdminDashboard = '
        <div class="xs-col-12"> <!-- admin dashboard col -->
            {INCLUDE_ADMIN_DASHBOARD}
        </div> <!-- admin dashboard col -->
        ';
    # others will be added - these are just the static, known ones
    public $MACRO_Container_Class = '',
        $MACRO_Row_Class = '',
        $MACRO_Col_Class = 'xs-col-12',
        $MACRO_Form_Name = 'usForm',
        $MACRO_Form_Method = 'post',
        $MACRO_Form_Action = '',
        $MACRO_Browser_Title = '',
        $MACRO_Form_Title = null;

	public function __construct($fields=[], $opts=[]) {
        parent::__construct($opts, false); // no handleOpts() now
        $this->_initFields = $fields;
        $this->_initOpts = $opts;
        if (@$this->_initOpts['topform'] || in_array('Header', $this->elementList)) {
            $junk = 'no index - top level';
            $this->initElement($junk, $this, $this);
            /*
            $this->dbgFields();
            $x = $this->getField('site_name');
            if (is_null($x)) { dbg("x was null"); } else { dbg("x was not null"); }
            die();
            */
            $this->getAllProcessors();
            $this->prepDbTables();
            $this->doFormProcessing();
            # If using FormTab_Contents and FormTab_Pane then set up default tabs
            $this->defaultTabs();
            if ($this->getAutoShow()) {
                echo $this->getHTML();
            }
        }
    }
    public function initElement(&$myOwnIdx, $parentForm, $topForm) {
        parent::initElement($k, $parentForm, $topForm);
        $this->applyDefaultOpt(); // handle $opts['default']
        $this->_initOpts[$this->repElement] = $this->expandFieldList($this->_initFields, $this->_initOpts);
        if (basename($_SERVER['PHP_SELF']) == 'default.php') {
            $this->setFormAction($GLOBALS['formName']);
        }
        // $formName is usually set prior to master_form.php
        if (!$this->_formName && @$GLOBALS['formName']) {
            $this->_formName = $GLOBALS['formName'];
        }
        if (is_null($this->getMacro('Form_Title'))) {
            $this->setTitleByPage();
        }
        if (!$this->getKeepAdminDashBoard()) {
            $this->deleteElements('AdminDashboard');
        }
        $this->handleOpts($this->_initOpts);
        # Recalculate field list, deleting via delete_if/keep_if
        # and renaming indices according to aliases if needed
        $newFields = [];
        foreach ($this->repData as $k=>$fld) {
            if (is_object($fld)) {
                #dbg("initElement($k, ...) (idx=$myOwnIdx)");
                $fld->initElement($k, $this, $topForm); // $k is by reference and may be renamed
                #dbg("initElement(): k=$k");
                if (!$fld->getDeleteMe()) {
                    $newFields[$k] = $fld;
                    foreach ($fld->getElementRefs() as $fldName=>&$fldRef) {
                        $fldRef = $topForm->getField($fldName);
                    }
                }
            } else {
                $newFields[$k] = $fld;
            }
        }
        #pre_r($newFields);
        $this->repData = $newFields;
    }

    public function applyDefaultOpt() {
        if (!isset($this->_initOpts['default'])) {
            return;
        }
        if (in_array(@$this->_initOpts['default'], ['all', 'fields', 'processing', 'autoprocess', 'formprocess', 'process'])) {
            if (isset($this->_initOpts['dbtable'])) {
                $table = $this->_initOpts['dbtable'];
            } elseif (isset($this->_initOpts['table'])) {
                $table = $this->_initOpts['table'];
            } else {
                dbg("FATAL ERROR: Must set dbtable for these defaults");
                exit;
            }
            $this->setDBTable($table);
        }
        if (in_array(@$this->_initOpts['default'], ['all', 'processing', 'autoprocess', 'formprocess', 'process'])) {
            # tableId will be defaulted in dbLoadData()
            $this->setDbAutoLoad(true);
            $this->setDbAutoLoadPosted($_POST);
            $this->setDbAutoSave(true);
            $this->setAutoShow(true);
            $this->setAutoRedirect(true);
            $this->setAutoSaveUploads(true);
        }
        if (in_array(@$this->_initOpts['default'], ['all', 'fields'])) {
            $this->_initFields['fields'] = ['all'];
        }
        if (in_array(@$this->_initOpts['default'], ['all', 'buttons'])) {
            $this->_initFields['buttons'] = ['all'];
        }
        unset($this->_initOpts['default']);
    }

    /*
     * If no $fields['fields'] nor $fields['buttons'] then simply
     * returns $fields
     */
    public function expandFieldList($fields, $opts) {
        if (!@$opts['autofields'] && !@$opts['autobuttons']) {
            return $fields;
        }
        $newFieldList = [];
        foreach ($fields as $f => &$v) {
            /*
             * 'fields' => 'thisfield,thatfield,theOtherField' (delimiters can also be pipe symbol or whitespace)
             * 'buttons' => 'save|save_and_new|save_and_return' (ditto re delimiters)
             */
            if (is_string($v) && preg_match('/^\s*(field|button)s?\s*:\s*:(.*)\s*$/i', $v, $m)) {
                $pieces = preg_split("/[|,\s]+/", $m[2], PREG_SPLIT_NO_EMPTY);
                if (strtolower($m[1]) == 'field') {
                    $newFieldList = array_merge($newFieldList, $this->calcDefaultFields($pieces));
                } else { // 'button'
                    $newFieldList = array_merge($newFieldList, $this->calcButtons($pieces));
                }
            } elseif (is_array($v)) {
                if (strncasecmp($f, "field", strlen("field")) == 0) {
                    /* 'fields' => ['thisfield', 'thatfield', 'theOtherField'] */
                    $newFieldList = array_merge($newFieldList, $this->calcDefaultFields($v));
                } elseif (strncasecmp($f, "button", strlen("button")) == 0) {
                    /* 'buttons' => ['save', 'save_and_new', 'save_and_return'] */
                    $newFieldList = array_merge($newFieldList, $this->calcButtons($v));
                } else {
                    $newFieldList[$f] = $v;
                }
            } else {
                $newFieldList[$f] = $v;
            }
        }
        return $newFieldList;
    }

    public function prepDbTables() {
        if (!$this->getDBTableId() && Input::get('id', 'get')) {
            #dbg("::prepDbTables(): id=".Input::get('id', 'get'));
            $this->setDBTableId(Input::get('id', 'get'));
        }
        $this->_allDbTables[$this->getDbTable()] = [
            'dbtable' => $this->getDbTable(),
            'realdbtable' => $this->getDbTable(),
            'id' => $this->getDbTableId(),
        ];
        foreach ($this->getSubDbTables() as $tbl=>$opts) {
            #dbg("prepDbTables(): TOP OF LOOP, _allDbTables follows...");
            #pre_r($this->_allDbTables);
            $this->_allDbTables[$tbl] = array_merge((array)@$this->_allDbTables[$tbl], $opts);
            if (isset($opts['dbalias'])) { // alias is specified, get realdbtable from dbtable or $tbl
                if (isset($opts['dbtable'])) {
                    $this->_allDbTables[$tbl]['realdbtable'] = $opts['dbtable'];
                } else {
                    $this->_allDbTables[$tbl]['realdbtable'] = $tbl;
                }
                $this->_allDbTables[$tbl]['dbtable'] = $opts['dbtable'] = $opts['dbalias'];
            } elseif (isset($opts['realdbtable'])) { // realdb is specified, get alias (dbtable) from $tbl
                if (isset($opts['dbtable'])) {
                    $this->_allDbTables[$tbl]['dbtable'] = $opts['dbtable'];
                } else {
                    $this->_allDbTables[$tbl]['dbtable'] = $opts['dbtable'] = $tbl;
                }
                $this->_allDbTables[$tbl]['realdbtable'] = $opts['realdbtable'];
            } elseif (isset($opts['dbtable'])) { # both realdbtable and dbtable are the same from dbtable
                $this->_allDbTables[$tbl]['realdbtable'] = $this->_allDbTables[$tbl]['dbtable'] = $opts['dbtable'];
            } else { # neither dbalias nor realdbtable nor dbtable was specified - all are the same from $tbl
                $this->_allDbTables[$tbl]['realdbtable'] = $this->_allDbTables[$tbl]['dbtable'] = $opts['dbtable'] = $tbl;
            }
            $this->_allDbTables[$tbl]['id'] = $this->calcSubTableId($opts);
            if (isset($opts['mode'])) {
                $this->_allDbTables[$tbl]['mode'] = $opts['mode'];
            } else {
                #dbg("table=$tbl");
                #pre_r($this->_allDbTables[$tbl]);
                $this->_allDbTables[$tbl]['mode'] = ($this->_allDbTables[$tbl]['id'] ? 'UPDATE' : 'INSERT');
            }
        }
    }

    private function calcSubTableId(&$opts) {
        #dbg("calcSubTableId(): opts follow");
        #pre_r($opts);
        if (isset($opts['id'])) {
            return $opts['id'];
        } elseif ((isset($opts['mode']) && $opts['mode']=='INSERT') || @$opts['dbtable'] == $this->getDbTable()) {
            return null;
        } elseif (isset($opts['sql'])) {
            $bindvals = (isset($opts['bindvals']) ? $opts['bindvals'] : []);
            $q = $this->_db->query($opts['sql'], $bindvals);
            $this->_db->checkErrors($this->errors, true); // die if sql error
            $row = $q->first();
            #dbg($opts['sql']);
            #pre_r($bindvals);
            #var_dump($row);
            if ($q->count() == 1) {
                #dbg("Got 1 = id=".$row->id);
                return $row->id;
            } elseif ($q->count() == 0) {
                #dbg("Got 0");
                $except = isset($opts['found0']) ? strtoupper($opts['found0']) : 'ERROR';
                if ($except == 'ERROR') {
                    $this->errors[] = "ERROR: No Row Found for dbtable={$opts['dbtable']}";
                    if (configGet('debug_mode')) {
                        dbg("No rows found for this SQL:");
                        dbg("SQL=$opts[sql]");
                        pre_r($opts['bindvals']);
                    }
                    throw new Exception("DEV ERROR: No Row Found for dbtable={$opts['dbtable']}");
                } elseif ($except == 'INSERT') {
                    $opts['mode'] = 'INSERT';
                    return null;
                }
            } elseif ($q->count() > 1) {
                #dbg("Got multiple");
                $except = isset($opts['found2']) ? strtoupper($opts['found2']) : 'ERROR';
                if ($except == 'ERROR') {
                    $this->errors[] = "ERROR: No Single Row Found for dbtable={$opts['dbtable']}";
                    if (configGet('debug_mode')) {
                        dbg("DEBUG ERROR: MULTIPLE (".$q->count().") rows found for this SQL:");
                        dbg("DEBUG ERROR: SQL=$opts[sql]");
                        pre_r(@$opts['bindvals']);
                    }
                    throw new Exception("DEV ERROR: MULTIPLE Row Found for dbtable={$opts['dbtable']}");
                } elseif ($except == 'FIRST') {
                    #dbg("FIRST id={$row->id}");
                    return $row->id;
                } else {
                    throw new Exception("DEV ERROR: Unknown `found2` value '$except' for dbtable={$opts['dbtable']}");
                }
            }
        } else {
            $err = "DEV ERROR: ID not specified for subtable={$opts['dbtable']}";
            $this->error[] = $err;
            echo "$err";
        }
    }

    public function doFormProcessing() {
        $this->debug(1, "doFormProcessing(): Entering");
        if ($this->getAutoSaveUploads()) {
            $this->saveUploads();
        }
        if ($this->getDbAutoLoadPosted()) {
            $this->setNewValues($this->getDbAutoLoadPosted());
        }
        # Load values into fields to use in saving
        if ($this->getDbAutoLoad()) {
            $this->dbLoadData();
        }
        if ($this->getDBTable()) {
            if ($this->getDbAutoSave()) {
                $this->saveDbData();
                #dbg("gar=".$this->getAutoRedirect().", gras=".$this->getRedirectAfterSave());
                if ($this->getAutoRedirect() && $this->getRedirectAfterSave()) {
                    dbg("Redirecting to ".$this->getRedirectAfterSave());
                    #Redirect::to($this->getRedirectAfterSave());
                }
                # Now load the values that were just saved
                if ($this->getDbAutoLoad()) {
                    $this->dbLoadData();
                }
            }
        } else {
            if ($this->getDbAutoSave()) {
                $errors[] = "DEV ERROR: Cannot specify autosave without specifying dbtable";
            }
        }
	}
    public function handle1Opt($name, &$val) {
        $this->debug(4, "::(BaseForm::)handle1Opt($name, ".print_r($val,true)."): Entering");
        $simpleName = strtolower(str_replace('_', '', $name));
        switch ($simpleName) {
            case 'titletoken':
            case 'titlelang':
                $val = lang($val);
                # no break/return - falling through with new $val
            case 'title':
                # NOTE: may fall through from above
                $this->setMacro('Form_Title', $val);
                return true;
            case 'keepadmindashboard':
                $this->setKeepAdminDashBoard($val);
                return true;
            case 'formmode':
                $this->setFormMode($val);
                return true;
            case 'formaction':
                $this->setFormAction($val);
                return true;
            case 'tableid':
            case 'dbtableid':
                $this->setDbTableId($val);
                return true;
            case 'data':
            case 'loaddata':
                $this->setFieldValues(null, $val);
                return true;
            case 'autoload':
            case 'dbautoload':
                $this->setDbAutoLoad($val);
                return true;
            case 'autoshow':
                $this->setAutoShow($val);
                return true;
            case 'autoredirect':
                $this->setAutoRedirect($val);
                return true;
            case 'excludefields':
                $this->setExcludeFields($val);
                return true;
            case 'footercode':
            case 'footersnippet':
            case 'footersnippets':
                $this->setFooterSnippets($val);
                return true;
            case 'headercode':
            case 'headersnippet':
            case 'headersnippets':
                $this->setHeaderSnippets($val);
                return true;
            case 'autosave':
            case 'dbautosave':
                $this->setDbAutoSave($val);
                return true;
            case 'autoupload':
            case 'autosaveupload':
                $this->setAutoSaveUploads($val);
                return true;
            case 'autotoc':
                $this->setAutoToC($val);
                return true;
            case 'autoloadposted':
            case 'autoloadnew':
                $this->setDbAutoLoadPosted($val);
                return true;
            case (preg_match('/^(single|multi)[-_]?(update|insert|delete)[-_]?(fail|success|nothing)[-_]?(?:msgtoken|token)?$/', $simpleName, $m) ? $simpleName : null):
                $this->_msgTokens[$m[1]][$m[2]][$m[3]] = $val;
                return true;
            case (preg_match('/^(single|multi)[-_]?(update|insert|delete)[-_]?(fail|success|nothing)[-_]?(?:message|msg)?$/', $simpleName, $m) ? $simpleName : null):
                $this->_msgStrings[$m[1]][$m[2]][$m[3]] = $val;
                return true;
            case 'deletebutton':
            case 'deletesinglerowbutton':
                $this->_deleteSingleRowButton = $val;
                return true;
            case 'deletekey':
                $this->_deleteKey = $val;
                return true;
            case 'savebutton':
            case 'savesinglerowbutton':
                $this->_saveSingleRowButton = $val;
                return true;
            // the next 2 options determine which config values are used for post-successful-save redirects
            case 'singlerow':
                $val = !$val;
                // falling through - no break/return
            case 'multirow':
                // falling through from above - no break/return
                $this->setIsMultiRow($val);
                return true;
            case (preg_match('/^multi/', $simpleName) ? $simpleName : null): // special regex so multi1, multi2, multi_x can be used for many options
                #dbg("MULTI!!! name=$name");
                $this->addProcessor($val);
                return true;
            case 'activetab':
                $this->setActiveTab($val);
                return true;
            case 'validfunc':
                $this->setValidFunc($val);
                return true;
            case 'validfuncargs':
                $this->setValidFuncArgs($val);
                return true;
            case 'savefunc':
                $this->setSaveFunc($val);
                return true;
            case 'savefuncargs':
                $this->setSaveFuncArgs($val);
                return true;
            case 'dbtables': // note the plural
            case 'subtables':
                $this->setSubDbTables($val);
                return true;
        }
        return parent::handle1Opt($name, $val);
    }
    # If dev didn't specify which tabs to put in ToC or if he didn't specify an active
    # tab then take care of appropriate defaults
    public function defaultTabs() {
        #dbg("defaultTabs(): checking autotoc=".$this->getAutoToC());
        if ($this->getAutoToC() && $toc = $this->getField($this->getAutoToC())) {
            $curTabs = $this->getAllFields([], ['class'=>'FormTab_Pane', 'not_only_fields'=>true]);
            #var_dump($toc->getRepData());
            # Check that one of the tabs is active
            $found_active = false;
            $activeTabName = $this->getActiveTab();
            foreach ($curTabs as $fld) {
                if ($fld->getMacro('Tab_Pane_Active')) {
                    $found_active = true;
                    break;
                }
                if ($activeTabName && $activeTabName == $fld->getFieldName()) {
                    $activeTab = $fld;
                }
            }
            if (!$found_active) {
                #var_dump($curTabs);
                reset($curTabs);
                $firstKey = key($curTabs);
                $curTabs[$firstKey]->setTabIsActive('active');
                #$firstTab = $this->getField($tabId);
            }
            $curTabs = $this->getAllFields([], ['class'=>'FormTab_Pane', 'not_only_fields'=>true]);
            $toc->setRepData($curTabs);
            #dbg("toc->getRepData() follows");
            #var_dump($toc->getRepData());
        }
    }
    public function calcButtons($btns=[]) {
        if (!$btns || (sizeof($btns) == 1 && $btns[0] == 'all')) {
            $btns = ['save_and_edit', 'save_and_new', 'save_and_return'];
        }
        $defs = [];
        foreach ($btns as $k=>$btn) {
            if (is_numeric($k)) {
                $opts = [];
            } else {
                # 'buttons' => [ 'save'=>'Save X', 'save_and_new'=>'Another label']
                $opts = ['display'=>$btn];
                $btn = $k;
            }
            if (strncasecmp($btn, "delete", strlen("delete")) == 0) {
                $defs[$btn] = new FormField_ButtonDelete($opts);
            } elseif (strncasecmp($btn, "cancel", strlen("cancel")) == 0) {
                $defs[$btn] = new FormField_ButtonCancel($opts);
            } else { // assume 'save'
                $defs[$btn] = new FormField_ButtonSubmit($opts);
            }
        }
        return $defs;
    }
    public function calcDefaultFields($flds=[]) {
        if (sizeof($flds) == 1 && @$flds[0] == 'all') { // 'fields' => 'all'
            $needBtn = true;
            $flds = [];
        } else {
            $needBtn = false;
        }
        if (!isset($T[$this->getDbTable()])) {
            $T[$this->getDbTable()] = $this->getDbTable();
        }
        $db = DB::getInstance(); // $this->_db not available yet
        $fields = $db->query("SHOW COLUMNS FROM {$T[$this->getDbTable()]}")->results();
        #dbg($db->errorString(true));
        $defs = [];
        foreach ($fields as $f) {
            if (!$flds && in_array($f->Field, $this->_excludeFields)) {
                continue;
            }
            if ($flds && !in_array($f->Field, $flds)) {
                continue;
            }
            if (($i = strpos($f->Type, '(')) === false) {
                $type = $f->Type;
            } else {
                $type = substr($f->Type, 0, $i);
            }
            $fn = $f->Field;
            #var_dump($f);
            #dbg("name={$f->Field}, type=$type ({$f->Type})");
            if ($f->Type == 'tinyint(1)') { // boolean
                $defs[$fn] = new FormField_Checkbox;
            } elseif ($f->Type == 'text') {
                $defs[$fn] = new FormField_Textarea;
            } else {
                # later on we can get fancy with dates and stuff
                $defs[$fn] = new FormField_Text;
            }
        }
        $this->_defaultFields = $defs;
        return $this->_defaultFields;
    }
    # Recursively traverse all fields and apply needed actions such as checking for needed
    # deletion or calculating repData from SQL
    public function processFields($recursive=false, $doDeleteMe=true, $doCalcRep=true, $doDeleteIfEmpty=true) {
        $this->debug(2,"::processFields(r=".($recursive?"TRUE":"FALSE").", ddm=".($doDeleteMe?"TRUE":"FALSE").", dcr=".($doCalcRep?"TRUE":"FALSE").", ddie=".($doDeleteIfEmpty?"TRUE":"FALSE")."): ==ENTERING==");
        foreach ($this->getFields([], ['not_only_fields']) as $fieldName=>$f) {
            if (!is_object($f)) {
                continue;
            }
            $this->debug(4,"::processFields() Checking for deletion: $fieldName (doDeleteMe=$doDeleteMe, getDeleteMe=".$f->getDeleteMe().")");
            if ($doDeleteMe && (method_exists($f, 'getDeleteMe') && $f->getDeleteMe())) {
                $this->debug(5,"Deleting field=$fieldName");
                $this->deleteField($fieldName);
            }
            if ($doCalcRep && (method_exists($f, 'calcRepData'))) {
                $this->debug(5,"processFields(): Calculating repData for field=$fieldName");
                $f->calcRepData(true); // recalc even if already has data
            }
            $this->debug(4,"::processFields() Checking for deleteIfEmpty: $fieldName (doDeleteIfEmpty=$doDeleteIfEmpty, getDeleteIfEmpty=".($f->getDeleteIfEmpty()?"TRUE":"FALSE").", repDataIsEmpty=".$f->repDataIsEmpty().")");
            if ($doDeleteIfEmpty && method_exists($f, 'getDeleteIfEmpty') &&
                    method_exists($f, 'repDataIsEmpty') &&
                    $f->getDeleteIfEmpty() && $f->repDataIsEmpty(false, $recursive)) {
                $this->debug(4,"Deleting empty field=$fieldName");
                $this->deleteField($fieldName);
            }
            if ($recursive && method_exists($f, 'processFields')) {
                dbg("I DON'T THINK I EVER GET HERE. LET ME KNOW IF I DO. I THINK I CAN DUMP RECURSION IN THIS FUNCTION");
                $this->debug(5,"calling processFields for $fieldName");
                $f->processFields($recursive, $doDeleteMe, $doCalcRep, $doDeleteIfEmpty);
            }
        }
        $this->debug(2,"::processFields(r=".($recursive?"TRUE":"FALSE").", ddm=".($doDeleteMe?"TRUE":"FALSE").", dcr=".($doCalcRep?"TRUE":"FALSE").", ddie=".($doDeleteIfEmpty?"TRUE":"FALSE")."): ==EXITING==");
    }
    public function getAllProcessors() {
        $this->debug(2,"::getAllProcessors(): Entering");
        foreach ($this->getFields([], ['not_only_fields']) as $fieldName=>$f) {
            if (!is_object($f)) {
                continue;
            }
            #dbg(get_class($this)."::getAllProcessors(): TOP LOOP: processors=");
            #var_dump($this->_processors);
            if (method_exists($f, 'getProcessor')) {
                $this->debug(4,"::getAllProcessors(): getting processor field=$fieldName");
                if ($proc = $f->getProcessor()) {
                    $this->_processors[] = $proc;
                }
            }
            #dbg(get_class($this)."::getAllProcessors(): BOTTOM LOOP: processors=");
            #var_dump($this->_processors);
        }
        return $this->_processors;
    }
    public function hasFormFieldType($type) {
        $this->debug(2,"::hasFormFieldType(): Entering");
        foreach ($this->getFields([], ['not_only_fields']) as $fieldName=>$f) {
            if (is_object($f) && get_class($f) == $type) {
                return true;
            }
        }
        return false;
    }
    public function checkDeleteIfEmpty($recursive=false) {
        $this->debug(2,"::checkDeleteIfEmpty(".($recursive?"TRUE":"FALSE")."): Entering");
        $this->processFields($recursive, false, false, true);
    }
    public function dbLoadData() {
        $this->debug(1, "BaseForm::dbLoadData(): Entering");
        /*
        dbg("dbLoadData(): STARTING TEST");
        $fieldList = $this->fixFieldList([], true, $this->getDbTable());
        foreach ($this->getSubDbTables() as $tbl => $opts) {
            $fieldList = $this->fixFieldList([], true, $tbl);
        }
        dbg("dbLoadData(): ENDING TEST");
        */
        foreach ($this->_allDbTables as $opts) {
            #var_dump($opts);
            if ($opts['dbtable'] && !isset($opts['id']) && @$opts['mode'] != 'INSERT') {
                $opts['id'] = $this->calcSubTableId($opts);
            }
            if (@$opts['id'] && $opts['realdbtable']) {
                $dbData = $this->_db->findById($opts['realdbtable'], $opts['id']);
                if ($dbData->error()) {
                    $this->errors[] = lang('SQL_ERROR').' ('.$this->errorString().')';
                }
                if ($dbData && !$dbData->error() && $dbData->count() > 0) {
                    #dbg("BaseForm::dbLoadData(): found dbData record");
                    #var_dump($opts);
                    #var_dump($dbData->first());
                    $this->setFieldValues($opts['dbtable'], $dbData->first());
                    #dbg("dbLoadData(): Back from setFieldValues()");
                } else {
                    #dbg("AutoLoad failure - no row matching id={$this->_dbTableId} for table={$this->_dbTable}");
                    $this->errors[] = "ERROR: No row in table {$opts['dbtable']} matching id={$opts['id']}. This form is in an error state.";
                    return false;
                }
            }
        }
        #dbg("dbLoadData(): Out of loop, calling processFields");
        #dbg("BaseForm::dbLoadData(): Calling processFields with calcRepData=true");
        $this->processFields(false, false, true, false); // run calcRepData on any/all relevant fields
        #dbg("dbLoadData(): back from processFields()");
    }
    public function saveDbData($fieldFilter=[]) {
        $allFields = $this->fieldListNewValues($fieldFilter, false);
        if (!$this->checkFieldValidation($allFields, $this->errors)) {
            $this->setSaveStatus(self::UPDATE_ERROR);
            return false;
        }
        $this->debug(1, "BaseForm::saveDbData(): Entering");
        if ($this->buttonPressed($this->getSaveSingleRowButton(true))) { // was save button clicked?
            if (($status = $this->saveSingleRow()) === self::UPDATE_ERROR) {
                $this->setSaveStatus($status);
                return false;
            } elseif ($status == self::UPDATE_SUCCESS) {
                $this->setSaveStatus($status);
            }
        }
        if ($this->buttonPressed($this->getDeleteSingleRowButton(true))) { // was delete button clicked?
            if (($status = $this->deleteSingleRow()) === self::UPDATE_ERROR) {
                $this->setSaveStatus($status);
                return false;
            } elseif ($status == self::UPDATE_SUCCESS) {
                $this->setSaveStatus($status);
            }
        }
        $this->processMultiRowActions();
        # Return true as long as we didn't encounter an error
        # If the calling form wants to differentiate UPDATE_SUCCESS from UPDATE_NO_CHANGE
        # then they should check $this->getSaveStatus()
        return ($this->getSaveStatus() != self::UPDATE_ERROR);
    }

    # Save data into all single-row tables ('dbtable' option [and optionally 'subtables' options])
    public function saveSingleRow($ignoreValid=false) {
        $this->debug(2,"BaseForm::saveSingleRow(): Entering.");
        if (!$ignoreValid && !$this->_validatePassed) {
            $this->errors[] = "DEV ERROR: saveSingleRow() cannot be called without first validating data";
            return false;
        }
        #$this->setNewValues($_POST); // this already happened if autosaveposted set; shouldn't happen if it wasn't
        #dbg("saveSingleRow() allDbTables follows");
        #pre_r($this->_allDbTables);
        foreach ($this->_allDbTables as &$opts) {
            /*
             * If the general save button was not pressed and we didn't provide a special
             * button for this sub-table then we don't save this sub-table
             * -or-
             * If we set a special button for this sub-table and it wasn't pressed then
             * we don't save this sub-table.
             */
            if (    (!$this->buttonPressed($this->getSaveSingleRowButton())
                      && (!isset($opts['buttons']['save']) ) )
                 || (isset($opts['buttons']['save'])
                      && !$this->buttonPressed($opts['buttons']['save']) ) ) {
                continue;
            }
            if (!isset($opts['mode']) || !$opts['mode']) {
                if (isset($opts['id']) && $opts['id']) {
                    $opts['mode'] = 'UPDATE';
                } else {
                    $opts['mode'] = 'INSERT';
                }
            }
            if ($opts['mode'] == 'UPDATE') { // update existing row
                $this->debug(2,"BaseForm::saveSingleRow(): Updating ".$opts['dbtable']);
                if (($rtn = $this->updateRow($opts)) == self::UPDATE_SUCCESS) {
                    $this->setSaveStatus($rtn);
                    if ($msg = $this->getMessage($opts, 'single', 'update', 'success')) {
                        $this->successes[] = $msg;
                    }
                    $this->postSuccessfulSave($opts['dbtable'], 'update');
                } elseif ($rtn == self::UPDATE_ERROR) {
                    $this->setSaveStatus($rtn);
                    if ($msg = $this->getMessage($opts, 'single', 'update', 'fail')) {
                        $this->errors[] = $msg;
                    }
                } else { // assume self::UPDATE_NO_CHANGE
                    # don't setSaveStatus if UPDATE_NO_CHANGE
                    if ($msg = $this->getMessage($opts, 'single', 'update', 'nothing')) {
                        $this->successes[] = $msg;
                    }
                }
            } else { // $opts['mode'] == 'INSERT' -- insert new row
                $this->debug(2,"BaseForm::saveSingleRow(): Inserting to ".$opts['dbtable']);
                if (($rtn = $this->insertRow($opts)) == self::UPDATE_SUCCESS) {
                    $this->setSaveStatus($rtn);
                    $this->setLastId($opts['dbtable']);
                    if ($msg = $this->getMessage($opts, 'single', 'insert', 'success')) {
                        $this->successes[] = $msg;
                    }
                    $this->postSuccessfulSave($opts['dbtable'], 'insert', $this->_db->lastId());
                } elseif ($rtn == self::UPDATE_ERROR) {
                    $this->setSaveStatus($rtn);
                    if ($msg = $this->getMessage($opts, 'single', 'insert', 'fail')) {
                        $this->errors[] = $msg;
                    }
                } else { // assume self::UPDATE_NO_CHANGE
                    # don't setSaveStatus if UPDATE_NO_CHANGE
                    if ($msg = $this->getMessage($opts, 'single', 'insert', 'nothing')) {
                        $this->successes[] = $msg;
                    }
                }
            }
        }
        return $this->getSaveStatus();
    }
    public function deleteSingleRow() {
        $this->debug(2,"BaseForm::deleteSingleRow(): Entering.");
        $deleted = $errCount = 0;
        foreach ($this->_allDbTables as &$tableOpts) {
            # If you don't want to enforce referential integrity set 'no_delete' in sub-table options
            if (@$tableOpts['no_delete'] || !@$tableOpts['id']) {
                continue;
            }
            /*
             * If the general delete button was not pressed and we didn't provide a special
             * delete button for this sub-table then we don't delete from this sub-table
             * -or-
             * If we set a special delete button for this sub-table and it wasn't pressed then
             * we don't delete from this sub-table.
             */
            /*
            dbg("deleteSingleRow(): tableOpts=");
            pre_r($tableOpts);
            if (isset($tableOpts['buttons']['delete'])) {
                dbg("opts[buttons][delete]=".print_r($tableOpts['buttons']['delete'], true));
                if ($this->buttonPressed($tableOpts['buttons']['delete'])) {
                    dbg("YES, BUTTON WAS PRESSED!");
                } else {
                    dbg("NO, BUTTON WAS NOT PRESSED");
                }
            }
            */
            if (    (!$this->buttonPressed($this->getDeleteSingleRowButton())
                      && (!isset($tableOpts['buttons']['delete']) ) )
                 || (isset($tableOpts['buttons']['delete'])
                      && !$this->buttonPressed($tableOpts['buttons']['delete']) ) ) {
                continue;
            }
            $delId = $tableOpts['id'];
            #dbg("Deleting id=$delId from {$tableOpts['dbtable']}");
            if ($delId && $this->_db->deleteById($tableOpts['dbtable'], $delId)) {
                $deleted += $this->_db->count();
                unset($tableOpts['id']); // force a reload
            }
            if ($this->_db->error() || $deleted < 1) {
                #die("GOT ERROR");
                #exit;
                $this->errors[] = $this->getMessage([], 'single', 'delete', 'fail');
                $errCount++;
            } else {
                #die("NO ERROR");
                #exit;
                $this->successes[] = $this->getMessage([], 'single', 'delete', 'success', $deleted);
                $this->postSuccessfulSave($tableOpts['dbtable'], 'delete', null, false);
            }
        }
        if ($errCount) {
            return self::UPDATE_ERROR;
        } elseif ($deleted) {
            return self::UPDATE_SUCCESS;
        } else {
            return self::UPDATE_NO_CHANGE;
        }
    }
    public function processMultiRowActions() {
        $this->debug(2, "processMultiRowActions(): Entering");
        $rtn = true; // reasonable default
        # You can have multiple sets of options, each defining a different action to take on multi-rows.
        # Loop through each of those sets
        #var_dump($this->getProcessors());
        foreach ($this->getProcessors() as $k=>$optSet) {
            #dbg("processMultiRowActions(): Looping k=$k, idfield=".@$optSet['idfield']);
            # Check if the button was pressed (if no button set, just assume we should do the action)
            $buttons = $this->getMultiOpt($optSet, 'button', $this->getSaveMultiRowsButton());
            if ($buttons && !$this->buttonPressed($buttons)) {
                #dbg("processMultiRowActions(): k=$k - Button not pressed. Going to next.");
                continue;
            }
            # Now grab the rest of the options from this $multiOpts element
            #dbg("processMultiRowActions(): Grabbing options.");
            if (!isset($optSet['action'])) {
                throw new Exception("DEV ERROR: `action` must be specified");
            }
            $action = $optSet['action'];
            if (!isset($optSet['idfield'])) {
                throw new Exception("DEV ERROR: `idfield` must be specified");
            }
            $idfield = $optSet['idfield'];
            $dbtable = $this->getMultiOpt($optSet, 'dbtable', $this->getDbTable());
            $idbyidx = $this->getMultiOpt($optSet, 'idbyidx', false);
            $function = $this->getMultiOpt($optSet, 'function', '');
            $method = $this->getMultiOpt($optSet, 'method', 'post');
            $fieldsTemplate = $this->getMultiOpt($optSet, 'fields', []);
            $whereTemplate = $this->getMultiOpt($optSet, 'where', []);
            $errCount = $successCount = 0;
            $db = $this->_db;
            # Loop through each $id that should be processed
            foreach ((array)@$_POST[$idfield] as $k=>$id) {
                #dbg("processMultiRowActions(): Top of loop k=$k, id=$id");
                # If $idfield is by index instead of by value, fix $id appropriately
                if ($idbyidx) {
                    if (!$id) {
                        continue;
                    }
                    $id = $k;
                }
                # Create $fields and $where from the respective templates
                $inputVals = $this->getInputVals($fieldsTemplate, $method);
                $fields = $this->mkFieldList($fieldsTemplate, $inputVals, $id);
                $inputVals = $this->getInputVals($whereTemplate, $method);
                $where = $this->mkFieldList($whereTemplate, $inputVals, $id);
                #dbg("Processing with dbtable=$dbtable, id=$id, fields=<pre>".print_r($fields,true)."</pre>, where=<pre>".print_r($where,true)."</pre>");
                # Do the actual database processing
                if ($function) {
                    # if a function was specified, call it (allows for maintaining referential integrity or etc)
                    # Note that messages should be handled within the function
                    $function($dbtable, $id, $where, $fields);
                } else {
                    # if no function was specified, call $db functions according to $action
                    switch ($action) {
                        case 'delete':
                            if ($where) {
                                $db->delete($dbtable, $where);
                            } else {
                                $db->deleteById($dbtable, $id);
                            }
                            break;
                        case 'update':
                            if ($where) {
                                $db->updateAll($dbtable, $where, $fields);
                            } else {
                                $db->updateById($dbtable, $id, $fields);
                            }
                            break;
                        case 'insert':
                        #pre_r($fields);
                            $db->insert($dbtable, $fields);
                            $rtn = $db->lastId();
                            break;
                        default:
                            throw new Exception("ERROR: Unknown action `$action`");
                    }
                    if ($db->error()) {
                        $this->errors[] = lang('SQL_ERROR');
                        $this->errors[] = $db->errorString();
                        $errCount++;
                    } else {
                        $successCount += $db->count();
                    }
                }
            } // foreach through all $id
            if ($errCount) {
                $this->setSaveStatus(self::UPDATE_ERROR);
                $rtn = false;
                if ($msg = $this->getMessage($optSet, 'multi', $action, 'fail')) {
                    $this->errors[] = $msg;
                }
            } elseif ($successCount > 0) {
                $this->setSaveStatus(self::UPDATE_SUCCESS);
                if ($msg = $this->getMessage($optSet, 'multi', $action, 'success', [$successCount])) {
                    $this->successes[] = $msg;
                }
            } elseif ($msg = $this->getMessage($optSet, 'multi', $action, 'nothing')) {
                $this->successes[] = $msg;
            }
        } // foreach through all $MultiOpts
        return $rtn;
    }
    public function saveUploads() {
        #dbg("saveUploads(): Entering");
        foreach ($this->getFields([], ['class'=>'FormField_File']) as $k=>$obj) {
            #dbg("saveUploads(): k=$k");
            if ($obj->dataIsValid()) {
                $obj->saveUpload();
            } else {
                $this->errors = $obj->stackErrorMessages($this->errors);
            }
        }
    }
    public function getMessage($optSet, $single_multi, $action, $status, $args=[]) {
        if (isset($optSet[$status.'_message'])) {
            return $optSet[$status.'_message'];
        } elseif (isset($optSet[$status.'_msgtoken'])) {
            return lang($optSet[$status].'_msgtoken', $args);
        } elseif (isset($this->_msgStrings[$single_multi][$action][$status])) {
            return $this->_msgStrings[$single_multi][$action][$status];
        } elseif ($this->_msgTokens[$single_multi][$action][$status]) {
            return lang($this->_msgTokens[$single_multi][$action][$status], $args);
        } else {
            return false;
        }
    }
    public function setSaveStatus($val) {
        #dbg("setSaveStatus($val): Entering (was {$this->_saveStatus})");
        # UPDATE_NO_CHANGE (2) can be changed to UPDATE_SUCCESS (1) or UPDATE_ERROR (0)
        # UPDATE_SUCCESS (1) can be changed to UPDATE_ERROR (0)
        # UPDATE_ERROR (0) cannot be changed - we always report an error if it happened
        if ($this->_saveStatus > $val) {
            $this->_saveStatus = $val;
        }
    }
    # UPDATE_NO_CHANGE = nothing was done
    # UPDATE_SUCCESS = something was successfully saved, no error conditions occurred
    # UPDATE_ERROR = some error condition or failure occurred
    public function getSaveStatus() {
        return $this->_saveStatus;
    }
    public function getInputVals($template, $method='post') {
        $vals = [];
        foreach ($template as $k=>$fld) {
            if (preg_match('/^\{([^{}]+)\}$/', $fld, $m) && strtolower($m[1]) != 'id') { // 'x' => '{field}'
                $vals[$m[1]] = Input::get($m[1], $method);
            }
        }
        return $vals;
    }
    public function mkFieldList($template, $inputVals, $id) {
        $rtn = [];
        foreach ($template as $k=>$fld) {
            if (preg_match('/^\{([^{}]+)\}$/', $fld, $m)) { // 'x' => '{field}'
                if (strtolower($m[1]) == 'id') {
                    $rtn[$k] = $id;
                } elseif (preg_match('/^([^.]+)\.last_?id$/i', $m[1], $mm)) {
                    #dbg($m[1]);
                    #pre_r($mm);
                    $rtn[$k] = $this->getLastId($mm[1]);
                } else {
                    # At some point we're going to find an unset value, but what to do if found?
                    # For now I'll just let it throw an error, I guess...
                    if (!isset($inputVals[$m[1]][$id])) { dbg("m[1]={$m[1]}, id=$id"); pre_r($inputVals); }
                    $rtn[$k] = $inputVals[$m[1]][$id];
                }
            } else {
                $rtn[$k] = $fld; // hard-coded value
            }
        }
        return $rtn;
    }

    public function postSuccessfulSave($dbTable, $action, $lastId=null, $override=true) {
        static $alreadySet = false; // needed to delete doesn't override edit settings if both called
        # we're really only interested in redirecting on the primary table
        if ($dbTable != $this->getDbTable()) {
            return;
        }
        #dbg("postSuccessfulSave(): alreadySet=$alreadySet, override=$override");
        if ($override || !$alreadySet) {
            #dbg( "Calling sRAS() with rDAS (lastId=$lastId)");
            $alreadySet = true;
            $this->setRedirectAfterSave(redirDestAfterSave($action, [$_SERVER['PHP_SELF'], $this->getFormName(), basename($_SERVER['PHP_SELF'])], $this->getIsMultiRow(), $lastId));
            #dbg( "postSuccessfulSave(): RedirAfterSave=".$this->getRedirectAfterSave());
        }
    }
    public function buttonPressed($buttonKeys) {
        $this->debug(4, "::buttonPressed(".print_r($buttonKeys,true)."): Entering");
        foreach ((array)$buttonKeys as $btn) {
            if (!empty(Input::get($btn))) {
                $this->_actionButton = $btn;
                $this->debug(4, "::buttonPressed(): Returning true with button=$btn");
                return true;
            }
        }
        $this->debug(4, "::buttonPressed(): Returning false");
        return false;
    }
    public function setTitleByPage() {
        if (($pageRow = getPagerowByName([$_SERVER['PHP_SELF'], $this->_formName])) && $pageRow->title_token) {
            $this->setMacro('Form_Title', lang($pageRow->title_token));
        }
    }

    public function setActiveTab($val) {
        $this->_activeTab = $val;
    }
    public function getActiveTab() {
        return $this->_activeTab;
    }
    public function setLastId($tbl) {
        $this->_lastId[$tbl] = $this->_db->lastId();
    }
    public function getLastId($dbTable) {
        if (isset($this->_lastId[$dbTable])) {
            return $this->_lastId[$dbTable];
        } else {
            return null; // error condition, presumably
        }
    }
    public function setFormMode($mode) {
        if (!in_array($mode, ['UPDATE', 'INSERT'])) {
            $this->errors[] = "DEV ERROR: Cannot set form Mode to $mode. Must be INSERT or UPDATE";
        }
        $this->_formMode = $mode;
    }
    public function getFormMode() {
        return $this->_formMode;
    }
    public function getProcessors() {
        return $this->_processors;
    }
    public function getHTMLHeader($opts=[], $noFill=false) {
        if (isset($GLOBALS['headerSnippets'])) {
            $vars['headerSnippets'] = array_merge($GLOBALS['headerSnippets'], $this->getHeaderSnippets());
        } else {
            $vars['headerSnippets'] = $this->getHeaderSnippets();
        }
        return getInclude(pathFinder('includes/header.php'), $vars);
    }
    public function getHTMLNavigation($opts=[], $noFill=false) {
        return getInclude(pathFinder('includes/navigation.php'));
    }
    public function getHTMLPageFooter($opts=[], $noFill=false) {
        if (isset($GLOBALS['footerSnippets'])) {
            $vars['footerSnippets'] = array_merge($GLOBALS['footerSnippets'], $this->getFooterSnippets());
        } else {
            $vars['footerSnippets'] = $this->getFooterSnippets();
        }
        return getInclude(pathFinder('includes/page_footer.php'), $vars);
    }
    public function getHTMLFooter($opts=[], $noFill=false) {
        $html = getInclude(pathFinder('includes/html_footer.php'));
        $scripts = [];
        foreach ($this->getAllFields() as $f=>$field) {
            if (method_exists($field, 'getHTMLScripts')) {
                #dbg("getHTMLFooter f=$f");
                $scripts = array_merge($scripts, (array)$field->getHTMLScripts());
                #dbg("SCRIPTS:");
                #pre_r($scripts);
            }
        }
        $scripts = array_unique($scripts);
        $html = implode("\n", $scripts).$html;
        return $html;
    }

    public function getMacros($s, $opts) {
        if (is_array($s)) {
            $s = implode("\n", $s); // only care about string
        }
        $macros = parent::getMacros($s, $opts);
        # These macros are "expensive" to evaluate and so are only
        # evaluated if they actually exist in the $s string
        if (stripos($s, "{RESULT_BLOCK}") !== false) {
            $errors = isset($opts['errors']) ? $opts['errors'] : $this->errors;
            $successes = isset($opts['successes']) ? $opts['successes'] : $this->successes;
            $macros['{RESULT_BLOCK}'] = resultBlock((array)$errors, (array)$successes);
        }
        if (stripos($s, "{GENERATE_CSRF_TOKEN}") !== false) {
            $macros['{GENERATE_CSRF_TOKEN}'] = Token::generate();
        }
        if (stripos($s, "{INCLUDE_ADMIN_DASHBOARD}") !== false) {
            $macros['{INCLUDE_ADMIN_DASHBOARD}'] = getInclude(pathFinder('includes/admin_dashboard.php'));
            $this->setFooterSnippets('<script>$("#admin")</script>');
        }
        if (stripos($s, "{FORM_ENCTYPE}") !== false) {
            if ($this->hasFormFieldType('FormField_File')) {
                $macros['{FORM_ENCTYPE}'] = 'enctype="multipart/form-data"';
            } else {
                $macros['{FORM_ENCTYPE}'] = '';
            }
        }
        return $macros;
    }

    public function getMultiRowDeleteWhere() {
        return $this->_multiRowDeleteWhere;
    }
    public function getMultiRowUpdateFields() {
        return $this->_multiRowUpdateFields;
    }
    public function getMultiRowInsertFields() {
        return $this->_multiRowInsertFields;
    }
    public function getMultiRowUpdateKey() {
        return $this->_multiRowUpdateKey;
    }
    public function getMultiRowInsertKey() {
        return $this->_multiRowInsertKey;
    }
    public function getActionButton() {
        return $this->_actionButton;
    }
    public function getSaveSingleRowButton($checkSubTables=false) {
        $s = [];
        if ($checkSubTables) {
            foreach ($this->_allDbTables as $tableOpts) {
                if (isset($tableOpts['buttons']['save'])) {
                    $s = array_merge($s, (array)$tableOpts['buttons']['save']);
                }
            }
        }
        $s = array_merge($s, (array)$this->_saveSingleRowButton);
        return $s;
    }
    public function getSaveMultiRowsButton() {
        return $this->_saveMultiRowsButton;
    }
    public function getDeleteSingleRowButton($checkSubTables=false) {
        $s = [];
        if ($checkSubTables) {
            foreach ($this->_allDbTables as $tableOpts) {
                if (isset($tableOpts['buttons']['delete'])) {
                    $s = array_merge($s, (array)$tableOpts['buttons']['delete']);
                }
            }
        }
        $s = array_merge($s, (array)$this->_deleteSingleRowButton);
        return $s;
    }
    public function getDeleteMultiRowsButton() {
        return $this->_deleteMultiRowsButton;
    }
    public function getDeleteKey() {
        return $this->_deleteKey;
    }
    public function getHeaderSnippets() {
        return $this->_headerSnippets;
    }
    public function setHeaderSnippets($val) {
        foreach ((array)$val as $v) {
            #dbg("setHeaderSnippets(): HEADER ADDED: ".htmlentities($v));
            $this->_headerSnippets[] = $v;
        }
    }
    public function getFooterSnippets() {
        return $this->_footerSnippets;
    }
    public function setFooterSnippets($val) {
        $this->_footerSnippets = $val;
    }
    public function getAutoSaveUploads() {
        return $this->_autoSaveUploads;
    }
    public function setAutoSaveUploads($val) {
        $this->_autoSaveUploads = $val;
    }
    public function getAutoShow() {
        return $this->_autoShow;
    }
    public function getAutoToC() {
        return $this->_autoToC;
    }
    public function setAutoToC($val) {
        $this->_autoToC = $val;
    }
    public function setAutoShow($val) {
        $this->_autoShow = $val;
    }
    public function getAutoRedirect() {
        return $this->_autoRedirect;
    }
    public function setAutoRedirect($val) {
        $this->_autoRedirect = $val;
    }
    public function getRedirectAfterSave() {
        # If form specifies then it over-rides
        if (($r = $this->getAutoRedirect()) && is_string($r)) {
            return $r;
        }
        return $this->_redirectAfterSave;
    }
    public function setRedirectAfterSave($val) {
        $this->_redirectAfterSave = $val;
    }
    public function getExcludeFields() {
        return $this->_excludeFields;
    }
    public function setExcludeFields($val) {
        $this->_excludeFields = $val;
    }
    public function getIdsToDelete() {
        return $this->_idsToDelete;
    }
    public function setIdsToDelete($val) {
        $this->_idsToDelete = $val;
    }
    public function getDbAutoSave() {
        return (Input::exists() && $this->_dbAutoSave);
    }
    public function setDbAutoSave($val) {
        $this->_dbAutoSave = $val;
    }
    public function getDbAutoLoadPosted() {
        return $this->_dbAutoLoadPosted;
    }
    public function setDbAutoLoadPosted($val) {
        if ($val) {
            if (is_array($val)) {
                $this->_dbAutoLoadPosted = $val;
            } else {
                $this->_dbAutoLoadPosted = $_POST;
            }
        } else {
            $this->_dbAutoLoadPosted = $val;
        }
    }
    public function getDbAutoLoad() {
        return $this->_dbAutoLoad;
    }
    public function setDbAutoLoad($val) {
        $this->_dbAutoLoad = $val;
    }
    public function getDbTableId() {
        return $this->_dbTableId;
    }
    public function setDbTableId($val) {
        $this->_dbTableId = $val;
    }
	public function setFormAction($action) {
		$this->MACRO_Form_Action=$action;
	}
    public function setKeepAdminDashBoard($val) {
        $this->_keepAdminDashBoard = $val;
    }
    public function getKeepAdminDashBoard() {
        return $this->_keepAdminDashBoard;
    }
    public function getAllFields($fieldFilter=[], $opts=[]) {
        $opts = array_merge($opts, ['recursive'=>true]);
        return $this->getFields($fieldFilter, $opts);
    }
    public function dbgFields($level=0) {
        foreach ($this->repData as $k=>$v) {
            echo str_repeat("&nbsp;&nbsp;&nbsp;", $level).$k." ".(is_null($v)?"NULL":"NOT NULL")."<br />\n";
            if (method_exists($v, 'dbgFields')) {
                $v->dbgFields($level+1);
            }
        }
    }
    public function getFields($fieldFilter=[], $opts=[]) {
        $this->debug(4, "getFields(): Entering");
        #dbg("opts=".print_r($opts,true));
        # default to $onlyFields==true unless override
        $onlyFields = !(@$opts['not_only_fields'] || in_array('not_only_fields', $opts));
        $dbTable = (!empty($opts['dbtable']) ? $opts['dbtable'] : false);
        #$recursive = (@$opts['recursive'] || in_array('recursive', $opts));
        $recursive = false; // always false due to fixFieldList() implementation?
        $fieldList = $this->fixFieldList($fieldFilter, $onlyFields);
        #dbg("getFields() fieldlist follows");
        #pre_r($fieldList);
        $rtn = [];
        foreach ($fieldList as $f) {
            $field = $this->getField($f);
            if (isset($opts['class']) && is_a($field, $opts['class'])) {
                $rtn[$f] = $field;
            } else {
                if ($recursive && method_exists($field, 'getFields')) {
                    // allow for forms nested in forms
                    $rtn = array_merge($rtn, $field->getFields($fieldFilter, $opts));
                } elseif (!isset($opts['class'])) {
                    $rtn[$f] = $field;
                }
            }
        }
        #dbg("getFields(): Returning keys=<pre>".print_r(array_keys($rtn),true)."</pre><br />\n");
        #dbg("getFields(): Returning<pre>".print_r($rtn,true)."</pre><br />\n");
        return $rtn;
    }
    public function getField($fieldName) {
        if (isset($this->repData[$fieldName])) {
            # normal field
            return $this->repData[$fieldName];
        } else {
            # perhaps it's in a FormTab_Contents or other form-section class
            foreach ($this->repData as $k=>$v) {
                if (is_a($v, 'BaseForm') && method_exists($v, 'getField') && ($f = $v->getField($fieldName))) {
                    return $f;
                }
            }
        }
    }
    public function deleteField($fieldName) {
        unset($this->repData[$fieldName]);
    }
    public function listFields($onlyFields=true, $onlyDbTable=false, $parentDbTable=false) {
        $this->debug(3, "listFields(".($onlyFields?"TRUE":"FALSE").", ".(is_bool($onlyDbTable)?($onlyDbTable?"TRUE":"FALSE"):print_r($onlyDbTable, true))."): entering");
        if (!$parentDbTable) {
            $parentDbTable = $this->getDbTable();
            #dbg("listFields() Set parentDbTable to $parentDbTable");
        }
        $currentDbTable = ($this->getDbTable() ? $this->getDbTable() : $parentDbTable);
        $rtn = [];
        foreach ($this->repData as $k=>$v) {
            $childDbTable = (method_exists($v, 'getDbTable') ? $v->getDbTable() : '');
            if (!$childDbTable) {
                $childDbTable = $currentDbTable;
            }
            if (method_exists($v, 'listFields')) {
                $rtn = array_merge($rtn, $v->listFields($onlyFields, $onlyDbTable, $currentDbTable));
            }
            if ($onlyDbTable && $childDbTable !== $onlyDbTable) {
                #dbg("listFields(): Continuing onlyDbTable=$onlyDbTable != v->getDbTable()=".$v->getDbTable());
                continue; // wrong table - not interested
            }
            if (!$onlyFields || is_a($v, 'US_FormField')) {
                #dbg("listFields(): adding $k");
                $rtn[] = $k;
            }
        }
        #if (!$parentDbTable) {
            #dbg("listFields(): Returning <pre>".print_r($rtn,true)."</pre><br />");
        #}
        return $rtn;
    }
    public function setFieldValues($dbTable, $vals, $fieldFilter=array()) {
        $this->debug(1, '::setFieldValues('.print_r($dbTable,true).', '.print_r($vals,true).'): Entering ');
        if (empty($dbTable)) {
            $dbTable = $this->getDbTable();
        }
        $fieldList = $this->fixFieldList($fieldFilter, true, $dbTable);
        #dbg("setFieldValues(): back from ffl");
        #var_dump($fieldList);
        foreach ($fieldList as $f) {
            $this->debug(2, "::setFieldValues() - looping with f=$f");
            if (is_array($vals)) {
                # Hmmm... I don't think we ever get in here...
                dbg("DEVELOPER NOTE: LET ME KNOW IF I EVER GET IN THIS SECTION OF setFieldValues(). I DON'T THINK WE EVER PASS AN ARRAY");
                if (isset($vals[$f])) {
                    $this->getField($f)->setFieldValue($vals[$f]);
                    $this->debug(3, "::setFieldValues(): using array to set ".print_r($vals[$f],true));
                }
            } else { # presumably it is an object
                $curObj = $this->getField($f);
                if ($curObj->getDbFieldName()) {
                    $f = $curObj->getDbFieldName();
                }
                if (is_null($curObj)) { dbg("null f=$f"); }
                // handle nested forms (like for tabs)
                if (method_exists($curObj, 'setFieldValues')) {
                    $this->debug(3, "::setFieldValues(): using curObj to call setFieldValues()");
                    $curObj->setFieldValues($dbTable, $vals, $fieldFilter);
                } elseif (isset($vals->$f)) {
                    #if (!method_exists($curObj, 'setFieldValue')) { dbg( 'class='.get_class($curObj).', parent='.get_parent_class($curObj)); var_dump($curObj); }
                    $this->debug(3, "::setFieldValues(): using curObj to set ".print_r($vals->$f,true));
                    $curObj->setFieldValue($vals->$f);
                #} else {
                #    dbg("WHAT ON EARTH?!");
                #    pre_r($vals);
                }
            }
        }
        $this->_hasFieldValues = true; // mark it as loaded
    }

    public function setNewValues($vals, $fieldFilter=array()) {
        #dbg("setNewValues(): Entering");
        #pre_r($vals);
        $fieldList = $this->fixFieldList($fieldFilter, true);
        foreach ($fieldList as $f) {
            #dbg("setNewValues(): f=$f");
            if (isset($vals[$f])) {
                $this->getField($f)->setNewValue($vals[$f]);
            }
        }
    }
    public function fieldListNewValues($fieldFilter=array(), $onlyDB=true, $onlyDbTable=false) {
        $this->debug(2, "fieldListNewValues(): Entering");
        $fieldList = $this->fixFieldList($fieldFilter, $onlyDB, $onlyDbTable);
        $rtn = [];
        #dbg("fieldListNewValues(): fieldlist=");
        #pre_r($fieldList);
        foreach ($fieldList as $f) {
            $fld = $this->getField($f);
            if (method_exists($fld, 'getDbFieldName') && $fld->getDbFieldName()) {
                $f = $fld->getDbFieldName();
            }
            if (is_a($fld, 'FormField') && (!$onlyDB || $fld->getIsDBField())) {
                if (($newVal = $fld->getNewValue()) !== null) {
                    $rtn[$f] = $newVal;
                }
            }
        }
        #dbg("fieldListNewValues(): returning rtn=");
        #var_dump($rtn);
        return $rtn;
    }
    # If someone wants a sub-set of fields, make sure they are all in the form
    protected function fixFieldList($fieldFilter, $onlyFields=true, $onlyDbTable=false) {
        if ($fieldFilter) {
            return array_intersect($this->listFields($onlyFields, $onlyDbTable), $fieldFilter);
        } else {
            #dbg("ffl: calling lf");
            #$rtn = $this->listFields($onlyFields, $onlyDbTable);
            #dbg("ffl: back from calling lf");
            #return $rtn;
            return $this->listFields($onlyFields, $onlyDbTable);
        }
    }
    public function isChanged($fieldFilter=array()) {
        $fieldList = $this->fixFieldList($fieldFilter);
        foreach ($fieldList as $f) {
            if ($this->getField($f)->isChanged()) {
                return true;
            }
        }
        return false;
    }
    public function repDataIsEmpty($considerPlaceholder=false, $recursive=false) {
        if (!$recursive) {
            return !(boolean)$this->repData;
        }
        $isEmpty = false;
        foreach ($this->repData as $k=>$r) {
            $this->debug(2, "::repDataIsEmpty(): k=$k");
            if (!method_exists($r, 'isRepeating') || !$r->isRepeating()) {
                continue;
            }
            if (method_exists($r, 'repDataIsEmpty')) {
                if ($r->repDataIsEmpty($considerPlaceholder, $recursive)) {
                    $isEmpty = true;
                } else {
                    $isEmpty = false;
                    break;
                }
            }
        }
        return $isEmpty;
    }

    public function getMultiOpt($optSet, $key, $default) {
        if (!isset($optSet[$key])) {
            return $default;
        }
        return $optSet[$key];
    }
    public function addProcessor($val) {
		$this->_processors[]=$val;
	}
	public function getIsMultiRow() {
		return $this->_isMultiRow;
	}
    public function setIsMultiRow($val) {
		$this->_isMultiRow=$val;
	}
	public function getFormName() {
		return $this->_formName;
	}
	public function setFormName($name) {
		$this->_formName=$name;
	}

    public function insertRow(&$tableOpts, $fieldFilter=[]) {
        $this->debug(4, "insertRow(".print_r($tableOpts,true)."): Entering");
        $fields = $this->fieldListNewValues($fieldFilter, true, $tableOpts['dbtable']);
        /* 5/1/2017 deleting this without testing... Need to continue in case
         * the saveFunc() wants to do something...
        if (!$fields) {
            return self::UPDATE_NO_CHANGE; // means no error, but no update occurred
        }
        */
        #dbg("insertRow(): fields follows");
        #var_dump($fields);
        $this->addExtraFields($fields, $tableOpts, 'insert');
        if ($savefunc = $this->getSaveFunc()) {
            $args = $this->getSaveFuncArgs();
            $id = @$tableOpts['id']; // almost certainly blank
            $result = $savefunc($tableOpts['mode'],
                            $tableOpts['realdbtable'],
                            $id,
                            $fields,
                            $args,
                            $this->errors,
                            $this->successes
                        );
            # If function returns a negative value we continue on below
            if ($result >= 0) {
                return $result;
            }
            $tableOpts['id'] = $id; // set to id of newly inserted record
        }
        if (isset($fields['id'])) {
            unset($fields['id']); // use auto increment
        }
        if (!$fields) {
            return self::UPDATE_NO_CHANGE;
        }
        #pre_r($fields);
        if ($this->_db->insert($tableOpts['realdbtable'], $fields)) {
            $tableOpts['id'] = $this->_db->lastId(); // set to id of newly inserted record
            return self::UPDATE_SUCCESS;
        } else {
            $this->errors[] = lang('SQL_ERROR').' ('.$this->_db->errorString().')';
            return self::UPDATE_ERROR;
        }
    }

    protected function updateRow(&$tableOpts, $fieldFilter=[]) {
        $this->debug(4, "updateRow(".print_r($tableOpts,true)."): Entering");
        $fields = $this->fieldListNewValues($fieldFilter, true, $tableOpts['dbtable']);
        #dbg("updateRow(): table=".$tableOpts['dbtable'].", id=".$tableOpts['id']);
        #var_dump($fields);
        #dbg("updateRow(): allFields follows");
        #var_dump($allFields);
        $this->addExtraFields($fields, $tableOpts, 'update');
        if ($func = $this->getSaveFunc()) {
            $args = $this->getSaveFuncArgs();
            $result = $func($tableOpts['mode'],
                            $tableOpts['realdbtable'],
                            $tableOpts['id'],
                            $fields,
                            $args,
                            $this->errors,
                            $this->successes
                        );
            # If the function returns a negative number then continue to do a normal
            # save afterwards
            if ($result >= 0) {
                return $result;
            }
        }
        if (!$fields) {
            return self::UPDATE_NO_CHANGE; // means no error, but no update occurred
        }
        if ($this->_db->update($tableOpts['realdbtable'], $tableOpts['id'], $fields)) {
            #dbg("updateRow(): SUCCESS");
            if ($this->_db->count() > 0) {
                return self::UPDATE_SUCCESS; // means update occurred
            } else {
                return self::UPDATE_NO_CHANGE; // means no error, but no update
            }
        } else {
            $this->errors[] = lang('SQL_ERROR').' ('.$this->_db->errorString().')';
            return self::UPDATE_ERROR; // error return value
        }
    }

    private function addExtraFields(&$fields, $tableOpts, $prefix) {
        $this->debug(5, "addExtraFields(..., $prefix): Entering ({$tableOpts['dbtable']})");
        #pre_r($tableOpts);
        if (isset($tableOpts[$prefix.'_fields'])) {
            $fields = array_merge((array)$tableOpts[$prefix.'_fields'], $fields);
        } elseif (isset($tableOpts['fields'])) {
            $fields = array_merge((array)$tableOpts['fields'], $fields);
        }
        foreach ($fields as $k=>&$v) {
            if (substr($v, 0, 1) == '{' && substr($v, -1) == '}') {
                $fn = substr($v, 1, -1);
                $v = Input::get($fn);
            }
        }
        $this->debug(5, "addExtraFields(): fields is now ".print_r($fields,true));
    }

    # Delete rows identified by $ids in the DB table for this form
    public function delete($ids, &$errors) {
        if (!$ids) {
            return true;
        }
        if (!$table = $this->getDBTable()) {
            $errors[] = 'ERROR: No table specified';
            return false;
        }
        $count = 0;
        foreach ($ids as $id) {
            $count += $db->delete($table, $id)->count();
        }
        return $count;
    }
	public function checkFieldValidation($data=[], &$errors, $fieldFilter=array()) {
        foreach ($this->fixFieldList($fieldFilter) as $f) {
            $fieldList[$f] = $this->getField($f);
        }
        $errors = [];
        if (($func = $this->getValidFunc())) {
            $args = $this->getValidFuncArgs();
            $this->_validatePassed = $func($fieldList, $data, $args, $errors);
        } else {
            $this->_validatePassed = true; // assume it passes unless we get errors
        }
        foreach ($fieldList as $f => $obj) {
            #dbg("Checking dataIsValid for f=$f");
            # some fields (a submit button, for instance, don't have validation)
            if (!$obj->dataIsValid($data)) {
                $this->_validatePassed = false;
                $errors = $obj->stackErrorMessages($errors);
            }
        }
        $this->_validateErrors = $errors;
        return $this->_validatePassed;
	}

    # must be called AFTER checkFieldValidation() - tells whether the last validation
    # was successfully passed or not
	public function passedFieldValidation() {
		return $this->_validatePassed;
	}

	public function getFormValidationErrors() {
		$validateErrors=$this->_validateObject->errors();
		$this->_validateObject=null;
		return $validateErrors;
	}

    # The save function is called immediately prior to the $db->update() or
    # the $db->insert() function.
    #   saveFunc($mode=[INSERT|UPDATE], $dbTable, $id, &$data, $args, &$errors, &$successes)
    # $dbTable contains the database table to be updated
    # If inserting then $id==null. For update it has the $id of the row to be updated.
    # $data can be a call by reference, changing the $data (fieldlist) that will
    # be saved.
    # $args is your own argument (use as a hash to pass multiple)
    # $errors and $successes are the usual - fill them up with messages
    # If it returns BaseForm::UPDATE_SUCCESS or BaseForm::UPDATE_NO_CHANGE or
    # BaseForm::UPDATE_ERROR then the Form object WILL NOT SAVE but will simply use
    # this result.
    # If it returns a negative number then the Form object will continue with the
    # $db->update() or $db->insert().
    # This facilitates replacing the save function entirely.
    # Set this up in the $opts arg of a Form object with:
    #   'savefunc' => 'mySaveFunc',
    #   'savefuncargs' => [ 'arg1' => 'val1', 'arg2' => 'val2' ]
    private function getSaveFunc() {
        return $this->_saveFunc;
    }
    public function setSaveFunc($func) {
        $this->_saveFunc = $func;
    }
    public function getSaveFuncArgs() {
        return $this->_saveFuncArgs;
    }
    public function setSaveFuncArgs($func) {
        $this->_saveFuncArgs = $func;
    }

    # The valid function is called immediately prior to engaging the validation
    # objects' Validate::check() function.
    #   validFunc($mode=[INSERT|UPDATE], $id, &$fieldList, &$data, $args, &$errors)
    # If inserting then $id==null (or just ignore it).
    # $fieldList and/or $data can be a pass by reference, changing them as needed.
    # $fieldList is the list of fields [[$fieldName => $fieldObject],[...]...] which
    #   will be traversed to call $fieldObject->dataIsValid($data) on it.
    # $data is the actual data being passed to $fieldObject->dataIsValid($data)
    # $args is your own argument (use as a hash to pass multiple)
    # $errors is the usual - fill it up with messages
    # Set this up in the $opts arg of a Form object with:
    #   'validfunc' => 'myValidFunc',
    #   'validfuncargs' => [ 'arg1' => 'val1', 'arg2' => 'val2' ]
    # The function should return TRUE/FALSE depending on whether the validation
    # was successful. No save will occur if it returns FALSE.
    public function getValidFunc() {
        return $this->_validationFunc;
    }
    public function setValidFunc($func) {
        $this->_validationFunc = $func;
    }
    public function getValidFuncArgs() {
        return $this->_validationFuncArgs;
    }
    public function setValidFuncArgs($func) {
        $this->_validationFuncArgs = $func;
    }
    public function getSubDbTables() {
        return $this->_subDbTables;
    }
    public function setSubDbTables($val) {
        $this->_subDbTables = $val;
    }
}

# This makes it possible for the Form class to be slightly special
abstract class US_Form extends US_BaseForm {
}
