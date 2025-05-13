public $baseUrl = 'https://manager.eu.shadow.tech';
public $loginUrl = 'https://shadow.tech/';
public $invoicePageUrl = 'https://manager.eu.shadow.tech/billing/invoices';

public $continue_login_button = 'div a[href*="/account"]';
public $username_selector = 'input#identifier';
public $password_selector = 'input[type="password"]';
public $remember_me_selector = '';
public $submit_login_selector = '.form-button button[type="submit"]';

public $accept_cookie_selector = '#didomi-notice-agree-button';

public $check_login_failed_selector = 'main#authCard > div > div[data-testid]';
public $check_login_success_selector = 'button[data-testid="header-user-button"]';

public $isNoInvoice = true;

/**
* Entry Method thats called for a portal
* @param Integer $count Number of times portal is retried.
*/
private function initPortal($count)
{

    $this->exts->log('Begin initPortal ' . $count);
    $this->exts->loadCookiesFromFile();
    $this->exts->openUrl($this->baseUrl);
    sleep(10);
    $this->check_solve_cloudflare_page();

    $this->acceptCookies();
    if (!$this->checkLogin()) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();
        if ($this->exts->exists('button#didomi-notice-agree-button')) {
            $this->exts->click_element('button#didomi-notice-agree-button');
            sleep(10);
        }
        $this->fillForm(0);

        $this->exts->openUrl($this->baseUrl);
        sleep(10);
        $this->check_solve_cloudflare_page();

        $this->fillForm(0);
    }

    if ($this->checkLogin()) {
        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
        $this->exts->capture("LoginSuccess");

        if ($this->exts->exists('button#didomi-notice-agree-button')) {
            $this->exts->click_element('button#didomi-notice-agree-button');
            sleep(7);
        }

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        $this->exts->log(__FUNCTION__ . '::Use login failed');
        $this->exts->log(__FUNCTION__ . '::Last URL: ' . $this->exts->getUrl());
        $this->exts->loginFailure();
    }
}

private function check_solve_blocked_page()
{
    $this->exts->capture_by_chromedevtool("blocked-page-checking");
    $this->waitFor('div[style="display: grid;"] > div > div', 15);
    for ($i = 0; $i < 5; $i++) {
        if ($this->exts->check_exist_by_chromedevtool('div[style="display: grid;"] > div > div')) {
            $this->exts->capture_by_chromedevtool("blocked-by-cloudflare");
            $this->exts->refresh();
            sleep(10);

            $this->exts->click_by_xdotool('div[style="display: grid;"] > div > div', 30, 28);
            sleep(15);

            if (!$this->exts->check_exist_by_chromedevtool('div[style="display: grid;"] > div > div')) {
                break;
            }
        } else {
            break;
        }
    }
}
private function check_solve_cloudflare_page()
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

function acceptCookies()
{
    $this->waitFor($this->accept_cookie_selector, 7);
    if ($this->exts->exists($this->accept_cookie_selector)) {
        $this->exts->click_element($this->accept_cookie_selector);
    }
}

public function waitFor($selector, $seconds = 7)
{
    for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
        $this->exts->log('Waiting for Selectors.....');
        sleep($seconds);
    }
}


function fillForm($count)
{
    $this->exts->log("Begin fillForm " . $count);
    $this->waitFor($this->username_selector, 8);

    if ($this->exts->querySelector($this->username_selector) != null) {

        $this->exts->capture("1-pre-login");
        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);

        sleep(2);

        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(1);
        $this->exts->capture("1-login-page-filled");
        sleep(5);
        if ($this->exts->exists($this->submit_login_selector)) {
            $this->exts->click_element($this->submit_login_selector);
            sleep(5);
        }
        $this->check_solve_cloudflare_page();
    }

    $error_text = strtolower($this->exts->extract('div#error-identifier'));
    $this->exts->log(__FUNCTION__ . '::Error text: ' . $error_text);

    if ($this->exts->exists($this->check_login_failed_selector)) {
        $this->exts->log("Wrong credential !!!!");
        $this->exts->loginFailure(1);
    } else if (stripos($error_text, strtolower('The email format is invalid')) !== false) {
        $this->exts->loginFailure(1);
    }
}


/**
    * Method to Check where user is logged in or not
    * return boolean true/false
    */
function checkLogin()
{
    $this->exts->log("Begin checkLogin ");
    $isLoggedIn = false;
    try {
        $this->waitFor($this->check_login_success_selector, 10);
        if ($this->exts->exists($this->check_login_success_selector)) {

            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");

            $isLoggedIn = true;
        }
    } catch (Exception $exception) {

        $this->exts->log("Exception checking loggedin " . $exception);
    }
    return $isLoggedIn;
}