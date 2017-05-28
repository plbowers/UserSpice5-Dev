<?php
/*
 * Class inheritance related to Form Fields:
 * The rule with UserSpice is "Do NOT modify files under core/ - make the
 * change under local/ instead." For these Form Fields the way to do that is to
 * use the classes defined in local/Classes/FormFieldTypes.php and to make
 * modifications only to that file and local/Classes/FormField.php.
 *
 * core/Classes/FormField.php (this) defines class "US_FormField" which is
 * just the parent class. This class is abstract. Do not modify this file.
 *
 * local/Classes/FormField.php in turn defines class "FormField" which inherits
 * from class "US_FormField". Feel free to make changes to
 * local/Classes/FormField.php.
 *
 * core/Classes/FormFieldTypes.php in turn defines several classes such as
 * "US_FormField_Text", "US_FormField_Button", "US_FormField_Hidden", etc. which
 * inherit from class "FormField". Do not modify this file.
 *
 * local/Classes/FormFieldTypes.php in turn defines several classes such as
 * "FormField_Text", "FormField_Button", "FormField_Hidden", etc. which inherit
 * from the classes mentioned above which are named the same but with a "US_"
 * prefix. THESE ARE THE CLASSES YOU SHOULD USE IN YOUR CODE TO DEFINE FORM
 * FIELDS! Feel free to modify local/Classes/FormFieldTypes.php.
 */
abstract class US_FormField extends Element {
    protected $_validateObject=null,
        $_fieldName=null,
        $_dbFieldName=null,
        $_fieldId='',
        $_fieldLabel='',
        $_fieldPlaceholder=null,
        $_fieldValue=null,
        $_fieldNewValue=null,
        $_fieldType=null, // should be set by inheriting classes
        $_isDBField=true, // whether this is from the primary table in the DB
        $_postFunc=null,
        $_postFuncArgs=[],
        $_dataFunc=null,
        $_dataFuncArgs=[],
        # for repeating elements, this class can control the SELECT
        # rather than setting it manually by setRepData(). This allows
        # things like pagination,
        $_sql='',  // entire SQL SELECT statement (not available if paginating - use cols/from/where)
        $_sqlCols='',  // SELECT <this part here>
        $_sqlFrom='',  //   FROM <this part here>
        $_sqlWhere='', //  WHERE <this part here>
        $_sqlGroup='', //  <any GROUP BY or HAVING or etc>
        $_sqlOrder='', //  ORDER BY <this part here>
        $_sqlBindVals=[],//bind values matching ? or :var in sqlWhere
        $_pageItems=0, // paginates if non-zero and isRepeating() (i.e., this is the flag to turn on paginating)
        $_curPage=0,   // current page
        $_pageVarName='page',
        $_importSource='',
        $_importField='',
        $_initOpts=[],
        $_processor=[],
        $_totalPages=0;// total number of pages
    public $repEmptyAlternateReplacesAll = true;
    public
        $HTML_Pre = '
            <div class="{DIV_CLASS} {EXTRA_OUTER_CLASS}" id="outer-{FIELD_ID}" > <!-- type={TYPE} id={FIELD_ID} name={FIELD_NAME} -->
              <label class="{LABEL_CLASS}" for="{FIELD_ID}" {ON_CLICK}>{LABEL_TEXT}
              <span class="{HINT_CLASS}" title="{HINT_TEXT}"></span></label>
              <br />',
        $HTML_Input = '
              <input class="{INPUT_CLASS}" type="{TYPE}" id="{FIELD_ID}" '
            .'name="{FIELD_NAME}" placeholder="{PLACEHOLDER}" value="{VALUE}" '
            .'{REQUIRED_ATTRIB} {EXTRA_ATTRIB} {DISABLED} {READONLY}>',
        $HTML_Post = '
            </div> <!-- {DIV_CLASS} (type={TYPE} id={FIELD_ID}, name={FIELD_NAME}) -->',
        $HTML_Page_Index = '<a href="?{PAGE_VAR_NAME}={PAGE_NUM}">{PAGE_NUM}</a>&nbsp;',
        $HTML_CurPage_Index = '<strong>{PAGE_NUM}</strong>&nbsp;',
        $elementList = ['Pre', 'Input', 'Post'];
    public
        $MACRO_Div_Class = 'form-group',
        $MACRO_Extra_Outer_Class = '',
        $MACRO_Label_Class = 'control-label',
        $MACRO_Label_Text = '',
        $MACRO_Input_Class = 'form-control',
        $MACRO_Required_Class = 'fa fa-asterisk',
        $MACRO_Hint_Class = '',
        $MACRO_Hint_Class_Not_Required = 'fa fa-info-circle',
        $MACRO_Hint_Class_Required = 'fa fa-asterisk',
        $MACRO_Hint_Text = '',
        $MACRO_TH_Class = '',
        $MACRO_Placeholder = '',
        $MACRO_Extra_Attrib = '',
        $MACRO_Value = '',
        $MACRO_Disabled = '',
        $MACRO_Readonly = '',
        $MACRO_Page_Index = '',
        $MACRO_On_Click = '';

