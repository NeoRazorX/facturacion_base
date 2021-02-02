<?php
/**
 * This file is part of facturacion_base
 * Copyright (C) 2013-2021 Carlos Garcia Gomez <neorazorx@gmail.com>
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
 * Description of cierre_ejercicio
 *
 * @author Carlos Garcia Gomez <neorazorx@gmail.com>
 */
class cierre_ejercicio
{

    /**
     * 
     * @var fs_db2
     */
    private $database;

    /**
     * 
     * @var ejercicio
     */
    private $ejercicio;

    /**
     * 
     * @var array
     */
    private $errors;

    /**
     * 
     * @param string $eje
     */
    public function __construct($eje)
    {
        $this->database = new fs_db2();
        $this->ejercicio = $eje;
    }

    /**
     * 
     * @return array
     */
    public function get_errors()
    {
        return $this->errors;
    }

    /**
     * 
     * @param string $err
     */
    private function new_error_msg($err)
    {
        $this->errors[] = $err;
    }

    /**
     * 
     * @return bool
     */
    public function paso1()
    {
        $asiento = new asiento();
        $continuar = TRUE;

        /**
         * Eliminamos los asientos de cierre, pérdidas y ganancias y apertura del siguiente ejercicio
         */
        if (isset($this->ejercicio->idasientopyg)) {
            $aspyg = $asiento->get($this->ejercicio->idasientopyg);
            if ($aspyg) {
                if (!$aspyg->delete()) {
                    $this->new_error_msg('Imposible eliminar el asiento de pérdidas y ganancias.');
                    $continuar = FALSE;
                }
            } else {
                $this->ejercicio->idasientopyg = NULL;
                $this->ejercicio->save();
            }
        }

        if (isset($this->ejercicio->idasientocierre)) {
            $asc = $asiento->get($this->ejercicio->idasientocierre);
            if ($asc) {
                if (!$asc->delete()) {
                    $this->new_error_msg('Imposible eliminar el asiento de cierre.');
                    $continuar = FALSE;
                }
            } else {
                $this->ejercicio->idasientocierre = NULL;
                $this->ejercicio->save();
            }
        }

        $siguiente_ejercicio = $this->ejercicio->get_by_fecha(Date('d-m-Y', strtotime($this->ejercicio->fechafin) + 24 * 3600));
        if (!$siguiente_ejercicio) {
            $this->new_error_msg('Imposible obtener el siguiente ejercicio.');
            $continuar = FALSE;
        } else if (isset($siguiente_ejercicio->idasientoapertura)) {
            $asap = $asiento->get($siguiente_ejercicio->idasientoapertura);
            if ($asap) {
                if (!$asap->delete()) {
                    $this->new_error_msg('Imposible eliminar el asiento de apertura.');
                    $continuar = FALSE;
                }
            } else {
                $siguiente_ejercicio->idasientoapertura = NULL;
                $siguiente_ejercicio->save();
            }
        }

        /**
         * Ahora creamos de nuevo los asientos de pyg, cierre y apertura del siguiente
         */
        if ($continuar) {
            $asiento_pyg = new asiento();
            $asiento_pyg->codejercicio = $this->ejercicio->codejercicio;
            $asiento_pyg->concepto = 'Regularización ejercicio ' . $this->ejercicio->nombre;
            $asiento_pyg->editable = FALSE;
            $asiento_pyg->fecha = $this->ejercicio->fechafin;
            if ($asiento_pyg->save()) {
                $this->ejercicio->idasientopyg = $asiento_pyg->idasiento;
                $this->ejercicio->save();
            } else {
                $this->new_error_msg('Error al crear el asiento de regularización.');
                $continuar = FALSE;
            }
        }

        if ($continuar) {
            $asiento_cierre = new asiento();
            $asiento_cierre->codejercicio = $this->ejercicio->codejercicio;
            $asiento_cierre->concepto = 'Asiento de cierre del ejercicio ' . $this->ejercicio->nombre;
            $asiento_cierre->editable = FALSE;
            $asiento_cierre->fecha = $this->ejercicio->fechafin;
            if ($asiento_cierre->save()) {
                $this->ejercicio->idasientocierre = $asiento_cierre->idasiento;
                $this->ejercicio->save();
            } else {
                $this->new_error_msg('Error al crear el asiento de cierre.');
                $continuar = FALSE;
            }
        }

        if ($continuar) {
            $asiento_apertura = new asiento();
            $asiento_apertura->codejercicio = $siguiente_ejercicio->codejercicio;
            $asiento_apertura->concepto = 'Asiento de apertura del ejercicio ' . $siguiente_ejercicio->nombre;
            $asiento_apertura->editable = FALSE;
            $asiento_apertura->fecha = $siguiente_ejercicio->fechainicio;
            if ($asiento_apertura->save()) {
                $siguiente_ejercicio->idasientoapertura = $asiento_apertura->idasiento;
                $siguiente_ejercicio->save();
            } else {
                $this->new_error_msg('Error al crear el asiento de apertura.');
                $continuar = FALSE;
            }
        }

        return $continuar;
    }

