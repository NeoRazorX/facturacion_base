<?php
/*
 * This file is part of FacturaScripts
 * Copyright (C) 2013-2016  Carlos Garcia Gomez  neorazorx@gmail.com
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 * 
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

require_model('albaran_cliente.php');
require_model('albaran_proveedor.php');
require_model('articulo.php');
require_model('asiento.php');
require_model('factura_cliente.php');
require_model('factura_proveedor.php');
require_once 'plugins/facturacion_base/extras/libromayor.php';
require_once 'plugins/facturacion_base/extras/inventarios_balances.php';

class facturacion_base_cron
{
   public function __construct(&$db)
   {
      $alb_cli = new albaran_cliente();
      echo "Ejecutando tareas para los ".FS_ALBARANES." de cliente...";
      $alb_cli->cron_job();
      
      $alb_pro = new albaran_proveedor();
      echo "\nEjecutando tareas para los ".FS_ALBARANES." de proveedor...";
      $alb_pro->cron_job();
      
      $fac_cli = new factura_cliente();
      echo "\nEjecutando tareas para las facturas de cliente...";
      $fac_cli->cron_job();
      
      $fac_pro = new factura_proveedor();
      echo "\nEjecutando tareas para las facturas de proveedor...";
      $fac_pro->cron_job();
      
      $articulo = new articulo();
      echo "\nEjecutando tareas para los artículos...";
      $articulo->cron_job();
      
      $asiento = new asiento();
      echo "\nEjecutando tareas para los asientos...";
      $asiento->cron_job();
      
      if(FS_LIBROS_CONTABLES)
      {
         $libro = new libro_mayor();
         echo "\nGeneramos el libro mayor para cada subcuenta y el libro diario para cada ejercicio...";
         $libro->cron_job();
         
         $inventarios_balances = new inventarios_balances($db);
         echo "\nGeneramos el libro de inventarios y balances para cada ejercicio...";
         $inventarios_balances->cron_job();
      }
      else
      {
         $libro = new libro_mayor();
         echo "\nComprobamos algunas subcuentas...";
         $libro->cron_job();
      }
   }
}

new facturacion_base_cron($db);