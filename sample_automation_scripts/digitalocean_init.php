public $baseUrl = "https://cloud.digitalocean.com/login";
public $loginUrl = "https://cloud.digitalocean.com/login";
public $dashboardUrl = "https://cloud.digitalocean.com/droplets";
public $form_selector = "form[autocomplete='none']";
public $username_selector = "input#email";
public $password_selector = "input#password";
public $submit_button_selector = "button[type=\"submit\"]";
public $twofa_form_selector =  "form input[id=\"code\"]";
public $twofa_form_selector1 =  "form[class*=\"tfa-form\"] input[name=\"otp\"]";
public $restrictPages = 3;
public $login_tryout = 0;
public $no_invoice = true;
public $no_payment_receipt = 0;

public $accounts_name_array = array();

/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
    $this->no_payment_receipt = isset($this->exts->config_array["no_payment_receipt"]) ? (int)@$this->exts->config_array["no_payment_receipt"] : $this->no_payment_receipt;

    $this->exts->openUrl($this->baseUrl);
    sleep(12);
    $this->exts->capture("Home-page-without-cookie");
    $this->exts->loadCookiesFromFile();
    sleep(1);
    $this->exts->openUrl($this->baseUrl);
    sleep(15);
    $this->exts->capture("Home-page-with-cookie");

    if ($this->exts->exists("button#truste-consent-button")) {
        $this->exts->moveToElementAndClick("button#truste-consent-button");
    }
    sleep(5);

    if (!$this->checkLogin()) {
        $this->exts->openUrl($this->baseUrl);
        sleep(2);
        $this->exts->capture("after-login-clicked");
        sleep(15);
        if ($this->exts->exists('iframe[title="TrustArc Cookie Consent Manager"]')) {
            $this->switchToFrame('iframe[title="TrustArc Cookie Consent Manager"]');
            sleep(5);
            $this->exts->moveToElementAndClick("a.acceptAllButtonLower");
            sleep(10);
            if ($this->exts->exists("a#gwt-debug-close_id")) {
                $this->exts->moveToElementAndClick("a#gwt-debug-close_id");
            }
        }
        sleep(5);

        $this->fillForm(0);
        sleep(15);


        if ($this->exts->urlContains('challenge=/login')) {
            $this->check_solve_blocked_page();
        }
        sleep(10);

        $this->fillForm(0);
        sleep(15);


        if ($this->exts->urlContains('challenge=/login')) {
            $this->check_solve_blocked_page();
        }
        sleep(10);
    }

    if ($this->checkLogin()) {
        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
        $this->check_solve_blocked_page();
        sleep(15);
        $this->exts->capture("LoginSuccess");

        $this->exts->openUrl($this->baseUrl);
        sleep(15);
        $mes_el = $this->exts->getElement('//img[contains(@src, "/account-settings/")]/../../../../../div/h3', null, 'xpath');
        $mes = 'mes';
        if ($mes_el != null) {
            $mes = strtolower($mes_el->getText());
        }
        $this->exts->log('mes: ' . $mes);
        if (strpos($mes, 'verify your identity') !== false) {
            $this->exts->account_not_ready();
        }

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        $this->exts->log('url login failed: ' . $this->exts->getUrl());
        $mes_el = $this->exts->getElement('//img[contains(@src, "/account-settings/")]/../../../../../div/h3', null, 'xpath');
        $mes = 'mes';
        if ($mes_el != null) {
            $mes = strtolower($mes_el->getText());
        }
        $this->exts->log('mes: ' . $mes);
        if (strpos($this->exts->extract('form div.is-error p'), 'passwor') !== false) {
            $this->exts->capture("LoginFailed");
            $this->exts->loginFailure(1);
        } else if (strpos($this->exts->extract('form div.is-error p'), 'an account with this email address was not found') !== false) {
            $this->exts->capture("LoginFailed");
            $this->exts->loginFailure(1);
        } else if (strpos($this->exts->extract('div[class*="ErrorBanner"] small'), 'incorrect email or password') !== false) {
            $this->exts->capture("LoginFailed");
            $this->exts->loginFailure(1);
        } else if (strpos($mes, 'verify your identity') !== false) {
            $this->exts->account_not_ready();
        } else {
            $this->exts->capture("LoginFailed");
            $this->exts->loginFailure();
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

function getInnerTextByJS($selector_or_object, $parent = null)
{
    if ($selector_or_object == null) {
        $this->exts->log(__FUNCTION__ . ' Can not get innerText of null');
        return;
    }
    $element = $selector_or_object;
    if (is_string($selector_or_object)) {
        $element = $this->exts->getElement($selector_or_object, $parent);
        if ($element == null) {
            $element = $this->exts->getElement($selector_or_object, $parent, 'xpath');
        }
        if ($element == null) {
            $this->exts->log(__FUNCTION__ . ':: Can not found element with selector/xpath: ' . $selector_or_object);
        }
    }
    if ($element != null) {
        return $this->exts->executeSafeScript("return arguments[0].innerText", [$element]);
    }
}

/**
    * Method to fill login form
    * @param Integer $count Number of times portal is retried.
    */
function fillForm($count = 0)
{
    $this->exts->log("Begin fillForm " . $count);
    try {

        if ($this->exts->getElement($this->password_selector) != null || $this->exts->getElement($this->username_selector) != null) {
            sleep(2);
            $this->login_tryout = (int)$this->login_tryout + 1;
            $this->exts->capture("1-pre-login");

            if ($this->exts->exists($this->username_selector)) {
                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);
                sleep(2);
            }

            if ($this->exts->exists($this->password_selector)) {
                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                sleep(2);
            }
            $this->exts->moveToElementAndClick($this->submit_button_selector);
            sleep(15);


            if ($this->exts->urlContains('challenge=/login')) {
                $this->check_solve_blocked_page();
            }
            sleep(10);
            $this->checkAndSolveHumanCaptcha();
            sleep(3);
            if (
                $this->exts->getElement($this->password_selector) != null && $this->exts->getElement($this->username_selector) != null
                && !$this->exts->exists('form div.is-error p') && (int)$this->login_tryout < 2
            ) {
                $this->exts->clearCookies();
                $this->exts->openUrl($this->baseUrl);
                sleep(1);

                $this->fillForm(0);
            }

            if ($this->exts->getElement($this->twofa_form_selector) != null) {
                $this->checkFillTwoFactor($this->twofa_form_selector, 'h4 ~ p', $this->submit_button_selector);
            }
            sleep(5);
        }
    } catch (\Exception $exception) {
        $this->exts->log("Exception filling loginform " . $exception->getMessage());
    }
}

private function checkFillTwoFactor($two_factor_selector, $two_factor_message_selector, $two_factor_submit_selector)
{
    if ($this->exts->getElement($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor");

        if ($this->exts->getElement($two_factor_message_selector) != null) {
            $this->exts->two_factor_notif_msg_en = "";
            for ($i = 0; $i < count($this->exts->getElements($two_factor_message_selector)); $i++) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->getElements($two_factor_message_selector)[$i]->getText() . "\n";
            }
            $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
            $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
        } else {
            $two_factor_message_selector = '//input[@id="code"]/../../../preceding-sibling::p';
            if ($this->exts->getElement($two_factor_message_selector, null, 'xpath') != null) {
                $this->exts->two_factor_notif_msg_en = "";

                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->getElements($two_factor_message_selector, null, 'xpath')[0]->getText();

                $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
                $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
            }
        }
        if ($this->exts->two_factor_attempts == 2) {
            $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
        }

        $two_factor_code = trim($this->exts->fetchTwoFactorCode());
        if (!empty($two_factor_code) && trim($two_factor_code) != '') {
            $this->exts->log("checkFillTwoFactor: Entering two_factor_code." . $two_factor_code);
            $this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);
            $this->exts->moveToElementAndClick('input[name="trust_device"]');
            sleep(1);
            $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

            $this->exts->moveToElementAndClick($two_factor_submit_selector);
            sleep(15);

            if ($this->exts->getElement($two_factor_selector) == null) {
                $this->exts->log("Two factor solved");
            } else if ($this->exts->two_factor_attempts < 3) {
                $this->exts->two_factor_attempts++;
                $this->checkFillTwoFactor($two_factor_selector, $two_factor_message_selector, $two_factor_submit_selector);
            } else {
                $this->exts->log("Two factor can not solved");
            }
        } else {
            $this->exts->log("Not received two factor code");
        }
    }
}

