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

class informe_errores extends fs_controller
{

    public $ajax;
    public $ejercicio;
    public $errores;
    public $informe;
    public $mostrar_cancelar;

    public function __construct()
    {
        parent::__construct(__CLASS__, 'Errores', 'informes', FALSE, TRUE);
    }

    protected function private_core()
    {
        $this->ajax = FALSE;
        $this->ejercicio = new ejercicio();
        $this->errores = array();
        $this->informe = array(
            'model' => 'tablas',
            'duplicados' => isset($_POST['duplicados']),
            'offset' => 0,
            'pages' => 0,
            'show_page' => 0,
            'started' => FALSE,
            'all' => FALSE,
            'ejercicio' => ''
        );
        $this->mostrar_cancelar = FALSE;

        if (isset($_GET['cancelar'])) {
            if (file_exists('tmp/' . FS_TMP_NAME . 'informe_errores.txt')) {
                unlink('tmp/' . FS_TMP_NAME . 'informe_errores.txt');
            }
            $this->cache->clean();
        } else if (file_exists('tmp/' . FS_TMP_NAME . 'informe_errores.txt')) { /// continua examinando
            $this->continuar_test();
        } else if (isset($_POST['modelo'])) { /// empieza a examinar
            $this->iniciar_test();
        }
    }

    private function iniciar_test()
    {
        $file = fopen('tmp/' . FS_TMP_NAME . 'informe_errores.txt', 'w');
        if ($file) {
            $this->mostrar_cancelar = TRUE;

            if ($_POST['modelo'] == 'todo') {
                $this->informe['model'] = 'tablas';
                $this->informe['started'] = TRUE;
                $this->informe['all'] = TRUE;
            } else if ($_POST['modelo'] != '') {
                $this->informe['model'] = $_POST['modelo'];
                $this->informe['started'] = TRUE;
            }

            if (isset($_POST['ejercicio'])) {
                $this->informe['ejercicio'] = $_POST['ejercicio'];
            }

            if (isset($_GET['show_page'])) {
                $this->informe['show_page'] = intval($_GET['show_page']);
            }

            /// guardamos esta configuración
            fwrite($file, join(';', $this->informe) . "\n--------------------------------------------------------\n");
            fclose($file);
        }
    }

    private function continuar_test()
    {
        $file = fopen('tmp/' . FS_TMP_NAME . 'informe_errores.txt', 'r+');
        if ($file) {
            /*
             * leemos el archivo tmp/informe_errores.txt donde guardamos los datos
             * y extraemos la configuración y los errores de la "página" seleccionada
             */
            $linea = explode(';', trim(fgets($file)));
            if (count($linea) == 8) {
                $this->informe['model'] = $linea[0];
                $this->informe['duplicados'] = ($linea[1] == 1);
                $this->informe['offset'] = intval($linea[2]);
                $this->informe['pages'] = intval($linea[3]);

                if (isset($_POST['show_page'])) {
                    $this->informe['show_page'] = intval($_POST['show_page']);
                } else if (isset($_GET['show_page'])) {
                    $this->informe['show_page'] = intval($_GET['show_page']);
                } else
                    $this->informe['show_page'] = intval($linea[4]);

                $this->informe['started'] = ($linea[5] == 1);
                $this->informe['all'] = ($linea[6] == 1);
                $this->informe['ejercicio'] = $linea[7];
            }

            if (isset($_REQUEST['ajax'])) {
                $this->ajax = TRUE;

                /// leemos los errores de la "página" seleccionada
                $numlinea = 0;
                while (!feof($file)) {
                    $linea = explode(';', trim(fgets($file)));
                    if (count($linea) == 7) {
                        if ($numlinea > $this->informe['show_page'] * FS_ITEM_LIMIT && $numlinea <= (1 + $this->informe['show_page']) * FS_ITEM_LIMIT) {
                            $this->errores[] = array(
                                'error' => $linea[0],
                                'model' => $linea[1],
                                'ejercicio' => $linea[2],
                                'id' => $linea[3],
                                'url' => $linea[4],
                                'fecha' => $linea[5],
                                'fix' => ($linea[6] == 1)
                            );
                        }
                    }

                    $numlinea++;
                }

                $new_results = $this->test_models();
                if (!empty($new_results)) {
                    foreach ($new_results as $nr) {
                        $this->errores[] = $nr;
                        fwrite($file, join(';', $nr) . "\n");
                        $numlinea++;
                    }
                }

                $this->informe['pages'] = intval($numlinea / FS_ITEM_LIMIT);

                /// guardamos la configuración
                rewind($file);
                fwrite($file, join(';', $this->informe) . "\n------\n");
            } else
                $this->mostrar_cancelar = TRUE;

            fclose($file);
        }
    }

