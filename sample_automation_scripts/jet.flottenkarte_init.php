public $baseUrl = 'https://flottenkarte.jet-tankstellen.de/default.ixsp';
public $loginUrl = 'https://flottenkarte.jet-tankstellen.de/default.ixsp';
public $invoicePageUrl = 'https://flottenkarte.jet-tankstellen.de/default.ixsp';

public $username_selector = 'form#ID_frmLogin input[name="fr_LoginName"], form[name="frmLogin"] input[name="fr_LoginName"]';
public $password_selector = 'form#ID_frmLogin input[name="fr_Password"], form[name="frmLogin"] input[name="fr_Password"]';
public $remember_me_selector = '';
public $submit_login_selector = 'form#ID_frmLogin [type="submit"], form[name="frmLogin"] [type="submit"]';

public $check_login_failed_selector = 'div.Login_Error, .Login_InfoBox.InfoBox_Error .InfoBoxContent span.text';
public $check_login_success_selector = 'a[id*="ID_Logout"]';
public $user_customer_number = "";

public $isNoInvoice = true;
/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    $this->user_customer_number = isset($this->exts->config_array['customer_number']) ? trim($this->exts->config_array['customer_number']) : "";

    $this->exts->openUrl($this->baseUrl);
    sleep(1);

    // Load cookies
    $this->exts->loadCookiesFromFile();
    sleep(1);
    $this->exts->openUrl($this->baseUrl);
    sleep(10);
    if ($this->exts->exists('.uc-btn-accept-wrapper button#uc-btn-accept-banner')) {
        $this->exts->moveToElementAndClick('.uc-btn-accept-wrapper button#uc-btn-accept-banner');
        sleep(5);
    }
    $this->exts->capture('1-init-page');

    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    $this->exts->waitTillPresent($this->check_login_success_selector, 20);
    if ($this->exts->querySelector($this->check_login_success_selector) == null) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->openUrl($this->loginUrl);
        sleep(15);
        if ($this->exts->exists('.uc-btn-accept-wrapper button#uc-btn-accept-banner')) {
            $this->exts->moveToElementAndClick('.uc-btn-accept-wrapper button#uc-btn-accept-banner');
            sleep(5);
        }
        $this->checkFillLogin();
        sleep(20);
        if ($this->exts->type_key_by_xdotool('Return')) {
            $this->exts->log("accept Alert");
            sleep(10);
        }
    }

    $this->exts->waitTillPresent($this->check_login_success_selector, 20);
    if ($this->exts->querySelector($this->check_login_success_selector) != null) {
        sleep(3);
        $this->exts->log(__FUNCTION__ . '::User logged in');
        $this->exts->capture("3-login-success");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        $this->exts->log(__FUNCTION__ . '::Use login failed');
        if ($this->exts->querySelector($this->check_login_failed_selector) != null) {
            if (
                strpos(strtolower($this->exts->extract($this->check_login_failed_selector, null, 'innerText')), 'your username/password combination is not valid') !== false ||
                strpos(strtolower($this->exts->extract($this->check_login_failed_selector, null, 'innerText')), 'ihre benutzername/passwortkombination ist nicht') !== false
            ) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        } else {
            $this->exts->loginFailure();
        }
    }
}

private function checkFillLogin()
{
    if ($this->exts->querySelector($this->password_selector) != null) {
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