public $baseUrl = 'https://stanleystella.com/de-de/';
public $loginUrl = 'https://stanleystella.com/de-de/customer/account/login/';
public $invoicePageUrl = 'https://stanleystella.com/de-de/customer/invoices/';

public $username_selector = 'main input[name="login[username]"]';
public $password_selector = 'main input[name="login[password]"]';
public $remember_me_selector = '';
public $submit_login_selector = 'main button[type="submit"]';

public $check_login_failed_selector = 'div[class="message error"] span';
public $check_login_success_selector = 'nav a[href*="account/logout/"]';

public $isNoInvoice = true;

/**
 * Entry Method thats called for a portal
 * @param Integer $count Number of times portal is retried.
 */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    $this->exts->openUrl($this->baseUrl);
    sleep(5);
    $this->exts->waitTillPresent('button#PiwikPROConsentForm-agree-to-all', 10);
    if ($this->exts->exists('button#PiwikPROConsentForm-agree-to-all')) {
        $this->exts->moveToElementAndClick('button#PiwikPROConsentForm-agree-to-all');
        sleep(2);
    }
    $this->exts->loadCookiesFromFile();

    if (!$this->checkLogin()) {
        $this->exts->log('NOT logged via cookie');

        $this->exts->clearCookies();
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
        $this->exts->log(__FUNCTION__ . '::Use login failed');
        $this->exts->log(__FUNCTION__ . '::Last URL: ' . $this->exts->getUrl());

        $error_text = strtolower($this->exts->extract($this->check_login_failed_selector));

        $this->exts->log(__FUNCTION__ . '::Error text: ' . $error_text);
        if (
            stripos($error_text, strtolower('Die Anmeldedaten waren inkorrekt oder dein Konto ist noch nicht aktiviert. Bitte setze dein Passwort zurück, um Zugang zum neuen Webshop zu erhalten, indem du das detaillierte Verfahren befolgst.')) !== false ||
            stripos($error_text, strtolower('The login credentials were incorrect or your account is not yet activated. Please reset your password to access the new webshop by following the detailed procedure.')) !== false
        ) {
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