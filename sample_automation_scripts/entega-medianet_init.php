public $baseUrl = 'http://www.entega-medianet.de/';
public $loginUrl = 'http://www.entega-medianet.de/';
public $invoicePageUrl = 'https://www.meineentega.de/start/secure/Meine-Produkte/Oekoenergie/Rechnungen.html';

public $username_selector = 'input#pkLogin_name';
public $password_selector = 'input#pkLogin_password';
public $remember_me_selector = '';
public $submit_login_selector = 'button.loginModal__button';

public $check_login_failed_selector = '.error-message.show-feedback,SELECTOR_error';
public $check_login_success_selector = 'a[href="/start/logout.html"]';

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
    if ($this->exts->getElement($this->check_login_success_selector) == null) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->openUrl($this->loginUrl);
        sleep(20);
        if ($this->exts->exists('button[id="accept-recommended-btn-handler"]')) {
            $this->exts->moveToElementAndClick('button[id="accept-recommended-btn-handler"]');
            sleep(20);
        }

        $this->exts->moveToElementAndClick('.metaNavigation'); // click login
        sleep(5);

        if ($this->exts->exists('button[class*="button--login"]')) {
            $this->exts->moveToElementAndClick('button[class*="button--login"]');
            sleep(5);
        }
        //
        $this->checkFillLogin();
        sleep(20);
    }

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

        $error_text = strtolower($this->exts->extract($this->check_login_failed_selector));

        $this->exts->log(__FUNCTION__ . '::Error text: ' . $error_text);
        if (stripos($error_text, strtolower('passwort')) !== false) {
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