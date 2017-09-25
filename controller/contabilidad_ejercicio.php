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

class contabilidad_ejercicio extends fbase_controller
{

    public $asiento_apertura_url;
    public $asiento_cierre_url;
    public $asiento_pyg_url;
    public $ejercicio;
    public $listado;
    public $listar;
    public $offset;
    public $url_recarga;

    public function __construct()
    {
        parent::__construct(__CLASS__, 'Ejercicio', 'contabilidad', FALSE, FALSE);
    }

    protected function private_core()
    {
        parent::private_core();

        /// cargamos las putas secuencias para que se actualicen.
        /// Abanq/Eneboo, yo te maldigooooo!!!!!!!!!!!!!!!!!!!!!!
        new secuencia_ejercicio();
        new secuencia_contabilidad();
        new secuencia();

        $this->ejercicio = FALSE;
        if (isset($_POST['codejercicio'])) {
            $eje0 = new ejercicio();
            $this->ejercicio = $eje0->get($_POST['codejercicio']);
            if ($this->ejercicio) {
                $this->ejercicio->nombre = $_POST['nombre'];
                $this->ejercicio->fechainicio = $_POST['fechainicio'];
                $this->ejercicio->fechafin = $_POST['fechafin'];
                $this->ejercicio->longsubcuenta = intval($_POST['longsubcuenta']);
                $this->ejercicio->estado = $_POST['estado'];
                if ($this->ejercicio->save()) {
                    $this->new_message('Datos guardados correctamente.');
                } else
                    $this->new_error_msg('Imposible guardar los datos.');
            }
        }
        else if (isset($_GET['cod'])) {
            $eje0 = new ejercicio();
            $this->ejercicio = $eje0->get($_GET['cod']);
        }

        if ($this->ejercicio) {
            if (isset($_GET['export'])) {
                $this->exportar_xml();
            } else {
                $this->page->title = $this->ejercicio->codejercicio . ' (' . $this->ejercicio->nombre . ')';

                if (isset($_GET['cerrar']) && isset($_GET['petid'])) {
                    if ($this->duplicated_petition($_GET['petid'])) {
                        $this->new_error_msg('Petición duplicada. Evita hacer doble clic sobre los botones.');
                    } else
                        $this->cerrar_ejercicio();
                }
                else if (isset($_GET['cerrar2'])) {
                    $this->cerrar_ejercicio2();
                } else {
                    $this->ejercicio->full_test();
                    $this->check_asientos();

                    /// comprobamos el proceso de importación
                    $this->importar_xml();
                }

                $this->offset = 0;
                if (isset($_GET['offset'])) {
                    $this->offset = intval($_GET['offset']);
                }

                if (!isset($_GET['listar'])) {
                    $this->listar = 'cuentas';
                } else if ($_GET['listar'] == 'grupos') {
                    $this->listar = 'grupos';
                } else if ($_GET['listar'] == 'epigrafes') {
                    $this->listar = 'epigrafes';
                } else if ($_GET['listar'] == 'subcuentas') {
                    $this->listar = 'subcuentas';
                } else
                    $this->listar = 'cuentas';

                switch ($this->listar) {
                    default:
                        $cuenta = new cuenta();
                        $this->listado = $cuenta->full_from_ejercicio($this->ejercicio->codejercicio);
                        break;

                    case 'grupos';
                        $ge = new grupo_epigrafes();
                        $this->listado = $ge->all_from_ejercicio($this->ejercicio->codejercicio);
                        break;

                    case 'epigrafes':
                        $epigrafe = new epigrafe();
                        $this->listado = $epigrafe->all_from_ejercicio($this->ejercicio->codejercicio);
                        break;

                    case 'subcuentas':
                        $subcuenta = new subcuenta();
                        $this->listado = $subcuenta->all_from_ejercicio($this->ejercicio->codejercicio);
                        break;
                }
            }
        } else {
            $this->new_error_msg('Ejercicio no encontrado.', 'error', FALSE, FALSE);
        }
    }

