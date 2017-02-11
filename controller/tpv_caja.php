<?php
/*
 * This file is part of FacturaSctipts
 * Copyright (C) 2013-2015  Carlos Garcia Gomez  neorazorx@gmail.com
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 * 
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

require_model('almacen.php');
require_model('asiento.php');
require_model('caja.php');
require_model('serie.php');
require_model('terminal_caja.php');
require_model('forma_pago.php');

class tpv_caja extends fs_controller {

    public $allow_delete;
    public $almacen;
    public $caja;
    public $offset;
    public $resultados;
    public $serie;
    public $terminal;
    public $terminales;
    public $forma_pago;

    public function __construct() {
        parent::__construct(__CLASS__, 'Cajas', 'TPV', FALSE, TRUE);
    }

    protected function private_core() {
        /// ¿El usuario tiene permiso para eliminar en esta página?
        $this->allow_delete = $this->user->allow_delete_on(__CLASS__);

        $this->almacen = new almacen();
        $this->caja = new caja();
        $this->serie = new serie();
        $this->terminal = new terminal_caja();

        $action = (string)isset($_GET['action']) ? $_GET['action'] : 'list';
        $this->idcaja = (int)isset($_GET['idcaja']) ? $_GET['idcaja'] : '0';
        $this->idterminal = (int)isset($_GET['idterminal']) ? $_GET['idterminal'] : '0';
        $this->idterminadefecto = 1;

        // Código feo que viene de la version anterior
        if (isset($_POST['nuevot'])) {
            /// nuevo terminal
            $action = 'addTerminal';
        } elseif (isset($_POST['idt'])) {
            /// editar terminal
            $action = 'editTerminal';
        } elseif (isset($_GET['deletet'])) {
            /// eliminar terminal
            $action = 'deleteTerminal';
        } elseif (isset($_GET['delete'])) {
            /// eliminar caja
            $action = 'deleteCaja';
        } elseif (isset($_GET['cerrar'])) {
            $action = 'cerrarCaja';
        }


        switch ($action) {
            default:
            case 'list':
                $this->indexAction();
                break;
            case 'addCaja':
                $this->addCajaAction();
                break;
            case 'cerrarCaja':
                $this->cerrarCajaAction();
                break;
            case 'deleteCaja':
                $this->deleteCajaAction();
                break;
            case 'addTerminal':
                $this->addTerminalAction();
                break;
            case 'editTerminal':
                $this->editTerminalAction();
                break;
            case 'deleteTerminal':
                $this->deleteTerminalAction();
                break;
        }


    }

    public function indexAction() {
        $terminal = new terminal_caja();
        $this->template = 'tpv_caja';
        $this->offset = 0;
        if (isset($_GET['offset'])) {
            $this->offset = intval($_GET['offset']);
        }

        $this->resultados = $this->caja->all($this->offset);
        $this->terminales = $terminal->all();
    }

    /**
     * Acciones relacionadas a las cajas!
     */

    public function addCajaAction() {
        $this->page->extra_url = '&action=addCaja';
        $this->caja = new caja();
        $this->template = 'tpv_caja/caja_form';
        if ($this->user->admin) {
            if ($this->terminal) {
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $this->caja->setValues($_POST);
                    $this->caja->setConfCaja(conf_caja::get_info());
                    if ($this->caja->save()) {
                        $this->new_message("Caja iniciada con " . $this->show_precio($this->caja->dinero_inicial));
                        $this->indexAction();
                    } else {
                        $this->new_error_msg("¡Imposible guardar los datos de caja!");
                    }
                }
            }
        } else {
            $this->new_error_msg('Sólo un administrador puede abrir la caja.');
        }
    }

    public function cerrarCajaAction() {
        if ($this->user->admin) {
            $caja2 = caja::get($_GET['cerrar']);
            if ($caja2 && !$caja2->fecha_fin) {
                $caja2->setEdit();
                $caja2->fecha_fin = date('d-m-Y H:i:s');
                if ($caja2->save()) {
                    $this->new_message("Caja cerrada correctamente.");
                    $this->indexAction();
                } else {
                    $this->new_error_msg("¡Imposible cerrar la caja!");
                }
            } else {
                $this->new_error_msg("Caja no encontrada o caja ya cerrada.");
            }
        } else {
            $this->new_error_msg("Tienes que ser administrador para poder cerrar cajas.");
        }
        $this->indexAction();
    }

    public function deleteCajaAction() {
        if ($this->user->admin) {
            $caja2 = $this->caja->get($_GET['delete']);
            if ($caja2) {
                if ($caja2->delete()) {
                    $this->new_message("Caja eliminada correctamente.");
                } else {
                    $this->new_error_msg("¡Imposible eliminar la caja!");
                }
            } else {
                $this->new_error_msg("Caja no encontrada.");
            }
        } else {
            $this->new_error_msg("Tienes que ser administrador para poder eliminar cajas.");
        }
    }

    /**
     * Acciones relacionadas a las terminales
     */

    public function addTerminalAction() {
        $terminal = new terminal_caja();
        $terminal->codalmacen = $_POST['codalmacen'];
        $terminal->codserie = $_POST['codserie'];

        $terminal->codcliente = NULL;
        if ($_POST['codcliente'] != '') {
            $terminal->codcliente = $_POST['codcliente'];
        }

        $terminal->anchopapel = intval($_POST['anchopapel']);
        $terminal->comandoapertura = $_POST['comandoapertura'];
        $terminal->comandocorte = $_POST['comandocorte'];
        $terminal->num_tickets = intval($_POST['num_tickets']);

        if ($terminal->save()) {
            $this->new_message('Terminal añadido correctamente.');
        } else {
            $this->new_error_msg('Error al guardar los datos.');
        }
    }

    public function editTerminalAction() {
        $terminal = new terminal_caja();
        $t2 = $terminal->get($_POST['idt']);
        if ($t2) {
            $t2->codalmacen = $_POST['codalmacen'];
            $t2->codserie = $_POST['codserie'];

            $t2->codcliente = NULL;
            if ($_POST['codcliente'] != '') {
                $t2->codcliente = $_POST['codcliente'];
            }

            $t2->anchopapel = intval($_POST['anchopapel']);
            $t2->comandoapertura = $_POST['comandoapertura'];
            $t2->comandocorte = $_POST['comandocorte'];
            $t2->num_tickets = intval($_POST['num_tickets']);

            if ($t2->save()) {
                $this->new_message('Datos guardados correctamente.');
            } else {
                $this->new_error_msg('Error al guardar los datos.');
            }
        } else {
            $this->new_error_msg('Terminal no encontrado.');
        }
    }

    public function deleteTerminalAction() {
        $terminal = new terminal_caja();
        if ($this->user->admin) {
            $t2 = $terminal->get($_GET['deletet']);
            if ($t2) {
                if ($t2->delete()) {
                    $this->new_message('Terminal eliminado correctamente.');
                } else {
                    $this->new_error_msg('Error al eliminar el terminal.');
                }
            } else {
                $this->new_error_msg('Terminal no encontrado.');
            }
        } else {
            $this->new_error_msg("Tienes que ser administrador para poder eliminar terminales.");
        }
    }

    public function new_caja_url() {
        return $this->url() . '&action=addCaja';
    }

    public function asiento_url(asiento $asiento) {
        return $asiento->url();
    }

    public function anterior_url() {
        $url = '';

        if ($this->offset > 0) {
            $url = $this->url() . "&offset=" . ($this->offset - FS_ITEM_LIMIT);
        }

        return $url;
    }

    public function siguiente_url() {
        $url = '';

        if (count($this->resultados) == FS_ITEM_LIMIT) {
            $url = $this->url() . "&offset=" . ($this->offset + FS_ITEM_LIMIT);
        }

        return $url;
    }

    public function facturas_url(caja $caja) {
        return str_ireplace('tpv_caja', 'facturascaja', $this->url()) . '&idcaja=' . $caja->id;
    }

    public function recibos_url(caja $caja) {
        return str_ireplace('tpv_caja', 'reciboscaja', $this->url()) . '&idcaja=' . $caja->id;
    }

}
