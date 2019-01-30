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
namespace FacturaScripts\model;

require_once __DIR__ . '/../../extras/fbase_asiento_factura.php';

/**
 * Esta clase permite genera un asiento a partir de una factura.
 *
 * @author Carlos García Gómez <neorazorx@gmail.com>
 */
class asiento_factura extends \fbase_asiento_factura
{

    /**
     * Genera el asiento contable para una factura de compra.
     * Devuelve TRUE si el asiento se ha generado correctamente, False en caso contrario.
     * Si genera el asiento, este es accesible desde $this->asiento.
     *
     * @param \factura_proveedor $factura
     */
    public function generar_asiento_compra(&$factura)
    {
        $this->asiento = false;

        /// el asiento ya existe
        if ($factura->idasiento) {
            $this->core_log->new_message("Ya hay un asiento vinculado a la <a href='" . $factura->url() . "'>factura</a>.");
            return false;
        }

        /// obtenemos las tasas de conversión, para las ocasiones en que la factura está en otra divisa
        $tasaconv = 1;
        $tasaconv2 = $factura->tasaconv;
        $this->set_tasasconv($factura, $tasaconv, $tasaconv2, true);

        /// obtenemos el proveedor de la factura y su subcuenta
        $proveedor0 = new \proveedor();
        $proveedor = $proveedor0->get($factura->codproveedor);
        $subcuenta_prov = $this->get_subcuenta_proveedor($proveedor, $factura->codejercicio);
        if (!$subcuenta_prov) {
            /// $proveedor->get_subcuenta() ya genera mensajes en caso de error.
            if (!$this->soloasiento) {
                $this->core_log->new_message("Aun así la <a href='" . $factura->url() . "'>factura</a> se ha generado correctamente," .
                    " pero sin asiento contable.");
            }
            return false;
        }

        $asiento = new \asiento();
        $asiento->set_coddivisa($this->empresa->coddivisa);
        $asiento->codejercicio = $factura->codejercicio;
        $asiento->documento = $factura->codigo;
        $asiento->editable = false;
        $asiento->fecha = $factura->fecha;
        $asiento->tipodocumento = "Factura de proveedor";

        if ($factura->numproveedor) {
            $asiento->concepto = "Factura de compra " . $factura->numproveedor . " - " . $factura->nombre;
        } else if ($factura->idfacturarect) {
            $asiento->concepto = ucfirst(FS_FACTURA_RECTIFICATIVA) . " de " . $factura->codigorect . " (compras) - " . $factura->nombre;
        } else {
            $asiento->concepto = "Factura de compra " . $factura->codigo . " - " . $factura->nombre;
        }

        if (!$asiento->save()) {
            $this->core_log->new_error("¡Imposible guardar el asiento!");
            return false;
        }

        $asiento_correcto = $this->generar_partidas_asiento_compra($asiento, $factura, $proveedor, $subcuenta_prov, $tasaconv, $tasaconv2);

        /// si es una factura negativa o rectificativa, invertimos los importes
        if ($asiento_correcto && ($factura->idfacturarect || $factura->total < 0)) {
            $this->invertir_asiento($asiento);
        }

        if ($asiento_correcto && !$this->check_asiento($asiento)) {
            $this->core_log->new_error('El asiento está descuadrado.');
            $asiento_correcto = false;
        }

        if ($asiento_correcto) {
            $factura->idasiento = $asiento->idasiento;
            if ($factura->pagada && empty($factura->idasientop)) {
                $factura->idasientop = $this->generar_asiento_pago(
                    $asiento, $factura->codpago, $factura->fecha, $subcuenta_prov
                );
            }

            if ($factura->save()) {
                $this->asiento = $asiento;
                return true;
            }

            $factura->idasiento = null;
            $this->core_log->new_error("¡Imposible añadir el asiento a la factura!");
        }

        if ($asiento->delete()) {
            $this->core_log->new_message("El asiento se ha borrado.");
        } else {
            $this->core_log->new_error("¡Imposible borrar el asiento!");
        }

        return false;
    }

