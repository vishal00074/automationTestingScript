public $baseUrl = 'https://www.groupon.de/merchant/center/login';
public $loginUrl = 'https://www.groupon.de/merchant/center/login';
public $invoicePageUrl = 'https://www.groupon.de/merchant/center/vat';

public $username_selector = 'form#loginForm input#emailInput';
public $password_selector = 'form#loginForm input#passwordInput';
public $remember_me_selector = 'form#loginForm input[name="stayloggedin"] + span.checkbox';
public $submit_login_btn = 'form#loginForm button.submitButton';

public $checkLoginFailedSelector = 'input[aria-invalid="true"],span.mx-alert-error';
public $checkLoggedinSelector = 'div[data-bhw="SignOut"],a[href="/merchant/center/vat"]';
public $files_copied = [];
public $folders_need_rm = [];
public $start_date = '';

/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal' . $count);

    $this->start_date = isset($this->exts->config_array["start_date"]) ? trim($this->exts->config_array["start_date"]) : $this->start_date;

    $this->exts->openUrl($this->baseUrl);
    sleep(1);
    $this->exts->capture("Home-page-without-cookie");

    // Load cookies
    $this->exts->loadCookiesFromFile();
    sleep(1);
    $this->exts->openUrl($this->baseUrl);
    if ($this->exts->exists('div[class="mx-modal-container"] button[data-bhw="RRDPAnnouncementModalRemindMeLater"]')) {
        $this->exts->moveToElementAndClick('div[class="mx-modal-container"] button[data-bhw="RRDPAnnouncementModalRemindMeLater"]');
        sleep(3);
    }
    // after load cookies and open base url, check if user logged in

    // Wait for selector that make sure user logged in
    sleep(10);

    if ($this->exts->exists('div.mx-modal-header span')) {
        $this->exts->moveToElementAndClick('div.mx-modal-header span');
    }
    sleep(5);
    if ($this->exts->exists('mc-modal-next > div > div > div > span')) {
        $this->exts->moveToElementAndClick('mc-modal-next > div > div > div > span');
    }

    sleep(10);
    $this->exts->waitTillPresent($this->checkLoggedinSelector, 20);
    if ($this->exts->getElement($this->checkLoggedinSelector) != null) {
        // If user has logged in via cookies, call waitForLogin
        $this->exts->log('Logged in from initPortal');
        $this->exts->capture('0-init-portal-loggedin');
        $this->waitForLogin();
    } else {
        // If user hase not logged in, open the login url and wait for login form
        $this->exts->log('NOT logged in from initPortal');
        $this->exts->capture('0-init-portal-not-loggedin');
        $this->exts->clearCookies();
        $this->exts->executeSafeScript('
    localStorage.clear();
    sessionStorage.clear();
');
        sleep(5);
        $this->exts->openUrl($this->baseUrl);
        sleep(10);
        if (strpos($this->exts->extract('.main-wrapper h1 + p'), 'Entweder wird die Webseite aktualisiert oder jemand hat seinen') !== false) {
            sleep(2);
            $this->exts->openUrl($this->baseUrl);
            sleep(10);
        }

        $this->exts->waitTillPresent($this->username_selector, 20);

        // Check if the error message contains the specified substring
        if ($this->exts->getElement($this->username_selector) != null ||  !$this->exts->exists($this->checkLoginFailedSelector)) {
            // Retry opening the URL
            $this->exts->openUrl($this->baseUrl);
            sleep(30); // Wait for the page to load

            // Attempt to fill the login form
            $this->waitForLoginPage();
            sleep(30); // Wait for the login process to complete
        }

        if ($this->exts->getElement($this->username_selector) != null) {
            $this->checkFillLoginUndetected();
        }
    }
    sleep(15);
    $this->waitForLogin();
}

private function waitForLoginPage($count = 1)
{
    sleep(5);
    if ($this->exts->getElement($this->password_selector) != null) {
        $this->exts->capture("1-pre-login");

        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(2);

        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(2);

        if ($this->remember_me_selector != '')
            $this->exts->moveToElementAndClick($this->remember_me_selector);
        sleep(2);

        $this->exts->capture("1-filled-login");
        $this->exts->moveToElementAndClick($this->submit_login_btn);
        sleep(10);
        $is_captcha = $this->solve_captcha_by_clicking(0);
        if ($is_captcha) {
            for ($i = 1; $i < 10; $i++) {
                if ($is_captcha == false) {
                    break;
                }
                $is_captcha = $this->solve_captcha_by_clicking($i);
            }
        }
    } else {
        if ($count < 5) {
            $count = $count + 1;
            $this->waitForLoginPage($count);
        } else {
            $this->exts->log('Timeout waitForLoginPage');
            $this->exts->capture("LoginFailed");
            $this->exts->loginFailure();
        }
    }
}


