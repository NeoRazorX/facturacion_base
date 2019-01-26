<?php
/**
 * This file is part of facturacion_base
 * Copyright (C) 2016-2019 Carlos Garcia Gomez <neorazorx@gmail.com>
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

/**
 * Description of ventas_factura_devolucion
 *
 * @author Carlos Garcia Gomez
 */
class ventas_factura_devolucion extends fbase_controller
{

    /**
     *
     * @var factura_cliente|bool
     */
    public $factura;

    /**
     *
     * @var serie
     */
    public $serie;

    public function __construct()
    {
        parent::__construct(__CLASS__, 'Devoluciones de factura de venta', 'ventas', FALSE, FALSE);
    }

    protected function private_core()
    {
        $this->share_extension();
        $this->template = 'tab/' . __CLASS__;

        $this->serie = new serie();

        $fact0 = new factura_cliente();
        $this->factura = FALSE;
        if (isset($_REQUEST['id'])) {
            $this->factura = $fact0->get($_REQUEST['id']);
        }

        if ($this->factura) {
            if (isset($_POST['id'])) {
                $this->nueva_rectificativa();
            }
        } else {
            $this->new_error_msg('Factura no encontrada.', 'error', FALSE, FALSE);
        }
    }

    private function nueva_rectificativa()
    {
        $continuar = TRUE;

        $eje0 = new ejercicio();
        $ejercicio = $eje0->get_by_fecha($_POST['fecha']);
        if (!$ejercicio) {
            $this->new_error_msg('Ejercicio no encontrado o está cerrado.');
            $continuar = FALSE;
        }

        if ($continuar) {
            $frec = clone $this->factura;
            $frec->idfactura = NULL;
            $frec->numero = NULL;
            $frec->numero2 = NULL;
            $frec->codigo = NULL;
            $frec->idasiento = NULL;
            $frec->idfacturarect = $this->factura->idfactura;
            $frec->codigorect = $this->factura->codigo;
            $frec->codejercicio = $ejercicio->codejercicio;
            $frec->codserie = $_POST['codserie'];
            $frec->set_fecha_hora($_POST['fecha'], $this->hour());
            $frec->observaciones = $_POST['motivo'];
            $frec->femail = NULL;
            $frec->numdocs = NULL;

            $frec->irpf = 0.0;
            $frec->neto = 0.0;
            $frec->netosindto = 0.0;
            $frec->total = 0.0;
            $frec->totalirpf = 0.0;
            $frec->totaliva = 0.0;
            $frec->totalrecargo = 0.0;

            $guardar = FALSE;
            foreach ($this->factura->get_lineas() as $value) {
                if (isset($_POST['devolver_' . $value->idlinea]) && floatval($_POST['devolver_' . $value->idlinea]) > 0) {
                    $guardar = TRUE;
                    break;
                }
            }

            /// función auxiliar para implementar en los plugins que lo necesiten
            fs_generar_numero2($frec);

            if ($guardar) {
                if ($frec->save()) {
                    $art0 = new articulo();

                    foreach ($this->factura->get_lineas() as $value) {
                        if (isset($_POST['devolver_' . $value->idlinea]) && floatval($_POST['devolver_' . $value->idlinea]) > 0) {
                            $linea = clone $value;
                            $linea->idlinea = NULL;
                            $linea->idfactura = $frec->idfactura;
                            $linea->idalbaran = NULL;
                            $linea->cantidad = 0 - floatval($_POST['devolver_' . $value->idlinea]);
                            $linea->pvpsindto = $linea->cantidad * $linea->pvpunitario;

                            // Descuento Unificado Equivalente
                            $due_linea = $this->fbase_calc_due(array($linea->dtopor, $linea->dtopor2, $linea->dtopor3, $linea->dtopor4));
                            $linea->pvptotal = $linea->cantidad * $linea->pvpunitario * $due_linea;

                            if ($linea->save()) {
                                $articulo = $art0->get($linea->referencia);
                                if ($articulo) {
                                    $articulo->sum_stock($frec->codalmacen, 0 - $linea->cantidad, FALSE, $linea->codcombinacion);
                                }

                                if ($linea->irpf > $frec->irpf) {
                                    $frec->irpf = $linea->irpf;
                                }
                            }
                        }
                    }

                    /// obtenemos los subtotales por impuesto
                    $due_totales = $this->fbase_calc_due([$frec->dtopor1, $frec->dtopor2, $frec->dtopor3, $frec->dtopor4, $frec->dtopor5]);
                    foreach ($this->fbase_get_subtotales_documento($frec->get_lineas(), $due_totales) as $subt) {
                        $frec->netosindto += $subt['netosindto'];
                        $frec->neto += $subt['neto'];
                        $frec->totaliva += $subt['iva'];
                        $frec->totalirpf += $subt['irpf'];
                        $frec->totalrecargo += $subt['recargo'];
                    }

                    $frec->total = round($frec->neto + $frec->totaliva - $frec->totalirpf + $frec->totalrecargo, FS_NF0);
                    $frec->pagada = TRUE;

                    if ($frec->save()) {
                        $this->generar_asiento($frec);

                        /// Función de ejecución de tareas post guardado correcto de la factura
                        fs_documento_post_save($frec);

                        $this->new_message(FS_FACTURA_RECTIFICATIVA . ' creada correctamente.');
                    }
                } else {
                    $this->new_error_msg('Error al guardar la ' . FS_FACTURA_RECTIFICATIVA);
                }
            } else {
                $this->new_advice('Todas las cantidades a devolver están a 0.');
            }
        }
    }

    /**
     * 
     * @param factura_cliente $factura
     */
    private function generar_asiento(&$factura)
    {
        if ($this->empresa->contintegrada) {
            $asiento_factura = new asiento_factura();
            $asiento_factura->generar_asiento_venta($factura);
            return;
        }

        /// generamos las líneas de IVA de todas formas
        $factura->get_lineas_iva();
    }

    private function share_extension()
    {
        $fsxet = new fs_extension();
        $fsxet->name = 'tab_devoluciones';
        $fsxet->from = __CLASS__;
        $fsxet->to = 'ventas_factura';
        $fsxet->type = 'tab';
        $fsxet->text = '<span class="glyphicon glyphicon-share" aria-hidden="true"></span>'
            . '<span class="hidden-xs">&nbsp; Devoluciones</span>';
        $fsxet->save();

        $fsxet2 = new fs_extension();
        $fsxet2->name = 'tab_editar_factura';
        $fsxet2->from = __CLASS__;
        $fsxet2->to = 'editar_factura';
        $fsxet2->type = 'tab';
        $fsxet2->text = '<span class="glyphicon glyphicon-share" aria-hidden="true"></span>'
            . '<span class="hidden-xs">&nbsp; Devoluciones</span>';
        $fsxet2->save();
    }

    /**
     * Devuelve la suma de las cantidad devueltas para una factura y referencia
     * en sus respectivas facturas rectificativas.
     *
     * @param int    $idfactura
     * @param string $referencia
     *
     * @return int
     */
    public function cantidad_devuelta($idfactura, $referencia)
    {
        $fact = (new factura_cliente())->get($idfactura);
        $devolucion = 0;
        if ($fact) {
            foreach ($fact->get_rectificativas() as $fact_rect) {
                foreach ($fact_rect->get_lineas() as $lin_fact_rect) {
                    if ($lin_fact_rect->referencia === $referencia) {
                        $devolucion += $lin_fact_rect->cantidad;
                    }
                }
            }
        }

        return $devolucion;
    }
}