    public function url()
    {
        if (!isset($this->ejercicio)) {
            return parent::url();
        } else if ($this->ejercicio) {
            return $this->ejercicio->url();
        }
        
        return parent::url();
    }

    private function check_asientos()
    {
        $asiento = new asiento();

        $this->asiento_apertura_url = FALSE;
        if ($this->ejercicio->idasientoapertura) {
            $asiento_a = $asiento->get($this->ejercicio->idasientoapertura);
            if ($asiento_a) {
                $this->asiento_apertura_url = $asiento_a->url();
            }
        }

        $this->asiento_cierre_url = FALSE;
        if ($this->ejercicio->idasientocierre) {
            $asiento_c = $asiento->get($this->ejercicio->idasientocierre);
            if ($asiento_c) {
                $this->asiento_cierre_url = $asiento_c->url();
            }
        }

        $this->asiento_pyg_url = FALSE;
        if ($this->ejercicio->idasientopyg) {
            $asiento_pyg = $asiento->get($this->ejercicio->idasientopyg);
            if ($asiento_pyg) {
                $this->asiento_pyg_url = $asiento_pyg->url();
            }
        }
    }

    private function exportar_xml()
    {
        /// desactivamos el motor de plantillas
        $this->template = FALSE;

        /// creamos el xml
        $cadena_xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
<!--
    Document   : ejercicio_" . $this->ejercicio->codejercicio . ".xml
    Description:
        Estructura de grupos de epígrafes, epígrafes, cuentas y subcuentas del ejercicio " .
            $this->ejercicio->codejercicio . ".
-->

<ejercicio>
</ejercicio>\n";
        $archivo_xml = simplexml_load_string($cadena_xml);

        /// añadimos los balances
        $balance = new balance();
        foreach ($balance->all() as $ba) {
            $aux = $archivo_xml->addChild("balance");
            $aux->addChild("codbalance", $ba->codbalance);
            $aux->addChild("naturaleza", $ba->naturaleza);
            $aux->addChild("nivel1", $ba->nivel1);
            $aux->addChild("descripcion1", base64_encode($ba->descripcion1));
            $aux->addChild("nivel2", $ba->nivel2);
            $aux->addChild("descripcion2", base64_encode($ba->descripcion2));
            $aux->addChild("nivel3", $ba->nivel3);
            $aux->addChild("descripcion3", base64_encode($ba->descripcion3));
            $aux->addChild("orden3", $ba->orden3);
            $aux->addChild("nivel4", $ba->nivel4);
            $aux->addChild("descripcion4", base64_encode($ba->descripcion4));
            $aux->addChild("descripcion4ba", base64_encode($ba->descripcion4ba));
        }

        /// añadimos las cuentas de balances
        $balance_cuenta = new balance_cuenta();
        foreach ($balance_cuenta->all() as $ba) {
            $aux = $archivo_xml->addChild("balance_cuenta");
            $aux->addChild("codbalance", $ba->codbalance);
            $aux->addChild("codcuenta", $ba->codcuenta);
            $aux->addChild("descripcion", base64_encode($ba->desccuenta));
        }

        /// añadimos las cuentas de balance abreviadas
        $balance_cuenta_a = new balance_cuenta_a();
        foreach ($balance_cuenta_a->all() as $ba) {
            $aux = $archivo_xml->addChild("balance_cuenta_a");
            $aux->addChild("codbalance", $ba->codbalance);
            $aux->addChild("codcuenta", $ba->codcuenta);
            $aux->addChild("descripcion", base64_encode($ba->desccuenta));
        }

        /// añadimos las cuentas especiales
        $cuenta_esp = new cuenta_especial();
        foreach ($cuenta_esp->all() as $ce) {
            $aux = $archivo_xml->addChild("cuenta_especial");
            $aux->addChild("idcuentaesp", $ce->idcuentaesp);
            $aux->addChild("descripcion", base64_encode($ce->descripcion));
        }

        /// añadimos los grupos de epigrafes
        $grupo_epigrafes = new grupo_epigrafes();
        $grupos_ep = $grupo_epigrafes->all_from_ejercicio($this->ejercicio->codejercicio);
        foreach ($grupos_ep as $ge) {
            $aux = $archivo_xml->addChild("grupo_epigrafes");
            $aux->addChild("codgrupo", $ge->codgrupo);
            $aux->addChild("descripcion", base64_encode($ge->descripcion));
        }

        /// añadimos los epigrafes
        $epigrafe = new epigrafe();
        foreach ($epigrafe->all_from_ejercicio($this->ejercicio->codejercicio) as $ep) {
            $aux = $archivo_xml->addChild("epigrafe");
            $aux->addChild("codgrupo", $ep->codgrupo);
            $aux->addChild("codpadre", $ep->codpadre());
            $aux->addChild("codepigrafe", $ep->codepigrafe);
            $aux->addChild("descripcion", base64_encode($ep->descripcion));
        }

        /// añadimos las cuentas
        $cuenta = new cuenta();
        $cuentas = $cuenta->full_from_ejercicio($this->ejercicio->codejercicio);
        foreach ($cuentas as $c) {
            $aux = $archivo_xml->addChild("cuenta");
            $aux->addChild("codepigrafe", $c->codepigrafe);
            $aux->addChild("codcuenta", $c->codcuenta);
            $aux->addChild("descripcion", base64_encode($c->descripcion));
            $aux->addChild("idcuentaesp", $c->idcuentaesp);
        }

        /// añadimos las subcuentas
        $subcuenta = new subcuenta();
        foreach ($subcuenta->all_from_ejercicio($this->ejercicio->codejercicio) as $sc) {
            $aux = $archivo_xml->addChild("subcuenta");
            $aux->addChild("codcuenta", $sc->codcuenta);
            $aux->addChild("codsubcuenta", $sc->codsubcuenta);
            $aux->addChild("descripcion", base64_encode($sc->descripcion));
            $aux->addChild("coddivisa", $sc->coddivisa);
        }

        /// volcamos el XML
        header("content-type: application/xml; charset=UTF-8");
        header('Content-Disposition: attachment; filename="ejercicio_' . $this->ejercicio->codejercicio . '.xml"');
        echo $archivo_xml->asXML();
    }

