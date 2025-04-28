public $baseUrl = 'https://www.remove.bg/dashboard';
public $loginUrl = 'https://www.remove.bg/dashboard';
public $invoiceUrl = 'https://www.remove.bg/profile#payment-billing';

public $username_selector = '#user_email';
public $password_selector = '#user_password';
public $submit_btn = 'div.actions button';
public $check_login_success_selector = 'a[href="#payment-billing"], a[href*="users/sign_out"], a[href*="/profile"], a[id*="userProfile"], ul > div > div > button > div';
public $noInvoice = true;
public $restrictPages = 3;


public $isNoInvoice = true;


/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);

    $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;

    $isCookieLoginSuccess = false;
    if ($this->exts->loadCookiesFromFile()) {
        sleep(1);
        $this->exts->openUrl($this->baseUrl);
        sleep(10);
        $this->exts->capture("Home-page-with-cookie");
        if ($this->checkLogin()) {
            $isCookieLoginSuccess = true;
        }
    }

    if (!$isCookieLoginSuccess) {


        $this->exts->log("initPortal:: could not click on login link, try opening login URL");
        $this->exts->openUrl($this->baseUrl);
        $this->waitFor('a#sso-sign-in-btn');
        if ($this->exts->exists('a#sso-sign-in-btn')) {
            $this->exts->moveToElementAndClick('a#sso-sign-in-btn');
        } else {
            $this->exts->openUrl($this->loginUrl);
        }
        $this->fillForm(0);
        $this->checkFillRecaptcha(0);
        sleep(5);
        $this->check_solve_blocked_page();
        //redirected you too many times.
        if ($this->exts->exists('#reload-button')) {
            $this->exts->moveToElementAndClick('#reload-button');
            sleep(10);
        }
        if ($this->exts->exists('#reload-button')) {
            $this->exts->moveToElementAndClick('#reload-button');
            sleep(10);
        }

        $this->exts->capture("after-login");
        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");

            if (!empty($this->exts->config_array['allow_login_success_request'])) {
                $this->exts->triggerLoginSuccess();
            }

            $this->exts->success();
        } else {
            $this->exts->log(">>>>>>>>>>>>>> after-login check failed!!!!");
            if (strpos($this->exts->extract('div.alert-danger'), 'Email oder Passwort ung') !== false || strpos($this->exts->extract('div.alert-danger'), 'Invalid Email or password') !== false) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->capture("LoginFailed");
                $this->exts->loginFailure();
            }
        }
    } else {
        $this->exts->log(">>>>>>>>>>>>>>>Login successful with cookie!!!!");
        $this->exts->capture("LoginSuccess");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    }
}


/**
    * Method to fill login form
    * @param Integer $count Number of times portal is retried.
    */
