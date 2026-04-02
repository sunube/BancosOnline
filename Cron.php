<?php

namespace FacturaScripts\Plugins\BancosOnline;

use FacturaScripts\Core\Template\CronClass;
use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\BancosOnline\Lib\EnableBankingAPI;
use FacturaScripts\Plugins\BancosOnline\Model\BancoOnlineConfig;
use FacturaScripts\Plugins\BancosOnline\Model\BancoOnlineCuenta;
use FacturaScripts\Plugins\BancosOnline\Model\BancoOnlineMovimiento;

class Cron extends CronClass
{
    public function run(): void
    {
        $config = new BancoOnlineConfig();
        $configs = $config->all([], [], 0, 0);

        foreach ($configs as $cfg) {
            if (!$cfg->activo || !$cfg->isConfigured()) {
                continue;
            }

            $frecuencia = $cfg->sync_frecuencia ?: '4 hours';
            $jobName = 'bancos-online-sync-' . ($cfg->idempresa ?? 'global');

            $this->job($jobName)->every($frecuencia)->run(function () use ($cfg) {
                $this->syncAccounts($cfg);
            });
        }
    }

    private function syncAccounts(BancoOnlineConfig $config): void
    {
        try {
            $api = EnableBankingAPI::fromConfig($config);

            $cuenta = new BancoOnlineCuenta();
            $cuentas = $cuenta->all([], [], 0, 0);
            $synced = 0;

            foreach ($cuentas as $c) {
                // Si la config tiene idempresa, solo sincronizar cuentas de esa empresa
                if ($config->idempresa && $c->idempresa && $c->idempresa !== $config->idempresa) {
                    continue;
                }

                // Saldos
                try {
                    $balances = $api->getBalances($c->account_uid);
                    foreach ($balances['balances'] ?? [] as $bal) {
                        $amount = (float) ($bal['balance_amount']['amount'] ?? 0);
                        $currency = $bal['balance_amount']['currency'] ?? 'EUR';
                        $c->updateBalance($amount, $currency);
                    }
                } catch (\Exception $e) {
                    Tools::log('BancosOnline')->warning($c->banco . ' (saldos): ' . $e->getMessage());
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
                    Tools::log('BancosOnline')->warning($c->banco . ' (movimientos): ' . $e->getMessage());
                }
            }

            // Actualizar fecha
            $config->last_sync = date('Y-m-d H:i:s');
            $config->save();

            if ($synced > 0) {
                Tools::log('BancosOnline')->notice('Cron: ' . $synced . ' cuenta(s) sincronizadas.');
            }
        } catch (\Exception $e) {
            Tools::log('BancosOnline')->error('Cron sync error: ' . $e->getMessage());
        }
    }
}
