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

/**
 * Esta clase permite genera un asiento a partir de una factura.
 *
 * @author Carlos García Gómez <neorazorx@gmail.com>
 */
abstract class fbase_asiento_factura
{

    /**
     *
     * @var articulo
     */
    protected $articulo;

    /**
     *
     * @var articulo_propiedad
     */
    protected $articulo_propiedad;

    /**
     *
     * @var bool
     */
    protected $articulo_propiedad_vacio;

    /**
     *
     * @var asiento|false
     */
    public $asiento;

    /**
     *
     * @var fs_core_log
     */
    protected $core_log;

    /**
     *
     * @var cuenta_banco
     */
    protected $cuenta_banco;

    /**
     *
     * @var divisa
     */
    protected $divisa;

    /**
     *
     * @var ejercicio
     */
    protected $ejercicio;

    /**
     *
     * @var empresa
     */
    protected $empresa;

    /**
     *
     * @var forma_pago
     */
    protected $forma_pago;

    /**
     *
     * @var impuesto[]
     */
    protected $impuestos;

    /**
     *
     * @var bool
     */
    public $soloasiento;

    /**
     *
     * @var subcuenta
     */
    protected $subcuenta;

    abstract public function generar_asiento_compra(&$factura);

    abstract public function generar_asiento_pago(&$asiento, $codpago = false, $fecha = false, $subclipro = false, $importe = NULL);

    abstract public function generar_asiento_venta(&$factura);

    abstract public function invertir_asiento(&$asiento);

    public function __construct()
    {
        $this->articulo = new articulo();

        /**
         * Las subcuentas personalizadas para cada artículo se almacenan en articulo_propiedad.
         * Para optimizar comprobamos si la tabla está vacía, así no hacemos consultas innecesarias.
         */
        $this->articulo_propiedad = new articulo_propiedad();
        $this->articulo_propiedad_vacio = TRUE;
        foreach ($this->articulo_propiedad->all() as $ap) {
            $this->articulo_propiedad_vacio = false;
            break;
        }

        $this->asiento = false;
        $this->core_log = new fs_core_log();
        $this->cuenta_banco = new cuenta_banco();
        $this->divisa = new divisa();
        $this->ejercicio = new ejercicio();
        $this->empresa = new empresa();
        $this->forma_pago = new forma_pago();

        /// Pre-cargamos todos los impuestos para optimizar.
        $impuesto = new impuesto();
        $this->impuestos = [];
        foreach ($impuesto->all() as $imp) {
            $this->impuestos[$imp->codimpuesto] = $imp;
        }

        $this->soloasiento = false;
        $this->subcuenta = new subcuenta();
    }

    /**
     * Comprueba la validez de un asiento contable
     *
     * @param asiento $asiento
     *
     * @return bool
     */
    protected function check_asiento($asiento)
    {
        $debe = 0;
        $haber = 0;
        foreach ($asiento->get_partidas() as $lin) {
            $debe += $lin->debe;
            $haber += $lin->haber;
        }

        return abs($debe - $haber) < .01;
    }