    /**
     * Generamos un asiento de pago del asiento seleccionado.
     *
     * @param \asiento   $asiento
     * @param string     $codpago
     * @param string     $fecha
     * @param \subcuenta $subclipro
     * @param float      $importe0
     *
     * @return \asiento
     */
    public function generar_asiento_pago(&$asiento, $codpago = false, $fecha = false, $subclipro = false, $importe0 = null)
    {
        /// necesitamos un importe para asegurarnos de que la partida elegida es la correcta
        $importe = is_null($importe0) ? $asiento->importe : abs(round($importe0, FS_NF0));
        $importe2 = abs(round($asiento->importe, FS_NF0));

        $nasientop = new \asiento();
        $nasientop->editable = false;
        $nasientop->tipodocumento = $asiento->tipodocumento;
        $nasientop->documento = $asiento->documento;
        $nasientop->concepto = ($asiento->tipodocumento == 'Factura de cliente') ? 'Cobro ' . $asiento->concepto : 'Pago ' . $asiento->concepto;
        if ($fecha) {
            $nasientop->fecha = $fecha;
        }

        /// asignamos la mejor fecha
        $eje = $this->ejercicio->get_by_fecha($nasientop->fecha);
        if ($eje) {
            $nasientop->codejercicio = $eje->codejercicio;
            $nasientop->fecha = $eje->get_best_fecha($nasientop->fecha);
        } else {
            $this->core_log->new_error('Ningún ejercico encontrado.');
            return null;
        }

        /// necesitamos la subcuenta de caja
        $subcaja = $this->get_subcuenta_caja($nasientop->codejercicio, $codpago);
        if (empty($subcaja)) {
            $this->core_log->new_error('No se ha encontrado ninguna subcuenta de caja para el ejercicio '
                . $eje->codejercicio . '. <a href="' . $eje->url() . '">¿Has importado los datos del ejercicio?</a>');
            return null;
        }

        if (!$nasientop->save()) {
            $this->core_log->new_error('Error al guardar el asiento de pago.');
            return null;
        }

        /// elegimos la partida sobre la que hacer el pago
        $idpartida = 0;
        foreach ($asiento->get_partidas() as $par) {
            if ($subclipro && $par->codsubcuenta == $subclipro->codsubcuenta) {
                $idpartida = $par->idpartida;
                break;
            }

            if ($nasientop->floatcmp(abs($par->debe), $importe, FS_NF0) || $nasientop->floatcmp(abs($par->debe), $importe2, FS_NF0)) {
                $idpartida = $par->idpartida;
                /// sin break
            } else if ($nasientop->floatcmp(abs($par->haber), $importe, FS_NF0) || $nasientop->floatcmp(abs($par->haber), $importe2, FS_NF0)) {
                $idpartida = $par->idpartida;
                /// sin break
            }
        }

        if (empty($idpartida)) {
            $this->core_log->new_error('No se ha encontrado la partida necesaria para generar el asiento'
                . ' de pago de <a href="' . $asiento->url() . '">' . $asiento->concepto . '</a>');
            $nasientop->delete();
            return null;
        }

        /// generamos las partidas
        foreach ($asiento->get_partidas() as $par) {
            if ($par->idpartida != $idpartida) {
                continue;
            }

            if (!$subclipro) {
                /// si no tenemos una subcuenta de cliente/proveedor, usamos la de la partida
                $subclipro = $this->subcuenta->get_by_codigo($par->codsubcuenta, $nasientop->codejercicio);
            }

            if (!$subclipro) {
                $this->core_log->new_error('No se ha encontrado la subcuenta ' . $par->codsubcuenta . ' en el ejercicio ' . $nasientop->codejercicio);
                $nasientop->delete();
                return null;
            }

            /// 1º partida
            $partida1 = $nasientop->get_new_partida($subclipro->codsubcuenta, '');
            if ($par->debe != 0) {
                $partida1->haber = $par->debe;
                $nasientop->importe = $par->debe;
            } else {
                $partida1->debe = $par->haber;
                $nasientop->importe = $par->haber;
            }

            $partida1->tasaconv = $par->tasaconv;
            $partida1->codserie = $par->codserie;
            $partida1->save();

            /// 2º partida
            $partida2 = $nasientop->get_new_partida($subcaja->codsubcuenta, '');
            if ($par->debe != 0) {
                $partida2->debe = $par->debe;
            } else {
                $partida2->haber = $par->haber;
            }

            $partida2->tasaconv = $par->tasaconv;
            $partida2->codserie = $par->codserie;
            $partida2->save();
            break;
        }

        $nasientop->save();
        return $nasientop->idasiento;
    }