    public function __construct($opts=[], $processor=[]) {
        // We cannot do our initialization here because we need the name of the field
        // which is passed in from the parent Form
        $this->_initOpts = $opts;
        $this->_processor = $processor;
    }
    public function initFormField($myIdx='') {
        $opts = $this->_initOpts;
        global $T;
        #dbg("initFormField($myIdx): Entering, opts=");
        #pre_r($opts);
        if ($dbField = @$opts['dbfield']) {
            if ($alias = @$opts['field']) {
                $alias = $opts['field'];
            } elseif (!is_numeric($myIdx)) {
                $alias = $myIdx;
            } else {
                $alias = $dbField;
            }
            #dbg("initFormField(): before myIdx=$myIdx, alias=$alias, dbField=$dbField");
            $field_def = $this->useFieldDef($alias, $dbField); // $alias may be altered by 'alias' option
            #dbg("initFormField(): after alias=$alias");
            unset($opts['dbfield']); // don't need anymore
            unset($opts['field']);   // don't need anymore
        } elseif ($alias = @$opts['field']) { // specifying 'field' is like alias
            $this->setFieldName($alias);
            unset($opts['field']); // don't need anymore
            $field_def = []; // no field-def to work with
        } else { // gotta be both alias and dbname was specified by index
            $alias = $dbName = $myIdx; // the index to the array element will also be the field name and dbName
            $field_def = $this->useFieldDef($alias, $dbName); // $alias may be altered by alias
            #$this->initElement($alias);
        }
        if (is_null($this->getPlaceholder())) {
            $this->setPlaceholder($this->getFieldLabel());
        }
        if ($this->_processor && !isset($this->_processor['idfield'])) {
            $this->_processor['idfield'] = $fn;
        }
        parent::__construct($opts); // misc plus handleOpts()
        # Now handle what we found in $field_def, but don't let
        # values there override what was passed in $opts
        if (isset($opts['display']) || isset($opts['display_lang'])) {
            unset($field_def['display']);
            unset($field_def['display_lang']);
        }
        # This handles only opts that are in $field_def but not in $opts
        # (since $opts was already handled above in the parent::__construct($opts) call)
        $this->handleOpts(array_diff_key((array)$field_def, $opts));
        return $alias;
    }