    /**
     * 
     * @param object    $factura
     * @param asiento   $asiento
     * @param partida   $partida2
     * @param subcuenta $subcuenta_compras
     * @param float     $tasaconv
     * @param float     $tasaconv2
     */
    protected function comprobar_subc_art_compra(&$factura, &$asiento, &$partida2, &$subcuenta_compras, $tasaconv, $tasaconv2)
    {
        $partidaA = $asiento->get_new_partida('', '');
        $partidaA->coddivisa = $this->empresa->coddivisa;
        $partidaA->tasaconv = $tasaconv2;

        /// importe a restar a la partida2
        $restar = 0;

        /**
         * Para cada artículo de la factura, buscamos su subcuenta de compra o compra con irpf
         */
        foreach ($factura->get_lineas() as $lin) {
            $articulo = $lin->referencia ? $this->articulo->get($lin->referencia) : false;
            if (empty($articulo)) {
                continue;
            }

            $subcart = $articulo->codsubcuentacom ? $this->subcuenta->get_by_codigo($articulo->codsubcuentacom, $factura->codejercicio) : false;
            if (!$subcart || $subcart->idsubcuenta == $subcuenta_compras->idsubcuenta) {
                /// no hay / no se encuentra ninguna subcuenta asignada al artículo / o es la misma
                continue;
            }

            if (is_null($partidaA->idsubcuenta)) {
                $partidaA->idsubcuenta = $subcart->idsubcuenta;
                $partidaA->codsubcuenta = $subcart->codsubcuenta;
                $partidaA->debe = $lin->pvptotal * $tasaconv;
            } else if ($partidaA->idsubcuenta == $subcart->idsubcuenta) {
                $partidaA->debe += $lin->pvptotal * $tasaconv;
            } else {
                $partidaA->debe = round($partidaA->debe, FS_NF0);
                $restar += $partidaA->debe;
                if (!$partidaA->save()) {
                    $this->core_log->new_error("¡Imposible generar la partida para la subcuenta del artículo "
                        . $lin->referencia . "!");
                    return false;
                }

                $partidaA = $asiento->get_new_partida($subcart->codsubcuenta, '');
                $partidaA->debe = $lin->pvptotal * $tasaconv;
                $partidaA->tasaconv = $tasaconv2;
            }
        }

        if ($partidaA->idsubcuenta && $partidaA->codsubcuenta) {
            $partidaA->debe = round($partidaA->debe, FS_NF0);
            $restar += $partidaA->debe;
            if (!$partidaA->save()) {
                $this->core_log->new_error("¡Imposible generar la partida para la subcuenta " . $partidaA->codsubcuenta . "!");
                return false;
            }

            $partida2->debe -= $restar;
            if ($partida2->debe == 0) {
                $partida2->delete();
            } else {
                $partida2->save();
            }
        }

        return true;
    }

    /**
     * 
     * @param factura_proveedor $factura
     * @param asiento           $asiento
     * @param partida           $partida3
     * @param subcuenta         $subcuenta_irpf
     * @param float             $tasaconv
     * @param float             $tasaconv2
     */
    protected function comprobar_subc_art_compra_irpf(&$factura, &$asiento, &$partida3, &$subcuenta_irpf, $tasaconv, $tasaconv2)
    {
        $partidaA = $asiento->get_new_partida('', '');
        $partidaA->coddivisa = $this->empresa->coddivisa;
        $partidaA->tasaconv = $tasaconv2;

        /// importe a restar a la partida2
        $restar = 0;

        /**
         * Para cada artículo de la factura, buscamos su subcuenta de compra o compra con irpf
         */
        foreach ($factura->get_lineas() as $lin) {
            $articulo = $lin->referencia ? $this->articulo->get($lin->referencia) : false;
            if (empty($articulo) || empty($lin->irpf)) {
                continue;
            }

            $subcart = $articulo->codsubcuentairpfcom ? $this->subcuenta->get_by_codigo($articulo->codsubcuentairpfcom, $factura->codejercicio) : false;
            if (!$subcart || $subcart->idsubcuenta == $subcuenta_irpf->idsubcuenta) {
                /// no hay / no se encuentra ninguna subcuenta asignada al artículo / o es la misma
                continue;
            }

            if (is_null($partidaA->idsubcuenta)) {
                $partidaA->idsubcuenta = $subcart->idsubcuenta;
                $partidaA->codsubcuenta = $subcart->codsubcuenta;
                $partidaA->haber = ($lin->pvptotal * $lin->irpf / 100) * $tasaconv;
            } else if ($partidaA->idsubcuenta == $subcart->idsubcuenta) {
                $partidaA->haber += ($lin->pvptotal * $lin->irpf / 100) * $tasaconv;
            } else {
                $partidaA->haber = round($partidaA->haber, FS_NF0);
                $restar += $partidaA->haber;
                if (!$partidaA->save()) {
                    $this->core_log->new_error("¡Imposible generar la partida para la subcuenta del artículo "
                        . $lin->referencia . "!");
                    return false;
                }

                $partidaA = $asiento->get_new_partida($subcart->codsubcuenta, '');
                $partidaA->haber = ($lin->pvptotal * $lin->irpf / 100) * $tasaconv;
                $partidaA->tasaconv = $tasaconv2;
            }
        }

        if ($partidaA->idsubcuenta && $partidaA->codsubcuenta) {
            $partidaA->debe = round($partidaA->debe, FS_NF0);
            $restar += $partidaA->debe;
            if (!$partidaA->save()) {
                $this->core_log->new_error("¡Imposible generar la partida para la subcuenta " . $partidaA->codsubcuenta . "!");
                return false;
            }

            $partida3->debe -= $restar;
            if ($partida3->debe == 0) {
                $partida3->delete();
            } else {
                $partida3->save();
            }
        }

        return true;
    }

