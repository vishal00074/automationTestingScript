public $baseUrl = 'https://www.hood.de/mein-hood.htm?sec=1';
public $loginUrl = 'https://www.hood.de/mein-hood.htm?sec=1';
public $invoicePageUrl = 'https://www.hood.de/mein-hood.htm?sec=1';

public $username_selector = 'form#hoodForm input[name="email"]';
public $password_selector = 'form#hoodForm input[name="accountpass"]';
public $remember_me_selector = 'form#hoodForm label input[data-parsley-multiple*="noLoginFlag"] ';
public $submit_login_selector = 'form#hoodForm button[type="submit"]';

public $check_login_failed_selector = 'div[class*="iError iErrorActive"] ul[class*="iListMessage"] li';
public $check_login_success_selector = 'div[onclick*="logout"] ';

public $isNoInvoice = true;
/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    $this->exts->openUrl($this->baseUrl);
    sleep(1);

    // Load cookies
    $this->exts->loadCookiesFromFile();
    sleep(1);
    $this->exts->openUrl($this->baseUrl);
    sleep(5);
    $this->waitFor($this->check_login_success_selector);
    $this->exts->capture('1-init-page');

    $this->exts->execute_javascript('
            var shadow = document.querySelector("#usercentrics-cmp-ui");
            if(shadow){
                shadow.shadowRoot.querySelector(\'button[id="accept"]\').click();
            }
        ');

    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if ($this->exts->getElement($this->check_login_success_selector) == null) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();
        $this->exts->openUrl($this->loginUrl);
        sleep(3);
        $this->checkFillLogin();
        sleep(2);
        $this->waitFor($this->check_login_success_selector);
    }

    // then check user logged in or not
    // for ($wait_count = 1; $wait_count <= 10 && $this->exts->getElement($this->check_login_success_selector) == null; $wait_count++) {
    //  $this->exts->log('Waiting for login...');
    //  sleep(5);
    // }
    if ($this->exts->getElement($this->check_login_success_selector) != null) {
        sleep(3);
        $this->exts->log(__FUNCTION__ . '::User logged in');
        $this->exts->capture("3-login-success");

        $this->exts->execute_javascript('
            var shadow = document.querySelector("#usercentrics-cmp-ui");
            if(shadow){
                shadow.shadowRoot.querySelector(\'button[id="accept"]\').click();
            }
        ');

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        $this->exts->log(__FUNCTION__ . '::Use login failed');
        if (strpos(strtolower($this->exts->extract($this->check_login_failed_selector, null, 'innerText')), 'passwor') !== false) {
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}

private function checkFillLogin()
{
    $this->waitFor($this->password_selector);
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


public function waitFor($selector, $seconds = 7)
{
    for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
        $this->exts->log('Waiting for Selectors.....');
        sleep($seconds);
    }
}