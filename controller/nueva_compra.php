<?php
/*
 * This file is part of facturacion_base
 * Copyright (C) 2014-2017  Carlos Garcia Gomez  neorazorx@gmail.com
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

class nueva_compra extends fbase_controller
{

    public $agente;
    public $almacen;
    public $articulo;
    public $articulo_prov;
    public $divisa;
    public $fabricante;
    public $familia;
    public $forma_pago;
    public $impuesto;
    public $proveedor;
    public $proveedor_s;
    public $results;
    public $serie;
    public $tipo;

    public function __construct()
    {
        parent::__construct(__CLASS__, 'Nueva compra...', 'compras', FALSE, FALSE, TRUE);
    }

    protected function private_core()
    {
        parent::private_core();

        $this->articulo_prov = new articulo_proveedor();
        $this->fabricante = new fabricante();
        $this->familia = new familia();
        $this->impuesto = new impuesto();
        $this->proveedor = new proveedor();
        $this->proveedor_s = FALSE;
        $this->results = array();

        if (isset($_REQUEST['tipo'])) {
            $this->tipo = $_REQUEST['tipo'];
        } else {
            foreach ($this->tipos_a_guardar() as $t) {
                $this->tipo = $t['tipo'];
                break;
            }
        }

        if (isset($_REQUEST['buscar_proveedor'])) {
            $this->fbase_buscar_proveedor($_REQUEST['buscar_proveedor']);
        } else if (isset($_REQUEST['datosproveedor'])) {
            $this->datos_proveedor();
        } else if (isset($_REQUEST['new_articulo'])) {
            $this->new_articulo();
        } else if ($this->query != '') {
            $this->new_search();
        } else if (isset($_POST['referencia4precios'])) {
            $this->get_precios_articulo();
        } else if (isset($_POST['referencia4combi'])) {
            $this->get_combinaciones_articulo();
        } else if (isset($_POST['proveedor'])) {
            $this->proveedor_s = $this->proveedor->get($_POST['proveedor']);

            if (isset($_POST['nuevo_proveedor']) && $_POST['nuevo_proveedor'] != '') {
                $this->proveedor_s = FALSE;
                if ($_POST['nuevo_cifnif'] != '') {
                    $this->proveedor_s = $this->proveedor->get_by_cifnif($_POST['nuevo_cifnif']);
                    if ($this->proveedor_s) {
                        $this->new_advice('Ya existe un proveedor con ese ' . FS_CIFNIF . '. Se ha seleccionado.');
                    }
                }

                if (!$this->proveedor_s) {
                    $this->proveedor_s = new proveedor();
                    $this->proveedor_s->codproveedor = $this->proveedor_s->get_new_codigo();
                    $this->proveedor_s->nombre = $this->proveedor_s->razonsocial = $_POST['nuevo_proveedor'];
                    $this->proveedor_s->tipoidfiscal = $_POST['nuevo_tipoidfiscal'];
                    $this->proveedor_s->cifnif = $_POST['nuevo_cifnif'];
                    $this->proveedor_s->acreedor = isset($_POST['acreedor']);
                    $this->proveedor_s->personafisica = isset($_POST['personafisica']);
                    $this->proveedor_s->save();

                    if ($this->empresa->contintegrada) {
                        /// forzamos crear la subcuenta
                        $this->proveedor_s->get_subcuenta($this->empresa->codejercicio);
                    }
                }
            }

            if (isset($_POST['codagente'])) {
                $agente = new agente();
                $this->agente = $agente->get($_POST['codagente']);
            } else {
                $this->agente = $this->user->get_agente();
            }

            $this->almacen = new almacen();
            $this->serie = new serie();
            $this->forma_pago = new forma_pago();
            $this->divisa = new divisa();

            if (isset($_POST['tipo'])) {
                if ($_POST['tipo'] == 'factura') {
                    $this->nueva_factura_proveedor();
                } else if ($_POST['tipo'] == 'albaran') {
                    $this->nuevo_albaran_proveedor();
                } else if ($_POST['tipo'] == 'pedido' && class_exists('pedido_proveedor')) {
                    $this->nuevo_pedido_proveedor();
                }
            }
        }
    }

    /**
     * Devuelve los tipos de documentos a guardar,
     * así para añadir tipos no hay que tocar la vista.
     * @return type
     */
    public function tipos_a_guardar()
    {
        $tipos = array();

        if ($this->user->have_access_to('compras_pedido') && class_exists('pedido_proveedor')) {
            $tipos[] = array('tipo' => 'pedido', 'nombre' => ucfirst(FS_PEDIDO) . ' de compra');
        }

        if ($this->user->have_access_to('compras_albaran')) {
            $tipos[] = array('tipo' => 'albaran', 'nombre' => ucfirst(FS_ALBARAN) . ' de compra');
        }

        if ($this->user->have_access_to('compras_factura')) {
            $tipos[] = array('tipo' => 'factura', 'nombre' => 'Factura de compra');
        }

        return $tipos;
    }

    public function url()
    {
        return 'index.php?page=' . __CLASS__ . '&tipo=' . $this->tipo;
    }

    private function datos_proveedor()
    {
        /// desactivamos la plantilla HTML
        $this->template = FALSE;

        header('Content-Type: application/json');
        echo json_encode($this->proveedor->get($_REQUEST['datosproveedor']));
    }

    private function new_articulo()
    {
        /// desactivamos la plantilla HTML
        $this->template = FALSE;

        $art0 = new articulo();
        if ($_REQUEST['referencia'] != '') {
            $art0->referencia = $_REQUEST['referencia'];
        } else {
            $art0->referencia = $art0->get_new_referencia();
        }

        if ($art0->exists()) {
            $this->results[] = $art0->get($art0->referencia);
        } else {
            $art0->descripcion = $_REQUEST['descripcion'];
            $art0->codbarras = $_REQUEST['codbarras'];
            $art0->set_impuesto($_REQUEST['codimpuesto']);
            $art0->set_pvp(floatval($_REQUEST['pvp']));
            $art0->costemedio = floatval($_REQUEST['coste']);
            $art0->preciocoste = floatval($_REQUEST['coste']);

            $art0->secompra = isset($_POST['secompra']);
            $art0->sevende = isset($_POST['sevende']);
            $art0->nostock = isset($_POST['nostock']);
            $art0->publico = isset($_POST['publico']);

            if ($_POST['codfamilia'] != '') {
                $art0->codfamilia = $_REQUEST['codfamilia'];
            }

            if ($_POST['codfabricante'] != '') {
                $art0->codfabricante = $_REQUEST['codfabricante'];
            }

            if ($_POST['refproveedor'] != '' && $_POST['refproveedor'] != $_POST['referencia']) {
                $art0->equivalencia = $_POST['refproveedor'];
            }

            if ($art0->save()) {
                $art0->coste = floatval($_POST['coste']);
                $art0->dtopor = 0;

                /// buscamos y guardamos el artículo del proveedor
                $ap = $this->articulo_prov->get_by($art0->referencia, $_POST['codproveedor'], $_POST['refproveedor']);
                if ($ap) {
                    $art0->coste = $ap->precio;
                    $art0->dtopor = $ap->dto;
                } else {
                    $ap = new articulo_proveedor();
                    $ap->codproveedor = $_POST['codproveedor'];
                }
                $ap->referencia = $art0->referencia;
                $ap->refproveedor = $_POST['refproveedor'];
                $ap->descripcion = $art0->descripcion;
                $ap->codimpuesto = $art0->codimpuesto;
                $ap->precio = floatval($_POST['coste']);

                /// pero solamente si tiene una refproveedor asignada
                if ($_POST['refproveedor'] != '') {
                    $ap->save();
                }

                $this->results[] = $art0;
            }
        }

        header('Content-Type: application/json');
        echo json_encode($this->results);
    }

    private function new_search()
    {
        /// desactivamos la plantilla HTML
        $this->template = FALSE;

        $stock = new stock();
        $this->results = $this->search_from_proveedor();

        /// completamos los datos de la búsqueda
        foreach ($this->results as $i => $value) {
            $this->results[$i]->query = $this->query;
            $this->results[$i]->coste = $value->preciocoste();
            $this->results[$i]->dtopor = 0;
            $this->results[$i]->cantidad = 1;
            $this->results[$i]->coddivisa = $this->empresa->coddivisa;

            /// si tenemos un codproveedor, ahí que buscar el coste para este proveedor
            if (isset($_REQUEST['codproveedor'])) {
                $ap = $this->articulo_prov->get_by($value->referencia, $_REQUEST['codproveedor']);
                if ($ap) {
                    $this->results[$i]->coste = $ap->precio;
                    $this->results[$i]->dtopor = $ap->dto;
                }
            }

            /// añadimos el stock del almacén y el general
            $this->results[$i]->stockalm = $this->results[$i]->stockfis;
            if ($this->multi_almacen && isset($_REQUEST['codalmacen'])) {
                $this->results[$i]->stockalm = $stock->total_from_articulo($this->results[$i]->referencia, $_REQUEST['codalmacen']);
            }

            /// convertimos la divisa
            if (isset($_REQUEST['coddivisa']) && $_REQUEST['coddivisa'] != $this->empresa->coddivisa) {
                $this->results[$i]->coddivisa = $_REQUEST['coddivisa'];
                $this->results[$i]->coste = $this->divisa_convert($value->coste, $this->empresa->coddivisa, $_REQUEST['coddivisa']);
                $this->results[$i]->pvp = $this->divisa_convert($value->pvp, $this->empresa->coddivisa, $_REQUEST['coddivisa']);
            }
        }

        /// ejecutamos las funciones de las extensiones
        foreach ($this->extensions as $ext) {
            if ($ext->type == 'function' && $ext->params == 'new_search') {
                $name = $ext->text;
                $name($this->db, $this->results);
            }
        }

        header('Content-Type: application/json');
        echo json_encode($this->results);
    }

    private function get_precios_articulo()
    {
        /// cambiamos la plantilla HTML
        $this->template = 'ajax/nueva_compra_precios';

        $articulo = new articulo();
        $this->articulo = $articulo->get($_POST['referencia4precios']);
    }

    private function get_combinaciones_articulo()
    {
        /// cambiamos la plantilla HTML
        $this->template = 'ajax/nueva_compra_combinaciones';

        $this->results = array();
        $comb1 = new articulo_combinacion();
        foreach ($comb1->all_from_ref($_POST['referencia4combi']) as $com) {
            if (isset($this->results[$com->codigo])) {
                $this->results[$com->codigo]['desc'] .= ', ' . $com->nombreatributo . ' - ' . $com->valor;
                $this->results[$com->codigo]['txt'] .= ', ' . $com->nombreatributo . ' - ' . $com->valor;
            } else {
                $this->results[$com->codigo] = array(
                    'ref' => $_POST['referencia4combi'],
                    'desc' => base64_decode($_POST['desc']) . "\n" . $com->nombreatributo . ' - ' . $com->valor,
                    'pvp' => floatval($_POST['pvp']) + $com->impactoprecio,
                    'dto' => floatval($_POST['dto']),
                    'codimpuesto' => $_POST['codimpuesto'],
                    'txt' => $com->nombreatributo . ' - ' . $com->valor,
                    'codigo' => $com->codigo,
                    'stockfis' => $com->stockfis,
                );
            }
        }
    }

    private function nuevo_pedido_proveedor()
    {
        $continuar = TRUE;

        $proveedor = $this->proveedor->get($_POST['proveedor']);
        if (!$proveedor) {
            $this->new_error_msg('Proveedor no encontrado.');
            $continuar = FALSE;
        }

        $almacen = $this->almacen->get($_POST['almacen']);
        if ($almacen) {
            $this->save_codalmacen($_POST['almacen']);
        } else {
            $this->new_error_msg('Almacén no encontrado.');
            $continuar = FALSE;
        }

        $eje0 = new ejercicio();
        $ejercicio = $eje0->get_by_fecha($_POST['fecha'], FALSE);
        if (!$ejercicio) {
            $this->new_error_msg('Ejercicio no encontrado.');
            $continuar = FALSE;
        }

        $serie = $this->serie->get($_POST['serie']);
        if (!$serie) {
            $this->new_error_msg('Serie no encontrada.');
            $continuar = FALSE;
        }

        $forma_pago = $this->forma_pago->get($_POST['forma_pago']);
        if ($forma_pago) {
            $this->save_codpago($_POST['forma_pago']);
        } else {
            $this->new_error_msg('Forma de pago no encontrada.');
            $continuar = FALSE;
        }

        $divisa = $this->divisa->get($_POST['divisa']);
        if (!$divisa) {
            $this->new_error_msg('Divisa no encontrada.');
            $continuar = FALSE;
        }

        $pedido = new pedido_proveedor();

        if ($this->duplicated_petition($_POST['petition_id'])) {
            $this->new_error_msg('Petición duplicada. Has hecho doble clic sobre el botón guardar
               y se han enviado dos peticiones. Mira en <a href="' . $pedido->url() . '">' . FS_PEDIDOS . '</a>
               para ver si el ' . FS_PEDIDO . ' se ha guardado correctamente.');
            $continuar = FALSE;
        }

        if ($continuar) {
            $this->nuevo_documento($pedido, $proveedor, $almacen, $ejercicio, $serie, $forma_pago, $divisa);

            /// función auxiliar para implementar en los plugins que lo necesiten
            if (!fs_generar_numproveedor($pedido)) {
                $pedido->numproveedor = $_POST['numproveedor'];
            }

            if ($pedido->save()) {
                $art0 = new articulo();
                $n = floatval($_POST['numlineas']);
                for ($i = 0; $i < $n; $i++) {
                    if (isset($_POST['referencia_' . $i])) {
                        $linea = new linea_pedido_proveedor();
                        $linea->idpedido = $pedido->idpedido;
                        $this->nueva_linea($linea, $i, $serie, $proveedor);

                        $articulo = $art0->get($_POST['referencia_' . $i]);
                        if ($articulo) {
                            $linea->referencia = $articulo->referencia;
                            if ($_POST['codcombinacion_' . $i]) {
                                $linea->codcombinacion = $_POST['codcombinacion_' . $i];
                            }
                        }

                        if ($linea->save()) {
                            if ($articulo && isset($_POST['costemedio'])) {
                                if ($articulo->costemedio == 0 && $linea->cantidad > 0) {
                                    $articulo->costemedio = $linea->pvptotal / $linea->cantidad;
                                    $articulo->save();
                                }

                                $this->actualizar_precio_proveedor($pedido->codproveedor, $linea);
                            }

                            if ($linea->irpf > $pedido->irpf) {
                                $pedido->irpf = $linea->irpf;
                            }
                        } else {
                            $this->new_error_msg("¡Imposible guardar la linea con referencia: " . $linea->referencia);
                            $continuar = FALSE;
                        }
                    }
                }

                if ($continuar) {
                    /// obtenemos los subtotales por impuesto
                    foreach ($this->fbase_get_subtotales_documento($pedido->get_lineas()) as $subt) {
                        $pedido->neto += $subt['neto'];
                        $pedido->totaliva += $subt['iva'];
                        $pedido->totalirpf += $subt['irpf'];
                        $pedido->totalrecargo += $subt['recargo'];
                    }

                    $pedido->total = round($pedido->neto + $pedido->totaliva - $pedido->totalirpf + $pedido->totalrecargo, FS_NF0);

                    if (abs(floatval($_POST['atotal']) - $pedido->total) >= .02) {
                        $this->new_error_msg("El total difiere entre la vista y el controlador ("
                            . $_POST['atotal'] . " frente a " . $pedido->total . "). Debes informar del error.");
                        $pedido->delete();
                    } else if ($pedido->save()) {
                        /// Función de ejecución de tareas post guardado correcto del pedido
                        fs_documento_post_save($pedido);

                        $this->new_message("<a href='" . $pedido->url() . "'>" . ucfirst(FS_PEDIDO) . "</a> guardado correctamente.");
                        $this->new_change(ucfirst(FS_PEDIDO) . ' Proveedor ' . $pedido->codigo, $pedido->url(), TRUE);

                        if ($_POST['redir'] == 'TRUE') {
                            header('Location: ' . $pedido->url());
                        }
                    } else {
                        $this->new_error_msg("¡Imposible actualizar el <a href='" . $pedido->url() . "'>" . FS_PEDIDO . "</a>!");
                    }
                } else if ($pedido->delete()) {
                    $this->new_message(ucfirst(FS_PEDIDO) . " eliminado correctamente.");
                } else {
                    $this->new_error_msg("¡Imposible eliminar el <a href='" . $pedido->url() . "'>" . FS_PEDIDO . "</a>!");
                }
            } else {
                $this->new_error_msg("¡Imposible guardar el " . FS_PEDIDO . "!");
            }
        }
    }

    private function nuevo_albaran_proveedor()
    {
        $continuar = TRUE;

        $proveedor = $this->proveedor->get($_POST['proveedor']);
        if (!$proveedor) {
            $this->new_error_msg('Proveedor no encontrado.');
            $continuar = FALSE;
        }

        $almacen = $this->almacen->get($_POST['almacen']);
        if ($almacen) {
            $this->save_codalmacen($_POST['almacen']);
        } else {
            $this->new_error_msg('Almacén no encontrado.');
            $continuar = FALSE;
        }

        $eje0 = new ejercicio();
        $ejercicio = $eje0->get_by_fecha($_POST['fecha'], FALSE);
        if (!$ejercicio) {
            $this->new_error_msg('Ejercicio no encontrado.');
            $continuar = FALSE;
        }

        $serie = $this->serie->get($_POST['serie']);
        if (!$serie) {
            $this->new_error_msg('Serie no encontrada.');
            $continuar = FALSE;
        }

        $forma_pago = $this->forma_pago->get($_POST['forma_pago']);
        if ($forma_pago) {
            $this->save_codpago($_POST['forma_pago']);
        } else {
            $this->new_error_msg('Forma de pago no encontrada.');
            $continuar = FALSE;
        }

        $divisa = $this->divisa->get($_POST['divisa']);
        if (!$divisa) {
            $this->new_error_msg('Divisa no encontrada.');
            $continuar = FALSE;
        }

        $albaran = new albaran_proveedor();

        if ($this->duplicated_petition($_POST['petition_id'])) {
            $this->new_error_msg('Petición duplicada. Has hecho doble clic sobre el botón guardar
               y se han enviado dos peticiones. Mira en <a href="' . $albaran->url() . '">' . FS_ALBARANES . '</a>
               para ver si el ' . FS_ALBARAN . ' se ha guardado correctamente.');
            $continuar = FALSE;
        }

        if ($continuar) {
            $this->nuevo_documento($albaran, $proveedor, $almacen, $ejercicio, $serie, $forma_pago, $divisa);

            /// función auxiliar para implementar en los plugins que lo necesiten
            if (!fs_generar_numproveedor($albaran)) {
                $albaran->numproveedor = $_POST['numproveedor'];
            }

            if ($albaran->save()) {
                $trazabilidad = FALSE;

                $art0 = new articulo();
                $n = floatval($_POST['numlineas']);
                for ($i = 0; $i < $n; $i++) {
                    if (isset($_POST['referencia_' . $i])) {
                        $linea = new linea_albaran_proveedor();
                        $linea->idalbaran = $albaran->idalbaran;
                        $this->nueva_linea($linea, $i, $serie, $proveedor);

                        $articulo = $art0->get($_POST['referencia_' . $i]);
                        if ($articulo) {
                            $linea->referencia = $articulo->referencia;
                            if ($articulo->trazabilidad) {
                                $trazabilidad = TRUE;
                            }

                            if ($_POST['codcombinacion_' . $i]) {
                                $linea->codcombinacion = $_POST['codcombinacion_' . $i];
                            }
                        }

                        if ($linea->save()) {
                            if ($articulo) {
                                if (isset($_POST['stock'])) {
                                    $articulo->sum_stock($albaran->codalmacen, $linea->cantidad, isset($_POST['costemedio']), $linea->codcombinacion);
                                } else if (isset($_POST['costemedio'])) {
                                    /// modificamos virtualmente el stock para que se recalcule el coste medio
                                    $articulo->stockfis += $linea->cantidad;
                                    $articulo->costemedio = $articulo->get_costemedio();
                                    $articulo->stockfis -= $linea->cantidad;
                                    $articulo->save();
                                }

                                if (isset($_POST['costemedio'])) {
                                    $this->actualizar_precio_proveedor($albaran->codproveedor, $linea);
                                }
                            }

                            if ($linea->irpf > $albaran->irpf) {
                                $albaran->irpf = $linea->irpf;
                            }
                        } else {
                            $this->new_error_msg("¡Imposible guardar la linea con referencia: " . $linea->referencia);
                            $continuar = FALSE;
                        }
                    }
                }

                if ($continuar) {
                    /// obtenemos los subtotales por impuesto
                    foreach ($this->fbase_get_subtotales_documento($albaran->get_lineas()) as $subt) {
                        $albaran->neto += $subt['neto'];
                        $albaran->totaliva += $subt['iva'];
                        $albaran->totalirpf += $subt['irpf'];
                        $albaran->totalrecargo += $subt['recargo'];
                    }

                    $albaran->total = round($albaran->neto + $albaran->totaliva - $albaran->totalirpf + $albaran->totalrecargo, FS_NF0);

                    if (abs(floatval($_POST['atotal']) - $albaran->total) >= .02) {
                        $this->new_error_msg("El total difiere entre la vista y el controlador (" .
                            $_POST['atotal'] . " frente a " . $albaran->total . "). Debes informar del error.");
                        $albaran->delete();
                    } else if ($albaran->save()) {
                        /// Función de ejecución de tareas post guardado correcto del albaran
                        fs_documento_post_save($albaran);

                        $this->new_message("<a href='" . $albaran->url() . "'>" . ucfirst(FS_ALBARAN) . "</a> guardado correctamente.");
                        $this->new_change(ucfirst(FS_ALBARAN) . ' Proveedor ' . $albaran->codigo, $albaran->url(), TRUE);

                        if ($trazabilidad) {
                            header('Location: index.php?page=compras_trazabilidad&doc=albaran&id=' . $albaran->idalbaran);
                        } else if ($_POST['redir'] == 'TRUE') {
                            header('Location: ' . $albaran->url());
                        }
                    } else {
                        $this->new_error_msg("¡Imposible actualizar el <a href='" . $albaran->url() . "'>" . FS_ALBARAN . "</a>!");
                    }
                } else if ($albaran->delete()) {
                    $this->new_message(FS_ALBARAN . " eliminado correctamente.");
                } else {
                    $this->new_error_msg("¡Imposible eliminar el <a href='" . $albaran->url() . "'>" . FS_ALBARAN . "</a>!");
                }
            } else {
                $this->new_error_msg("¡Imposible guardar el " . FS_ALBARAN . "!");
            }
        }
    }

    private function nueva_factura_proveedor()
    {
        $continuar = TRUE;

        $proveedor = $this->proveedor->get($_POST['proveedor']);
        if (!$proveedor) {
            $this->new_error_msg('Proveedor no encontrado.');
            $continuar = FALSE;
        }

        $almacen = $this->almacen->get($_POST['almacen']);
        if ($almacen) {
            $this->save_codalmacen($_POST['almacen']);
        } else {
            $this->new_error_msg('Almacén no encontrado.');
            $continuar = FALSE;
        }

        $eje0 = new ejercicio();
        $ejercicio = $eje0->get_by_fecha($_POST['fecha']);
        if (!$ejercicio) {
            $this->new_error_msg('Ejercicio no encontrado o está cerrado.');
            $continuar = FALSE;
        }

        $serie = $this->serie->get($_POST['serie']);
        if (!$serie) {
            $this->new_error_msg('Serie no encontrada.');
            $continuar = FALSE;
        }

        $forma_pago = $this->forma_pago->get($_POST['forma_pago']);
        if ($forma_pago) {
            $this->save_codpago($_POST['forma_pago']);
        } else {
            $this->new_error_msg('Forma de pago no encontrada.');
            $continuar = FALSE;
        }

        $divisa = $this->divisa->get($_POST['divisa']);
        if (!$divisa) {
            $this->new_error_msg('Divisa no encontrada.');
            $continuar = FALSE;
        }

        $factura = new factura_proveedor();

        if ($this->duplicated_petition($_POST['petition_id'])) {
            $this->new_error_msg('Petición duplicada. Has hecho doble clic sobre el botón guardar
               y se han enviado dos peticiones. Mira en <a href="' . $factura->url() . '">Facturas</a>
               para ver si la factura se ha guardado correctamente.');
            $continuar = FALSE;
        }

        if ($continuar) {
            $this->nuevo_documento($factura, $proveedor, $almacen, $ejercicio, $serie, $forma_pago, $divisa);
            $factura->set_fecha_hora($_POST['fecha'], $_POST['hora']);

            if ($forma_pago->genrecibos == 'Pagados') {
                $factura->pagada = TRUE;
            }

            /// función auxiliar para implementar en los plugins que lo necesiten
            if (!fs_generar_numproveedor($factura)) {
                $factura->numproveedor = $_POST['numproveedor'];
            }

            $regularizacion = new regularizacion_iva();
            if ($regularizacion->get_fecha_inside($factura->fecha)) {
                $this->new_error_msg("El " . FS_IVA . " de ese periodo ya ha sido regularizado."
                    . " No se pueden añadir más facturas en esa fecha.");
            } else if ($factura->save()) {
                $trazabilidad = FALSE;

                $art0 = new articulo();
                $n = floatval($_POST['numlineas']);
                for ($i = 0; $i < $n; $i++) {
                    if (isset($_POST['referencia_' . $i])) {
                        $linea = new linea_factura_proveedor();
                        $linea->idfactura = $factura->idfactura;
                        $this->nueva_linea($linea, $i, $serie, $proveedor);

                        $articulo = $art0->get($_POST['referencia_' . $i]);
                        if ($articulo) {
                            $linea->referencia = $articulo->referencia;
                            if ($articulo->trazabilidad) {
                                $trazabilidad = TRUE;
                            }

                            if ($_POST['codcombinacion_' . $i]) {
                                $linea->codcombinacion = $_POST['codcombinacion_' . $i];
                            }
                        }

                        if ($linea->save()) {
                            if ($articulo) {
                                if (isset($_POST['stock'])) {
                                    $articulo->sum_stock($factura->codalmacen, $linea->cantidad, isset($_POST['costemedio']), $linea->codcombinacion);
                                } else if (isset($_POST['costemedio'])) {
                                    /// modificamos virtualmente el stock para que se recalcule el coste medio
                                    $articulo->stockfis += $linea->cantidad;
                                    $articulo->costemedio = $articulo->get_costemedio();
                                    $articulo->stockfis -= $linea->cantidad;
                                    $articulo->save();
                                }

                                if (isset($_POST['costemedio'])) {
                                    $this->actualizar_precio_proveedor($factura->codproveedor, $linea);
                                }
                            }

                            if ($linea->irpf > $factura->irpf) {
                                $factura->irpf = $linea->irpf;
                            }
                        } else {
                            $this->new_error_msg("¡Imposible guardar la linea con referencia: " . $linea->referencia);
                            $continuar = FALSE;
                        }
                    }
                }

                if ($continuar) {
                    /// obtenemos los subtotales por impuesto
                    foreach ($this->fbase_get_subtotales_documento($factura->get_lineas()) as $subt) {
                        $factura->neto += $subt['neto'];
                        $factura->totaliva += $subt['iva'];
                        $factura->totalirpf += $subt['irpf'];
                        $factura->totalrecargo += $subt['recargo'];
                    }

                    $factura->total = round($factura->neto + $factura->totaliva - $factura->totalirpf + $factura->totalrecargo, FS_NF0);

                    if (abs(floatval($_POST['atotal']) - $factura->total) >= .02) {
                        $this->new_error_msg("El total difiere entre el controlador y la vista (" .
                            $factura->total . " frente a " . $_POST['atotal'] . "). Debes informar del error.");
                        $factura->delete();
                    } else if ($factura->save()) {
                        $this->fbase_generar_asiento($factura, FALSE);

                        /// Función de ejecución de tareas post guardado correcto de la factura
                        fs_documento_post_save($factura);

                        $this->new_message("<a href='" . $factura->url() . "'>Factura</a> guardada correctamente.");
                        $this->new_change('Factura Proveedor ' . $factura->codigo, $factura->url(), TRUE);

                        if ($trazabilidad) {
                            header('Location: index.php?page=compras_trazabilidad&doc=factura&id=' . $factura->idfactura);
                        } else if ($_POST['redir'] == 'TRUE') {
                            header('Location: ' . $factura->url());
                        }
                    } else {
                        $this->new_error_msg("¡Imposible actualizar la <a href='" . $factura->url() . "'>factura</a>!");
                    }
                } else if ($factura->delete()) {
                    $this->new_message("Factura eliminada correctamente.");
                } else {
                    $this->new_error_msg("¡Imposible eliminar la <a href='" . $factura->url() . "'>factura</a>!");
                }
            } else {
                $this->new_error_msg("¡Imposible guardar la factura!");
            }
        }
    }

    /**
     * Actualiza los datos de artículos de proveedor en base a las líneas del documento de compra.
     * @param string $codproveedor
     * @param linea_albaran_proveedor $linea
     */
    private function actualizar_precio_proveedor($codproveedor, $linea)
    {
        if (!is_null($linea->referencia)) {
            $artp = $this->articulo_prov->get_by($linea->referencia, $codproveedor, $linea->referencia);
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

    private function search_from_proveedor()
    {
        $artilist = array();
        $query = $this->articulo_prov->no_html(mb_strtolower($this->query, 'UTF8'));
        $sql = "SELECT * FROM articulos WHERE bloqueado = false";
        $separador = ' AND';

        if ($_REQUEST['codfamilia'] != '') {
            $sql .= $separador . " codfamilia = " . $this->articulo_prov->var2str($_REQUEST['codfamilia']);
        }

        if ($_REQUEST['codfabricante'] != '') {
            $sql .= $separador . " codfabricante = " . $this->articulo_prov->var2str($_REQUEST['codfabricante']);
        }

        if (isset($_REQUEST['con_stock'])) {
            $sql .= $separador . " stockfis > 0";
        }

        if (isset($_REQUEST['solo_proveedor']) && isset($_REQUEST['codproveedor'])) {
            $sql .= $separador . " referencia IN (SELECT referencia FROM articulosprov WHERE codproveedor = "
                . $this->articulo_prov->var2str($_REQUEST['codproveedor']) . ")";
        }

        if (is_numeric($query)) {
            $sql .= $separador . " (referencia = " . $this->articulo_prov->var2str($query)
                . " OR referencia LIKE '%" . $query . "%'"
                . " OR partnumber LIKE '%" . $query . "%'"
                . " OR equivalencia LIKE '%" . $query . "%'"
                . " OR descripcion LIKE '%" . $query . "%'"
                . " OR codbarras = " . $this->articulo_prov->var2str($query) . ")";
        } else {
            /// ¿La búsqueda son varias palabras?
            $palabras = explode(' ', $query);
            if (count($palabras) > 1) {
                $sql .= $separador . " (lower(referencia) = " . $this->articulo_prov->var2str($query)
                    . " OR lower(referencia) LIKE '%" . $query . "%'"
                    . " OR lower(partnumber) LIKE '%" . $query . "%'"
                    . " OR lower(equivalencia) LIKE '%" . $query . "%'"
                    . " OR (";

                foreach ($palabras as $i => $pal) {
                    if ($i == 0) {
                        $sql .= "lower(descripcion) LIKE '%" . $pal . "%'";
                    } else {
                        $sql .= " AND lower(descripcion) LIKE '%" . $pal . "%'";
                    }
                }

                $sql .= "))";
            } else {
                $sql .= $separador . " (lower(referencia) = " . $this->articulo_prov->var2str($query)
                    . " OR lower(referencia) LIKE '%" . $query . "%'"
                    . " OR lower(partnumber) LIKE '%" . $query . "%'"
                    . " OR lower(equivalencia) LIKE '%" . $query . "%'"
                    . " OR lower(codbarras) = " . $this->articulo_prov->var2str($query)
                    . " OR lower(descripcion) LIKE '%" . $query . "%')";
            }
        }

        if (strtolower(FS_DB_TYPE) == 'mysql') {
            $sql .= " ORDER BY lower(referencia) ASC";
        } else {
            $sql .= " ORDER BY referencia ASC";
        }

        $data = $this->db->select_limit($sql, FS_ITEM_LIMIT, 0);
        if ($data) {
            foreach ($data as $a) {
                $artilist[] = new articulo($a);
            }
        }

        return $artilist;
    }

    private function nuevo_documento(&$documento, $proveedor, $almacen, $ejercicio, $serie, $forma_pago, $divisa)
    {
        $documento->fecha = $_POST['fecha'];
        $documento->hora = $_POST['hora'];
        $documento->codproveedor = $proveedor->codproveedor;
        $documento->nombre = $_POST['nombre'];
        $documento->cifnif = $_POST['cifnif'];
        $documento->codalmacen = $almacen->codalmacen;
        $documento->codejercicio = $ejercicio->codejercicio;
        $documento->codserie = $serie->codserie;
        $documento->codpago = $forma_pago->codpago;
        $documento->coddivisa = $divisa->coddivisa;
        $documento->tasaconv = $divisa->tasaconv_compra;

        if ($_POST['tasaconv'] != '') {
            $documento->tasaconv = floatval($_POST['tasaconv']);
        }

        $documento->codagente = $this->agente->codagente;
        $documento->observaciones = $_POST['observaciones'];
    }

    private function nueva_linea(&$linea, $i, $serie, $proveedor)
    {
        $linea->descripcion = $_POST['desc_' . $i];

        if (!$serie->siniva && $proveedor->regimeniva != 'Exento') {
            $imp0 = $this->impuesto->get_by_iva($_POST['iva_' . $i]);
            if ($imp0) {
                $linea->codimpuesto = $imp0->codimpuesto;
                $linea->iva = floatval($_POST['iva_' . $i]);
                $linea->recargo = floatval(fs_filter_input_post('recargo_' . $i, 0));
            } else {
                $linea->iva = floatval($_POST['iva_' . $i]);
                $linea->recargo = floatval(fs_filter_input_post('recargo_' . $i, 0));
            }
        }

        $linea->irpf = floatval(fs_filter_input_post('irpf_' . $i, 0));
        $linea->pvpunitario = floatval($_POST['pvp_' . $i]);
        $linea->cantidad = floatval($_POST['cantidad_' . $i]);
        $linea->dtopor = floatval(fs_filter_input_post('dto_' . $i, 0));
        $linea->pvpsindto = $linea->cantidad * $linea->pvpunitario;
        $linea->pvptotal = $linea->cantidad * $linea->pvpunitario * (100 - $linea->dtopor) / 100;
    }
}
