public $baseUrl = 'https://my.fastbill.com/index.php?cmd=1';
public $loginUrl = 'https://my.fastbill.com/index.php?cmd=1';
public $invoicePageUrl = 'https://my.fastbill.com/index.php?s=hteETP4i6c_3hfXW40owyUgyNVhGbHMMWS1C-3uI96w';

public $username_selector = 'form.card input[name="email"]';
public $password_selector = 'form.card input[name="password"]';
public $remember_me_selector = '';
public $submit_login_selector = 'form.card input[type="submit"]';

public $check_login_failed_selector = 'form.card .fielderror';
public $check_login_success_selector = 'a[href*="/logout"]';

public $isNoInvoice = true;
/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    $this->exts->openUrl($this->baseUrl);

    // Load cookies
    $this->exts->loadCookiesFromFile();

    $this->exts->openUrl($this->baseUrl);

    $this->exts->capture('1-init-page');

    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    $this->waitFor($this->check_login_success_selector);
    if ($this->exts->querySelector($this->check_login_success_selector) == null) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();
        $this->exts->openUrl($this->loginUrl);
        $this->waitFor("//button[.//text()[contains(., 'Zustimmen')]]");
        if ($this->exts->exists("//button[.//text()[contains(., 'Zustimmen')]]")) {
            $this->exts->click_element("//button[.//text()[contains(., 'Zustimmen')]]");
        }
        // $cookie_buttons = $this->exts->getElements('div[width="100%"] >div > button');
        // $this->exts->log('Finding Completted trips button...');
        // foreach ($cookie_buttons as $key => $cookie_button) {
        //     $tab_name = trim($cookie_button->getText());
        //     if (stripos($tab_name, 'Zustimmen') !== false) {
        //         $this->exts->log('Completted trips button found');
        //         $cookie_button->click();
        //         sleep(5);
        //         break;
        //     }
        // }
        $this->checkFillLogin();
        $this->waitFor('button#CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll');
        if ($this->exts->exists('button#CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll')) {
            $this->exts->moveToElementAndClick('button#CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll');
            sleep(2);
        }
    }
    $this->waitFor($this->check_login_success_selector);
    if ($this->exts->querySelector($this->check_login_success_selector) != null) {
        $this->exts->log(__FUNCTION__ . '::User logged in');
        $this->exts->capture("3-login-success");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        $this->exts->log(__FUNCTION__ . '::Use login failed');
        if (strpos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'passwor') !== false) {
            $this->exts->loginFailure(1);
        } else if (strpos(strtolower($this->exts->extract('.fielderror', null, 'text')), 'ist aufgetreten') !== false) {
            $this->exts->account_not_ready();
        } else if ($this->exts->urlContains('account-setup') && $this->exts->exists('div.account-setup__form')) {
            $this->exts->account_not_ready();
        } else {
            $this->exts->loginFailure();
        }
    }
}

public function waitFor($selector, $seconds = 7)
{
    for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
        $this->exts->log('Waiting for Selectors.....');
        sleep($seconds);
    }
}

private function checkFillLogin()
{
    $this->waitFor($this->password_selector);
    if ($this->exts->querySelector($this->password_selector) != null) {
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
        sleep(10);
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}