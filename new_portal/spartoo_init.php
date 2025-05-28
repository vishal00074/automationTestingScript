public $baseUrl = 'https://www.spartoo.com/securelogin.php?from=compte';
public $loginUrl = 'https://www.spartoo.com/securelogin.php?from=compte';
public $invoicePageUrl = '';

public $username_selector = 'input[name="email_address"]';
public $password_selector = 'input[name="password"]';
public $remember_me_selector = '';
public $submit_login_selector = 'button[id="loginVal"]';

public $check_login_failed_selector = 'div.messageStackError';
public $check_login_success_selector = 'div[id*="Orders"]';

public $isNoInvoice = true;

/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    $this->exts->openUrl($this->baseUrl);
    sleep(10);
    $this->exts->loadCookiesFromFile();

    if ($this->exts->exists('a#ld_close_div')) {
        $this->exts->moveToElementAndClick('a#ld_close_div');
        sleep(2);
    }

    if ($this->exts->exists('button[class="cookies_info-pop-buttons-accept"]')) {
        $this->exts->moveToElementAndClick('button[class="cookies_info-pop-buttons-accept"]');
        sleep(2);
    }

    if (!$this->checkLogin()) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();
        // $this->exts->openUrl($this->loginUrl);
        $this->fillForm(0);
    }

    if ($this->checkLogin()) {
        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
        $this->exts->capture("LoginSuccess");

        if ($this->exts->exists('a#ld_close_div')) {
            $this->exts->moveToElementAndClick('a#ld_close_div');
            sleep(2);
        }

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        $this->exts->log(__FUNCTION__ . '::Use login failed');
        $this->exts->log(__FUNCTION__ . '::Last URL: ' . $this->exts->getUrl());

        $error_text = strtolower($this->exts->extract($this->check_login_failed_selector));

        $this->exts->log(__FUNCTION__ . '::Error text: ' . $error_text);
        if (stripos($error_text, strtolower('Aucun résultat à cette adresse électronique et/ou mot de passe')) !== false) {
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
            sleep(2);
        }
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
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