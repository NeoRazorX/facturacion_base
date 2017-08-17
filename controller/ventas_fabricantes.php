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

class ventas_fabricantes extends fbase_controller
{

    public $fabricante;
    public $resultados;

    public function __construct()
    {
        parent::__construct(__CLASS__, 'Fabricantes', 'ventas', FALSE, FALSE);
    }

    protected function private_core()
    {
        parent::private_core();

        $this->share_extensions();
        $this->fabricante = new fabricante();

        if (isset($_POST['ncodfabricante'])) {
            $this->nuevo_fabricante();
        } else if (isset($_GET['delete'])) {
            $this->eliminar_fabricante();
        }

        $this->resultados = $this->fabricante->search($this->query);
    }

    private function nuevo_fabricante()
    {
        $fab = $this->fabricante->get($_POST['ncodfabricante']);
        if ($fab) {
            $this->new_error_msg('El fabricante <a href="' . $fab->url() . '">' . $fab->codfabricante . '</a> ya existe.');
        } else {
            $fab = new fabricante();
            $fab->codfabricante = $_POST['ncodfabricante'];
            $fab->nombre = $_POST['nnombre'];
            if ($fab->save()) {
                Header('location: ' . $fab->url());
            } else
                $this->new_error_msg("¡Imposible guardar el fabricante!");
        }
    }

    private function eliminar_fabricante()
    {
        $fab = $this->fabricante->get($_GET['delete']);
        if ($fab) {
            if (!$this->allow_delete) {
                $this->new_message("No tienes permiso para eliminar en esta página.");
            } else if ($fab->delete()) {
                $this->new_message("Fabricante " . $_GET['delete'] . " eliminado correctamente");
            } else
                $this->new_error_msg("¡Imposible eliminar el fabricante " . $_GET['delete'] . "!");
        } else
            $this->new_error_msg("Fabricante " . $_GET['delete'] . " no encontrado.");
    }

    public function total_fabricantes()
    {
        return $this->fbase_sql_total('fabricantes', 'codfabricante');
    }

    private function share_extensions()
    {
        /// añadimos la extensión para ventas_artículos
        $fsext = new fs_extension();
        $fsext->name = 'btn_fabricantes';
        $fsext->from = __CLASS__;
        $fsext->to = 'ventas_articulos';
        $fsext->type = 'button';
        $fsext->text = '<span class="glyphicon glyphicon-folder-open" aria-hidden="true"></span><span class="hidden-xs"> &nbsp; Fabricantes</span>';
        $fsext->save();
    }
}
