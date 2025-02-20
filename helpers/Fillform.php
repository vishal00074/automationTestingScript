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
    public $loginUrl = "";
    public $submit_login_selector = "";
    

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


    private function checkFillLoginUndetected()
    {
        $this->exts->type_key_by_xdotool("Ctrl+t");
        sleep(13);

        $this->exts->type_key_by_xdotool("F5");

        sleep(5);

        $this->exts->type_text_by_xdotool($this->loginUrl);
        $this->exts->type_key_by_xdotool("Return");
        sleep(30);
        for ($i = 0; $i < 5; $i++) {
            $this->exts->type_key_by_xdotool("Tab");
            sleep(1);
        }
        $this->exts->type_key_by_xdotool("Tab");
        $this->exts->log("Enter Username");
        $this->exts->type_text_by_xdotool($this->username);
        $this->exts->capture("enter-username");
        $this->exts->type_key_by_xdotool("Return");
        sleep(5);
        $this->exts->type_key_by_xdotool("Tab");
        sleep(1);
        $this->exts->log("Enter Password");
        $this->exts->type_text_by_xdotool($this->password);
        $this->exts->capture("enter-password");
        sleep(5);
        $this->exts->log("Submit Login Form");
        $this->exts->moveToElementAndClick($this->submit_login_selector);
        $this->exts->capture("submit-login");
        sleep(5);
        $this->exts->type_key_by_xdotool("Tab");
        sleep(1);
        $this->exts->type_key_by_xdotool("Return");
        sleep(10);
    }
}
