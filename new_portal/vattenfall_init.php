public $baseUrl = 'https://portal.realestate.vattenfall.de/';
public $loginUrl = 'https://portal.realestate.vattenfall.de/';
public $invoicePageUrl = '';

public $username_selector = 'input[name="username"]';
public $password_selector = 'input[name="password"]';
public $remember_me_selector = '';
public $submit_login_selector = 'input[name="login"]';

public $check_login_failed_selector = 'span[class*="kc-feedback-text"]';
public $check_login_success_selector = 'li a[href*="bundleCustomerNumber"]';

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
    if ($this->exts->exists('div.cookie-container button')) {
        $this->exts->moveToElementAndClick('div.cookie-container button');
        sleep(7);
    }
    $this->exts->loadCookiesFromFile();
    if (!$this->checkLogin()) {
        $this->exts->log('NOT logged via cookie');

        $this->exts->clearCookies();
        sleep(5);
        $this->exts->openUrl($this->loginUrl);
        sleep(10);
        if ($this->exts->exists('div.cookie-container button')) {
            $this->exts->moveToElementAndClick('div.cookie-container button');
            sleep(7);
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
        if (stripos($this->exts->extract($this->check_login_failed_selector), 'Der Zugriff wurde verweigert. Bitte überprüfen Sie Ihre Eingabedaten.') !== false) {
            $this->exts->log("Wrong credential !!!!");
            $this->exts->loginFailure(1);
        } elseif (stripos($this->exts->extract($this->check_login_failed_selector), 'Die Aktion ist nicht mehr gÃ¼ltig. Bitte fahren Sie nun mit der Anmeldung fort.') !== false) {
            $this->exts->account_not_ready();
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
                sleep(5);
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