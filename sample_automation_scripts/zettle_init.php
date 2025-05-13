public $baseUrl = 'https://my.izettle.com';
public $loginUrl = 'https://my.izettle.com';
public $invoicePageUrl = 'https://my.zettle.com/invoices/orders?status=PAID';
public $username_selector = 'form input[name="username"]';
public $password_selector = 'form input[name="password"], input[name="login_password"]';
public $remember_me_selector = '';
public $submit_login_selector = 'form button[type="submit"], button[id="btnLogin"]';
public $check_login_failed_selector = '.flash.error .message, .error-message, p[id="inputError"], p[role="alert"]';
public $check_login_success_selector = 'iz-bo-header, iz-bo-header[user-name], .dropdown-user a[ng-click*="logout"], iz-bo-vertical-navigation';
public $isNoInvoice = true;
public $download_monthly_report = 0;
public $download_daily_report = 0;
public $restrictPages = 3;

/**

    * Entry Method thats called for a portal

    * @param Integer $count Number of times portal is retried.  

    */
private function initPortal($count)
{
    $this->clearChrome();
    sleep(2);
    $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int) @$this->exts->config_array["restrictPages"] : 3;
    $this->download_monthly_report = isset($this->exts->config_array["download_monthly_report"]) ? (int) @$this->exts->config_array["download_monthly_report"] : 0;
    $this->download_daily_report = isset($this->exts->config_array["download_daily_report"]) ? (int) @$this->exts->config_array["download_daily_report"] : 0;

    $this->exts->log('download_monthly_report ' . $this->download_monthly_report);
    $this->exts->log('download_daily_report ' . $this->download_daily_report);


    $this->exts->log('Begin initPortal ' . $count);
    $this->exts->loadCookiesFromFile();
    $this->exts->openUrl($this->loginUrl);
    if (!$this->checkLogin()) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();
        $this->exts->openUrl($this->loginUrl);
        $this->fillForm(0);
        sleep(10);
        if (stripos(strtolower($this->exts->extract('p.message')), 'suspicious behaviour') !== false) {
            $this->exts->capture('suspicious-behaviour-detected');
            $this->exts->log('Try to login in again!');
            $this->clearChrome();
            $this->exts->openUrl($this->loginUrl);
            $this->fillForm(0);
            sleep(10);
        }

        $this->checkFillRecaptcha(1);
        sleep(10);
        // $this->solve_captcha_by_clicking(1);
        $is_captcha = $this->solve_captcha_by_clicking(0);
        if ($is_captcha) {
            for ($i = 1; $i < 30; $i++) {
                if ($is_captcha == false) {
                    break;
                }
                $is_captcha = $this->solve_captcha_by_clicking($i);
            }
        }
        sleep(5);
        $this->exts->waitTillPresent('div[data-nemo="twofactorPage"] button', 10);
        if ($this->exts->exists('div[data-nemo="twofactorPage"] button')) {
            $this->exts->click_by_xdotool('div[data-nemo="twofactorPage"] button');
        }
        $this->checkFillTwoFactor();
    }


    if ($this->checkLogin()) {
        $this->exts->log(">>>>>>>>>>>>>>>Login successfufl!!!!");
        $this->exts->capture("LoginSuccess");

        // accecpt cookies
        if ($this->exts->exists('button#onetrust-accept-btn-handler')) {
            $this->exts->moveToElementAndClick('button#onetrust-accept-btn-handler');
            sleep(2);
        }

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        if (
            stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'passwor') !== false ||
            stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'correct') !== false
        ) {
            $this->exts->log("Wrong credential !!!!");
            $this->exts->loginFailure(1);
        } else if (stripos($this->exts->extract('p#inputError', null, 'innerText'), 'ihr konto wurde aus sicherheitsgr') !== false || stripos($this->exts->extract('p#inputError', null, 'innerText'), 'we have temporarily locked it') !== false) {
            $this->exts->account_not_ready();
        } else if (strpos(strtolower($this->exts->extract('p#inputError', null, 'innerText')), 'please type a valid email address') !== false) {
            $this->exts->loginFailure(1);
        } else if ($this->exts->exists($this->check_login_failed_selector)) {
            $errMsg = $this->exts->extract($this->check_login_failed_selector);
            $this->exts->log('Error Message - ' . $errMsg);
            if (stripos($errMsg, 'Login-Daten') !== false || stripos($errMsg, 'Passwort ist falsch') !== false) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        } else if ($this->exts->exists('section#login .notifications p.notification-critical')) {
            $errMsg = $this->exts->extract('section#login .notifications p.notification-critical');
            $this->exts->log('Error Message - ' . $errMsg);
            if (stripos($errMsg, 'Login-Daten') !== false || stripos($errMsg, 'Passwort ist falsch') !== false || stripos($errMsg, 'your information isn\'t correct') !== false) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        } else {
            $this->exts->loginFailure();
        }
    }
}


