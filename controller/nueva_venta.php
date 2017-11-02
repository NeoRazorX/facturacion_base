<?php
/*
 * This file is part of facturacion_base
 * Copyright (C) 2014-2017  Carlos Garcia Gomez       neorazorx@gmail.com
 * Copyright (C) 2014-2015  Francesc Pineda Segarra   shawe.ewahs@gmail.com
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

class nueva_venta extends fbase_controller
{

    public $agencia;
    public $agente;
    public $almacen;
    public $articulo;
    public $cliente;
    public $cliente_s;
    public $direccion;
    public $divisa;
    public $fabricante;
    public $familia;
    public $forma_pago;
    public $grupo;
    public $impuesto;
    public $nuevocli_setup;
    public $pais;
    public $results;
    public $serie;
    public $tipo;

    public function __construct()
    {
        parent::__construct(__CLASS__, 'Nueva venta...', 'ventas', FALSE, FALSE, TRUE);
    }

    protected function private_core()
    {
        parent::private_core();

        $this->agencia = new agencia_transporte();
        $this->cliente = new cliente();
        $this->cliente_s = FALSE;
        $this->direccion = FALSE;
        $this->fabricante = new fabricante();
        $this->familia = new familia();
        $this->impuesto = new impuesto();
        $this->results = array();
        $this->grupo = new grupo_clientes();
        $this->pais = new pais();

        /// cargamos la configuración
        $fsvar = new fs_var();
        $this->nuevocli_setup = $fsvar->array_get(
            array(
            'nuevocli_cifnif_req' => 0,
            'nuevocli_direccion' => 0,
            'nuevocli_direccion_req' => 0,
            'nuevocli_codpostal' => 0,
            'nuevocli_codpostal_req' => 0,
            'nuevocli_pais' => 0,
            'nuevocli_pais_req' => 0,
            'nuevocli_provincia' => 0,
            'nuevocli_provincia_req' => 0,
            'nuevocli_ciudad' => 0,
            'nuevocli_ciudad_req' => 0,
            'nuevocli_telefono1' => 0,
            'nuevocli_telefono1_req' => 0,
            'nuevocli_telefono2' => 0,
            'nuevocli_telefono2_req' => 0,
            'nuevocli_email' => 0,
            'nuevocli_email_req' => 0,
            'nuevocli_codgrupo' => '',
            ), FALSE
        );

        if (isset($_REQUEST['tipo'])) {
            $this->tipo = $_REQUEST['tipo'];
        } else {
            foreach ($this->tipos_a_guardar() as $t) {
                $this->tipo = $t['tipo'];
                break;
            }
        }

        if (isset($_REQUEST['buscar_cliente'])) {
            $this->fbase_buscar_cliente($_REQUEST['buscar_cliente']);
        } else if (isset($_REQUEST['datoscliente'])) {
            $this->datos_cliente();
        } else if (isset($_REQUEST['new_articulo'])) {
            $this->new_articulo();
        } else if ($this->query != '') {
            $this->new_search();
        } else if (isset($_POST['referencia4precios'])) {
            $this->get_precios_articulo();
        } else if (isset($_POST['referencia4combi'])) {
            $this->get_combinaciones_articulo();
        } else if (isset($_POST['cliente'])) {
            $this->cliente_s = $this->cliente->get($_POST['cliente']);

            /**
             * Nuevo cliente
             */
            if (isset($_POST['nuevo_cliente']) && $_POST['nuevo_cliente'] != '') {
                $this->cliente_s = FALSE;
                if ($_POST['nuevo_cifnif'] != '') {
                    $this->cliente_s = $this->cliente->get_by_cifnif($_POST['nuevo_cifnif']);
                    if ($this->cliente_s) {
                        $this->new_advice('Ya existe un cliente con ese ' . FS_CIFNIF . '. Se ha seleccionado.');
                    }
                }

                if (!$this->cliente_s) {
                    $this->cliente_s = new cliente();
                    $this->cliente_s->codcliente = $this->cliente_s->get_new_codigo();
                    $this->cliente_s->nombre = $this->cliente_s->razonsocial = $_POST['nuevo_cliente'];
                    $this->cliente_s->tipoidfiscal = $_POST['nuevo_tipoidfiscal'];
                    $this->cliente_s->cifnif = $_POST['nuevo_cifnif'];
                    $this->cliente_s->personafisica = isset($_POST['personafisica']);

                    if (isset($_POST['nuevo_email'])) {
                        $this->cliente_s->email = $_POST['nuevo_email'];
                    }

                    if (isset($_POST['codgrupo']) && $_POST['codgrupo'] != '') {
                        $this->cliente_s->codgrupo = $_POST['codgrupo'];
                    }

                    if (isset($_POST['nuevo_telefono1'])) {
                        $this->cliente_s->telefono1 = $_POST['nuevo_telefono1'];
                    }

                    if (isset($_POST['nuevo_telefono2'])) {
                        $this->cliente_s->telefono2 = $_POST['nuevo_telefono2'];
                    }

                    if ($this->cliente_s->save()) {
                        if ($this->empresa->contintegrada) {
                            /// forzamos crear la subcuenta
                            $this->cliente_s->get_subcuenta($this->empresa->codejercicio);
                        }

                        $dircliente = new direccion_cliente();
                        $dircliente->codcliente = $this->cliente_s->codcliente;
                        $dircliente->codpais = $this->empresa->codpais;
                        $dircliente->provincia = $this->empresa->provincia;
                        $dircliente->ciudad = $this->empresa->ciudad;

                        if (isset($_POST['nuevo_pais'])) {
                            $dircliente->codpais = $_POST['nuevo_pais'];
                        }

                        if (isset($_POST['nuevo_provincia'])) {
                            $dircliente->provincia = $_POST['nuevo_provincia'];
                        }

                        if (isset($_POST['nuevo_ciudad'])) {
                            $dircliente->ciudad = $_POST['nuevo_ciudad'];
                        }

                        if (isset($_POST['nuevo_codpostal'])) {
                            $dircliente->codpostal = $_POST['nuevo_codpostal'];
                        }

                        if (isset($_POST['nuevo_direccion'])) {
                            $dircliente->direccion = $_POST['nuevo_direccion'];
                        }

                        if ($dircliente->save()) {
                            $this->new_message('Cliente agregado correctamente.');
                        }
                    } else {
                        $this->new_error_msg("¡Imposible guardar la dirección del cliente!");
                    }
                }
            }

            if ($this->cliente_s) {
                foreach ($this->cliente_s->get_direcciones() as $dir) {
                    if ($dir->domfacturacion) {
                        $this->direccion = $dir;
                        break;
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
                    $this->nueva_factura_cliente();
                } else if ($_POST['tipo'] == 'albaran') {
                    $this->nuevo_albaran_cliente();
                } else if ($_POST['tipo'] == 'pedido' && class_exists('pedido_cliente')) {
                    $this->nuevo_pedido_cliente();
                } else if ($_POST['tipo'] == 'presupuesto' && class_exists('presupuesto_cliente')) {
                    $this->nuevo_presupuesto_cliente();
                }

                /// si el cliente no tiene cifnif nos guardamos el que indique
                if ($this->cliente_s->cifnif == '') {
                    $this->cliente_s->cifnif = $_POST['cifnif'];
                    $this->cliente_s->save();
                }

                /// ¿Guardamos la dirección como nueva?
                if ($_POST['coddir'] == 'nueva') {
                    $this->direccion = new direccion_cliente();
                    $this->direccion->codcliente = $this->cliente_s->codcliente;
                    $this->direccion->codpais = $_POST['codpais'];
                    $this->direccion->provincia = $_POST['provincia'];
                    $this->direccion->ciudad = $_POST['ciudad'];
                    $this->direccion->codpostal = $_POST['codpostal'];
                    $this->direccion->direccion = $_POST['direccion'];
                    $this->direccion->apartado = $_POST['apartado'];
                    $this->direccion->save();
                } else if ($_POST['envio_coddir'] == 'nueva') {
                    $this->direccion = new direccion_cliente();
                    $this->direccion->codcliente = $this->cliente_s->codcliente;
                    $this->direccion->codpais = $_POST['envio_codpais'];
                    $this->direccion->provincia = $_POST['envio_provincia'];
                    $this->direccion->ciudad = $_POST['envio_ciudad'];
                    $this->direccion->codpostal = $_POST['envio_codpostal'];
                    $this->direccion->direccion = $_POST['envio_direccion'];
                    $this->direccion->apartado = $_POST['envio_apartado'];
                    $this->direccion->domfacturacion = FALSE;
                    $this->direccion->domenvio = TRUE;
                    $this->direccion->save();
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

        if ($this->user->have_access_to('ventas_presupuesto') && class_exists('presupuesto_cliente')) {
            $tipos[] = array('tipo' => 'presupuesto', 'nombre' => ucfirst(FS_PRESUPUESTO) . ' para cliente');
        }

        if ($this->user->have_access_to('ventas_pedido') && class_exists('pedido_cliente')) {
            $tipos[] = array('tipo' => 'pedido', 'nombre' => ucfirst(FS_PEDIDO) . ' de cliente');
        }

        if ($this->user->have_access_to('ventas_albaran')) {
            $tipos[] = array('tipo' => 'albaran', 'nombre' => ucfirst(FS_ALBARAN) . ' de cliente');
        }

        if ($this->user->have_access_to('ventas_factura')) {
            $tipos[] = array('tipo' => 'factura', 'nombre' => 'Factura de cliente');
        }

        return $tipos;
    }

    public function url()
    {
        return 'index.php?page=' . __CLASS__ . '&tipo=' . $this->tipo;
    }

    private function datos_cliente()
    {
        /// desactivamos la plantilla HTML
        $this->template = FALSE;

        header('Content-Type: application/json');
        echo json_encode($this->cliente->get($_REQUEST['datoscliente']));
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

            $art0->secompra = isset($_POST['secompra']);
            $art0->sevende = isset($_POST['sevende']);
            $art0->nostock = isset($_POST['nostock']);
            $art0->publico = isset($_POST['publico']);

            if ($_REQUEST['codfamilia'] != '') {
                $art0->codfamilia = $_REQUEST['codfamilia'];
            }

            if ($_REQUEST['codfabricante'] != '') {
                $art0->codfabricante = $_REQUEST['codfabricante'];
            }

            if ($art0->save()) {
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

        $articulo = new articulo();
        $codfamilia = '';
        if (isset($_REQUEST['codfamilia'])) {
            $codfamilia = $_REQUEST['codfamilia'];
        }
        $codfabricante = '';
        if (isset($_REQUEST['codfabricante'])) {
            $codfabricante = $_REQUEST['codfabricante'];
        }
        $con_stock = isset($_REQUEST['con_stock']);
        $this->results = $articulo->search($this->query, 0, $codfamilia, $con_stock, $codfabricante);

        /// añadimos la busqueda, el descuento, la cantidad, etc...
        $stock = new stock();
        foreach ($this->results as $i => $value) {
            $this->results[$i]->query = $this->query;
            $this->results[$i]->dtopor = 0;
            $this->results[$i]->cantidad = 1;
            $this->results[$i]->coddivisa = $this->empresa->coddivisa;

            /// añadimos el stock del almacén y el general
            $this->results[$i]->stockalm = $this->results[$i]->stockfis;
            if ($this->multi_almacen && isset($_REQUEST['codalmacen'])) {
                $this->results[$i]->stockalm = $stock->total_from_articulo($this->results[$i]->referencia, $_REQUEST['codalmacen']);
            }
        }

        /// ejecutamos las funciones de las extensiones
        foreach ($this->extensions as $ext) {
            if ($ext->type == 'function' && $ext->params == 'new_search') {
                $name = $ext->text;
                $name($this->db, $this->results);
            }
        }

        /// buscamos el grupo de clientes y la tarifa
        if (isset($_REQUEST['codcliente'])) {
            $cliente = $this->cliente->get($_REQUEST['codcliente']);
            $tarifa0 = new tarifa();

            if ($cliente && $cliente->codtarifa) {
                $tarifa = $tarifa0->get($cliente->codtarifa);
                if ($tarifa) {
                    $tarifa->set_precios($this->results);
                }
            } else if ($cliente && $cliente->codgrupo) {
                $grupo0 = new grupo_clientes();

                $grupo = $grupo0->get($cliente->codgrupo);
                if ($grupo) {
                    $tarifa = $tarifa0->get($grupo->codtarifa);
                    if ($tarifa) {
                        $tarifa->set_precios($this->results);
                    }
                }
            }
        }

        /// convertimos la divisa
        if (isset($_REQUEST['coddivisa']) && $_REQUEST['coddivisa'] != $this->empresa->coddivisa) {
            foreach ($this->results as $i => $value) {
                $this->results[$i]->coddivisa = $_REQUEST['coddivisa'];
                $this->results[$i]->pvp = $this->divisa_convert($value->pvp, $this->empresa->coddivisa, $_REQUEST['coddivisa']);
            }
        }

        header('Content-Type: application/json');
        echo json_encode($this->results);
    }

    private function get_precios_articulo()
    {
        /// cambiamos la plantilla HTML
        $this->template = 'ajax/nueva_venta_precios';

        $articulo = new articulo();
        $this->articulo = $articulo->get($_POST['referencia4precios']);
    }

    private function get_combinaciones_articulo()
    {
        /// cambiamos la plantilla HTML
        $this->template = 'ajax/nueva_venta_combinaciones';

        $impuestos = $this->impuesto->all();

        $this->results = array();
        $comb1 = new articulo_combinacion();
        foreach ($comb1->all_from_ref($_POST['referencia4combi']) as $com) {
            if (isset($this->results[$com->codigo])) {
                $this->results[$com->codigo]['desc'] .= ', ' . $com->nombreatributo . ' - ' . $com->valor;
                $this->results[$com->codigo]['txt'] .= ', ' . $com->nombreatributo . ' - ' . $com->valor;
            } else {
                $iva = 0;
                foreach ($impuestos as $imp) {
                    if ($imp->codimpuesto == $_POST['codimpuesto']) {
                        $iva = $imp->iva;
                        break;
                    }
                }

                $this->results[$com->codigo] = array(
                    'ref' => $_POST['referencia4combi'],
                    'desc' => base64_decode($_POST['desc']) . "\n" . $com->nombreatributo . ' - ' . $com->valor,
                    'pvp' => floatval($_POST['pvp']) + $com->impactoprecio,
                    'dto' => floatval($_POST['dto']),
                    'codimpuesto' => $_POST['codimpuesto'],
                    'iva' => $iva,
                    'cantidad' => floatval($_POST['cantidad']),
                    'txt' => $com->nombreatributo . ' - ' . $com->valor,
                    'codigo' => $com->codigo,
                    'stockfis' => $com->stockfis,
                );
            }
        }
    }

    public function get_tarifas_articulo($ref)
    {
        $tarlist = array();
        $articulo = new articulo();
        $tarifa = new tarifa();

        foreach ($tarifa->all() as $tar) {
            $art = $articulo->get($ref);
            if ($art) {
                $art->dtopor = 0;
                $aux = array($art);
                $tar->set_precios($aux);
                $tarlist[] = $aux[0];
            }
        }

        return $tarlist;
    }

    private function nuevo_presupuesto_cliente()
    {
        $continuar = TRUE;

        $cliente = $this->cliente->get($_POST['cliente']);
        if (!$cliente) {
            $this->new_error_msg('Cliente no encontrado.');
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

        $presupuesto = new presupuesto_cliente();

        if ($this->duplicated_petition($_POST['petition_id'])) {
            $this->new_error_msg('Petición duplicada. Has hecho doble clic sobre el botón guardar
               y se han enviado dos peticiones. Mira en <a href="' . $presupuesto->url() . '">Presupuestos</a>
               para ver si el presupuesto se ha guardado correctamente.');
            $continuar = FALSE;
        }

        if ($continuar) {
            $this->nuevo_documento($presupuesto, $ejercicio, $serie, $almacen, $forma_pago, $divisa, $cliente);

            /// establecemos la fecha de finoferta
            $presupuesto->finoferta = date("Y-m-d", strtotime($_POST['fecha'] . " +1 month"));
            $fsvar = new fs_var();
            $dias = $fsvar->simple_get('presu_validez');
            if ($dias) {
                $presupuesto->finoferta = date("Y-m-d", strtotime($_POST['fecha'] . " +" . intval($dias) . " days"));
            }

            /// función auxiliar para implementar en los plugins que lo necesiten
            if (!fs_generar_numero2($presupuesto)) {
                $presupuesto->numero2 = $_POST['numero2'];
            }

            if ($presupuesto->save()) {
                $art0 = new articulo();
                $n = floatval($_POST['numlineas']);
                for ($i = 0; $i <= $n; $i++) {
                    if (isset($_POST['referencia_' . $i])) {
                        $linea = new linea_presupuesto_cliente();
                        $linea->idpresupuesto = $presupuesto->idpresupuesto;
                        $this->nueva_linea($linea, $i, $serie, $cliente);

                        $articulo = $art0->get($_POST['referencia_' . $i]);
                        if ($articulo) {
                            $linea->referencia = $articulo->referencia;
                            if ($_POST['codcombinacion_' . $i]) {
                                $linea->codcombinacion = $_POST['codcombinacion_' . $i];
                            }
                        }

                        if ($linea->save()) {
                            if ($linea->irpf > $presupuesto->irpf) {
                                $presupuesto->irpf = $linea->irpf;
                            }
                        } else {
                            $this->new_error_msg("¡Imposible guardar la linea con referencia: " . $linea->referencia);
                            $continuar = FALSE;
                        }
                    }
                }

                if ($continuar) {
                    /// obtenemos los subtotales por impuesto
                    $due_totales = $this->fbase_calc_due([$presupuesto->dtopor1, $presupuesto->dtopor2, $presupuesto->dtopor3, $presupuesto->dtopor4, $presupuesto->dtopor5]);
                    foreach ($this->fbase_get_subtotales_documento($presupuesto->get_lineas(), $due_totales) as $subt) {
                        $presupuesto->netosindto += $subt['netosindto'];
                        $presupuesto->neto += $subt['neto'];
                        $presupuesto->totaliva += $subt['iva'];
                        $presupuesto->totalirpf += $subt['irpf'];
                        $presupuesto->totalrecargo += $subt['recargo'];
                    }

                    $presupuesto->total = round($presupuesto->neto + $presupuesto->totaliva - $presupuesto->totalirpf + $presupuesto->totalrecargo, FS_NF0);

                    if (abs(floatval($_POST['atotal']) - $presupuesto->total) >= .02) {
                        $this->new_error_msg("El total difiere entre el controlador y la vista (" .
                            $presupuesto->total . " frente a " . $_POST['atotal'] . "). Debes informar del error.");
                        $presupuesto->delete();
                    } else if ($presupuesto->save()) {
                        /// Función de ejecución de tareas post guardado correcto del presupuesto
                        fs_documento_post_save($presupuesto);

                        $this->new_message("<a href='" . $presupuesto->url() . "'>" . ucfirst(FS_PRESUPUESTO) . "</a> guardado correctamente.");
                        $this->new_change(ucfirst(FS_PRESUPUESTO) . ' a Cliente ' . $presupuesto->codigo, $presupuesto->url(), TRUE);

                        if ($_POST['redir'] == 'TRUE') {
                            header('Location: ' . $presupuesto->url());
                        }
                    } else {
                        $this->new_error_msg("¡Imposible actualizar el <a href='" . $presupuesto->url() . "'>" . FS_PRESUPUESTO . "</a>!");
                    }
                } else if ($presupuesto->delete()) {
                    $this->new_message(ucfirst(FS_PRESUPUESTO) . " eliminado correctamente.");
                } else {
                    $this->new_error_msg("¡Imposible eliminar el <a href='" . $presupuesto->url() . "'>" . FS_PRESUPUESTO . "</a>!");
                }
            } else {
                $this->new_error_msg("¡Imposible guardar el " . FS_PRESUPUESTO . "!");
            }
        }
    }

    private function nuevo_pedido_cliente()
    {
        $continuar = TRUE;

        $cliente = $this->cliente->get($_POST['cliente']);
        if (!$cliente) {
            $this->new_error_msg('Cliente no encontrado.');
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

        $pedido = new pedido_cliente();

        if ($this->duplicated_petition($_POST['petition_id'])) {
            $this->new_error_msg('Petición duplicada. Has hecho doble clic sobre el botón guardar
               y se han enviado dos peticiones. Mira en <a href="' . $pedido->url() . '">Pedidos</a>
               para ver si el pedido se ha guardado correctamente.');
            $continuar = FALSE;
        }

        if ($continuar) {
            $this->nuevo_documento($pedido, $ejercicio, $serie, $almacen, $forma_pago, $divisa, $cliente);

            /// función auxiliar para implementar en los plugins que lo necesiten
            if (!fs_generar_numero2($pedido)) {
                $pedido->numero2 = $_POST['numero2'];
            }

            if ($pedido->save()) {
                $art0 = new articulo();
                $n = floatval($_POST['numlineas']);
                for ($i = 0; $i <= $n; $i++) {
                    if (isset($_POST['referencia_' . $i])) {
                        $linea = new linea_pedido_cliente();
                        $linea->idpedido = $pedido->idpedido;
                        $this->nueva_linea($linea, $i, $serie, $cliente);

                        $articulo = $art0->get($_POST['referencia_' . $i]);
                        if ($articulo) {
                            $linea->referencia = $articulo->referencia;
                            if ($_POST['codcombinacion_' . $i]) {
                                $linea->codcombinacion = $_POST['codcombinacion_' . $i];
                            }
                        }

                        if ($linea->save()) {
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
                    $due_totales = $this->fbase_calc_due([$pedido->dtopor1, $pedido->dtopor2, $pedido->dtopor3, $pedido->dtopor4, $pedido->dtopor5]);
                    foreach ($this->fbase_get_subtotales_documento($pedido->get_lineas(), $due_totales) as $subt) {
                        $pedido->netosindto += $subt['netosindto'];
                        $pedido->neto += $subt['neto'];
                        $pedido->totaliva += $subt['iva'];
                        $pedido->totalirpf += $subt['irpf'];
                        $pedido->totalrecargo += $subt['recargo'];
                    }

                    $pedido->total = round($pedido->neto + $pedido->totaliva - $pedido->totalirpf + $pedido->totalrecargo, FS_NF0);

                    if (abs(floatval($_POST['atotal']) - $pedido->total) >= .02) {
                        $this->new_error_msg("El total difiere entre el controlador y la vista ("
                            . $pedido->total . " frente a " . $_POST['atotal'] . "). Debes informar del error.");
                        $pedido->delete();
                    } else if ($pedido->save()) {
                        /// Función de ejecución de tareas post guardado correcto del pedido
                        fs_documento_post_save($pedido);

                        $this->new_message("<a href='" . $pedido->url() . "'>" . ucfirst(FS_PEDIDO) . "</a> guardado correctamente.");
                        $this->new_change(ucfirst(FS_PEDIDO) . " a Cliente " . $pedido->codigo, $pedido->url(), TRUE);

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

    private function nuevo_albaran_cliente()
    {
        $continuar = TRUE;

        $cliente = $this->cliente->get($_POST['cliente']);
        if (!$cliente) {
            $this->new_error_msg('Cliente no encontrado.');
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

        $art0 = new articulo();
        $albaran = new albaran_cliente();
        $stock0 = new stock();

        if ($this->duplicated_petition($_POST['petition_id'])) {
            $this->new_error_msg('Petición duplicada. Has hecho doble clic sobre el botón guardar
               y se han enviado dos peticiones. Mira en <a href="' . $albaran->url() . '">' . FS_ALBARANES . '</a>
               para ver si el ' . FS_ALBARAN . ' se ha guardado correctamente.');
            $continuar = FALSE;
        }

        if ($continuar) {
            $this->nuevo_documento($albaran, $ejercicio, $serie, $almacen, $forma_pago, $divisa, $cliente);

            /// función auxiliar para implementar en los plugins que lo necesiten
            if (!fs_generar_numero2($albaran)) {
                $albaran->numero2 = $_POST['numero2'];
            }

            if ($albaran->save()) {
                $trazabilidad = FALSE;

                $n = floatval($_POST['numlineas']);
                for ($i = 0; $i <= $n; $i++) {
                    if (isset($_POST['referencia_' . $i])) {
                        $linea = new linea_albaran_cliente();
                        $linea->idalbaran = $albaran->idalbaran;
                        $this->nueva_linea($linea, $i, $serie, $cliente);

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
                            if ($articulo && isset($_POST['stock'])) {
                                $stockfis = $articulo->stockfis;
                                if ($this->multi_almacen) {
                                    $stockfis = $stock0->total_from_articulo($articulo->referencia, $albaran->codalmacen);
                                }

                                if (!$articulo->controlstock && $linea->cantidad > $stockfis) {
                                    $this->new_error_msg("No hay suficiente stock del artículo <b>" . $linea->referencia . '</b>.');
                                    $linea->delete();
                                    $continuar = FALSE;
                                } else {
                                    /// descontamos del stock
                                    $articulo->sum_stock($albaran->codalmacen, 0 - $linea->cantidad, FALSE, $linea->codcombinacion);
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
                    $due_totales = $this->fbase_calc_due([$albaran->dtopor1, $albaran->dtopor2, $albaran->dtopor3, $albaran->dtopor4, $albaran->dtopor5]);
                    foreach ($this->fbase_get_subtotales_documento($albaran->get_lineas(), $due_totales) as $subt) {
                        $albaran->netosindto += $subt['netosindto'];
                        $albaran->neto += $subt['neto'];
                        $albaran->totaliva += $subt['iva'];
                        $albaran->totalirpf += $subt['irpf'];
                        $albaran->totalrecargo += $subt['recargo'];
                    }

                    $albaran->total = round($albaran->neto + $albaran->totaliva - $albaran->totalirpf + $albaran->totalrecargo, FS_NF0);

                    if (abs(floatval($_POST['atotal']) - $albaran->total) >= .02) {
                        $this->new_error_msg("El total difiere entre la vista y el controlador (" . $_POST['atotal'] .
                            " frente a " . $albaran->total . "). Debes informar del error.");
                        $albaran->delete();
                    } else if ($albaran->save()) {
                        /// Función de ejecución de tareas post guardado correcto del albaran
                        fs_documento_post_save($albaran);

                        $this->new_message("<a href='" . $albaran->url() . "'>" . ucfirst(FS_ALBARAN) . "</a> guardado correctamente.");
                        $this->new_change(ucfirst(FS_ALBARAN) . ' Cliente ' . $albaran->codigo, $albaran->url(), TRUE);

                        if ($trazabilidad) {
                            header('Location: index.php?page=ventas_trazabilidad&doc=albaran&id=' . $albaran->idalbaran);
                        } else if ($_POST['redir'] == 'TRUE') {
                            header('Location: ' . $albaran->url());
                        }
                    } else {
                        $this->new_error_msg("¡Imposible actualizar el <a href='" . $albaran->url() . "'>" . FS_ALBARAN . "</a>!");
                    }
                } else {
                    /// actualizamos el stock
                    foreach ($albaran->get_lineas() as $linea) {
                        if ($linea->referencia) {
                            $articulo = $art0->get($linea->referencia);
                            if ($articulo) {
                                $articulo->sum_stock($albaran->codalmacen, $linea->cantidad, FALSE, $linea->codcombinacion);
                            }
                        }
                    }

                    if (!$albaran->delete()) {
                        $this->new_error_msg("¡Imposible eliminar el <a href='" . $albaran->url() . "'>" . FS_ALBARAN . "</a>!");
                    }
                }
            } else {
                $this->new_error_msg("¡Imposible guardar el " . FS_ALBARAN . "!");
            }
        }
    }

    private function nueva_factura_cliente()
    {
        $continuar = TRUE;

        $cliente = $this->cliente->get($_POST['cliente']);
        if (!$cliente) {
            $this->new_error_msg('Cliente no encontrado.');
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

        $art0 = new articulo();
        $factura = new factura_cliente();
        $stock0 = new stock();

        if ($this->duplicated_petition($_POST['petition_id'])) {
            $this->new_error_msg('Petición duplicada. Has hecho doble clic sobre el botón guardar
               y se han enviado dos peticiones. Mira en <a href="' . $factura->url() . '">Facturas</a>
               para ver si la factura se ha guardado correctamente.');
            $continuar = FALSE;
        }

        if ($continuar) {
            $this->nuevo_documento($factura, $ejercicio, $serie, $almacen, $forma_pago, $divisa, $cliente);
            $factura->set_fecha_hora($_POST['fecha'], $_POST['hora']);

            if ($forma_pago->genrecibos == 'Pagados') {
                $factura->pagada = TRUE;
            }

            $factura->vencimiento = $forma_pago->calcular_vencimiento($factura->fecha, $cliente->diaspago);

            /// función auxiliar para implementar en los plugins que lo necesiten
            if (!fs_generar_numero2($factura)) {
                $factura->numero2 = $_POST['numero2'];
            }

            $regularizacion = new regularizacion_iva();
            if ($regularizacion->get_fecha_inside($factura->fecha)) {
                $this->new_error_msg("El " . FS_IVA . " de ese periodo ya ha sido regularizado."
                    . " No se pueden añadir más facturas en esa fecha.");
            } else if ($factura->save()) {
                $trazabilidad = FALSE;

                $n = floatval($_POST['numlineas']);
                for ($i = 0; $i <= $n; $i++) {
                    if (isset($_POST['referencia_' . $i])) {
                        $linea = new linea_factura_cliente();
                        $linea->idfactura = $factura->idfactura;
                        $this->nueva_linea($linea, $i, $serie, $cliente);

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
                            if ($articulo && isset($_POST['stock'])) {
                                $stockfis = $articulo->stockfis;
                                if ($this->multi_almacen) {
                                    $stockfis = $stock0->total_from_articulo($articulo->referencia, $factura->codalmacen);
                                }

                                if (!$articulo->controlstock && $linea->cantidad > $stockfis) {
                                    $this->new_error_msg("No hay suficiente stock del artículo <b>" . $linea->referencia . '</b>.');
                                    $linea->delete();
                                    $continuar = FALSE;
                                } else {
                                    /// descontamos del stock
                                    $articulo->sum_stock($factura->codalmacen, 0 - $linea->cantidad, FALSE, $linea->codcombinacion);
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
                    $due_totales = $this->fbase_calc_due([$factura->dtopor1, $factura->dtopor2, $factura->dtopor3, $factura->dtopor4, $factura->dtopor5]);
                    foreach ($this->fbase_get_subtotales_documento($factura->get_lineas(), $due_totales) as $subt) {
                        $factura->netosindto += $subt['netosindto'];
                        $factura->neto += $subt['neto'];
                        $factura->totaliva += $subt['iva'];
                        $factura->totalirpf += $subt['irpf'];
                        $factura->totalrecargo += $subt['recargo'];
                    }

                    $factura->total = round($factura->neto + $factura->totaliva - $factura->totalirpf + $factura->totalrecargo, FS_NF0);

                    if (abs(floatval($_POST['atotal']) - $factura->total) >= .02) {
                        $this->new_error_msg("El total difiere entre la vista y el controlador (" . $_POST['atotal'] .
                            " frente a " . $factura->total . "). Debes informar del error.");
                        $factura->delete();
                    } else if ($factura->save()) {
                        $this->fbase_generar_asiento($factura, FALSE);

                        /// Función de ejecución de tareas post guardado correcto de la factura
                        fs_documento_post_save($factura);

                        $this->new_message("<a href='" . $factura->url() . "'>Factura</a> guardada correctamente.");
                        $this->new_change('Factura Cliente ' . $factura->codigo, $factura->url(), TRUE);

                        if ($trazabilidad) {
                            header('Location: index.php?page=ventas_trazabilidad&doc=factura&id=' . $factura->idfactura);
                        } else if ($_POST['redir'] == 'TRUE') {
                            header('Location: ' . $factura->url());
                        }
                    } else {
                        $this->new_error_msg("¡Imposible actualizar la <a href='" . $factura->url() . "'>Factura</a>!");
                    }
                } else {
                    /// actualizamos el stock
                    foreach ($factura->get_lineas() as $linea) {
                        if ($linea->referencia) {
                            $articulo = $art0->get($linea->referencia);
                            if ($articulo) {
                                $articulo->sum_stock($factura->codalmacen, $linea->cantidad, FALSE, $linea->codcombinacion);
                            }
                        }
                    }

                    if (!$factura->delete()) {
                        $this->new_error_msg("¡Imposible eliminar la <a href='" . $factura->url() . "'>Factura</a>!");
                    }
                }
            } else {
                $this->new_error_msg("¡Imposible guardar la Factura!");
            }
        }
    }

    private function nuevo_documento(&$documento, $ejercicio, $serie, $almacen, $forma_pago, $divisa, $cliente)
    {
        $documento->fecha = $_POST['fecha'];
        $documento->hora = $_POST['hora'];
        $documento->codejercicio = $ejercicio->codejercicio;
        $documento->codserie = $serie->codserie;
        $documento->codalmacen = $almacen->codalmacen;
        $documento->codpago = $forma_pago->codpago;
        $documento->coddivisa = $divisa->coddivisa;
        $documento->tasaconv = $divisa->tasaconv;

        if ($_POST['tasaconv'] != '') {
            $documento->tasaconv = floatval($_POST['tasaconv']);
        }

        $documento->codagente = $this->agente->codagente;
        $documento->observaciones = $_POST['observaciones'];
        $documento->porcomision = $this->agente->porcomision;

        $documento->codcliente = $cliente->codcliente;
        $documento->cifnif = $_POST['cifnif'];
        $documento->nombrecliente = $_POST['nombrecliente'];
        $documento->codpais = $_POST['codpais'];
        $documento->provincia = $_POST['provincia'];
        $documento->ciudad = $_POST['ciudad'];
        $documento->codpostal = $_POST['codpostal'];
        $documento->direccion = $_POST['direccion'];
        $documento->apartado = $_POST['apartado'];

        if (is_numeric($_POST['coddir'])) {
            $documento->coddir = $_POST['coddir'];
        }

        /// envío
        $documento->envio_nombre = $_POST['envio_nombre'];
        $documento->envio_apellidos = $_POST['envio_apellidos'];
        if ($_POST['envio_codtrans'] != '') {
            $documento->envio_codtrans = $_POST['envio_codtrans'];
        }
        $documento->envio_codigo = $_POST['envio_codigo'];
        $documento->envio_codpais = $_POST['envio_codpais'];
        $documento->envio_provincia = $_POST['envio_provincia'];
        $documento->envio_ciudad = $_POST['envio_ciudad'];
        $documento->envio_codpostal = $_POST['envio_codpostal'];
        $documento->envio_direccion = $_POST['envio_direccion'];
        $documento->envio_apartado = $_POST['envio_apartado'];

        $documento->dtopor1 = floatval($_POST['adtopor1']);
        $documento->dtopor2 = floatval($_POST['adtopor2']);
        $documento->dtopor3 = floatval($_POST['adtopor3']);
        $documento->dtopor4 = floatval($_POST['adtopor4']);
        $documento->dtopor5 = floatval($_POST['adtopor5']);
    }

    private function nueva_linea(&$linea, $i, $serie, $cliente)
    {
        $linea->descripcion = $_POST['desc_' . $i];

        if (!$serie->siniva && $cliente->regimeniva != 'Exento') {
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
        $linea->dtopor2 = floatval(fs_filter_input_post('dto2_' . $i, 0));
        $linea->dtopor3 = floatval(fs_filter_input_post('dto3_' . $i, 0));
        $linea->dtopor4 = floatval(fs_filter_input_post('dto4_' . $i, 0));
        $linea->pvpsindto = $linea->pvpunitario * $linea->cantidad;

        // Descuento Unificado Equivalente
        $due_linea = $this->fbase_calc_due(array($linea->dtopor, $linea->dtopor2, $linea->dtopor3, $linea->dtopor4));
        $linea->pvptotal = $linea->cantidad * $linea->pvpunitario * $due_linea;
    }
}
