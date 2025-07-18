public $baseUrl = 'https://app.pluralsight.com/id';
public $loginUrl = 'https://app.pluralsight.com/id';
public $invoicePageUrl = 'https://billing.pluralsight.com/billing/history';

public $username_selector = 'input#Username';
public $password_selector = 'input#Password';
public $remember_me_selector = '';
public $submit_login_selector = 'button#login';

public $check_login_failed_selector = 'div#errorMessage';
public $check_login_success_selector = 'input#prism-search-input, div[class*="user"] img[src*="customer"], a[href*="/signout"], a[href="/profile"]';

public $isNoInvoice = true;
/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    sleep(1);
    $this->exts->openUrl($this->baseUrl);
    sleep(10);
    $this->check_solve_cloudflare_page();
    // Load cookies
    $this->exts->loadCookiesFromFile();
    sleep(1);
    $this->exts->openUrl($this->baseUrl);
    sleep(10);
    $this->exts->capture('1-init-page');

    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if ($this->exts->querySelector($this->check_login_success_selector) == null) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->openUrl($this->loginUrl);
        sleep(7);
        $this->check_solve_cloudflare_page();
        $this->checkFillHcaptcha();
        $this->checkFillLogin();
        $this->checkFillHcaptcha();
        $this->checkFillTwoFactor();
        sleep(20);
    }



    // then check user logged in or not
    for ($wait_count = 1; $wait_count <= 10 && $this->exts->querySelector($this->check_login_success_selector) == null; $wait_count++) {
        $this->exts->log('Waiting for login...');
        // reload element
        $this->exts->refresh();
        sleep(5);
    }
    if ($this->exts->querySelector($this->check_login_success_selector) != null) {
        sleep(3);
        $this->exts->log(__FUNCTION__ . '::User logged in');
        $this->exts->capture("3-login-success");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        $this->exts->log(__FUNCTION__ . '::Use login failed');
        if ($this->exts->querySelector($this->check_login_failed_selector) != null) {
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}

private function checkFillLogin()
{
    sleep(7);
    $this->check_solve_blocked_page();
    $this->waitFor($this->password_selector, 20);
    if ($this->exts->exists($this->password_selector)) {
        // $this->capture_by_chromedevtool("2-login-page");
        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(1);
        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(3);
        // $this->capture_by_chromedevtool("2-login-page-filled");
        $this->exts->moveToElementAndClick($this->submit_login_selector);
        sleep(10);

        $error_text = strtolower($this->exts->extract($this->check_login_failed_selector));

        $this->exts->log(__FUNCTION__ . '::Error text: ' . $error_text);
        if (stripos($error_text, strtolower('password')) !== false) {
            $this->exts->loginFailure(1);
        }
    }
}

public function waitFor($selector, $seconds = 7)
{
    for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
        $this->exts->log('Waiting for Selectors.....');
        sleep($seconds);
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

private function checkFillHcaptcha($count = 0)
{
    $hcaptcha_iframe_selector = 'div#cf-hcaptcha-container iframe[src*="hcaptcha"]';
    $this->waitFor($hcaptcha_iframe_selector);
    if ($this->exts->exists($hcaptcha_iframe_selector)) {
        $iframeUrl = $this->exts->extract($hcaptcha_iframe_selector, null, 'src');
        $data_siteKey =  end(explode("&sitekey=", $iframeUrl));
        $data_siteKey =  explode("&", $data_siteKey)[0];
        $jsonRes = $this->exts->processHumanCaptcha("", $data_siteKey, $this->exts->getUrl(), false);

        if (!empty($jsonRes) && trim($jsonRes) != '') {
            $captchaScript = "
        function submitToken(token) {
            document.querySelector('[name=\"h-captcha-response\"]').innerText = token;
        }
        submitToken(arguments[0]);
    ";
            $params = array($jsonRes);
            $this->exts->executeSafeScript($captchaScript, $params);
            sleep(2);

            $captchaScript = '
        function submitToken1(token) {
            form1 = document.querySelector("form#challenge-form div#cf-hcaptcha-container div:not([style*=\"display: none\"]) iframe");
            form1.removeAttribute("data-hcaptcha-response");
            var att = document.createAttribute("data-hcaptcha-response");
            att.value = token;
            
            form1.setAttributeNode(att);
        }
        submitToken1(arguments[0]);
        ';
            $params = array($jsonRes);
            $this->exts->executeSafeScript($captchaScript, $params);

            // $captchaScript = '
            //     function submitToken2(token) {
            //       form1 = document.querySelector("form#challenge-form div#cf-hcaptcha-container div[style*=\"display: none\"] iframe");
            //       form1.removeAttribute("data-hcaptcha-response");
            //       var att = document.createAttribute("data-hcaptcha-response");
            //       att.value = token;

            //       form1.setAttributeNode(att);
            //     }
            //     submitToken2(arguments[0]);
            //  ';
            // $params = array($jsonRes);
            // $this->exts->executeSafeScript($captchaScript, $params);
            $this->exts->log('-------------------------------');
            $this->exts->log($this->exts->extract('[name="h-captcha-response"]', null, 'innerText'));
            $this->exts->log('-------------------------------');
            $this->exts->log($this->exts->extract('form#challenge-form div#cf-hcaptcha-container div:not([style*="display: none"]) iframe', null, 'data-hcaptcha-response'));
            $this->exts->log('-------------------------------');
            $this->exts->log($this->exts->extract('form#challenge-form div#cf-hcaptcha-container div[style*="display: none"] iframe', null, 'data-hcaptcha-response'));
            $this->exts->log('-------------------------------');
            $this->exts->executeSafeScript('document.querySelector("form#challenge-form").submit();');
            sleep(15);
        }

        if ($this->exts->exists($hcaptcha_iframe_selector) && $count < 5) {
            $count++;
            $this->exts->refresh();
            sleep(15);
            $this->checkFillHcaptcha($count);
        }
    }
}

private function checkFillTwoFactor()
{
    $two_factor_selector = 'input#mfa-code';
    $two_factor_message_selector = 'form#mfaSignInForm div.text-with-icon span';
    $two_factor_submit_selector = 'button#mfa-code-button';
    $this->waitFor($two_factor_selector);
    if ($this->exts->querySelector($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor");

        if ($this->exts->querySelector($two_factor_message_selector) != null) {
            $this->exts->two_factor_notif_msg_en = "";
            for ($i = 0; $i < count($this->exts->querySelectorAll($two_factor_message_selector)); $i++) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->querySelectorAll($two_factor_message_selector)[$i]->getText() . "\n";
            }
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
            $this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);

            $this->exts->log("checkFillTwoFactor: Clicking submit button.");
            sleep(3);
            $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

            $this->exts->moveToElementAndClick($two_factor_submit_selector);
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

private function check_solve_blocked_page()
{
    // $this->capture_by_chromedevtool("blocked-page-checking");
    if ($this->exts->exists('iframe[src*="challenges.cloudflare.com"]')) {
        // $this->capture_by_chromedevtool("blocked-by-cloudflare");
        $this->exts->click_by_xdotool('iframe[src*="challenges.cloudflare.com"]');
        sleep(10);
        if ($this->exts->exists('iframe[src*="challenges.cloudflare.com"]')) {
            $this->exts->click_by_xdotool('iframe[src*="challenges.cloudflare.com"]');
            sleep(10);
        }
        if ($this->exts->exists('iframe[src*="challenges.cloudflare.com"]')) {
            $this->exts->click_by_xdotool('iframe[src*="challenges.cloudflare.com"]');
            sleep(10);
        }
    }
}