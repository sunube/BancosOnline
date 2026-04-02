<?php

namespace FacturaScripts\Plugins\BancosOnline\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\BancosOnline\Lib\EnableBankingAPI;
use FacturaScripts\Plugins\BancosOnline\Model\BancoOnlineConfig;
use FacturaScripts\Plugins\BancosOnline\Model\BancoOnlineCuenta;
use FacturaScripts\Plugins\BancosOnline\Model\BancoOnlineMovimiento;
use FacturaScripts\Plugins\BancosOnline\Model\BancoOnlineBanco;
use FacturaScripts\Plugins\BancosOnline\Model\BancoOnlineAuth;

class BancosOnline extends Controller
{
    /** @var array */
    public $cuentas = [];

    /** @var float */
    public $saldoTotal = 0;

    /** @var array */
    public $movimientosRecientes = [];

    /** @var array */
    public $totalesMensuales = [];

    /** @var array */
    public $flujoDiario = [];

    /** @var array */
    public $saldosPorEmpresa = [];

    /** @var array */
    public $saldosPorBanco = [];

    /** @var array */
    public $bancosDisponibles = [];

    /** @var array */
    public $empresas = [];

    /** @var bool */
    public $configured = false;

    /** @var int|null */
    public $idempresa;

    /** @var string */
    public $lastSync = '';

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['name'] = 'BancosOnline';
        $data['title'] = 'bancos-online';
        $data['menu'] = 'accounting';
        $data['icon'] = 'fa-solid fa-university';
        $data['showonmenu'] = true;
        $data['ordernum'] = 55;
        return $data;
    }

    public function privateCore(&$response, $user, $permissions): void
    {
        parent::privateCore($response, $user, $permissions);

        $config = BancoOnlineConfig::current();
        $this->configured = $config->isConfigured();
        $this->idempresa = $this->request->query->get('idempresa');
        $this->lastSync = $config->last_sync ?? '';

        // Cargar empresas para el filtro
        $this->loadEmpresas();

        if (!$this->configured) {
            return;
        }

        // Acciones POST
        $action = $this->request->request->get('action', $this->request->query->get('action', ''));

        switch ($action) {
            case 'sync':
                $this->syncAll($config);
                break;

            case 'connect':
                $this->connectBank($config);
                return;

            case 'delete-account':
                $this->deleteAccount();
                break;

            case 'set-company':
                $this->setAccountCompany();
                break;
        }

        // Cargar datos del dashboard
        $this->loadDashboard();
    }

    public function getTemplate(): string
    {
        return 'BancosOnline.html.twig';
    }

    private function loadEmpresas(): void
    {
        $db = new \FacturaScripts\Core\Base\DataBase();
        $this->empresas = $db->select("SELECT idempresa, nombrecorto FROM empresas ORDER BY nombrecorto");
    }

    private function loadDashboard(): void
    {
        $db = new \FacturaScripts\Core\Base\DataBase();

        // Verificar que las tablas existen antes de consultar
        if (!$db->tableExists('bancos_online_cuentas')) {
            Tools::log()->warning('Las tablas del plugin no se han creado. Desactiva y vuelve a activar el plugin.');
            return;
        }

        $idempresa = $this->idempresa ? (int) $this->idempresa : null;

        // Cuentas con detalles
        $this->cuentas = BancoOnlineCuenta::allWithDetails($idempresa);

        // Saldo total
        $this->saldoTotal = BancoOnlineCuenta::totalBalance($idempresa);

        if (!$db->tableExists('bancos_online_movimientos')) {
            return;
        }

        // Movimientos recientes
        $filters = [];
        if ($idempresa) {
            $filters['idempresa'] = $idempresa;
        }
        $this->movimientosRecientes = BancoOnlineMovimiento::search($filters, 15);

        // Totales mensuales (6 meses)
        $this->totalesMensuales = BancoOnlineMovimiento::monthlyTotals(6, $idempresa);

        // Flujo diario (30 dias)
        $this->flujoDiario = BancoOnlineMovimiento::dailyFlow(30, $idempresa);

        // Saldos agrupados
        $this->calcSaldosAgrupados();
    }

    private function calcSaldosAgrupados(): void
    {
        $porEmpresa = [];
        $porBanco = [];

        foreach ($this->cuentas as $c) {
            // Por empresa
            $eName = $c['empresa_nombre'] ?? 'Sin asignar';
            if (!isset($porEmpresa[$eName])) {
                $porEmpresa[$eName] = ['nombre' => $eName, 'saldo' => 0, 'cuentas' => 0];
            }
            $porEmpresa[$eName]['saldo'] += (float) ($c['saldo'] ?? 0);
            $porEmpresa[$eName]['cuentas']++;

            // Por banco
            $bName = $c['banco'] ?? 'Desconocido';
            if (!isset($porBanco[$bName])) {
                $porBanco[$bName] = [
                    'nombre' => $bName,
                    'saldo' => 0,
                    'cuentas' => 0,
                    'color' => $c['banco_color'] ?? '#6366f1',
                    'logo' => $c['banco_logo'] ?? '',
                ];
            }
            $porBanco[$bName]['saldo'] += (float) ($c['saldo'] ?? 0);
            $porBanco[$bName]['cuentas']++;
        }

        $this->saldosPorEmpresa = array_values($porEmpresa);
        $this->saldosPorBanco = array_values($porBanco);
    }

    // ─── Acciones ────────────────────────────────────────────────

    private function syncAll(BancoOnlineConfig $config): void
    {
        try {
            $api = EnableBankingAPI::fromConfig($config);

            $cuenta = new BancoOnlineCuenta();
            $cuentas = $cuenta->all([], [], 0, 0);
            $synced = 0;
            $errors = 0;

            foreach ($cuentas as $c) {
                // Saldos
                try {
                    $balances = $api->getBalances($c->account_uid);
                    foreach ($balances['balances'] ?? [] as $bal) {
                        $amount = (float) ($bal['balance_amount']['amount'] ?? 0);
                        $currency = $bal['balance_amount']['currency'] ?? 'EUR';
                        $c->updateBalance($amount, $currency);
                    }
                } catch (\Exception $e) {
                    $msg = $e->getMessage();
                    if (strpos($msg, '429') !== false || strpos($msg, 'RATE_LIMIT') !== false) {
                        Tools::log()->warning($c->banco . ': Limite de accesos PSD2 superado. Espera unas horas.');
                    } else {
                        Tools::log()->warning($c->banco . ' (saldos): ' . $msg);
                    }
                    $errors++;
                    continue;
                }

                // Movimientos
                try {
                    $dateFrom = $c->last_sync
                        ? date('Y-m-d', strtotime($c->last_sync . ' -3 days'))
                        : date('Y-m-d', strtotime('-' . ($config->dias_historico ?: 90) . ' days'));
                    $dateTo = date('Y-m-d');

                    $txs = $api->getAllTransactions($c->account_uid, $dateFrom, $dateTo);

                    foreach ($txs as $tx) {
                        $parsed = EnableBankingAPI::parseTransaction($tx);
                        BancoOnlineMovimiento::upsert($c->id, $parsed);
                    }

                    $synced++;
                } catch (\Exception $e) {
                    $msg = $e->getMessage();
                    if (strpos($msg, '429') !== false || strpos($msg, 'RATE_LIMIT') !== false) {
                        Tools::log()->warning($c->banco . ': Limite de accesos PSD2 superado.');
                    } else {
                        Tools::log()->warning($c->banco . ' (movimientos): ' . $msg);
                    }
                    $errors++;
                }
            }

            // Actualizar fecha de ultima sincronizacion
            $config->last_sync = date('Y-m-d H:i:s');
            $config->save();

            Tools::log()->notice('Sincronizacion completada: ' . $synced . ' cuenta(s) actualizadas, ' . $errors . ' error(es).');
        } catch (\Exception $e) {
            Tools::log()->error('Error de sincronizacion: ' . $e->getMessage());
        }
    }

    private function connectBank(BancoOnlineConfig $config): void
    {
        $bankName = $this->request->request->get('bank_name', '');
        $country = $this->request->request->get('country', 'ES');
        $psuType = $this->request->request->get('psu_type', 'business');
        $companyId = $this->request->request->get('idempresa');

        if (empty($bankName)) {
            Tools::log()->warning('Debes seleccionar un banco.');
            return;
        }

        try {
            $api = EnableBankingAPI::fromConfig($config);

            $state = bin2hex(random_bytes(20));
            $result = $api->startAuth($bankName, $country, $psuType, $state);

            // Guardar estado pendiente
            $auth = new BancoOnlineAuth();
            $auth->state = $state;
            $auth->authorization_id = $result['authorization_id'] ?? '';
            $auth->banco = $bankName;
            $auth->idempresa = $companyId ? (int) $companyId : null;
            $auth->save();

            // Redirigir al banco
            $authUrl = $result['url'] ?? '';
            if (!empty($authUrl)) {
                header('Location: ' . $authUrl);
                exit;
            }

            Tools::log()->error('No se recibio URL de autorizacion del banco.');
        } catch (\Exception $e) {
            Tools::log()->error('Error al conectar banco: ' . $e->getMessage());
        }
    }

    private function deleteAccount(): void
    {
        $accountId = (int) $this->request->request->get('idcuenta', 0);

        if ($accountId > 0) {
            $cuenta = new BancoOnlineCuenta();
            if ($cuenta->loadFromCode($accountId)) {
                $nombre = $cuenta->banco . ' - ' . $cuenta->iban;
                $cuenta->delete();
                Tools::log()->notice('Cuenta eliminada: ' . $nombre);
            }
        }
    }

    private function setAccountCompany(): void
    {
        $accountId = (int) $this->request->request->get('idcuenta', 0);
        $companyId = $this->request->request->get('idempresa');

        if ($accountId > 0) {
            $cuenta = new BancoOnlineCuenta();
            if ($cuenta->loadFromCode($accountId)) {
                $cuenta->idempresa = $companyId ? (int) $companyId : null;
                $cuenta->save();
                Tools::log()->notice('Empresa actualizada para: ' . $cuenta->iban);
            }
        }
    }
}
