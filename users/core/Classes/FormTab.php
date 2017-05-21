<?php
class US_FormTab_Contents extends BaseForm {
    public $elementList = [
        'openTab', 'Fields', 'closeTab',
    ];
    protected $HTML_openTab = '
        <div class="tab-content {TAB_CONTENT_CLASS}" id="{TAB_ID}">
        ';
    protected $HTML_closeTab = '
        </div> <!-- tab-content (id={TAB_ID}) -->
        ';
}
class US_FormTab_Pane extends BaseForm {
    public $elementList = [
        'openTab', 'Fields', 'closeTab',
    ];
    protected $HTML_openTab = '
        <div class="tab-pane {TAB_PANE_ACTIVE} {TAB_PANE_CLASS}" id="{TAB_ID}">
        ';
    protected $HTML_closeTab = '
        </div> <!-- tab-pane (id={TAB_ID}) -->
        ';
    public
        $MACRO_Tab_Class = '',
        $MACRO_Tab_Content_Class = '',
        $MACRO_Tab_Pane_Class = 'xs-col-12',
        $MACRO_Tab_Pane_Active = '',
        $MACRO_Tab_Id = '';
    public function handle1Opt($name, &$val) {
        switch (strtolower(str_replace('_', '', $name))) {
            case 'activetab':
                $this->setTabIsActive($val);
                return true;
            case 'tabid':
                $this->setTabId($val);
                return true;
        }
        return parent::handle1Opt($name, $val);
    }
    public function initElement(&$fn, $parent, $mainFormObj) {
        #dbg("FormTab_Pane::setDefaults($fn): Entering");
        parent::initElement($fn, $parent, $mainFormObj);
        if (!$this->getMacro('Tab_Id')) {
            $this->setTabId($fn);
        }
    }
    public function setTabIsActive($val) {
        if ($val) {
            $val = 'active';
        }
        $this->MACRO_Tab_Pane_Active = $val;
    }
    public function setTabId($val) {
        $this->MACRO_Tab_Id = $val;
    }
}
class US_Form_Form extends BaseForm {
    public $elementList = [
        'openForm', 'Fields', 'closeForm',
    ];
}
class US_Form_Col extends BaseForm {
    public $elementList = [
        'openCol', 'Fields', 'closeCol',
    ];
}
class US_Form_Row extends BaseForm {
    public $elementList = [
        'openRow', 'Fields', 'closeRow',
    ];
}
class US_Form_Panel extends BaseForm {
    public $elementList = [
        'openPanel', 'Heading', 'openBody', 'Title', 'Fields', 'closeBody', 'Footing', 'closePanel',
    ];
    protected $HTML_Head = '',
        $HTML_Foot = '';
    protected $HTML_Title = '
        <h4>{FORM_TITLE}</h4>
        ';
    protected $HTML_openPanel = '
        <div class="panel {PANEL_COLOR} {PANEL_CLASS}">';
    protected $HTML_openBody = '
        <div class="panel-body {BODY_CLASS}">';
    protected $HTML_closeBody = '
        </div> <!-- panel-body (title={FORM_TITLE}) -->
        ';
    protected $HTML_closePanel = '
        </div> <!-- panel -->
        ';
    public $MACRO_Panel_Class = '',
        $MACRO_Panel_Color = 'panel-default', // panel-primary, -success, -info, -warning, or -danger
        $MACRO_Body_Class = '',
        $MACRO_Head_Class = '',
        $MACRO_Foot_Class = '',
        $MACRO_Form_Title = ''; // if null it reads title from pages table which is not usually right for a panel
    public function handle1Opt($name, &$val) {
        switch (strtolower(str_replace('_', '', $name))) {
            case 'head':
            case 'header':
            case 'heading':
                $this->HTML_Head = $val;
                return true;
            case 'foot':
            case 'footer':
            case 'footing':
                $this->HTML_Foot = $val;
                return true;
        }
        return parent::handle1Opt($name, $val);
    }
    public function getHTMLHeading() {
        if ($this->HTML_Head) {
            return '<div class="panel-heading {HEAD_CLASS}">'.$this->HTML_Head.'</div>';
        } else {
            return '';
        }
    }
    public function getHTMLFooting() {
        if ($this->HTML_Foot) {
            return '<div class="panel-footer {FOOT_CLASS}">'.$this->HTML_Foot.'</div>';
        } else {
            return '';
        }
    }
}
class US_Form_Well extends BaseForm {
    public $elementList = [
        // since we are changing the order, maybe just re-use title?
        'openWell', 'Title', 'Fields', 'closeWell',
    ];
    protected $HTML_Title = '
        <h4>{FORM_TITLE}</h4>
        ';
    protected $HTML_openWell = '
        <div class="well {WELL_CLASS}">';
    protected $HTML_closeWell = '
        </div> <!-- well (title={FORM_TITLE}) -->
        ';
    public $MACRO_Well_Class = 'well-sm';
}
