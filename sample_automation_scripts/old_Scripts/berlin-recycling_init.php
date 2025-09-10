public $baseUrl = 'http://kundenportal.berlin-recycling.de/';
public $loginUrl = 'http://kundenportal.berlin-recycling.de/';
public $invoicePageUrl = 'URL_url_to_invoice_page';

public $username_selector = 'form input#Username, form input#userField';
public $password_selector = 'form input#Password, form input#passwordField';
public $remember_me_selector = '#PortalRemLogin';
public $submit_login_selector = 'form #textBtnGo, button#LogBtn';

public $check_login_failed_selector = '#PortalInfoCaption';
public $check_login_success_selector = 'a[onclick*="logout"]';

public $isNoInvoice = true;
/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    $this->exts->openUrl($this->baseUrl);
    sleep(1);

    // Load cookies
    $this->exts->loadCookiesFromFile();
    sleep(1);
    $this->exts->openUrl($this->baseUrl);
    sleep(15);
    if ($this->exts->exists('div.cc-compliance')) {
        $this->exts->moveToElementAndClick('div.cc-compliance');
        sleep(3);
    }
    $this->exts->capture('1-init-page');

    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if (!$this->exts->exists($this->check_login_success_selector)) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->openUrl($this->loginUrl);
        sleep(5);
        $this->checkFillLogin();
        sleep(15);
    }

    // then check user logged in or not
    // for ($wait_count = 1; $wait_count <= 10 && $this->exts->getElement($this->check_login_success_selector) == null; $wait_count++) {
    // 	$this->exts->log('Waiting for login...');
    // 	sleep(5);
    // }
    if ($this->exts->exists($this->check_login_success_selector)) {
        sleep(10);
        $this->exts->log(__FUNCTION__ . '::User logged in');
        $this->exts->capture("3-login-success");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }
        
        $this->exts->success();
    } else {
        $this->exts->log(__FUNCTION__ . '::Use login failed url: ' . $this->exts->getUrl());

        $this->exts->loginFailure();
    }
}

private function checkFillLogin()
{
    sleep(3);
    $this->exts->log(__FUNCTION__);
    $this->exts->capture(__FUNCTION__);
    if ($this->exts->exists($this->password_selector)) {
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
        $this->exts->waitTillPresent('div.notyf__message');
        if ($this->exts->exists("div.notyf__message")) {
            $this->exts->log("Login Failure : " . $this->exts->extract("div.notyf__message"));
            if (strpos(strtolower($this->exts->extract("div.notyf__message")), 'incorrect login data') !== false || strpos(strtolower($this->exts->extract("div.notyf__message")), '503: service unavailable') !== false) {
                $this->exts->loginFailure(1);
            }
        }

        sleep(10);
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}