    /**
     * 
     * @param asiento           $asiento
     * @param factura_proveedor $factura
     * @param proveedor         $proveedor
     * @param subcuenta         $subcuenta_prov
     * @param float             $tasaconv
     * @param float             $tasaconv2
     *
     * @return boolean
     */
    protected function generar_partidas_asiento_compra(&$asiento, &$factura, $proveedor, $subcuenta_prov, $tasaconv, $tasaconv2)
    {
        /// generamos una partida por cada impuesto
        foreach ($factura->get_lineas_iva() as $li) {
            $subcuenta_iva = $this->get_subcuenta_ivasop($asiento, $li->codimpuesto);

            if ($li->totaliva == 0 && $li->totalrecargo == 0) {
                /// no hacemos nada si no hay IVA ni RE
                continue;
            } else if ($subcuenta_iva) {
                $partida1 = $asiento->get_new_partida($subcuenta_iva->codsubcuenta, $subcuenta_prov->codsubcuenta);
                $partida1->debe = round($li->totaliva * $tasaconv, FS_NF0);
                $partida1->codserie = $factura->codserie;
                $partida1->factura = $factura->numero;
                $partida1->baseimponible = round($li->neto * $tasaconv, FS_NF0);
                $partida1->iva = $li->iva;
                $partida1->tasaconv = $tasaconv2;

                if ($proveedor) {
                    $partida1->cifnif = $proveedor->cifnif;
                }

                if (!$partida1->save()) {
                    $this->core_log->new_error("¡Imposible generar la partida para la subcuenta " . $partida1->codsubcuenta . "!");
                    return false;
                }

                if ($li->recargo != 0) {
                    $partida11 = $asiento->get_new_partida($subcuenta_iva->codsubcuenta, $subcuenta_prov->codsubcuenta);
                    $partida11->debe = round($li->totalrecargo * $tasaconv, FS_NF0);
                    $partida11->codserie = $factura->codserie;
                    $partida11->factura = $factura->numero;
                    $partida11->baseimponible = round($li->neto * $tasaconv, FS_NF0);
                    $partida11->recargo = $li->recargo;
                    $partida11->tasaconv = $tasaconv2;

                    if ($proveedor) {
                        $partida11->cifnif = $proveedor->cifnif;
                    }

                    if (!$partida11->save()) {
                        $this->core_log->new_error("¡Imposible generar la partida para la subcuenta " . $partida11->codsubcuenta . "!");
                        return false;
                    }
                }
            } else if (!$subcuenta_iva) {
                $this->core_log->new_error('No se encuentra ninguna subcuenta de ' . FS_IVA . ' para el ejercicio '
                    . $asiento->codejercicio . ' (cuenta especial IVASOP).');
                return false;
            }
        }

        $subcuenta_compras = $this->subcuenta->get_cuentaesp('COMPRA', $asiento->codejercicio);
        if ($subcuenta_compras) {
            $partida2 = $asiento->get_new_partida($subcuenta_compras->codsubcuenta, '');
            $partida2->debe = round($factura->neto * $tasaconv, FS_NF0);
            $partida2->tasaconv = $tasaconv2;
            $partida2->codserie = $factura->codserie;
            if (!$partida2->save()) {
                $this->core_log->new_error("¡Imposible generar la partida para la subcuenta " . $partida2->codsubcuenta . "!");
                return false;
            }
        } else if (!$subcuenta_compras) {
            $this->core_log->new_error('No se encuentra ninguna subcuenta de compras para el ejercicio '
                . $asiento->codejercicio . ' (cuenta especial COMPRA).');
            return false;
        }

        /// ¿IRPF?
        if ($factura->totalirpf != 0) {
            $subcuenta_irpf = $this->subcuenta->get_cuentaesp('IRPFPR', $asiento->codejercicio);
            if ($subcuenta_irpf) {
                $partida3 = $asiento->get_new_partida($subcuenta_irpf->codsubcuenta, '');
                $partida3->haber = round($factura->totalirpf * $tasaconv, FS_NF0);
                $partida3->tasaconv = $tasaconv2;
                $partida3->codserie = $factura->codserie;
                if (!$partida3->save()) {
                    $this->core_log->new_error("¡Imposible generar la partida para la subcuenta " . $partida3->codsubcuenta . "!");
                    return false;
                }
            } else if (!$subcuenta_irpf) {
                $this->core_log->new_error('No se encuentra ninguna subcuenta de ' . FS_IRPF . ' para el ejercicio '
                    . $asiento->codejercicio . ' (cuenta especial IRPFPR).');
                return false;
            }
        }

        /// comprobamos si los artículos tienen subcuentas asociadas
        $asiento_correcto = $this->comprobar_subc_art_compra($factura, $asiento, $partida2, $subcuenta_compras, $tasaconv, $tasaconv2);
        if (isset($partida3) && $asiento_correcto) {
            if (!$this->comprobar_subc_art_compra_irpf($factura, $asiento, $partida3, $subcuenta_irpf, $tasaconv, $tasaconv2)) {
                return false;
            }
        }

        /**
         * Ahora creamos la partida para la subcuenta del proveedor y calculamos
         * los importes para que todo cuadre.
         */
        $debe = $haber = 0;
        foreach ($asiento->get_partidas() as $p) {
            $debe += $p->debe;
            $haber += $p->haber;
        }
        $partida0 = $asiento->get_new_partida($subcuenta_prov->codsubcuenta, '');
        $partida0->haber = $debe - $haber;
        $partida0->tasaconv = $tasaconv2;
        $partida0->codserie = $factura->codserie;
        if ($partida0->save()) {
            $asiento->importe = max(array(abs($debe), abs($haber)));
            return $asiento->save();
        }

        $this->core_log->new_error("¡Imposible generar la partida para la subcuenta " . $partida0->codsubcuenta . "!");
        return false;
    }