    public function handle1Opt($name, &$val) {
        $this->debug(2, "::(FormField::)handle1Opt($name, ".print_r($val,true).")");
        switch (strtolower(str_replace('_', '', $name))) {
            case 'displaylang':
            case 'displaytoken':
                $val = lang($val);
                # NOTE: No break/return - falling through to 'display' with $val set
            case 'display':
                # NOTE: We could be falling through from above with no break/return
                $this->setFieldLabel($val);
                $this->setMacro('Label_Text', $val);
                return true;
            case 'value':
                $this->setFieldValue($val);
                return true;
            case 'newvalid':
            case 'newvalidate':
            case 'valid':
            case 'validate':
            case 'validateobject':
                $this->setValidator($val);
                return true;
            case 'isdbfield':
                $this->setIsDBField($val);
                return true;
            case 'placeholder':
                $this->setPlaceholder($val);
                return true;
            case 'extra':
                $this->setMacro('Extra_Attrib', $val);
                return true;
            case 'fieldid':
                $this->setFieldId($val);
                return true;
            case 'hideable':
                $this->setMacro('On_Click', 'onclick="$(\'.hide_{FIELD_ID}\').toggle();"');
                return true;
            case 'hidden':
                $this->HTML_Scripts[] = '<script type="text/javascript"> $(".hide_{FIELD_ID}").hide(); </script>';
                return true;
            case 'postfunc':
            case 'postfunction':
                $this->setPostFunc($val);
                return true;
            case 'postfuncargs':
            case 'postfunctionargs':
                $this->setPostFuncArgs($val);
                return true;
            case 'datafunc':
            case 'datafunction':
                $this->setDataFunc($val);
                return true;
            case 'datafuncargs':
            case 'datafunctionargs':
                $this->setDataFuncArgs($val);
                return true;
            case 'sql':
                $this->setSQL($val);
                return true;
            case 'sqlcols':
                $this->setSQLCols($val);
                return true;
            case 'sqlfrom':
                $this->setSQLFrom($val);
                return true;
            case 'sqlwhere':
                $this->setSQLWhere($val);
                return true;
            case 'sqlgroup':
                $this->setSQLGroup($val);
                return true;
            case 'sqlorder':
                $this->setSQLOrder($val);
                return true;
            case 'bindvals':
            case 'sqlbindvals':
                $this->setSQLBindVals($val);
                return true;
            case 'pageitems':
                $this->setPageItems($val);
                return true;
            case 'pagevarname':
                $this->setPageVarName($val);
                return true;
            case 'importfield':
                $this->setImportField($val);
                return true;
            case 'importfile':
            case 'importsource':
                $this->setImportSource($val);
                return true;
            case 'extraclass':
                $this->setMacro('Extra_Outer_Class', $val);
                return true;
        }
        return parent::handle1Opt($name, $val);
    }

    public function useFieldDef(&$fn, $dbFieldName) {
        $db = DB::getInstance();
        $field_def = $db->queryAll("field_defs", [ 'name' => $fn ])->first(true);
        if (!$dbFieldName) {
            $dbFieldName = $fn;
        }
        if ($field_def && $field_def['alias']) {
            $fn = $field_def['alias'];
            #dbg("useFieldDef(): $fn, $dbFieldName");
        }
        $this->setDBFieldName($dbFieldName);
        $this->setFieldName($fn);
        return $field_def;
    }

    public function getMacros($s, $opts) {
        $this->MACRO_Type = $this->getFieldType();
        $this->MACRO_Field_Name = $this->getFieldName();
        $this->MACRO_Field_ID = $this->getFieldId();
        $this->MACRO_Label_Text = $this->getFieldLabel();
        $this->MACRO_Value = $this->getFieldValue();
        $this->MACRO_Required_Attrib = ($this->getRequired() ? 'required' : '');
        if (!$this->MACRO_Hint_Text && $this->hasValidation()) {
            $this->MACRO_Hint_Text = $this->getValidator()->describe($this->getFieldName());
        }
        $this->MACRO_Hint_Class = $this->getHintClass();
        return parent::getMacros($s, $opts);
    }

