<?php
/**
 * This file is part of facturacion_base
 * Copyright (C) 2014-2019 Carlos Garcia Gomez <neorazorx@gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 * 
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */
require_once 'plugins/facturacion_base/extras/fbase_controller.php';
require_once 'plugins/facturacion_base/extras/libromayor.php';

class contabilidad_subcuenta extends fbase_controller
{

    /**
     *
     * @var cuenta
     */
    public $cuenta;

    /**
     *
     * @var divisa
     */
    public $divisa;

    /**
     *
     * @var ejercicio
     */
    public $ejercicio;

    /**
     *
     * @var string
     */
    public $pdf_libromayor;

    /**
     *
     * @var array
     */
    public $resultados;

    /**
     *
     * @var subcuenta|bool
     */
    public $subcuenta;

    /**
     *
     * @var int
     */
    public $offset;

    public function __construct()
    {
        parent::__construct(__CLASS__, 'Subcuenta', 'contabilidad', FALSE, FALSE);
    }

    protected function private_core()
    {
        parent::private_core();
        $this->divisa = new divisa();

        $subcuenta = new subcuenta();
        $this->subcuenta = FALSE;
        if (isset($_GET['id'])) {
            $this->subcuenta = $subcuenta->get($_GET['id']);
        }

        if ($this->subcuenta) {
            /// configuramos la página previa
            $this->ppage = $this->page->get('contabilidad_cuenta');
            $this->ppage->title = 'Cuenta: ' . $this->subcuenta->codcuenta;
            $this->ppage->extra_url = '&id=' . $this->subcuenta->idcuenta;

            $this->page->title = 'Subcuenta: ' . $this->subcuenta->codsubcuenta;
            $this->cuenta = $this->subcuenta->get_cuenta();
            $this->ejercicio = $this->subcuenta->get_ejercicio();

            $this->offset = 0;
            if (isset($_GET['offset'])) {
                $this->offset = intval($_GET['offset']);
            }

            $this->resultados = $this->subcuenta->get_partidas($this->offset);

            if (isset($_POST['puntear'])) {
                $this->modificar();
            } else if (isset($_GET['genlm'])) {
                $this->generar_libro_mayor();
            }

            $this->pdf_libromayor = '';
            if (file_exists('tmp/' . FS_TMP_NAME . 'libro_mayor/' . $this->subcuenta->idsubcuenta . '.pdf')) {
                $this->pdf_libromayor = 'tmp/' . FS_TMP_NAME . 'libro_mayor/' . $this->subcuenta->idsubcuenta . '.pdf';
            }

            /// comprobamos la subcuenta
            $this->subcuenta->test();
        } else {
            $this->new_error_msg("Subcuenta no encontrada.", 'error', FALSE, FALSE);
            $this->ppage = $this->page->get('contabilidad_cuentas');
        }
    }

    public function url()
    {
        if (!isset($this->subcuenta)) {
            return parent::url();
        } else if ($this->subcuenta) {
            return $this->subcuenta->url();
        }

        return $this->ppage->url();
    }

    public function paginas()
    {
        return $this->fbase_paginas($this->url(), $this->subcuenta->count_partidas(), $this->offset);
    }

    private function modificar()
    {
        if ($_POST['descripcion'] != $this->subcuenta->descripcion) {
            $this->subcuenta->descripcion = $_POST['descripcion'];
            $this->subcuenta->coddivisa = $_POST['coddivisa'];
            $this->subcuenta->save();
        }

        foreach ($this->resultados as $pa) {
            if (isset($_POST['punteada'])) {
                $valor = in_array($pa->idpartida, $_POST['punteada']);
            } else {
                $valor = FALSE;
            }

            if ($pa->punteada != $valor) {
                $pa->punteada = $valor;
                $pa->save();
            }
        }

        $this->new_message('Datos guardados correctamente.');
    }

    private function generar_libro_mayor()
    {
        /// generamos el PDF del libro mayor si no existe
        $libro_mayor = new libro_mayor();
        $libro_mayor->libro_mayor($this->subcuenta);
        if (file_exists('tmp/' . FS_TMP_NAME . 'libro_mayor/' . $this->subcuenta->idsubcuenta . '.pdf')) {
            header('Location: ' . FS_PATH . 'tmp/' . FS_TMP_NAME . 'libro_mayor/' . $this->subcuenta->idsubcuenta . '.pdf');
        } else {
            $this->new_error_msg('Error al generar el libro mayor.');
        }
    }
}
