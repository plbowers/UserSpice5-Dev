<?php
/*
 * See the detailed description of the inheritance structure related to
 * the class "FormField" which is provided in the header comment in
 * core/Classes/FormField.php.
 *
 * In short,
 *    - do not change this file (it is under core/ - don't ever change
 *      anything under core/)
 *    - do not instantiate from these classes (they are abstract)
 *    - the classes you are looking for are in local/Classes/FormField.php
 *      (they are named like these but without the "US_" prefix)
 */

# Namespace used in US_FormField_Password
use ZxcvbnPhp\Zxcvbn;

# To modify FormField_Button, find the definitions of FormField_ButtonAnchor,
# FormField_ButtonSubmit, etc. in local/Classes/FormFieldTypes.php
abstract class FormField_Button extends FormField {
    protected $_fieldType = "submit";
    protected $_isDBField = false; // more appropriate default for most buttons
    protected $_fieldValue = "pressed";
    public $elementList = ['Input'], // no Pre or Post
        $HTML_Input = '
            <button class="{INPUT_CLASS} {EXTRA_OUTER_CLASS} hide_{FIELD_ID}" name="{FIELD_NAME}" value="{VALUE}" id="{FIELD_ID}" /><span class="{BUTTON_ICON}"></span> {LABEL_TEXT}</button>
            ',
        $MACRO_Button_Icon = '',
        $MACRO_Input_Class = 'btn btn-primary';
} /* Button */
abstract class US_FormField_ButtonAnchor extends FormField_Button {
    protected $_fieldType = "button";
    public $HTML_Input = '
            <a href="{LINK}" target="{TARGET}" class="{INPUT_CLASS} {EXTRA_OUTER_CLASS} hide_{FIELD_ID}" type="{TYPE}"><span class="{BUTTON_ICON}"></span> {LABEL_TEXT}</a>
            ',
        $MACRO_Link = '',
        $MACRO_Target = '_self';
    public function handle1Opt($name, &$val) {
        if (in_array(strtolower($name), ['href', 'link', 'dest'])) {
            $this->MACRO_Link = $val;
            return true;
        } elseif (strtolower($name) == 'target') {
            $this->MACRO_Target = $val;
            return true;
        }
        return parent::handle1Opt($name, $val);
    }
} /* ButtonAnchor */
abstract class US_FormField_ButtonSubmit extends FormField_Button {
} /* ButtonSubmit */
abstract class US_FormField_ButtonDelete extends FormField_Button {
    public $MACRO_Input_Class = 'btn btn-primary btn-danger';
} /* ButtonDelete */

abstract class US_FormField_Checkbox extends FormField {
    protected $_fieldType = "checkbox";
    protected $checked = 'checked';
    public $MACRO_Checked = '';
	public $HTML_Pre =
            '<div class="{DIV_CLASS} {EXTRA_OUTER_CLASS}" {ON_CLICK}> <!-- checkbox -->
            ',
        $HTML_Input =
    		'<input type="hidden" name="{FIELD_NAME}" value="0" />
             <input type="{TYPE}" name="{FIELD_NAME}" id="{FIELD_ID}" class="hide_{FIELD_ID}" value="1" {CHECKED} >
            ',
        $HTML_Post =
		    '<label class="{LABEL_CLASS}" for="{FIELD_ID}">{LABEL_TEXT}</label>
        	 </div> <!-- {DIV_CLASS} (checkbox name={FIELD_NAME}, id={FIELD_ID}) -->
             ';
    public function getMacros($s, $opts) {
        $macros = parent::getMacros($s, $opts);
        $fv = $this->getFieldValue();
        if ($fv) {
            $macros['{Checked}'] = $this->checked;
        } else  {
            $macros['{Checked}'] = '';
        }
        return $macros;
    }
} /* Checkbox */

abstract class US_FormField_Checklist extends FormField {
    protected $_fieldType = "checkbox";
    protected $_isDBField = false;
    /* Array can be ($_indexBy=='id') indexed by id and value contains 1 or 0 or it can be
     * ($_indexBy=='seq') an arbitrary sequential-from-0 index and contain the id in the value.
     */
    protected $_indexBy = 'seq'; // arbitrary (sequential from 0) index - value will be id; 'id' is alternative setting
    public $elementList = ['Input', 'Post'], // no Pre or Post
        $HTML_Pre = '', # you can set this to '<br /> or something if desired'
        $HTML_Input = '', # this will be set to either checkboxIndexById or $...Seq depending on $_indexBy
        $HTML_Post = '{FOOTER}',
        $checkboxIndexById = '<label class="{LABEL_CLASS} hide_{FIELD_ID}"><input type="{TYPE}" name="{COLUMN_NAME_PREFIX}{NAME}[{ID}]" value="1">{INTER_SPACE}{COLUMN_VALUE}</label>{SEPARATOR}',
        $checkboxIndexBySeq = '<label class="{LABEL_CLASS} hide_{FIELD_ID}"><input type="{TYPE}" name="{COLUMN_NAME_PREFIX}{NAME}[]" value="{ID}">{INTER_SPACE}{COLUMN_VALUE}</label>{SEPARATOR}';
    public $repMacroAliases = ['ID', 'COLUMN_VALUE'],
        $repElement = 'HTML_Input',
        $MACRO_Column_Name_Prefix = '',
        $MACRO_Footer='<br />',
        $MACRO_Inter_Space=' ', // between the checkbox and the label
        $MACRO_Separator='<br />'; // you might want to set this to str_repeat('&nbsp;', 5) or something for more horizontally oriented checklist

    public function handleOpts($opts=[]) {
        $rtn = parent::handleOpts($opts);
        if ($this->_indexBy == 'id') {
            $this->HTML_Input = $this->checkboxIndexById;
        } else { // presumably 'seq'
            $this->HTML_Input = $this->checkboxIndexBySeq;
        }
        #dbg('<pre>'.htmlentities($this->HTML_Input).'</pre>');
        return $rtn;
    }
    public function handle1Opt($name, &$val) {
        switch (strtolower(str_replace('_', '', $name))) {
            case 'prefix':
                $this->setMacro('Column_Name_Prefix', $val);
                return true;
            case 'separator':
            case 'sep':
                $this->setMacro('Separator', $val);
                return true;
            case 'foot':
            case 'footer':
                $this->setMacro('Footer', $val);
                return true;
            case 'indexby':
                $this->_indexBy = $val;
                return true;
        }
        return parent::handle1Opt($name, $val);
    }
} /* Checklist */

abstract class US_FormField_File extends FormField {
    protected $_fieldType = "file";
    protected $_isDBField = false; // unlikely that a file will be stored in the DB
    protected $_rename = "", // empty string means don't rename
        $_uploadDir="", // destination directory for uploads
        $_required=false, // is this upload required
        $_allowedExt=[], // allowed extensions (i.e., jpg,png,gif)
        $_maxSize=null, // maximum size
        $_allowOverwrite = false; // if file exists do we overwrite?
    public $elementList = ['Pre', 'Input', 'Post'], // Pre and Post come from FormField
        $HTML_Input = '
            <input type="hidden" name="MAX_FILE_SIZE" value="{MAX_FILE_SIZE}"/>
            <input type="{TYPE}" class="{INPUT_CLASS} hide_{FIELD_ID}" id="{FIELD_ID}" name="{FIELD_NAME}" {DISABLED} {READONLY} />
            ';
    public $MACRO_Max_File_Size = -1;

