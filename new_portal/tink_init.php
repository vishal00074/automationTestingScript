public $baseUrl = 'https://www.tink.de/';
public $loginUrl = 'https://www.tink.de/customer/account/login';
public $invoicePageUrl = 'https://www.tink.de/sales/order/history/';

public $username_selector = 'div[class*="login-form"] input[name="email"]';
public $password_selector = 'div[class*="login-form"] input[name="password"]';
public $remember_me_selector = '';
public $submit_login_selector = 'div[class*="login-form"] button[type="submit"]';

public $check_login_failed_selector = 'div.InfoBox_Error span[class*="BasicIcons-Error"]';
public $check_login_success_selector = 'ul.account_submenu a[href="/customer/account/logout/"]';

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
    $this->exts->loadCookiesFromFile();

    $this->exts->waitTillPresent('div.uc-banner-content button#uc-btn-deny-banner');
    if ($this->exts->exists('div.uc-banner-content button#uc-btn-deny-banner')) {
        $this->exts->moveToElementAndClick('div.uc-banner-content button#uc-btn-deny-banner');
        sleep(5);
    }

    if (!$this->checkLogin()) {
        $this->exts->log('NOT logged via cookie');

        $this->exts->clearCookies();
        $this->exts->openUrl($this->loginUrl);
        $this->fillForm(0);
        sleep(10);

        if ($this->exts->exists('button[class*="TopBrand__CloseWidgetButton"]')) {
            $this->exts->moveToElementAndClick('button[class*="TopBrand__CloseWidgetButton"]');
            sleep(5);
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
        $this->exts->log(__FUNCTION__ . '::Use login failed');
        $this->exts->log(__FUNCTION__ . '::Last URL: ' . $this->exts->getUrl());
        $this->exts->loginFailure();
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
        if ($this->exts->exists('button[class*="TopBrand__CloseWidgetButton"]')) {
            $this->exts->moveToElementAndClick('button[class*="TopBrand__CloseWidgetButton"]');
            sleep(5);
        }

        if ($this->exts->exists($this->remember_me_selector)) {
            $this->exts->click_by_xdotool($this->remember_me_selector);
            sleep(2);
        }

        $this->exts->capture("1-login-page-filled");
        if ($this->exts->exists($this->submit_login_selector)) {
            $this->exts->moveToElementAndClick($this->submit_login_selector);
            sleep(3);
        }
        $isErrorMessage = $this->exts->execute_javascript('document.body.innerHTML.includes("Invalid username or password.");');
        if ($isErrorMessage) {
            $this->exts->capture("login-failed-confirmed-1");
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            $this->exts->log(__FUNCTION__ . '::Last URL: ' . $this->exts->getUrl());
            $this->exts->loginFailure(1);
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
