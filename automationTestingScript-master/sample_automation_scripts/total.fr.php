<?php // added password selector and select customer code and updated extract zip code add condition if zipFile name is not empty the nextract the file 

/**
 * Chrome Remote via Chrome devtool protocol script, for specific process/portal
 *
 * @package uwa
 *
 * @copyright   GetMyInvoices
 */

define('KERNEL_ROOT', '/var/www/remote-chrome/utils/');

$gmi_browser_core = realpath('/var/www/remote-chrome/utils/GmiChromeManager.php');
require_once($gmi_browser_core);
class PortalScriptCDP
{

    private $exts;
    public $setupSuccess = false;
    private $chrome_manage;
    private $username;
    private $password;

    public function __construct($mode, $portal_name, $process_uid, $username, $password)
    {
        $this->username = $username;
        $this->password = $password;

        $this->exts = new GmiChromeManager();
        $this->exts->screen_capture_location = '/var/www/remote-chrome/screens/';
        $this->exts->init($mode, $portal_name, $process_uid, $username, $password);
        $this->setupSuccess = true;
    }

    /**
     * Method that called first for executing portal script, this method should not be altered by Users.
     */
    public function run()
    {
        if ($this->setupSuccess) {
            try {
                // Start portal script execution
                $this->initPortal(0);
            } catch (\Exception $exception) {
                $this->exts->log('Exception: ' . $exception->getMessage());
                $this->exts->capture("error");
                var_dump($exception);
            }


            $this->exts->log('Execution completed');

            $this->exts->process_completed();
            $this->exts->dump_session_files();
        } else {
            echo 'Script execution failed.. ' . "\n";
        }
    }

    // Server-Portal-ID: 37597 - Last modified: 24.03.2025 14:41:57 UTC - User: 1

    /*Define constants used in script*/
    public $baseUrl = 'https://gronline.total.fr/secure/clients/factures/recherche.do';
    public $loginUrl = 'https://gronline.total.fr/secure/clients/factures/recherche.do';
    public $invoicePageUrl = 'https://gronline.total.fr/secure/clients/factures/recherche.do';

    public $username_selector = 'input#loginRem';
    public $password_selector = 'input[name="j_password"]';
    public $remember_me_selector = 'input#rem';
    public $submit_login_selector = 'div#okbtn';

    public $check_login_failed_selector = 'SELECTOR_error';
    public $check_login_success_selector = '[href="/Home/Disconnect"], #btn-deconnexion, form[action*="/logout"], li#user-menu-list a.nav-user';

    public $isNoInvoice = true;

    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        $this->exts->openUrl($this->baseUrl);


        // Load cookies
        $this->exts->loadCookiesFromFile();

        $this->exts->openUrl($this->baseUrl);

        $this->exts->capture('1-init-page');

        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        $this->exts->waitTillPresent($this->check_login_success_selector);
        if ($this->exts->querySelector($this->check_login_success_selector) == null) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->openUrl($this->loginUrl);
            $this->checkFillLogin();
            $this->exts->waitTillPresent('select[name*="loginCompteCardpro"]');

            // if ($this->exts->exists('select[name*="loginCompteCardpro"]')) {
            //     $org_values = $this->exts->getElementsAttribute('select[name*="loginCompteCardpro"] option', 'value');
            //     $this->exts->changeSelectbox('select[name*="loginCompteCardpro"]', $org_values[0]);
            //     sleep(12);
            // }

            $this->exts->log("I've waited for a second.");
            $this->exts->capture('1-pre-login-fillform-selectOrg');
            $this->exts->moveToElementAndClick('div[name*="terminerSelectionCompte"]');

            if ($this->exts->exists('form[name="form/transverse/migrergigya"] [name="startProcess"]')) {
                $this->exts->account_not_ready();
            }