    public function calcRepData($recalc=false) {
        static $cnt=1;
        $this->debug(1, '::calcRepData(): Entering ('.$this->getFieldName().')');
        if (!$this->isRepeating() || (!$this->repDataIsEmpty() && !$recalc)) {
            # If it's not a repeating-data field or if the repeating data already
            # has something in it then get out...
            return false;
        }
        $this->debug(2, '::calcRepData(): Continuing '.$this->getFieldName().', count='.$cnt++);
        $rtn = false;
        $repData = [];
        $setRep = false;
        if ($func = $this->getDataFunc()) {
            $setRep = true;
            $repData = $func($this->getDataFuncArgs());
        } elseif ($this->getSQL() || $this->getSQLCols() || $this->getSQLFrom()) {
            $setRep = true;
            $repData = $this->calcRepDataFromSQL();
        } elseif ($this->getImportSource() || $this->getImportField()) {
            $setRep = true;
            $repData = $this->calcRepDataFromImport();
        }
        if ($setRep) {
            $this->setRepData($repData);
            if ($func = $this->getPostFunc()) {
                $func($this->repData, $this->getPostFuncArgs());
            }
        }
        return (boolean)$this->getRepData();
    }
    public function calcRepDataFromImport() {
        $this->debug(2, "calcRepDataFromImport(): Entering");
        if ($fn = $this->getImportField()) {
            if ($uploadFld = $this->getElementRef($fn)) {
                $fileName = $uploadFld->getFieldValue();
            } else {
                return false;
            }
        } else {
            return false;
        }
        if (!$fileName) {
            return false;
        }
        /*
        $importSource = $this->getImportSource();
        if (!$importSource || !file_exists($importSource)) {
            return false;
        }
        */
        $fh = fopen($fileName, "r");
        $headers = fgetcsv($fh, 1024);
        #var_dump($headers);
        $rtn = [];
        $lineNum = 1;
        while ($line = fgetcsv($fh, 4096)) {
            $lineNum++;
            if (sizeof($headers) != sizeof($line)) {
                $this->errors[] = "Import error: ".sizeof($headers)." fields in headers and ".sizeof($line)." fields in line #$lineNum";
                continue;
            }
            for ($i=0; $i<sizeof($headers); $i++) {
                $newRow[$headers[$i]] = $line[$i];
            }
            $rtn[] = $newRow;
        }
        #dbg("RepData: ".print_r($this->getRepData(),true));
        return $rtn;
    }
    public function calcRepDataFromSQL() {
        $this->debug(2, "calcRepDataFromSQL(): Entering");
        $fullSql = $this->getSQL();
        $cols = $this->getSQLCols();
        $from = $this->getSQLFrom();
        // these are required elements - use DUAL in the (unlikely) case
        // where you don't have a FROM table; use 1=1 in the case where
        // you don't have a WHERE clause
        if (!$fullSql && (!$cols || !$from)) {
            if ($cols || $from) {
                dbg("ERROR: Must specify at least cols and from");
            }
            return false;
        }
        $where = $this->getSQLWhere();
        if ($where) {
            $where = "WHERE $where";
        }
        $groupBy = $this->getSQLGroup();
        $order = $this->getSQLOrder();
        $bindVals = $this->getSQLBindVals();
        $pageItems = $this->getPageItems();
        $this->debug(5,"fullSql=$fullSql");
        $this->debug(5,"cols=$cols");
        $this->debug(5,"from=$from");
        $this->debug(5,"groupBy=$groupBy");
        $this->debug(5,"where=$where");
        $this->debug(2, '::calcRepDataFromSQL(): Still Continuing 2');
        if ($pageItems) {
            if ($fullSql) {
                dbg("FATAL ERROR: Cannot do pagination specifying straight SQL");
                return false;
            }
            if ($this->getCurPage() < 1) {
                #dbg('varname='.$this->getPageVarName());
                if (!$cur = Input::get($this->getPageVarName())) {
                    $cur = 1;
                }
                $this->setCurPage($cur);
            }
            // paginating
            // calculate total number of pages
            $sql = "SELECT COUNT(*) AS c FROM $from $groupBy $where";
            $result = $this->_db->query($sql, $bindVals)->first();
            $totalPages = ceil($result->c / $pageItems);
            $this->setTotalPages($totalPages);
            $curPage = $this->getCurPage();
            if ($curPage > $totalPages) {
                $curPage = $totalPages;
            }
            $offset = max(0, ($curPage - 1)) * $pageItems;
            $this->setPageIndex();
        }
        if ($fullSql) {
            $sql = $fullSql;
        } else {
            $sql = "SELECT $cols FROM $from $groupBy $where";
            if ($order) {
                $sql .= " ORDER BY $order";
            }
            if ($pageItems) { // paginating
                $sql .= " LIMIT $offset,$pageItems";
            }
        }
        $this->debug(3, "::calcRepDataFromSQL(): sql=$sql");
        $rtn = $this->_db->query($sql, $bindVals)->results();
        #pre_r($rtn);
        if ($this->_db->error()) {
            $this->errors[] = lang('SQL_ERROR');
            $this->errors[] = $this->_db->errorString();
        }
        #dbg("ERROR STRING: ".$this->_db->errorString());
        return $rtn;
    }
    public function setRepData($val) {
        $this->debug(1, '::setRepData(y): Entering');
        # convert from object ($data->id, $data->name) to associative array
        # ($data['id'], $data['name']) if needed
        #var_dump($val);
        if (sizeof($val)>0 && is_object($val[0])) {
            $tmp = [];
            foreach ((array)$val as $k=>$o) {
                $tmp[$k] = (array)$o;
            }
            $this->repData = $tmp;
        } else {
            $this->repData = $val;
        }
        #dbg("setRepData(): values follow (class=".get_class($this).")");
        #var_dump($this->repData);
        $this->debug(0, '::setRepData(): '.print_r($this->repData,true));
        return (boolean)$this->repData;
    }
    public function describeValidation() {
        return $this->getValidator()->describe($this->getFieldName());
    }
    public function getHintClass() {
        if ($this->getRequired()) {
            return $this->MACRO_Hint_Class_Required;
        } elseif ($this->MACRO_Hint_Text) {
            return $this->MACRO_Hint_Class_Not_Required;
        } else {
            return '';
        }
    }