    /**
     * 
     * @param asiento         $asiento
     * @param factura_cliente $factura
     * @param cliente         $cliente
     * @param subcuenta       $subcuenta_cli
     * @param float           $tasaconv
     * @param float           $tasaconv2
     *
     * @return bool
     */
    protected function generar_partidas_asiento_venta(&$asiento, &$factura, $cliente, $subcuenta_cli, $tasaconv, $tasaconv2)
    {
        /// generamos una partida por cada impuesto
        foreach ($factura->get_lineas_iva() as $li) {
            $subcuenta_iva = $this->get_subcuenta_ivarep($asiento, $li->codimpuesto);

            if ($li->totaliva == 0 && $li->totalrecargo == 0) {
                /// no hacemos nada si no hay IVA ni RE
                continue;
            } else if ($subcuenta_iva) {
                $partida1 = $asiento->get_new_partida($subcuenta_iva->codsubcuenta, $subcuenta_cli->codsubcuenta);
                $partida1->haber = round($li->totaliva * $tasaconv, FS_NF0);
                $partida1->codserie = $factura->codserie;
                $partida1->factura = $factura->numero;
                $partida1->baseimponible = round($li->neto * $tasaconv, FS_NF0);
                $partida1->iva = $li->iva;
                $partida1->tasaconv = $tasaconv2;

                if ($cliente) {
                    $partida1->cifnif = $cliente->cifnif;
                }

                if (!$partida1->save()) {
                    $this->core_log->new_error("¡Imposible generar la partida para la subcuenta " . $partida1->codsubcuenta . "!");
                    return false;
                }

                if ($li->recargo != 0) {
                    $partida11 = $asiento->get_new_partida($subcuenta_iva->codsubcuenta, $subcuenta_cli->codsubcuenta);
                    $partida11->haber = round($li->totalrecargo * $tasaconv, FS_NF0);
                    $partida11->codserie = $factura->codserie;
                    $partida11->factura = $factura->numero;
                    $partida11->baseimponible = round($li->neto * $tasaconv, FS_NF0);
                    $partida11->recargo = $li->recargo;
                    $partida11->tasaconv = $tasaconv2;

                    if ($cliente) {
                        $partida11->cifnif = $cliente->cifnif;
                    }

                    if (!$partida11->save()) {
                        $this->core_log->new_error("¡Imposible generar la partida para la subcuenta " . $partida11->codsubcuenta . "!");
                        return false;
                    }
                }
            } else if (!$subcuenta_iva) {
                $this->core_log->new_error('No se encuentra ninguna subcuenta de ' . FS_IVA . ' para el ejercicio '
                    . $asiento->codejercicio . ' (cuenta especial IVAREP).');
                return false;
            }
        }

        $subcuenta_ventas = $this->subcuenta->get_cuentaesp('VENTAS', $asiento->codejercicio);
        if ($subcuenta_ventas) {
            $partida2 = $asiento->get_new_partida($subcuenta_ventas->codsubcuenta, '');
            $partida2->haber = round($factura->neto * $tasaconv, FS_NF0);
            $partida2->tasaconv = $tasaconv2;
            $partida2->codserie = $factura->codserie;
            if (!$partida2->save()) {
                $this->core_log->new_error("¡Imposible generar la partida para la subcuenta " . $partida2->codsubcuenta . "!");
                return false;
            }
        } else if (!$subcuenta_ventas) {
            $this->core_log->new_error('No se encuentra ninguna subcuenta de ventas para el ejercicio '
                . $asiento->codejercicio . ' (cuenta especial VENTAS).');
            return false;
        }

        /// ¿IRPF?
        if ($factura->totalirpf != 0) {
            $subcuenta_irpf = $this->subcuenta->get_cuentaesp('IRPF', $asiento->codejercicio);
            if (!$subcuenta_irpf) {
                $subcuenta_irpf = $this->subcuenta->get_by_codigo('4730000000', $asiento->codejercicio);
            }

            if ($subcuenta_irpf) {
                $partida3 = $asiento->get_new_partida($subcuenta_irpf->codsubcuenta, '');
                $partida3->debe = round($factura->totalirpf * $tasaconv, FS_NF0);
                $partida3->tasaconv = $tasaconv2;
                $partida3->codserie = $factura->codserie;
                if (!$partida3->save()) {
                    $this->core_log->new_error("¡Imposible generar la partida para la subcuenta " . $partida3->codsubcuenta . "!");
                    return false;
                }
            } else if (!$subcuenta_irpf) {
                $this->core_log->new_error('No se encuentra ninguna subcuenta de ' . FS_IRPF . ' para el ejercicio '
                    . $asiento->codejercicio . ' (cuenta especial IRPF).');
                return false;
            }
        }

        $partidaA = $asiento->get_new_partida('', '');
        $partidaA->tasaconv = $tasaconv2;

        /// importe a restar a la partida2
        $restar = 0;

        /**
         * Para cada artículo de la factura, buscamos su subcuenta de compra o compra con irpf
         */
        foreach ($factura->get_lineas() as $lin) {
            $subcart = false;
            if (!$this->articulo_propiedad_vacio) {
                $aprops = $this->articulo_propiedad->array_get($lin->referencia);
                if (isset($aprops['codsubcuentaventa'])) {
                    $subcart = $this->subcuenta->get_by_codigo($aprops['codsubcuentaventa'], $factura->codejercicio);
                }
            }

            if (!$subcart || $subcart->idsubcuenta == $subcuenta_ventas->idsubcuenta) {
                /// no hay / no se encuentra ninguna subcuenta asignada al artículo / o es la misma
                continue;
            }

            if (is_null($partidaA->idsubcuenta)) {
                $partidaA->idsubcuenta = $subcart->idsubcuenta;
                $partidaA->codsubcuenta = $subcart->codsubcuenta;
                $partidaA->haber = $lin->pvptotal * $tasaconv;
            } else if ($partidaA->idsubcuenta == $subcart->idsubcuenta) {
                $partidaA->haber += $lin->pvptotal * $tasaconv;
            } else {
                $partidaA->haber = round($partidaA->haber, FS_NF0);
                $restar += $partidaA->haber;
                if (!$partidaA->save()) {
                    $this->core_log->new_error("¡Imposible generar la partida para la subcuenta del artículo "
                        . $lin->referencia . "!");
                    return false;
                }

                $partidaA = $asiento->get_new_partida($subcart->codsubcuenta, '');
                $partidaA->haber = $lin->pvptotal * $tasaconv;
                $partidaA->coddivisa = $this->empresa->coddivisa;
                $partidaA->tasaconv = $tasaconv2;
            }
        }

        if ($partidaA->idsubcuenta && $partidaA->codsubcuenta) {
            $partidaA->haber = round($partidaA->haber, FS_NF0);
            $restar += $partidaA->haber;
            if (!$partidaA->save()) {
                $this->core_log->new_error("¡Imposible generar la partida para la subcuenta " . $partidaA->codsubcuenta . "!");
                return false;
            }

            $partida2->haber -= $restar;
            if ($partida2->haber == 0) {
                $partida2->delete();
            } else {
                $partida2->save();
            }
        }

        /**
         * Ahora calculamos los importes y añadimos la partida a la subcuenta
         * del cliente.
         */
        $debe = $haber = 0;
        foreach ($asiento->get_partidas() as $p) {
            $debe += $p->debe;
            $haber += $p->haber;
        }

        $partida0 = $asiento->get_new_partida($subcuenta_cli->codsubcuenta, '');
        $partida0->debe = $haber - $debe;
        $partida0->tasaconv = $tasaconv2;
        $partida0->codserie = $factura->codserie;
        if ($partida0->save()) {
            $asiento->importe = max(array(abs($debe), abs($haber)));
            return $asiento->save();
        }

        $this->core_log->new_error("¡Imposible generar la partida para la subcuenta " . $partida0->codsubcuenta . "!");
        return false;
    }

