public $baseUrl = 'https://quaderno.io/';
public $loginUrl = 'https://quadernoapp.com/login';
public $invoicePageUrl = 'https://fxforaliving.quadernoapp.com/invoices';
public $billingPageUrl = 'https://ninive-7362.quadernoapp.com/settings/payment-history';

public $username_selector = 'input#user_email';
public $password_selector = 'input#user_password';
public $remember_me_selector = '';
public $submit_login_selector = 'input[type="submit"]';

public $check_login_failed_selector = 'div.alerts.error';
public $check_login_success_selector = 'a[href*="/logout"]';

public $isNoInvoice = true;
public $only_sales_invoice = 0;
public $restrictPages = 3;

public $total_invoices = 0;
/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    sleep(1);
    $this->only_sales_invoice = isset($this->exts->config_array["only_sales_invoice"]) ? (int)$this->exts->config_array["only_sales_invoice"] : $this->only_sales_invoice;
    $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;

    $this->exts->log('restrictPages:: ' .  $this->restrictPages);
    $this->exts->log('only_sales_invoice:: ' .  $this->only_sales_invoice);

    $this->exts->openUrl($this->baseUrl);
    sleep(10);

    // Load cookies
    // $this->exts->loadCookiesFromFile();
    if ($this->exts->exists('#onetrust-accept-btn-handler')) {
        $this->exts->moveToElementAndClick('#onetrust-accept-btn-handler');
    }
    sleep(2);
    $this->exts->capture('1-init-page');

    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if ($this->exts->getElement($this->check_login_success_selector) == null) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();
        $this->exts->openUrl($this->loginUrl);
        sleep(5);
        $this->checkFillLogin();
        sleep(20);
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
        if (strpos(strtolower($this->exts->extract('div.alert-message')), 'an error processing the form') !== false) {
            $this->exts->loginFailure(1);
        } else if (!filter_var($this->username, FILTER_VALIDATE_EMAIL)) {
            $this->exts->log("Username is not a valid email address");
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}

private function checkFillLogin()
{
    $this->exts->waitTillPresent($this->username_selector);
    if ($this->exts->getElement($this->password_selector) != null) {

        $this->exts->capture("2-login-page");

        $this->exts->log("Enter Username");
        //$this->exts->moveToElementAndType($this->username_selector, $this->username);
        $this->exts->click_by_xdotool($this->username_selector);
        $this->exts->type_key_by_xdotool("ctrl+a");
        $this->exts->type_key_by_xdotool("Delete");
        $this->exts->type_text_by_xdotool($this->username);
        //sleep(1);
        sleep(2);

        $this->exts->log("Enter Password");
        //$this->exts->moveToElementAndType($this->password_selector, $this->password);
        $this->exts->click_by_xdotool($this->password_selector);
        $this->exts->type_key_by_xdotool("ctrl+a");
        $this->exts->type_key_by_xdotool("Delete");
        $this->exts->type_text_by_xdotool($this->password);
        sleep(2);

        // if($this->remember_me_selector != ''){
        //  $this->exts->moveToElementAndClick($this->remember_me_selector);
        //  sleep(2);
        // }

        $this->exts->capture("2-login-page-filled");
        sleep(5);
        $this->exts->click_by_xdotool($this->submit_login_selector);
        //$this->exts->moveToElementAndClick($this->submit_login_selector);
        sleep(10);
        if ($this->exts->getElement($this->password_selector) != null) {
            $this->checkFillLoginUndetected();
        }
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}


private function checkFillLoginUndetected()
{
    $this->exts->waitTillPresent($this->username_selector);
    $this->exts->log(__FUNCTION__);
    if ($this->exts->exists($this->username_selector)) {
        sleep(2);

        $this->exts->log("Enter Username");

        $this->exts->click_by_xdotool($this->username_selector);
        sleep(2);
        $this->exts->type_text_by_xdotool($this->username);
        sleep(2);

        $this->exts->log("Enter Password");

        $this->exts->click_by_xdotool($this->password_selector);
        sleep(2);
        $this->exts->type_text_by_xdotool($this->password);
        sleep(2);

        if ($this->exts->querySelector($this->submit_login_selector) != null) {
            $this->exts->execute_javascript("document.querySelector(\"input[type='submit']\")?.click();");
            sleep(10);
        }
    }
}