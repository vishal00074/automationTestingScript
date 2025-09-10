public $baseUrl = 'https://beraterwelt.fondsfinanz.de/#Dashboard';
public $loginUrl = 'https://beraterwelt.fondsfinanz.de/#Dashboard';
public $username_selector = 'input#loginId';
public $password_selector = 'input#password';
public $submit_login_selector = 'button.submit-button';
public $check_login_failed_selector = 'div.field-error-message';
public $check_login_success_selector = 'div.customers-initials';
public $isNoInvoice = true;
public $restrictPages = 3;
public $totalInvoices = 0;
/**

    * Entry Method thats called for a portal

    * @param Integer $count Number of times portal is retried.

    */
private function initPortal($count)
{
    $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int) @$this->exts->config_array["restrictPages"] : 3;
    $this->exts->log('Begin initPortal ' . $count);
    $this->exts->loadCookiesFromFile();
    sleep(3);
    $this->exts->openUrl($this->loginUrl);
    sleep(5);
    if (!$this->checkLogin()) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();
        $this->exts->openUrl($this->loginUrl);
        $this->waitFor('button#cookie-consent-submit-all-button', 5);
        if ($this->exts->exists('button#cookie-consent-submit-all-button')) {
            $this->exts->click_element('button#cookie-consent-submit-all-button');
        }
        $this->fillForm(0);
        sleep(30);
        if ($this->exts->exists($this->username_selector)) {
            $this->exts->openUrl($this->loginUrl);
            $this->fillForm(0);
            sleep(10);
        }
    }

    if ($this->checkLogin()) {
        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
        $this->exts->capture("LoginSuccess");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        if (stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'passwor') !== false) {
            $this->exts->log("Wrong credential !!!!");
            $this->exts->loginFailure(1);
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

private function fillForm($count)
{
    $this->exts->log("Begin fillForm " . $count);
    $this->waitFor($this->username_selector, 5);
    try {
        if ($this->exts->querySelector($this->username_selector) != null) {

            $this->exts->capture("1-pre-login");
            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(2);
            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(2);

            $this->exts->capture("1-login-page-filled");

            $this->waitFor($this->submit_login_selector, 10);
            if ($this->exts->exists($this->submit_login_selector)) {
                // $this->exts->click_element($this->submit_login_selector);
                $this->exts->execute_javascript("arguments[0].click();", [$this->exts->querySelector($this->submit_login_selector)]);
                sleep(5);
            }

            $this->waitFor($this->check_login_failed_selector, 20);
            if (stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'passwor') !== false) {
                $this->exts->log("Wrong credential !!!!");
                $this->exts->loginFailure(1);
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
private function checkLogin()
{
    $this->exts->log("Begin checkLogin ");
    $isLoggedIn = false;
    try {
        // i have set manual wait for element as it throws fatal error in test engine
        for (
            $wait = 0;
            $wait < 20
                && $this->exts->executeSafeScript("return !!document.querySelector('" . $this->check_login_success_selector . "');") != 1;
            $wait++
        ) {
            $this->exts->log('Waiting for login.....');
            sleep(3);
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

