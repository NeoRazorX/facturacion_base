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

class compras_albaran extends fbase_controller
{

    public $agente;
    public $albaran;
    public $allow_delete_fac;
    public $almacen;
    public $divisa;
    public $ejercicio;
    public $fabricante;
    public $familia;
    public $forma_pago;
    public $impuesto;
    public $nuevo_albaran_url;
    public $proveedor;
    public $proveedor_s;
    public $serie;

    public function __construct()
    {
        parent::__construct(__CLASS__, FS_ALBARAN . ' de proveedor', 'compras', FALSE, FALSE);
    }

    protected function private_core()
    {
        parent::private_core();

        $this->ppage = $this->page->get('compras_albaranes');
        $this->agente = FALSE;

        $albaran = new albaran_proveedor();
        $this->albaran = FALSE;
        $this->almacen = new almacen();
        $this->divisa = new divisa();
        $this->ejercicio = new ejercicio();
        $this->fabricante = new fabricante();
        $this->familia = new familia();
        $this->forma_pago = new forma_pago();
        $this->impuesto = new impuesto();
        $this->proveedor = new proveedor();
        $this->proveedor_s = FALSE;
        $this->serie = new serie();

        /// ¿El usuario tiene permiso para eliminar la factura?
        $this->allow_delete_fac = $this->user->allow_delete_on('compras_factura');

        /// comprobamos si el usuario tiene acceso a nueva_compra
        $this->nuevo_albaran_url = FALSE;
        if ($this->user->have_access_to('nueva_compra', FALSE)) {
            $nuevoalbp = $this->page->get('nueva_compra');
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

            /// cargamos el proveedor
            $this->proveedor_s = $this->proveedor->get($this->albaran->codproveedor);

            /// comprobamos el albarán
            $this->albaran->full_test();

            if (isset($_POST['facturar']) && isset($_POST['petid']) && $this->albaran->ptefactura) {
                if ($this->duplicated_petition($_POST['petid'])) {
                    $this->new_error_msg('Petición duplicada. Evita hacer doble clic sobre los botones.');
                } else {
                    $this->generar_factura();
                }
            }
        } else {
            $this->new_error_msg("¡" . ucfirst(FS_ALBARAN) . " de compra no encontrado!", 'error', FALSE, FALSE);
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

        /// ¿El albarán es editable o ya ha sido facturado?
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

            /// ¿Cambiamos el proveedor?
            if ($_POST['proveedor'] != $this->albaran->codproveedor) {
                $proveedor = $this->proveedor->get($_POST['proveedor']);
                if ($proveedor) {
                    $this->albaran->codproveedor = $proveedor->codproveedor;
                    $this->albaran->nombre = $proveedor->razonsocial;
                    $this->albaran->cifnif = $proveedor->cifnif;
                } else {
                    $this->albaran->codproveedor = NULL;
                    $this->albaran->nombre = $_POST['nombre'];
                    $this->albaran->cifnif = $_POST['cifnif'];
                }
            } else {
                $this->albaran->nombre = $_POST['nombre'];
                $this->albaran->cifnif = $_POST['cifnif'];
                $proveedor = $this->proveedor->get($this->albaran->codproveedor);
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
                    $this->albaran->tasaconv = $divisa->tasaconv_compra;
                }
            } else if ($_POST['tasaconv'] != '') {
                $this->albaran->tasaconv = floatval($_POST['tasaconv']);
            }

            if (isset($_POST['numlineas'])) {
                $numlineas = intval($_POST['numlineas']);

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
                                $art0->sum_stock($this->albaran->codalmacen, 0 - $l->cantidad, TRUE, $l->codcombinacion);
                            }
                        } else {
                            $this->new_error_msg("¡Imposible eliminar la línea del artículo " . $l->referencia . "!");
                        }
                    }
                }

                $regimeniva = 'general';
                if ($proveedor) {
                    $regimeniva = $proveedor->regimeniva;
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
                                $lineas[$k]->pvpsindto = $value->cantidad * $value->pvpunitario;
                                $lineas[$k]->pvptotal = $value->cantidad * $value->pvpunitario * (100 - $value->dtopor) / 100;
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
                                            $art0->sum_stock($this->albaran->codalmacen, $lineas[$k]->cantidad - $cantidad_old, TRUE, $lineas[$k]->codcombinacion);
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
                            $linea = new linea_albaran_proveedor();
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
                            $linea->pvpsindto = $linea->cantidad * $linea->pvpunitario;
                            $linea->pvptotal = $linea->cantidad * $linea->pvpunitario * (100 - $linea->dtopor) / 100;

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
                                    $art0->sum_stock($this->albaran->codalmacen, $linea->cantidad, TRUE, $linea->codcombinacion);
                                    $this->actualizar_precio_proveedor($this->albaran->codproveedor, $linea);
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
                foreach ($this->fbase_get_subtotales_documento($this->albaran->get_lineas()) as $subt) {
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
        if (!fs_generar_numproveedor($this->albaran)) {
            $this->albaran->numproveedor = $_POST['numproveedor'];
        }

        if ($this->albaran->save()) {
            /// Función de ejecución de tareas post guardado correcto del albarán
            fs_documento_post_save($this->albaran);

            $this->new_message(ucfirst(FS_ALBARAN) . " modificado correctamente.");
            $this->new_change(ucfirst(FS_ALBARAN) . ' Proveedor ' . $this->albaran->codigo, $this->albaran->url());
        } else {
            $this->new_error_msg("¡Imposible modificar el " . FS_ALBARAN . "!");
        }
    }

    private function generar_factura()
    {
        $this->fbase_facturar_albaran_proveedor([$this->albaran], $_POST['facturar']);
    }

    /**
     * Actualiza los datos de artículos de proveedor en base a las líneas del documento de compra.
     * @param string $codproveedor
     * @param linea_albaran_proveedor $linea
     */
    private function actualizar_precio_proveedor($codproveedor, $linea)
    {
        if (!is_null($linea->referencia)) {
            $artp0 = new articulo_proveedor();
            $artp = $artp0->get_by($linea->referencia, $codproveedor, $linea->referencia);
            if (!$artp) {
                $artp = new articulo_proveedor();
                $artp->codproveedor = $codproveedor;
                $artp->referencia = $linea->referencia;
                $artp->refproveedor = $linea->referencia;
                $artp->codimpuesto = $linea->codimpuesto;
                $artp->descripcion = $linea->descripcion;
            }

            if ($artp->referencia == $linea->referencia) {
                $artp->precio = $linea->pvpunitario;
                $artp->dto = $linea->dtopor;
                $artp->save();
            }
        }
    }
}