            if (
                $this->exts->urlContains('seconnecter/authentification.do') && $this->exts->allExists([
                    'input[name="loginID"]',
                    'div[name="checkLoginId"]'
                ])
            ) {
                $this->exts->moveToElementAndType('input[name="loginID"]', $this->username);
                sleep(1);
                $this->exts->moveToElementAndClick('div[name="checkLoginId"]');
                sleep(10);
                // $this->exts->waitTillPresent("form input#tec", 10);
                // if ($this->exts->exists("form input#tec")) {
                //     $this->exts->click_element("form input#tec");
                // }

                $this->exts->waitTillPresent('input[name="j_password"]', 10);
                if ($this->exts->exists('input[name="j_password"]')) {
                    $this->exts->moveToElementAndType('input[name="j_password"]', $this->password);
                    sleep(2);
                    $this->exts->click_element("div#okbtn");
                    sleep(4);
                }

                if ($this->exts->exists('input[name="password"][placeholder]')) {
                    $this->exts->moveToElementAndType('input[name="password"][placeholder]', $this->password);
                    sleep(2);
                    $this->exts->moveToElementAndClick('input[type="submit"]#passwd-submit');
                    sleep(4);
                }

                $this->exts->waitTillPresent('input#tec');
            }
            sleep(10);
            if ($this->exts->urlContains('fleet.total')) {
                $this->exts->openUrl('https://fleet.total.com/');
                sleep(10);

                $this->checkFillLoginFleet();
                sleep(18);
                $this->exts->moveToElementAndClick('span[onclick="closeAnnouncementPopup()"]');
                sleep(4);
            } else if ($this->exts->urlContains('mobility.total')) {
                $this->exts->moveToElementAndClick('input#tec');
                sleep(10);
                $this->checkFillLoginMobility();
                sleep(18);
                $this->exts->moveToElementAndClick('span[onclick="closeAnnouncementPopup()"]');
                sleep(4);
            }

            // Select Customer
            $this->exts->execute_javascript("let select = document.querySelector('select[name=\"loginCompteCardpro\"]');
                select.selectedIndex = 0;
                select.dispatchEvent(new Event('change'));");

            if ($this->exts->exists('div.btn-blue')) {
                $this->exts->moveToElementAndClick('div.btn-blue');
                sleep(4);
            }
        }

        // then check user logged in or not
        // for ($wait_count = 1; $wait_count <= 10 && $this->exts->querySelector($this->check_login_success_selector) == null; $wait_count++) {
        // 	$this->exts->log('Waiting for login...');
        // 	sleep(5);
        // }
        $this->exts->waitTillPresent($this->check_login_success_selector);
        if ($this->exts->querySelector($this->check_login_success_selector) != null) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");
            $this->exts->waitTillPresent('button#cb_accept_btn');
            if ($this->exts->exists('button#cb_accept_btn')) {
                $this->exts->click_element('button#cb_accept_btn');
                sleep(3);
            }
            // Open invoices url and download invoice
            if ($this->exts->urlContains('granalytics.total.fr')) {
                $this->exts->openUrl('https://granalytics.total.fr/Invoice/Index');
                sleep(15);
                $this->processInvoicesGranalytics();
            }

            // Fleet
            else if ($this->exts->urlContains('fleet.total')) {
                $this->exts->openUrl('https://fleet.total.com/private/guest/invoices');
                sleep(12);

                if ($this->exts->exists('.multiaccount-radio-btn')) {
                    $this->processFleetMultiAccounts();
                } else {
                    $this->processInvoicesFleet();
                }
            }

