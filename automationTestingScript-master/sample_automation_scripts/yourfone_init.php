public $baseUrl = 'https://service.yourfone.de';
public $loginUrl = 'https://service.yourfone.de';
public $invoicePageUrl = 'https://service.yourfone.de/mytariff/invoice/showAll';

public $username_selector = 'input[id*="UserLoginType_alias"]';
public $password_selector = 'input[id*="UserLoginType_password"]';
public $remember_me_selector = 'input[type*="checkbox"]';
public $submit_login_selector = 'a[onclick*="submitForm"]';

public $check_login_failed_selector = 'div.error.s-validation';
public $check_login_success_selector = 'div#logoutLink, span#logoutLink';

public $isNoInvoice = true;
/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    $this->exts->loadCookiesFromFile();
    sleep(1);
    $this->exts->openUrl($this->baseUrl);
    sleep(10);
    $this->exts->capture('1-init-page');

    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if ($this->exts->getElement($this->check_login_success_selector) == null) {
        $this->exts->log('NOT logged via cookie');
        $this->checkFillLogin();
        $this->exts->waitTillPresent($this->check_login_success_selector);
    }
    if ($this->exts->exists('div[class*="layout-wrap"] button[id*="submit_all"], button#consent_wall_optin')) {
        $this->exts->log("Accept Cookie");
        $this->exts->moveToElementAndClick('div[class*="layout-wrap"] button[id*="submit_all"], button#consent_wall_optin');
        sleep(15);
    }
    if ($this->exts->getElement($this->check_login_success_selector) != null) {
        sleep(3);
        $this->exts->log(__FUNCTION__ . '::User logged in');
        $this->exts->capture("3-login-success");

        if ($this->exts->exists('div.c-overlay-content a.c-overlay-close')) {
            $this->exts->moveToElementAndClick('div.c-overlay-content a.c-overlay-close');
            sleep(5);
        }


        if ($this->exts->exists('button#consent_wall_optout')) {
            $this->exts->moveToElementAndClick('button#consent_wall_optout');
            sleep(5);
        }

        if ($this->exts->exists('button#preferences_prompt_submit_all')) {
            $this->exts->moveToElementAndClick('button#preferences_prompt_submit_all');
            sleep(5);
        }

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        $this->exts->log(__FUNCTION__ . '::Use login failed');
        $this->exts->log(__FUNCTION__ . '::Last URL: ' . $this->exts->getUrl());

        $error_text = strtolower($this->exts->extract($this->check_login_failed_selector));

        $this->exts->log(__FUNCTION__ . 'error_text::  ' . $$error_text);

        if (stripos($error_text, strtolower('Die Angaben sind nicht korrekt.')) !== false) {
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}

private function checkFillLogin()
{
    if ($this->exts->getElement($this->password_selector) != null) {
        sleep(3);
        $this->exts->capture("2-login-page");

        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(1);

        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(1);

        if ($this->remember_me_selector != '')
            $this->exts->moveToElementAndClick($this->remember_me_selector);
        sleep(2);

        $this->exts->capture("2-login-page-filled");
        $this->exts->moveToElementAndClick($this->submit_login_selector);
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}

