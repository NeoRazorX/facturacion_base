<?php
/*
 * This file is part of facturacion_base
 * Copyright (C) 2012-2017  Carlos Garcia Gomez  neorazorx@gmail.com
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

require_once 'plugins/facturacion_base/model/core/inventario.php';

/**
 * Esta tabla guardará el calculo de inventario diario de cada artículo
 * para así poder presentar la información de los ultimos 30 días de stock en
 * ventas_articulo y no recargar el view de datos
 * Para informe_articulos servirá para mostrar graficos de resumen por fecha
 * sin tener que estar calculando directamente de las tablas involucradas
 * 
 * @author Joe Nilson <joenilson at gmail.com>
 */
class inventario extends FacturaScripts\model\inventario
{

}
