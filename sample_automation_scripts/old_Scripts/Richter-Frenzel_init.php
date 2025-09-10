public $baseUrl = 'https://rf24.de/';
public $loginUrl = 'https://rf24.de/login';
public $invoicePageUrl = 'https://rf24.de/mein-konto/vorgaenge';

public $username_selector = 'input[name="email"]';
public $password_selector = 'input[name="password"]';
public $remember_me_selector = '';
public $submit_login_selector = 'button[data-qa="login-button"]';

public $check_login_failed_selector = 'div[data-qa="login-form-error-message"] span.loginForm__errorMessageLabel';
public $check_login_success_selector = 'a[href="abmelden.htm"], a[href*="/mein-konto/"]';

public $isNoInvoice = true;
public $restrictPages = 3;
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
    sleep(10);
    $this->exts->capture('1-init-page');

    if ($this->exts->getElement('button[data-qa="cookie-banner-accept-all"]') != null) {
        $this->exts->moveToElementAndClick('button[data-qa="cookie-banner-accept-all"]');
        sleep(2);
    }

    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if ($this->exts->getElement($this->check_login_success_selector) == null) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->openUrl($this->loginUrl);
        sleep(15);
        if ($this->exts->getElement('button[data-qa="cookie-banner-accept-all"]') != null) {
            $this->exts->moveToElementAndClick('button[data-qa="cookie-banner-accept-all"]');
            sleep(2);
        }
        $this->checkFillLogin();
        sleep(20);
    }

    // then check user logged in or not
    // for ($wait_count = 1; $wait_count <= 10 && $this->exts->getElement($this->check_login_success_selector) == null; $wait_count++) {
    // 	$this->exts->log('Waiting for login...');
    // 	sleep(5);
    // }
    if ($this->exts->getElement($this->check_login_success_selector) != null) {
        sleep(3);
        $this->exts->log(__FUNCTION__ . '::User logged in');
        $this->exts->capture("3-login-success");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        $this->exts->log(__FUNCTION__ . '::Use login failed');
        if ($this->exts->getElement($this->check_login_failed_selector) != null) {
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