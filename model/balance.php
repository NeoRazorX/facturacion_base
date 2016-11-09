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

require_once 'plugins/facturacion_base/model/core/balance.php';

/**
 * Define que cuentas hay que usar para generar los distintos informes contables.
 * 
 * @author Carlos García Gómez <neorazorx@gmail.com>
 */
class balance extends FacturaScripts\model\balance
{
   
}


/**
 * Detalle de un balance.
 * 
 * @author Carlos García Gómez <neorazorx@gmail.com>
 */
class balance_cuenta extends FacturaScripts\model\balance_cuenta
{
   
}


/**
 * Detalle abreviado de un balance.
 * 
 * @author Carlos García Gómez <neorazorx@gmail.com>
 */
class balance_cuenta_a extends FacturaScripts\model\balance_cuenta_a
{
   
}
