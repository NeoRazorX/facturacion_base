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

class ventas_albaran extends fbase_controller
{

    public $agencia;
    public $agente;
    public $albaran;
    public $allow_delete_fac;
    public $almacen;
    public $cliente;
    public $cliente_s;
    public $divisa;
    public $ejercicio;
    public $fabricante;
    public $familia;
    public $forma_pago;
    public $impuesto;
    public $nuevo_albaran_url;
    public $pais;
    public $serie;

    public function __construct()
    {
        parent::__construct(__CLASS__, FS_ALBARAN . ' de cliente', 'ventas', FALSE, FALSE);
    }

    protected function private_core()
    {
        parent::private_core();

        $this->ppage = $this->page->get('ventas_albaranes');
        $this->agente = FALSE;

        $this->agencia = new agencia_transporte();
        $albaran = new albaran_cliente();
        $this->albaran = FALSE;
        $this->almacen = new almacen();
        $this->cliente = new cliente();
        $this->cliente_s = FALSE;
        $this->divisa = new divisa();
        $this->ejercicio = new ejercicio();
        $this->fabricante = new fabricante();
        $this->familia = new familia();
        $this->forma_pago = new forma_pago();
        $this->impuesto = new impuesto();
        $this->nuevo_albaran_url = FALSE;
        $this->pais = new pais();
        $this->serie = new serie();

        /// ¿El usuario tiene permiso para eliminar la factura?
        $this->allow_delete_fac = $this->user->allow_delete_on('ventas_factura');

        /**
         * Comprobamos si el usuario tiene acceso a nueva_venta,
         * necesario para poder añadir líneas.
         */
        if ($this->user->have_access_to('nueva_venta', FALSE)) {
            $nuevoalbp = $this->page->get('nueva_venta');
            if ($nuevoalbp) {
                $this->nuevo_albaran_url = $nuevoalbp->url();
            }
        }

        if (isset($_POST['idalbaran'])) {
            $this->albaran = $albaran->get($_POST['idalbaran']);
            $this->modificar();
        } else if (isset($_GET['id'])) {
            $this->albaran = $albaran->get($_GET['id']);
        }

        if ($this->albaran) {
            $this->page->title = $this->albaran->codigo;

            /// cargamos el agente
            if (!is_null($this->albaran->codagente)) {
                $agente = new agente();
                $this->agente = $agente->get($this->albaran->codagente);
            }

            /// cargamos el cliente
            $this->cliente_s = $this->cliente->get($this->albaran->codcliente);

            /// comprobamos el albarán
            $this->albaran->full_test();

            if (isset($_REQUEST['facturar']) && isset($_REQUEST['petid'])) {
                if ($this->duplicated_petition($_REQUEST['petid'])) {
                    $this->new_error_msg('Petición duplicada. Evita hacer doble clic sobre los botones.');
                } else if (!$this->albaran->ptefactura || !is_null($this->albaran->idfactura)) {
                    $this->new_error_msg('Parece que este ' . FS_ALBARAN . ' ya está facturado.');
                } else {
                    $this->generar_factura();
                }
            }
        } else {
            $this->new_error_msg("¡" . ucfirst(FS_ALBARAN) . " de venta no encontrado!", 'error', FALSE, FALSE);
        }
    }

    public function url()
    {
        if (!isset($this->albaran)) {
            return parent::url();
        } else if ($this->albaran) {
            return $this->albaran->url();
        }

        return $this->page->url();
    }