private function waitForLogin($count = 1)
{
    sleep(5);
    if ($this->exts->exists('div.mc-tour-container span.mx-modal-close')) {
        $this->exts->moveToElementAndClick('div.mc-tour-container span.mx-modal-close');
        sleep(3);
    }

    if ($this->exts->exists('div[class="mx-modal-container"] button[data-bhw="RRDPAnnouncementModalRemindMeLater"]')) {
        $this->exts->click_by_xdotool('div[class="mx-modal-container"] button[data-bhw="RRDPAnnouncementModalRemindMeLater"]');
        sleep(3);
    }
    if ($this->exts->exists($this->checkLoggedinSelector)) {
        sleep(3);
        $this->exts->log('User logged in.');
        $this->exts->capture("2-post-login");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        if ($count < 5) {
            $count = $count + 1;
            $this->waitForLogin($count);
        } else {
            $this->exts->log('Timeout waitForLogin');
            $this->exts->capture("LoginFailed");

            if (
                strpos(strtolower($this->exts->extract($this->checkLoginFailedSelector, null, 'innerText')), 'passwor') !== false ||
                $this->exts->exists($this->checkLoginFailedSelector)
            ) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }
}

private function checkFillLoginUndetected($count = 0)
{
    $this->exts->log(__FUNCTION__);
    $this->exts->log('Fill Count - ' . $count);
    $this->exts->type_key_by_xdotool("Ctrl+l");
    sleep(1);
    $this->exts->type_text_by_xdotool($this->baseUrl);
    sleep(1);
    $this->exts->type_key_by_xdotool("Return");
    sleep(20);
    $this->exts->click_by_xdotool($this->username_selector);
    sleep(2);
    $this->exts->type_text_by_xdotool($this->username);
    sleep(2);
    $this->exts->type_key_by_xdotool("Tab");
    sleep(2);
    $this->exts->type_text_by_xdotool($this->password);
    sleep(2);
    $this->exts->type_key_by_xdotool("Tab");
    sleep(2);
    $this->exts->click_by_xdotool($this->remember_me_selector);
    sleep(2);
    $this->exts->type_key_by_xdotool("Tab");
    sleep(2);
    $this->exts->log('Login-filled');
    $this->exts->capture('login-filled-' . $count);
    $this->exts->type_key_by_xdotool("Return");
    sleep(10);
}

private function solve_captcha_by_clicking($count = 1)
{
    $this->exts->log("Checking captcha");
    $this->waitFor('iframe[title="recaptcha challenge expires in two minutes"]', 10);
    $language_code = '';
    if ($this->exts->exists('iframe[title="recaptcha challenge expires in two minutes"]')) {
        $this->exts->capture("brevo-captcha");

        $captcha_instruction = $this->exts->makeFrameExecutable('iframe[title="recaptcha challenge expires in two minutes"]')->extract('.rc-imageselect-desc-no-canonical');
        if (trim($captcha_instruction) == '') {
            $captcha_instruction = $this->exts->makeFrameExecutable('iframe[title="recaptcha challenge expires in two minutes"]')->extract('.rc-imageselect-desc');
        }

        //$captcha_instruction = $this->exts->extract($iframeElement_instartion,null, 'innerText');
        $this->exts->log('language_code: ' . $language_code . ' Instruction: ' . $captcha_instruction);
        sleep(5);
        $captcha_wraper_selector = 'iframe[title="recaptcha challenge expires in two minutes"]';

        if ($this->exts->exists($captcha_wraper_selector)) {
            $coordinates = $this->getCoordinates($captcha_wraper_selector, $captcha_instruction, '', $json_result = false);


            // if($coordinates == '' || count($coordinates) < 2){
            //  $coordinates = $this->exts->processClickCaptcha($captcha_wraper_selector, $captcha_instruction, '', $json_result=false);
            // }
            if ($coordinates != '') {
                // $challenge_wraper = $this->exts->querySelector($captcha_wraper_selector);

                foreach ($coordinates as $coordinate) {
                    $this->click_recaptcha_point($captcha_wraper_selector, (int)$coordinate['x'], (int)$coordinate['y']);
                }

                $this->exts->capture("brevo-captcha-selected " . $count);
                $this->exts->makeFrameExecutable('iframe[title="recaptcha challenge expires in two minutes"]')->click_element('button[id="recaptcha-verify-button"]');
                sleep(10);
                return true;
            }
        }

        return false;
    }
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