private function fillForm($count)
{
    $this->exts->log("Begin fillForm " . $count);
    $this->exts->waitTillPresent($this->username_selector, 5);
    try {
        if ($this->exts->querySelector($this->username_selector) != null) {

            $this->exts->capture("1-pre-login");
            $this->exts->log("Enter Username");
            // $this->exts->moveToElementAndType($this->username_selector, $this->username);
            $this->exts->click_by_xdotool($this->username_selector);
            sleep(2);
            $this->exts->type_text_by_xdotool($this->username);
            sleep(3);

            if (!$this->isValidEmail($this->username)) {
                $this->exts->loginFailure(1);
            }

            if ($this->exts->exists($this->password_selector)) {
                $this->exts->log("Enter Password");
                // $this->exts->moveToElementAndType($this->password_selector, $this->password);
                $this->exts->click_by_xdotool($this->password_selector);
                sleep(2);
                $this->exts->type_text_by_xdotool($this->password);
            } else {
                if ($this->exts->exists($this->submit_login_selector)) {
                    $this->exts->click_by_xdotool($this->submit_login_selector);
                }
            }
            $this->exts->waitTillPresent('button#onetrust-accept-btn-handler, button#acceptAllButton');
            if ($this->exts->exists('button#onetrust-accept-btn-handler, button#acceptAllButton')) {
                $this->exts->moveToElementAndClick('button#onetrust-accept-btn-handler, button#acceptAllButton');
                sleep(1);
            }
            $this->exts->waitTillPresent($this->password_selector, 20);
            if ($this->exts->exists($this->password_selector)) {
                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
            }

            sleep(2);
            $this->exts->capture("1-login-page-filled");
            sleep(5);
            if ($this->exts->exists($this->submit_login_selector)) {
                $this->exts->moveToElementAndClick($this->submit_login_selector);
                sleep(10);
            }


            if ($this->exts->urlContains('email-confirmation')) {
                $this->exts->type_key_by_xdotool('Return');
                sleep(5);
                $this->exts->moveToElementAndClick('a[class="skip-button"]');
                sleep(10);
            }

            $error_text = strtolower($this->exts->extract('p#inputError'));
            $this->exts->log("Error text:: " . $error_text);
            if (
                stripos(strtolower($error_text), strtolower('Check your email and password and try again.')) !== false ||
                stripos(strtolower($error_text), strtolower('Please type a valid email address.')) !== false
            ) {
                $this->exts->log(__FUNCTION__ . '::Use login failed');
                $this->exts->log(__FUNCTION__ . '::Last URL: ' . $this->exts->getUrl());
                $this->exts->log("Wrong credential !!!!");
                $this->exts->loginFailure(1);
            }
        }
    } catch (\Exception $exception) {

        $this->exts->log("Exception filling loginform " . $exception->getMessage());
    }
}


/**
    * Method to Check where user is logged in or not
    * return boolean true/false
    */
private function checkLogin()
{
    $this->exts->log("Begin checkLogin ");
    $isLoggedIn = false;
    try {
        $this->exts->waitTillPresent($this->check_login_success_selector, 20);
        if ($this->exts->exists($this->check_login_success_selector)) {

            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");

            $isLoggedIn = true;
        }
    } catch (Exception $exception) {

        $this->exts->log("Exception checking loggedin " . $exception);
    }

    return $isLoggedIn;
}

private function isValidEmail($username)
{

    $emailPattern = '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/';
    if (preg_match($emailPattern, $username)) {
        return 'email';
    }
    return false;
}