    private function modificar()
    {
        $this->albaran->observaciones = $_POST['observaciones'];

        /// ¿Es editable o ya ha sido facturado?
        if ($this->albaran->ptefactura) {
            $eje0 = $this->ejercicio->get_by_fecha($_POST['fecha'], FALSE);
            if ($eje0) {
                $this->albaran->fecha = $_POST['fecha'];
                $this->albaran->hora = $_POST['hora'];

                if ($this->albaran->codejercicio != $eje0->codejercicio) {
                    $this->albaran->codejercicio = $eje0->codejercicio;
                    $this->albaran->new_codigo();
                }
            } else {
                $this->new_error_msg('Ningún ejercicio encontrado.');
            }

            /// ¿cambiamos el cliente?
            if ($_POST['cliente'] != $this->albaran->codcliente) {
                $cliente = $this->cliente->get($_POST['cliente']);
                if ($cliente) {
                    $this->albaran->codcliente = $cliente->codcliente;
                    $this->albaran->cifnif = $cliente->cifnif;
                    $this->albaran->nombrecliente = $cliente->razonsocial;
                    $this->albaran->apartado = NULL;
                    $this->albaran->ciudad = NULL;
                    $this->albaran->coddir = NULL;
                    $this->albaran->codpais = NULL;
                    $this->albaran->codpostal = NULL;
                    $this->albaran->direccion = NULL;
                    $this->albaran->provincia = NULL;

                    foreach ($cliente->get_direcciones() as $d) {
                        if ($d->domfacturacion) {
                            $this->albaran->apartado = $d->apartado;
                            $this->albaran->ciudad = $d->ciudad;
                            $this->albaran->coddir = $d->id;
                            $this->albaran->codpais = $d->codpais;
                            $this->albaran->codpostal = $d->codpostal;
                            $this->albaran->direccion = $d->direccion;
                            $this->albaran->provincia = $d->provincia;
                            break;
                        }
                    }
                } else {
                    $this->albaran->codcliente = NULL;
                    $this->albaran->nombrecliente = $_POST['nombrecliente'];
                    $this->albaran->cifnif = $_POST['cifnif'];
                    $this->albaran->coddir = NULL;
                }
            } else {
                $this->albaran->nombrecliente = $_POST['nombrecliente'];
                $this->albaran->cifnif = $_POST['cifnif'];
                $this->albaran->codpais = $_POST['codpais'];
                $this->albaran->provincia = $_POST['provincia'];
                $this->albaran->ciudad = $_POST['ciudad'];
                $this->albaran->codpostal = $_POST['codpostal'];
                $this->albaran->direccion = $_POST['direccion'];
                $this->albaran->apartado = $_POST['apartado'];

                $this->albaran->envio_nombre = $_POST['envio_nombre'];
                $this->albaran->envio_apellidos = $_POST['envio_apellidos'];
                $this->albaran->envio_codtrans = NULL;
                if ($_POST['envio_codtrans'] != '') {
                    $this->albaran->envio_codtrans = $_POST['envio_codtrans'];
                }
                $this->albaran->envio_codigo = $_POST['envio_codigo'];
                $this->albaran->envio_codpais = $_POST['envio_codpais'];
                $this->albaran->envio_provincia = $_POST['envio_provincia'];
                $this->albaran->envio_ciudad = $_POST['envio_ciudad'];
                $this->albaran->envio_codpostal = $_POST['envio_codpostal'];
                $this->albaran->envio_direccion = $_POST['envio_direccion'];
                $this->albaran->envio_apartado = $_POST['envio_apartado'];

                $cliente = $this->cliente->get($this->albaran->codcliente);
            }

            $serie = $this->serie->get($this->albaran->codserie);

            /// ¿cambiamos la serie?
            if ($_POST['serie'] != $this->albaran->codserie) {
                $serie2 = $this->serie->get($_POST['serie']);
                if ($serie2) {
                    $this->albaran->codserie = $serie2->codserie;
                    $this->albaran->new_codigo();

                    $serie = $serie2;
                }
            }

            $this->albaran->codpago = $_POST['forma_pago'];

            /// ¿Cambiamos la divisa?
            if ($_POST['divisa'] != $this->albaran->coddivisa) {
                $divisa = $this->divisa->get($_POST['divisa']);
                if ($divisa) {
                    $this->albaran->coddivisa = $divisa->coddivisa;
                    $this->albaran->tasaconv = $divisa->tasaconv;
                }
            } else if ($_POST['tasaconv'] != '') {
                $this->albaran->tasaconv = floatval($_POST['tasaconv']);
            }

            if (isset($_POST['numlineas'])) {
                $numlineas = intval($_POST['numlineas']);

                $this->albaran->irpf = 0;
                $this->albaran->netosindto = 0;
                $this->albaran->neto = 0;
                $this->albaran->totaliva = 0;
                $this->albaran->totalirpf = 0;
                $this->albaran->totalrecargo = 0;
                $this->albaran->dtopor1 = floatval($_POST['adtopor1']);
                $this->albaran->dtopor2 = floatval($_POST['adtopor2']);
                $this->albaran->dtopor3 = floatval($_POST['adtopor3']);
                $this->albaran->dtopor4 = floatval($_POST['adtopor4']);
                $this->albaran->dtopor5 = floatval($_POST['adtopor5']);

                $lineas = $this->albaran->get_lineas();
                $articulo = new articulo();

                /// eliminamos las líneas que no encontremos en el $_POST
                foreach ($lineas as $l) {
                    $encontrada = FALSE;
                    for ($num = 0; $num <= $numlineas; $num++) {
                        if (isset($_POST['idlinea_' . $num]) && $l->idlinea == intval($_POST['idlinea_' . $num])) {
                            $encontrada = TRUE;
                            break;
                        }
                    }
                    if (!$encontrada) {
                        if ($l->delete()) {
                            /// actualizamos el stock
                            $art0 = $articulo->get($l->referencia);
                            if ($art0) {
                                $art0->sum_stock($this->albaran->codalmacen, $l->cantidad, FALSE, $l->codcombinacion);
                            }
                        } else {
                            $this->new_error_msg("¡Imposible eliminar la línea del artículo " . $l->referencia . "!");
                        }
                    }
                }

                $regimeniva = 'general';
                if ($cliente) {
                    $regimeniva = $cliente->regimeniva;
                }

                /// modificamos y/o añadimos las demás líneas
                for ($num = 0; $num <= $numlineas; $num++) {
                    $encontrada = FALSE;
                    if (isset($_POST['idlinea_' . $num])) {
                        foreach ($lineas as $k => $value) {
                            /// modificamos la línea
                            if ($value->idlinea == intval($_POST['idlinea_' . $num])) {
                                $encontrada = TRUE;
                                $cantidad_old = $value->cantidad;
                                $lineas[$k]->cantidad = floatval($_POST['cantidad_' . $num]);
                                $lineas[$k]->pvpunitario = floatval($_POST['pvp_' . $num]);
                                $lineas[$k]->dtopor = floatval(fs_filter_input_post('dto_' . $num, 0));
                                $lineas[$k]->dtopor2 = floatval(fs_filter_input_post('dto2_' . $num, 0));
                                $lineas[$k]->dtopor3 = floatval(fs_filter_input_post('dto3_' . $num, 0));
                                $lineas[$k]->dtopor4 = floatval(fs_filter_input_post('dto4_' . $num, 0));
                                $lineas[$k]->pvpsindto = $value->cantidad * $value->pvpunitario;

                                // Descuento Unificado Equivalente
                                $due_linea = $this->fbase_calc_due(array($lineas[$k]->dtopor, $lineas[$k]->dtopor2, $lineas[$k]->dtopor3, $lineas[$k]->dtopor4));
                                $lineas[$k]->pvptotal = $lineas[$k]->cantidad * $lineas[$k]->pvpunitario * $due_linea;

                                $lineas[$k]->descripcion = $_POST['desc_' . $num];
                                $lineas[$k]->codimpuesto = NULL;
                                $lineas[$k]->iva = 0;
                                $lineas[$k]->recargo = 0;
                                $lineas[$k]->irpf = floatval(fs_filter_input_post('irpf_' . $num, 0));
                                if (!$serie->siniva && $regimeniva != 'Exento') {
                                    $imp0 = $this->impuesto->get_by_iva($_POST['iva_' . $num]);
                                    if ($imp0) {
                                        $lineas[$k]->codimpuesto = $imp0->codimpuesto;
                                    }

                                    $lineas[$k]->iva = floatval($_POST['iva_' . $num]);
                                    $lineas[$k]->recargo = floatval(fs_filter_input_post('recargo_' . $num, 0));
                                }

                                if ($lineas[$k]->save()) {
                                    if ($value->irpf > $this->albaran->irpf) {
                                        $this->albaran->irpf = $value->irpf;
                                    }

                                    if ($lineas[$k]->cantidad != $cantidad_old) {
                                        /// actualizamos el stock
                                        $art0 = $articulo->get($value->referencia);
                                        if ($art0) {
                                            $art0->sum_stock($this->albaran->codalmacen, $cantidad_old - $lineas[$k]->cantidad, FALSE, $lineas[$k]->codcombinacion);
                                        }
                                    }
                                } else {
                                    $this->new_error_msg("¡Imposible modificar la línea del artículo " . $value->referencia . "!");
                                }

                                break;
                            }
                        }

                        /// añadimos la línea
                        if (!$encontrada && intval($_POST['idlinea_' . $num]) == -1 && isset($_POST['referencia_' . $num])) {
                            $linea = new linea_albaran_cliente();
                            $linea->idalbaran = $this->albaran->idalbaran;
                            $linea->descripcion = $_POST['desc_' . $num];

                            if (!$serie->siniva && $regimeniva != 'Exento') {
                                $imp0 = $this->impuesto->get_by_iva($_POST['iva_' . $num]);
                                if ($imp0) {
                                    $linea->codimpuesto = $imp0->codimpuesto;
                                }

                                $linea->iva = floatval($_POST['iva_' . $num]);
                                $linea->recargo = floatval(fs_filter_input_post('recargo_' . $num, 0));
                            }

                            $linea->irpf = floatval(fs_filter_input_post('irpf_' . $num, 0));
                            $linea->cantidad = floatval($_POST['cantidad_' . $num]);
                            $linea->pvpunitario = floatval($_POST['pvp_' . $num]);
                            $linea->dtopor = floatval(fs_filter_input_post('dto_' . $num, 0));
                            $linea->dtopor2 = floatval(fs_filter_input_post('dto2_' . $num, 0));
                            $linea->dtopor3 = floatval(fs_filter_input_post('dto3_' . $num, 0));
                            $linea->dtopor4 = floatval(fs_filter_input_post('dto4_' . $num, 0));
                            $linea->pvpsindto = $linea->cantidad * $linea->pvpunitario;

                            // Descuento Unificado Equivalente
                            $due_linea = $this->fbase_calc_due(array($linea->dtopor, $linea->dtopor2, $linea->dtopor3, $linea->dtopor4));
                            $linea->pvptotal = $linea->cantidad * $linea->pvpunitario * $due_linea;

                            $art0 = $articulo->get($_POST['referencia_' . $num]);
                            if ($art0) {
                                $linea->referencia = $art0->referencia;
                                if ($_POST['codcombinacion_' . $num]) {
                                    $linea->codcombinacion = $_POST['codcombinacion_' . $num];
                                }
                            }

                            if ($linea->save()) {
                                if ($art0) {
                                    /// actualizamos el stock
                                    $art0->sum_stock($this->albaran->codalmacen, 0 - $linea->cantidad, FALSE, $linea->codcombinacion);
                                }

                                if ($linea->irpf > $this->albaran->irpf) {
                                    $this->albaran->irpf = $linea->irpf;
                                }
                            } else {
                                $this->new_error_msg("¡Imposible guardar la línea del artículo " . $linea->referencia . "!");
                            }
                        }
                    }
                }

                /// obtenemos los subtotales por impuesto
                $due_totales = $this->fbase_calc_due([$this->albaran->dtopor1, $this->albaran->dtopor2, $this->albaran->dtopor3, $this->albaran->dtopor4, $this->albaran->dtopor5]);
                foreach ($this->fbase_get_subtotales_documento($this->albaran->get_lineas(), $due_totales) as $subt) {
                    $this->albaran->netosindto += $subt['netosindto'];
                    $this->albaran->neto += $subt['neto'];
                    $this->albaran->totaliva += $subt['iva'];
                    $this->albaran->totalirpf += $subt['irpf'];
                    $this->albaran->totalrecargo += $subt['recargo'];
                }

                $this->albaran->total = round($this->albaran->neto + $this->albaran->totaliva - $this->albaran->totalirpf + $this->albaran->totalrecargo, FS_NF0);

                if (abs(floatval($_POST['atotal']) - $this->albaran->total) >= .02) {
                    $this->new_error_msg("El total difiere entre el controlador y la vista (" . $this->albaran->total .
                        " frente a " . $_POST['atotal'] . "). Debes informar del error.");
                }
            }
        }

        /// función auxiliar para implementar en los plugins que lo necesiten
        if (!fs_generar_numero2($this->albaran)) {
            $this->albaran->numero2 = $_POST['numero2'];
        }

        if ($this->albaran->save()) {
            /// Función de ejecución de tareas post guardado correcto del albarán
            fs_documento_post_save($this->albaran);

            $this->new_message(ucfirst(FS_ALBARAN) . " modificado correctamente.");
            $this->propagar_cifnif();
            $this->new_change(ucfirst(FS_ALBARAN) . ' Cliente ' . $this->albaran->codigo, $this->albaran->url());
        } else {
            $this->new_error_msg("¡Imposible modificar el " . FS_ALBARAN . "!");
        }
    }

