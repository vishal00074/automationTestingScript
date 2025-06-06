public $baseUrl = "https://www.mollie.com";
public $loginUrl = "https://www.mollie.com/dashboard/login?lang=en";
public $invoiceUrl = "https://www.mollie.com/dashboard/administration/invoices";
public $settlementUrl = "https://www.mollie.com/dashboard/administration/settlements";
public $currentInvoiceUrl = "";
public $currentSettlementUrl = "";

public $submit_btn = "form.auth-form button[type='submit']:not([disabled])";
public $username_selector = 'form.auth-form input#username, input#email';
public $password_selector = 'form.auth-form input#password';
public $remember_me_selector = "b.checkbox__button";
public $login_error_msg_selector = "div.errorbox";

public $logout_link = "button[data-testid='organization-switcher-toggle'],button.c-user__button, button.c-user__button, a[href*='/logout'], a[href*='payments']";
public $login_tryout = 0;
public $restrictPages = 0;
public $last_state = array();
public $current_state = array();
public $invoice_data_arr = array();
public $isNoInvoice = true;

public $user_account_ids = array();
public $download_settlements = 0;

private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
    $this->download_settlements = isset($this->exts->config_array["download_settlements"]) ? (int)@$this->exts->config_array["download_settlements"] : $this->download_settlements;
    $account_numbers = isset($this->exts->config_array["account_numbers"]) ? trim($this->exts->config_array["account_numbers"]) : '';
    $this->exts->log('Account Number Provided by user - ' . $account_numbers);
    if (trim($account_numbers) != '' && !empty($account_numbers)) {
        $this->user_account_ids = explode(',', $account_numbers);
    }
    $this->exts->log('Account Number Provided by user - ' . print_r($this->user_account_ids, true));
    $this->exts->openUrl($this->invoiceUrl);
    sleep(4);
    $this->exts->loadCookiesFromFile();
    sleep(1);
    $this->exts->openUrl($this->invoiceUrl);
    sleep(10);
    $this->exts->capture("1-init-page");
    if (!$this->checkLogin()) {
        $this->exts->openUrl($this->loginUrl);
        // $this->checkFillRecaptcha();
        $this->fillForm(0);
        sleep(5);
        if ($this->exts->urlContains("challengePage=true")) {
            $this->exts->capture("challengePage");
            sleep(5);
            $this->solve_captcha_by_clicking();
            $this->solve_captcha_by_clicking();
            $this->solve_captcha_by_clicking();
            $this->solve_captcha_by_clicking();
            $this->solve_captcha_by_clicking();
            if ($this->exts->exists($this->submit_btn)) {
                $this->fillForm(0);
                sleep(5);
                if ($this->exts->querySelector('form.js-two-factor-authentication-form') != null || $this->exts->urlContains('twofactorauthentication')) {
                    $this->chooseMethodTwoFactor();
                } else {
                    $this->exts->log('--- Not found 2FA page ---');
                }
            }
        } else {
            if ($this->exts->querySelector('form.js-two-factor-authentication-form') != null || $this->exts->urlContains('twofactorauthentication')) {
                $this->chooseMethodTwoFactor();
            } else {
                $this->exts->log('--- Not found 2FA page ---');
            }
            sleep(5);
        }
        $this->exts->capture("2.2-after-login");
    }

    if ($this->checkLogin()) {
        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
        $this->exts->capture("3-LoginSuccess");
        
        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        if ($this->exts->urlContains('onboarding') && $this->exts->getElement('[data-control-name="countryCode"]') != null) {
            $this->exts->account_not_ready();
        }
        if (
            stripos(strtolower($this->exts->extract($this->login_error_msg_selector)), 'invalid combination of email') !== false ||
            stripos(strtolower($this->exts->extract($this->login_error_msg_selector)), 'kombination aus e-mail-adresse') !== false ||
            stripos(strtolower($this->exts->extract($this->login_error_msg_selector)), 'invalid login details') !== false
        ) {
            $this->exts->capture("LoginFailed-confirmed");
            $this->exts->loginFailure(1);
        } else {
            $this->exts->capture("LoginFailed");
            $this->exts->loginFailure();
        }
    }
}