private function solve_captcha_by_clicking($count = 1)
{
    $this->exts->log("Checking captcha");
    $language_code = '';
    // sleep(30);
    // $unsolved_hcaptcha_submit_selector = 'div[id="hcaptcha-d"] iframe, iframe[title="reCAPTCHA"]';
    // $hcaptcha_challenger_wraper_selector = 'div[style*="visible"] iframe[src*="hcaptcha"][title*="hallenge"], div[style*="visibility: visible;"] > div > iframe[title*="in zwei Minuten ab"]';

    $this->exts->waitTillPresent('iframe[name="recaptcha"]', 20);
    if ($this->exts->exists('iframe[name="recaptcha"]')) {
        $this->switchToFrame('iframe[name="recaptcha"]');
        sleep(5);
    }
    $this->exts->waitTillAnyPresent(['div[id="hcaptcha-d"] iframe', 'iframe[title="reCAPTCHA"]'], 15);


    // $this->exts->waitTillAnyPresent([$unsolved_hcaptcha_submit_selector, $hcaptcha_challenger_wraper_selector], 20);
    if ($this->exts->exists('div[id="hcaptcha-d"] iframe') || $this->exts->exists('iframe[title="reCAPTCHA"]')) {
        // Check if challenge images hasn't showed yet, Click checkbox to show images challenge
        if (!$this->exts->exists('div[style*="visible"] iframe[src*="hcaptcha"][title*="hallenge"]') && $this->exts->exists('div[id="hcaptcha-d"] iframe')) {
            $this->exts->click_element('div[id="hcaptcha-d"] iframe');
            $this->exts->log(">>>>>>>>>>>>>> hcaptcha");
            $hcaptcha_challenger_wraper_selector = 'div[style*="visible"] iframe[src*="hcaptcha"][title*="hallenge"]';
        } elseif (!$this->exts->exists('div[style*="visibility: visible;"] > div > iframe[title*="in zwei Minuten ab"]') && $this->exts->exists('iframe[title="reCAPTCHA"]')) {
            $this->exts->click_element('iframe[title="reCAPTCHA"]');
            $this->exts->log(">>>>>>>>>>>>>> recaptcha");
            $hcaptcha_challenger_wraper_selector = 'div[style*="visibility: visible;"] > div > iframe[title*="in zwei Minuten ab"]';
        }
        $this->exts->waitTillAnyPresent(['div[style*="visible"] iframe[src*="hcaptcha"][title*="hallenge"]', 'div[style*="visibility: visible;"] > div > iframe[title*="in zwei Minuten ab"]'], 30);
        $this->exts->capture("paypal-captcha");

        $captcha_instruction = '';

        //$captcha_instruction = $this->exts->extract($iframeElement_instartion,null, 'innerText');
        $this->exts->log('language_code: ' . $language_code . ' Instruction: ' . $captcha_instruction);
        sleep(5);
        // $captcha_wraper_selector = $hcaptcha_challenger_wraper_selector;
        $captcha_wraper_selector = 'iframe[name="recaptcha"]';

        $this->exts->switchToDefault();
        sleep(3);
        if ($this->exts->exists($captcha_wraper_selector)) {
            $coordinates = $this->getCoordinates($captcha_wraper_selector, $captcha_instruction, '', $json_result = false);


            // if($coordinates == '' || count($coordinates) < 2){
            //  $coordinates = $this->exts->processClickCaptcha($captcha_wraper_selector, $captcha_instruction, '', $json_result=false);
            // }
            if ($coordinates != '') {
                // $challenge_wraper = $this->exts->querySelector($captcha_wraper_selector);

                foreach ($coordinates as $coordinate) {
                    $this->exts->click_by_xdotool($captcha_wraper_selector, (int) $coordinate['x'], (int) $coordinate['y']);
                }
                $this->switchToFrame('iframe[name="recaptcha"]');
                $this->exts->capture("paypal-captcha-selected " . $count);
                if ($this->exts->exists('div[style*="visible"] iframe[src*="hcaptcha"][title*="hallenge"]')) {
                    $this->exts->makeFrameExecutable('div[style*="visible"] iframe[src*="hcaptcha"][title*="hallenge"]')->click_element('div.button-submit');
                } elseif ($this->exts->exists('div[style*="visibility: visible;"] > div > iframe[title*="in zwei Minuten ab"]')) {
                    $this->exts->makeFrameExecutable('div[style*="visibility: visible;"] > div > iframe[title*="in zwei Minuten ab"]')->click_element('button[id="recaptcha-verify-button"]');
                }
                sleep(10);
                $this->exts->switchToDefault();
                return true;
            }
        }
        $this->exts->switchToDefault();
        return false;
    }
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
                        $coordinates[] = ['x' => (int) $matches[1], 'y' => (int) $matches[2]];
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
private function checkFillRecaptcha($count = 1)
{
    $this->exts->waitTillPresent('iframe[src*="reCaptchaEnterpriseEnabled=true"]', 20);
    $this->switchToFrame('iframe[src*="reCaptchaEnterpriseEnabled=true"]');
    $this->exts->log(__FUNCTION__);
    $recaptcha_iframe_selector = 'iframe[src*="/recaptcha/enterprise"], iframe[src*="/recaptcha/api2/anchor?"]';
    $recaptcha_textarea_selector = 'textarea[name="g-recaptcha-response"]';
    $this->exts->waitTillPresent($recaptcha_iframe_selector);
    sleep(5);
    if ($this->exts->exists($recaptcha_iframe_selector)) {
        $iframeUrl = $this->exts->extract($recaptcha_iframe_selector, null, 'src');
        $data_siteKey = explode('&', end(explode("&k=", $iframeUrl)))[0];
        $this->exts->log("iframe url  - " . $iframeUrl);
        $this->exts->log("SiteKey - " . $data_siteKey);

        $isCaptchaSolved = $this->exts->processRecaptcha($this->exts->getUrl(), $data_siteKey, false);
        $this->exts->log("isCaptchaSolved - " . $isCaptchaSolved);

        if ($isCaptchaSolved) {
            // Step 1 fill answer to textarea
            $this->exts->log(__FUNCTION__ . "::filling reCaptcha response..");
            $recaptcha_textareas = $this->exts->querySelectorAll($recaptcha_textarea_selector);
            for ($i = 0; $i < count($recaptcha_textareas); $i++) {
                $this->exts->execute_javascript("arguments[0].innerHTML = '" . $this->exts->recaptcha_answer . "';", [$recaptcha_textareas[$i]]);
            }
            sleep(2);
            $this->exts->capture('recaptcha-filled');

            $gcallbackFunction = $this->exts->execute_javascript('(function() { 
                if(document.querySelector("[data-callback]") != null){
                    return document.querySelector("[data-callback]").getAttribute("data-callback");
                }

                var result = ""; var found = false;
                function recurse (cur, prop, deep) {
                    if(deep > 5 || found){ return;}console.log(prop);
                    try {
                        if(cur == undefined || cur == null || cur instanceof Element || Object(cur) !== cur || Array.isArray(cur)){ return;}
                        if(prop.indexOf(".callback") > -1){result = prop; found = true; return;
                        } else { deep++;
                            for (var p in cur) { recurse(cur[p], prop ? prop + "." + p : p, deep);}
                        }
                    } catch(ex) { console.log("ERROR in function: " + ex); return; }
                }

                recurse(___grecaptcha_cfg.clients[0], "", 0);
                return found ? "___grecaptcha_cfg.clients[0]." + result : null;
            })();
            ');
            $this->exts->log('Callback function: ' . $gcallbackFunction);
            $this->exts->log('Callback function: ' . $this->exts->recaptcha_answer);
            if ($gcallbackFunction != null) {
                $this->exts->execute_javascript($gcallbackFunction . '("' . $this->exts->recaptcha_answer . '");');
                sleep(10);
            }
        } else {
            // try again if recaptcha expired
            if ($count < 3) {
                $count++;
                $this->checkFillRecaptcha($count);
            }
        }
    } else {
        $this->exts->log(__FUNCTION__ . '::Not found reCaptcha');
    }
    $this->exts->switchToDefault();
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
    for ($i = 0; $i < 6; $i++) {
        $this->exts->type_key_by_xdotool('Tab');
    }
    $this->exts->type_key_by_xdotool('Return');
    sleep(15);
    $this->exts->capture("after-clear");
}

private function checkFillTwoFactor()
{
    $two_factor_selector = 'input.otp-box.otp-input';
    $two_factor_message_selector = 'div.smsChallenge h1, div.smsChallenge p, div[data-nemo="twofactorPage"] p:nth-child(1)';
    $two_factor_submit_selector = 'button#submitBtn:not(:disabled), button#securityCodeSubmit, button[data-nemo="twofactorSubmit"]';

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
            //$this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);

            $resultCodes = str_split($two_factor_code);
            $code_inputs = $this->exts->querySelectorAll($two_factor_selector);
            foreach ($code_inputs as $key => $code_input) {
                if (array_key_exists($key, $resultCodes)) {
                    $this->exts->log('"checkFillTwoFactor: Entering key ' . $resultCodes[$key] . ' to input #');
                    $this->exts->moveToElementAndType('input.otp-box.otp-input:nth-child(' . ($key + 1) . ')', $resultCodes[$key]);
                } else {
                    $this->exts->log('"checkFillTwoFactor: Have no char for input #');
                }
            }

            $this->exts->log("checkFillTwoFactor: Clicking submit button.");
            sleep(3);
            $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

            $this->exts->moveToElementAndClick($two_factor_submit_selector);
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