    public function __construct($opts=[], $processor=[]) {
        # Set some appropriate defaults that can be over-ridden by parent::__construct()
        $this->setMaxSize(configGet('upload_max_size', 1));
        $this->setUploadDir(configGet('upload_dir', US_ROOT_DIR."uploads"));
        $this->setAllowedExt(configGet('upload_allowed_ext'));
        parent::__construct($opts, $processor);
    }
    public function handleOpts($opts=[]) {
        $rtn = parent::handleOpts($opts);
        # If the dev didn't already set up validation then set it up here
        # (normally dev should just specify the options and let us set it up here)
        if (is_null($this->_validateObject)) {
            $this->setValidator([
                'upload_max_size' => $this->getMaxSize(),
                'upload_ext' => $this->getAllowedExt(),
                'required' => $this->_required,
                'upload_errs' => true,
            ]);
        }
        #dbg("Setting max to ".$this->getMaxSize());
        $this->setMacro('Max_File_Size', $this->getMaxSize());
        return $rtn;
    }
    public function handle1Opt($name, &$val) {
        switch (strtolower(str_replace('_', '', $name))) {
            case 'maxfilesize':
            case 'maxuploadsize':
            case 'uploadmaxsize':
                $this->setMaxSize($val);
                return true;
            case 'ext':
            case 'extension':
            case 'allowedextension':
            case 'uploadext':
                $this->setAllowedExt($val);
                return true;
            case 'uploaddir':
            case 'dir':
                $this->setUploadDir($val);
                return true;
            case 'overwrite':
            case 'allowoverwrite':
                $this->_allowOverwrite = $val;
                return true;
            case 'required':
                $this->_required = $val;
                return true;
        }
        return parent::handle1Opt($name, $val);
    }
    public function getMaxSize() {
        return $this->_maxSize;
    }
    public function setMaxSize($val) {
        $this->_maxSize = $val;
    }
    public function setUploadDir($val) {
        $this->_uploadDir = $val;
    }
    public function setAllowedExt($val) {
        if (is_array($val)) {
            $this->_allowedExt = $val;
        } elseif (empty($val)) {
            $this->_allowedExt = [];
        } else {
            $this->_allowedExt = preg_split('/[,|\s]+/', trim($val), PREG_SPLIT_NO_EMPTY);
        }
    }
    public function getAllowedExt() {
        return $this->_allowedExt;
    }
    public function saveUpload() {
        $myFile = $_FILES[$this->getFieldName()];
        if ($myFile['error'] == UPLOAD_ERR_OK) {
            $uploadDir = $this->getUploadDir();
            if (!$uploadDir) {
                $this->errors[] = lang("UPLOAD_DIR_NOT_SET");
                return false;
            }
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir); // take a shot at creating it
            }
            if (!file_exists($uploadDir)) {
                $this->errors[] = lang("UPLOAD_DIR_NONEXIST");
                return false;
            } else {
                $fullDest = $uploadDir.$myFile['name'];
                if (file_exists($fullDest) && !$this->getAllowOverwrite()) {
                    $this->errors[] = lang("UPLOAD_FILE_EXISTS_NO_OVERWRITE");
                } else {
                    move_uploaded_file($myFile['tmp_name'], $fullDest);
                    $this->setFieldValue($fullDest);
                    $this->successes[] = lang('UPLOAD_SUCCESS', $fullDest);
                }
            }
        }
    }
    public function getUploadDir() {
        if ($this->_uploadDir) {
            return $this->_uploadDir;
        } else {
            return configGet('upload_dir', US_ROOT_DIR.'uploads');
        }
    }
    public function dataIsValid($data=null) {
        if ($this->hasValidation()) {
            if (!$data) {
                $data = $_FILES;
            }
            return $this->getValidator()->check($data)->passed();
        } else {
            return true; // if no validation then it cannot fail
        }
    }
    public function getAllowOverwrite() {
        return $this->_allowOverwrite;
    }
} /* File (upload) */

abstract class US_FormField_Hidden extends FormField {
    protected $_fieldType = "hidden";
    public $elementList = ['Input'], // no Pre or Post
        $HTML_Input = '
            <input type="{TYPE}" name="{FIELD_NAME}" value="{VALUE}">
            ';
} /* Hidden */

abstract class US_FormField_HTML extends FormField {
    public $_isDBField = false,
        $HTML_Input = '{VALUE}';
    public function __construct($opts=[], $processor=[]) {
        $this->MACRO_Extra_Outer_Class = 'hide_{FIELD_ID}';
        parent::__construct($opts, $processor);
    }
    public function getHTML($opts=[]) {
        return html_entity_decode(parent::getHTML($opts));
    }
} /* HTML */

abstract class US_FormField_MultiHidden extends FormField {
    protected $_fieldType = "hidden";
    public $elementList = ['Input'], // no Pre or Post
        $HTML_Input = '<input type="{TYPE}" name="{COLUMN_NAME_PREFIX}{COLUMN_NAME}[{ID}]" value="{COLUMN_VALUE}">
            ';
        #$_dataFields = [],
        #$_dataFieldLabels = [];
    public $repMacroAliases = ['ID', 'COLUMN_NAME'],
        $repElement = 'HTML_Input',
        $MACRO_Column_Name_Prefix = '';

    public function handle1Opt($name, &$val) {
        switch (strtolower(str_replace('_', '', $name))) {
            case 'hiddencols':
                $saveInput = $this->HTML_Input;
                $this->HTML_Input = '';
                foreach ($val as $v) {
                    if ($v == 'id') continue;
                    $this->HTML_Input .= str_replace(
                        ['{COLUMN_NAME}','{COLUMN_VALUE}'],
                        [$v, '{'.$v.'}'], $saveInput);
                }
                #dbg("HIDDEN INPUT: ".htmlentities($this->HTML_Input));
                return true;
            case 'prefix':
                $this->setMacro('Column_Name_Prefix', $val);
                return true;
        }
        return parent::handle1Opt($name, $val);
    }
} /* MultiHidden */