    // the key to the __construct hash handed to the field list (1st arg)
    // can initialize both the field name and the display labels
    public function initElement(&$k, $parentFormObj, $mainFormObj) {
        $this->debug(1, "::initElement($k): Entering");
        parent::initElement($k, $parentFormObj, $mainFormObj);
        #dbg("Back from parent::initElement - k=$k");
        $k = $this->initFormField($k); // handle (late) initialization
        $langKey = strtoupper($k);
        if (hasLang($langKey)) {
            $prettyText = lang($langKey);
        } else {
            $prettyText = ucwords(str_replace('_', ' ', $k));
        }
        if (!$this->getFieldLabel()) {
            $this->setFieldLabel($prettyText);
        }
        if (!$this->getMacro('Label_Text')) {
            $this->setMacro('Label_Text', $prettyText);
        }
        return $this->getFieldName();
    }
    public function isChanged() {
        return ($this->_fieldNewValue != $this->_fieldValue);
    }
    public function getProcessor() {
        return $this->_processor;
    }
    public function getImportField() {
        #dbg("getImportField(): Returning: ".$this->_importField);
        return $this->_importField;
    }
    public function setImportField($val) {
        $this->_importField = $val;
        $this->elementRefs[$val] = null; // "request" a reference to this field from the initElement() function in the parent form
    }
    public function getImportSource() {
        #dbg("getImportSource(): Returning: ".$this->_importSource);
        return $this->_importSource;
    }
    public function setImportSource($val) {
        $this->_importSource = $val;
    }
	public function getPostFunc(){
		return $this->_postFunc;
	}
	public function setPostFunc($val){
		$this->_postFunc = $val;
	}
	public function getPostFuncArgs(){
		return $this->_postFuncArgs;
	}
	public function setPostFuncArgs($val){
		$this->_postFuncArgs = $val;
	}
	public function getDataFunc(){
		return $this->_dataFunc;
	}
	public function setDataFunc($val){
		$this->_dataFunc = $val;
	}
	public function getDataFuncArgs(){
		return $this->_dataFuncArgs;
	}
	public function setDataFuncArgs($val){
		$this->_dataFuncArgs = $val;
	}
	public function getSQL(){
		return $this->_sql;
	}
	public function setSQL($val){
		$this->_sql = $val;
	}
	public function getSQLCols(){
        if (is_array($this->_sqlCols)) {
            return implode(',', $this->_sqlCols);
        }
		return $this->_sqlCols;
	}
	public function setSQLCols($val){
		$this->_sqlCols = $val;
	}
	public function getSQLFrom(){
		return $this->_sqlFrom;
	}
	public function setSQLFrom($val){
		$this->_sqlFrom = $val;
	}
	public function getSQLWhere(){
		return $this->_sqlWhere;
	}
	public function setSQLWhere($val){
		$this->_sqlWhere = $val;
	}
	public function getSQLGroup(){
		return $this->_sqlGroup;
	}
	public function setSQLGroup($val){
		$this->_sqlGroup = $val;
	}
	public function getSQLOrder(){
		return $this->_sqlOrder;
	}
	public function setSQLOrder($val){
		$this->_sqlOrder = $val;
	}
	public function getSQLBindVals(){
		return $this->_sqlBindVals;
	}
	public function setSQLBindVals($val){
		$this->_sqlBindVals = $val;
	}
	public function getPageItems(){
		return $this->_pageItems;
	}
	public function setPageItems($val){
		$this->_pageItems = $val;
	}
	public function getCurPage(){
		return $this->_curPage;
	}
	public function setCurPage($val){
		$this->_curPage = $val;
	}
	public function getPageVarName(){
		return $this->_pageVarName;
	}
	public function setPageVarName($val){
		$this->_pageVarName = $val;
	}
	public function getTotalPages(){
		return $this->_totalPages;
	}
	public function setTotalPages($val){
		$this->_totalPages = $val;
	}
	public function setPageIndex(){
		$this->MACRO_Page_Index = '';
        #dbg("curpage=".$this->getCurPage().', totalpages='.$this->getTotalPages());
        if ($this->getTotalPages() > 1) { // don't bother printing links if just 1
            $macros = ['{PAGE_VAR_NAME}'=>$this->getPageVarName(), '{LAST_PAGE_NUM}'=>$this->getTotalPages()];
            for ($i = max(1, $this->getCurPage()-5); $i <= min($this->getTotalPages(), $this->getCurPage()+5); $i++) {
                #dbg("Processing $i");
                $macros['{PAGE_NUM}'] = $i;
                if ($i == $this->getCurPage()) {
                    $this->MACRO_Page_Index .= str_replace(array_keys($macros), array_values($macros), $this->HTML_CurPage_Index);
                } else {
                    $this->MACRO_Page_Index .= str_replace(array_keys($macros), array_values($macros), $this->HTML_Page_Index);
                }
            }
        }
	}
	public function getPlaceholder(){
		return $this->MACRO_Placeholder;
	}
	public function setPlaceholder($placeholder){
		$this->MACRO_Placeholder = $placeholder;
	}
    # Does the validation for this field say it is a required field?
	public function getRequired() {
        if ($valid = $this->getValidator()) {
            return $valid->getRequired($this->getFieldName());
        } else {
            return false;
        }
	}
	public function setRequired($v){
        $this->debug(2, "::setRequired($v) - Entering");
        if ($valid = $this->getValidator()) {
            #dbg("setRequired(): Before setRequired fieldname=".$this->getFieldName().", dbfieldname=".$this->getDBFieldName().", valid=");
            #var_dump($valid);
            $valid->setRequired($this->getFieldName(), $v);
            #dbg("setRequired(): After setRequired valid=");
            #var_dump($valid);
        } else {
            throw new Exception("No validation. Cannot set `required` for field ".$this->getFieldName().".");
        }
	}
    # <input ... id="THIS-IS-FIELD-ID" ...>
    public function setFieldId($id) {
        $this->_fieldId = $id;
    }
    public function getFieldId() {
        # Often developers will not specify the ID since they will just
        # want it to be the same as the field name. Thus the short-cut.
        if (!empty($this->_fieldId)) {
            return $this->_fieldId;
        } else {
            return $this->getFieldName();
        }
    }
    public function setFieldLabel($label) {
        $this->_fieldLabel = $label;
    }
    public function getFieldLabel() {
        return $this->_fieldLabel;
    }
    public function setIsDBField($isdb) {
        $this->_isDBField = $isdb;
    }
    public function getIsDBField() {
        return $this->_isDBField;
    }
    public function setDBFieldName($fn) {
        $this->_dbFieldName = $fn;
    }
    public function getDBFieldName() {
        return $this->_dbFieldName;
    }
    public function setFieldName($fn) {
        $this->_fieldName = $fn;
    }
    public function getFieldName() {
        return $this->_fieldName;
    }
    public function setFieldType($type) {
        $this->_fieldType = $type;
    }
    public function getFieldType() {
        return $this->_fieldType;
    }
    public function setFieldValue($value) {
        $this->_fieldValue = $value;
    }
    public function getFieldValue() {
        return $this->_fieldValue;
    }
    public function getNewValue() {
        return $this->_fieldNewValue;
    }
    public function setNewValue($val) {
        $this->_fieldNewValue = Input::sanitize($val);
    }