function checkLogin()
{
    $this->exts->log("Begin checkLogin ");
    $isLoggedIn = false;
    try {
        if ($this->exts->getElement('a[href*="/logout"], a[href*="/notification"]') != null) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $isLoggedIn = true;
        } else {
            $this->exts->moveToElementAndClick('div[aria-label="User Menu"]');
            sleep(2);
            if ($this->exts->exists('a[href*="/logout"], a[href*="/notification"]')) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $isLoggedIn = true;
            }
        }
    } catch (\Exception $exception) {
        $this->exts->log("Exception checking loggedin " . $exception->getMessage());
    }

    return $isLoggedIn;
}

public function checkAndSolveHumanCaptcha()
{
    $hcaptcha_form_selector = ".challenge-form";
    $hcaptcha_textarea_selector = "textarea[name='h-captcha-response']";
    $gcaptcha_textarea_selector = "textarea[name='g-recaptcha-response']";
    $hcaptcha_sitekey = "33f96e6a-38cd-421b-bb68-7806e1764460";
    $submit_captcha_button = "button#hcaptcha_submit";
    $solved = false;
    $count = 0;
    while (!$solved && $count < 3) {
        if ($this->exts->getElement($hcaptcha_form_selector)) {
            $this->exts->log("Try solving human captcha count " . $count);
            $token = $this->exts->processHumanCaptcha($hcaptcha_form_selector, $hcaptcha_sitekey, $this->exts->getUrl(), true);

            // $captchaScript = '
            // 	function submitToken(token) {
            // 		document.querySelector("[name=g-recaptcha-response]").innerText = token;
            // 		document.querySelector("[name=h-captcha-response]").innerText = token;
            // 		document.querySelector(".challenge-form").submit();
            // 	}
            // 	submitToken(arguments[0]);
            // ';

            // $this->exts->log($captchaScript);
            // $this->exts->executeSafeScript($captchaScript, array($token));
            sleep(5);
            $count++;
        } else {
            $this->exts->log("No captcha found!");
            $solved = true;
        }
    }

    $this->exts->log("Human captcha solved: " . var_export($solved, true));
}

