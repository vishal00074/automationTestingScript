public $baseUrl = 'http://www.urbansportsclub.com';
public $loginUrl = 'https://urbansportsclub.com/login';
public $invoicePageUrl = 'https://urbansportsclub.com/en/profile/payment-history';

public $username_selector = '.container form#login-form input#email';
public $password_selector = '.container form#login-form input#password';
public $remember_me_selector = '';
public $submit_login_selector = '.container form#login-form input.btn-lg';

public $check_login_failed_selector = '.container form#login-form .alert-danger';
public $check_login_success_selector = '.smm-navmenu a[href*="/logout"]';

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
    // after load cookies and open base url, check if user logged in
    // Wait for selector that make sure user logged in
    sleep(5);
    $this->checkAndLogin();
}

private function checkAndLogin()
{
    sleep(5);
    if ($this->exts->exists('button#onetrust-accept-btn-handler')) {
        $this->exts->moveToElementAndClick('button#onetrust-accept-btn-handler');
        sleep(2);
    }
    $this->exts->capture('1-init-page');

    // If user hase not logged in from cookie, open the login url and wait for login form
    if ($this->exts->getElement($this->check_login_success_selector) == null) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();
        $this->exts->openUrl($this->loginUrl);
        sleep(15);
        if ($this->exts->getElement($this->password_selector) != null) {
            sleep(3);
            $this->exts->capture("2-login-page");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(1);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(1);

            if ($this->exts->getElement($this->remember_me_selector) != null)
                $this->exts->moveToElementAndClick($this->remember_me_selector);
            sleep(2);

            $this->exts->capture("2-login-page-filled");
            $this->exts->moveToElementAndClick($this->submit_login_selector);
            sleep(20);
        } else {
            $this->exts->log('Login page not found');
            $this->exts->loginFailure();
        }
    }
    if ($this->exts->getElement($this->check_login_success_selector) != null) {
        sleep(3);
        $this->exts->log('User logged in');
        $this->exts->capture("3-login-success");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        $this->exts->log('Timeout waitForLogin');
        if ($this->exts->getElement($this->check_login_failed_selector) != null) {
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}