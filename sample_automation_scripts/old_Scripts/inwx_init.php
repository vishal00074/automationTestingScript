public $baseUrl = 'https://www.inwx.de/de/accounting/invoices';
public $loginUrl = 'https://www.inwx.de/de/customer/login';
public $invoicePageUrl = 'https://www.inwx.de/de/accounting/invoices';

public $username_selector = '#maincontent form[action*="/customer/login"] input[name="usr"]';
public $password_selector = '#maincontent form[action*="/customer/login"] input[name="pwd"]';
public $remember_me_selector = '';
public $submit_login_selector = '#maincontent form[action*="/customer/login"] input#btn_subm';

public $check_login_failed_selector = 'div#realcontent p[style="color:red"]';
public $check_login_success_selector = 'a[href*="/logout"]';

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
    $this->exts->capture('1-init-page');

    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    $this->waitFor($this->check_login_success_selector, 10);
    if ($this->exts->getElement($this->check_login_success_selector) == null) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->openUrl($this->loginUrl);

        $this->checkFillHcaptcha();

        $this->checkFillLogin();
        sleep(15);
        if ($this->exts->getElement($this->password_selector) != null && ($this->exts->urlContains('login/wrongprovider') || $this->exts->getElement('//p[contains(text(),"falschen Provider anzumelden")]') != null)) {
            $this->checkFillLogin();
        }

        $this->checkFillHcaptcha();
        $this->waitFor('iframe[src*="/unlock"]');

        if ($this->exts->exists('iframe[src*="/unlock"]')) {

            $this->switchToFrame('iframe[src*="/unlock"]');
            $this->checkFillTwoFactor();
            $this->exts->switchToDefault();
        }
    }


    if ($this->exts->getElement($this->check_login_success_selector) != null) {
        sleep(3);
        $this->exts->log(__FUNCTION__ . '::User logged in');
        $this->exts->capture("3-login-success");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        $this->exts->log(__FUNCTION__ . '::Use login failed');
        if ($this->exts->getElement($this->check_login_failed_selector) != null && strpos(strtolower($this->exts->extract($this->check_login_failed_selector, null, 'innerText')), 'passwor') !== false) {
            $this->exts->loginFailure(1);
        } else if (strpos($this->exts->extract('#realcontent h3', null, 'innerText'), ' gesperrt') !== false || strpos($this->exts->extract('#realcontent h3', null, 'innerText'), ' suspended') !== false) {
            $this->exts->account_not_ready();
        } else {
            $this->exts->loginFailure();
        }
    }
}

public function waitFor($selector = null, $seconds = 10)
{
    for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
        $this->exts->log('Waiting for selector.....');
        sleep($seconds);
    }
}

private function checkFillLogin()
{

    // $this->exts->waitTillPresent($this->username_selector,10);
    $this->waitFor($this->username_selector);

    if ($this->exts->getElement($this->password_selector) != null) {
        sleep(3);
        $this->exts->capture("2-login-page");

        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(2);

        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(2);

        if ($this->remember_me_selector != '')
            $this->exts->click_element($this->remember_me_selector);
        sleep(2);

        $this->exts->capture("2-login-page-filled");

        if ($this->exts->exists($this->submit_login_selector)) {
            $this->exts->click_element($this->submit_login_selector);
        }
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}

public function switchToFrame($query_string)
{
    $this->exts->log(__FUNCTION__ . " Begin with " . $query_string);
    $this->exts->log('In frame');
    $frame = null;
    if (is_string($query_string)) {
        $frame = $this->exts->queryElement($query_string);
    }

    if ($frame != null) {
        $this->exts->log('In frame 1');
        $frame_context = $this->exts->get_frame_excutable_context($frame);
        if ($frame_context != null) {
            $this->exts->log('In frame 2');
            $this->exts->current_context = $frame_context;
            return true;
        }
    } else {
        $this->exts->log(__FUNCTION__ . " Frame not found " . $query_string);
    }

    return false;
}

private function checkFillTwoFactor()
{
    $two_factor_selector = 'form input[id="otp"]';
    $two_factor_message_selector = 'div#realcontent_small > div.inwx-col2-force > div >p';
    $two_factor_submit_selector = 'input[name="btn_submit"]';
    $this->exts->waitTillPresent($two_factor_selector, 10);
    if ($this->exts->querySelector($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor");
        if ($this->exts->getElement($two_factor_message_selector) != null) {
            $this->exts->two_factor_notif_msg_en = $this->exts->extract($two_factor_message_selector);
            $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
            $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
        }
        if ($this->exts->two_factor_attempts == 2) {
            $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
        }
        $two_factor_code = trim($this->exts->fetchTwoFactorCode());
        if (!empty($two_factor_code) && trim($two_factor_code) != '') {
            $this->exts->log("checkFillTwoFactor: Entering two_factor_code." . $two_factor_code);
            $this->exts->click_by_xdotool($two_factor_selector);
            sleep(2);
            $this->exts->type_text_by_xdotool($two_factor_code);

            $this->exts->log("checkFillTwoFactor: Clicking submit button.");
            sleep(3);
            $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);


            $this->exts->click_by_xdotool($two_factor_submit_selector);
            sleep(15);
            if ($this->exts->querySelector($two_factor_selector) == null) {
                $this->exts->log("Two factor solved");
            } else if ($this->exts->two_factor_attempts < 3) {
                $this->exts->two_factor_attempts++;
                $this->checkFillTwoFactor();
            } else {
                $this->exts->log("Two factor can not solved");
            }
        } else {
            $this->exts->log("Not received two factor code");
        }
    }
}

private function checkFillHcaptcha($count = 0)
{
    $hcaptcha_iframe_selector = 'iframe[src*="hcaptcha"]';
    if ($this->exts->exists($hcaptcha_iframe_selector)) {
        $iframeUrl = $this->exts->extract($hcaptcha_iframe_selector, null, 'src');
        $data_siteKey =  end(explode("&sitekey=", $iframeUrl));
        $this->exts->processHumanCaptcha("", $data_siteKey, $this->exts->getUrl(), true);
        sleep(5);
        if ($this->exts->exists($hcaptcha_iframe_selector) && $count < 5) {
            $count++;
            $this->checkFillHcaptcha($count);
        }
    }
}