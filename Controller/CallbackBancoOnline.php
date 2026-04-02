<?php

namespace FacturaScripts\Plugins\BancosOnline\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\BancosOnline\Lib\EnableBankingAPI;
use FacturaScripts\Plugins\BancosOnline\Model\BancoOnlineAuth;
use FacturaScripts\Plugins\BancosOnline\Model\BancoOnlineBanco;
use FacturaScripts\Plugins\BancosOnline\Model\BancoOnlineConfig;
use FacturaScripts\Plugins\BancosOnline\Model\BancoOnlineCuenta;

class CallbackBancoOnline extends Controller
{
    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['name'] = 'CallbackBancoOnline';
        $data['title'] = 'bancos-online-callback';
        $data['showonmenu'] = false;
        return $data;
    }

    public function privateCore(&$response, $user, $permissions): void
    {
        parent::privateCore($response, $user, $permissions);

        $code = $this->request->query->get('code', '');
        $state = $this->request->query->get('state', '');

        if (empty($code) || empty($state)) {
            Tools::log()->warning('Callback bancario: parametros invalidos.');
            $this->redirect('BancosOnline');
            return;
        }

        // Buscar la autorizacion pendiente
        $auth = BancoOnlineAuth::findByState($state);
        if (null === $auth) {
            Tools::log()->warning('Callback bancario: estado desconocido.');
            $this->redirect('BancosOnline');
            return;
        }

        $config = BancoOnlineConfig::current();
        if (!$config->isConfigured()) {
            Tools::log()->error('Enable Banking no esta configurado.');
            $this->redirect('BancosOnline');
            return;
        }

        try {
            $api = new EnableBankingAPI(
                $config->enablebanking_app_id,
                $config->enablebanking_key_path,
                $config->enablebanking_redirect_url
            );

            // Crear sesion con el code del banco
            $sessionData = $api->createSession($code);
            $sessionId = $sessionData['session_id'] ?? '';

            // Guardar las cuentas que devuelve
            $accountsCount = 0;
            foreach ($sessionData['accounts'] ?? [] as $acc) {
                BancoOnlineCuenta::upsert([
                    'account_uid' => $acc['uid'] ?? '',
                    'banco' => $auth->banco,
                    'iban' => $acc['account_id']['iban'] ?? '',
                    'moneda' => $acc['currency'] ?? 'EUR',
                    'nombre' => $acc['name'] ?? '',
                    'tipo_cuenta' => $acc['cash_account_type'] ?? '',
                    'session_id' => $sessionId,
                    'identification_hash' => $acc['identification_hash'] ?? '',
                    'idempresa' => $auth->idempresa,
                ]);
                $accountsCount++;
            }

            // Guardar settings del banco (logo, etc.)
            BancoOnlineBanco::upsert($auth->banco);

            // Eliminar la autorizacion pendiente
            $auth->delete();

            Tools::log()->notice('Banco conectado: ' . $auth->banco . ' (' . $accountsCount . ' cuenta(s))');
        } catch (\Exception $e) {
            Tools::log()->error('Error al procesar callback: ' . $e->getMessage());
        }

        $this->redirect('BancosOnline?connected=true&bank=' . urlencode($auth->banco));
    }

    public function getTemplate(): string
    {
        return 'BancosOnline.html.twig';
    }
}
