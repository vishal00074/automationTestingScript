public $baseUrl = 'https://www.mobile.de';
public $loginUrl = 'https://www.mobile.de/api/auth/login';
public $invoicePageUrl = 'https://www.mobile.de/rechnung/herunterladen/?utmSource=invoice-email';

public $username_selector = 'input#login-username';
public $password_selector = 'input#login-password';
public $remember_me_selector = '';
public $submit_login_selector = 'button#login-submit';

public $check_login_failed_selector = 'div#login-error';
public $check_login_success_selector = 'button[data-testid="my-mobile-logout"]';

public $isNoInvoice = true;

/**<input type="password" name="password" autocomplete="current-password" class="textinput textInput" required id="id_password">

    * Entry Method thats called for a portal

    * @param Integer $count Number of times portal is retried.

    */
private function initPortal($count)
{

    $this->exts->log('Begin initPortal ' . $count);
    $this->disableExtension();

    $this->exts->loadCookiesFromFile();
    $this->exts->openUrl($this->baseUrl);

    $acceptAllBtn = 'button.mde-consent-accept-btn';

    $this->waitFor($acceptAllBtn, 7);
    if ($this->exts->exists($acceptAllBtn)) {
        $this->exts->click_element($acceptAllBtn);
    }

    if (!$this->checkLogin()) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();
        $this->exts->openUrl($this->loginUrl);

        $this->waitFor($acceptAllBtn, 7);
        if ($this->exts->exists($acceptAllBtn)) {
            $this->exts->click_element($acceptAllBtn);
        }
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
        if ($this->exts->exists($this->check_login_failed_selector)) {
            $this->exts->log("Wrong credential !!!!");
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
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

            if ($this->exts->exists($this->remember_me_selector)) {
                $this->exts->log("Remember Me");
                $this->exts->moveToElementAndClick($this->remember_me_selector);
                sleep(1);
            }

            $this->exts->capture("1-login-page-filled");
            sleep(5);

            if ($this->exts->exists($this->submit_login_selector)) {
                $this->exts->click_by_xdotool($this->submit_login_selector);
            }
        }
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

/**

    * Method to Check where user is logged in or not

    * return boolean true/false

    */
private function checkLogin()
{
    $this->exts->log("Begin checkLogin ");
    $isLoggedIn = false;
    try {
        $this->waitFor($this->check_login_success_selector, 15);
        if ($this->exts->exists($this->check_login_success_selector)) {

            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");

            $isLoggedIn = true;
        }
    } catch (Exception $exception) {
        $this->exts->log("Exception checking loggedin " . $exception);
    }

    return $isLoggedIn;
}


private function disableExtension()
{
    $this->exts->log('Disabling Accept all cookies extension!');
    $this->exts->openUrl('chrome://extensions/?id=ncmbalenomcmiejdkofaklpmnnmgmpdk');

    $this->waitFor('extensions-manager', 7);
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