function fillForm($count)
{
    if ($this->exts->exists($this->password_selector)) {

        $this->exts->capture("1-pre-login");
        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(2);

        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(2);

        if ($this->exts->exists($this->remember_me_selector)) {
            $this->exts->click_element($this->remember_me_selector);
            sleep(2);
        }

        if ($this->exts->querySelector($this->submit_btn) != null) {
            $this->exts->click_by_xdotool($this->submit_btn);
            sleep(7);
        }

        if ($this->exts->querySelector('div.errorbox button') != null) {
            $this->exts->click_by_xdotool('div.errorbox button');
            sleep(7);
        }
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}

private function solve_captcha_by_clicking($count = 1)
{
    $this->exts->log("Checking captcha");
    $language_code = '';
    $hcaptcha_challenger_wraper_selector = 'div[style*="visibility: visible;"]  iframe[title="recaptcha challenge expires in two minutes"]';
    $this->exts->waitTillPresent($hcaptcha_challenger_wraper_selector, 20);
    if ($this->exts->exists($hcaptcha_challenger_wraper_selector)) {

        $this->exts->capture("re-captcha");

        $captcha_instruction = '';

        //$captcha_instruction = $this->exts->extract($iframeElement_instartion,null, 'innerText');
        $this->exts->log('language_code: ' . $language_code . ' Instruction: ' . $captcha_instruction);
        sleep(5);
        $captcha_wraper_selector = 'div[style*="visibility: visible;"]  iframe[title="recaptcha challenge expires in two minutes"]';

        if ($this->exts->exists($captcha_wraper_selector)) {
            $coordinates = $this->getCoordinates($captcha_wraper_selector, $captcha_instruction, '', $json_result = false);

            // if($coordinates == '' || count($coordinates) < 2){
            //  $coordinates = $this->exts->processClickCaptcha($captcha_wraper_selector, $captcha_instruction, '', $json_result=false);
            // }
            if ($coordinates != '') {
                // $challenge_wraper = $this->exts->querySelector($captcha_wraper_selector);

                foreach ($coordinates as $coordinate) {
                    $this->click_captcha_point($captcha_wraper_selector, (int)$coordinate['x'], (int)$coordinate['y']);
                }

                $this->exts->capture("re-captcha-selected " . $count);
                $this->exts->makeFrameExecutable('div[style*="visibility: visible;"]  iframe[title="recaptcha challenge expires in two minutes"]')->click_element('button[id="recaptcha-verify-button"]');
                sleep(10);
                return true;
            }
        }

        return false;
    }
}

private function click_captcha_point($selector = '', $x_on_element = 0, $y_on_element = 0)
{
    $this->exts->log(__FUNCTION__ . " $selector $x_on_element $y_on_element");
    $selector = base64_encode($selector);
    $element_coo = $this->exts->execute_javascript('
        var x_on_element = ' . $x_on_element . ';
        var y_on_element = ' . $y_on_element . ';
        var coo = document.querySelector(atob("' . $selector . '")).getBoundingClientRect();
        // Default get center point in element, if offset inputted, out put them
        if(x_on_element > 0 || y_on_element > 0) {
            Math.round(coo.x + x_on_element) + "|" + Math.round(coo.y + y_on_element);
        } else {
            Math.round(coo.x + coo.width/2) + "|" + Math.round(coo.y + coo.height/2);
        }
        
    ');
    // sleep(1);
    $this->exts->log("Browser clicking position: $element_coo");
    $element_coo = explode('|', $element_coo);

    $root_position = $this->exts->get_brower_root_position();
    $this->exts->log("Browser root position");
    $this->exts->log(print_r($root_position, true));

    $clicking_x = (int)$element_coo[0] + (int)$root_position['root_x'];
    $clicking_y = (int)$element_coo[1] + (int)$root_position['root_y'];
    $this->exts->log("Screen clicking position: $clicking_x $clicking_y");
    $node_name = !empty($this->exts->config_array['node_name']) ? $this->exts->config_array['node_name'] : "selenium-node-" . $this->exts->process_uid;
    // move randomly
    exec("sudo docker exec " . $node_name . " bash -c 'xdotool mousemove " . rand($clicking_x - 60, $clicking_x + 60) . " " . rand($clicking_y - 50, $clicking_y + 50) . "'");
    exec("sudo docker exec " . $node_name . " bash -c 'xdotool mousemove " . rand($clicking_x - 50, $clicking_x + 50) . " " . rand($clicking_y - 50, $clicking_y + 50) . "'");
    exec("sudo docker exec " . $node_name . " bash -c 'xdotool mousemove " . rand($clicking_x - 40, $clicking_x + 40) . " " . rand($clicking_y - 41, $clicking_y + 40) . "'");
    exec("sudo docker exec " . $node_name . " bash -c 'xdotool mousemove " . rand($clicking_x - 30, $clicking_x + 30) . " " . rand($clicking_y - 35, $clicking_y + 30) . "'");
    exec("sudo docker exec " . $node_name . " bash -c 'xdotool mousemove " . rand($clicking_x - 20, $clicking_x + 20) . " " . rand($clicking_y - 25, $clicking_y + 25) . "'");
    exec("sudo docker exec " . $node_name . " bash -c 'xdotool mousemove " . rand($clicking_x - 10, $clicking_x + 10) . " " . rand($clicking_y - 10, $clicking_y + 10) . "'");

    exec("sudo docker exec " . $node_name . " bash -c 'xdotool mousemove " . $clicking_x . " " . $clicking_y . " click 1;'");
}

private function getCoordinates(
    $captcha_image_selector,
    $instruction = '',
    $lang_code = '',
    $json_result = false,
    $image_dpi = 75
) {
    $this->exts->log("--GET Coordinates By 2CAPTCHA--");
    $response = '';
    $image_path = $this->exts->captureElement($this->exts->process_uid, $captcha_image_selector);
    $source_image = imagecreatefrompng($image_path);
    imagejpeg($source_image, $this->exts->screen_capture_location . $this->exts->process_uid . '.jpg', $image_dpi);

    $cmd = $this->exts->config_array['click_captcha_shell_script'] . " --PROCESS_UID::" . $this->exts->process_uid . " --CAPTCHA_INSTRUCTION::" . urlencode($instruction) . " --LANG_CODE::" . urlencode($lang_code) . " --JSON_RESULT::" . urlencode($json_result);
    $this->exts->log('Executing command : ' . $cmd);
    exec($cmd, $output, $return_var);
    $this->exts->log('Command Result : ' . print_r($output, true));

    if (!empty($output)) {
        $output = trim($output[0]);
        if ($json_result) {
            if (strpos($output, '"status":1') !== false) {
                $response = json_decode($output, true);
                $response = $response['request'];
            }
        } else {
            if (strpos($output, 'coordinates:') !== false) {
                $array = explode("coordinates:", $output);
                $response = trim(end($array));
                $coordinates = [];
                $pairs = explode(';', $response);
                foreach ($pairs as $pair) {
                    preg_match('/x=(\d+),y=(\d+)/', $pair, $matches);
                    if (!empty($matches)) {
                        $coordinates[] = ['x' => (int)$matches[1], 'y' => (int)$matches[2]];
                    }
                }
                $this->exts->log('>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>');
                $this->exts->log(print_r($coordinates, true));
                return $coordinates;
            }
        }
    }

    if ($response == '') {
        $this->exts->log("Can not get result from API");
    }
    return $response;
}

private function chooseMethodTwoFactor()
{
    $this->exts->capture('2.1-two-factor-page');
    if ($this->exts->exists('form.js-two-factor-authentication-form input.js-char-input')) {
        $this->checkFillTwoFactorOTP();
    } else {
        // choose other methord
        if ($this->exts->querySelector('form.js-two-factor-authentication-app') != null && $this->exts->querySelector('.form-group--button a[href*="twofactorauthentication/method"]') != null) {
            $this->exts->moveToElementAndClick('.form-group--button a[href*="twofactorauthentication/method"]');
            sleep(5);
            if ($this->exts->querySelector('ul li a.auth-form__nav-link[href*="totp"]') != null) {
                // fill code from authenticator app
                $this->exts->moveToElementAndClick('ul li a.auth-form__nav-link[href*="totp"]');
                sleep(5);
                $this->checkFillTwoFactorOTP();
            } else if ($this->exts->querySelector('ul li a.auth-form__nav-link[href*="sms"]') != null) {
                // fill code send to sms
                $this->exts->moveToElementAndClick('ul li a.auth-form__nav-link[href*="sms"]');
                sleep(5);
                $this->checkFillTwoFactorOTP('SMS');
            } else if ($this->exts->querySelector('ul li a.auth-form__nav-link[href*="switch/app"]') != null) {
                // accept on user device
                $this->exts->moveToElementAndClick('ul li a.auth-form__nav-link[href*="switch/app"]');
                sleep(5);
                $this->checkFillTwoFactorPush();
            } else if ($this->exts->querySelector('ul li a.auth-form__nav-link[href*="backup_code"]') != null) {
                // fill backup code
                $this->exts->moveToElementAndClick('ul li a.auth-form__nav-link[href*="backup_code"]');
                sleep(5);
                $this->checkFillTwoFactorOTP("Backup Code");
            } else {
                $this->exts->log("----- Not found any 2FA method -----");
            }
        } else {
            $this->exts->log("----- Not found button choose other 2FA method -----");
        }
    }
}

public function checkFillTwoFactorOTP($type = "Authenticator App")
{
    if ($this->exts->exists('form.js-two-factor-authentication-form input.js-char-input')) {
        $two_factor_selector = 'form.js-two-factor-authentication-form input.js-char-input';
        $two_factor_message_selector = 'form.js-two-factor-authentication-form p.auth-form__verify-paragraph, .auth-form-title';
        $two_factor_submit_selector = 'form.js-two-factor-authentication-form button[type="submit"]';

        if ($this->exts->querySelector($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor-otp");

            $this->exts->two_factor_notif_msg_en = "CHECK your " . $type . "\n";
            $this->exts->two_factor_notif_msg_en = trim($this->exts->extract($two_factor_message_selector, null, 'innerText'));
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
            $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
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

                $this->exts->moveToElementAndClick($two_factor_submit_selector);
                sleep(15);

                if ($this->exts->querySelector($two_factor_selector) == null) {
                    $this->exts->log("Two factor solved");
                } else if ($this->exts->two_factor_attempts < 3) {
                    $this->exts->two_factor_attempts++;
                    $this->checkFillTwoFactorOTP();
                } else {
                    $this->exts->log("Two factor can not solved");
                }
            } else {
                $this->exts->log("Not received two factor code");
            }
        }
    }
}

private function checkFillTwoFactorPush()
{
    $two_factor_message_selector = 'form.js-two-factor-authentication-app  p.auth-form-text';
    $two_factor_submit_selector = 'form.js-two-factor-authentication-app .on-verification-pending button[data-request-status="success"]';
    sleep(5);
    if ($this->exts->querySelector($two_factor_message_selector) != null && $this->exts->two_factor_attempts < 3) {
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor-push");
        if ($this->exts->querySelector($two_factor_message_selector) != null) {
            $this->exts->two_factor_notif_msg_en = join("\n", $this->exts->getElementsAttribute($two_factor_message_selector, 'innerText'));
            $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en) . ' Please input "OK" when finished!!';
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
            $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
        }
        if ($this->exts->two_factor_attempts == 2) {
            $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
        }
        $two_factor_code = trim(strtolower($this->exts->fetchTwoFactorCode()));
        if (!empty($two_factor_code) && trim(strtolower($two_factor_code)) == 'ok') {
            $this->exts->log("checkFillTwoFactorForMobileAcc: Entering two_factor_code." . $two_factor_code);
            $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);
            sleep(15);
            if ($this->exts->querySelector($two_factor_message_selector) == null) {
                $this->exts->log("Two factor solved");
            } else if ($this->exts->two_factor_attempts < 3) {
                $this->exts->two_factor_attempts++;
                $this->checkFillTwoFactorPush();
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
        $isLoginForm = $this->exts->exists($this->username_selector);
        if (!$isLoginForm) {
            if ($this->exts->exists($this->logout_link)) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful 1!!!!");
                $isLoggedIn = true;
            }
        }
    } catch (\Exception $exception) {
        $this->exts->log("Exception checking loggedin " . $exception);
    }

    return $isLoggedIn;
}