public $baseUrl = "https://www.ggmgastro.com/de-de-eur/";
public $loginUrl = "https://www.ggmgastro.com/de-de-eur/my-account/orders";
public $invoicePageUrl = 'https://www.ggmgastro.com/de-de-eur/my-account/orders';
public $username_selector = 'input[type="email"]';
public $password_selector = 'input[type="password"]';
public $remember_me_selector = '';
public $submit_button_selector = 'form button[type="submit"].from-primaryButtonColor';
public $check_login_failed_selector = 'div.error';
public $check_login_success_selector = 'a[data-test="logout"]';
public $login_tryout = 0;
public $isFailed = false;
public $isNoInvoice = true;
/**
* Entry Method thats called for a portal
* @param Integer $count Number of times portal is retried.
*/

private function initPortal($count)
{

    $this->exts->log('Begin initPortal ' . $count);
    $this->exts->loadCookiesFromFile();
    $this->exts->openUrl($this->baseUrl);

    if (!$this->checkLogin()) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();
        $this->exts->openUrl($this->loginUrl);
        sleep(5);
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
        if (!$this->isFailed) {
            if ($this->exts->exists($this->check_login_failed_selector)) {
                $this->exts->log("Wrong credential !!!!");
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }
}

function fillForm($count)
{
    $this->exts->log("Begin fillForm " . $count);
    $this->exts->waitTillPresent($this->username_selector, 20);

    if ($this->exts->querySelector($this->username_selector) != null) {

        $this->login_tryout = (int) $this->login_tryout + 1;
        $this->exts->capture("1-pre-login");
        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);

        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(1);

        if ($this->exts->exists($this->remember_me_selector)) {
            $this->exts->click_by_xdotool($this->remember_me_selector);
            sleep(2);
        }

        $this->exts->click_by_xdotool($this->submit_button_selector);
        sleep(1); // Portal itself has one second delay after showing toast

        $this->exts->log(__FUNCTION__ . '::Use login failed');
        $this->exts->log(__FUNCTION__ . '::Last URL: ' . $this->exts->getUrl());

        $error_text = strtolower($this->exts->extract('span.break-words'));
        if (
            stripos($error_text, strtolower('Die Kontoanmeldung war falsch oder Ihr Konto wurde vorübergehend deaktiviert. Bitte warten Sie und versuchen Sie es später erneut.')) !== false
        ) {
            $this->exts->log("Wrong credential !!!!");
            $this->exts->loginFailure(1);
        }
    }
}

/**
    * Method to Check where user is logged in or not
    * return boolean true/false
    */
public function checkLogin()
{
    $this->exts->log("Begin checkLogin ");
    $isLoggedIn = false;
    try {
        for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $this->check_login_failed_selector . "');") != 1; $wait++) {
            $this->exts->log('Waiting for login.....');
            sleep(10);
        }
        if ($this->exts->exists($this->check_login_failed_selector)) {
            $this->exts->log("Wrong credential !!!!");
            $this->isFailed = true;
            $this->exts->loginFailure(1);
        } else {

            for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $this->check_login_success_selector . "');") != 1; $wait++) {
                $this->exts->log('Waiting for login.....');
                sleep(10);
            }

            if ($this->exts->exists($this->check_login_success_selector)) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $isLoggedIn = true;
            }
        }
    } catch (Exception $exception) {
        $this->exts->log("Exception checking loggedin " . $exception);
    }

    return $isLoggedIn;
}