    private function test_models()
    {
        $last_errores = array();

        switch ($this->informe['model']) {
            default:
                /// tablas
                $this->test_tablas();
                break;

            case 'ejercicio':
                $sc0 = new subcuenta();
                foreach ($this->ejercicio->all() as $eje) {
                    if (!$eje->abierto()) {
                        /// comprobamos que los saldos de las subcuentas estén a 0
                        foreach ($sc0->all_from_ejercicio($eje->codejercicio) as $sc) {
                            if (!$sc0->floatcmp($sc->saldo, 0, FS_NF0, TRUE)) {
                                $err = array(
                                    'error' => 'Ejercicio cerrado pero la subcuenta tiene saldo: '
                                    . $this->show_precio($sc->saldo, $sc->coddivisa),
                                    'model' => $this->informe['model'],
                                    'ejercicio' => $sc->codejercicio,
                                    'id' => $sc->codsubcuenta,
                                    'url' => $sc->url(),
                                    'fecha' => $eje->fechafin,
                                    'fix' => FALSE
                                );

                                /// intentamos corregir
                                $sc->save();
                                if ($sc0->floatcmp($sc->saldo, 0, FS_NF0, TRUE)) {
                                    $err['fix'] = TRUE;
                                }

                                $last_errores[] = $err;
                            }
                        }
                    }
                }
                $this->informe['offset'] = 0;
                $this->informe['model'] = 'fin';
                if ($this->informe['all']) {
                    $this->informe['model'] = 'asiento';
                }
                break;

            case 'asiento':
                $asiento = new asiento();
                $asientos = $asiento->all($this->informe['offset']);
                if (!empty($asientos)) {
                    if ($this->informe['offset'] == 0) {
                        foreach ($this->check_partidas_erroneas() as $err) {
                            $last_errores[] = $err;
                        }
                    }

                    foreach ($asientos as $asi) {
                        if ($asi->codejercicio == $this->informe['ejercicio']) {
                            $this->informe['offset'] = 0;
                            $this->informe['model'] = 'fin';
                            if ($this->informe['all']) {
                                $this->informe['model'] = 'factura cliente';
                            }
                            break;
                        } else if (!$asi->full_test($this->informe['duplicados'])) {
                            $last_errores[] = array(
                                'error' => 'Fallo en full_test()',
                                'model' => $this->informe['model'],
                                'ejercicio' => $asi->codejercicio,
                                'id' => $asi->numero,
                                'url' => $asi->url(),
                                'fecha' => $asi->fecha,
                                'fix' => $asi->fix()
                            );
                        }
                    }
                    $this->informe['offset'] += FS_ITEM_LIMIT;
                } else if ($this->informe['all']) {
                    $this->informe['model'] = 'factura cliente';
                    $this->informe['offset'] = 0;
                } else {
                    $this->informe['model'] = 'fin';
                    $this->informe['offset'] = 0;
                }
                break;

            case 'factura cliente':
                $factura = new factura_cliente();
                $facturas = $factura->all($this->informe['offset']);
                if (!empty($facturas)) {
                    foreach ($facturas as $fac) {
                        if ($fac->codejercicio == $this->informe['ejercicio']) {
                            $this->informe['offset'] = 0;
                            $this->informe['model'] = 'fin';
                            if ($this->informe['all']) {
                                $this->informe['model'] = 'factura proveedor';
                            }
                            break;
                        } else if (!$fac->full_test($this->informe['duplicados'])) {
                            $last_errores[] = array(
                                'error' => 'Fallo en full_test()',
                                'model' => $this->informe['model'],
                                'ejercicio' => $fac->codejercicio,
                                'id' => $fac->codigo,
                                'url' => $fac->url(),
                                'fecha' => $fac->fecha,
                                'fix' => FALSE
                            );
                        }
                    }
                    $this->informe['offset'] += FS_ITEM_LIMIT;
                } else if ($this->informe['all']) {
                    $this->informe['model'] = 'factura proveedor';
                    $this->informe['offset'] = 0;
                } else {
                    $this->informe['model'] = 'fin';
                    $this->informe['offset'] = 0;
                }
                break;

            case 'factura proveedor':
                $factura = new factura_proveedor();
                $facturas = $factura->all($this->informe['offset']);
                if (!empty($facturas)) {
                    foreach ($facturas as $fac) {
                        if ($fac->codejercicio == $this->informe['ejercicio']) {
                            $this->informe['offset'] = 0;
                            $this->informe['model'] = 'fin';
                            if ($this->informe['all']) {
                                $this->informe['model'] = 'albaran cliente';
                            }
                            break;
                        } else if (!$fac->full_test($this->informe['duplicados'])) {
                            $last_errores[] = array(
                                'error' => 'Fallo en full_test()',
                                'model' => $this->informe['model'],
                                'ejercicio' => $fac->codejercicio,
                                'id' => $fac->codigo,
                                'url' => $fac->url(),
                                'fecha' => $fac->fecha,
                                'fix' => FALSE
                            );
                        }
                    }
                    $this->informe['offset'] += FS_ITEM_LIMIT;
                } else if ($this->informe['all']) {
                    $this->informe['model'] = 'albaran cliente';
                    $this->informe['offset'] = 0;
                } else {
                    $this->informe['model'] = 'fin';
                    $this->informe['offset'] = 0;
                }
                break;

            case 'albaran cliente':
                $albaran = new albaran_cliente();
                $albaranes = $albaran->all($this->informe['offset']);
                if (!empty($albaranes)) {
                    foreach ($albaranes as $alb) {
                        if ($alb->codejercicio == $this->informe['ejercicio']) {
                            $this->informe['offset'] = 0;
                            $this->informe['model'] = 'fin';
                            if ($this->informe['all']) {
                                $this->informe['model'] = 'albaran proveedor';
                            }
                            break;
                        } else if (!$alb->full_test($this->informe['duplicados'])) {
                            $last_errores[] = array(
                                'error' => 'Fallo en full_test()',
                                'model' => $this->informe['model'],
                                'ejercicio' => $alb->codejercicio,
                                'id' => $alb->codigo,
                                'url' => $alb->url(),
                                'fecha' => $alb->fecha,
                                'fix' => FALSE
                            );
                        }
                    }
                    $this->informe['offset'] += FS_ITEM_LIMIT;
                } else if ($this->informe['all']) {
                    $this->informe['model'] = 'albaran proveedor';
                    $this->informe['offset'] = 0;
                } else {
                    $this->informe['model'] = 'fin';
                    $this->informe['offset'] = 0;
                }
                break;

            case 'albaran proveedor':
                $albaran = new albaran_proveedor();
                $albaranes = $albaran->all($this->informe['offset']);
                if (!empty($albaranes)) {
                    foreach ($albaranes as $alb) {
                        if ($alb->codejercicio == $this->informe['ejercicio']) {
                            $this->informe['model'] = 'fin';
                            $this->informe['offset'] = 0;
                            break;
                        } else if (!$alb->full_test($this->informe['duplicados'])) {
                            $last_errores[] = array(
                                'error' => 'Fallo en full_test()',
                                'model' => $this->informe['model'],
                                'ejercicio' => $alb->codejercicio,
                                'id' => $alb->codigo,
                                'url' => $alb->url(),
                                'fecha' => $alb->fecha,
                                'fix' => FALSE
                            );
                        }
                    }
                    $this->informe['offset'] += FS_ITEM_LIMIT;
                } else {
                    $this->informe['model'] = 'dirclientes';
                    $this->informe['offset'] = 0;
                }
                break;

            case 'dirclientes':
                $dircli0 = new direccion_cliente();
                $direcciones = $dircli0->all($this->informe['offset']);
                if (!empty($direcciones)) {
                    foreach ($direcciones as $dir) {
                        /// simplemente guardamos para que se eliminen espacios de ciudades, provincias, etc...
                        $dir->save();
                    }

                    $this->informe['offset'] += FS_ITEM_LIMIT;
                } else {
                    $this->informe['model'] = 'fin';
                    $this->informe['offset'] = 0;
                }
                break;

            case 'fin':
                break;
        }

        return $last_errores;
    }

