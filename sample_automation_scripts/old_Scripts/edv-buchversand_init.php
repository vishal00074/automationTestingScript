public $baseUrl = 'https://www.edv-buchversand.de/index.php?cnt=userportal';
public $loginUrl = 'https://www.edv-buchversand.de/index.php?cnt=userportal';
public $invoicePageUrl = 'https://www.edv-buchversand.de/shift/userportal/invoice';

public $username_selector = 'input[id*="customer-email"]';
public $password_selector = 'input[id*="customer-pass"]';
public $remember_me_selector = '';
public $submit_login_selector = 'input#loginButton, input[id*="login_button"], button#login_button';

public $check_login_failed_selector = 'span#password-error[style*="display: inline"], span#email-error[style*="display: inline"]';

public $check_login_success_selector = 'button[onclick*="logout"]';

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
    $this->exts->capture('1-init-page-before-loadcookies');
    // Load cookies
    $this->exts->loadCookiesFromFile();
    sleep(1);
    $this->exts->openUrl($this->baseUrl);
    sleep(10);
    $this->exts->capture('1-init-page');
    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if ($this->exts->getElement($this->check_login_success_selector) == null || $this->exts->getElement($this->password_selector) != null) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();
        sleep(5);
        $this->exts->openUrl($this->loginUrl);
        sleep(15);
        for ($i = 0; $i < 5; $i++) {
            if ($this->exts->exists('#reload-button')) {
                $this->exts->moveToElementAndClick('#reload-button');
                sleep(10);
            } else {
                break;
            }
        }
        $this->exts->moveToElementAndClick('button[onclick*="login_modal"]');
        if (!$this->exts->exists($this->password_selector)) {
            sleep(2);
            $this->exts->moveToElementAndClick('button[onclick*="login_modal"]');
        }
        sleep(3);
        $this->checkFillLogin();
        sleep(20);
    }

    // then check user logged in or not
    if ($this->exts->getElement($this->check_login_success_selector) != null && $this->exts->getElement($this->password_selector) == null) {
        sleep(3);
        $this->exts->log(__FUNCTION__ . '::User logged in');
        $this->exts->capture("3-login-success");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        $this->exts->log(__FUNCTION__ . '::Use login failed');
        if ($this->exts->getElement($this->check_login_failed_selector) != null) {
            $this->exts->log('*********************Usename or password incorrect****************************');
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
        sleep(2);
        $this->exts->moveToElementAndClick($this->submit_login_selector);
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}