    /**
     * Genera el asiento contable para una factura de venta.
     * Devuelve TRUE si el asiento se ha generado correctamente, False en caso contrario.
     * Si genera el asiento, este es accesible desde $this->asiento.
     *
     * @param \factura_cliente $factura
     */
    public function generar_asiento_venta(&$factura)
    {
        $this->asiento = false;

        /// el asiento ya existe
        if ($factura->idasiento) {
            $this->core_log->new_message("Ya hay un asiento vinculado a la <a href='" . $factura->url() . "'>factura</a>.");
            return false;
        }

        /// obtenemos las tasas de conversión, para las ocasiones en que la factura está en otra divisa
        $tasaconv = 1;
        $tasaconv2 = $factura->tasaconv;
        $this->set_tasasconv($factura, $tasaconv, $tasaconv2);

        /// obtenemos el clientes y su subcuenta
        $cliente0 = new \cliente();
        $cliente = $cliente0->get($factura->codcliente);
        $subcuenta_cli = $this->get_subcuenta_cliente($cliente, $factura->codejercicio);
        if (!$subcuenta_cli) {
            /// $cliente->get_subcuenta() ya genera mensajes en caso de error
            if (!$this->soloasiento) {
                $this->core_log->new_message("Aun así la <a href='" . $factura->url() . "'>factura</a> se ha generado correctamente,"
                    . " pero sin asiento contable.");
            }
            return false;
        }

        $asiento = new \asiento();
        $asiento->set_coddivisa($this->empresa->coddivisa);
        $asiento->codejercicio = $factura->codejercicio;
        $asiento->documento = $factura->codigo;
        $asiento->editable = false;
        $asiento->fecha = $factura->fecha;
        $asiento->tipodocumento = 'Factura de cliente';

        if ($factura->idfacturarect) {
            $asiento->concepto = ucfirst(FS_FACTURA_RECTIFICATIVA) . " de " . $factura->codigo . " (ventas) - " . $factura->nombrecliente;
        } else {
            $asiento->concepto = "Factura de venta " . $factura->codigo . " - " . $factura->nombrecliente;
        }

        if (!$asiento->save()) {
            $this->core_log->new_error("¡Imposible guardar el asiento!");
            return false;
        }

        $asiento_correcto = $this->generar_partidas_asiento_venta($asiento, $factura, $cliente, $subcuenta_cli, $tasaconv, $tasaconv2);

        /// si es una factura negativa o rectificativa, invertimos los importes
        if ($asiento_correcto && ($factura->idfacturarect || $factura->total < 0)) {
            $this->invertir_asiento($asiento);
        }

        if ($asiento_correcto && !$this->check_asiento($asiento)) {
            $this->core_log->new_error('El asiento está descuadrado.');
            $asiento_correcto = false;
        }

        if ($asiento_correcto) {
            $factura->idasiento = $asiento->idasiento;
            if ($factura->pagada && empty($factura->idasientop)) {
                $factura->idasientop = $this->generar_asiento_pago(
                    $asiento, $factura->codpago, $factura->fecha, $subcuenta_cli
                );
            }

            if ($factura->save()) {
                $this->asiento = $asiento;
                return true;
            }

            $factura->idasiento = null;
            $this->core_log->new_error("¡Imposible añadir el asiento a la factura!");
        }

        if ($asiento->delete()) {
            $this->core_log->new_message("El asiento se ha borrado.");
        } else {
            $this->core_log->new_error("¡Imposible borrar el asiento!");
        }

        return false;
    }

    /**
     * Invierte los valores debe/haber de las líneas del asiento. Además de recalcular el importe.
     *
     * @param \asiento $asiento
     */
    public function invertir_asiento(&$asiento)
    {
        $t_debe = $t_haber = 0;
        foreach ($asiento->get_partidas() as $part) {
            if ($part->debe < 0 || $part->haber < 0) {
                $debe = abs($part->debe);
                $haber = abs($part->haber);

                $part->debe = $haber;
                $part->haber = $debe;
                $part->baseimponible = abs($part->baseimponible);
                $part->save();

                $t_debe += $part->debe;
                $t_haber += $part->haber;
            }
        }

        $asiento->importe = max(array(abs($t_debe), abs($t_haber)));
        $asiento->save();
    }
}
