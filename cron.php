<?php
/*
 * This file is part of facturacion_base
 * Copyright (C) 2013-2018 Carlos Garcia Gomez <neorazorx@gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 * 
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

require_once 'plugins/facturacion_base/extras/libromayor.php';
require_once 'plugins/facturacion_base/extras/inventarios_balances.php';

class facturacion_base_cron
{

    public function __construct(&$db)
    {
        echo "Ejecutando tareas para los " . FS_ALBARANES . " de venta...";
        $alb_cli = new albaran_cliente();
        $alb_cli->cron_job();

        echo "\nEjecutando tareas para los " . FS_ALBARANES . " de compra...";
        $alb_pro = new albaran_proveedor();
        $alb_pro->cron_job();

        echo "\nEjecutando tareas para las facturas de venta...";
        $fac_cli = new factura_cliente();
        $fac_cli->cron_job();

        echo "\nEjecutando tareas para las facturas de compra...";
        $fac_pro = new factura_proveedor();
        $fac_pro->cron_job();

        echo "\nEjecutando tareas para los articulos...";
        $articulo = new articulo();
        $articulo->cron_job();

        echo "\nEjecutando tareas para los asientos...";
        $asiento = new asiento();
        $asiento->cron_job();

        echo "\nEjecutando tareas para los clientes...";
        $cliente = new cliente();
        $cliente->fix_db();

        echo "\nEjecutando tareas para los proveedores...";
        $proveedor = new proveedor();
        $proveedor->fix_db();

        if (FS_LIBROS_CONTABLES) {
            echo "\nGeneramos el libro mayor para cada subcuenta y el libro diario para cada ejercicio...";
            $libro = new libro_mayor();
            $libro->cron_job();

            echo "\nGeneramos el libro de inventarios y balances para cada ejercicio...";
            $inventarios_balances = new inventarios_balances($db);
            $inventarios_balances->cron_job();
        } else {
            echo "\nComprobamos algunas subcuentas...";
            $libro = new libro_mayor();
            $libro->cron_job();
        }
    }
}

new facturacion_base_cron($db);
