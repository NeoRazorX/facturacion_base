<?php
/*
 * This file is part of facturacion_base
 * Copyright (C) 2014-2017  Carlos Garcia Gomez  neorazorx@gmail.com
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

class admin_almacenes extends fbase_controller
{

    public $almacenes;
    public $pais;

    public function __construct()
    {
        parent::__construct(__CLASS__, 'Almacenes', 'admin');
    }

    protected function private_core()
    {
        parent::private_core();

        $almacen = new almacen();
        $this->pais = new pais();

        if (isset($_POST['scodalmacen'])) {
            $this->editar_almacen($almacen);
        } else if (isset($_GET['delete'])) {
            $this->eliminar_almacen($almacen);
        } else {
            $this->guardar_opciones_avanzadas();
        }

        $this->almacenes = $almacen->all();

        /// si hay más de un almacén activamos el soporte multi-almacén en los listados
        $fsvar = new fs_var();
        if (count($this->almacenes) > 1) {
            $fsvar->simple_save('multi_almacen', TRUE);
        } else {
            $fsvar->simple_delete('multi_almacen');
        }
    }

    private function editar_almacen(&$almacen)
    {
        $al0 = $almacen->get($_POST['scodalmacen']);
        if (!$al0) {
            /// si no existe el almacén, lo creamos
            $al0 = new almacen();
            $al0->codalmacen = $_POST['scodalmacen'];
        }
        $al0->nombre = $_POST['snombre'];
        $al0->codpais = $_POST['scodpais'];
        $al0->provincia = $_POST['sprovincia'];
        $al0->poblacion = $_POST['spoblacion'];
        $al0->direccion = $_POST['sdireccion'];
        $al0->codpostal = $_POST['scodpostal'];
        $al0->telefono = $_POST['stelefono'];
        $al0->fax = $_POST['sfax'];
        $al0->contacto = $_POST['scontacto'];

        if ($al0->save()) {
            $this->new_message("Almacén " . $al0->codalmacen . " guardado correctamente.");
        } else {
            $this->new_error_msg("¡Imposible guardar el almacén!");
        }
    }

    private function eliminar_almacen(&$almacen)
    {
        $al0 = $almacen->get($_GET['delete']);
        if ($al0) {
            if (!$this->user->admin) {
                $this->new_error_msg("Solo un administrador puede eliminar un almacén.");
            } else if ($al0->delete()) {
                $this->new_message("Almacén " . $al0->codalmacen . " eliminado correctamente");
            } else {
                $this->new_error_msg("¡Imposible eliminar el almacén!");
            }
        } else {
            $this->new_error_msg("¡Almacén no encontrado!");
        }
    }

    private function guardar_opciones_avanzadas()
    {
        /// ¿Guardamos las opciones avanzadas?
        $guardar = FALSE;
        foreach ($GLOBALS['config2'] as $i => $value) {
            if (isset($_POST[$i])) {
                $GLOBALS['config2'][$i] = $_POST[$i];
                $guardar = TRUE;
            }
        }

        if ($guardar) {
            $file = fopen('tmp/' . FS_TMP_NAME . 'config2.ini', 'w');
            if ($file) {
                foreach ($GLOBALS['config2'] as $i => $value) {
                    if (is_numeric($value)) {
                        fwrite($file, $i . " = " . $value . ";\n");
                    } else {
                        fwrite($file, $i . " = '" . $value . "';\n");
                    }
                }

                fclose($file);
            }

            $this->new_message('Datos guardados correctamente.');
        }
    }
}
