public $baseUrl = 'https://www.drukwerkdeal.nl/nl/account';
public $loginUrl = 'https://www.drukwerkdeal.nl/nl/inloggen';
public $invoicePageUrl = 'https://www.drukwerkdeal.nl/nl/account/invoices?month=';

public $username_selector = 'input[class="pd-input__input"][name="email"]';
public $password_selector = 'input[class="pd-input__input"][name="password"]';
public $remember_me_selector = '';
public $submit_login_selector = 'button[type="submit"].pd-button';

public $check_login_failed_selector = 'div.pd-alert--error';
public $check_login_success_selector = 'div#side-bar a[href="/nl/account/orders"]';

public $isNoInvoice = true;

/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    $this->exts->openUrl($this->baseUrl);
    sleep(2);
    $this->exts->waitTillPresent('button#CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll', 7);

    if ($this->exts->exists('button#CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll')) {
        $this->exts->moveToElementAndClick('button#CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll');
        sleep(7);
    }
    $this->exts->loadCookiesFromFile();
    if (!$this->checkLogin()) {
        $this->exts->log('NOT logged via cookie');

        $this->exts->clearCookies();
        sleep(5);
        $this->exts->openUrl($this->loginUrl);
        $this->fillForm(0);
    }
    if ($this->checkLogin()) {
        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
        $this->exts->capture("LoginSuccess");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        if (stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), strtolower('Je opgegeven wachtwoord klopt niet. Probeer het opnieuw met een ander wachtwoord of stel je wachtwoord opnieuw in.')) !== false) {
            $this->exts->log("Wrong credential !!!!");
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}

public function fillForm($count)
{
    $this->exts->log("Begin fillForm " . $count);

    $this->exts->waitTillPresent($this->username_selector);
    try {
        if ($this->exts->querySelector($this->username_selector) != null) {

            $this->exts->capture("1-pre-login");
            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(2);
            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(2);

            if ($this->exts->exists($this->remember_me_selector)) {
                $this->exts->click_by_xdotool($this->remember_me_selector);
                sleep(2);
            }

            $this->exts->capture("1-login-page-filled");

            if ($this->exts->exists($this->submit_login_selector)) {
                $this->exts->moveToElementAndClick($this->submit_login_selector);
                sleep(10);
            }
        } else {
            $this->exts->log("Login page not found");
            for ($i = 0; $i < 10; $i++) {
                $this->exts->waitTillPresent('a[href*="login"][target="_self"]');
                $this->exts->moveToElementAndClick('a[href*="login"][target="_self"]');
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
public  function checkLogin()
{
    $this->exts->log("Begin checkLogin ");
    $isLoggedIn = false;
    try {
        for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $this->check_login_success_selector . "');") != 1; $wait++) {
            $this->exts->log('Waiting for login.....');
            sleep(10);
        }
        if ($this->exts->exists($this->check_login_success_selector)) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $isLoggedIn = true;
        }
    } catch (Exception $exception) {

        $this->exts->log("Exception checking loggedin " . $exception);
    }
    return $isLoggedIn;
}