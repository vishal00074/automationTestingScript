<?php

use GmiChromeManager;

class Fillform
{
    public $exts;
    public $username_selector = "";
    public $password_selector = '';

    public $submit_button_selector = "";
    public $username = "";
    public $password = "";

    public function __construct()
    {
        $this->exts = new GmiChromeManager;
    }


    public function fillForm()
    {
        $this->exts->capture("1-pre-login");

        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);

        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        $this->exts->capture("1-password-filled");
        $this->exts->moveToElementAndClick($this->submit_button_selector);
        sleep(6);
    }
}