abstract class US_FormField_Password extends FormField {
    protected $_fieldType = "password",
        $_encryptBeforeSave = false,
        $_zxcvbn = null;
    public $HTML_Scripts = [
        '<script type="text/javascript" src="'.US_URL_ROOT.'resources/js/zxcvbn.js"></script>',
        '<script type="text/javascript" src="'.US_URL_ROOT.'resources/js/zxcvbn-bootstrap-strength-meter.js"></script>',
    ];
    public function handle1Opt($name, &$val) {
        switch (strtolower(str_replace('_', '', $name))) {
            case 'passwordmeter':
            case 'strengthmeter':
            case 'pwmeter':
            case 'zxcvbn':
                if ($val) {
                    if (configGet('min_pw_score')<0) { // -1=disabled - silently ignore
                        return true;
                    }
                    $this->HTML_Scripts[] =
                        '<script type="text/javascript">
                        	$(document).ready(function () {
                        		$("#{FIELD_ID}-StrengthProgressBar").zxcvbnProgressBar({ passwordInput: "#{FIELD_ID}" });
                        	});
                        </script>';
                    $this->HTML_Post =
                        '<div class="progress">
                        	<div id="{FIELD_ID}-StrengthProgressBar" class="progress-bar"></div>
                         </div>' . $this->HTML_Post;
                }
                $this->_zxcvbn = $val;
                return true;
            case 'encrypt':
                $this->_encryptBeforeSave = $val;
                return true;
        }
        return parent::handle1Opt($name, $val);
    }
    public function dataIsValid($data=null) {
        global $user;
        $isAdmin = (isset($user) ? $user->isAdmin() : false);
        if (!$isAdmin && $this->_zxcvbn && @$data[$this->getFieldName()] && configGet('min_pw_score')) {
            $zxcvbn = new Zxcvbn();
            $strength = $zxcvbn->passwordStrength($data[$this->getFieldName()]);
            dbg("zxcvbn strength=".print_r($strength['score'],true));
            dbg("min_pw_score=".configGet('min_pw_score'));
            if ($strength['score'] < configGet('min_pw_score')) {
                $this->errors[] = lang('PASSWORD_TOO_SIMPLE');
                dbg("Returning FALSE");
                return false;
            }
            dbg("OK");
        }
        return parent::dataIsValid($data);
    }
    public function getNewValue() {
        $rtn = parent::getNewValue();
        if ($this->_encryptBeforeSave) {
            dbg("Encrypted rtn=$rtn =".password_hash($rtn, PASSWORD_BCRYPT, array('cost' => 12)));
            return password_hash($rtn, PASSWORD_BCRYPT, array('cost' => 12));
        } else {
            dbg("Unencrypted=$rtn");
            return $rtn;
        }
    }
} /* Password */

abstract class US_FormField_Radio extends FormField {
    protected $_fieldType = "radio";
    public
        $HTML_Pre = '
            <div class="{DIV_CLASS} {EXTRA_OUTER_CLASS}" {ON_CLICK}> <!-- Radio (id={FIELD_ID}, name={FIELD_NAME}) -->
            <label class="{LABEL_CLASS}" for="{FIELD_ID}">{LABEL_TEXT}
            <span class="{HINT_CLASS}" title="{HINT_TEXT}"></span></label>
            ',
        $HTML_Input = '
            <div class="radio hide_{FIELD_ID}">
				<label for="{FIELD_ID}-{ID}" class="{LABEL_CLASS}">
					<input type="{TYPE}" name="{FIELD_NAME}" id="{FIELD_ID}-{ID}" class="{INPUT_CLASS}" value="{ID}">
					{OPTION_LABEL}
				</label>
			</div> <!-- radio -->
            ',
        $HTML_Post = '
            </div> <!-- {DIV_CLASS} Radio (id={FIELD_ID}, name={FIELD_NAME}) -->
            ',
        $repElement = 'HTML_Input';
} /* Radio */

abstract class US_FormField_ReCaptcha extends FormField {
    protected $_fieldType = "recaptcha"; // not used
    protected $_validateErrors = [];
    private $_siteVerifyUrl =
        "https://www.google.com/recaptcha/api/siteverify";
    private $_secretKey = null,
        $_siteKey = null;
    public $MACRO_Recaptcha_Class = 'g-recaptcha',
        $MACRO_Recaptcha_Site_Key = '',
        $MACRO_Recaptcha_Secret_Key = '';
    public $HTML_Pre = '
            <div class="{DIV_CLASS} {EXTRA_OUTER_CLASS}" id="{FIELD_ID}" {ON_CLICK}> <!-- recaptcha -->
    		<label class="{LABEL_CLASS}">{LABEL_TEXT}</label>
             ',
        $HTML_Input = '
            <div class="{RECAPTCHA_CLASS} hide_{FIELD_ID}" data-sitekey="{RECAPTCHA_SITE_KEY}" ></div>
            ',
        $HTML_Noscript = '
            <noscript>
              <div>
                <div style="width: 302px; height: 422px; position: relative;">
                  <div style="width: 302px; height: 422px; position: absolute;">
                    <iframe src="https://www.google.com/recaptcha/api/fallback?k={RECAPTCHA_SITE_KEY}"
                            frameborder="0" scrolling="no"
                            style="width: 302px; height:422px; border-style: none;">
                    </iframe>
                  </div>
                </div>
                <div style="width: 300px; height: 60px; border-style: none;
                               bottom: 12px; left: 25px; margin: 0px; padding: 0px; right: 25px;
                               background: #f9f9f9; border: 1px solid #c1c1c1; border-radius: 3px;">
                  <textarea id="g-recaptcha-response" name="g-recaptcha-response"
                               class="g-recaptcha-response"
                               style="width: 250px; height: 40px; border: 1px solid #c1c1c1;
                                      margin: 10px 25px; padding: 0px; resize: none;" >
                  </textarea>
                </div>
              </div>
            </noscript>
            ',
        $HTML_Post = '
            </div> <!-- {DIV_CLASS} recaptcha -->
            ',
        $HTML_Scripts = '<script type="text/javascript" src="https://www.google.com/recaptcha/api.js" async defer></script>';
    public function __construct($opts=[], $processor=[]) {
        if (configGet('recaptcha_noscript')) {
            $this->elementList = ['Pre', 'Input', 'Noscript', 'Post'];
        }
        return parent::__construct($opts, $processor);
    }
    public function dataIsValid($data) {
        $this->_secretKey = configGet("recaptcha_secret_key");
        $this->_siteKey = configGet("recaptcha_site_key");
        if (empty($this->_secretKey) || empty($this->_siteKey)) {
            die(lang('RECAPTCHA_NEED_KEYS'));
        }
        # This will occur if either (a) they don't click the "I'm human" checkbox or
        # (b) they don't have javascript enabled on their browser
        if (!isset($_POST['g-recaptcha-response'])) {
            $this->errors[] = lang("RECAPTCHA_NO_RESPONSE");
            return false;
        }
    	$data = array(
    		'secret' => $this->_secretKey,
    		'response' => $_POST["g-recaptcha-response"]
    	);
    	$options = array(
    		'http' => array (
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
    			'method' => 'POST',
    			'content' => http_build_query($data)
    		)
    	);
    	$context  = stream_context_create($options);
    	$verify = file_get_contents($this->_siteVerifyUrl, false, $context);
    	$captcha_result=json_decode($verify, true);
        if (in_array('missing-input-response', $captcha_result['error-codes'])) {
            if ($this->getRequired()) {
                $this->_passed = false;
                $this->errors[] = lang("RECAPTCHA_NO_RESPONSE");
            } else {
                $this->_passed = true;
            }
        } elseif (!$this->_passed = $captcha_result['success']) {
            $this->errors[] = lang("RECAPTCHA_ERROR");
        }
        return ($this->_passed);
    }

