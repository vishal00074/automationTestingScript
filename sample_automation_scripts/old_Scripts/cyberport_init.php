public $baseUrl = "https://www.cyberport.de/";
public $accountUrl = "https://www.cyberport.de/tools/my-account/meine-daten";
public $loginUrl = "https://www.cyberport.de/";
public $username_selector = 'form.loginForm input[name="j_username"], form input[name="username"]';
public $password_selector = 'form.loginForm input[name="j_password"], form input[name="password"]';
public $submit_button_selector = 'form.loginForm button[type="submit"], form button[type=submit]';
public $check_login_success_selector = 'form#customer-profile-form, header #headerContainer > div > div > div > div:nth-child(2) > div > button:nth-child(3)';
public $login_tryout = 0;
public $restrictPages = 3;
public $isNoInvoice = true;
public $restrictDate = '';
public $dateRestriction = true;
public $maxInvoices = 10;
public $invoiceCount = 0;
public $terminateLoop = false;
public $totalInvoices = 0;


private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    $this->disableExtension();
    $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;

    $this->exts->openUrl($this->baseUrl);
    sleep(4);
    // Load cookies
    $this->exts->loadCookiesFromFile();

    $this->exts->openUrl($this->baseUrl);
    sleep(7);

    $this->check_solve_blocked_page();

    $accecptAllBtn = 'button#consent-accept-all';
    $this->exts->waitTillPresent($accecptAllBtn, 10);
    if ($this->exts->exists($accecptAllBtn)) {
        $this->exts->click_element($accecptAllBtn);
        sleep(7);
    }



    if (!$this->checkLogin()) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();
        $this->exts->openUrl($this->baseUrl);
        sleep(5);
        $this->check_solve_blocked_page();

        $this->exts->waitTillPresent($accecptAllBtn, 15);
        if ($this->exts->exists($accecptAllBtn)) {
            $this->exts->click_element($accecptAllBtn);
            sleep(10);
        }

        $this->fillForm(0);
        sleep(15);

        if (
            strpos(strtolower($this->exts->extract('form .text-info.text-error')), 'mit diesen zugangsdaten ist eine anmeldung nicht') !== false) {
            $this->exts->loginFailure(1);
        } else if (strpos(strtolower($this->exts->extract('form.loginForm  div.notification-error')), 'eine anmeldung ist mit diesen zugangsdaten nicht') !== false) {
            $this->exts->loginFailure(1);
        } else {
            $this->exts->moveToElementAndClick('header #headerContainer');
            sleep(10);
        }

        if (!$this->checkLogin()) {
            $this->exts->openUrl($this->accountUrl);
            sleep(10);
            $this->exts->openUrl($this->accountUrl);
            sleep(20);
        }

        sleep(2);
        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");

            if (!empty($this->exts->config_array['allow_login_success_request'])) {
                $this->exts->triggerLoginSuccess();
            }

            $this->exts->success();
        } else {
            $this->exts->capture("LoginFailed");
            $this->exts->loginFailure();
        }
    } else {
        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
        $this->exts->capture("LoginSuccess");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    }
}

private function fillForm($count)
{
    $this->exts->log("Begin fillForm " . $count);
    try {
        sleep(5);
        $this->exts->moveToElementAndClick('header #headerContainer');
        sleep(5);
        if ($this->exts->getElement($this->username_selector) != null) {
            sleep(2);
            $this->login_tryout = (int)$this->login_tryout + 1;
            $this->exts->capture("1-pre-login");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(5);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(5);
            $this->exts->capture("1-login-filled");

            $this->exts->waitTillPresent($this->submit_button_selector, 20);
            if ($this->exts->exists($this->submit_button_selector)) {
                $this->exts->click_element($this->submit_button_selector);
            }
            sleep(10);
            $this->exts->type_key_by_xdotool('Return');
            $this->exts->capture("1-login-after-submit");
        }
    } catch (\Exception $exception) {
        $this->exts->log("Exception filling loginform " . $exception->getMessage());
    }
}

/**
    * Method to Check where user is logged in or not
    * return boolean true/false
    */


private function waitForSelectors($selector, $max_attempt, $sec)
{
    for (
        $wait = 0;
        $wait < $max_attempt && $this->exts->executeSafeScript("return !!document.querySelector(\"" . $selector . "\");") != 1;
        $wait++
    ) {
        $this->exts->log('Waiting for Selectors!!!!!!');
        sleep($sec);
    }
}

private function checkLogin()
{
    $this->exts->log("Begin checkLogin ");
    $isLoggedIn = false;
    try {
        sleep(15);
        $this->waitForSelectors($this->check_login_success_selector, 10, 2);
        sleep(2);
        if ($this->exts->exists($this->check_login_success_selector)) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $isLoggedIn = true;
        }
    } catch (Exception $exception) {

        $this->exts->log("Exception checking loggedin " . $exception);
    }
    return $isLoggedIn;
}

private function check_solve_blocked_page()
{
    $this->exts->capture_by_chromedevtool("blocked-page-checking");

    for ($i = 0; $i < 5; $i++) {
        if ($this->exts->check_exist_by_chromedevtool('div[style="display: grid;"] > div > div')) {
            $this->exts->capture_by_chromedevtool("blocked-by-cloudflare");
            $this->exts->refresh();
            sleep(10);

            $this->exts->click_by_xdotool('div[style="display: grid;"] > div > div', 30, 28);
            sleep(15);

            if (!$this->exts->check_exist_by_chromedevtool('div[style="display: grid;"] > div > div')) {
                break;
            }
        } else {
            break;
        }
    }
}


private function disableExtension()
{
    $this->exts->log('Disabling Accept all cookies extension!');
    $this->exts->openUrl('chrome://extensions/?id=ncmbalenomcmiejdkofaklpmnnmgmpdk');

    $this->exts->waitTillPresent('extensions-manager', 15);
    if ($this->exts->exists('extensions-manager')) {
        $this->exts->execute_javascript("
        var button = document
                    .querySelector('extensions-manager')
                    ?.shadowRoot?.querySelector('extensions-detail-view')
                    ?.shadowRoot?.querySelector('cr-toggle') || null;
                        
        if (button) {
            button.click();
        }
    ");
    }
}