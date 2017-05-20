<?php
/*
UserSpice 4
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
abstract class US_Validate {
	private $_passed = false,
			$_errors = [],
			$_db = null,
			$_ruleList = [];

	public function __construct($rules=null) {
		$this->_db = DB::getInstance();
		if ($rules || is_array($rules)) {
			$this->_ruleList = $this->stdRules($rules);
		}
	}

	public function stdRules($rules) {
        global $T;
		$newRuleList = [];
		foreach ($rules as $rulename => $rule) {
			$newrule = [];
			if (is_numeric($rulename) && !is_array($rule))
				$rulename = $rule; // shorthand
			$query = $this->_db->query("SELECT * FROM $T[field_defs] WHERE name = ?", [$rulename]);
			$results = $query->first();
			foreach (['display_lang', 'display', 'alias', 'required', 'max', 'min',
                                'min_val', 'max_val', 'unique'=>'unique_in_table',
                                'matches'=>'match_field', 'match'=>'match_field',
								'update_id', 'is_numeric', 'valid_email', 'regex',
								'regex_display', 'upload_max_size', 'upload_ext', 'upload_errs']
                                as $k => $rn) {
                #dbg("k=$k, rn=$rn");
				if (is_numeric($k)) $k = $rn;
#var_dump($results);
#echo "rn=$rn<br />\n";
				if ($k == 'unique' && isset($rule['action'])) {
					$k = $k.'_'.$rule['action']; // 'unique'=='unique_add' or 'unique_update'
				}
                if ($k == 'display_lang' && $results) {
                    $results->$k = lang($results->$k);
                    $k = 'display';
                }
				if (isset($rule[$k])) {
					if ($rule[$k] !== 'unset') { // special value to avoid getting DB validation rule
						$newrule[$k] = $rule[$k];
                    }
				} elseif (isset($results->$rn)) {
					$newrule[$k] = $results->$rn;
				}
			}
			if (isset($newrule['alias'])) $rulename = $newrule['alias'];
			if (isset($rule['alias'])) $rulename = $rule['alias'];
			$newRuleList[$rulename] = $newrule;
		}
		return $newRuleList;
	}
    public function addRule($ruleName, $ruleVal) {
        $this->_ruleList[$ruleName] = $ruleVal;
    }

    public function listFields() {
        return array_keys($this->_ruleList);
    }

	public function describe($fields=[], $ruleList=[], $rulesToDescribe=[]) {
		$rtn = [];
		if (!$ruleList) $ruleList = $this->_ruleList;
		if (!$fields) $fields = array_keys($ruleList);
		foreach ((array)$fields as $f) {
			#dbg( "DEBUG: f=$f<br />\n");
			if (isset($ruleList[$f])) {
				foreach ((array)$ruleList[$f] as $k => $r) {
					#dbg( "DEBUG: k=$k<br />\n");
					switch ($k) {
						case 'min_val':
							$rtn[] = lang('VALID_MIN_VAL', $r).' ';
							break;
						case 'max_val':
							$rtn[] = lang('VALID_MAX_VAL', $r).' ';
                            break;
						case 'min':
							$rtn[] = lang('VALID_MIN_CHARS', $r).' ';
							break;
						case 'max':
							$rtn[] = lang('VALID_MAX_CHARS', $r).' ';
							break;
						case 'required':
							if ($r) $rtn[] = lang('VALID_REQUIRED').' ';
							else $rtn[] = lang('VALID_OPTIONAL').' ';
							break;
						case 'unique':
						case 'unique_add':
						case 'unique_update':
							$rtn[] = lang('VALID_MUST_BE_UNIQUE').' ';
							break;
						case 'is_numeric':
							$rtn[] = lang('VALID_MUST_BE_NUM').' ';
							break;
						case 'valid_email':
							$rtn[] = lang('VALID_MUST_BE_EMAIL').' ';
							break;
						case 'regex':
							$rtn[] = $ruleList[$f]['regex_display'].' ';
							break;
						case 'match':
						case 'matches':
						case 'match_field':
                            #echo " r=$r<br />\n";
                            $fdisp = (isset($ruleList[$r]['display'])) ? $ruleList[$r]['display'] : $r;
							$rtn[] = lang('VALID_MUST_MATCH', $fdisp).' ';
							break;
                        case 'upload_max_size':
                            $rtn[] = lang('VALID_MAX_FILE_SIZE', $r).' ';
                            break;
                        case 'upload_ext':
                            if ($r) {
                                $rtn[] = lang('VALID_UPLOAD_EXT', implode(", ", (array)$r)).' ';
                            }
                            break;
					}
				}
			}
		}
		return implode($rtn, ' &nbsp;-&nbsp; ');
	}

	public function check($source, $items=[]) {
        #dbg("Validator::check(): Entering. Source follows");
        #pre_r($source);
        global $T; // table prefix map
		$this->_errors = [];
		if (!$items && $this->_ruleList) {
            $items = $this->_ruleList;
        }
        #dbg("ITEMS");
		#var_dump($items);
		foreach ($items as $item => $rules) {
            #dbg("ITEM: ".$item);
            #var_dump($rules);
			$item = sanitize($item);
            if (!isset($rules['display'])) {
    			$rules['display'] = $item; // poor default, but that's what we've got
            }
			$display = $rules['display'];
			foreach ($rules as $rule => $rule_value) {
                #dbg("Validate::check(): rule=$rule item=$item\n");
                if (is_array(@$source[$item])) {
                    $value = $source[$item];
                } else {
    				$value = trim(@$source[$item]);
    				$value = Input::sanitize($value);
                }

				if (in_array($rule, ['display','regex_display','alias', 'update_id']))
					continue; // these aren't really "rules" per se
                #dbg("Validate::check(): after continue<br />\n");
                #dbg("Validate::check(): rule=$rule, rule_value=$rule_value, value='$value'<br />\n");
				if ($rule == 'required') {
                    if ($rule_value && empty($value)) {
                        #dbg("ERROR - required item=$item, display=$display");
    					$this->addError([lang('VALID_ERR_REQUIRED', $display),$item]);
                    } elseif ($rule_value && isset($value['error']) && $value['error'] == UPLOAD_ERR_NO_FILE) {
                        $this->addError([lang('VALID_ERR_UPLOAD_REQUIRED', $display)]);
                    }
				} elseif (!empty($value)) {
                    #dbg("Validate::check(): rule=$rule, rule_value=<pre>".print_r($rule_value,true)."</pre>, item=$item<br />\n");
					switch ($rule) {
                        case 'min_val':
                            if ($value < $rule_value) {
								$this->addError([lang('VALID_ERR_MIN_VAL',[$display,$rule_value]),$item]);
                            }
                            break;

                        case 'max_val':
                            if ($value > $rule_value) {
								$this->addError([lang('VALID_ERR_MAX_VAL',[$display,$rule_value]),$item]);
                            }
                            break;

						case 'min':
						case 'min_len':
						case 'min_chars':
							if (strlen($value) < $rule_value) {
								$this->addError([lang('VALID_ERR_MIN_CHARS',[$display,$rule_value]),$item]);
							}
							break;

						case 'max':
						case 'max_len':
						case 'max_chars':
							if (strlen($value) > $rule_value) {
								$this->addError([lang('VALID_ERR_MAX_CHARS',[$display,$rule_value]),$item]);
							}
							break;

						case 'in_list':
							if (!in_array($value, (array)$rule_value)) {
								if (!$display_desc = @$rules['in_list_display']) {
    								$display_desc = implode(", ", $rule_value);
                                }
								$this->addError([lang('VALID_ERR_MUST_BE_IN_LIST', [$display_desc, $display]),$item]);
							}
							break;

						case 'matches':
							if ($value != $source[$rule_value]) {
								$match = $items[$rule_value]['display'];
								$this->addError([lang('VALID_ERR_MUST_MATCH', [$match, $display]),$item]);
							}
							break;

						case 'unique':
						case 'unique_add':
							$check = $this->_db->get($rule_value, [$item, '=', $value]);
							if ($check->count()) {
								$this->addError([lang('VALID_ERR_NOT_UNIQUE', $display),$item]);
							}
							break;

						case 'unique_update':
							if (isset($rules['update_id'])) {
								$table = $rule_value;
								$id = $rules['update_id'];
							} elseif (strstr(",", $rule_value)) {
								list($table, $id) = explode(',', $rule_value);
                            } else {
                                $this->addError(["DEV ERROR: update_id not specified for $display", $item]);
                                break;
                            }
                            if (@$T[$table]) {
								$table = $T[$table];
                            }
							$query = "SELECT * FROM {$table} WHERE id != ? AND {$item} = ?";
							$check = $this->_db->query($query, [$id, $value]);
							if ($check->count()) {
								$this->addError([lang('VALID_ERR_NOT_UNIQUE', $display),$item]);
							}
							break;

						case 'regex':
							if (!preg_match($rule_value, $value)) {
								$regex_display = $rules['regex_display'];
								$this->addError([lang('VALID_ERR_BAD_REGEX', [$display, $regex_display]),$item]);
							}
							break;

						case 'is_numeric':
							if (!is_numeric($value)) {
								$this->addError([lang('VALID_ERR_MUST_BE_NUM', $display),$item]);
							}
							break;

						case 'valid_email':
							if (!filter_var($value,FILTER_VALIDATE_EMAIL)) {
								$this->addError([lang('VALID_ERR_MUST_BE_EMAIL', $display),$item]);
							}
							break;

                        case 'upload_max_size':
                            if ($value['size'] > $rule_value) {
                                $this->addError([lang('VALID_ERR_MAX_FILE_SIZE', $rule_value),$item]);
                            }
                            break;

                        case 'upload_ext':
                            if ($rule_value) {
                                $pathInfo = pathinfo($value['name']);
                                # no $rule_value means any extensions are allowed
                                if (isset($pathInfo['extension']) && !in_array($pathInfo['extension'], $rule_value)) {
                                    $this->addError([lang('VALID_ERR_UPLOAD_EXT', implode(", ", (array)$rule_value)),$item]);
                                }
                            }
                            break;

                        case 'upload_errs':
                            // If "OK" or "NO_FILE" that's ok - if "NO_FILE" wasn't OK it would
                            // be caught in the "required" rule above
                            if (!in_array($value['error'], [UPLOAD_ERR_OK, UPLOAD_ERR_NO_FILE])) {
                                $upload_errors = [
                                    UPLOAD_ERR_INI_SIZE => 'UPLOAD_ERR_INI_SIZE',
                                    UPLOAD_ERR_FORM_SIZE => 'UPLOAD_ERR_FORM_SIZE',
                                    UPLOAD_ERR_PARTIAL => 'UPLOAD_ERR_PARTIAL',
                                    #UPLOAD_ERR_NO_FILE => 'UPLOAD_ERR_NO_FILE', // handled in "required"
                                    UPLOAD_ERR_NO_TMP_DIR => 'UPLOAD_ERR_NO_TMP_DIR',
                                    UPLOAD_ERR_CANT_WRITE => 'UPLOAD_ERR_CANT_WRITE',
                                    UPLOAD_ERR_EXTENSION => 'UPLOAD_ERR_EXTENSION',
                                ];
                                $this->addError([lang($upload_errors[$value['error']]), $item]);
                            }
                            break;
					}
				}
			}

		}

		if (empty($this->_errors)) {
			$this->_passed = true;
		}
        #dbg("check(): returning ".($this->_passed?'true':'false')."<br />\n");
		return $this;
	}

	public function addError($error) {
		$this->_errors[] = $error;
		$this->_passed = false;
	}

	public function display_errors() {
		$html = '<ul class="bg-danger">';
		foreach($this->_errors as $error) {
			if (is_array($error)) {
				$html .= '<li class="text-danger">'.$error[0].'</li>';
				$html .= '<script>jQuery("document").ready(function() {jQuery("#'.$error[1].'").parent().closest("div").addClass("has-error");});</script>';
			}else{
				$html .= '<li class="text-danger">'.$error.'</li>';
			}
		}
		$html .= '</ul>';
		return $html;
	}

	public function stackErrorMessages($errs=[]) {
        # errors in $this->_errors contain more than just the string so must iterate
		foreach ($this->_errors as $err)
			$errs[] = $err[0];
		return $errs;
	}

    public function setRequired($fld, $val) {
        $this->_ruleList[$fld]['required'] = $val;
    }
    public function getRequired($fld) {
        return (boolean)@$this->_ruleList[$fld]['required'];
    }

	public function errors() {
		return $this->_errors;
	}

	public function passed() {
		return $this->_passed;
	}
}
