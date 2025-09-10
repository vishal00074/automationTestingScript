public $baseUrl = 'https://mybill.dhl.com/';
public $loginUrl = 'https://mybill.dhl.com/login';
public $invoicePageUrl = 'https://mybill.dhl.com/invoicing/';

public $username_selector = 'input#id_email';
public $password_selector = 'input#id_password';
public $remember_me_selector = '';
public $submit_login_selector = 'button[type="submit"]';

public $check_login_failed_selector = 'div[class="fieldArea loginMessage"] span.errorMessage';
public $check_login_success_selector = 'a[href*="logout"]';

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
    sleep(10);
    $this->exts->capture('1-init-page');

    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if ($this->exts->querySelector($this->check_login_success_selector) == null) {
        $this->exts->log('NOT logged via cookie');
        // $this->exts->clearCookies();
        $this->exts->openUrl($this->loginUrl);
        sleep(15);
        if ($this->exts->querySelector('button#onetrust-accept-btn-handler') != null) {
            $this->exts->moveToElementAndClick('button#onetrust-accept-btn-handler');
            sleep(5);
        }
        $this->checkFillLogin();
        sleep(20);
    }

    // then check user logged in or not
    // for ($wait_count = 1; $wait_count <= 10 && $this->exts->getElementByCssSelector($this->check_login_success_selector) == null; $wait_count++) {
    //  $this->exts->log('Waiting for login...');
    //  sleep(5);
    // }
    if ($this->exts->querySelector($this->check_login_success_selector) != null) {
        sleep(3);
        $acountNotReady = strtolower($this->exts->extract('div#loginWindow h2'));
        if (stripos($acountNotReady, strtolower('Passwort')) !== false) {
            $this->exts->account_not_ready();
        }

        $this->exts->log(__FUNCTION__ . '::User logged in');
        $this->exts->capture("3-login-success");
        //

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        $this->exts->log(__FUNCTION__ . '::Use login failed');
        $logged_in_failed_selector = $this->exts->getElementByText('.errorText', ['Username or password incorrect', 'Benutzername oder Kennwort ist ungültig'], null, false);
        $logged_in_other_session = $this->exts->getElementByText('.errorText, .errorMessage', ['Unable to login, you already have an active session'], null, false);

        $error_text = strtolower($this->exts->extract($this->check_login_failed_selector));

        $this->exts->log(__FUNCTION__ . '::Error text: ' . $error_text);
        if ($logged_in_failed_selector != null) {
            $this->exts->loginFailure(1);
        } else if ($logged_in_other_session != null) {
            $this->exts->account_not_ready();
        } else if (stripos($error_text, strtolower('password')) !== false) {
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}

private function checkFillLogin()
{
    if ($this->exts->querySelector($this->password_selector) != null) {
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