    # methods related to validation
    public function setValidator($v) {
        $this->_validateObject = $v;
    }
    // if construct used array rather than object then convert to
    // object. Intentionally late conversion because we need the
    // (possibly late defaulted) values for display and field name
    public function fixValidator() {
        $v = $this->_validateObject;
        if (!isset($v['display']) && ($d = $this->getFieldLabel())) {
            $v['display'] = $d;
        }
        $args = [$this->getDBFieldName() => $v];
        $this->_validateObject = new Validate($args);
    }
    public function hasValidation() {
        return (boolean)$this->_validateObject;
    }
    public function getValidator($createIfNeeded=true) {
        #dbg('getValidator(): '.$this->getDBFieldName().' starting with validateObj');
        #var_dump($this->_validateObject);
        if ($createIfNeeded && !$this->_validateObject) {
            #dbg("getValidator() creating");
            $args = [$this->getDBFieldName() => []];
            $this->setValidator(new Validate($args));
        } else {
            if (is_array($this->_validateObject)) {
                #dbg("getValidator() fixing");
                $this->fixValidator(); // fix (late) if needed
            }
        }
        #dbg('getValidator(): '.$this->getDBFieldName().' returning validateObj');
        #var_dump($this->_validateObject);
        return $this->_validateObject;
    }

