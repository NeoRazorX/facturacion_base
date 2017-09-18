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

class ventas_agrupar_albaranes extends fbase_controller
{

    public $albaran;
    public $cliente;
    public $coddivisa;
    public $codserie;
    public $desde;
    public $divisa;
    public $hasta;
    public $neto;
    public $observaciones;
    public $resultados;
    public $serie;
    public $total;
    private $ejercicio;
    public $forma_pago;

    public function __construct()
    {
        parent::__construct(__CLASS__, 'Agrupar ' . FS_ALBARANES, 'ventas', FALSE, FALSE);
    }

    protected function private_core()
    {
        parent::private_core();

        $this->albaran = new albaran_cliente();
        $this->cliente = FALSE;
        $this->divisa = new divisa();
        $this->ejercicio = new ejercicio();
        $this->forma_pago = new forma_pago();
        $this->serie = new serie();
        $this->neto = 0;
        $this->total = 0;

        $this->coddivisa = $this->empresa->coddivisa;
        if (isset($_REQUEST['coddivisa'])) {
            $this->coddivisa = $_REQUEST['coddivisa'];
        }

        $this->codserie = $this->empresa->codserie;
        if (isset($_REQUEST['codserie'])) {
            $this->codserie = $_REQUEST['codserie'];
        }

        $this->desde = Date('01-01-Y');
        if (isset($_REQUEST['desde'])) {
            $this->desde = $_REQUEST['desde'];
        }

        $this->hasta = Date('t-m-Y');
        if (isset($_REQUEST['hasta'])) {
            $this->hasta = $_REQUEST['hasta'];
        }

        /// el desde no puede ser mayor que el hasta
        if (strtotime($this->desde) > strtotime($this->hasta)) {
            $this->hasta = $this->desde;
        }

        $this->observaciones = '';
        if (isset($_REQUEST['observaciones'])) {
            $this->observaciones = $_REQUEST['observaciones'];
        }

        if (isset($_REQUEST['buscar_cliente'])) {
            $this->fbase_buscar_cliente($_REQUEST['buscar_cliente']);
        } else if (isset($_POST['idalbaran'])) {
            $this->cliente = new cliente();
            $this->agrupar();
        } else if (isset($_REQUEST['codcliente'])) {
            $cli0 = new cliente();
            $this->cliente = $cli0->get($_REQUEST['codcliente']);

            if ($this->cliente) {
                $this->resultados = $this->albaran->search_from_cliente(
                    $this->cliente->codcliente, $this->desde, $this->hasta, $this->codserie, $this->observaciones, $this->coddivisa
                );
                if (!empty($this->resultados)) {
                    foreach ($this->resultados as $alb) {
                        $this->neto += $alb->neto;
                        $this->total += $alb->total;
                    }
                } else {
                    $this->new_message("Sin resultados.");
                }
            }
        } else {
            $this->share_extensions();
        }
    }

    private function agrupar()
    {
        $continuar = TRUE;
        $albaranes = array();

        if ($this->duplicated_petition($_POST['petition_id'])) {
            $this->new_error_msg('Petición duplicada. Has hecho doble clic sobre el botón y se han enviado dos peticiones.');
            $continuar = FALSE;
        } else {
            foreach ($_POST['idalbaran'] as $id) {
                $albaranes[] = $this->albaran->get($id);
            }

            foreach ($albaranes as $alb) {
                if (!$alb->ptefactura) {
                    $this->new_error_msg("El " . FS_ALBARAN . " <a href='" . $alb->url() . "'>" . $alb->codigo . "</a> ya está facturado.");
                    $continuar = FALSE;
                    break;
                }
            }
        }
        
        $codpago = '';
        if(isset($_REQUEST['codpago'])) {
            $codpago = $_REQUEST['codpago'];
        }

        if ($continuar) {
            if (isset($_POST['individuales'])) {
                foreach ($albaranes as $alb) {
                    $this->fbase_facturar_albaran_cliente(array($alb), $_POST['fecha'], $codpago);
                }
            } else {
                $this->fbase_facturar_albaran_cliente($albaranes, $_POST['fecha'], $codpago);
            }
        }
    }

    private function share_extensions()
    {
        $extension = array(
            'name' => 'agrupar_albaranes',
            'page_from' => __CLASS__,
            'page_to' => 'ventas_albaranes',
            'type' => 'button',
            'text' => '<span class="glyphicon glyphicon-duplicate"></span><span class="hidden-xs">&nbsp; Agrupar</span>',
            'params' => ''
        );
        $fsext = new fs_extension($extension);
        $fsext->save();
    }

    public function pendientes()
    {
        $pendientes = array();

        $offset = 0;
        $albaranes = $this->albaran->all_ptefactura($offset);
        while (!empty($albaranes)) {
            foreach ($albaranes as $alb) {
                if ($alb->codcliente) {
                    /// Comprobamos si el cliente ya está en la lista.
                    $encontrado = FALSE;
                    foreach ($pendientes as $i => $pe) {
                        if ($alb->codcliente == $pe['codcliente'] && $alb->codserie == $pe['codserie'] && $alb->coddivisa == $pe['coddivisa']) {
                            $encontrado = TRUE;
                            $pendientes[$i]['num'] ++;

                            if (strtotime($alb->fecha) < strtotime($pe['desde'])) {
                                $pendientes[$i]['desde'] = $alb->fecha;
                            }

                            if (strtotime($alb->fecha) > strtotime($pe['hasta'])) {
                                $pendientes[$i]['hasta'] = $alb->fecha;
                            }

                            break;
                        }
                    }

                    /// Añadimos a la lista de pendientes.
                    if (!$encontrado) {
                        $pendientes[] = array(
                            'codcliente' => $alb->codcliente,
                            'nombre' => $alb->nombrecliente,
                            'codserie' => $alb->codserie,
                            'coddivisa' => $alb->coddivisa,
                            'desde' => date('d-m-Y', min(array(strtotime($alb->fecha), strtotime($this->desde)))),
                            'hasta' => date('d-m-Y', max(array(strtotime($alb->fecha), strtotime($this->hasta)))),
                            'num' => 1
                        );
                    }
                }

                $offset++;
            }

            $albaranes = $this->albaran->all_ptefactura($offset);
        }

        return $pendientes;
    }
}
