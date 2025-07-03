public $baseUrl = 'https://www.webfleet.com/';
public $loginUrl = 'https://www.webfleet.com/webfleet/products/login/';
public $invoicePageUrl = 'https://live.webfleet.com/web/index.html#/invoices';

public $username_selector = 'form#frmWebfleetLogin input[name="username"],  form#kc-form-login input[name="useraccount"]';
public $password_selector = 'form#frmWebfleetLogin input[name="password"], form#kc-form-login input[name="password"]';
public $remember_me_selector = 'form#frmWebfleetLogin input[name="rememberme"]';
public $submit_login_selector = 'form#frmWebfleetLogin button#formWebfleetLoginSubmit, form#kc-form-login button#submit_btn';

public $username_selector_new = 'input#useraccount';
public $password_selector_new = 'input#password';
public $remember_me_selector_new = 'input#rememberMe';
public $submit_login_selector_new = 'button#submit_btn';

public $check_login_failed_selector = '#wf-login-content .kc-feedback-text';
public $check_login_success_selector = 'a[href*="/invoices"]';

// added the webfleet account hardcoded 
public $webfleet_account_name = '';

public $isNoInvoice = true;
/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    $this->webfleet_account_name = (isset($this->exts->config_array["webfleet_account_name"]) && !empty($this->exts->config_array["webfleet_account_name"])) ? $this->exts->config_array["webfleet_account_name"] : $this->webfleet_account_name;

    // I have added hardcoded value for account name
    $this->webfleet_account_name = 'blumenwelt';
    
    // $this->exts->openUrl($this->baseUrl);
    // sleep(1);
    // Load cookies
    $this->exts->loadCookiesFromFile();
    sleep(1);
    $this->exts->openUrl($this->loginUrl);
    sleep(10);
    $this->exts->capture('1-init-page');
    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if ($this->exts->querySelector($this->check_login_success_selector) == null) {
        $this->exts->log('NOT logged via cookie');
        // $this->exts->clearCookies();
        $this->exts->openUrl($this->loginUrl);
        $this->checkFillLogin();
        sleep(20);
    }
    $this->exts->waitTillPresent($this->check_login_success_selector);
    if ($this->exts->querySelector($this->check_login_success_selector) != null) {
        sleep(3);
        $this->exts->log(__FUNCTION__ . '::User logged in');
        $this->exts->capture("3-login-success");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        $this->exts->log(__FUNCTION__ . '::Use login failed');
        $this->exts->log(__FUNCTION__ . '::Last URL: ' . $this->exts->getUrl());
        if (stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'account name or password') !== false) {
            $this->exts->loginFailure(1);
        } else if (stripos($this->exts->extract('.kc-feedback-text', null, 'innerText'), 'nvalid username or password') !== false || stripos($this->exts->extract('.kc-feedback-text', null, 'innerText'), 'Benutzername oder Passwort') !== false) {
            $this->exts->loginFailure(1);
        } else if ($this->webfleet_account_name === '') {
            $this->exts->loginFailure(1);
        } else if ($this->exts->exists('section#step1-2fa-apps')) {
            $this->exts->account_not_ready();
        } else {
            $this->exts->loginFailure();
        }
    }
}

private function checkFillLogin()
{
    $this->exts->waitTillPresent($this->password_selector);
    if ($this->exts->querySelector($this->password_selector) != null) {
        sleep(3);
        $this->exts->capture("2-login-page");

        $this->exts->log("Enter Accountname");
        $this->exts->moveToElementAndType('form#kc-form-login input[name="accountname"]', $this->webfleet_account_name);
        sleep(1);

        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(1);

        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(1);

        if ($this->remember_me_selector != '') {
            $this->exts->moveToElementAndClick($this->remember_me_selector);
        }
        sleep(2);

        $this->exts->capture("2-login-page-filled");
        $this->exts->moveToElementAndClick($this->submit_login_selector);
    } else if ($this->exts->querySelector($this->password_selector_new) != null) {
        sleep(3);
        $this->exts->capture("2-login-page");

        $this->exts->log("Enter Accountname : " . $this->webfleet_account_name);
        $this->exts->moveToElementAndType('input#accountname', $this->webfleet_account_name);
        sleep(1);

        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector_new, $this->username);
        sleep(1);

        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector_new, $this->password);
        sleep(1);

        if ($this->remember_me_selector != '') {
            $this->exts->moveToElementAndClick($this->remember_me_selector_new);
        }
        sleep(2);

        $this->exts->capture("2-login-page-filled");
        $this->exts->moveToElementAndClick($this->submit_login_selector_new);
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}