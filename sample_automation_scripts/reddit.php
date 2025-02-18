<?php // updated login code
// Server-Portal-ID: 7990 - Last modified: 22.01.2025 13:02:36 UTC - User: 1

/*Define constants used in script*/

public $baseUrl = 'https://www.reddit.com/login';
public $loginUrl = 'https://www.reddit.com/login';
public $invoicePageUrl = '';

public $username_selector = 'input[name="username"]';
public $password_selector = 'input[name="password"]';
public $remember_me_selector = '';
public $submit_login_selector = 'button.login';

public $check_login_failed_selector = 'faceplate-text-input#login-password[faceplate-validity="invalid"][faceplate-dirty]';
public $check_login_success_selector = "//span[contains(text(), 'Home')]";

public $isNoInvoice = true;

/**

    * Entry Method thats called for a portal

    * @param Integer $count Number of times portal is retried.

    */
private function initPortal($count)
{

    $this->exts->log('Begin initPortal ' . $count);
    $this->exts->loadCookiesFromFile();
    $this->exts->openUrl($this->loginUrl);
    sleep(10);
    if (!$this->checkLogin()) {
        $this->exts->log('NOT logged via cookie');
        $this->clearChrome();

        $this->exts->openUrl($this->loginUrl);

        $this->exts->waitTillPresent("a[href*='login']", 10);
        if ($this->exts->exists("a[href*='login']")) {
            $this->exts->log('Go to login page');
            $this->exts->click_element("a[href*='login']");
        }

        $this->exts->waitTillPresent("//button[contains(text(), 'Accept All')]", 10);
        if ($this->exts->exists("//button[contains(text(), 'Accept All')]")) {
            $this->exts->click_element("//button[contains(text(), 'Accept All')]");
        }
        
        $this->fillForm(0);
    }


    
    if ($this->checkLogin()) {
        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
        $this->exts->capture("LoginSuccess");
        
        // Final, check no invoice
        if ($this->isNoInvoice) {
            $this->exts->no_invoice();
        }

        $this->exts->success();
    } else {
    
        if ($this->exts->exists($this->check_login_failed_selector)) {
            $this->exts->log("Wrong credential !!!!");
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}

private function clearChrome()
{
    $this->exts->log("Clearing browser history, cookie, cache");
    $this->exts->openUrl('chrome://settings/clearBrowserData');
    sleep(10);
    $this->exts->capture("clear-page");
    for ($i = 0; $i < 2; $i++) {
        $this->exts->type_key_by_xdotool('Tab');
        sleep(1);
    }
    $this->exts->type_key_by_xdotool('Tab');
        sleep(1);
    $this->exts->type_key_by_xdotool('Return');
        sleep(1);
    $this->exts->type_key_by_xdotool('a');
    sleep(1);
    $this->exts->type_key_by_xdotool('Return');
    sleep(3);
    $this->exts->capture("clear-page");
    for ($i = 0; $i < 5; $i++) {
        $this->exts->type_key_by_xdotool('Tab');
        sleep(1);
    }
    $this->exts->type_key_by_xdotool('Return');
    sleep(15);
    $this->exts->capture("after-clear");
}

function fillForm($count)
{
    $this->exts->log("Begin fillForm " . $count);
    $this->exts->waitTillPresent($this->username_selector, 5);
    try {
        $inputUsernameExists = $this->exts->execute_javascript("
            (function() {
                var host1 = document.querySelector('faceplate-text-input#login-username');
                if (host1 && host1.shadowRoot) {
                    var input = host1.shadowRoot.querySelector('input[name=\"username\"]');
                    if (input) {
                        return 1;
                    }
                }
                return 0;
            })();
        ");
        
        if ($inputUsernameExists) {

            $this->exts->capture("1-pre-login");
            
            $this->exts->log("Enter Username");
            $this->exts->execute_javascript("
                var host1 = document.querySelector('faceplate-text-input#login-username');
                if (host1 && host1.shadowRoot) {
                    var input = host1.shadowRoot.querySelector('input[name=\"username\"]');
                    if (input) {
                        input.value = '".$this->username."';
                        input.dispatchEvent(new Event('change'));
                    }
                }
            ");
            
            sleep(2);

            $this->exts->log("Enter Password");
            $this->exts->execute_javascript("
                var host1 = document.querySelector('faceplate-text-input#login-password');
                if (host1 && host1.shadowRoot) {
                    var input = host1.shadowRoot.querySelector('input[name=\"password\"]');
                    if (input) {
                        input.value = '".$this->password."';
                        input.dispatchEvent(new Event('change'));
                    }
                }
            ");
            sleep(1);

            $this->exts->capture("1-login-page-filled");
            sleep(5);
            
            $this->exts->log("Click Login");
            if ($this->exts->exists($this->submit_login_selector)) {
                $this->exts->click_by_xdotool($this->submit_login_selector);
            }
        }
    } catch (\Exception $exception) {
        $this->exts->log("Exception filling loginform " . $exception->getMessage());
    }
}

/**

    * Method to Check where user is logged in or not

    * return boolean true/false

    */
function checkLogin()
{
    $this->exts->log("Begin checkLogin ");
    $isLoggedIn = false;
    try {

        $this->exts->waitTillPresent("a[href*='login']", 10);
        if ($this->exts->exists("a[href*='login']")) {
            $this->exts->log('Go to login page');
            $this->exts->click_element("a[href*='login']");
            sleep(7);
            $this->fillForm(1);
            sleep(5);
        }
       

        $this->exts->waitTillPresent($this->check_login_success_selector, 20);
        if ($this->exts->exists($this->check_login_success_selector)) {

            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");

            $isLoggedIn = true;
        }
    } catch (Exception $exception) {
        $this->exts->log("Exception checking loggedin " . $exception);
    }

    return $isLoggedIn;
}