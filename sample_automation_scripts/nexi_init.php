public $baseUrl = 'https://login.nexi.de/';
public $loginUrl = 'https://login.nexi.de/';
public $invoicePageUrl = 'https://portal.nexi.de/web/Download';
public $username_selector = 'input#input-username';
public $password_selector = 'input#input-password';
public $remember_me_selector = '';
public $submit_login_selector = 'button#button-login';
public $check_login_failed_selector = 'span.text-danger';
public $check_login_success_selector = 'a[href*="Logout"], a[href="/web/documents"], li[data-menu-id*="-menu-dashboard"]';
public $isNoInvoice = true;
public $restrictPages = 3;
public $totalInvoices = 0;

private function initPortal($count)
{

    $this->exts->log('Begin initPortal ' . $count);
    $this->exts->loadCookiesFromFile();
    $this->exts->openUrl($this->loginUrl);
    sleep(5);
    if (!$this->checkLogin()) {
        $this->exts->log('NOT logged via cookie');
        if ($this->exts->exists('div#iubenda-cs-banner button.iubenda-cs-accept-btn, button#didomi-notice-agree-button')) {
            $this->exts->click_by_xdotool('div#iubenda-cs-banner button.iubenda-cs-accept-btn, button#didomi-notice-agree-button');
        }
        $this->fillForm(0);
        sleep(20);
    }

    if ($this->checkLogin()) {
        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
        $this->exts->capture("LoginSuccess");
        // $this->exts->success();

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }
        
        $this->exts->success();
    } else {
        if (stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'passwor') !== false) {
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
    try {
        if ($this->exts->querySelector($this->username_selector) != null) {

            $this->exts->capture("1-pre-login");
            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(1);

            if ($this->exts->exists($this->remember_me_selector)) {
                $this->exts->click_by_xdotool($this->remember_me_selector);
                sleep(1);
            }

            $this->exts->click_by_xdotool($this->submit_login_selector);
            sleep(2); // Portal itself has one second delay after showing toast
        }
    } catch (\Exception $exception) {

        $this->exts->log("Exception filling loginform " . $exception->getMessage());
    }
}

private function checkLogin()
{
    $this->exts->log("Begin checkLogin ");
    $isLoggedIn = false;
    try {
        $this->exts->waitTillPresent($this->check_login_success_selector, 20);
        if ($this->exts->exists($this->check_login_success_selector)) {

            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");

            $isLoggedIn = true;
        }
    } catch (Exception $exception) {

        $this->exts->log("Exception checking loggedin " . $exception);
    }

    return $isLoggedIn;
}