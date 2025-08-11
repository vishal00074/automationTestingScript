public $baseUrl = 'https://timebutler.de/login/';
public $loginUrl = 'https://timebutler.de/login/';
public $invoicePageUrl = 'https://timebutler.de/do?ha=pay&ac=30';
public $username_selector = 'input[name="login"]';
public $password_selector = 'input[name="passwort"]';
public $remember_me_selector = 'input[type="checkbox"]';
public $submit_login_selector = 'button[type="submit"]';
public $check_login_failed_selector = 'div[class="box-body"] p';
public $check_login_success_selector = 'a[href="javascript:destroyFloating();print()"]';
public $isNoInvoice = true;


private function initPortal($count)
{

    $this->exts->log('Begin initPortal ' . $count);
    $this->exts->loadCookiesFromFile();
    $this->exts->openUrl($this->loginUrl);
    $this->exts->waitTillAnyPresent([$this->username_selector, $this->check_login_success_selector]);
    if (!$this->checkLogin()) {
        $this->exts->log('NOT logged via cookie');
        sleep(5);
        $this->exts->execute_javascript('
            var shadow = document.querySelector("#cmpwrapper");
            if(shadow){
                shadow.shadowRoot.querySelector(\'span[id="cmpwelcomebtnyes"] a\').click();
            }
        ');

        $this->fillForm(0);
        sleep(5);
        if ($this->exts->exists('div[id="step1"]  a.btn-default')) {
            $this->exts->click_element('div[id="step1"]  a.btn-default');
        }
        $this->checkFillTwoFactor();
        sleep(10);
        $skip_button = $this->exts->getElementByText('a[href*="do"]', 'Hinweis nicht mehr anzeigen', null, false);
        if ($skip_button != null) {
            try {
                $this->exts->log('Click download button');
                $skip_button->click();
            } catch (\Exception $exception) {
                $this->exts->log('Click download button by javascript');
                $this->exts->execute_javascript("arguments[0].click()", [$skip_button]);
            }
        }

        $this->exts->waitTillPresent($this->check_login_success_selector);
    }

    if ($this->checkLogin()) {
        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
        $this->exts->capture("LoginSuccess");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        if (stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'passwor') !== false) {
            $this->exts->log("Wrong credential !!!!");
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}

function fillForm($count)
{
    $this->exts->log("Begin fillForm " . $count);
    try {
        if ($this->exts->querySelector($this->username_selector) != null) {

            $this->exts->capture("1-pre-login");
            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(2);

            if ($this->exts->exists($this->remember_me_selector)) {
                $this->exts->click_by_xdotool($this->remember_me_selector);
                sleep(1);
            }

            $this->exts->capture("1-login-page-filled");
            sleep(5);
            if ($this->exts->exists($this->submit_login_selector)) {
                $this->exts->click_by_xdotool($this->submit_login_selector);
            }
        }
    } catch (\Exception $exception) {

        $this->exts->log("Exception filling loginform " . $exception->getMessage());
    }
}

private function checkFillTwoFactor(): void
{
    $selector = 'input#tfadigits';
    $message_selector = 'div.registration-form-content > p';
    $submit_selector = 'button#tfasubmit';

    while ($this->exts->getElement($selector) !== null && $this->exts->two_factor_attempts < 3) {
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor");

        // Collect and log the 2FA instruction messages
        $this->exts->two_factor_notif_msg_en = "";
        $messages = $this->exts->getElements($message_selector);
        foreach ($messages as $msg) {
            $this->exts->two_factor_notif_msg_en .= $msg->getAttribute('innerText') . "\n";
        }

        $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
        $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
        $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);

        // Add retry message if this is the final attempt
        if ($this->exts->two_factor_attempts === 2) {
            $this->exts->two_factor_notif_msg_en .= ' ' . $this->exts->two_factor_notif_msg_retry_en;
            $this->exts->two_factor_notif_msg_de .= ' ' . $this->exts->two_factor_notif_msg_retry_de;
        }

        $code = trim($this->exts->fetchTwoFactorCode());
        if ($code === '') {
            $this->exts->log("2FA code not received");
            break;
        }

        $this->exts->log("checkFillTwoFactor: Entering 2FA code: " . $two_factor_code);
        $this->exts->click_by_xdotool($selector);
        $this->exts->type_text_by_xdotool($code);
        $this->exts->moveToElementAndClick('input#nomore2fa');
        $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

        $this->exts->moveToElementAndClick($submit_selector);
        sleep(5); // Added: Ensure time for 2FA processing

        if ($this->exts->getElement($selector) === null) {
            $this->exts->log("Two factor solved");
            break;
        }

        $this->exts->two_factor_attempts++;
    }

    if ($this->exts->two_factor_attempts >= 3) {
        $this->exts->log("Two factor could not be solved after 3 attempts");
    }
}

function checkLogin()
{
    $this->exts->log("Begin checkLogin ");
    $isLoggedIn = false;
    try {
        if ($this->exts->exists($this->check_login_success_selector)) {

            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");

            $isLoggedIn = true;
        }
    } catch (Exception $exception) {

        $this->exts->log("Exception checking loggedin " . $exception);
    }


    return $isLoggedIn;
}