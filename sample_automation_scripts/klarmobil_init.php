public $baseUrl = 'https://klarmobil.de/';
public $invoicePageUrl = 'https://www.klarmobil.de/online-service/meine-rechnungen';
public $username_selector = 'input#username';
public $password_selector = 'input#password';
public $remember_me_selector = '';
public $submit_login_selector = 'button[type="submit"]';
public $check_login_success_selector = 'form#logout_form, .main-navigation a[href="/online-service/meine-daten"]';
public $isNoInvoice = true;

/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);

    // Load cookies
    $this->exts->openUrl($this->baseUrl);
    sleep(15);
    $this->accept_consent();


    if ($this->exts->exists('header .user__link a[href*="/onlineservice"]')) {
        $this->exts->moveToElementAndClick('header .user__link a[href*="/onlineservice"]');
    }
    sleep(5);
    $this->accept_consent();
    $this->exts->capture('1-init-page');

    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if (!$this->checkLogin()) {
        $this->exts->log('NOT logged via cookie');
        if (
            strpos($this->exts->get_page_content(), 'ERR_TOO_MANY_REDIRECTS') !== false ||
            strpos($this->exts->get_page_content(), 'Access Denied') !== false ||
            strpos($this->exts->get_page_content(), 'ERR_CONNECTION_TIMED_OUT') !== false
        ) {
            $this->exts->log(__FUNCTION__ . "ERROR load page");
            $this->clearChrome();
            sleep(1);
        }

        // $this->exts->clearCookies();// Expired cookie doern't affect to login but it make download getting error, so clear it.
        $this->exts->openUrl($this->invoicePageUrl);
        $this->checkFillLogin();

        if ($this->checkLogin()){
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            if (!empty($this->exts->config_array['allow_login_success_request'])) {
                $this->exts->triggerLoginSuccess();
            }

            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed ' . $this->exts->getUrl());

            // Aufgrund zu vieler fehlerhafter Login-Versuche wurde der Login zu Ihrer Sicherheit bis
            if (strpos(strtolower($this->exts->extract('span.status-message__text')), 'deine eingegebene e-mail-adresse und das passwort') !== false) {
                $this->exts->loginFailure();
            } else if ($this->exts->urlContains('onlineservice/fehler') && $this->exts->exists('a[href*="logout"]') || $this->exts->urlContains('/onlineservice/benutzer-verknuepfen')) {
                $this->exts->account_not_ready();
            } else if (
                $this->exts->urlContains('onlineservice/info') &&
                (strpos($this->exts->extract('span.status-message__text'), 'Zu Ihrem Zugang konnten keine aktiven') !== false ||
                    strpos($this->exts->extract('span.status-message__text'), 'Zu Deinem Online-Konto existiert kein aktiver Vertrag') !== false ||
                    strpos($this->exts->extract('span.status-message__text'), 'Zu Ihrem Online-Account existiert kein aktiver Vertrag') !== false)
            ) {
                $this->exts->account_not_ready();
            } else if ($this->exts->urlContains('benutzer-verknuepfen') || $this->exts->urlContains('user-link')) {
                $this->exts->account_not_ready();
            } else if ($this->exts->exists('div#error-cs-email-invalid') || $this->exts->exists('span[id*="error-element-password"]')) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->capture("else-login-failed-page");
                $this->exts->loginFailure();
            }
        }
    }
}
private function clearChrome()
{
    $this->exts->log("Clearing browser history, cookie, cache");
    $this->exts->openUrl('chrome://settings/clearBrowserData');
    sleep(10);
    $this->exts->capture("clear-page");
    for ($i = 0; $i < 2; $i++) {
        $this->exts->type_key_by_xdotool('Tab');
    }
    $this->exts->type_key_by_xdotool('Tab');
    $this->exts->type_key_by_xdotool('Return');
    $this->exts->type_key_by_xdotool('a');
    sleep(1);
    $this->exts->type_key_by_xdotool('Return');
    sleep(3);
    $this->exts->capture("clear-page");
    for ($i = 0; $i < 5; $i++) {
        $this->exts->type_key_by_xdotool('Tab');
    }
    $this->exts->type_key_by_xdotool('Return');
    sleep(15);
    $this->exts->capture("after-clear");
}
private function accept_consent($reload_page = false)
{
    $this->exts->switchToDefault();
    if ($this->exts->check_exist_by_chromedevtool('iframe[src*="privacy"]')) {
        $this->switchToFrame('iframe[src*="privacy"]');
        $this->exts->waitTillPresent('button[aria-label*="Alle akzeptieren"]', 30);
        if ($this->exts->exists('button[aria-label*="Alle akzeptieren"]')) {
            $this->exts->click_element('button[aria-label*="Alle akzeptieren"]');
            sleep(5);
        }

        $this->exts->switchToDefault();
        if ($reload_page) {
            $this->exts->refresh();
            sleep(15);
        }
    }
}

public function switchToFrame($query_string)
{
    $this->exts->log(__FUNCTION__ . " Begin with " . $query_string);
    $frame = null;
    if (is_string($query_string)) {
        $frame = $this->exts->queryElement($query_string);
    }

    if ($frame != null) {
        $frame_context = $this->exts->get_frame_excutable_context($frame);
        if ($frame_context != null) {
            $this->exts->current_context = $frame_context;
            return true;
        }
    } else {
        $this->exts->log(__FUNCTION__ . " Frame not found " . $query_string);
    }

    return false;
}

private function checkFillLogin()
{
    $this->exts->waitTillPresent($this->username_selector);
    if ($this->exts->getElement($this->username_selector) != null) {
        $this->exts->capture("2-login-page");

        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(2);

        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(2);

        if ($this->remember_me_selector != '')
            $this->exts->moveToElementAndClick($this->remember_me_selector);
        sleep(2);

        $this->exts->capture("2-login-page-filled");

        sleep(5); // Portal itself has one second delay after showing toast
        $this->solve_login_cloudflare();

        if ($this->exts->exists($this->submit_login_selector)) {
            $this->exts->click_by_xdotool($this->submit_login_selector);
        }
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}

private function solve_login_cloudflare()
{
    $this->exts->capture_by_chromedevtool("blocked-page-checking");
    if ($this->exts->check_exist_by_chromedevtool('div.cf-turnstile')) {
        $this->exts->capture_by_chromedevtool("blocked-by-cloudflare");
        $this->exts->click_by_xdotool('div.cf-turnstile', 30, 28);
        sleep(20);
        if ($this->exts->check_exist_by_chromedevtool('div.cf-turnstile') && $this->exts->check_exist_by_chromedevtool('input[name="turnstile_captcha"][value=""]')) {
            $this->exts->click_by_xdotool('div.cf-turnstile', 30, 28);
            sleep(20);
        }
        if ($this->exts->check_exist_by_chromedevtool('div.cf-turnstile') && $this->exts->check_exist_by_chromedevtool('input[name="turnstile_captcha"][value=""]')) {
            $this->exts->click_by_xdotool('div.cf-turnstile', 30, 28);
            sleep(20);
        }
    }
}

/**
    * Method to Check where user is logged in or not
    * return boolean true/false
    */
public  function checkLogin()
{
    $this->exts->log("Begin checkLogin ");
    $isLoggedIn = false;
    try {
        for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $this->check_login_success_selector . "');") != 1; $wait++) {
            $this->exts->log('Waiting for login.....');
            sleep(10);
        }
        if ($this->exts->exists($this->check_login_success_selector)) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $isLoggedIn = true;
        }
    } catch (Exception $exception) {

        $this->exts->log("Exception checking loggedin " . $exception);
    }
    return $isLoggedIn;
}