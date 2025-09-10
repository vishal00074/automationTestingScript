public $baseUrl = "https://selfserve.fleetcor.de/";
public $loginUrl = "https://selfserve.fleetcor.de/gfnsmewww/pages/public/login.aspx";
public $invoicePageUrl_01 = "https://simplyui-sme.azurewebsites.net/reports";
public $invoicePageUrl_02 = "https://selfserve.fleetcor.de/GFNSMEWWW/Client/Pages/User/DirectDebit.aspx";
public $username_selector = 'input#ctl00_MainBody_txtUserName, input[name="loginName"], input#username';
public $password_selector = 'input#ctl00_MainBody_txtPassword, input[name="password"], input#password';
public $submit_button_selector = 'input#ctl00_MainBody_btnLogin, #login-btn, button[type=submit]';
public $login_tryout = 0;
public $restrictPages = 3;
public $isNoInvoice = true;
public $summary_invoice = 0;

/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);

    $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
    $this->summary_invoice = isset($this->exts->config_array["summary_invoice"]) ? (int)@$this->exts->config_array["summary_invoice"] : 0;
    $this->exts->openUrl($this->baseUrl);
    sleep(2);
    $this->exts->capture("Home-page-without-cookie");

    $isCookieLoginSuccess = false;
    $this->exts->openUrl($this->invoicePageUrl_01);

    if ($this->checkLogin()) {
        $isCookieLoginSuccess = true;
    } else {
        $this->exts->openUrl($this->invoicePageUrl_02);

        if ($this->checkLogin()) {
            $isCookieLoginSuccess = true;
        } else {
            $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
        }
    }


    if (!$isCookieLoginSuccess) {
        $this->waitFor('button[id*=AllowAll]', 10);
        if ($this->exts->exists('button[id*=AllowAll]')) {
            $this->exts->click_element('button[id*=AllowAll]');
        }
        $this->fillForm(0);
        sleep(20);

        $mesg = strtolower($this->exts->extract('h2', null, 'innerText'));
        if (strpos($mesg, '404 ')  !== false && strpos($mesg, 'File or directory not found')  !== false) {
            $this->exts->capture("before- refresh-page");
            $this->exts->refresh();
            sleep(15);
        }

        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");
           
            if (!empty($this->exts->config_array['allow_login_success_request'])) {
                $this->exts->triggerLoginSuccess();
            }

            $this->exts->success();
        } else {
            $this->exts->capture("LoginFailed");
            if ($this->exts->querySelector('div.login-error-display.errMessage') != null) {
                $this->exts->loginFailure(1);
            } else if ($this->exts->getElementByText('div[class*="_error"]', 'Anmeldedaten werden nicht erkannt', null, false) != null) {
                $this->exts->loginFailure(1);
            }
            $this->exts->loginFailure();
        }
    } else {
        sleep(10);
        $this->exts->log(">>>>>>>>>>>>>>>Login successful with cookie!!!!");
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
        $this->waitFor($this->username_selector);
        if ($this->exts->exists($this->username_selector) && $this->exts->exists($this->password_selector)) {
            sleep(1);
            $this->login_tryout = (int)$this->login_tryout + 1;
            $this->exts->capture("2-pre-login");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);

            $this->exts->capture("2-filled-login");
            $this->exts->moveToElementAndClick($this->submit_button_selector);
            sleep(5);
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }

        sleep(10);
    } catch (\Exception $exception) {
        $this->exts->log("Exception filling loginform " . $exception->getMessage());
    }
}

public function waitFor($selector, $seconds = 7)
{
    for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
        $this->exts->log('Waiting for Selectors.....');
        sleep($seconds);
    }
}

private function checkLogin()
{
    $this->exts->log("Begin checkLogin ");
    $isLoggedIn = false;
    try {
        $this->waitFor('div[class*=profileButton] button');
        if ($this->exts->exists('div[class*=profileButton] button')) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $isLoggedIn = true;
        }
    } catch (Exception $exception) {
        $this->exts->log("Exception checking loggedin " . $exception);
    }
    return $isLoggedIn;
}