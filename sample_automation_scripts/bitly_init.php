public $baseUrl = 'https://app.bitly.com';
public $username_selector = 'input[name="username"][autocomplete="username email"]';
public $password_selector = 'input[name="password"][autocomplete="current-password"]';
public $submit_login_selector = 'button[type="submit"]';
public $check_login_failed_selector = '.error-message, aside[role="alert"]';
public $check_login_success_selector = '.navigation--switch .main-menu .orb-dropdown, a[href*="sign_out"], .user-menu .user-name';
public $isNoInvoice = true;
/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);

    // Load cookies
    $this->exts->loadCookiesFromFile();
    $this->exts->openUrl($this->baseUrl);
    $this->exts->capture('1-init-page');
    sleep(7);

    $this->waitFor($this->check_login_success_selector, 10);
    if ($this->exts->querySelector($this->check_login_success_selector) == null) {
        $this->exts->log('NOT logged via cookie');
        $this->checkFillLogin();
        sleep(5);
    }


    //check for verfication email button
    $this->waitFor('#send_verification_email');
    if ($this->exts->exists('#send_verification_email')) {
        $this->exts->log('verfication email send button found');
        $this->exts->capture('send_verification_email');
        if ($this->exts->exists('#send_verification_email')) {
            $this->exts->click_by_xdotool('#send_verification_email');
        }
        $verfication_link = $this->fetchVerificationLink();
        $this->exts->openUrl($verfication_link);
    } elseif ($this->exts->exists('div.email-modal')) {
        $this->checkFill2FAPushNotification();
    }

    $this->checkFillTwoFactor();

    // then check user logged in or not
    // $this->exts->click_by_xdotool('.navigation--switch .main-menu .orb-dropdown .selector-icon');
    $this->waitFor($this->check_login_success_selector);
    if ($this->exts->querySelector($this->check_login_success_selector) != null) {
        $this->exts->log(__FUNCTION__ . '::User logged in');
        $this->exts->capture("3-login-success");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        $this->exts->log(__FUNCTION__ . '::Use login failed');
        if (stripos($this->exts->extract($this->check_login_failed_selector, null, 'innerText'), 'Email / username or password is incorrect.') !== false) {
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}

private function checkFillLogin()
{
    $this->waitFor('//div[text()="Password"]/following-sibling::input');
    if ($this->exts->queryXpath('//div[text()="Password"]/following-sibling::input') != null) {
        $this->exts->click_by_xdotool('form');
        sleep(3);
        $this->exts->capture("2-login-page");

        $this->exts->log("Enter Username");
        $username_element = $this->exts->queryXpath('//div[text()="Email"]/following-sibling::input');
        $this->exts->click_element($username_element);
        sleep(1);
        $this->exts->type_text_by_xdotool($this->username);
        // $this->exts->moveToElementAndType($username_element, $this->username);
        sleep(1);

        $this->exts->log("Enter Password");
        $password_element = $this->exts->queryXpath('//div[text()="Password"]/following-sibling::input');
        $this->exts->click_element($password_element);
        sleep(1);
        $this->exts->type_text_by_xdotool($this->password);
        // $this->exts->moveToElementAndType($password_element, $this->password);
        sleep(1);

        $this->exts->capture("2-login-page-filled");
        $this->exts->click_by_xdotool($this->submit_login_selector);
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}

public function waitFor($selector, $seconds = 7)
{
    for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
        $this->exts->log('Waiting for Selectors.....');
        sleep($seconds);
    }
}

private function checkFillTwoFactor(): void
{
    $selector = 'input[maxlength="6"]';
    $message_selector = 'form h1 + p';
    $submit_selector = 'button[type="submit"]';

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
        $this->exts->moveToElementAndClick('form input[type="checkbox"]');
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

private function checkFill2FAPushNotification()
{
    $two_factor_message_selector = 'div.email-modal div.content p:first-child';
    $two_factor_submit_selector = '';
    $this->waitFor($two_factor_message_selector, 15);
    if ($this->exts->querySelector($two_factor_message_selector) != null && $this->exts->two_factor_attempts < 3) {
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor");
        if ($this->exts->querySelector($two_factor_message_selector) != null) {
            $this->exts->two_factor_notif_msg_en = $this->exts->extract($two_factor_message_selector);
            $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en) . ' Please input "OK" when finished!!';
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
            $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
        }
        if ($this->exts->two_factor_attempts == 2) {
            $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
        }
        $two_factor_code = trim(strtolower($this->exts->fetchTwoFactorCode()));
        if (!empty($two_factor_code) && trim($two_factor_code) == 'ok') {
            $this->exts->log("checkFillTwoFactorForMobileAcc: Entering two_factor_code." . $two_factor_code);
            $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);
            sleep(15);
            if ($this->exts->querySelector($two_factor_message_selector) == null) {
                $this->exts->log("Two factor solved");
            } else if ($this->exts->two_factor_attempts < 3) {
                $this->exts->two_factor_attempts++;
                $this->checkFill2FAPushNotification();
            } else {
                $this->exts->log("Two factor can not solved");
            }
        } else {
            $this->exts->log("Not received two factor code");
        }
    }
}

public $two_factor_notif_msg_de = '';
public $two_factor_notif_msg_en = 'Please enter the confirmation link to proceed with the login.';
public $two_factor_notif_title_en = "%portal% - Two-Factor-Authorization";
public $two_factor_notif_title_de = "%portal% - Zwei-Faktor-Anmeldung";
public $two_factor_notif_msg_retry_en = " (Your last input was either wrong or too late)";
public $two_factor_notif_msg_retry_de = " (Ihre letzte Eingabe war leider falsch oder zu spÃ¤t)";
public $two_factor_timeout = 15;

public function fetchVerificationLink()
{
    $this->exts->log("--Fetching Two Factor Code--");
    $this->exts->capture("TwoFactorFetchCode");
    // if (!$this->two_factor_notif_msg_en || trim($this->two_factor_notif_msg_en) == "") {
    //     $this->two_factor_notif_msg_en = $this->exts->default_two_factor_notif_msg_en;
    // }
    // if (!$this->two_factor_notif_msg_de || trim($this->two_factor_notif_msg_de) == "") {
    //     $this->two_factor_notif_msg_de = $this->default_two_factor_notif_msg_de;
    // }
    $extra_data = array(
        "en_title" => $this->two_factor_notif_title_en,
        "en_msg" => $this->two_factor_notif_msg_en,
        "de_title" => $this->two_factor_notif_title_de,
        "de_msg" => $this->two_factor_notif_msg_de,
        "timeout" => $this->two_factor_timeout,
        "retry_msg_en" => $this->two_factor_notif_msg_retry_en,
        "retry_msg_de" => $this->two_factor_notif_msg_retry_de
    );

    $verfication_link = $this->exts->sendRequest($this->exts->process_uid, $this->exts->config_array['two_factor_shell_script'], $extra_data);
    $this->exts->log('verfication link');
    $this->exts->log($verfication_link);

    return $verfication_link;
}