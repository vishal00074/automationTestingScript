public $baseUrl = 'https://www.dazn.com/en-DE/myaccount';
public $loginUrl = 'https://www.dazn.com/en-DE/myaccount';
public $invoicePageUrl = 'https://www.dazn.com/en-DE/myaccount/payment-history';

public $username_selector = '.emailFieldSec input, input[name="email"]';
public $password_selector = '.pwdFieldSec input#idEmailPwd, input[name="password"]';
public $remember_me_selector = '';
public $submit_login_selector = 'button[data-test-id="refined-button-signin"]';

public $check_login_failed_selector = 'form[action*="/mylogin"] #myAlert a, div[class*="errorCode"], div[data-test-id="error-message-EMAIL-is-email"], div[data-test-id="PASSWORD_ERROR_MESSAGE"]';
public $check_login_success_selector = 'div[class*="signOutContainer"]';

public $isNoInvoice = true;
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    $this->disable_unexpected_extensions();
    $this->exts->openUrl($this->baseUrl);
    sleep(5);
    if ($this->exts->getElement('//div[contains(text(),"ERR_CERT_COMMON_NAME_INVALID")]', null, 'xpath') != null) {
        $this->exts->moveToElementAndClick('button#details-button');
        sleep(1);
        $this->exts->moveToElementAndClick('a#proceed-link');
        sleep(15);
    }
    $this->check_solve_blocked_page();
    sleep(5);
    // Load cookies
    $this->exts->loadCookiesFromFile();
    sleep(10);
    $this->checkAndLogin();
}

private function disable_unexpected_extensions()
{
    $this->exts->openUrl('chrome://extensions/?id=cjpalhdlnbpafiamejdnhcphjbkeiagm'); // disable Block origin extension
    sleep(2);
    $this->exts->execute_javascript("
        if(document.querySelector('extensions-manager') != null) {
            if(document.querySelector('extensions-manager').shadowRoot.querySelector('extensions-detail-view')  != null){
                var disable_button = document.querySelector('extensions-manager').shadowRoot.querySelector('extensions-detail-view').shadowRoot.querySelector('#enableToggle[checked]');
                if(disable_button != null){
                    disable_button.click();
                }
            }
        }
    ");
    sleep(1);
    $this->exts->openUrl('chrome://extensions/?id=ifibfemgeogfhoebkmokieepdoobkbpo');
    sleep(1);
    $this->exts->execute_javascript("if (document.querySelector('extensions-manager').shadowRoot.querySelector('extensions-detail-view').shadowRoot.querySelector('#enableToggle[checked]') != null) {
            document.querySelector('extensions-manager').shadowRoot.querySelector('extensions-detail-view').shadowRoot.querySelector('#enableToggle[checked]').click();
        }");
    sleep(2);
}

private function check_solve_blocked_page()
{
    $this->exts->capture("blocked-page-checking");
    if ($this->exts->getElement('iframe[src*="challenges.cloudflare.com"]') != null) {
        $this->exts->capture("blocked-by-cloudflare");
        $this->exts->click_by_xdotool('iframe[src*="challenges.cloudflare.com"]', 30, 28, true);
        sleep(20);
        if ($this->exts->getElement('iframe[src*="challenges.cloudflare.com"]') != null) {
            $this->exts->click_by_xdotool('iframe[src*="challenges.cloudflare.com"]', 30, 28, true);
            sleep(25);
        }
        if ($this->exts->getElement('iframe[src*="challenges.cloudflare.com"]') != null) {
            $this->exts->click_by_xdotool('iframe[src*="challenges.cloudflare.com"]', 30, 28, true);
            sleep(25);
        }
        if ($this->exts->getElement('iframe[src*="challenges.cloudflare.com"]') != null) {
            $this->exts->click_by_xdotool('iframe[src*="challenges.cloudflare.com"]', 30, 28, true);
            sleep(25);
        }
    }
}

private function checkAndLogin()
{
    sleep(5);
    if ($this->exts->getElement('button#onetrust-accept-btn-handler') != null) {
        $this->exts->moveToElementAndClick('button#onetrust-accept-btn-handler');
        sleep(2);
    }
    $this->exts->capture('1-init-page');
    $this->check_solve_blocked_page();
    // If user hase not logged in from cookie, open the login url and wait for login form
    if ($this->exts->getElement($this->check_login_success_selector) == null) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();
        sleep(2);
        if ($this->exts->getElement('button#onetrust-accept-btn-handler') != null) {
            $this->exts->moveToElementAndClick('button#onetrust-accept-btn-handler');
            sleep(2);
        }
        sleep(7);
        $this->exts->capture('after-clear-cookies');
        $this->exts->openUrl($this->loginUrl);
        sleep(25);

        if ($this->exts->getElement('button#onetrust-accept-btn-handler') != null) {
            $this->exts->moveToElementAndClick('button#onetrust-accept-btn-handler');
            sleep(8);
        }
        $this->checkFillLogin();
        sleep(8);
    }

    if ($this->exts->getElement($this->check_login_success_selector) != null) {
        sleep(3);
        $this->exts->log('User logged in');
        $this->exts->capture("3-login-success");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        $this->exts->log('Timeout waitForLogin');
        if ($this->exts->urlContains('account/payment-plan') && $this->exts->getElement('[data-test-id="select-subscription__page"] [data-test-id="signUpStepsIndicator__step"]') != null) {
            $this->exts->account_not_ready();
        } else if ($this->exts->getElement($this->check_login_failed_selector) != null) {
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}

public function checkFillLogin()
{
    $this->exts->capture("login-page");
    if ($this->exts->getElement($this->username_selector) != null) {
        sleep(3);
        $this->exts->capture("2-login-page");

        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(3);
        $this->exts->moveToElementAndClick($this->submit_login_selector);
        sleep(10);
        $this->exts->capture("2-username-filled");
        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(2);

        if ($this->exts->getElement($this->remember_me_selector) != null)
            $this->exts->moveToElementAndClick($this->remember_me_selector);
        sleep(3);

        $this->exts->capture("2-login-page-filled");
        $this->exts->moveToElementAndClick($this->submit_login_selector);
        sleep(15);
    } else {
        $this->exts->log('Login page not found');
        $this->exts->loginFailure();
    }
}