    /**
     * 
     * @param string $codejercicio
     * @param string $codpago
     *
     * @return subcuenta
     */
    protected function get_subcuenta_caja($codejercicio, $codpago)
    {
        if ($codpago) {
            /**
             * Si nos han pasado una forma de pago, intentamos buscar la subcuenta
             * asociada a la cuenta bancaria.
             */
            $formap = $this->forma_pago->get($codpago);
            if ($formap && $formap->codcuenta) {
                $cuentab = $this->cuenta_banco->get($formap->codcuenta);
                if ($cuentab) {
                    $subc = $this->subcuenta->get_by_codigo($cuentab->codsubcuenta, $codejercicio);
                    if ($subc) {
                        return $subc;
                    }
                }
            }
        }

        return $this->subcuenta->get_cuentaesp('CAJA', $codejercicio);
    }

    /**
     * 
     * @param cliente $cliente
     * @param string  $codejercicio
     *
     * @return subcuenta
     */
    protected function get_subcuenta_cliente($cliente, $codejercicio)
    {
        if ($cliente) {
            return $cliente->get_subcuenta($codejercicio);
        }

        /// buscamos la cuenta 0 de clientes
        $subcuenta_cli = $this->subcuenta->get_cuentaesp('CLIENT', $codejercicio);
        if (!$subcuenta_cli) {
            $eje0 = $this->ejercicio->get($codejercicio);
            $this->core_log->new_error("No se ha podido generar una subcuenta para el cliente "
                . "¿<a href='" . $eje0->url() . "'>Has importado los datos del ejercicio</a>?");
        }
        return $subcuenta_cli;
    }

