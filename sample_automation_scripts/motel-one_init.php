public $baseUrl = 'https://www.motel-one.com/';
public $loginUrl = 'https://www.motel-one.com/';
public $invoicePageUrl = 'https://booking.motel-one.com/de/profile/reservations/?state=EXPIRED';
public $username_selector = 'form[id*="loggedOut"] input[name="email"]';
public $password_selector = 'form[id*="loggedOut"] input[name="password"]';
public $remember_me_selector = '';
public $submit_login_selector = 'form[id*="loggedOut"] button[type="submit"]';
public $check_login_failed_selector = 'p.message-feedback__msg, #notification-viewport li, .formkit-message';
public $check_login_success_selector = 'a[href*="/reservations"]';
public $isNoInvoice = true;
public $errorMessage = '';

private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    // $this->exts->loadCookiesFromFile();
    $this->exts->openUrl($this->loginUrl);
    $this->acceptCookies();

    if (!$this->checkLogin()) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();
        $this->exts->openUrl($this->loginUrl);
        $this->acceptCookies();
        $this->exts->click_element('button[aria-label="Kundenkonto verwalten"]');
        $this->fillForm(0);
    }

    if ($this->checkLogin()) {
        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
        $this->exts->capture("LoginSuccess");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        if (stripos($this->errorMessage, 'not correct') !== false || stripos($this->errorMessage, 'nicht korrekt') !== false) {
            $this->exts->log("Wrong credential !!!!");
            $this->exts->loginFailure(1);
        } else if (stripos($this->errorMessage, 'E-Mail Adresse ist ung') !== false || stripos($this->errorMessage, 'enter a valid email address') !== false) {
            $this->exts->log("Wrong credential !!!!");
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}

public function fillForm($count)
{
    $this->exts->log("Begin fillForm " . $count);
    sleep(10);
    try {
        if ($this->exts->querySelector($this->username_selector) != null) {

            $this->exts->capture("1-pre-login");
            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(1);

            if ($this->exts->exists($this->remember_me_selector)) {
                $this->exts->click_element($this->remember_me_selector);
                sleep(1);
            }
            $this->exts->capture("2-login-page-filled");
            $this->exts->moveToElementAndClick($this->submit_login_selector);
            sleep(2);
            if ($this->exts->extract($this->check_login_failed_selector) != null) {
                $this->errorMessage = $this->exts->extract($this->check_login_failed_selector);
                $this->exts->log("Wrong credential message: " . $this->errorMessage);
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
        sleep(10);
    } catch (\Exception $exception) {

        $this->exts->log("Exception filling loginform " . $exception->getMessage());
    }
}

public function acceptCookies()
{
    sleep(10);
    if ($this->exts->getElement('#usercentrics-cmp-ui')) {
        $this->exts->execute_javascript('
            var shadow = document.querySelector("#usercentrics-cmp-ui");
            if(shadow){
                shadow.shadowRoot.querySelector("button[id*=\'accept\']").click();
            }
        ');
        sleep(3);
    }

    if ($this->exts->getElement('button[aria-label*="Schlie"]:first-child') != null) {
        $this->exts->click_element('button[aria-label*="Schlie"]:first-child');
        sleep(2);
    }
}

public function checkLogin()
{
    sleep(5);
    $this->exts->log("Begin checkLogin ");
    $isLoggedIn = false;
    try {
        if ($this->exts->getElement("button[aria-label*='verwalten']") != null && $this->exts->getElement($this->check_login_success_selector) == null) {
            $this->exts->moveToElementAndClick("button[aria-label*='verwalten']");
            sleep(5);
        }

        if ($this->exts->getElement($this->check_login_success_selector) != null) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $isLoggedIn = true;
        }
    } catch (Exception $exception) {

        $this->exts->log("Exception checking loggedin " . $exception);
    }
    return $isLoggedIn;
}