    private function importar_xml()
    {
        $import_step = 0;
        $this->url_recarga = FALSE;

        if (isset($_POST['fuente'])) {
            if (file_exists('tmp/' . FS_TMP_NAME . 'ejercicio.xml')) {
                unlink('tmp/' . FS_TMP_NAME . 'ejercicio.xml');
            }

            if ($_POST['fuente'] == 'archivo' && isset($_POST['archivo'])) {
                if (copy($_FILES['farchivo']['tmp_name'], 'tmp/' . FS_TMP_NAME . 'ejercicio.xml')) {
                    $import_step = 1;
                    $this->url_recarga = $this->url() . '&importar=' . (1 + $import_step);
                } else {
                    $this->new_error_msg('Error al copiar el archivo.');
                }
            } else if ($_POST['fuente'] != '') {
                if (copy($_POST['fuente'], 'tmp/' . FS_TMP_NAME . 'ejercicio.xml')) {
                    $import_step = 1;
                    $this->url_recarga = $this->url() . '&importar=' . (1 + $import_step);
                } else {
                    $this->new_error_msg('Error al copiar el archivo.');
                }
            } else {
                $this->new_error_msg('Has seleccionado importar desde un archivo externo,
               pero no has seleccionado ningún archivo.');
            }
        } else if (isset($_GET['importar'])) {
            $import_step = intval($_GET['importar']);
            if ($import_step < 7) {
                $this->url_recarga = $this->url() . '&importar=' . (1 + $import_step);
            } else {
                $this->new_advice('Datos importados correctamente &nbsp; <span class="glyphicon glyphicon-ok" aria-hidden="true"></span>');
                $import_step = 0;
            }
        }

        if (file_exists('tmp/' . FS_TMP_NAME . 'ejercicio.xml') && $import_step > 0) {
            $offset = 0;
            if (isset($_GET['offset'])) {
                $offset = intval($_GET['offset']);
            }

            if ($offset == 0) {
                $this->new_message('Importando ejercicio: paso ' . $import_step . ' de 6 ...'
                    . '<br/>Espera a que termine &nbsp; <i class="fa fa-refresh fa-spin"></i>');
            } else {
                $this->new_message('Importando ejercicio: paso ' . $import_step . '.' . ($offset / 500) . ' de 6 ...'
                    . '<br/>Espera a que termine &nbsp; <i class="fa fa-refresh fa-spin"></i>');
            }

            $xml = simplexml_load_file('tmp/' . FS_TMP_NAME . 'ejercicio.xml');
            if ($xml) {
                if ($xml->balance && $import_step == 1) {
                    foreach ($xml->balance as $b) {
                        $balance = new balance();
                        if (!$balance->get($b->codbalance)) {
                            $balance->codbalance = $b->codbalance;
                            $balance->naturaleza = $b->naturaleza;
                            $balance->nivel1 = $b->nivel1;
                            $balance->descripcion1 = base64_decode($b->descripcion1);
                            $balance->nivel2 = $balance->intval($b->nivel2);
                            $balance->descripcion2 = base64_decode($b->descripcion2);
                            $balance->nivel3 = $b->nivel3;
                            $balance->descripcion3 = base64_decode($b->descripcion3);
                            $balance->orden3 = $b->orden3;
                            $balance->nivel4 = $b->nivel4;
                            $balance->descripcion4 = base64_decode($b->descripcion4);
                            $balance->descripcion4ba = base64_decode($b->descripcion4ba);

                            if (!$balance->save()) {
                                $this->url_recarga = FALSE;
                            }
                        }
                    }

                    if ($xml->balance_cuenta) {
                        $balance_cuenta = new balance_cuenta();
                        $all_bcs = $balance_cuenta->all();
                        foreach ($xml->balance_cuenta as $bc) {
                            $encontrado = FALSE;
                            foreach ($all_bcs as $bc2) {
                                if ($bc2->codbalance == $bc->codbalance && $bc2->codcuenta == $bc->codcuenta) {
                                    $encontrado = TRUE;
                                    break;
                                }
                            }
                            if (!$encontrado) {
                                $new_bc = new balance_cuenta();
                                $new_bc->codbalance = $bc->codbalance;
                                $new_bc->codcuenta = $bc->codcuenta;
                                $new_bc->desccuenta = base64_decode($bc->descripcion);

                                if (!$new_bc->save()) {
                                    $this->url_recarga = FALSE;
                                }
                            }
                        }
                    }

                    if ($xml->balance_cuenta_a) {
                        $balance_cuenta_a = new balance_cuenta_a();
                        $all_bcas = $balance_cuenta_a->all();
                        foreach ($xml->balance_cuenta_a as $bc) {
                            $encontrado = FALSE;
                            foreach ($all_bcas as $bc2) {
                                if ($bc2->codbalance == $bc->codbalance && $bc2->codcuenta == $bc->codcuenta) {
                                    $encontrado = TRUE;
                                    break;
                                }
                            }
                            if (!$encontrado) {
                                $new_bc = new balance_cuenta_a();
                                $new_bc->codbalance = $bc->codbalance;
                                $new_bc->codcuenta = $bc->codcuenta;
                                $new_bc->desccuenta = base64_decode($bc->descripcion);

                                if (!$new_bc->save()) {
                                    $this->url_recarga = FALSE;
                                }
                            }
                        }
                    }
                }

                if ($import_step == 2) {
                    if ($xml->cuenta_especial) {
                        foreach ($xml->cuenta_especial as $ce) {
                            $cuenta_especial = new cuenta_especial();
                            if (!$cuenta_especial->get($ce->idcuentaesp)) {
                                $cuenta_especial->idcuentaesp = $ce->idcuentaesp;
                                $cuenta_especial->descripcion = base64_decode($ce->descripcion);

                                if (!$cuenta_especial->save()) {
                                    $this->url_recarga = FALSE;
                                }
                            }
                        }
                    }

                    if ($xml->grupo_epigrafes) {
                        foreach ($xml->grupo_epigrafes as $ge) {
                            $grupo_epigrafes = new grupo_epigrafes();
                            if (!$grupo_epigrafes->get_by_codigo($ge->codgrupo, $this->ejercicio->codejercicio)) {
                                $grupo_epigrafes->codejercicio = $this->ejercicio->codejercicio;
                                $grupo_epigrafes->codgrupo = $ge->codgrupo;
                                $grupo_epigrafes->descripcion = base64_decode($ge->descripcion);

                                if (!$grupo_epigrafes->save()) {
                                    $this->url_recarga = FALSE;
                                }
                            }
                        }
                    }

                    if ($xml->epigrafe) {
                        $grupo_epigrafes = new grupo_epigrafes();
                        foreach ($xml->epigrafe as $ep) {
                            $epigrafe = new epigrafe();
                            if (!$epigrafe->get_by_codigo($ep->codepigrafe, $this->ejercicio->codejercicio)) {
                                $epigrafe->codejercicio = $this->ejercicio->codejercicio;
                                $epigrafe->codepigrafe = $ep->codepigrafe;
                                $epigrafe->descripcion = base64_decode($ep->descripcion);

                                $ge = $grupo_epigrafes->get_by_codigo($ep->codgrupo, $this->ejercicio->codejercicio);
                                if ($ge) {
                                    /// si encuentra el grupo, lo añade con el grupo
                                    $epigrafe->idgrupo = $ge->idgrupo;
                                    $epigrafe->codgrupo = $ge->codgrupo;
                                } else if ($ep->codpadre) {
                                    $padre = $epigrafe->get_by_codigo($ep->codpadre, $this->ejercicio->codejercicio);
                                    if ($padre) {
                                        /// si encuentra al padre, lo añade con el padre
                                        $epigrafe->idpadre = $padre->idepigrafe;
                                    }
                                }

                                if (!$epigrafe->save()) {
                                    $this->url_recarga = FALSE;
                                }
                            }
                        }
                    }
                }

                if ($xml->cuenta && $import_step == 3) {
                    $epigrafe = new epigrafe();
                    foreach ($xml->cuenta as $c) {
                        $cuenta = new cuenta();
                        if (!$cuenta->get_by_codigo($c->codcuenta, $this->ejercicio->codejercicio)) {
                            $ep = $epigrafe->get_by_codigo($c->codepigrafe, $this->ejercicio->codejercicio);
                            if ($ep) {
                                $cuenta->idepigrafe = $ep->idepigrafe;
                                $cuenta->codepigrafe = $ep->codepigrafe;
                                $cuenta->codcuenta = $c->codcuenta;
                                $cuenta->codejercicio = $this->ejercicio->codejercicio;
                                $cuenta->descripcion = base64_decode($c->descripcion);
                                $cuenta->idcuentaesp = $c->idcuentaesp;

                                if (!$cuenta->save()) {
                                    $this->url_recarga = FALSE;
                                }
                            }
                        }
                    }
                }

                if ($xml->subcuenta && $import_step == 4) {
                    $cuenta = new cuenta();
                    foreach ($xml->subcuenta as $sc) {
                        $subcuenta = new subcuenta();
                        if (!$subcuenta->get_by_codigo($sc->codsubcuenta, $this->ejercicio->codejercicio)) {
                            $cu = $cuenta->get_by_codigo($sc->codcuenta, $this->ejercicio->codejercicio);
                            if ($cu) {
                                $subcuenta->idcuenta = $cu->idcuenta;
                                $subcuenta->codcuenta = $cu->codcuenta;
                                $subcuenta->coddivisa = $this->empresa->coddivisa;
                                if (isset($sc->coddivisa)) {
                                    $subcuenta->coddivisa = $sc->coddivisa;
                                }
                                $subcuenta->codejercicio = $this->ejercicio->codejercicio;
                                $subcuenta->codsubcuenta = $sc->codsubcuenta;
                                $subcuenta->descripcion = base64_decode($sc->descripcion);

                                if (strlen($sc->codsubcuenta) != $this->ejercicio->longsubcuenta) {
                                    $this->new_error_msg('La subcuenta tiene una longitud de ' . strlen($sc->codsubcuenta)
                                        . ', mientras que el ejercicio tiene definida una longitud de: ' . $this->ejercicio->longsubcuenta
                                        . '. Debeas cambiarla para evitar problemas.');
                                    $this->url_recarga = FALSE;
                                    break;
                                } else if (!$subcuenta->save()) {
                                    $this->url_recarga = FALSE;
                                }
                            }
                        }
                    }
                }

                if ($import_step == 5) {
                    $error = FALSE;
                    $cliente = new cliente();
                    $clientes = $cliente->all($offset);
                    while (!empty($clientes)) {
                        foreach ($clientes as $cli) {
                            /// forzamos la generación y asociación de una subcuenta para el cliente
                            if ($cli->get_subcuenta($this->ejercicio->codejercicio)) {
                                $offset++;
                            } else {
                                $error = TRUE;
                                break;
                            }
                        }

                        if ($error || count($this->get_errors()) > 0) {
                            $this->new_error_msg('Proceso detenido.');
                            $this->url_recarga = FALSE;
                            break;
                        } else if ($offset % 500 == 0) {
                            /// cada 500 clientes volvemos a recargar la página para continuar
                            $this->url_recarga = $this->url() . '&importar=' . $import_step . '&offset=' . $offset;
                            break;
                        } else {
                            $clientes = $cliente->all($offset);
                        }
                    }
                }

                if ($import_step == 6) {
                    $error = FALSE;
                    $proveedor = new proveedor();
                    $proveedores = $proveedor->all($offset);
                    while (!empty($proveedores)) {
                        foreach ($proveedores as $pro) {
                            /// forzamos la generación y asociación de una subcuenta para cada proveedor
                            if ($pro->get_subcuenta($this->ejercicio->codejercicio)) {
                                $offset++;
                            } else {
                                $error = TRUE;
                                break;
                            }
                        }

                        if ($error || count($this->get_errors()) > 0) {
                            $this->new_error_msg('Proceso detenido.');
                            $this->url_recarga = FALSE;
                            break;
                        } else if ($offset % 500 == 0) {
                            /// cada 500 proveedores volvemos a recargar la página para continuar
                            $this->url_recarga = $this->url() . '&importar=' . $import_step . '&offset=' . $offset;
                            break;
                        } else {
                            $proveedores = $proveedor->all($offset);
                        }
                    }
                }
            } else {
                $this->new_error_msg("Imposible leer el archivo.");
            }
        }
    }

    private function cerrar_ejercicio()
    {
        $this->new_message('Cerrando ejercicio...');

        $cie = new cierre_ejercicio($this->ejercicio);
        $continuar = $cie->paso1();

        if ($continuar) {
            /// actualizamos los saldos de las subcuentas:
            $subcuenta = new subcuenta();
            foreach ($subcuenta->all_from_ejercicio($this->ejercicio->codejercicio) as $sc) {
                $sc->save();
            }

            $this->new_message('Recargando... &nbsp; <i class="fa fa-refresh fa-spin"></i>');
            $this->url_recarga = $this->url() . '&cerrar2=TRUE';
        } else {
            $this->new_error_msg('Se han producido errores al comprobar el ejercicio. Proceso abortado.');
            foreach ($cie->get_errors() as $err) {
                $this->new_error_msg($err);
            }
        }
    }

    private function cerrar_ejercicio2()
    {
        $this->new_message('Cerrando ejercicio...');

        $cie = new cierre_ejercicio($this->ejercicio);
        $continuar = $cie->paso2();

        if ($continuar) {
            $this->new_message('Ejercicio cerrado correctamente &nbsp; <span class="glyphicon glyphicon-ok" aria-hidden="true"></span>');
        } else {
            $this->new_error_msg('Se han producido errores. Proceso abortado.');
            foreach ($cie->get_errors() as $err) {
                $this->new_error_msg($err);
            }
        }
    }
}
