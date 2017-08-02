<?php
/*
 * This file is part of facturacion_base
 * Copyright (C) 2015         Pablo Peralta
 * Copyright (C) 2015-2017    Carlos Garcia Gomez  neorazorx@gmail.com
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

require_once 'plugins/facturacion_base/extras/fbase_controller.php';

class admin_transportes extends fbase_controller
{

    public $listado;

    public function __construct()
    {
        parent::__construct(__CLASS__, 'Agencias de transporte', 'admin');
    }

    protected function private_core()
    {
        parent::private_core();

        $agencia = new agencia_transporte();

        if (filter_input(INPUT_POST, 'codtrans')) {
            $this->editar_agencia($agencia);
        } else if (filter_input(INPUT_GET, 'delete')) {
            $this->eliminar_agencia($agencia);
        }

        $this->listado = $agencia->all();
    }

    private function editar_agencia(&$agencia)
    {
        $agencia2 = $agencia->get(filter_input(INPUT_POST, 'codtrans'));
        if (!$agencia2) {
            /// si no existe la creamos
            $agencia2 = new agencia_transporte();
            $agencia2->codtrans = filter_input(INPUT_POST, 'codtrans');
        }

        $agencia2->nombre = filter_input(INPUT_POST, 'nombre');
        $agencia2->telefono = filter_input(INPUT_POST, 'telefono');
        $agencia2->web = filter_input(INPUT_POST, 'web');
        $agencia2->activo = isset($_POST['activo']);

        if ($agencia2->save()) {
            $this->new_message('Datos guardaddos correctamente.');
        } else {
            $this->new_error_msg('Error al guardar los datos.');
        }
    }

    private function eliminar_agencia(&$agencia)
    {
        $agencia2 = $agencia->get(filter_input(INPUT_GET, 'delete'));
        if ($agencia2) {
            if (!$this->allow_delete) {
                $this->new_error_msg('No tienes permiso para eliminar en esta página.');
            } else if ($agencia2->delete()) {
                $this->new_message('Agencia eliminada correctamente.');
            } else {
                $this->new_error_msg('Error al eliminar la agencia.');
            }
        } else {
            $this->new_error_msg('Agencia no encontrada.');
        }
    }
}
