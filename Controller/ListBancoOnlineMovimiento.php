<?php

namespace FacturaScripts\Plugins\BancosOnline\Controller;

use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Lib\ExtendedController\ListController;

class ListBancoOnlineMovimiento extends ListController
{
    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['name'] = 'ListBancoOnlineMovimiento';
        $data['title'] = 'bancos-online-movimientos';
        $data['menu'] = 'accounting';
        $data['icon'] = 'fa-solid fa-exchange-alt';
        $data['showonmenu'] = true;
        $data['ordernum'] = 56;
        return $data;
    }

    protected function createViews(): void
    {
        $this->addView('ListBancoOnlineMovimiento', 'BancoOnlineMovimiento', 'movimientos-bancarios', 'fa-solid fa-exchange-alt');
        $this->addSearchFields('ListBancoOnlineMovimiento', ['descripcion', 'contraparte']);
        $this->addOrderBy('ListBancoOnlineMovimiento', ['fecha', 'id'], 'fecha', 2);
        $this->addOrderBy('ListBancoOnlineMovimiento', ['importe'], 'importe');

        // Filtro de periodo
        $this->addFilterPeriod('ListBancoOnlineMovimiento', 'fecha', 'periodo', 'fecha');

        // Filtro de cuenta: construir array de valores
        $cuentas = $this->getCuentasFilter();
        if (!empty($cuentas)) {
            $this->addFilterSelect('ListBancoOnlineMovimiento', 'idcuenta', 'cuenta', 'idcuenta', $cuentas);
        }
    }

    protected function loadData($viewName, $view): void
    {
        switch ($viewName) {
            case 'ListBancoOnlineMovimiento':
                $view->loadData();
                break;
        }
    }

    private function getCuentasFilter(): array
    {
        $result = [];
        $db = new DataBase();

        if (!$db->tableExists('bancos_online_cuentas')) {
            return $result;
        }

        $rows = $db->select("SELECT id, iban, banco FROM bancos_online_cuentas ORDER BY banco, iban");
        foreach ($rows as $row) {
            $result[] = [
                'code' => $row['id'],
                'description' => $row['banco'] . ' - ' . $row['iban'],
            ];
        }

        return $result;
    }
}