    public function stackErrorMessages($errors) {
        return array_merge($errors, $this->_validateErrors);
    }
    public function hasValidation() {
        return true; // just a different kind of validation
    }
    public function getMacros($s, $opts) {
        $this->MACRO_Recaptcha_Site_Key = configGet('recaptcha_site_key');
        $this->MACRO_Recaptcha_Secret_Key = configGet('recaptcha_secret_key');
        return parent::getMacros($s, $opts);
    }
} /* Recaptcha */

abstract class US_FormField_SearchQ extends FormField {
    public
        $HTML_Pre = '
            <div class="input-group col-xs-12 {EXTRA_OUTER_CLASS}" {ON_CLICK}> <!-- SearchQ -->
            <!-- USE TWITTER TYPEAHEAD JSON WITH API TO SEARCH -->
            ',
        $HTML_Input = '
            <input class="{INPUT_CLASS} hide_{FIELD_ID}" id="{FIELD_ID}" name="{FIELD_NAME}" placeholder="{PLACEHOLDER}" {REQUIRED_ATTRIB}>
            <span class="input-group-btn hide_{FIELD_ID}">
              <button type="submit" class="btn btn-default"><i class="fa fa-search"></i></button>
            </span>
			<div class="searchQinfo">&nbsp;</div>
            ',
        $HTML_Post = '
            </div> <!-- SearchQ -->
            ',
        $HTML_Scripts = '<script type="text/javascript" src="'.US_URL_ROOT.'resources/js/search.js" charset="utf-8"></script>';
    public $MACRO_Field_Id = 'system-search',
        $MACRO_Field_Name = 'q',
        $MACRO_Placeholder = 'Search Text...';
    protected $_isDBField = false;
} /* SearchQ */

abstract class US_FormField_Select extends FormField {
    protected $_fieldType = "select";
    public $MACRO_Selected = '';
    public $idField = 'id';
    public $repMacroAliases = ['OPTION_VALUE', 'OPTION_LABEL'];
    public
        $HTML_Pre = '
            <div class="{DIV_CLASS} {EXTRA_OUTER_CLASS}" {ON_CLICK}> <!-- Select (name={FIELD_NAME}, id={FIELD_ID}) -->
            <label class="{LABEL_CLASS}" for="{FIELD_ID}">{LABEL_TEXT}
            <span class="{HINT_CLASS}" title="{HINT_TEXT}"></span></label>
            <br />
            <select class="{INPUT_CLASS} hide_{FIELD_ID}" id="{FIELD_ID}" name="{FIELD_NAME}" {DISABLED} {READONLY}>
            ',
        $HTML_Input = '
            <option value="{OPTION_VALUE}" {SELECTED}>{OPTION_LABEL}</option>
            ',
        $HTML_Post = '
            </select>
            </div> <!-- {DIV_CLASS} Select (id={FIELD_ID}, name={FIELD_NAME}) -->
            ',
        $repElement = 'HTML_Input';
    protected
        $placeholderRow = [],
        $selected = 'selected="selected"';
    public function handle1Opt($name, &$val) {
        switch (strtolower(str_replace('_', '', $name))) {
            case 'placeholderrow':
                $this->setPlaceholderRow($val);
                return true;
            case 'idfield':
                $this->setIdField($val);
                return true;
        }
        return parent::handle1Opt($name, $val);
    }
    public function setPlaceholderRow($v) {
        $this->placeholderRow = $v;
    }
    public function repDataIsEmpty($considerPlaceholder=false) {
        return (!(boolean)$this->repData &&
            (!$considerPlaceholder || !(boolean)$this->placeholderRow));
    }
    public function getRepData() {
        if ($this->placeholderRow) {
            return array_merge([$this->placeholderRow], $this->repData);
        } else {
            return $this->repData;
        }
    }
    public function specialRowMacros(&$macros, $row) {
        parent::specialRowMacros($macros, $row);
        # Look for match, but be careful because null==0 in PHP and 0 is
        # a very normal value for id fields. But === doesn't suffice because
        # a "blank" (unset) value might be null in data but '' in select statement
        # for the first "Choose below" item
        $fv = $this->getFieldValue();
        #if (!@$row[$this->getIdField()]) { dbg("id field=".$this->getIdField().", row follows"); var_dump($row); }
        $rowVal = $row[$this->getIdField()];
        #dbg("specialRowMacros: Comparing rowVal=$rowVal to fv=$fv");
        if (($fv === $rowVal) ||
                ($fv !== 0 && $rowVal !== 0 && $fv == $rowVal) ||
                ($fv === '0' && $rowVal === 0) ||
                ($fv === 0 && $rowVal === '0')) {
            #dbg("MATCH!");
            $macros['{SELECTED}'] = $this->selected;
        } else {
            #dbg("NO MATCH!");
            #var_dump($fv);
            #var_dump($rowVal);
            $macros['{SELECTED}'] = '';
        }
    }
    public function getMacros($s, $opts) {
        if (!$this->MACRO_Hint_Text) {
            $this->MACRO_Hint_Text = lang('CHOOSE_FROM_LIST_BELOW');
        }
        return parent::getMacros($s, $opts);
    }
    public function getIdField() {
        return $this->idField;
    }
    public function setIdField($val) {
        $this->idField = $val;
    }
} /* Select */