// helper functions
private function check_solve_blocked_page()
{
    $this->exts->capture_by_chromedevtool("blocked-page-checking");
    if ($this->exts->exists('iframe[src*="challenges.cloudflare.com"]')) {
        $this->exts->capture_by_chromedevtool("blocked-by-cloudflare");
        // $this->exts->refresh();
        sleep(40);
        //  $this->exts->click_by_xdotool('iframe[src*="challenges.cloudflare.com"]');
        $this->exts->click_by_xdotool('iframe[src*="challenges.cloudflare.com"]', 30, 28, true);
        sleep(40);
        if ($this->exts->exists('iframe[src*="challenges.cloudflare.com"]')) {
            $this->exts->click_by_xdotool('iframe[src*="challenges.cloudflare.com"]', 30, 28, true);
            sleep(40);
        }
        if ($this->exts->exists('iframe[src*="challenges.cloudflare.com"]')) {
            $this->exts->click_by_xdotool('iframe[src*="challenges.cloudflare.com"]', 30, 28, true);
            sleep(40);
        }
        if ($this->exts->exists('iframe[src*="challenges.cloudflare.com"]')) {
            $this->exts->click_by_xdotool('iframe[src*="challenges.cloudflare.com"]', 30, 28, true);
            sleep(40);
        }
    }
}