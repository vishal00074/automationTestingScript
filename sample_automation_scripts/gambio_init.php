public $baseUrl = 'https://www.gambio-support.de/';
public $loginUrl = 'https://www.gambio-support.de/';
public $invoicePageUrl = 'https://account.gambiocloud.com/de/invoices';

public $username_selector = 'input#email';
public $password_selector = 'input#password';
public $remember_me_selector = 'div.login form label[for="remember-me"]';
public $submit_login_selector = 'div.login form button[type="submit"]';

public $shop_username_selector = 'input[name="email_address"]';
public $shop_password_selector = 'input[name="password"]';
public $shop_submit_login_selector = 'form[name="login"] button[type="submit"], .dropdown-menu-login form input[type="submit"]';

public $check_login_failed_selector = 'div.alert-danger';
public $check_login_success_selector = 'a[href*="/logout"], a[href*="/logoff.php"]';

public $restrictPages = 3;
public $shopUrl = '';
public $sales_invoice = 0;
public $isNoInvoice = true;
/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);

    $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : $this->restrictPages;
    $this->shopUrl = isset($this->exts->config_array["shop_url"]) ? @$this->exts->config_array["shop_url"] : $this->shopUrl;
    $this->exts->log('Shop Url - ' . $this->shopUrl);
    $this->sales_invoice = isset($this->exts->config_array["sales_invoice"]) ? (int)@$this->exts->config_array["sales_invoice"] : $this->sales_invoice;
    $this->exts->log('sales_invoice - ' . $this->sales_invoice);

    if (!empty($this->shopUrl) && trim($this->shopUrl) != '') {
        $this->exts->openUrl($this->shopUrl);
        sleep(1);

        // Load cookies
        $this->exts->loadCookiesFromFile();
        sleep(1);
        $this->exts->openUrl($this->invoicePageUrl);
        sleep(10);
        $this->exts->capture('1-init-page');

        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if ($this->exts->getElement($this->check_login_success_selector) == null) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->openUrl($this->shopUrl);
            sleep(15);
            $this->exts->capture('shopurl-page');

            if (!$this->exts->exists($this->shop_username_selector) && !$this->exts->exists($this->shop_password_selector)) {
                $this->exts->openUrl($this->baseUrl);
                sleep(15);

                $this->checkFillLogin();
                sleep(20);
            }

            $this->checkFillShopLogin();
            sleep(20);
        }
    } else {
        $this->exts->openUrl($this->baseUrl);
        sleep(1);

        // Load cookies
        $this->exts->loadCookiesFromFile();
        sleep(1);
        $this->exts->openUrl($this->baseUrl);
        sleep(10);
        $this->exts->capture('1-init-page');

        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if ($this->exts->getElement($this->check_login_success_selector) == null) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->openUrl($this->loginUrl);
            sleep(15);
            $this->checkFillLogin();
            sleep(20);
        }
    }

    if ($this->exts->getElement($this->check_login_success_selector) != null) {
        sleep(3);
        $this->exts->log(__FUNCTION__ . '::User logged in');
        $this->exts->capture("3-login-success");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        $this->exts->log(__FUNCTION__ . '::Use login failed');
        if ($this->exts->getElement($this->check_login_failed_selector) != null && (stripos($this->exts->extract($this->check_login_failed_selector, null, 'innerText'), 'E-Mail-Adresse oder Passwort ist falsch') !== false || stripos($this->exts->extract($this->check_login_failed_selector, null, 'innerText'), 'Kein User mit dieser Email gefunden') !== false)) {
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}

private function checkFillLogin()
{
    if ($this->exts->getElement($this->password_selector) != null) {
        sleep(3);
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
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}

public function checkFillShopLogin()
{
    if ($this->exts->getElement($this->shop_password_selector) != null) {
        sleep(3);
        $this->exts->capture("2-login-page");

        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->shop_username_selector, $this->username);
        sleep(1);

        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->shop_password_selector, $this->password);
        sleep(1);

        $this->exts->capture("2-login-page-filled");
        $this->exts->moveToElementAndClick($this->shop_submit_login_selector);
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}