abstract class US_FormField_SigninFacebook extends FormField {
    protected $_fieldType = 'unused';
    public $elementList = ['Input'];
    public $HTML_Input = '
        <div class="fb-login-button {EXTRA_OUTER_CLASS} hide_{FIELD_ID}" onlogin="{ONLOGIN}" data-scope="{SCOPE}" data-max-rows="{MAX_ROWS}" data-size="{SIZE}" data-button-type="{BUTTON_TYPE}" data-show-faces="{SHOW_FACES}" data-auto-logout-link="{AUTO_LOGOUT_LINK}" data-use-continue-as="{USE_CONTINUE_AS}"></div>
        <input type="hidden" name="{FIELD_NAME}" />
<div id="status">
</div>
        ';
    public
        $MACRO_OnLogin = 'onFacebookSignIn',
        $MACRO_Scope = 'public_profile,email',
        $MACRO_Size = 'large',
        $MACRO_Button_Type = 'login_with',
        $MACRO_Max_Rows = '1',
        $MACRO_Show_Faces = 'false',
        $MACRO_Auto_Logout_Link = 'false',
        $MACRO_Use_Continue_As = 'false';

/*
If you decide to send it back to the server, you should make sure you reverify
the access token once it gets to the server. Reverifying the token is covered in
our documentation on manually building login flows. You'll need to verify that
the app_id and user_id match what you expected from the access token debug endpoint.
  https://developers.facebook.com/docs/facebook-login/manually-build-a-login-flow#checktoken
  https://developers.facebook.com/docs/facebook-login/access-tokens

  Javascript
  The Facebook SDK for Javascript obtains and persists user access tokens
  automatically in browser cookies. You can retrieve the user access token by
  making a call to FB.getAuthResponse which will include an accessToken property
  within the response.
FB.getLoginStatus(function(response) {
  if (response.status === 'connected') {
    var accessToken = response.authResponse.accessToken;
  }
} );

Here's 2 buttons that look uniform between fb and g (include styling below - make sure you fix the comments where end-comment is bad):
SOURCE: https://codepen.io/davidelrizzo/pen/vEYvyv
<button class="loginBtn loginBtn--facebook">
  Login with Facebook
</button>

<button class="loginBtn loginBtn--google">
  Login with Google
</button>
body { padding: 2em; }


/* Shared * /
.loginBtn {
  box-sizing: border-box;
  position: relative;
  /* width: 13em;  - apply for fixed size * /
  margin: 0.2em;
  padding: 0 15px 0 46px;
  border: none;
  text-align: left;
  line-height: 34px;
  white-space: nowrap;
  border-radius: 0.2em;
  font-size: 16px;
  color: #FFF;
}
.loginBtn:before {
  content: "";
  box-sizing: border-box;
  position: absolute;
  top: 0;
  left: 0;
  width: 34px;
  height: 100%;
}
.loginBtn:focus {
  outline: none;
}
.loginBtn:active {
  box-shadow: inset 0 0 0 32px rgba(0,0,0,0.1);
}


/* Facebook * /
.loginBtn--facebook {
  background-color: #4C69BA;
  background-image: linear-gradient(#4C69BA, #3B55A0);
  /*font-family: "Helvetica neue", Helvetica Neue, Helvetica, Arial, sans-serif;* /
  text-shadow: 0 -1px 0 #354C8C;
}
.loginBtn--facebook:before {
  border-right: #364e92 1px solid;
  background: url('https://s3-us-west-2.amazonaws.com/s.cdpn.io/14082/icon_facebook.png') 6px 6px no-repeat;
}
.loginBtn--facebook:hover,
.loginBtn--facebook:focus {
  background-color: #5B7BD5;
  background-image: linear-gradient(#5B7BD5, #4864B1);
}


/* Google * /
.loginBtn--google {
  /*font-family: "Roboto", Roboto, arial, sans-serif;* /
  background: #DD4B39;
}
.loginBtn--google:before {
  border-right: #BB3F30 1px solid;
  background: url('https://s3-us-west-2.amazonaws.com/s.cdpn.io/14082/icon_google.png') 6px 6px no-repeat;
}
.loginBtn--google:hover,
.loginBtn--google:focus {
  background: #E74B37;
}
*/
    public $HTML_Scripts = [ "<script>
      function onFacebookSignIn() {
        FB.getLoginStatus(function(response) {
            //statusChangeCallback(response);
            if (response.status === 'connected') {
                console.log(response.authResponse.accessToken);
                $('input[name=\"{FIELD_NAME}\"]').val(response.authResponse.accessToken);
                if (typeof afterExternalSignIn == 'function') {
                    //FB.api('/'+response.authResponse.userID+'/picture?redirect=0', (some function to set this value to use it below...));
                    FB.api('/'+response.authResponse.userID+'?fields=email,name,first_name,last_name,gender,picture,birthday,timezone',
                        function(response){
                            console.log(response);
                            userInfo = {
                                source:'facebook',
                                name:response.name,
                                fname:response.first_name,
                                lname:response.last_name,
                                email:response.email,
                                image_url:response.picture.data.url,
                                gender:response.gender,
                                birthday:response.birthday,
                                timezone:response.timezone
                            };
                            afterExternalSignIn(userInfo);
                        });
                }
            }
        });
      }

      window.fbAsyncInit = function() {
          FB.init({
            appId      : '1421874384522902',
            cookie     : true,  // enable cookies to allow the server to access
                                // the session
            xfbml      : true,  // parse social plugins on this page
            version    : 'v2.8' // use graph api version 2.8
          });

      };

      // Load the SDK asynchronously
      (function(d, s, id) {
        var js, fjs = d.getElementsByTagName(s)[0];
        if (d.getElementById(id)) return;
        js = d.createElement(s); js.id = id;
        js.src = '//connect.facebook.net/en_US/sdk.js';
        fjs.parentNode.insertBefore(js, fjs);
      }(document, 'script', 'facebook-jssdk'));

</script>",
    ];
}
/*
 * The following are available in the `payload` (PHP API doesn't mention in docs - I got these from Java API)
 * String email = payload.getEmail();
 * boolean emailVerified = Boolean.valueOf(payload.getEmailVerified());
 * String name = (String) payload.get("name");
 * String pictureUrl = (String) payload.get("picture");
 * String locale = (String) payload.get("locale");
 * String familyName = (String) payload.get("family_name");
 * String givenName = (String) payload.get("given_name");
 */