    public function all_pages()
    {
        $allp = array();
        $show_p = $this->informe['show_page'];

        /// cargamos todas las páginas
        for ($i = 0; $i <= $this->informe['pages']; $i++) {
            $allp[] = array('page' => $i, 'num' => $i + 1, 'selected' => ($i == $show_p));
        }

        /// ahora descartamos
        foreach ($allp as $j => $value) {
            if (($value['num'] > 1 && $j < $show_p - 3 && $value['num'] % 10) || ( $j > $show_p + 3 && $j < $i - 1 && $value['num'] % 10)) {
                unset($allp[$j]);
            }
        }

        return $allp;
    }

    private function check_partidas_erroneas()
    {
        $errores = array();
        $asient0 = new asiento();

        foreach ($this->ejercicio->all() as $eje) {
            $sql = "SELECT * FROM co_partidas WHERE idasiento IN
            (SELECT idasiento FROM co_asientos WHERE codejercicio = " . $eje->var2str($eje->codejercicio) . ")
            AND idsubcuenta NOT IN (SELECT idsubcuenta FROM co_subcuentas WHERE codejercicio = " . $eje->var2str($eje->codejercicio) . ");";
            $data = $this->db->select($sql);
            if ($data) {
                foreach ($data as $d) {
                    $asiento = $asient0->get($d['idasiento']);
                    if ($asiento) {
                        $errores[] = array(
                            'error' => 'Subcuenta ' . $d['codsubcuenta'] . ' no pertenece al mismo ejercicio que el asiento',
                            'model' => 'asiento',
                            'ejercicio' => $eje->codejercicio,
                            'id' => $asiento->numero,
                            'url' => $asiento->url(),
                            'fecha' => $asiento->fecha,
                            'fix' => FALSE
                        );
                    }
                }
            }
        }

        return $errores;
    }