            // Mobility.total
            else if ($this->exts->urlContains('mobility.total')) {
                $this->exts->openUrl('https://client.mobility.totalenergies.com/group/france/invoices#');
                sleep(12);

                if ($this->exts->exists('.multiaccount-radio-btn')) {
                    $this->processMobilityMultiAccounts();
                } else {
                    $this->processInvoicesMobility();
                }
            } else {
                $this->exts->openUrl('https://gronline.totalenergies.fr/secure/clients/factures/recherche.do');
                sleep(10);
                $this->invoicePageGronline();
            }

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }

            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            $err_msg = trim($this->exts->extract('td.connexionError div.connexionErrorDiv', null, 'innerText'));
            $err_msg1 = strtolower($this->exts->extract('td.connexionError div#connexionErrorDiv ul', null, 'innerText'));
            if ($this->exts->querySelector($this->check_login_failed_selector) != null) {
                $this->exts->loginFailure(1);
            } else if ($err_msg != '') {
                $this->exts->loginFailure(1);
            } else if (strpos($err_msg1, 'euillez vous authentifier')) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    private function checkFillLogin()
    {
        $this->exts->waitTillPresent($this->password_selector);
        if ($this->exts->querySelector($this->password_selector) != null) {
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

    private function checkFillLoginFleet()
    {
        $username_selector = 'form#gigya-login-form input[name="username"]';
        $password_selector = 'form#gigya-login-form input[name="password"]';
        $submit_selector = 'form#gigya-login-form input.gigya-input-submit';

        if ($this->exts->querySelector($password_selector) == null) {
            $this->exts->refresh();
            $this->exts->log('--- Not found selector login ---');
            sleep(20);
        }
        if ($this->exts->querySelector('form#gigya-login-form input[name="password"]') != null) {
            sleep(3);
            $this->exts->capture("2-login-page");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($username_selector, $this->username);
            sleep(1);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($password_selector, $this->password);
            sleep(1);

            if ($this->remember_me_selector != '')
                $this->exts->moveToElementAndClick($this->remember_me_selector);
            sleep(2);

            $this->exts->capture("2.1-login-page-filled");
            $this->exts->moveToElementAndClick($submit_selector);
            sleep(30);
            if (!$this->exts->exists($username_selector) && !$this->exts->exists($password_selector)) {
                $this->exts->refresh();
                $this->exts->log('--- Not found selector login ---');
                sleep(20);
            }
            if ($this->exts->exists($username_selector) && $this->exts->exists($password_selector)) {
                if (empty($this->exts->extract($username_selector, null, 'value')) && empty($this->exts->extract($password_selector, null, 'value'))) {
                    $this->exts->log('------- Page has reload -----');
                    if ($this->exts->exists($username_selector)) {
                        $this->exts->log("Enter Username");
                        $this->exts->moveToElementAndType($username_selector, $this->username);
                        sleep(2);
                    }

                    if ($this->exts->exists($password_selector)) {
                        $this->exts->log("Enter Password");
                        $this->exts->moveToElementAndType($password_selector, $this->password);
                        sleep(5);
                    }
                    $this->checkFillRecaptcha();
                    $this->exts->capture("2.2-login-page-filled-reload");
                }
            }
            $this->exts->moveToElementAndClick($submit_selector);
        } else {
            $this->exts->log(__FUNCTION__ . '::Fleet Login page not found');
            $this->exts->capture("2-fleet-login-page-not-found");
        }
    }

    private function checkFillLoginMobility()
    {
        $username_selector = 'form#gigya-login-form input[name="username"], form#gigya-passwordless-login-form input[name="identifier"]';
        $password_selector = 'form#gigya-login-form input[name="password"], form#gigya-password-auth-method-form input[name="password"]';
        $submit_selector = 'form#gigya-passwordless-login-form input.gigya-input-submit, form#gigya-login-form input.gigya-input-submit, form#gigya-password-auth-method-form input.gigya-input-submit';
        // if ($this->exts->querySelector($password_selector) == null) {
        //     $this->exts->refresh();
        //     $this->exts->log('--- Not found selector login ---');
        //     sleep(20);
        // }
        if ($this->exts->querySelector($password_selector) != null || $this->exts->querySelector($username_selector) != null) {
            sleep(3);
            $this->exts->capture("2-login-page");

            $this->exts->log("Enter Username");
            // $this->exts->moveToElementAndType($username_selector, $this->username);
            $this->exts->moveToElementAndType($username_selector, $this->username);
            sleep(3);

            if ($this->exts->exists($submit_selector)) {
                $this->exts->moveToElementAndClick($submit_selector);
            }

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($password_selector, $this->password);
            sleep(3);
            if (empty($this->exts->extract($password_selector, null, 'value'))) {
                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($password_selector, $this->password);
            }

            if ($this->remember_me_selector != '')
                $this->exts->moveToElementAndClick($this->remember_me_selector);
            sleep(2);

            $this->exts->capture("2.1-login-page-filled");
            $this->exts->moveToElementAndClick($submit_selector);
            sleep(30);
            if (!$this->exts->exists($username_selector) && !$this->exts->exists($password_selector)) {
                $this->exts->refresh();
                $this->exts->log('--- Not found selector login ---');
                sleep(20);
            }
            if ($this->exts->exists($username_selector) && $this->exts->exists($password_selector)) {
                if (empty($this->exts->extract($username_selector, null, 'value')) && empty($this->exts->extract($password_selector, null, 'value'))) {
                    $this->exts->log('------- Page has reload -----');
                    if ($this->exts->exists($username_selector)) {
                        $this->exts->log("Enter Username");
                        $this->exts->moveToElementAndType($username_selector, $this->username);
                        sleep(2);
                    }

                    if ($this->exts->exists($password_selector)) {
                        $this->exts->log("Enter Password");
                        $this->exts->moveToElementAndType($password_selector, $this->password);
                        sleep(5);
                    }
                    $this->checkFillRecaptcha();
                    $this->exts->capture("2.2-login-page-filled-reload");
                }
            }
            $this->exts->moveToElementAndClick($submit_selector);
        } else {
            $this->exts->log(__FUNCTION__ . '::Fleet Login page not found');
            $this->exts->capture("2-fleet-login-page-not-found");
        }
    }

    private function processInvoicesGranalytics()
    {
        sleep(25);
        // for ($wait_count = 1; $wait_count <= 10 && $this->exts->querySelector('table > tbody > tr a[href*="/downloadinvoice') == null; $wait_count++) {
        // 	$this->exts->log('Waiting for invoice...');
        // 	sleep(5);
        // }
        $this->exts->capture("4-invoices-page-granalytics");
        $invoices = [];

        $rows = $this->exts->querySelectorAll('#tableInvoices > tbody > tr');
        foreach ($rows as $row) {
            $tags = $this->exts->querySelectorAll('td', $row);
            if (count($tags) >= 9 && $this->exts->querySelector('a[href*="/downloadinvoice"]', $tags[4]) != null) {
                $invoiceUrl = $this->exts->querySelector('a[href*="/downloadinvoice"]', $tags[4])->getAttribute("href");
                $invoiceName = trim($tags[2]->getAttribute('innerText'));
                $invoiceDate = trim($tags[1]->getAttribute('innerText'));
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[6]->getAttribute('innerText'))) . ' EUR';
                $clientCode = trim(explode('-', $tags[0]->getAttribute('innerText'))[0]);

                array_push($invoices, array(
                    'invoiceName' => $invoiceName,
                    'invoiceDate' => $invoiceDate,
                    'invoiceAmount' => $invoiceAmount,
                    'invoiceUrl' => $invoiceUrl,
                    'clientCode' => $clientCode
                ));
                $this->isNoInvoice = false;
            }
        }

        // Because the casperjs user post method, so can't convert when not have account comfortable
        // Download all invoices
        $this->exts->log('Invoices found: ' . count($invoices));
        // foreach ($invoices as $invoice) {
        // 	$this->exts->log('--------------------------');
        // 	$this->exts->log('invoiceName: '.$invoice['invoiceName']);
        // 	$this->exts->log('invoiceDate: '.$invoice['invoiceDate']);
        // 	$this->exts->log('invoiceAmount: '.$invoice['invoiceAmount']);
        // 	$this->exts->log('invoiceUrl: '.$invoice['invoiceUrl']);

        // 	$invoiceFileName = $invoice['invoiceName'].'.pdf';
        // 	$invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd.m.Y','Y-m-d');
        // 	$this->exts->log('Date parsed: '.$invoice['invoiceDate']);

        // 	$downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
        // 	if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
        // 		$this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
        // 		sleep(1);
        // 	} else {
        // 		$this->exts->log(__FUNCTION__.'::No download '.$invoiceFileName);
        // 	}
        // }
    }

    private function invoicePageGronline()
    {
        $this->exts->log('start invoice page Gronline');

        if ($this->exts->exists('div#errors'))
            $this->exts->no_permission();

        if ($this->exts->exists('select[name="listeNumeroClient"]')) {
            $this->exts->click_by_xdotool('select[name="listeNumeroClient"]');
            sleep(2);
            $this->exts->click_by_xdotool('select[name="listeNumeroClient"] option[value="TOUS"]');
            // $this->exts->changeSelectbox('select[name="listeNumeroClient"]', 'TOUS');
            sleep(2);
        } elseif ($this->exts->exists('select[name="statutPli"]')) {
            $this->exts->click_by_xdotool('select[name="statutPli"]');
            sleep(2);
            $this->exts->click_by_xdotool('select[name="statutPli"] option[value="1"]');
            // $this->exts->changeSelectbox('select[name="listeNumeroClient"]', 'TOUS');
            sleep(2);
        }


        $this->exts->moveToElementAndType('input[name="dateDebutPeriodeFacturation"]', Date('d/m/Y', strtotime("-90 days")));
        sleep(4);
        $this->exts->moveToElementAndType('input[name="dateFinPeriodeFacturation"]', Date('d/m/Y', strtotime("0 days")));
        sleep(3);

        $this->exts->moveToElementAndClick('div[name="rechercher"]');
        sleep(14);

        $this->processInvoicesGronline();
    }

    private function processInvoicesGronline($paging_count = 1)
    {
        $this->exts->capture("4-invoices-page-gronline");
        $invoices = [];

        $rows = $this->exts->querySelectorAll('table.simple-result  > tbody > tr');
        foreach ($rows as $row) {
            $tags = $this->exts->querySelectorAll('td', $row);
            if (count($tags) >= 8 && $this->exts->querySelector('a[href*="resultat.do?method=traite"]', $row) != null) {
                $invoiceUrl = $this->exts->querySelector('a[href*="resultat.do?method=traite"]', $row)->getAttribute("href");
                $invoiceName = trim($this->exts->extract('a[href*="resultat.do?method=traite"]', $row, 'innerText'));
                $invoiceDate = trim($tags[1]->getAttribute('innerText'));
                $invoiceAmount = '';

                array_push($invoices, array(
                    'invoiceName' => $invoiceName,
                    'invoiceDate' => $invoiceDate,
                    'invoiceAmount' => $invoiceAmount,
                    'invoiceUrl' => $invoiceUrl
                ));
                $this->isNoInvoice = false;
            }
        }

        // Because the casperjs user post method, so can't convert when not have account comfortable
        // Download all invoices
        $this->exts->log('Invoices found: ' . count($invoices));
        foreach ($invoices as $invoice) {
            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
            $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
            $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
            $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

            $invoiceFileName = $invoice['invoiceName'] . '.pdf';
            $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd/m/Y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

            if ($this->exts->document_exists($invoiceFileName)) {
                continue;
            }
            $this->exts->openNewTab($invoice['invoiceUrl']);
            sleep(5);

            $this->exts->moveToElementAndClick('div#telecharger');
            $this->exts->wait_and_check_download('zip');

            $downloaded_file = $this->exts->find_saved_file('zip', $invoiceFileName);
            sleep(1);

            $this->exts->log($downloaded_file);
            $this->exts->log('start------------------------------');
            sleep(15);
            $pdf_files = $this->extract_zip_save_pdf($downloaded_file);
            $this->exts->log('final extract --------------------');

            foreach ($pdf_files as $pdf_file) {
                // $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                // sleep(1);
                // $this->isNoInvoice = false;
                $invoiceFileName = str_replace('.zip', '.pdf', $invoiceFileName);
                $invoiceName = explode('.pdf', $invoiceFileName)[0];
                $this->exts->log($invoiceName);
                @rename($pdf_file, $this->exts->config_array['download_folder'] . $invoiceFileName);
                if ($this->exts->invoice_exists($invoiceName)) {
                    $this->exts->log('Invoice existed ' . $invoiceFileName);
                } else {
                    $this->exts->new_invoice($invoiceName, $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                    sleep(1);
                }
            }
            $this->exts->switchToInitTab();
            sleep(2);
            $this->exts->closeAllTabsButThis();
        }
        $this->exts->switchToDefault();
        $this->exts->capture('after-download-1-page');

        $nextpage_sel = '.pagination-right a[href*="-p=' . ($paging_count + 1) . '"]';
        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int) @$this->exts->config_array["restrictPages"] : 3;
        if (
            $restrictPages == 0 &&
            $paging_count < 50 &&
            $this->exts->querySelector($nextpage_sel) != null
        ) {
            $paging_count++;
            $this->exts->moveToElementAndClick($nextpage_sel);
            sleep(5);
            $this->processInvoicesGronline($paging_count);
        }
    }

    // Fleet
    private function checkFillRecaptcha()
    {
        $this->exts->log(__FUNCTION__);
        $recaptcha_iframe_selector = 'iframe[src*="/recaptcha/api2/anchor?"]';
        $recaptcha_textarea_selector = 'textarea[name="g-recaptcha-response"]';
        $this->exts->capture("2.0-before-fill-recaptcha");
        if ($this->exts->exists($recaptcha_iframe_selector)) {
            $iframeUrl = $this->exts->extract($recaptcha_iframe_selector, null, 'src');
            if ($iframeUrl == '') {
                $this->exts->capture("1-after-extract-recaptcha-url");
                return;
            }
            $data_siteKey = explode('&', end(explode("&k=", $iframeUrl)))[0];
            $this->exts->log("iframe url  - " . $iframeUrl);
            $this->exts->log("SiteKey - " . $data_siteKey);

            $isCaptchaSolved = $this->exts->processRecaptcha($this->exts->getUrl(), $data_siteKey, false);
            $this->exts->log("isCaptchaSolved - " . $isCaptchaSolved);

            if ($isCaptchaSolved) {
                // Step 1 fill answer to textarea
                $this->exts->log(__FUNCTION__ . "::filling reCaptcha response..");
                $recaptcha_textareas = $this->exts->getElements($recaptcha_textarea_selector);
                for ($i = 0; $i < count($recaptcha_textareas); $i++) {
                    $this->exts->executeSafeScript("arguments[0].innerHTML = '" . $this->exts->recaptcha_answer . "';", [$recaptcha_textareas[$i]]);
                }
                sleep(2);
                $this->exts->capture('2.0-recaptcha-filled');

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
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Not found reCaptcha');
        }
    }
    private function processFleetMultiAccounts()
    {
        $this->exts->log('processFleetMultiAccounts');

        $acc_numbers = $this->exts->getElementsAttribute('input[name="selectedUserId"]', 'value');
        $this->exts->log('number of fleet acc number: ' . count($acc_numbers));
        if (count($acc_numbers) > 0) {
            foreach ($acc_numbers as $key => $acc_number) {
                $this->exts->log($acc_number);
                $this->exts->openUrl('https://fleet.total.com/private/guest/select-account');
                sleep(13);

                $acc_sel = 'input[value="' . $acc_number . '"]';
                $this->exts->moveToElementAndClick($acc_sel);
                sleep(2);

                $this->exts->moveToElementAndClick('form[name="selectCardProAccountForm"] [type="submit"]');
                sleep(15);

                $this->exts->openUrl('https://fleet.total.com/private/guest/invoices');
                sleep(14);

                $this->processInvoicesFleet();
            }
        } else {
            $this->processInvoicesFleet();
        }
    }
    private function processInvoicesFleet()
    {
        $this->exts->capture('processInvoicesFleet-1');
        $this->exts->moveToElementAndClick('#invoiceFilterDIV-tableActivefilters a[data-bind*="removeFilter"]');
        sleep(15);
        $this->exts->capture('processInvoicesFleet-2');

        $this->exts->capture("4-invoices-page-Fleet");
        $invoices = [];

        $rows = $this->exts->querySelectorAll('table > tbody > tr');
        foreach ($rows as $row) {
            $tags = $this->exts->querySelectorAll('td', $row);
            if (count($tags) >= 9 && $this->exts->querySelector('a[href*="invoke"], a[href*="TelechargerFacture"]', $row) != null) {
                $invoiceUrl = $this->exts->querySelector('a[href*="invoke"], a[href*="TelechargerFacture"]', $row)->getAttribute("href");
                $invoiceName = trim($tags[2]->getAttribute('innerText'));
                $invoiceDate = trim($tags[1]->getAttribute('innerText'));
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[6]->getAttribute('innerText'))) . ' EUR';

                array_push($invoices, array(
                    'invoiceName' => $invoiceName,
                    'invoiceDate' => $invoiceDate,
                    'invoiceAmount' => $invoiceAmount,
                    'invoiceUrl' => $invoiceUrl
                ));
                $this->isNoInvoice = false;
            }
        }

        // Because the casperjs user post method, so can't convert when not have account comfortable
        // Download all invoices
        $this->exts->log('Invoices found: ' . count($invoices));
        foreach ($invoices as $invoice) {
            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
            $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
            $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
            $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

            $invoiceFileName = $invoice['invoiceName'] . '.zip';
            $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd/m/Y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

            $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'zip', $invoiceFileName);
            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                sleep(1);
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
        }
    }

    // Mobility
    private function processMobilityMultiAccounts()
    {
        $this->exts->log('processFleetMultiAccounts');
        $acc_numbers = $this->exts->getElementsAttribute('input[name="selectedUserId"]', 'value');
        $this->exts->log('number of fleet acc number: ' . count($acc_numbers));
        if (count($acc_numbers) > 0) {
            foreach ($acc_numbers as $key => $acc_number) {
                $this->exts->log($acc_number);
                $this->exts->openUrl('https://client.mobility.com/private/guest/select-account');
                sleep(13);

                $acc_sel = 'input[value="' . $acc_number . '"]';
                $this->exts->moveToElementAndClick($acc_sel);
                sleep(2);

                $this->exts->moveToElementAndClick('form[name="selectCardProAccountForm"] [type="submit"]');
                sleep(15);

                $this->exts->openUrl('https://client.mobility.total/group/france/invoices');
                sleep(14);

                $this->processInvoicesMobility();
            }
        } else {
            $this->processInvoicesMobility();
        }
    }
    private function processInvoicesMobility($paging_count = 1)
    {
        $this->exts->capture('processInvoicesMobility-1');
        $this->exts->moveToElementAndClick('#invoiceFilterDIV-tableActivefilters a[data-bind*="removeFilter"]');
        sleep(15);
        $this->exts->capture('processInvoicesMobility-2');

        $this->exts->capture("4-invoices-page-Mobility");
        $invoices = [];

        $rows = $this->exts->querySelectorAll('table#invoiceListTable tbody tr');
        foreach ($rows as $row) {
            $tags = $this->exts->querySelectorAll('td', $row);
            if (count($tags) >= 12 && $this->exts->querySelector('a[onclick*=downloadTotalPDF], a[doc-url*="download"]', $tags[10]) != null) {
                $download_button = $this->exts->querySelector('a[onclick*=downloadTotalPDF], a[doc-url*="download"]', $tags[10]);
                $invoiceName = trim($tags[4]->getAttribute('innerText'));
                $invoiceDate = trim($tags[3]->getAttribute('innerText'));
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[7]->getAttribute('innerText'))) . ' EUR';

                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                $invoiceFileName = $invoiceName . '.zip';
                $invoiceDate = $this->exts->parse_date($invoiceDate, 'm/d/Y', 'Y-m-d');
                $this->exts->log('Date parsed: ' . $invoiceDate);
                // array_push($invoices, array(
                // 	'invoiceName'=>$invoiceName,
                // 	'invoiceDate'=>$invoiceDate,
                // 	'invoiceAmount'=>$invoiceAmount,
                // 	'invoiceUrl'=>$invoiceUrl
                // ));

                if ($this->exts->invoice_exists($invoiceName)) {
                    $this->exts->log('Invoice existed ' . $invoiceName);
                } else {
                    try {
                        $this->exts->log('Click download button');
                        $download_button->click();
                    } catch (\Exception $exception) {
                        $this->exts->log('Click download button by javascript');
                        $this->exts->executeSafeScript("arguments[0].click()", [$download_button]);
                    }
                    sleep(5);
                    $this->exts->wait_and_check_download('zip');
                    $downloaded_file = $this->exts->find_saved_file('zip', $invoiceFileName);
                    $this->exts->log("Download file: " . $downloaded_file);
                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $this->extract_zip_save_pdf($downloaded_file);
                        sleep(1);
                    } else {
                        $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                    }
                }
            } else if (count($tags) >= 9 && $this->exts->querySelector('a[onclick*=downloadTotalPDF], a[doc-url*="download"]', $tags[8]) != null) {
                $download_button = $this->exts->querySelector('a[onclick*=downloadTotalPDF], a[doc-url*="download"]', $tags[8]);
                $invoiceName = trim($tags[2]->getAttribute('innerText'));
                $invoiceDate = trim($tags[1]->getAttribute('innerText'));
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[7]->getAttribute('innerText'))) . ' EUR';
                $invoiceFileName = $invoiceName . '.zip';
                $invoiceDate = $this->exts->parse_date($invoiceDate, 'm/d/Y', 'Y-m-d');
                $this->exts->log('Date parsed: ' . $invoiceDate);
                // array_push($invoices, array(
                // 	'invoiceName'=>$invoiceName,
                // 	'invoiceDate'=>$invoiceDate,
                // 	'invoiceAmount'=>$invoiceAmount,
                // 	'invoiceUrl'=>$invoiceUrl
                // ));

                if ($this->exts->invoice_exists($invoiceName)) {
                    $this->exts->log('Invoice existed ' . $invoiceFileName);
                } else {
                    try {
                        $this->exts->log('Click download button');
                        $download_button->click();
                    } catch (\Exception $exception) {
                        $this->exts->log('Click download button by javascript');
                        $this->exts->executeSafeScript("arguments[0].click()", [$download_button]);
                    }
                    sleep(5);
                    $this->exts->wait_and_check_download('zip');
                    $downloaded_file = $this->exts->find_saved_file('zip', $invoiceFileName);
                    $downloaded_file = '';
                    $this->exts->log("Download file: " . $downloaded_file);
                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $this->extract_zip_save_pdf($downloaded_file);
                        sleep(1);
                    } else {
                        $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                    }
                }
            }
        }

        // Download all invoices
        // $this->exts->log('Invoices found: '.count($invoices));
        // foreach ($invoices as $invoice) {
        // 	$this->exts->log('--------------------------');
        // 	$this->exts->log('invoiceName: '.$invoice['invoiceName']);
        // 	$this->exts->log('invoiceDate: '.$invoice['invoiceDate']);
        // 	$this->exts->log('invoiceAmount: '.$invoice['invoiceAmount']);
        // 	$this->exts->log('invoiceUrl: '.$invoice['invoiceUrl']);

        // 	$invoiceFileName = $invoice['invoiceName'].'.zip';
        // 	$invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'm/d/Y','Y-m-d');
        // 	$this->exts->log('Date parsed: '.$invoice['invoiceDate']);


        // 	$downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'zip', $invoiceFileName);
        // 	if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
        // 		$this->extract_zip_save_pdf($downloaded_file);
        // 		sleep(1);
        // 	} else {
        // 		$this->exts->log(__FUNCTION__.'::No download '.$invoiceFileName);
        // 	}
        // }

        $str = "var div = document.querySelector('div.cc_container--open'); if (div != null) {  div.style.display = \"none\"; }";
        $this->exts->executeSafeScript($str);
        sleep(2);

        $str = "var div = document.querySelector('div.dydu-chatbox'); if (div != null) {  div.style.display = \"none\"; }";
        $this->exts->executeSafeScript($str);
        sleep(2);

        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int) @$this->exts->config_array["restrictPages"] : 3;
        if (
            $restrictPages == 0 &&
            $paging_count < 50 &&
            $this->exts->querySelector('a.paginate_button.current + a') != null
        ) {
            $paging_count++;
            $this->exts->moveToElementAndClick('a.paginate_button.current + a');
            sleep(5);
            $this->processInvoicesMobility($paging_count);
        }
    }

    private function extract_zip_save_pdf($zipfile)
    {
        $this->isNoInvoice = false;
        $zip = new \ZipArchive;
        if ($zipfile != '' || $zipfile != null) {
            $res = $zip->open($zipfile);
            if ($res === TRUE) {
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $zipPdfFile = $zip->statIndex($i);
                    $fileInfo = pathinfo($zipPdfFile['name']);
                    if ($fileInfo['extension'] === 'pdf') {
                        $invoice_name = basename($zipPdfFile['name'], '.pdf');
                        $zip->extractTo($this->exts->config_array['download_folder'], array(basename($zipPdfFile['name'])));
                        $saved_file = $this->exts->config_array['download_folder'] . basename($zipPdfFile['name']);
                        $this->exts->new_invoice($invoice_name, "", "", $saved_file);
                        sleep(1);
                    }
                    if ($fileInfo['extension'] === 'zip') {
                        $invoice_name = basename($zipPdfFile['name'], '.zip');
                        $zip->extractTo($this->exts->config_array['download_folder'], array(basename($zipPdfFile['name'])));
                        $saved_file = $this->exts->config_array['download_folder'] . basename($zipPdfFile['name']);
                        $this->extract_zip_save_pdf($saved_file);
                    }
                }
                $zip->close();
                unlink($zipfile);
            } else {
                $this->exts->log(__FUNCTION__ . '::File extraction failed');
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::File extraction failed');
        }
    }

    public $files_copied = [];
    public $folders_need_rm = [];
    function copyPdfFileToDownloadFolder($folders = [])
    {
        $this->exts->log('copyPdfFileToDownloadFolder');
        $folder_array = [];
        foreach ($folders as $filename) {
            $this->exts->log('filename: ' . $filename);

            if ($filename == $this->exts->config_array['download_folder']) {
                $files = glob($filename . "*");
            } else {
                $files = glob($filename . "/*");
            }

            foreach ($files as $file_n) {
                $this->exts->log('file_n: ' . $file_n);
                if (strpos($file_n, '.pdf') !== false && !in_array($file_n, $this->files_copied)) {
                    if (!copy($file_n, $this->exts->config_array['download_folder'] . end(explode('/', $file_n)))) {
                        $this->exts->log("not copy $file_n");
                    } else {
                        array_push($this->files_copied, $this->exts->config_array['download_folder'] . end(explode('/', $file_n)));
                        unlink($file_n);
                    }
                } else if (is_dir($file_n)) {
                    array_push($folder_array, $file_n);
                } else {
                    continue;
                }
            }

            if ($filename != $this->exts->config_array['download_folder']) {
                array_push($this->folders_need_rm, $filename);
            }
        }
        return $folder_array;
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