    /**
     * 
     * @return bool
     */
    public function paso2()
    {
        $asiento = new asiento();
        $asiento_apertura = FALSE;
        $continuar = TRUE;

        /// cargamos los asientos de cierre, pyg y apertura del siguiente
        $asiento_pyg = $asiento->get($this->ejercicio->idasientopyg);
        if (!$asiento_pyg) {
            $this->new_error_msg('Asiento de regularización no encontrado.');
            $continuar = FALSE;
        }

        $asiento_cierre = $asiento->get($this->ejercicio->idasientocierre);
        if (!$asiento_cierre) {
            $this->new_error_msg('Asiento de cierre no encontrado.');
            $continuar = FALSE;
        }

        $siguiente_ejercicio = $this->ejercicio->get_by_fecha(Date('d-m-Y', strtotime($this->ejercicio->fechafin) + 24 * 3600));
        if ($siguiente_ejercicio) {
            $asiento_apertura = $asiento->get($siguiente_ejercicio->idasientoapertura);
            if (!$asiento_apertura) {
                $this->new_error_msg('Asiento de apertura no encontrado.');
                $continuar = FALSE;
            }
        } else {
            $this->new_error_msg('Imposible obtener el siguiente ejercicio.');
            $continuar = FALSE;
        }

        if ($continuar) {
            $subcuenta = new subcuenta();

            /*
             * Abonamos y cargamos los saldos de las cuentas de los grupos 6 y 7,
             * la diferencia la enviamos a la cuenta PYG.
             */
            $diferencia = 0;
            foreach ($subcuenta->all_from_ejercicio($this->ejercicio->codejercicio) as $sc) {
                if (in_array(substr($sc->codcuenta, 0, 1), array('6', '7')) && $sc->tiene_saldo()) {
                    $ppyg = new partida();
                    $ppyg->idasiento = $asiento_pyg->idasiento;
                    $ppyg->concepto = $asiento_pyg->concepto;
                    $ppyg->idsubcuenta = $sc->idsubcuenta;
                    $ppyg->codsubcuenta = $sc->codsubcuenta;

                    if ($sc->saldo < 0) {
                        $ppyg->debe = abs($sc->saldo);
                    } else
                        $ppyg->haber = $sc->saldo;

                    $diferencia += $ppyg->debe - $ppyg->haber;

                    $ppyg->coddivisa = $sc->coddivisa;
                    if (!$ppyg->save()) {
                        $continuar = FALSE;
                    }
                }
            }

            $cuenta = new cuenta();
            $cuenta_pyg = $cuenta->get_cuentaesp('PYG', $this->ejercicio->codejercicio);
            if ($cuenta_pyg) {
                $subcuenta_pyg = FALSE;
                foreach ($cuenta_pyg->get_subcuentas() as $sc) {
                    $subcuenta_pyg = $sc;
                    break;
                }

                if ($subcuenta_pyg) {
                    $ppyg = new partida();
                    $ppyg->idasiento = $asiento_pyg->idasiento;
                    $ppyg->concepto = $asiento_pyg->concepto;
                    $ppyg->idsubcuenta = $subcuenta_pyg->idsubcuenta;
                    $ppyg->codsubcuenta = $subcuenta_pyg->codsubcuenta;
                    $ppyg->haber = $diferencia;
                    $ppyg->coddivisa = $subcuenta_pyg->coddivisa;
                    if (!$ppyg->save()) {
                        $continuar = FALSE;
                    }
                } else {
                    $this->new_error_msg('No se encuentra una subcuenta para la cuenta especial PYG (pérdidas y ganancias).');
                    $continuar = FALSE;
                }
            } else {
                $this->new_error_msg('No se encuentra la cuenta especial PYG (pérdidas y ganancias).');
                $continuar = FALSE;
            }

            /*
             * Generamos los asientos de cierre y apertura
             */
            foreach ($subcuenta->all_from_ejercicio($this->ejercicio->codejercicio) as $sc) {
                if ($sc->tiene_saldo()) {
                    $pac = new partida();
                    $pac->idasiento = $asiento_cierre->idasiento;
                    $pac->concepto = $asiento_cierre->concepto;
                    $pac->idsubcuenta = $sc->idsubcuenta;
                    $pac->codsubcuenta = $sc->codsubcuenta;

                    if ($sc->saldo < 0) {
                        $pac->debe = abs($sc->saldo);
                    } else
                        $pac->haber = $sc->saldo;

                    $pac->coddivisa = $sc->coddivisa;
                    if (!$pac->save()) {
                        $continuar = FALSE;
                    }

                    if ($sc->codcuenta == $cuenta_pyg->codcuenta) {
                        $nsc = $subcuenta->get_by_codigo('1200000000', $siguiente_ejercicio->codejercicio, TRUE);
                    } else {
                        $nsc = $this->get_new_subcuenta($this->ejercicio->codejercicio, $sc->codsubcuenta, $siguiente_ejercicio->codejercicio);
                    }

                    if ($nsc && $asiento !== FALSE) {
                        $paa = new partida();
                        $paa->idasiento = $asiento_apertura->idasiento;
                        $paa->concepto = $asiento_apertura->concepto;
                        $paa->idsubcuenta = $nsc->idsubcuenta;
                        $paa->codsubcuenta = $nsc->codsubcuenta;

                        if ($sc->saldo > 0) {
                            $paa->debe = round($sc->saldo, FS_NF0);
                        } else
                            $paa->haber = round(abs($sc->saldo), FS_NF0);

                        $paa->coddivisa = $nsc->coddivisa;
                        if (!$paa->save()) {
                            $continuar = FALSE;
                        }
                    } else
                        $continuar = FALSE;
                }
            }

            /// comprobamos los nuevos asientos
            $total = 0;
            foreach ($asiento_pyg->get_partidas() as $part) {
                $total += $part->debe - $part->haber;
            }
            if (abs($total) >= 0.01) {
                $continuar = FALSE;
                $this->new_error_msg('Asiento de pérdidas y ganancias descuadrado.');
            } else {
                $asiento_pyg->fix();
            }

            $total = 0;
            foreach ($asiento_cierre->get_partidas() as $part) {
                $total += $part->debe - $part->haber;
            }
            if (abs($total) >= 0.01) {
                $continuar = FALSE;
                $this->new_error_msg('Asiento de cierre descuadrado.');
            } else {
                $asiento_cierre->fix();
            }

            $total = 0;
            foreach ($asiento_apertura->get_partidas() as $part) {
                $total += $part->debe - $part->haber;
            }
            if (abs($total) >= 0.01) {
                /// buscamos la subcuenta de redondeo
                $subcuenta_redondeo = $subcuenta->get_cuentaesp('REDOND', $asiento_apertura->codejercicio);
                if (!$subcuenta_redondeo) {
                    /// si no está usamos la específica para España
                    $subcuenta_redondeo = $subcuenta->get_by_codigo('6780000000', $asiento_apertura->codejercicio);
                }

                if ($subcuenta_redondeo) {
                    $npaa = new partida();
                    $npaa->idasiento = $asiento_apertura->idasiento;
                    $npaa->concepto = $asiento_apertura->concepto;
                    $npaa->idsubcuenta = $subcuenta_redondeo->idsubcuenta;
                    $npaa->codsubcuenta = $subcuenta_redondeo->codsubcuenta;
                    $npaa->coddivisa = $subcuenta_redondeo->coddivisa;

                    if ($total > 0) {
                        $npaa->haber = $total;
                    } else
                        $npaa->debe = $total;

                    $npaa->save();
                    $asiento_apertura->fix();
                } else {
                    $continuar = FALSE;
                    $this->new_error_msg('Asiento de apertura descuadrado.');
                }
            } else {
                $asiento_apertura->fix();
            }

            /// cerramos el ejercicio
            if ($continuar) {
                $this->ejercicio->estado = 'CERRADO';
                $this->ejercicio->idasientopyg = $asiento_pyg->idasiento;
                $this->ejercicio->idasientocierre = $asiento_cierre->idasiento;
                if (!$this->ejercicio->save()) {
                    $this->new_error_msg('Error al cerrar el ejercicio.');
                }

                $siguiente_ejercicio->idasientoapertura = $asiento_apertura->idasiento;
                if (!$siguiente_ejercicio->save()) {
                    $this->new_error_msg('Error al modificar el siguiente ejercicio.');
                }
            } else {
                $this->new_error_msg('Error al generar los asientos.');

                if (!$asiento_pyg->delete()) {
                    $this->new_error_msg('Imposible eliminar el asiento de pérdidas y ganancias.');
                }

                if (!$asiento_cierre->delete()) {
                    $this->new_error_msg('Imposible eliminar el asiento de cierre.');
                }

                if (!$asiento_apertura->delete()) {
                    $this->new_error_msg('Imposible eliminar el asiento de apertura.');
                }
            }

            return $continuar;
        }
    }

