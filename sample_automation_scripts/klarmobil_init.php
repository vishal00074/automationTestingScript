public $baseUrl = 'https://klarmobil.de/';
public $invoicePageUrl = 'https://www.klarmobil.de/online-service/meine-rechnungen';
public $username_selector = 'input#username';
public $password_selector = 'input#password';
public $remember_me_selector = '';
public $submit_login_selector = 'button[type="submit"]';
public $check_login_success_selector = 'form#logout_form, .main-navigation a[href="/online-service/meine-daten"], button[data-testid="logout"]';
public $isNoInvoice = true;

/**
 * Entry Method thats called for a portal
 * @param Integer $count Number of times portal is retried.
 */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    $this->disable_extensions();
    sleep(5);

    // Load cookies
    $this->exts->openUrl($this->baseUrl);
    sleep(15);
    $this->accept_consent();


    if ($this->isExists('header .user__link a[href*="/onlineservice"]')) {
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
        sleep(10);
        $this->checkFillLogin();

        if ($this->exts->getElement($this->username_selector) != null) {
            $this->checkFillLogin();
        }
    }

    if ($this->checkLogin()) {
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
        } else if ($this->exts->urlContains('onlineservice/fehler') && $this->isExists('a[href*="logout"]') || $this->exts->urlContains('/onlineservice/benutzer-verknuepfen')) {
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
        } else if ($this->isExists('div#error-cs-email-invalid') || $this->isExists('span[id*="error-element-password"]')) {
            $this->exts->loginFailure(1);
        } else {
            $this->exts->capture("else-login-failed-page");
            $this->exts->loginFailure();
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
        $this->waitFor('button[aria-label*="Alle akzeptieren"]', 30);
        if ($this->isExists('button[aria-label*="Alle akzeptieren"]')) {
            $this->exts->click_by_xdotool('button[aria-label*="Alle akzeptieren"]');
            sleep(5);
        }

        $this->exts->switchToDefault();
        if ($reload_page) {
            $this->exts->refresh();
            sleep(15);
        }
    }
}

// Custom Exists function to check element found or not
private function isExists($selector = '')
{
    $safeSelector = addslashes($selector);
    $this->exts->log('Element:: ' . $safeSelector);
    $isElement = $this->exts->execute_javascript('!!document.querySelector("' . $safeSelector . '")');
    if ($isElement) {
        $this->exts->log('Element Found');
        return true;
    } else {
        $this->exts->log('Element not Found');
        return false;
    }
}


public function waitFor($selector, $seconds = 10)
{
    for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
        $this->exts->log('Waiting for Selectors.....');
        sleep($seconds);
    }
}

private function disable_extensions()
{
    $this->exts->openUrl('chrome://extensions/'); // disable Block origin extension
    sleep(2);
    $this->exts->execute_javascript("
    let manager = document.querySelector('extensions-manager');
    if (manager && manager.shadowRoot) {
        let itemList = manager.shadowRoot.querySelector('extensions-item-list');
        if (itemList && itemList.shadowRoot) {
            let items = itemList.shadowRoot.querySelectorAll('extensions-item');
            items.forEach(item => {
                let toggle = item.shadowRoot.querySelector('#enableToggle[checked]');
                if (toggle) toggle.click();
            });
        }
    }
");
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
    $this->waitFor($this->username_selector);
    if ($this->exts->getElement($this->username_selector) != null) {
        $this->exts->capture("2-login-page");

        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(5);

        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(5);

        if ($this->remember_me_selector != '') {
            $this->exts->moveToElementAndClick($this->remember_me_selector);
        }
        sleep(2);

        $this->exts->capture("2-login-page-filled");
        sleep(15); // Portal itself has one second delay after showing toast

        $this->solve_login_cloudflare();

        if ($this->isExists($this->submit_login_selector)) {
            $this->exts->click_by_xdotool($this->submit_login_selector);
            sleep(10);
        }
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}


private function solve_login_cloudflare()
{
    $unsolved_cloudflare_input_xpath = '//input[starts-with(@name, "cf") and contains(@name, "response") and string-length(@value) <= 0]';
    $solved_cloudflare_input_xpath = '//input[starts-with(@name, "cf") and contains(@name, "response") and string-length(@value) > 0]';
    $this->exts->capture("cloudflare-checking");
    if (
        !$this->exts->oneExists([$solved_cloudflare_input_xpath, $unsolved_cloudflare_input_xpath]) &&
        $this->exts->exists('#cf-please-wait > p:not([style*="display: none"]):not([style*="display:none"])')
    ) {
        for ($waiting = 0; $waiting < 10; $waiting++) {
            sleep(2);
            if ($this->exts->oneExists([$solved_cloudflare_input_xpath, $unsolved_cloudflare_input_xpath])) {
                sleep(3);
                break;
            }
        }
    }

    if ($this->exts->exists($unsolved_cloudflare_input_xpath)) {
        $this->exts->click_by_xdotool('*:has(>input[name^="cf"][name$="response"])', 30, 28);
        sleep(5);
        $this->exts->capture("cloudflare-clicked-1", true);
        sleep(3);
        if ($this->exts->exists($unsolved_cloudflare_input_xpath)) {
            $this->exts->click_by_xdotool('*:has(>input[name^="cf"][name$="response"])', 30, 28);
            sleep(5);
            $this->exts->capture("cloudflare-clicked-2", true);
            sleep(15);
        }
        if ($this->exts->exists($unsolved_cloudflare_input_xpath)) {
            $this->exts->click_by_xdotool('*:has(>input[name^="cf"][name$="response"])', 30, 28);
            sleep(5);
            $this->exts->capture("cloudflare-clicked-3", true);
            sleep(15);
        }
    }
}

/**
 * Method to Check where user is logged in or not
 * return boolean true/false
 */
public function checkLogin()
{
    $this->exts->log("Begin checkLogin ");
    $isLoggedIn = false;
    try {
        for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $this->check_login_success_selector . "');") != 1; $wait++) {
            $this->exts->log('Waiting for login.....');
            sleep(10);
        }
        if ($this->isExists($this->check_login_success_selector)) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $isLoggedIn = true;
        }

        if ($this->isExists('div[data-qa="billing-account-selection"]')) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $isLoggedIn = true;
        }

        
    } catch (Exception $exception) {

        $this->exts->log("Exception checking loggedin " . $exception);
    }
    return $isLoggedIn;
}

