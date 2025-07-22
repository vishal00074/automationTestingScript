public $baseUrl = 'https://app.hubstaff.com/organizations/';
public $loginUrl = 'https://app.hubstaff.com/organizations/';
public $invoicePageUrl = 'https://app.hubstaff.com/organizations/';

public $username_selector = 'form[action="/login"] input#user_email';
public $password_selector = 'form[action="/login"] input#user_password';
public $remember_me_selector = '';
public $submit_login_selector = 'form[action="/login"] button[type="submit"]';

public $check_login_failed_selector = 'form[action="/login"] .list-group-item-danger';
public $check_login_success_selector = 'a[href="/logout"]';

public $freelancer_invoices = 0;
public $isNoInvoice = true;
/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);


    $this->freelancer_invoices = isset($this->exts->config_array["freelancer_invoices"]) ? (int)@$this->exts->config_array["freelancer_invoices"] : $this->freelancer_invoices;

    // Load cookies
    $this->exts->loadCookiesFromFile();
    sleep(1);
    $this->exts->openUrl($this->baseUrl);
    sleep(10);
    $this->check_solve_blocked_page();
    $this->checkFillHcaptcha(0);
    $this->exts->capture('1-init-page');

    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if ($this->exts->getElement($this->check_login_success_selector) == null) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->openUrl($this->loginUrl);
        sleep(15);
        $this->check_solve_blocked_page();
        $this->checkFillHcaptcha(0);
        $this->checkFillLogin();
        sleep(20);
        $this->check_solve_blocked_page();
        $this->checkFillHcaptcha(0);

        $this->checkFillTwoFactor();
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
        if (
            strpos(strtolower($this->exts->extract($this->check_login_failed_selector, null, 'innerText')), 'passwor') !== false ||
            strpos(strtolower($this->exts->extract('span.help-block', null, 'innerText')), 'invalid') !== false
        ) {
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}

private function checkFillLogin()
{
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


private function checkFillTwoFactor()
{
    $two_factor_selector = 'input[id="user_otp"]';
    $two_factor_message_selector = 'span.help-block';
    $two_factor_submit_selector = 'button.submit-otp.confirm';

    if ($this->exts->querySelector($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor");

        $this->exts->type_key_by_xdotool('Return');
        sleep(5);

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
            sleep(1);
            $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

            // $this->exts->moveToElementAndClick($two_factor_submit_selector); // auto submit twoFA
            sleep(15);

            if ($this->exts->querySelector($two_factor_selector) == null) {
                $this->exts->log("Two factor solved");
            } else if ($this->exts->two_factor_attempts < 3) {
                $this->exts->two_factor_attempts++;
                $this->exts->notification_uid = '';
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
    $hcaptcha_iframe_selector = 'div#cf-hcaptcha-container iframe[src*="hcaptcha"]';
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

private function check_solve_blocked_page()
{
    $this->exts->capture_by_chromedevtool("blocked-page-checking");
    if ($this->exts->exists('iframe[src*="challenges.cloudflare.com"]')) {
        $this->exts->capture_by_chromedevtool("blocked-by-cloudflare");
        // $this->exts->refresh();
        sleep(10);
        // $this->exts->click_by_xdotool('iframe[src*="challenges.cloudflare.com"]');
        $this->exts->click_by_xdotool('iframe[src*="challenges.cloudflare.com"]', 30, 28);
        sleep(15);
        if ($this->exts->exists('iframe[src*="challenges.cloudflare.com"]')) {
            $this->exts->click_by_xdotool('iframe[src*="challenges.cloudflare.com"]', 30, 28);
            sleep(15);
        }
        if ($this->exts->exists('iframe[src*="challenges.cloudflare.com"]')) {
            $this->exts->click_by_xdotool('iframe[src*="challenges.cloudflare.com"]', 30, 28);
            sleep(15);
        }
    }
}