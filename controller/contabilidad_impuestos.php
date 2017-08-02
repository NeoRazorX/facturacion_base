<?php
/*
 * This file is part of facturacion_base
 * Copyright (C) 2013-2017  Carlos Garcia Gomez  neorazorx@gmail.com
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

class contabilidad_impuestos extends fbase_controller
{

    public $codsubcuentasop;
    public $codsubcuentarep;
    public $impuesto;

    public function __construct()
    {
        parent::__construct(__CLASS__, 'Impuestos', 'contabilidad');
    }

    protected function private_core()
    {
        parent::private_core();

        $this->impuesto = new impuesto();

        /// Leemos las subcuentas predeterminadas
        $this->subcuentas_predeterminadas();

        if (isset($_GET['delete'])) {
            $this->eliminar_impuesto();
        } else if (isset($_POST['codimpuesto'])) {
            $this->editar_impuesto();
        } else if (isset($_GET['set_default'])) {
            $this->save_codimpuesto($_GET['set_default']);
        }
    }

    private function subcuentas_predeterminadas()
    {
        $subcuenta = new subcuenta();

        $this->codsubcuentasop = '';
        $subcuentasop = $subcuenta->get_cuentaesp('IVASOP', $this->empresa->codejercicio);
        if ($subcuentasop) {
            $this->codsubcuentasop = $subcuentasop->codsubcuenta;
        }

        $this->codsubcuentarep = '';
        $subcuentarep = $subcuenta->get_cuentaesp('IVAREP', $this->empresa->codejercicio);
        if ($subcuentarep) {
            $this->codsubcuentarep = $subcuentarep->codsubcuenta;
            if (!$this->codsubcuentasop) {
                $this->codsubcuentasop = $this->codsubcuentarep;
            }
        }
    }

    private function editar_impuesto()
    {
        $impuesto = $this->impuesto->get($_POST['codimpuesto']);
        if (!$impuesto) {
            $impuesto = new impuesto();
            $impuesto->codimpuesto = $_POST['codimpuesto'];
        }

        $impuesto->descripcion = $_POST['descripcion'];

        $impuesto->codsubcuentarep = NULL;
        if ($_POST['codsubcuentarep'] != '') {
            $impuesto->codsubcuentarep = $_POST['codsubcuentarep'];
        }

        $impuesto->codsubcuentasop = NULL;
        if ($_POST['codsubcuentasop'] != '') {
            $impuesto->codsubcuentasop = $_POST['codsubcuentasop'];
        }

        $impuesto->iva = floatval($_POST['iva']);
        $impuesto->recargo = floatval($_POST['recargo']);

        if ($impuesto->save()) {
            $this->new_message("Impuesto " . $impuesto->codimpuesto . " guardado correctamente.");
        } else
            $this->new_error_msg("¡Error al guardar el impuesto!");
    }

    private function eliminar_impuesto()
    {
        if (!$this->user->admin) {
            $this->new_error_msg('Sólo un administrador puede eliminar impuestos.');
        } else {
            $impuesto = $this->impuesto->get($_GET['delete']);
            if ($impuesto) {
                if ($impuesto->delete()) {
                    $this->new_message('Impuesto eliminado correctamente.');
                } else
                    $this->new_error_msg('Ha sido imposible eliminar el impuesto.');
            } else
                $this->new_error_msg('Impuesto no encontrado.');
        }
    }
}