    /**
     * 
     * @param asiento $asiento
     * @param string  $codimpuesto
     *
     * @return subcuenta|bool
     */
    protected function get_subcuenta_ivarep($asiento, $codimpuesto)
    {
        $subcuenta_iva = false;

        /// ¿El impuesto tiene una subcuenta específica?
        if (isset($this->impuestos[$codimpuesto]) && $this->impuestos[$codimpuesto]->codsubcuentarep) {
            $subcuenta_iva = $this->subcuenta->get_by_codigo($this->impuestos[$codimpuesto]->codsubcuentarep, $asiento->codejercicio);
        }

        /// si no hemos encontrado una subcuenta, usamos la primera de IVAREP
        if (!$subcuenta_iva) {
            $subcuenta_iva = $this->subcuenta->get_cuentaesp('IVAREP', $asiento->codejercicio);
        }

        return $subcuenta_iva;
    }

    /**
     * 
     * @param asiento $asiento
     * @param string  $codimpuesto
     *
     * @return subcuenta|bool
     */
    protected function get_subcuenta_ivasop($asiento, $codimpuesto)
    {
        $subcuenta_iva = false;

        /// ¿El impuesto tiene una subcuenta específica?
        if (isset($this->impuestos[$codimpuesto]) && $this->impuestos[$codimpuesto]->codsubcuentasop) {
            $subcuenta_iva = $this->subcuenta->get_by_codigo($this->impuestos[$codimpuesto]->codsubcuentasop, $asiento->codejercicio);
        }

        /// si no hemos encontrado una subcuenta, usamos la primera de IVASOP
        if (!$subcuenta_iva) {
            $subcuenta_iva = $this->subcuenta->get_cuentaesp('IVASOP', $asiento->codejercicio);
        }

        /// si aun así no hemos encontrado una subcuenta, usamos la primera de IVAREP
        if (!$subcuenta_iva) {
            $subcuenta_iva = $this->subcuenta->get_cuentaesp('IVAREP', $asiento->codejercicio);
        }

        return $subcuenta_iva;
    }

