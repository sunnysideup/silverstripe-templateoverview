<?php

/**
 *@author nicolaas[at]sunnysideup.co.nz
 *
 **/

class ErrorNotifierController extends Controller
{
    private static $allowed_actions = array(
        "senderror" => "ADMIN"
    );

    private static $email_to_send_error_to = '';

    protected $showThankYouNote = false;

    public function Form()
    {
        if ($this->request->requestVar('_REDIRECT_BACK_URL')) {
            $url = $this->request->requestVar('_REDIRECT_BACK_URL');
        } elseif ($this->request->getHeader('Referer')) {
            $url = $this->request->getHeader('Referer');
        } else {
            $url = Director::baseURL();
        }
        $folder = Folder::find_or_make("ErrorScreenshots");

        $whatDidYouTryDoField = new TextareaField('WhatDidYouTryToDo', 'What did you try to do');
        $whatDidYouTryDoField->setRows(3);

        $whatWentWrongField = new TextareaField('WhatWentWrong', 'What went wrong');
        $whatWentWrongField->setRows(3);

        $screenshotField = new FileField(
            'Screenshot',
            'To take a screenshot press the PRT SCR button on your keyboard, then open MS Word or MS Paint and paste the screenshot. Save the file and then attach (upload) the file here.'
        );
        $screenshotField->setFolderName($folder->Name);

        $form = new Form($this, 'Form',
            new FieldList(
                new TextField('Name'),
                new TextField('Email'),
                new TextField('URL', 'What is the URL of the page the error occured (this is the address shown in the address bar (e.g. http://www.mysite.com/mypage/with/errors/)', $url),
                $whatDidYouTryDoField,
                $whatWentWrongField,
                $screenshotField
            ),
            new FieldList(
                new FormAction('senderror', 'Submit Error')
            ),
            new RequiredFields(array("Email", "Name"))
        );
        return $form;
    }

    public function senderror($data, $form)
    {
        mail($this->Config()->get("email_to_send_error_to"), "error on ".Director::absoluteURL(), print_r($data, true));
        $this->showThankYouNote = true;
        return array();
    }

    public function ShowThankYouNote()
    {
        return $this->showThankYouNote;
    }

    public function Link($action = "")
    {
        return "/error/report/";
    }
}