    public function setDisabled($val) {
        if ($val) {
            $this->MACRO_Disabled = 'disabled';
        } else {
            $this->MACRO_Disabled = '';
        }
    }
    public function setReadonly($val) {
        if ($val) {
            $this->MACRO_Readonly = 'readonly';
        } else {
            $this->MACRO_Readonly = '';
        }
    }

    #
    # these methods are simply "pass-through" to the validate object
    #
    public function dataIsValid($data) {
        if ($this->hasValidation()) {
            if (!$data) {
                $data = $this->_fieldNewValue;
            }
            return $this->getValidator()->check($data)->passed();
        } else {
            return true; // if no validation then it cannot fail
        }
    }
    public function stackErrorMessages($errors) {
        if ($this->hasValidation()) {
            return $this->getValidator()->stackErrorMessages($errors);
        } else {
            return $errors;
        }
    }

    public function getHTML($opts=[]) {
        #dbg("FormField::getHTML() Entering, calling calcRepData()");
        #$this->calcRepData();
        return parent::getHTML($opts);
    }
    // if an inheriting class needs to adjust the snippets
    // they can do it by setting any of ...
    // ... setting HTMLPre, HTMLInput, HTMLPost directly
    // ... or by overriding this function to do something else
    public function fixSnippets() {
    }
}