    private function test_tablas()
    {
        $recargar = TRUE;

        switch ($this->informe['offset']) {
            case 0:
                /// comprobamos la tabla familias
                $familia = new familia();
                $familia->fix_db();
                break;

            case 1:
                /// comprobamos artículos
                $articulo = new articulo();
                $articulo->fix_db();
                break;

            case 2:
                /// comprobamos artículos de proveedor
                $artp = new articulo_proveedor();
                $artp->fix_db();
                break;

            case 3:
                /// comprobamos el stock
                $stock = new stock();
                $stock->fix_db();
                break;

            case 4:
                /// comprobamos las regularizaciones de stock
                $regstock = new regularizacion_stock();
                $regstock->fix_db();
                break;

            case 5:
                /// eliminamos los elementos de contabilidad que apuntan a ejercicios que no existen
                $tablas = array('co_gruposepigrafes', 'co_epigrafes', 'co_cuentas', 'co_subcuentas');
                foreach ($tablas as $tabla) {
                    $this->db->exec("DELETE FROM " . $tabla . " WHERE codejercicio NOT IN (SELECT codejercicio FROM ejercicios);");
                }

                /// comprobamos los epígrafes
                $epi = new epigrafe();
                $epi->fix_db();
                break;

            case 6:
                /// comprobamos las direcciones de clientes
                $dircli = new direccion_cliente();
                $dircli->fix_db();
                break;

            case 7:
                /// comprobamos las subcuentas de clientes
                $subcli = new subcuenta_cliente();
                $subcli->fix_db();
                break;

            case 8:
                /// comprobamos las direcciones de proveedores
                $dirpro = new direccion_proveedor();
                $dirpro->fix_db();
                break;

            case 9:
                /// comprobamos las subcuentas de proveedores
                $subpro = new subcuenta_proveedor();
                $subpro->fix_db();
                break;

            case 10:
                /// comprobamos los clientes
                $cliente = new cliente();
                $cliente->fix_db();
                break;

            case 11:
                /// comprobamos los proveedores
                $proveedor = new proveedor();
                $proveedor->fix_db();
                break;

            case 12:
                $alcli = new albaran_cliente();
                $alcli->cron_job();
                break;

            case 13:
                $albpro = new albaran_proveedor();
                $albpro->cron_job();
                break;

            case 14:
                /// creamos valores por defecto, en caso de que no existan
                $almacen = new almacen();
                if (!$almacen->all()) {
                    $this->db->exec($almacen->install());
                }

                $divisa = new divisa();
                if (!$divisa->all()) {
                    $this->db->exec($divisa->install());
                }

                $formap = new forma_pago();
                if (!$formap->all()) {
                    $this->db->exec($formap->install());
                }

                $pais = new pais();
                if (!$pais->all()) {
                    $this->db->exec($pais->install());
                }

                $serie = new serie();
                if (!$serie->all()) {
                    $this->db->exec($serie->install());
                }

            default:
                $recargar = FALSE;
        }

        if ($recargar) {
            $this->informe['offset'] ++;
        } else {
            if ($this->informe['all']) {
                $this->informe['model'] = 'ejercicio';
            } else {
                $this->informe['model'] = 'fin';
            }

            $this->informe['offset'] = 0;
        }
    }
}
