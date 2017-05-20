<?php
/*
 * US_Element (becomes Element in local/Classes) is the base class
 * for all Form and FormField classes.
 *
 * It is designed to allow HTML elements (including nested elements)
 * to be generated dynamically.
 *
 * Properties:
 * $elementList
 *   This array contains the list of elements which will be used to generate
 *   the full HTML returned. Strings in this array will be prefixed with 'HTML_'
 *   and then accessed, so see "$HTML_*" below.
 *   Examples:
 *     For a form:
 *       ['Header', 'OpenContainer', 'OpenRow', 'OpenCol', 'OpenForm', ...]
 *       (see Form.php for an actual example)
 *     For a select field:
 *       ['openSelect', 'options', 'closeSelect']
 *       (see FormField_Select.php for an actual example)
 * $HTML_*
 *   Properties named with this prefix will be used as HTML templates
 *   (strings with macros which get replaced with actual values in order
 *   to generate HTML)
 * $MACRO_*
 *   Properties named with this prefix will be used as macro replacement
 *   values. For instance $MACRO_foo='bar' means that every occurrence
 *   of '{FOO}' in a $HTML_* template will be replaced with 'bar'
 *   Note that text to be replaced ({FOO}) is case insensitive but
 *   uppercase by convention.
 *
 * THESE PROPERTIES ARE FOR USE WITH REPEATING ELEMENTS:
 * $repElement
 *   When a given element must repeat n times (n=number of rows in
 *   $repData [described below]), this property is set to the name
 *   of that element (INCLUDING the HTML_ prefix if appropriate!)
 *   Example:
 *     For a form:
 *       'fields' - $x->getHMTL() will be called on each row in $repData
 *     For a select field:
 *       'options' - $HTML_Options will be repeated for each row in $repData
 * $repData
 *   When a given element must repeat n times, the data to create
 *   that repetition is stored in this property. It can be initialized as an
 *   array of either objects ($row[0]->id) or of assoc array ($row[0]['id'])
 *   but will be converted to an assoc array internally.
 * $repMacroAliases
 *   Sometimes it is convenient to have aliases based on the position within
 *   the record of $repData. For instance, {OPTION_VALUE} and {OPTION_LABEL}
 *   rather than having to adjust the macro for {ID} and {NAME} one time and
 *   {ID} and {SHORT_NAME} the next time. The values of this array will become
 *   aliases for the first n fields within the records of $repData.
 * $HTML_RepEmptyAlternate
 *   If a repeating element has no data, this value will be used as the
 *   alternate.
 * $repEmptyAlternateReplacesAll
 *   In some cases (forms) the $HTML_RepEmptyAlternate should replace just
 *   the $repElement (set to false) whereas in other cases (fields) it should
 *   replace ALL elements (set to true).
 */
