public $baseUrl = 'https://service.vattenfall.de/vertragskonto';
public $loginUrl = 'https://service.vattenfall.de/login';
public $invoicePageUrl = 'https://service.vattenfall.de/postfach';

public $username_selector = 'input[id*="loginModel.username"]';
public $password_selector = 'input[id*="loginModel.password"]';
public $remember_me_selector = '';
public $submit_login_selector = 'form.cso-login-with-password button[type="submit"]';

public $check_login_failed_selector = 'input[id*="loginModel.password"]';
public $check_login_success_selector = 'li > a[href="/kontostandinformationen"][class="link navigation--link"]';

public $isNoInvoice = true;

private function initPortal(int $count): void
{
    $this->exts->log('Begin initPortal ' . $count);

    // Load cookies
    $this->exts->loadCookiesFromFile();
    $this->exts->openUrl($this->baseUrl);

    // For this portal, if not logged in, it will redirect to the login page.
    // For other portals that do not show the login page, check for the login page selector.
    // Don't just wait for check_login_success_selector because if the user is not logged in via cookie, it will wait for 15s.
    $this->exts->waitTillAnyPresent([$this->username_selector, $this->check_login_success_selector]);
    if ($this->exts->exists('button#onetrust-accept-btn-handler')) {
        $this->exts->moveToElementAndClick('button#onetrust-accept-btn-handler');
        sleep(3);
    }
    $this->exts->capture('1-init-page');

    // If the user has not logged in from cookie, do login.
    if ($this->exts->getElement($this->check_login_success_selector) === null) {
        $this->exts->log('NOT logged in via cookie');
        $this->exts->openUrl($this->loginUrl);
        sleep(10);

        // If it did not redirect to login page after opening baseUrl, open loginUrl and wait for login page.
        $this->checkFillLogin();
        sleep(10);
        $this->exts->waitTillPresent($this->check_login_success_selector); // Wait for login to complete
    }

    if ($this->exts->getElement($this->check_login_success_selector) !== null) {
        $this->exts->log(__FUNCTION__ . '::User logged in');
        $this->exts->capture("3-login-success");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        $this->exts->log(__FUNCTION__ . '::User login failed');
        $this->exts->log(__FUNCTION__ . '::Last URL: ' . $this->exts->getUrl());
        $this->exts->capture('login-failed');

        if ($this->exts->getElement($this->check_login_failed_selector) != null || $this->exts->getElement('.cso-box.cso-error-handler .link.link--custom.link--button') != null) {
            $this->exts->loginFailure(1);
        } else if (strpos(strtolower($this->exts->extract('p.server-error', null, 'innerText')), 'und ihrem passwort ist falsch') !== false) {
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}

private function checkFillLogin(): void
{
    sleep(2);
    if ($this->exts->getElement($this->password_selector) !== null) {
        $this->exts->capture("2-login-page");

        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);

        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector, $this->password);

        if ($this->remember_me_selector !== '') {
            $this->exts->moveToElementAndClick($this->remember_me_selector);
        }

        $this->exts->capture("2-login-page-filled");

        // $this->checkFillRecaptcha(); // Uncomment this line if there is reCaptcha in the login page.

        // Need to check if submit button exists because sometimes after solving captcha, login form is submitted automatically.
        if ($this->exts->exists($this->submit_login_selector)) {
            $this->exts->moveToElementAndClick($this->submit_login_selector);
        }
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}