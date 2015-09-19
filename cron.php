<?php
/*
 * This file is part of FacturaSctipts
 * Copyright (C) 2013-2015  Carlos Garcia Gomez  neorazorx@gmail.com
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 * 
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

require_model('albaran_cliente.php');
require_model('albaran_proveedor.php');
require_model('articulo.php');
require_model('asiento.php');
require_once 'plugins/facturacion_base/extras/libromayor.php';
require_once 'plugins/facturacion_base/extras/inventarios_balances.php';

class facturacion_base_cron
{
   public function __construct(&$db)
   {
      $alb_cli = new albaran_cliente();
      echo "Ejecutando tareas para los ".FS_ALBARANES." de cliente...\n";
      $alb_cli->cron_job();
      
      $alb_pro = new albaran_proveedor();
      echo "Ejecutando tareas para los ".FS_ALBARANES." de proveedor...\n";
      $alb_pro->cron_job();
      
      $articulo = new articulo();
      echo "Ejecutando tareas para los artículos...";
      $articulo->cron_job();
      
      $asiento = new asiento();
      echo "\nEjecutando tareas para los asientos...\n";
      $asiento->cron_job();
      
      if(FS_LIBROS_CONTABLES)
      {
         $libro = new libro_mayor();
         echo "Generamos el libro mayor para cada subcuenta y el libro diario para cada ejercicio...";
         $libro->cron_job();
         
         $inventarios_balances = new inventarios_balances();
         echo "\nGeneramos el libro de inventarios y balances para cada ejercicio...";
         $inventarios_balances->cron_job();
      }
      else
      {
         $libro = new libro_mayor();
         echo "Comprobamos algunas subcuentas...";
         $libro->cron_job();
      }
   }
}

new facturacion_base_cron($db);