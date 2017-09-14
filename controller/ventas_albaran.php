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
        $this->agencia = new agencia_transporte();

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
        $netos = array();
        $netosdto = array();
        $ivas = array();
        $irpfs = array();
        $recargos = array();
        $netosindto = 0;
        $netocondto = 0;
        $neto = 0;
        $iva = 0;
        $irpf = 0;
        $recargo = 0;
        
        $error = FALSE;
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

                $this->albaran->netosindto = 0;
                $this->albaran->dtopor1 = 0;
                $this->albaran->dtopor2 = 0;
                $this->albaran->dtopor3 = 0;
                $this->albaran->dtopor4 = 0;
                $this->albaran->dtopor5 = 0;
                $this->albaran->neto = 0;
                $this->albaran->totaliva = 0;
                $this->albaran->totalirpf = 0;
                $this->albaran->totalrecargo = 0;
                $this->albaran->irpf = 0;

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
                                $lineas[$k]->orden = $num;
                                $lineas[$k]->cantidad = floatval($_POST['cantidad_' . $num]);
                                $lineas[$k]->pvpunitario = floatval($_POST['pvp_' . $num]);
                                $lineas[$k]->dtopor = floatval($_POST['dto_' . $num]);
                                $lineas[$k]->dtopor2 = floatval($_POST['dto2_' . $num]);
                                $lineas[$k]->dtopor3 = floatval($_POST['dto3_' . $num]);
                                $lineas[$k]->dtopor4 = floatval($_POST['dto4_' . $num]);
                                $lineas[$k]->pvpsindto = $value->cantidad * $value->pvpunitario;
                                // Descuento Unificado Equivalente
                                $due_linea = $this->calc_due(array($lineas[$k]->dtopor,$lineas[$k]->dtopor2,$lineas[$k]->dtopor3,$lineas[$k]->dtopor4));
                                $lineas[$k]->pvptotal = $lineas[$k]->cantidad * $lineas[$k]->pvpunitario * $due_linea;
                                $lineas[$k]->descripcion = $_POST['desc_' . $num];

                                $lineas[$k]->codimpuesto = NULL;
                                $lineas[$k]->iva = 0;
                                $lineas[$k]->recargo = 0;
                                $lineas[$k]->irpf = floatval($_POST['irpf_' . $num]);
                                if (!$serie->siniva && $regimeniva != 'Exento') {
                                    $imp0 = $this->impuesto->get_by_iva($_POST['iva_' . $num]);
                                    if ($imp0) {
                                        $lineas[$k]->codimpuesto = $imp0->codimpuesto;
                                    }

                                    $lineas[$k]->iva = floatval($_POST['iva_' . $num]);
                                    $lineas[$k]->recargo = floatval($_POST['recargo_' . $num]);
                                }

                                if ($lineas[$k]->save()) {
                                    if (!array_key_exists($lineas[$k]->codimpuesto, $netos)) {
                                        $netos[$lineas[$k]->codimpuesto] = 0;
                                        $netosdto[$lineas[$k]->codimpuesto] = 0;
                                        $ivas[$lineas[$k]->codimpuesto] = 0;
                                        $irpfs[$lineas[$k]->codimpuesto] = 0;
                                        $recargos[$lineas[$k]->codimpuesto] = 0;
                                    }
                                    $this->albaran->dtopor1 = floatval($_POST['adtopor1']);
                                    $this->albaran->dtopor2 = floatval($_POST['adtopor2']);
                                    $this->albaran->dtopor3 = floatval($_POST['adtopor3']);
                                    $this->albaran->dtopor4 = floatval($_POST['adtopor4']);
                                    $this->albaran->dtopor5 = floatval($_POST['adtopor5']);
                                    // Acumulamos por tipos de IVAs, que es el desglose de pie de página
                                    
                                    // Descuento Unificado Equivalente
                                    $due_totales = $this->calc_due(array($this->albaran->dtopor1,$this->albaran->dtopor2,$this->albaran->dtopor3,$this->albaran->dtopor4,$this->albaran->dtopor5));
                                    // Hacemos el recalculo del PVP por línea, con el descuento adicional de fin de documento
                                    $pvpcondto = $due_totales * $lineas[$k]->pvptotal;
                                    
                                    // Netos
                                    $netos[$lineas[$k]->codimpuesto] += $lineas[$k]->pvptotal;
                                    // Bases
                                    $netosdto[$lineas[$k]->codimpuesto] += $pvpcondto;
                                    // IVA
                                    $ivas[$lineas[$k]->codimpuesto] += $pvpcondto * ($lineas[$k]->iva /100);
                                    // IRPF
                                    $irpfs[$lineas[$k]->codimpuesto] += $pvpcondto * ($lineas[$k]->irpf /100);
                                    // RE
                                    $recargos[$lineas[$k]->codimpuesto] += $pvpcondto * ($lineas[$k]->recargo /100);

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
                                $linea->recargo = floatval($_POST['recargo_' . $num]);
                            }

                            $linea->irpf = floatval($_POST['irpf_' . $num]);
                            $linea->orden = $num;
                            $linea->cantidad = floatval($_POST['cantidad_' . $num]);
                            $linea->pvpunitario = floatval($_POST['pvp_' . $num]);
                            $linea->dtopor = floatval($_POST['dto_' . $num]);
                            $linea->dtopor2 = floatval($_POST['dto2_' . $num]);
                            $linea->dtopor3 = floatval($_POST['dto3_' . $num]);
                            $linea->dtopor4 = floatval($_POST['dto4_' . $num]);
                            $linea->pvpsindto = $linea->cantidad * $linea->pvpunitario;
                            $l_dto_due = (1-((1-$linea->dtopor/100)*(1-$linea->dtopor2/100)*(1-$linea->dtopor3/100)*(1-$linea->dtopor4/100)))*100;
                            $due_lineas = (1-$l_dto_due / 100);
                            $linea->pvptotal = $linea->cantidad * $linea->pvpunitario * $due_lineas;

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

                                if (!array_key_exists($linea->codimpuesto, $netos)) {
                                    // Neto
                                    $netos[$linea->codimpuesto] = 0;
                                    // Base
                                    $netosdto[$linea->codimpuesto] = 0;
                                    // IVA
                                    $ivas[$linea->codimpuesto] = 0;
                                    // IRPF
                                    $irpfs[$linea->codimpuesto] = 0;
                                    // RE
                                    $recargos[$linea->codimpuesto] = 0;
                                }

                                $this->albaran->netosindto += $netosindto;
                                $this->albaran->dtopor1 = floatval($_POST['adtopor1']);
                                $this->albaran->dtopor2 = floatval($_POST['adtopor2']);
                                $this->albaran->dtopor3 = floatval($_POST['adtopor3']);
                                $this->albaran->dtopor4 = floatval($_POST['adtopor4']);
                                $this->albaran->dtopor5 = floatval($_POST['adtopor5']);
                                // Acumulamos por tipos de IVAs, que es el desglose de pie de página

                                // Descuento Unificado Equivalente
                                $due_totales = $this->calc_due(array($this->albaran->dtopor1,$this->albaran->dtopor2,$this->albaran->dtopor3,$this->albaran->dtopor4,$this->albaran->dtopor5));
                                // Hacemos el recalculo del PVP por línea, con el descuento adicional de fin de documento
                                $pvpcondto = $due_totales * $linea->pvptotal;

                                // Neto
                                $netos[$linea->codimpuesto] += $linea->pvptotal;
                                // Base
                                $netosdto[$linea->codimpuesto] += $pvpcondto;
                                // IVA
                                $ivas[$linea->codimpuesto] += $pvpcondto * ($linea->iva /100);
                                // IRPF
                                $irpfs[$linea->codimpuesto] += $pvpcondto * ($linea->irpf /100);
                                // RE
                                $recargos[$linea->codimpuesto] += $pvpcondto * ($linea->recargo /100);

                                if ($linea->irpf > $this->albaran->irpf) {
                                    $this->albaran->irpf = $linea->irpf;
                                }
                            } else {
                                $this->new_error_msg("¡Imposible guardar la línea del artículo " . $linea->referencia . "!");
                            }
                        }
                    }
                }
                
                foreach ($netos as $pos => $ne) {
                    // Neto total de la línea (Neto)
                    $netosindto += $netos[$pos];
                    // Neto total de la línea, con el descuento total del documento (Base imponible)
                    $netocondto += $netosdto[$pos];
                    $iva += $ivas[$pos];
                    $irpf += $irpfs[$pos];
                    $recargo += $recargos[$pos];
                }

                /// redondeamos
                $this->albaran->netosindto = $netosindto;
                $this->albaran->neto = $netocondto;
                $this->albaran->totaliva = $iva;
                $this->albaran->totalirpf = $irpf;
                $this->albaran->totalrecargo = $recargo;
                $this->albaran->total = $this->albaran->neto + $this->albaran->totaliva - $this->albaran->totalirpf + $this->albaran->totalrecargo;

                if (abs(floatval($_POST['atotal']) - $this->albaran->total) >= .02) {
                    $this->new_error_msg("El total difiere entre el controlador y la vista (" . $this->albaran->total .
                        " frente a " . $_POST['atotal'] . "). Debes informar del error.");
                }
            }
        }

        fs_generar_numero2($this->albaran);

        if ($this->albaran->save()) {
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
        $factura = new factura_cliente();
        $factura->apartado = $this->albaran->apartado;
        $factura->cifnif = $this->albaran->cifnif;
        $factura->ciudad = $this->albaran->ciudad;
        $factura->codagente = $this->albaran->codagente;
        $factura->codalmacen = $this->albaran->codalmacen;
        $factura->codcliente = $this->albaran->codcliente;
        $factura->coddir = $this->albaran->coddir;
        $factura->coddivisa = $this->albaran->coddivisa;
        $factura->tasaconv = $this->albaran->tasaconv;
        $factura->codpago = $this->albaran->codpago;
        $factura->codpais = $this->albaran->codpais;
        $factura->codpostal = $this->albaran->codpostal;
        $factura->codserie = $this->albaran->codserie;
        $factura->direccion = $this->albaran->direccion;
        $factura->netosindto = $this->albaran->netosindto;
        $factura->dtopor1 = $this->albaran->dtopor1;
        $factura->dtopor2 = $this->albaran->dtopor2;
        $factura->dtopor3 = $this->albaran->dtopor3;
        $factura->dtopor4 = $this->albaran->dtopor4;
        $factura->dtopor5 = $this->albaran->dtopor5;
        $factura->neto = $this->albaran->neto;
        $factura->nombrecliente = $this->albaran->nombrecliente;
        $factura->observaciones = $this->albaran->observaciones;
        $factura->provincia = $this->albaran->provincia;
        $factura->envio_apartado = $this->albaran->envio_apartado;
        $factura->envio_apellidos = $this->albaran->envio_apellidos;
        $factura->envio_ciudad = $this->albaran->envio_ciudad;
        $factura->envio_codigo = $this->albaran->envio_codigo;
        $factura->envio_codpais = $this->albaran->envio_codpais;
        $factura->envio_codpostal = $this->albaran->envio_codpostal;
        $factura->envio_codtrans = $this->albaran->envio_codtrans;
        $factura->envio_direccion = $this->albaran->envio_direccion;
        $factura->envio_nombre = $this->albaran->envio_nombre;
        $factura->envio_provincia = $this->albaran->envio_provincia;
        $factura->total = $this->albaran->total;
        $factura->totaliva = $this->albaran->totaliva;
        $factura->numero2 = $this->albaran->numero2;
        $factura->irpf = $this->albaran->irpf;
        $factura->totalirpf = $this->albaran->totalirpf;
        $factura->totalrecargo = $this->albaran->totalrecargo;
        $factura->porcomision = $this->albaran->porcomision;

        /// comprobamos si se ha cambiado la forma de pago
        if (isset($_REQUEST['codpago'])) {
            $factura->codpago = $_REQUEST['codpago'];
        }

        if (is_null($factura->codagente)) {
            $factura->codagente = $this->user->codagente;
        }

        /// asignamos el ejercicio que corresponde a la fecha elegida
        $eje0 = $this->ejercicio->get_by_fecha($_REQUEST['facturar']);
        if ($eje0) {
            $factura->codejercicio = $eje0->codejercicio;
            $factura->set_fecha_hora($_REQUEST['facturar'], $factura->hora);
        }

        /// comprobamos la forma de pago para saber si hay que marcar la factura como pagada
        $forma0 = new forma_pago();
        $formapago = $forma0->get($factura->codpago);
        if ($formapago) {
            if ($formapago->genrecibos == 'Pagados') {
                $factura->pagada = TRUE;
            }

            if ($this->cliente_s) {
                $factura->vencimiento = $formapago->calcular_vencimiento($factura->fecha, $this->cliente_s->diaspago);
            } else {
                $factura->vencimiento = $formapago->calcular_vencimiento($factura->fecha);
            }
        }

        fs_generar_numero2($factura);

        $regularizacion = new regularizacion_iva();

        if (!$eje0) {
            $this->new_error_msg("Ejercicio no encontrado o está cerrado.");
        } else if (!$eje0->abierto()) {
            $this->new_error_msg("El ejercicio está cerrado.");
        } else if ($regularizacion->get_fecha_inside($factura->fecha)) {
            $this->new_error_msg("El " . FS_IVA . " de ese periodo ya ha sido regularizado. No se pueden añadir más facturas en esa fecha.");
        } else if ($factura->save()) {
            $continuar = TRUE;
            foreach ($this->albaran->get_lineas() as $l) {
                $n = new linea_factura_cliente();
                $n->idalbaran = $l->idalbaran;
                $n->idlineaalbaran = $l->idlinea;
                $n->idfactura = $factura->idfactura;
                $n->cantidad = $l->cantidad;
                $n->codimpuesto = $l->codimpuesto;
                $n->descripcion = $l->descripcion;
                $n->dtopor = $l->dtopor;
                $n->dtopor2 = $l->dtopor2;
                $n->dtopor3 = $l->dtopor3;
                $n->dtopor4 = $l->dtopor4;
                $n->irpf = $l->irpf;
                $n->iva = $l->iva;
                $n->pvpsindto = $l->pvpsindto;
                $n->pvptotal = $l->pvptotal;
                $n->pvpunitario = $l->pvpunitario;
                $n->recargo = $l->recargo;
                $n->referencia = $l->referencia;
                $n->codcombinacion = $l->codcombinacion;
                $n->orden = $l->orden;
                $n->mostrar_cantidad = $l->mostrar_cantidad;
                $n->mostrar_precio = $l->mostrar_precio;

                if (!$n->save()) {
                    $continuar = FALSE;
                    $this->new_error_msg("¡Imposible guardar la línea el artículo " . $n->referencia . "! ");
                    break;
                }
            }

            if ($continuar) {
                $this->albaran->idfactura = $factura->idfactura;
                $this->albaran->ptefactura = FALSE;
                if ($this->albaran->save()) {
                    $this->generar_asiento($factura);
                    fs_documento_post_save($factura);
                } else {
                    $this->new_error_msg("¡Imposible vincular el " . FS_ALBARAN . " con la nueva factura!");
                    if ($factura->delete()) {
                        $this->new_error_msg("La factura se ha borrado.");
                    } else {
                        $this->new_error_msg("¡Imposible borrar la factura!");
                    }
                }
            } else {
                if ($factura->delete()) {
                    $this->new_error_msg("La factura se ha borrado.");
                } else {
                    $this->new_error_msg("¡Imposible borrar la factura!");
                }
            }
        } else {
            $this->new_error_msg("¡Imposible guardar la factura!");
        }
    }

    private function generar_asiento(&$factura)
    {
        if ($this->empresa->contintegrada) {
            $asiento_factura = new asiento_factura();
            if ($asiento_factura->generar_asiento_venta($factura)) {
                $this->new_message("<a href='" . $factura->url() . "'>Factura</a> generada correctamente.");
            }

            foreach ($asiento_factura->errors as $err) {
                $this->new_error_msg($err);
            }

            foreach ($asiento_factura->messages as $msg) {
                $this->new_message($msg);
            }
        } else {
            $this->new_message("<a href='" . $factura->url() . "'>Factura</a> generada correctamente.");
        }

        $this->new_change('Factura ' . $factura->codigo, $factura->url(), TRUE);
    }
}
