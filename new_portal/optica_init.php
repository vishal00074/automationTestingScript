public $baseUrl = 'https://www.optica.de/app-meinoptica#portalApi';
public $loginUrl = 'https://www.optica.de/meinoptica-login';
public $invoicePageUrl = 'https://www.optica.de/app-meinoptica#portalApi/rezepte';

public $username_selector = 'input[id="username"]';
public $password_selector = 'input[id="password"]';
public $remember_me_selector = '';
public $submit_login_selector = 'button[id="kc-login"]';

public $check_login_failed_selector = 'div.alert-error span';
public $check_login_success_selector = 'a[class*="topmenu__signout"]';

public $isNoInvoice = true;

/**
* Entry Method thats called for a portal
* @param Integer $count Number of times portal is retried.
*/
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    $this->exts->openUrl($this->baseUrl);
    sleep(2);
    $this->exts->waitTillPresent('button#CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll');

    if($this->exts->exists('button#CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll')){
        $this->exts->moveToElementAndClick('button#CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll');
        sleep(7);
    }

    $this->exts->loadCookiesFromFile();

    sleep(10);
   
    if (!$this->checkLogin()) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();
        $this->exts->openUrl($this->loginUrl);
        sleep(10);
        // Accecpt cookies
        if($this->exts->exists('button#CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll')){
            $this->exts->moveToElementAndClick('button#CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll');
            sleep(7);
        }
        $this->fillForm(0);

    }
    if ($this->checkLogin()) {
        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
        $this->exts->capture("LoginSuccess");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
 
            $this->exts->triggerLoginSuccess();
        }

    } else {
        if (stripos($this->exts->extract($this->check_login_failed_selector), 'Ungültiger Benutzername oder Passwort.') !== false) {
            $this->exts->log("Wrong credential !!!!");
            $this->exts->loginFailure(1);
        } else if (stripos($this->exts->extract($this->check_login_failed_selector), 'Die Aktion ist nicht mehr gÃ¼ltig. Bitte fahren Sie nun mit der Anmeldung fort.') !== false) {
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
    
    $this->exts->waitTillPresent('iframe[src*="kuop.optica.de"]');

    $this->switchToFrame('iframe[src*="kuop.optica.de"]');

    $this->exts->waitTillPresent($this->username_selector);
    try {
        if ($this->exts->querySelector($this->username_selector) != null) {

            $this->exts->capture("1-pre-login");
            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(2);
            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(2);

            if ($this->exts->exists($this->remember_me_selector)) {
                $this->exts->click_by_xdotool($this->remember_me_selector);
                sleep(2);
            }

            $this->exts->capture("1-login-page-filled");
           
            if ($this->exts->exists($this->submit_login_selector)) {
                $this->exts->moveToElementAndClick($this->submit_login_selector);
                sleep(10);
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
function checkLogin()
{
    $this->exts->log("Begin checkLogin ");
    $isLoggedIn = false;
    try {
        $this->exts->waitTillPresent($this->check_login_success_selector);
        if ($this->exts->exists($this->check_login_success_selector)) {

            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");

            $isLoggedIn = true;
        }
    } catch (Exception $exception) {

        $this->exts->log("Exception checking loggedin " . $exception);
    }
    return $isLoggedIn;
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