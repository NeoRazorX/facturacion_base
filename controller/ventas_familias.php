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

class ventas_familias extends fbase_controller
{

    public $familia;
    public $madre;
    public $resultados;

    public function __construct()
    {
        parent::__construct(__CLASS__, 'Familias', 'ventas', FALSE, FALSE);
    }

    protected function private_core()
    {
        parent::private_core();

        $this->familia = new familia();

        $this->madre = NULL;
        if (isset($_REQUEST['madre'])) {
            $this->madre = $_REQUEST['madre'];
        }

        if (isset($_POST['ncodfamilia'])) {
            $this->nueva_familia();
        } else if (isset($_GET['delete'])) {
            $this->eliminar_familia();
        }

        if ($this->query != '') {
            $this->resultados = $this->familia->search($this->query);
        } else {
            $this->resultados = $this->familia->madres();
            $this->share_extensions();
        }
    }

    private function nueva_familia()
    {
        $fam = $this->familia->get($_POST['ncodfamilia']);
        if ($fam) {
            $this->new_error_msg('La familia <a href="' . $fam->url() . '">' . $fam->codfamilia . '</a> ya existe.');
        } else {
            $fam = new familia();
            $fam->codfamilia = $_POST['ncodfamilia'];
            $fam->descripcion = $_POST['ndescripcion'];
            $fam->madre = $this->madre;
            if ($fam->save()) {
                Header('location: ' . $fam->url());
            } else
                $this->new_error_msg("¡Imposible guardar la familia!");
        }
    }

    private function eliminar_familia()
    {
        $fam = $this->familia->get($_GET['delete']);
        if ($fam) {
            if (!$this->allow_delete) {
                $this->new_message("No tienes permiso para eliminar en esta página.");
            } else if ($fam->delete()) {
                $this->new_message("Familia " . $fam->codfamilia . " eliminada correctamente.");
            } else
                $this->new_error_msg("¡Imposible eliminar la familia " . $fam->codfamilia . "!");
        } else
            $this->new_error_msg("Familia " . $_GET['delete'] . " no encontrada.");
    }

    public function total_familias()
    {
        return $this->fbase_sql_total('familias', 'codfamilia');
    }

    private function share_extensions()
    {
        /// añadimos la extensión para ventas_artículos
        $fsext = new fs_extension();
        $fsext->name = 'btn_familias';
        $fsext->from = __CLASS__;
        $fsext->to = 'ventas_articulos';
        $fsext->type = 'button';
        $fsext->text = '<span class="glyphicon glyphicon-folder-open" aria-hidden="true"></span>'
            . '<span class="hidden-xs"> &nbsp; Familias</span>';
        $fsext->save();
    }
}