    /**
     * Actualizamos el cif/nif en el cliente y los albaranes de este cliente que no tenga cif/nif
     */
    private function propagar_cifnif()
    {
        if ($this->albaran->cifnif) {
            /// buscamos el cliente
            $cliente = $this->cliente->get($this->albaran->codcliente);
            if ($cliente && !$cliente->cifnif) {
                /// actualizamos el cliente
                $cliente->cifnif = $this->albaran->cifnif;
                if ($cliente->save()) {
                    /// actualizamos albaranes
                    $sql = "UPDATE albaranescli SET cifnif = " . $cliente->var2str($this->albaran->cifnif)
                        . " WHERE codcliente = " . $cliente->var2str($this->albaran->codcliente)
                        . " AND cifnif = '' AND fecha >= " . $cliente->var2str(date('01-01-Y')) . ";";
                    $this->db->exec($sql);

                    /// actualizamos facturas
                    $sql = "UPDATE facturascli SET cifnif = " . $cliente->var2str($this->albaran->cifnif)
                        . " WHERE codcliente = " . $cliente->var2str($this->albaran->codcliente)
                        . " AND cifnif = '' AND fecha >= " . $cliente->var2str(date('01-01-Y')) . ";";
                    $this->db->exec($sql);
                }
            }
        }
    }

    private function generar_factura()
    {
        $this->fbase_facturar_albaran_cliente([$this->albaran], $_REQUEST['facturar'], $_REQUEST['codpago']);
    }
}
