<?php

/*
 * This class parallels the Classes/Validate.php class closely
 * enough in its public interface that they can be used interchangably
 * by Classes/Form.php and Classes/FormField.php.
 */
abstract class US_ReCaptchaValidate extends Validate {
    private static $_siteVerifyUrl =
        "https://www.google.com/recaptcha/api/siteverify";
    private $_secretKey = null,
        $_siteKey = null;

    function __construct()
    {
        $this->_secretKey = configGet("recaptcha_secret_key");
        $this->_siteKey = configGet("recaptcha_site_key");
        if (empty($this->_secretKey) || empty($this->_siteKey)) {
            die(lang('RECAPTCHA_NEED_KEYS'));
        }
    }
	public function describe($fields=[], $ruleList=[], $rulesToDescribe=[]) {
        return lang("RECAPTCHA_YOU_ARE_HUMAN");
    }
	public function check($source, $items=[]) {
    	$data = array(
    		'secret' => $this->_secretKey,
    		'response' => $_POST["g-recaptcha-response"]
    	);
    	$options = array(
    		'http' => array (
    			'method' => 'POST',
    			'content' => http_build_query($data)
    		)
    	);
    	$context  = stream_context_create($options);
    	$verify = file_get_contents($this->_siteVerifyUrl, false, $context);
    	$captcha_success=json_decode($verify);
        if (!$this->_passed = $catpcha_success->success) {
            $this->errors[] = lang("RECAPTCHA_ERROR");
        }
        return ($this->_passed);
    }
}
