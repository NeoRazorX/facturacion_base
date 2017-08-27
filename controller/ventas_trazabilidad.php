<?php
/*
 * This file is part of facturacion_base
 * Copyright (C) 2017  Carlos Garcia Gomez  neorazorx@gmail.com
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

/**
 * Description of ventas_trazabilidad
 *
 * @author Carlos Garcia Gomez
 */
class ventas_trazabilidad extends fs_controller
{

    public $disponibles;
    public $documento;
    public $lineas;
    public $tab;
    public $tipo;
    public $volver;

    public function __construct()
    {
        parent::__construct(__CLASS__, 'Trazabilidad', 'ventas', FALSE, FALSE);
    }

    protected function private_core()
    {
        $this->documento = FALSE;
        if (isset($_GET['doc']) && isset($_GET['id'])) {
            if ($_GET['doc'] == 'albaran') {
                $alb = new albaran_cliente();
                $this->documento = $alb->get($_GET['id']);
                $this->tipo = FS_ALBARAN . ' de venta';
            } else if ($_GET['doc'] == 'factura') {
                $fac = new factura_cliente();
                $this->documento = $fac->get($_GET['id']);
                $this->tipo = 'factura de venta';
            }
        }

        $this->tab = isset($_GET['tab']);

        $this->volver = FALSE;
        if (isset($_REQUEST['volver'])) {
            $this->volver = $_REQUEST['volver'];
        }

        if ($this->documento) {
            if (isset($_POST['asignar'])) {
                $this->asignar();
            }

            $this->get_lineas();

            /**
             * Cargamos las extensiones solamente si se usa trazabilidad,
             * hasta entonces no aparecerá la pestaña.
             */
            $this->share_extensions();
        } else {
            $this->new_error_msg('Documento no encontrado.', 'error', FALSE, FALSE);
        }
    }

    private function share_extensions()
    {
        $fsext = new fs_extension();
        $fsext->name = 'tab_ventas_trazabilidad_fac';
        $fsext->from = __CLASS__;
        $fsext->to = 'ventas_factura';
        $fsext->type = 'tab';
        $fsext->text = '<i class="fa fa-code-fork" aria-hidden="true"></i>'
            . '<span class="hidden-xs">&nbsp;Trazabilidad</span>';
        $fsext->params = '&doc=factura&tab=TRUE';
        $fsext->save();

        $fsext2 = new fs_extension();
        $fsext2->name = 'tab_ventas_trazabilidad_alb';
        $fsext2->from = __CLASS__;
        $fsext2->to = 'ventas_albaran';
        $fsext2->type = 'tab';
        $fsext2->text = '<i class="fa fa-code-fork" aria-hidden="true"></i>'
            . '<span class="hidden-xs">&nbsp;Trazabilidad</span>';
        $fsext2->params = '&doc=albaran&tab=TRUE';
        $fsext2->save();
    }

    public function url()
    {
        if ($this->documento) {
            $extra = '';
            if ($this->tab) {
                $extra = '&tab=TRUE';
            }
            if ($this->volver) {
                $extra .= '&volver=' . urlencode($this->volver);
            }

            if (get_class_name($this->documento) == 'albaran_cliente') {
                return parent::url() . '&doc=albaran&id=' . $this->documento->idalbaran . $extra;
            } else
                return parent::url() . '&doc=factura&id=' . $this->documento->idfactura . $extra;
        } else
            return parent::url();
    }

    private function asignar()
    {
        $at0 = new articulo_traza();
        $ok = TRUE;

        /// añadimos números de serie
        foreach ($this->documento->get_lineas() as $lindoc) {
            $cantidad = 0;

            if (isset($_POST['idtraza_' . $lindoc->idlinea])) {
                foreach ($_POST['idtraza_' . $lindoc->idlinea] as $id) {
                    $traza = $at0->get($id);
                    if ($traza) {
                        $traza->fecha_salida = $this->documento->fecha;

                        if (get_class_name($this->documento) == 'albaran_cliente') {
                            $traza->idlalbventa = $lindoc->idlinea;
                        } else {
                            $traza->idlfacventa = $lindoc->idlinea;
                        }

                        if ($cantidad >= $lindoc->cantidad) {
                            break; /// no se pueden asignar más números de serie que cantidad tiene la línea
                        } else if ($traza->save()) {
                            $cantidad++;
                        } else {
                            $this->new_error_msg('Error al asignar el lote o número de serie.');
                            $ok = FALSE;
                        }
                    } else {
                        $this->new_error_msg('Traza no encontrada.');
                        $ok = FALSE;
                    }
                }
            }
        }

        /// eliminamos números de serie
        if (isset($_POST['delete_traza'])) {
            foreach ($_POST['delete_traza'] as $id) {
                $at = $at0->get($id);
                if ($at) {
                    $at->fecha_salida = NULL;
                    if (get_class_name($this->documento) == 'albaran_cliente') {
                        $at->idlalbventa = NULL;
                    } else {
                        $at->idlfacventa = NULL;
                    }
                    $at->save();
                }
            }
        }

        if ($ok) {
            if ($this->tab) {
                $this->new_message('Datos guardados correctamente.');
            } else if ($this->volver) {
                header('Location: ' . $this->volver);
            } else {
                header('Location: ' . $this->documento->url());
            }
        }
    }

    private function get_lineas()
    {
        $art0 = new articulo();
        $at0 = new articulo_traza();
        $this->disponibles = array();
        $this->lineas = array();

        /// ¿Existen ya las lineas de trazabilidad para este documento?
        foreach ($this->documento->get_lineas() as $lindoc) {
            if ($lindoc->referencia) {
                $articulo = $art0->get($lindoc->referencia);
                if ($articulo) {
                    if ($articulo->trazabilidad) {
                        $num = 0;
                        if (get_class_name($this->documento) == 'albaran_cliente') {
                            foreach ($at0->all_from_linea('idlalbventa', $lindoc->idlinea) as $traza) {
                                $this->lineas[] = $traza;
                                $num++;
                            }
                        } else {
                            /// primero comprobamos albaranes previos
                            if ($lindoc->idlineaalbaran) {
                                foreach ($at0->all_from_linea('idlalbventa', $lindoc->idlineaalbaran) as $traza) {
                                    if (is_null($traza->idlfacventa)) {
                                        $traza->idlfacventa = $lindoc->idlinea;
                                        $traza->save();
                                    }
                                }
                            }

                            /// ahora comprobamos la factura
                            foreach ($at0->all_from_linea('idlfacventa', $lindoc->idlinea) as $traza) {
                                $this->lineas[] = $traza;
                                $num++;
                            }
                        }

                        if ($num < $lindoc->cantidad) {
                            $this->disponibles[$articulo->referencia] = array(
                                'id' => $lindoc->idlinea,
                                'cantidad' => $lindoc->cantidad - $num,
                                'trazas' => $at0->all_from_ref($articulo->referencia, TRUE)
                            );
                        }
                    }
                }
            }
        }
    }
}
