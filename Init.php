<?php

namespace FacturaScripts\Plugins\BancosOnline;

use FacturaScripts\Core\Template\InitClass;

final class Init extends InitClass
{
    public function init(): void
    {
        // No se cargan extensiones en la v1
    }

    public function update(): void
    {
        // Forzar creacion de tablas instanciando cada modelo
        // Esto asegura que las tablas se crean si no existen
        $models = [
            new \FacturaScripts\Plugins\BancosOnline\Model\BancoOnlineConfig(),
            new \FacturaScripts\Plugins\BancosOnline\Model\BancoOnlineCuenta(),
            new \FacturaScripts\Plugins\BancosOnline\Model\BancoOnlineMovimiento(),
            new \FacturaScripts\Plugins\BancosOnline\Model\BancoOnlineBanco(),
            new \FacturaScripts\Plugins\BancosOnline\Model\BancoOnlineAuth(),
        ];

        foreach ($models as $model) {
            // all() con limit 0 fuerza la verificacion/creacion de la tabla
            $model->all([], [], 0, 0);
        }
    }

    public function uninstall(): void
    {
    }
}