abstract class US_FormField_SigninGoogle extends FormField {
    protected $_fieldType = 'unused';
    public $elementList = ['Input', 'ThirdPartyCookieTest'];
    public $MACRO_Long_Title = 'true',
        $MACRO_Theme = 'dark',
        $MACRO_OnSuccess = 'onGoogleSignIn',
        $MACRO_OnFailure = 'onGoogleSignInFailure',
        $MACRO_Scope = 'profile email',
        $MACRO_Width = null,
        $MACRO_Height = null,
        $MACRO_GoogleSignin_Extra = ''; // put additional data-X settings here
    public $HTML_Input = '
        <div class="g-signin2 {EXTRA_OUTER_CLASS} hide_{FIELD_ID}" data-scope="{SCOPE}" data-onsuccess="{ONSUCCESS}" data-onfailure="{ONFAILURE}" data-longtitle="{LONGTITLE}" data-theme="{THEME}" data-width="{WIDTH}" data-height="{HEIGHT}" {GOOGLESIGNIN_EXTRA}></div>
        <input type="hidden" name="{FIELD_NAME}" />
        ',
        # Google Signin doesn't work when third-party cookies are not enabled. Thus the extra work to determine if the
        # browser is configured correctly before actually loading the Google Signin button script via $.getScript()
        # 3rd-party-cookie test based on https://github.com/mindmup/3rdpartycookiecheck
        $HTML_ThirdPartyCookieTest = "
          <!-- Host start.html and complete.html in a different domain and specify it below -->
          <iframe src=\"http://www.aepfoundation.org/start.html\" style=\"display:none\" ></iframe>
        ",
        $HTML_Scripts = [
            #'<script src="https://apis.google.com/js/platform.js?onload=renderButton" async defer></script>', # this is pulled in via $.getScript() below
            "<script>
                var receiveMessage = function (evt) {
                  if (evt.data === 'MM:3PCunsupported') {
                     $('#g-signin2').html('<p>Third-party cookies are turned off in your browser. Google sign-in not possible.</p>');
                    console.log('third party cookies are not supported');
                    //$.getScript('https://apis.google.com/js/platform.js');
                  } else if (evt.data === 'MM:3PCsupported') {
                    console.log('third party cookies are supported');
                    $.getScript('https://apis.google.com/js/platform.js');
                  }
                };
                window.addEventListener(\"message\", receiveMessage, false);
            </script>
            ",
            '<script type="text/javascript">
                function onGoogleSignIn(googleUser) {
                    profile = googleUser.getBasicProfile();
                    $(\'input[name="{FIELD_NAME}"]\').val(googleUser.getAuthResponse().id_token);
                    // $(\'input[name="google_uid"]\').val(profile.getId()); // this MUST be validated! DO NOT DEPEND ON THIS!
                    if (typeof afterExternalSignIn == "function") {
                        var profile = googleUser.getBasicProfile();
                        userInfo = {
                            source:"google",
                            name:profile.getName(),
                            fname:profile.getGivenName(),
                            lname:profile.getFamilyName(),
                            email:profile.getEmail(),
                            image_url:profile.getImageUrl(),
                            gender:null,
                            birthday:null,
                            timezone:null
                        };
                        afterExternalSignIn(userInfo);
                    }
                }
            </script>',
        ];

    public function __construct($opts=[], $processor=[]) {
        $rtn = parent::__construct($opts, $processor);
        if (!configGet('glogin')) {
            # Theoretically this will never be displayed. If it is we have bigger problems.
            $this->HTML_Input = '<strong>DEV ERROR: GOOGLE LOGIN HAS NOT BEEN ENABLED ON YOUR SITE.</strong>';
            return false;
        }
        if (!configGet('glogin_client_id')) {
            $this->HTML_Input = '<strong>SETUP ERROR: NO GOOGLE CLIENT ID HAS BEEN CONFIGURED FOR GOOGLE LOGIN IN SITE SETTINGS</strong>';
        }
        return $rtn;
    }
    /* leave it in just in case I need it later...
    public function handle1Opt($name, &$val) {
        return parent::handle1Opt($name, $val);
    }
    */
    public function initElement(&$k, $parentFormObj, $mainFormObj) {
        #pre_r($mainFormObj->elementList);
        $mainFormObj->setHeaderSnippets([
            '<meta name="google-signin-client_id" content="'.configGet('glogin_client_id').'">',
        ]);
        return parent::initElement($k, $parentFormObj, $mainFormObj);
    }
    public function dataIsValid($data) {
        #dbg("::dataIsValid(): data[".$this->getFieldName()."]=".$data[$this->getFieldName()]);
        if (empty($data[$this->getFieldName()])) {
            if ($this->getRequired()) {
                $this->errors[] = lang('GOOGLE_SIGNIN_REQUIRED');
                return false; // you have to use Google Signin - validation fails
            } else {
                return true; // it's legitimate to log in other ways besides Google Signin
            }
        }
        $idToken = $data[$this->getFieldName()];
        $client = new Google_Client(['client_id' => configGet('glogin_client_id')]);
        if ($payload = $client->verifyIdToken($idToken)) {
            dbg("Setting payload=");
            pre_r($payload);
            $GLOBALS['googleSigninPayload'] = $payload; // available for post-form-processing
            return true;
        } else {
            dbg("OOPS! No Payload!");
            $_GLOBALS['googleSigninPayload'] = null; // available for post-form-processing
            $this->errors[] = lang('GOOGLE_SIGNIN_ERROR');
        }
    }
}

abstract class US_FormField_Table extends FormField {
    protected $_fieldType = "table",
        $_dataFields = [],
        $_dataFieldLabels = [],
        $_td_row=false,
        $_th_row=false,
        $selectOptions = [],
        $multiCheckboxes = [];
    public $repMacroAliases = ['ID', 'NAME'];
    public
        $MACRO_Table_Class = "table-hover",
        $MACRO_TH_Row_Class = "",
        $MACRO_TH_Cell_Class = "",
        $MACRO_TD_Row_Class = "",
        $MACRO_TD_Cell_Class = "",
        $MACRO_Checkbox_Label = "";
    public
        $HTML_Pre = '
            <div id="div-{FIELD_ID}" class="{DIV_CLASS} {EXTRA_OUTER_CLASS}" {ON_CLICK}> <!-- Table (name={FIELD_NAME}) -->
            <table id="{FIELD_ID}" class="table {TABLE_CLASS} hide_{FIELD_ID}">
            <thead>
            <tr class="{TH_ROW_CLASS}">{TABLE_HEAD_CELLS}</tr>
            </thead>
            <tbody>
            ',
        $HTML_Input = '
            <tr class="{TD_ROW_CLASS}">{TABLE_DATA_CELLS}</tr>
            ',
        $HTML_Post = '
            </tbody>
            </table>
            {PAGE_INDEX}
            </div> <!-- {DIV_CLASS} Table (name={FIELD_NAME}) -->
            ',
        $HTML_Checkallbox = '<label><input type="checkbox" id="checkall-{FIELD_NAME}" />{LABEL_TEXT}</label>',
        $HTML_Checkbox_Id = '<input type="checkbox" name="{FIELD_NAME}[]" id="{FIELD_NAME}-{ID}" value="{ID}"/><label class="{LABEL_CLASS}" for="{FIELD_NAME}-{ID}">&nbsp;{CHECKBOX_LABEL}</label>',
        $HTML_Checkbox_Value = '<input type="checkbox" name="{FIELD_NAME}[{ID}]" id="{FIELD_NAME}-{ID}" value="{VALUE}"/><label class="{LABEL_CLASS}" for="{FIELD_NAME}-{ID}">&nbsp;{CHECKBOX_LABEL}</label>',
        $HTML_Hidden_Id = '<input type="hidden" name="{FIELD_NAME}[{ID}]" id="{FIELD_NAME}-{ID}" value="{ID}"/>',
        $HTML_Fields = [
            'text' => '<input type="text" name="{FIELD_NAME}[{ID}]" id="{FIELD_NAME}-{ID}" value="{{FIELD_NAME}}"/>',
            'hidden' => '<input type="hidden" name="{FIELD_NAME}[{ID}]" id="{FIELD_NAME}-{ID}" value="{{FIELD_NAME}}"/>',
            'checkbox' => '<input type="hidden" name="{FIELD_NAME}[{ID}]" value="0" /><input type="checkbox" name="{FIELD_NAME}[{ID}]" id="{FIELD_NAME}-{ID}" value="1" {CHECKED}/><label class="{LABEL_CLASS}" for="{FIELD_NAME}-{ID}">&nbsp;{CHECKBOX_LABEL}</label>',
            'select' => 'GOOD LUCK!',
        ],
        $HTML_Checkall_Script = '<script type="text/javascript" src="'.US_URL_ROOT.'resources/js/jquery-check-all.min.js"></script>',
        $HTML_Checkall_Init = '<script>$("#checkall-{FIELD_NAME}").checkAll({ childCheckBoxes:"{FIELD_NAME}", showIndeterminate:true });</script>',
        $repElement = 'HTML_Input';

    public function handle1Opt($name, &$val) {
        # this goes above the switch because otherwise underscores
        # in the field name get lost
        if (preg_match('/^select\((.*)\)$/', $name, $m)) {
            $this->selectOptions[$m[1]] = $val;
            return true;
        }
        $simpleName = strtolower(str_replace('_', '', $name));
        switch ($simpleName) {
            case 'tabledatacells':
            case 'tdrow':
                $this->_td_row=true;
                #dbg('setting table_data_cells');
                if (is_array($val)) {
                    $val = '<td>'.implode('</td><td>', $val).'</td>';
                }
                preg_match_all('/{([a-z_][a-z_0-9]*)\((text|hidden|checkbox)(-?seq)?(?:,\s*([^()]*))?\)}/i', $val, $m, PREG_SET_ORDER);
                #var_dump($m);
                #var_dump($val);
                foreach ($m as $x) {
                    $repl = [ '{FIELD_NAME}' => $x[1], '{LABEL}' => @$x[4], ];
                    if (isset($x[3]) && $x[3]) {
                        $repl['{ID}']='{SEQ}';
                    }
                    if (strtolower($x[2]) == 'checkbox') {
                        $repl['{CHECKED}'] = "{{$x[1]}-CHECKED}";
                        $this->multiCheckboxes[] = $x[1];
                    }
                    $inputFld = str_ireplace(array_keys($repl), array_values($repl), $this->HTML_Fields[strtolower($x[2])]);
                    $val = str_replace($x[0], $inputFld, $val);
                }
                #var_dump($val);
                $this->HTML_Input = $this->processMacros(
                    [
                        '{TABLE_DATA_CELLS}'=>$val,
                        '{CHECKBOX_ID}' => $this->HTML_Checkbox_Id,
                        '{CHECKBOX_VALUE}' => $this->HTML_Checkbox_Value,
                        '{HIDDEN_ID}' => $this->HTML_Hidden_Id,
                        '{HIDDEN_SEQ}' => str_replace('ID', 'SEQ', $this->HTML_Hidden_Id),
                    ],
                    $this->HTML_Input);
                #dbg('AFTER: HTML_Input='.htmlentities($this->HTML_Input));
                return true;
            case 'tableheadcells':
            case 'throw': // th_row
                $this->_th_row=true;
                #dbg('setting table_head_cells');
                if (is_array($val)) {
                    $val = '<th>'.implode('</th><th>', $val).'</th>';
                }
                if (preg_match('/{([^{}]*)\(checkallbox\)}/i', $val, $m)) {
                    $newHTML = str_replace('{LABEL_TEXT}', $m[1], $this->HTML_Checkallbox);
                    $val = str_replace($m[0], $newHTML, $val);
                    $this->HTML_Scripts[] = $this->HTML_Checkall_Script;
                    $this->HTML_Scripts[] = $this->HTML_Checkall_Init;
                }
                $this->HTML_Pre = $this->processMacros(
                    ['{TABLE_HEAD_CELLS}'=>$val], $this->HTML_Pre);
                #dbg("Setting Data Fields<br />\n");
                #dbg("_dataFields=".print_r($this->_dataFields,true));
                #dbg("_dataFieldLabels=".print_r($this->_dataFieldLabels,true));
                #dbg('AFTER: HTML_Pre='.htmlentities($this->HTML_Pre));
                return true;
            case 'searchable':
                if ($val) {
                    $this->MACRO_Table_Class .= ' table-list-search';
                } else {
                    dbg("Turning searchable OFF is not implemented");
                }
                return true;
            case 'datatables':
                if ($val) {
                    $this->HTML_Scripts = array_merge($this->HTML_Scripts, [
                        '<script type="text/javascript" src="'.US_URL_ROOT.'resources/DataTables-1.10.15/js/jquery.dataTables.min.js"></script>',
                        '<script type="text/javascript" src="'.US_URL_ROOT.'resources/DataTables-1.10.15/js/dataTables.bootstrap.min.js"></script>',
                        '<script type="text/javascript" src="'.US_URL_ROOT.'resources/FixedHeader-3.1.2/js/dataTables.fixedHeader.min.js"></script>',
                        '<script type="text/javascript" src="'.US_URL_ROOT.'resources/Responsive-2.1.1/js/dataTables.responsive.min.js"></script>',
                        '<script type="text/javascript" src="'.US_URL_ROOT.'resources/Responsive-2.1.1/js/responsive.bootstrap.min.js"></script>',
                        '<script type="text/javascript">$(document).ready(function() { $("#{FIELD_ID}").DataTable('.$val.'); })</script>',
                    ]);
                    $mainForm = $this->getMainForm();
                    $mainForm->setHeaderSnippets([
                        '<link rel="stylesheet" type="text/css" href="DataTables-1.10.15/css/dataTables.bootstrap.min.css"/>',
                        '<link rel="stylesheet" type="text/css" href="FixedHeader-3.1.2/css/fixedHeader.bootstrap.min.css"/>',
                        '<link rel="stylesheet" type="text/css" href="Responsive-2.1.1/css/responsive.bootstrap.min.css"/>',
                    ]);
                }
                return true;
            case 'label':
            case 'display':
            case 'checkboxlabel':
                $this->setMacro('Checkbox_Label', $val);
                return true;
        }
        return parent::handle1Opt($name, $val);
    }
    public function specialRowMacros(&$macros, $row) {
        // find cells with "{field(SELECT)}" and replace it with select/option tags
        // as specified in select(field) option
        foreach ($this->selectOptions as $k => $opts) {
            $html = "<select name=\"{$k}[$row[id]]\">";
            foreach ($opts as $val => $disp) {
                if (@$row[$k] == $val) {
                    $selected = 'selected="selected"';
                } else {
                    $selected = "";
                }
                $html .= "<option value=\"$val\" $selected>$disp</option>";
            }
            $html .= '</select>';
            $macros['{'.$k.'(select)}'] = $html;
        }
        foreach ($this->multiCheckboxes as $field) {
            if (@$row[$field]) {
                $macros["{{$field}-CHECKED}"] = 'checked';
            } else {
                $macros["{{$field}-CHECKED}"] = '';
            }
        }
        #dbg("FormField_Table::specialRowMacros(): macros=");
        #pre_r($macros);
    }
    public function getHTML($opts=[]) {
        if (!$this->_th_row) {
            $this->errors[] = "DEVELOPMENT ERROR: th_row was not set";
        }
        if (!$this->_td_row) {
            $this->errors[] = "DEVELOPMENT ERROR: td_row was not set";
        }
        return parent::getHTML($opts);
    }
} /* Table */

# Tab Table-of-Contents
abstract class US_FormField_TabToC extends FormField {
    protected $_fieldType = "tabtoc"; // tabbed table of contents
    protected $tocType = "tab"; // "pill" is an alternative
    protected $tocClass = "nav nav-tabs";
    public $repElement = 'HTML_Input';
    public
        $HTML_Pre = '
            <ul class="{TAB_UL_CLASS} {EXTRA_OUTER_CLASS}" id="myTab"> <!-- ToC -->
            ',
        $HTML_Input = '
            <li class="{TAB_ACTIVE} hide_{FIELD_ID}"><a href="#{TAB_ID}" data-toggle="{TOC_TYPE}">{TITLE}</a></li>
             ',
        $HTML_Post = '
             </ul> <!-- ToC -->
             ',
        $HTML_Scripts = '<script type="text/javascript" >
            $(document).ready(function() {
                var activeTab = location.hash || sessionStorage.getItem("active_tab");
                if (activeTab) {
                    $("a[href=\'" + activeTab + "\']").tab("show");
                }
                $(document.body).on("click", "a[data-toggle]", function(event) {
                    if (sessionStorage) {
                        sessionStorage.setItem("active_tab", this.getAttribute("href"));
                    } else {
                        location.hash = this.getAttribute("href");
                    }
                });
            });
            $(window).on("popstate", function() {
                var anchor = location.hash || $("a[data-toggle=\'tab\']").first().attr("href");
                $("a[href=\'" + anchor + "\']").tab("show");
            });
        </script>';
    public function getTocType() {
        return $this->tocType;
    }
    public function setTocType($val) {
        $this->tocType = $val;
    }
    public function getMacros($s, $opts) {
        $this->MACRO_Tab_UL_Class = 'nav nav-'.$this->getTocType().'s'; # nav-tabs or nav-pills usually
        return parent::getMacros($s, $opts);
    }
    public function setRepData($opts=[]) {
        // typically getting an array from Form::getFields()
        $tmp = [];
        $active = 'active'; // first one active
        $toc_type = (isset($opts['toc-type']) ? $opts['toc-type'] : $this->tocType);
        foreach ($opts as $k=>$o) {
            #dbg('Class Name: '.get_class($o));
            $tmp[] = [
                'title'=>$o->getMacro('Form_Title'),
                'tab_id'=>$k,
                'tab_active'=>$o->getMacro('Tab_Pane_Active'),
                #'tab_active'=>$active,
                'toc_type'=>$toc_type,
            ];
            $active = '';
        }
        $this->repData = $tmp;
    }
} /* TabToC */

abstract class US_FormField_Text extends FormField {
    protected $_fieldType = "text";
} /* Text */

abstract class US_FormField_Textarea extends FormField {
    protected $_fieldType = "textarea";
    public
        $HTML_Input = '
            <div class="hide_{FIELD_ID}">
              <textarea class="{INPUT_CLASS} {EDITABLE} {EXTRA_OUTER_CLASS}" id="{FIELD_ID}" '
            .'name="{FIELD_NAME}" rows="{ROWS}" placeholder="{PLACEHOLDER}" '
            .'{REQUIRED_ATTRIB} {EXTRA_ATTRIB} {READONLY} {DISABLED}>{VALUE}</textarea>
            </div>',
        $MACRO_Rows = '6',
        $MACRO_Editable = 'editable',
        $MACRO_Us_Url_Root = US_URL_ROOT,
        $MACRO_Tinymce_Url = null,
        $MACRO_Tinymce_Apikey = null,
        $MACRO_Tinymce_Plugins = null,
        $MACRO_Tinymce_Height = null,
        $MACRO_Tinymce_Menubar = null,
        $MACRO_Tinymce_Toolbar = null,
        $MACRO_Tinymce_Skin = null,
        $MACRO_Tinymce_Theme = null,
        $MACRO_Tinymce_Readonly = 'false',
        $MACRO_Tinymce_Resize = 'false',
        /* Note that TINYMCE_MENUBAR and TINYMCE_TOOLBAR have the quotes added separately */
        $HTML_Scripts = ['<script type="text/javascript" src="{TINYMCE_URL}"></script>',
            '<script type="text/javascript" >
                tinymce.init({
                    selector: \'#{FIELD_ID}\',
                    plugins: \'{TINYMCE_PLUGINS}\',
                    height: {TINYMCE_HEIGHT},
                    menubar: {TINYMCE_MENUBAR},
                    toolbar: {TINYMCE_TOOLBAR},
                    skin: \'{TINYMCE_SKIN}\',
                    theme: \'{TINYMCE_THEME}\',
                    statusbar: false,
                    elementpath: false,
                    readonly: {TINYMCE_READONLY},
                    resize: {TINYMCE_RESIZE},
                 });
            </script>'];
    public function handleOpts($opts) {
        $rtn = parent::handleOpts($opts);
        $tinymceOpts = [
            'Tinymce_Url'=>'tinymce_url',
            'Tinymce_Apikey'=>'tinymce_apikey',
            'Tinymce_Plugins'=>'tinymce_plugins',
            'Tinymce_Menubar'=>'tinymce_menubar',
            'Tinymce_Toolbar'=>'tinymce_toolbar',
            'Tinymce_Height'=>'tinymce_height',
            'Tinymce_Skin'=>'tinymce_skin',
            'Tinymce_Theme'=>'tinymce_theme',
        ];
        foreach ($tinymceOpts as $macro => $config) {
            if (!$this->getMacro($macro) && ($x = configGet($config))) {
                $this->setMacro($macro, $x);
            }
        }
        if ($this->getMacro('Tinymce_Apikey') && ($tinyUrl = $this->getMacro('Tinymce_Url'))) {
            if (stripos($tinyUrl, '{TINYMCE_APIKEY}') === false) {
                $this->setMacro('Tinymce_Apikey', $tinyUrl.'?apiKey={TINYMCE_APIKEY}');
            }
        }
        /* These options can be true or false or a string - thus have to explicitly add quotes */
        foreach (['Tinymce_Menubar', 'Tinymce_Toolbar'] as $macro) {
            if (!in_array($x = $this->getMacro($macro), ['true', 'false']) && $x{0} != "'") {
                $this->setMacro($macro, "'".$x."'");
                #dbg($this->getMacro($macro));
            }
        }
        /* Technically this should be substituted automatically. Due to the order of the substitutions
         * (since this macro is contained within another) sometimes it doesn't work. Rather than making
         * a (complicated) system for ordering the macros, we just do a quick substitute here...
         */
        if (($tinyUrl = $this->getMacro('Tinymce_Url')) && stripos($tinyUrl, '{TINYMCE_APIKEY}') === false) {
            $this->setMacro('Tinymce_Url', str_ireplace('{US_URL_ROOT}', $this->MACRO_Us_Url_Root, $tinyUrl));
        }
        if ($this->getMacro('Readonly') || $this->getMacro('Disabled')) {
            $this->setMacro('Tinymce_Readonly', 'true');
            $this->setMacro('Tinymce_Toolbar', 'false');
            $this->setMacro('Tinymce_Menubar', 'false');
        }

        return $rtn;
    }
    public function handle1Opt($name, &$val) {
        if (strcasecmp('autoresize', $name) == 0 && $val) {
            $this->MACRO_Tinymce_Plugins .= ' autoresize'; // can't turn it off
            return true;
        } elseif (strcasecmp('resize', $name) == 0 || strcasecmp('resizable', $name) == 0) {
            if (is_bool($val)) {
                $this->MACRO_Tinymce_Resize = $val?'true':'false';
            } else {
                $this->MACRO_Tinymce_Resize = "'".$val."'";
            }
            return true;
        }
        return parent::handle1Opt($name, $val);
    }
} /* Textarea */
