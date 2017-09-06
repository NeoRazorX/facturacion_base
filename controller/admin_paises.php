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

class admin_paises extends fbase_controller
{

    public $pais;
    public $resultados;

    public function __construct()
    {
        parent::__construct(__CLASS__, 'Paises', 'admin');
    }

    protected function private_core()
    {
        parent::private_core();

        $this->pais = new pais();

        if (isset($_POST['scodpais'])) {
            $this->editar_pais();
        } else if (isset($_GET['delete'])) {
            $this->eliminar_pais();
        }
        
        $this->resultados = $this->pais->all();
    }

    private function editar_pais()
    {
        $pais = $this->pais->get($_POST['scodpais']);
        if (!$pais) {
            /// si no existe lo creamos
            $pais = new pais();
            $pais->codpais = $_POST['scodpais'];
        }

        $pais->codiso = $_POST['scodiso'];
        $pais->nombre = $_POST['snombre'];

        if ($pais->save()) {
            $this->new_message("País " . $pais->nombre . " guardado correctamente.");
        } else {
            $this->new_error_msg("¡Imposible guardar el país!");
        }
    }

    private function eliminar_pais()
    {
        if (FS_DEMO) {
            $this->new_error_msg('En el modo demo no puedes eliminar paises. Otro usuario podría necesitarlo.');
        } else if (!$this->allow_delete) {
            $this->new_error_msg('No tienes permiso para eliminar en esta página.');
        } else {
            $pais = $this->pais->get($_GET['delete']);
            if ($pais) {
                if ($pais->delete()) {
                    $this->new_message("País " . $pais->nombre . " eliminado correctamente.");
                } else {
                    $this->new_error_msg("¡Imposible eliminar el país!");
                }
            } else {
                $this->new_error_msg("¡País no encontrado!");
            }
        }
    }
}