    /**
     * 
     * @param string $old_codejercicio
     * @param string $codsubcuenta
     * @param string $new_codejercicio
     *
     * @return subcuenta
     */
    private function get_new_subcuenta($old_codejercicio, $codsubcuenta, $new_codejercicio)
    {
        /// buscamos la subcuenta entre las relacionadas con clientes. Si la encontramos devolvemos la nueva subcuenta.
        $sql_find_cli = "SELECT codcliente FROM co_subcuentascli WHERE codejercicio = '" . $old_codejercicio . "'"
            . " AND codsubcuenta = '" . $codsubcuenta . "';";
        foreach ($this->database->select($sql_find_cli) as $row) {
            $cliente_model = new cliente();
            $cliente = $cliente_model->get($row['codcliente']);
            if ($cliente) {
                return $cliente->get_subcuenta($new_codejercicio);
            }
        }

        /// buscamos la subcuenta entre las relacionadas con proveedores. Si la encontramos devolvemos la nueva subcuenta.
        $sql_find_prov = "SELECT codproveedor FROM co_subcuentasprov WHERE codejercicio = '" . $old_codejercicio . "'"
            . " AND codsubcuenta = '" . $codsubcuenta . "';";
        foreach ($this->database->select($sql_find_prov) as $row) {
            $proveedor_model = new proveedor();
            $proveedor = $proveedor_model->get($row['codproveedor']);
            if ($proveedor) {
                return $proveedor->get_subcuenta($new_codejercicio);
            }
        }

        $subcuenta = new subcuenta();
        return $subcuenta->get_by_codigo($codsubcuenta, $new_codejercicio, TRUE);
    }
}