abstract class US_Element {
    protected $_db=null,
        $_deleteMe=false,
        $_deleteIfEmpty=false,
        $_dbTable=null, // used for form processing, editing the DB
        #$_parentFormObj=null, # DO NOT UNCOMMENT THIS - CIRCULAR/RECURSIVE REFS KILLS PERFORMANCE
        #$_mainFormObj=null, # DO NOT UNCOMMENT THIS - CIRCULAR/RECURSIVE REFS KILLS PERFORMANCE
        $errors=[],
        $successes=[];
    public $debug = -1;
    public $elementList = [];
    public $elementRefs = []; // provides limited field/element lookups (circular/recursive references but of small areas of the tree)
    public $repElement = null; // name of repeating element from $elementList ('fields' for Form)
    public $repData = [];
    public $repMacroAliases = [];
    public $repEmptyAlternateReplacesAll = false;
    public $HTML_RepEmptyAlternate = '',
        $HTML_Scripts = [];
    # public $HTML_x = '<input type="{TYPE}" name="{NAME}"' ... />';
    # public $MACRO_type = 'hidden';
    public function __construct($opts=[], $handleOpts=true) {
        $this->debug(1, '::__construct(): Entering');
        $this->_db = DB::getInstance();
        # This is a strange, non-OOP way of mixing globals and properties, but UserSpice
        # has to work with 3rd-party developers who may or may not use the `Form` class.
        # Therefore we have to work with a global $errors and $successes without tripping.
        if (isset($GLOBALS['errors'])) {
            $this->errors = &$GLOBALS['errors'];
        }
        if (isset($GLOBALS['successes'])) {
            $this->successes = &$GLOBALS['successes'];
        }
        $this->fixElementList($opts);
        if ($handleOpts) {
            $this->handleOpts($opts);
        }
    }
    # These particular options must be applied early to fix the contents of
    # $this->elementList so that we can choose the top Form object based on
    # the presence/absence of the 'Header' element
    public function fixElementList(&$opts) {
        if (isset($opts['elements'])) {
            $this->setElementList($opts['elements']);
            unset($opts['elements']);
        }
        if ($del = preg_grep('/^exclude[-_]elements?$/', array_keys($opts))) {
            foreach ($del as $d) {
                $this->deleteElements($opts[$d]);
                unset($opts[$d]);
            }
        }
    }
    public function handleOpts($opts) {
        $this->debug(1, '::handleOpts(): Entering');
        #if (!is_array($opts)) var_dump($opts)   ;
        if ($reps = $this->getRepDataOpt($opts)) {
            $this->setRepData($reps);
        }
        foreach ($opts as $k=>&$v) {
            if (!$this->handle1Opt($k, $v) && configGet('debug_mode')) {
                if (!in_array($k, ['id', 'name', 'alias', 'min_val', 'max_val', 'min', 'max', 'unique_in_table', 'match_field', 'is_numeric', 'valid_email', 'regex', 'regex_display', 'keep_if', 'nodata', $this->repElement, ])) {
                    dbg("Unknown option $k for class=".get_class($this)." (value=".print_r($v, true).")");
                }
            }
        }
    }
    // when this method is overridden you probably want to call
    // parent::handle1Opt() to get the benefit of parent option handling
    public function handle1Opt($name, &$val) {
        switch (strtolower(str_replace('_', '', $name))) {
            case 'table':
            case 'dbtable':
                $this->setDBTable($val);
                return true;
            case 'debug':
                $this->debug = $val;
                return true;
            case 'keepif':
                $val = !$val;
                # NOTE: No break/return - falling through to 'deleteif'
            case 'deleteif':
                # NOTE: We could be falling through from above with no break/return
                $this->setDeleteMe($val);
                return true;
            case 'deleteifempty':
                $this->setDeleteIfEmpty($val);
                return true;
            case 'errors':
                $this->errors = &$val;
                return true;
            case 'successes':
                $this->successes = &$val;
                return true;
            case 'repemptyalternate':
            case 'nodata':
                $this->setRepEmptyAlternate($val);
                return true;
            case 'script':
                $this->HTML_Scripts[] = $val;
                return true;
        }
        $setMethod = 'set'.$name;
        $caseName = $this->fixCase($name);
        $propHTML = 'HTML_'.$caseName;
        $propMacro = 'MACRO_'.$caseName;
        $this->debug(3, "::handle1Opt(): name=$name");
#        dbg("propMacro=$propMacro");
        if (method_exists($this, $setMethod)) {
            $this->debug(3, "::handle1Opt(): METHOD: $setMethod, val=$val");
            $this->$setMethod($val);
            return true;
        } elseif (property_exists($this, $propHTML)) {
            $this->debug(3, "::handle1Opt(): PROP HTML: $propHTML");
            $this->$propHTML = $val;
            return true;
        } elseif (property_exists($this, $propMacro)) {
            $this->debug(3, "::handle1Opt(): PROP MACRO: $propMacro, val=$val");
            $this->$propMacro = $val;
            return true;
#        } elseif ($propMacro == 'MACRO_Rows') {
#            var_dump($this);
        }
        return false;
    }
    protected function getRepDataOpt(&$opts) {
        $repDataNames = ['data', 'repData', 'repeat', 'repeats', 'repeatvalue'];
        if ($this->getRepElement()) {
            $repDataNames[] = $this->getRepElement(); // i.e., 'fields'
            if (substr($this->getRepElement(), 0, strlen('HTML_')) == 'HTML_') {
                $repDataNames[] = substr($this->getRepElement(), strlen('HTML_'));
            }
        }
        foreach ($repDataNames as $i) {
            if (isset($opts[$i])) {
                $rtn = $opts[$i];
                unset($opts[$i]); // don't need anymore
                return $rtn;
            }
        }
    }
    public function getHTML($opts=[]) {
        $this->debug(1, '::getHTML(): Entering');
        $elementList = $this->getElementList($opts);
        $html = '';
        if ($this->isRepeating() && $this->getRepEmptyAlternateReplacesAll() && $this->repDataIsEmpty(true)) {
            $this->debug(2, '::getHTML(): calling getRepEmptyAlternate()');
            $html = $this->getRepEmptyAlternate();
        } else {
            #dbg("Before loop in getHTML");
            foreach ((array)$elementList as $e) {
                #dbg(substr($this->getHTMLElement($e, $opts), 0, 30));
                $this->debug(2, "::getHTML(): e=$e, calling getHTMLElement()");
                $html .= $this->getHTMLElement($e, $opts);
                #echo "\n\n===$e (".get_class($this).")===\n\n$html\n\n";
            }
            #dbg("After loop in getHTML");
            #die();
        }
        $this->debug(3, htmlentities($html));
        $macros = $this->getMacros($html, $opts);
        #pre_r($macros);
        return $this->processMacros($macros, $html, $opts);
    }
    public function getElementList($opts=[]) {
        if (isset($opts['elements'])) {
            return $opts['elements'];
        } elseif (isset($opts['exclude_elements'])) {
            return array_diff($this->elementList, $opts['exclude_elements']);
        } else {
            return $this->elementList;
        }
    }
    public function getHTMLScripts($opts=[]) {
        #dbg("::getHTMLScripts - ".$this->HTML_Scripts);
        $html = $this->HTML_Scripts;
        $macros = $this->getMacros($html, $opts);
        return $this->processMacros($macros, $html, $opts);
    }
    public function deleteElements($elems) {
        $this->elementList = array_diff($this->elementList, (array)$elems);
    }
    public function setElementList($elementList) {
        $this->elementList = $elementList;
    }
    public function getHTMLElement($element, $opts) {
        $this->debug(1, "::getHTMLElement('$element', \$opts): Entering");
        #dbg("getHTMLElement($element, \$opts) -->".get_class($this));
        if (is_string($element)) {
            $methodName = 'getHTML'.$element;
            $propName = 'HTML_'.$element;
            $prop2Name = $element;
            if (method_exists($this, $methodName)) {
                #dbg("getHTMLElement(): Method $methodName");
                $html = $this->$methodName($opts);
            } elseif (in_array($this->getRepElement(), [$propName, $prop2Name])) {
                # repeating element
                #dbg("getHTMLElement(): repeating element");
                $propName = $this->getRepElement();
                #dbg("getHTMLElement(): repeating element prop=$propName");
                $elem = isset($this->$propName) ? $this->$propName : $propName;
                #dbg("getHTMLElement(): repeating element elem=$elem");
                $html = $this->getHTMLRepElement($elem, $opts);
            } elseif (isset($this->$propName) || isset($this->$prop2Name)) {
                #dbg("getHTMLElement(): Prop=$propName");
                if (!isset($this->$propName)) {
                    $propName = $prop2Name; // esp 'Fields'
                }
                $html = $this->$propName;
            } else {
                #dbg("getHTMLElement(): String");
                $html = $element;
            }
        } elseif (method_exists($element, 'getHTML')) {
            #dbg('calling element->getHTML');
            $html = $element->getHTML($opts);
        }
        #dbg("getHTMLElement(<pre>".substr($html, 0, 30)."</pre>...): Entering (".get_class($this).")");
        return $html;
    }
    public function getHTMLRepElement($element, $opts) {
        $this->debug(1, "::getHTMLRepElement(): Entering");
        $this->debug(2, "::getHTMLRepElement(): element=$element");
        #dbg("getHTMLRepElement(<pre>".substr($element, 0, 20)."</pre>...): Entering (".get_class($this).")");
        if ($this->repDataIsEmpty(true)) {
            $this->debug(2, "::getHTMLRepElement(): returning empty alternate");
            return $this->getRepEmptyAlternate();
        }
        #dbg("getHTMLRepElement(): Not empty");
        $html = '';
        $this->debug(2, "::getHTMLRepElement(): Before Loop");
        $seq=1;
#$dbgcnt=0;
#dbg("this->class=".get_class($this));
#var_dump($this->repData);
#die;
#echo "repData=<pre>".print_r($this->repData,true)."</pre><br />\n";
        foreach ($this->getRepData() as $k=>$row) {
            $this->debug(5, "::getHTMLRepElement(): k=$k, row=<pre>".print_r($row,true)."</pre>");
            #dbg('REP ELEMENT class='.get_class($this).'==>'.$k);
#if ($dbgcnt++ >= 0) die;
            #if ($k == 'grouptype_id') var_dump($row);
            if (is_object($row) && (method_exists($row, 'getHTML') || method_exists($row, 'getRowMacros'))) {
                #dbg("OBJECT==>".get_class($row));
                if (method_exists($row, 'getHTML')) {
                    $element = $row->getHTML();
                }
                if (method_exists($row, 'getRowMacros')) {
                    $rowMacros = $row->getRowMacros();
                }
            } elseif (is_string($row)) {
                #dbg("SIMPLE STRING");
                $element = $row;
                $rowMacros = [];
            } else {
                #dbg("PRESUMABLY ASSOCIATIVE ARRAY (or iterable object)");
                $rowMacros = ['{SEQ}' => $seq++];
                $idx = 0;
                foreach ($row as $k=>$v) {
                    $this->debug(2, "::getHTMLRepElement(): k=$k, v=$v");
                    $rowMacros['{'.strtoupper($k).'}'] = $v;
                    if (isset($this->repMacroAliases[$idx]) && !isset($row[$this->repMacroAliases[$idx]])) {
                        $rowMacros['{'.strtoupper($this->repMacroAliases[$idx]).'}'] = $v;
                    }
                    $idx++;
                }
                // additional macros may be added to $rowMacros here
                $this->specialRowMacros($rowMacros, $row);
            }
            if ($this->debug>=5) { dbg("printout of rowMacros:"); var_dump($rowMacros); }
            $this->debug(6, "::getHTMLRepElement(): element=$element");
            $html .= str_ireplace(array_keys($rowMacros), array_values($rowMacros), $element);
            $this->debug(6, "::getHTMLRepElement(): html=$html");
        }
        return $html;
    }
    public function getDBTable() {
        return $this->_dbTable;
    }
    public function setDBTable($table) {
        $this->_dbTable = $table;
    }
    public function initElement(&$k, $parentForm, $mainFormObj) {
        # DO NOT UNCOMMENT THIS! Having a (circular) reference to the parentForm
        # and to the top-level form is elegant, but it KILLS performance on load times!
        # DO NOT UNCOMMENT THIS!
        #$this->_parentFormObj = $parentForm;
        #$this->_mainFormObj = $mainFormObj;
    }
    public function getDeleteIfEmpty() {
        return $this->_deleteIfEmpty;
    }
    public function setDeleteIfEmpty($val) {
        $this->_deleteIfEmpty = $val;
    }
    public function getDeleteMe() {
        return $this->_deleteMe;
    }
    public function setDeleteMe($val) {
        $this->_deleteMe = $val;
    }
    public function getRowMacros() {
        return [];
    }
    public function specialRowMacros(&$macros, $row) {
        // in children classes this may set 'selected="selected"' or
        // 'checked="checked"' or etc.
    }
    public function processMacros($macros, $html, $opts=[]) {
        if (in_array('nomacros', $opts)) {
            return $html;
        }
        #$macros = $this->getMacros($html, $opts);
        #var_dump($macros);
        $rtn = str_ireplace(array_keys($macros), array_values($macros), $html);
        #
        # DEBUGGING
        #
        if (false && configGet('debug_mode')) {
            $matches = [];
            $empties = '';
            if (true) {
                preg_match_all('/{[^{}]+}/', $rtn, $matches);
            }
            if (true) {
                foreach ($macros as $m=>$v) {
                    if (!$v && strpos($html, $m) !== false) {
                        $empties .= "<li>Empty macro $m ($v)</li>";
                    }
                }
            }
            if (@$matches[0] || $empties) {
                dbg("Debugging Macros: (".get_class($this).")");
                echo "<br>\nBEFORE = <pre>".htmlentities($html)."</pre>\n<br>\n";
                echo "<br>\nAFTER = <pre>".htmlentities($rtn)."</pre>\n<br>\n";
            }
            if (@$matches[0]) {
                dbg("\nHere are the non-matching MACROs from above\n");
                var_dump($matches);
            }
            if ($empties) {
                dbg("Empty Macros (may be valid)");
                echo "<ul>$empties</ul>";
            }
        }
        return $rtn;
    }
    public function getMacros($s, $opts) {
        foreach ($this->_getPropsByPrefix("MACRO_") as $k=>$v) {
            $macros['{'.$k.'}'] = $v;
        }
        return $macros;
    }
    public function getMacro($name) {
        $prop = 'MACRO_'.$name;
        return $this->$prop;
    }
    public function setMacro($name, $val) {
        $prop = 'MACRO_'.$name;
        if (property_exists($this, $prop)) {
            $this->$prop = $val;
            return true;
        } else {
            return false;
        }
    }
    public function getHTMLElements($opts) {
        return $this->_getPropsByPrefix("HTML_");
    }
    public function getRepEmptyAlternate() {
        return $this->HTML_RepEmptyAlternate;
    }
    public function setRepEmptyAlternate($val) {
        $this->HTML_RepEmptyAlternate = $val;
    }
    public function getRepEmptyAlternateReplacesAll() {
        return $this->repEmptyAlternateReplacesAll;
    }
    public function repDataIsEmpty($considerPlaceholder=false) {
        return !(boolean)$this->repData;
    }
    public function setRepData($val) {
        $this->repData = $val;
    }
    public function getRepData() {
        #dbg("getRepData(): class=".get_class($this)." returning...");
        #pre_r($this->repData);
        #if (get_class($this) != 'FormField_TabToC') die;
        return $this->repData;
    }
    public function getRepElement() {
        return $this->repElement;
    }
    public function isRepeating() {
        return (boolean)$this->getRepElement();
    }
    public function getIsDBField() {
        return false; // only FormField and descendants can be DB Fields
    }
    public function getElementRefs() {
        return (array)$this->elementRefs;
    }
    public function getElementRef($fieldName) {
        if (isset($this->elementRefs[$fieldName])) {
            return $this->elementRefs[$fieldName];
        } else {
            return null;
        }
    }
    private function _getPropsByPrefix($prefix) {
        $props = get_object_vars($this);
        #var_dump($props);
        $props = array_filter(get_object_vars($this), function ($v, $k) use ($prefix) {
            if (strncmp($prefix, $k, strlen($prefix)) == 0) {
                return true;
            }
            return false;
        }, ARRAY_FILTER_USE_BOTH);
        #var_dump($props);
        $props = array_combine(
            str_replace($prefix, '', array_keys($props)),
            array_values($props));
        #var_dump($props);
        return $props;
    }
    public function debug($lev, $str) {
        if ($lev <= $this->debug) {
            if (substr($str, 0 ,2) == '::') {
                $str = get_class($this).$str;
            }
            dbg($str);
        }
    }
    # convert this_property_here to This_Property_Here
    # We assume that all HTML_X and MACRO_X properties use
    # this type of case standard
    private function fixCase($prop) {
        return ucwords($prop, '_');
    }
}
