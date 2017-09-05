<?php
/**
 * This file is part of facturacion_base
 * Copyright (C) 2016-2017  Carlos Garcia Gomez  neorazorx@gmail.com
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

/**
 * Description of articulo_trazabilidad
 *
 * @author Carlos Garcia Gomez
 */
class articulo_trazabilidad extends fbase_controller
{

    public $articulo;
    public $trazas;

    public function __construct()
    {
        parent::__construct(__CLASS__, '', 'ventas', FALSE, FALSE);
    }

    protected function private_core()
    {
        parent::private_core();

        $this->articulo = FALSE;
        if (isset($_REQUEST['ref'])) {
            $art0 = new articulo();
            $this->articulo = $art0->get($_REQUEST['ref']);
        }

        if ($this->articulo) {
            if (isset($_POST['numserie'])) {
                if ($_POST['numserie'] != '' || $_POST['lote'] != '') {
                    $this->edit_traza();
                } else {
                    $this->new_error_msg('Debes escribir un número de serie o un lote o ambos,'
                        . ' pero algo debes escribir.');
                }
            } else if (isset($_GET['delete'])) {
                $this->delete_traza();
            }

            $this->get_trazas();
        } else {
            $this->new_error_msg('Artículo no encontrado.', 'error', FALSE, FALSE);
        }
    }

    public function url()
    {
        if ($this->articulo) {
            return 'index.php?page=' . __CLASS__ . '&ref=' . urlencode($this->articulo->referencia);
        }

        return parent::url();
    }

    private function edit_traza()
    {
        if (isset($_POST['id'])) {
            $atraza = new articulo_traza();
            $natraza = $atraza->get($_POST['id']);
        } else {
            $natraza = new articulo_traza();
            $natraza->referencia = $this->articulo->referencia;
            $natraza->fecha_entrada = $_POST['fecha_entrada'];
        }

        if ($natraza) {
            $natraza->numserie = NULL;
            if ($_POST['numserie'] != '') {
                $natraza->numserie = $_POST['numserie'];
            }

            $natraza->lote = NULL;
            if ($_POST['lote'] != '') {
                $natraza->lote = $_POST['lote'];
            }

            if ($natraza->save()) {
                $this->new_message('Datos guardados correctamente.');
            } else {
                $this->new_error_msg('Error al guardar los datos.');
            }
        } else {
            $this->new_error_msg('Datos no encontrados.');
        }
    }

    private function delete_traza()
    {
        $atraza = new articulo_traza();

        $natraza = $atraza->get($_GET['delete']);
        if ($natraza) {
            if ($natraza->delete()) {
                $this->new_message('Datos eliminados correctamente.');
            } else {
                $this->new_error_msg('Error al eliminar los datos.');
            }
        } else {
            $this->new_error_msg('Traza no encontrada.');
        }
    }

    private function get_trazas()
    {
        /// forzamos la comprobación de la tabla
        new articulo_traza();

        $this->trazas = array();
        $sql = "SELECT * FROM articulo_trazas WHERE referencia = " . $this->articulo->var2str($this->articulo->referencia)
            . " ORDER BY id DESC";

        $data = $this->db->select($sql);
        if ($data) {
            foreach ($data as $d) {
                $this->trazas[] = new articulo_traza($d);
            }
        }
    }
}
