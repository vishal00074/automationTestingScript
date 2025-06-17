public $baseUrl = 'https://kundencenter.optadata-gruppe.de';
public $username_selector = 'input[name="username"]';
public $password_selector = 'input[name="password"]';
public $submit_login_selector = '#submitButton, #submit-button';
public $check_login_success_selector = '[id$="_abmelden"], div[data-test="logout"]';

public $accounting_documents = 0;
public $restrictPages = 3;
public $isNoInvoice = true;
/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)$this->exts->config_array["restrictPages"] : 3;
    $this->accounting_documents = isset($this->exts->config_array["accounting_documents"]) ? (int)$this->exts->config_array["accounting_documents"] : 0;
    // $this->accounting_documents = 1;
    // Load cookies
    $this->exts->loadCookiesFromFile();
    sleep(1);
    $this->exts->openUrl($this->baseUrl);
    sleep(10);
    $this->exts->capture('1-init-page');

    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if ($this->exts->getElement($this->check_login_success_selector) == null) {
        $this->exts->log('NOT logged via cookie');
        // $this->exts->clearCookies();
        $this->exts->openUrl($this->baseUrl);
        sleep(10);
        $this->checkFillLogin();

        $this->waitFor('div[id = "errorMessageDiv"]');

        if ($this->exts->exists('div[id = "errorMessageDiv"]') || $this->exts->exists('table[class="login_table"]')) {
            $this->exts->openUrl('https://login-one.de/');
            $this->exts->log('new Login Portal');
            sleep(10);
            $this->checkFillLogin();
            $this->waitFor($this->check_login_success_selector);
        }
    }

    // then check user logged in or not
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
        $this->exts->log(__FUNCTION__ . '::Last URL: ' . $this->exts->getUrl());

        $this->exts->log(__FUNCTION__ . 'Login failed Status' . $this->exts->extract('div.alert-danger'));

        $error_text = strtolower($this->exts->extract('div#errorMessageDiv'));

        $this->exts->log(__FUNCTION__ . 'error_text' .  $error_text);

        if ($this->exts->exists('div.alert-danger')) {
            $errorMessage = $this->exts->extract('div.alert-danger');

            switch ($errorMessage) {
                case 'Die Aktion ist nicht mehr gültig. Bitte fahren Sie nun mit der Anmeldung fort.':
                    $this->exts->account_not_ready();
                    break;
                case 'Ungültiger Benutzername oder Passwort.':
                    $this->exts->loginFailure(1);
                    break;
                default:
                    $this->exts->loginFailure();
                    break;
            }
        } elseif (stripos($error_text, strtolower('Sie konnten sich leider nicht erfolgreich anmelden, bitte überprüfen Sie Ihre E-Mail-Adresse oder Ihren Benutzernamen')) !== false) {
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}
private function checkFillLogin()
{
    $this->waitFor($this->password_selector);
    if ($this->exts->getElement($this->password_selector) != null) {
        sleep(3);
        $this->exts->moveToElementAndClick('img[src="/img/header.png"]');
        sleep(4);
        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(2);
        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(3);
        $this->exts->capture("2-login-page-filled");
        $this->exts->moveToElementAndClick($this->submit_login_selector);
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
// commented due to processInvoices and  processAccountingDocuments is similar code