function fillForm($count)
{

    $this->waitFor($this->username_selector);

    if ($this->exts->exists('div.banner button.btn-success')) {
        $this->exts->moveToElementAndClick('div.banner button.btn-success');
        sleep(1);
    }
    $this->exts->log("Begin fillForm " . $count);
    $this->exts->capture("pre-fill-login");
    try {
        $this->waitFor($this->username_selector);

        if ($this->exts->querySelector($this->username_selector) != null) {
            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(1);
        }

        if ($this->exts->querySelector($this->password_selector) != null) {
            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
        }
        sleep(5);
        $this->exts->capture("post-fill-login");

        $this->exts->moveToElementAndClick($this->submit_btn);

        sleep(10);
    } catch (\Exception $exception) {
        $this->exts->log("Exception filling loginform " . $exception->getMessage());
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
        for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $this->check_login_success_selector . "');") != 1; $wait++) {
            $this->exts->log('Waiting for login.....');
            sleep(10);
        }

        $isLoginForm = $this->exts->querySelector($this->username_selector);
        if (!$isLoginForm) {
            if ($this->exts->querySelector($this->check_login_success_selector) != null) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful 1!!!!");
                $isLoggedIn = true;
            }
        }
    } catch (\Exception $exception) {
        $this->exts->log("Exception checking loggedin " . $exception);
    }

    return $isLoggedIn;
}
private function check_solve_blocked_page()
{
    $this->exts->capture("blocked-page-checking");
    // Check and solve blocked with hcaptcha
    $hcaptcha_displayed = $this->process_hcaptcha_by_clicking();
    if ($hcaptcha_displayed) {
        $hcaptcha_displayed = $this->process_hcaptcha_by_clicking();
    }

    // if Step above failed, try again
    if ($hcaptcha_displayed) {
        $hcaptcha_displayed = $this->process_hcaptcha_by_clicking();
        $hcaptcha_displayed = $this->process_hcaptcha_by_clicking();
    }
}
private function process_hcaptcha_by_clicking()
{
    $hcaptcha_challenger_wraper_selector = 'div:not([aria-hidden="true"]) > div > iframe[src*="/hcaptcha-challenge.html"]';
    $this->waitFor($hcaptcha_challenger_wraper_selector, 10);
    if ($this->exts->exists($hcaptcha_challenger_wraper_selector)) {
        $this->exts->capture("hcaptcha");

        $this->switchToFrame($hcaptcha_challenger_wraper_selector);
        // Change language to English
        $this->exts->click_element('#language-selector');
        sleep(1);
        $this->exts->click_element('//*[@role="option"]//*[text()="English"]');
        sleep(3);
        $captcha_instruction = $this->exts->extract('.challenge-header .prompt-text');
        $this->exts->log('language_code: ' . $language_code . ' Instruction: ' . $captcha_instruction);

        $this->exts->switchToDefault();
        $coordinates = $this->processClickCaptcha($hcaptcha_challenger_wraper_selector, $captcha_instruction, '', $json_result = true); // use $language_code and $captcha_instruction if they changed captcha content
        if ($coordinates == '') {
            $coordinates = $this->processClickCaptcha($hcaptcha_challenger_wraper_selector, $captcha_instruction, '', $json_result = true);
        }
        if ($coordinates != '') {
            $challenge_wraper = $hcaptcha_challenger_wraper_selector;
            if ($challenge_wraper != null) {
                foreach ($coordinates as $coordinate) {
                    $this->click_recaptcha_point($challenge_wraper, (int)$coordinate['x'], (int)$coordinate['y']);
                }
                $this->exts->capture("After captcha clicked.");
            }
            sleep(1);
            $this->switchToFrame($hcaptcha_challenger_wraper_selector);
            $this->exts->click_element('.button-submit');
            sleep(3);
            $this->exts->switchToDefault();
        }
        $this->exts->switchToDefault();
        return true;
    } else {
        $hcaptcha_challenger_wraper_selector = 'iframe[src*="/hcaptcha.html#frame=challenge"]';
        if ($this->exts->exists($hcaptcha_challenger_wraper_selector)) {
            $this->exts->capture("hcaptcha");

            $this->switchToFrame($hcaptcha_challenger_wraper_selector);
            // Change language to English
            $this->exts->click_element('div.display-language');
            sleep(1);
            $this->exts->click_element('//*[@role="option"]//*[text()="English"]');
            sleep(3);
            $captcha_instruction = $this->exts->extract('.challenge-header .prompt-text');
            $this->exts->log('language_code: ' . $language_code . ' Instruction: ' . $captcha_instruction);

            $this->exts->switchToDefault();
            $coordinates = $this->processClickCaptcha($hcaptcha_challenger_wraper_selector, $captcha_instruction, '', $json_result = true, 80); // use $language_code and $captcha_instruction if they changed captcha content
            if ($coordinates == '') {
                $coordinates = $this->processClickCaptcha($hcaptcha_challenger_wraper_selector, $captcha_instruction, '', $json_result = true, 80);
            }
            if ($coordinates != '') {
                $challenge_wraper = $hcaptcha_challenger_wraper_selector;
                if ($challenge_wraper != null) {
                    foreach ($coordinates as $coordinate) {
                        $this->click_recaptcha_point($challenge_wraper, (int)$coordinate['x'], (int)$coordinate['y']);
                    }
                    $this->exts->capture("After captcha clicked.");
                }
                sleep(1);
                $this->switchToFrame($hcaptcha_challenger_wraper_selector);
                $this->exts->click_element('.button-submit');
                sleep(3);
                $this->exts->switchToDefault();
            }
            $this->exts->switchToDefault();
            return true;
        }
    }
    $this->exts->switchToDefault();
    return false;
}
private function click_recaptcha_point($selector = '', $x_on_element = 0, $y_on_element = 0)
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
private function processClickCaptcha(
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
    $this->exts->log(__FUNCTION__);
    $recaptcha_iframe_selector = 'iframe[src*="/recaptcha/api2/anchor?"]';
    $recaptcha_textarea_selector = 'textarea[name="g-recaptcha-response"]';
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
            $recaptcha_textareas =  $this->exts->getElements($recaptcha_textarea_selector);
            for ($i = 0; $i < count($recaptcha_textareas); $i++) {
                $this->exts->executeSafeScript("arguments[0].innerHTML = '" . $this->exts->recaptcha_answer . "';", [$recaptcha_textareas[$i]]);
            }
            sleep(2);
            $this->exts->capture('recaptcha-filled');

            // Step 2, check if callback function need executed
            $gcallbackFunction = $this->exts->executeSafeScript('
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
    ');
            $this->exts->log('Callback function: ' . $gcallbackFunction);
            if ($gcallbackFunction != null) {
                $this->exts->executeSafeScript($gcallbackFunction . '("' . $this->exts->recaptcha_answer . '");');
                sleep(10);
            }
        } else {
            if ($count < 4) {
                $count++;
                $this->checkFillRecaptcha($count);
            }
        }
    } else {
        $this->exts->log(__FUNCTION__ . '::Not found reCaptcha');
    }
}

public function waitFor($selector, $seconds = 10)
{
    for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
        $this->exts->log('Waiting for Selectors.....');
        sleep($seconds);
    }
}