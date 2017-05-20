<?php
/* Feel free to customize these classes */
class FormField_ButtonAnchor  extends US_FormField_ButtonAnchor {
}
class FormField_ButtonSubmit  extends US_FormField_ButtonSubmit {
}
class FormField_ButtonDelete  extends US_FormField_ButtonDelete {
}
class FormField_Checkbox      extends US_FormField_Checkbox {
}
class FormField_Checklist     extends US_FormField_Checklist {
}
# "MultiCheckbox" is an alias for "Checklist" (note it extends FormField_Checklist
# and not US_FormField_Checklist)
class FormField_MultiCheckbox extends FormField_Checklist {
    # DO NOT MAKE CHANGES HERE - make changes to FormField_Checklist above
    # unless you are deliberately separating the functionality of what was
    # originally designed to be a simple alias
}
class FormField_File           extends US_FormField_File {
}
class FormField_Hidden         extends US_FormField_Hidden {
}
class FormField_HTML           extends US_FormField_HTML {
}
class FormField_MultiHidden    extends US_FormField_MultiHidden {
}
class FormField_Password       extends US_FormField_Password {
}
class FormField_Radio          extends US_FormField_Radio {
}
class FormField_ReCaptcha      extends US_FormField_ReCaptcha {
}
class FormField_SearchQ        extends US_FormField_SearchQ {
}
class FormField_Select         extends US_FormField_Select {
}
class FormField_SigninFacebook extends US_FormField_SigninFacebook {
}
class FormField_SigninGoogle   extends US_FormField_SigninGoogle {
}
class FormField_Table          extends US_FormField_Table {
}
class FormField_TabToC         extends US_FormField_TabToC {
}
class FormField_Text           extends US_FormField_Text {
}
class FormField_Textarea       extends US_FormField_Textarea {
}