    /**
     * 
     * @param proveedor|bool $proveedor
     * @param string         $codejercicio
     *
     * @return subcuenta
     */
    protected function get_subcuenta_proveedor($proveedor, $codejercicio)
    {
        if ($proveedor) {
            return $proveedor->get_subcuenta($codejercicio);
        }

        /// buscamos la cuenta 0 de proveedores
        $subcuenta_prov = $this->subcuenta->get_cuentaesp('PROVEE', $codejercicio);
        if (!$subcuenta_prov) {
            $eje0 = $this->ejercicio->get($codejercicio);
            $this->core_log->new_error("No se ha podido generar una subcuenta para el proveedor"
                . " ¿<a href='" . $eje0->url() . "'>Has importado los datos del ejercicio</a>?");
        }

        return $subcuenta_prov;
    }

    /**
     * 
     * @param object $factura
     * @param float  $tasa1
     * @param float  $tasa2
     * @param bool   $compras
     */
    protected function set_tasasconv(&$factura, &$tasa1, &$tasa2, $compras = false)
    {
        if ($factura->coddivisa == $this->empresa->coddivisa) {
            return;
        }

        $divisa = $this->divisa->get($this->empresa->coddivisa);
        if (!$divisa) {
            return;
        }

        if ($compras) {
            $tasa1 = $divisa->tasaconv_compra / $factura->tasaconv;
            $tasa2 = $divisa->tasaconv_compra;
        } else {
            $tasa1 = $divisa->tasaconv / $factura->tasaconv;
            $tasa2 = $divisa->tasaconv;
        }
    }
}
