public $baseUrl = 'https://app.mycraftnote.de/';
public $loginUrl = 'https://app.mycraftnote.de/';
public $invoicePageUrl = 'https://app.mycraftnote.de/settings/subscription';

public $username_selector = 'form input[type="text"]';
public $password_selector = 'form input[type="password"]';
public $remember_me_selector = '';
public $submit_login_selector = 'form button[type="submit"]';

public $check_login_failed_selector = 'span.mat-simple-snack-bar-content';
public $check_login_success_selector = 'button[data-cy="nav-header-settings"]';

public $isNoInvoice = true;

public $isFailedLogin = false;
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    $this->exts->loadCookiesFromFile();
    $this->exts->openUrl($this->baseUrl);
    $this->exts->capture('1-init-page');
    if (!$this->checkLogin()) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();
        $this->exts->openUrl($this->loginUrl);
        sleep(2);
        $this->exts->execute_javascript('
            var el = document.querySelector("#usercentrics-root")?.shadowRoot?.querySelector(\'button[data-testid="uc-accept-all-button"]\');
            if (el) el.click();
        ');
        $this->fillForm(0);
    }

    if ($this->checkLogin()) {
        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
        $this->exts->capture("3-login-success");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        if ($this->isFailedLogin) {
            $this->exts->log("Wrong credential !!!!");
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}

private function closeReminderScreen()
{
    $this->waitFor('div.remind-me-later-container a', 5);
    $this->exts->click_if_existed('div.remind-me-later-container a');
}

public function waitFor($selector, $seconds = 7)
{
    for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
        $this->exts->log('Waiting for Selectors.....');
        sleep($seconds);
    }
}

private function fillForm($count)
{
    $this->exts->log("Begin fillForm " . $count);
    $this->waitFor($this->username_selector, 10);
    $this->exts->execute_javascript('
        var el = document.querySelector("#usercentrics-root")?.shadowRoot?.querySelector(\'button[data-testid="uc-accept-all-button"]\');
        if (el) el.click();
    ');
    if ($this->exts->querySelector($this->username_selector) != null) {
        $this->exts->capture("2-login-page");
        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);

        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector, $this->password);

        $this->exts->capture("2-login-page-filled");
        sleep(1);
        if ($this->exts->exists($this->submit_login_selector)) {
            $this->exts->click_element($this->submit_login_selector);
            sleep(2);
        }
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}

private function checkLogin()
{
    $this->exts->log("Begin checkLogin ");
    $isLoggedIn = false;
    try {
        $this->waitFor($this->check_login_failed_selector, 15);
        if ($this->exts->exists($this->check_login_failed_selector)) {
            $this->isFailedLogin = true;
        }
        $this->closeReminderScreen();
        $this->waitFor($this->check_login_success_selector, 15);
        if ($this->exts->exists($this->check_login_success_selector)) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $isLoggedIn = true;
        }
    } catch (Exception $exception) {
        $this->exts->log("Exception checking loggedin " . $exception);
    }
    return $isLoggedIn;
}