public $baseUrl = 'https://balsamiq.cloud';
public $loginUrl = 'https://balsamiq.cloud/#login';
public $invoicePageUrl = 'https://balsamiq.cloud';

public $username_selector = 'input#dialog-login-email';
public $password_selector = 'input#dialog-login-password';
public $remember_me_selector = '';
public $submit_login_btn = 'button#dialog-login-submit';

public $checkLoginFailedSelector = '#dialog-login-error';
public $checkLoggedinSelector = 'div[class*="userview__myUser"], div.lcBody form[action="/logout"]';

public $isNoInvoice = true;
/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    // $this->fake_user_agent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/97.0.4692.71 Safari/537.36');
    $this->exts->openUrl($this->baseUrl);
    sleep(1);
    $this->exts->capture("Home-page-without-cookie");

    // Load cookies
    $this->exts->loadCookiesFromFile();
    sleep(1);
    $this->exts->openUrl($this->baseUrl);
    // after load cookies and open base url, check if user logged in

    // Wait for selector that make sure user logged in
    $this->exts->waitTillPresent($this->checkLoggedinSelector);

    if ($this->exts->getElement($this->checkLoggedinSelector) != null) {
        // If user has logged in via cookies, call waitForLogin
        $this->exts->log('Logged in from initPortal');
        $this->exts->capture('0-init-portal-loggedin');
        $this->waitForLogin();
    } else {
        // If user hase not logged in, open the login url and wait for login form
        $this->exts->log('NOT logged in from initPortal');
        $this->exts->capture('0-init-portal-not-loggedin');

        $this->exts->openUrl($this->loginUrl);
        sleep(15);
        for ($i = 0; $i < 5 && $this->exts->exists('div#balsamiq-loading-screen'); $i++) {
            sleep(15);
        }
        $this->waitForLoginPage();
    }
}

private function waitForLoginPage()
{
    if ($this->exts->getElement($this->password_selector) != null) {
        $this->exts->capture("1-pre-login");

        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(1);

        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(2);

        $this->exts->capture("1-filled-login");
        $this->exts->moveToElementAndClick($this->submit_login_btn);
        sleep(15);

        $this->waitForLogin();
    } else {
        $this->exts->log('Timeout waitForLoginPage');
        if ($this->exts->getElement('input[class*="DialogNewSite__SiteNameInput"]') != null) {
            $this->exts->account_not_ready();
        }

        $this->exts->capture("LoginFailed");
        $this->exts->loginFailure();
    }
}

private function waitForLogin()
{
    sleep(10);
    $currentUrl = $this->exts->getUrl();

    $this->exts->log('Current URL :' . $currentUrl);

    $parsed_url = parse_url($currentUrl);

    $path_parts = explode('/', $parsed_url['path']);
    $path_parts = array_slice($path_parts, 0, 2);
    $path_parts[] = 'billing';
    $new_path = implode('/', $path_parts);

    $new_url = $parsed_url['scheme'] . '://' . $parsed_url['host'] . $new_path;

    $this->exts->log('New URL :' . $new_url);

    $this->exts->openUrl($new_url);
    sleep(15);

    if ($this->exts->getElement('button[data-testid="menubar-menuUser"]') != null) {
        $this->exts->log('User logged in.');
        $this->exts->capture("2-post-login");
        
        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();

    } else if ($this->exts->getElement($this->checkLoggedinSelector) != null) {
        sleep(3);
        $this->exts->log('User logged in.');
        $this->exts->capture("2-post-login");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        $this->exts->log('Timeout waitForLogin');
        $this->exts->capture("LoginFailed");
        sleep(2);
        if ($this->exts->exists('div[class*="windowframe__titleLabel"]')) {
            $this->exts->account_not_ready();
            sleep(2);
        }

        if (stripos($this->exts->extract($this->checkLoginFailedSelector), 'Passwor') !== false) {
            $this->exts->loginFailure(1);
        } else if ($this->exts->getElement('input[class*="DialogNewSite__SiteNameInput"]') != null) {
            $this->exts->account_not_ready();
        } else {
            $this->exts->loginFailure();
        }
    }
}