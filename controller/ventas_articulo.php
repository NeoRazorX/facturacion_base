<?php
/*
 * This file is part of facturacion_base
 * Copyright (C) 2013-2018  Carlos Garcia Gomez  neorazorx@gmail.com
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

class ventas_articulo extends fbase_controller
{

    public $agrupar_stock_fecha;
    public $almacen;
    public $articulo;
    public $equivalentes;
    public $fabricante;
    public $familia;
    public $hay_atributos;
    public $impuesto;
    public $mgrupo;
    public $mostrar_boton_publicar;
    public $mostrar_tab_atributos;
    public $mostrar_tab_precios;
    public $mostrar_tab_stock;
    public $nuevos_almacenes;
    public $stock;
    public $stocks;
    public $regularizaciones;

    public function __construct()
    {
        parent::__construct(__CLASS__, 'Articulo', 'ventas', FALSE, FALSE);
    }

    protected function private_core()
    {
        parent::private_core();

        $articulo = new articulo();
        $this->almacen = new almacen();
        $this->articulo = FALSE;
        $this->fabricante = new fabricante();
        $this->impuesto = new impuesto();
        $this->stock = new stock();

        $this->check_extensions();

        if (isset($_POST['referencia'])) {
            $this->articulo = $articulo->get($_POST['referencia']);
        } else if (isset($_GET['ref'])) {
            $this->articulo = $articulo->get($_GET['ref']);
        }

        if ($this->articulo) {
            $this->modificar(); /// todas las modificaciones van aquí
            $this->page->title = $this->articulo->referencia;

            if ($this->articulo->bloqueado) {
                $this->new_advice("Este artículo está bloqueado / obsoleto.");
            }

            /**
             * Si no es un artículo con atributos, ocultamos la pestaña
             */
            if ($this->articulo->tipo != 'atributos') {
                $this->mostrar_tab_atributos = FALSE;
            }

            /**
             * Si está desactivado el control de stok en el artículo, ocultamos la pestaña
             */
            if ($this->articulo->nostock) {
                $this->mostrar_tab_stock = FALSE;
            }

            $this->familia = $this->articulo->get_familia();
            if (!$this->familia) {
                $this->familia = new familia();
            }

            $this->fabricante = $this->articulo->get_fabricante();
            if (!$this->fabricante) {
                $this->fabricante = new fabricante();
            }

            $this->stocks = $this->articulo->get_stock();

            /// metemos en un array los almacenes que no tengan stock de este producto
            $this->nuevos_almacenes = [];
            foreach ($this->almacen->all() as $a) {
                $encontrado = FALSE;
                foreach ($this->stocks as $s) {
                    if ($a->codalmacen == $s->codalmacen) {
                        $encontrado = TRUE;
                        break;
                    }
                }

                if (!$encontrado) {
                    $this->nuevos_almacenes[] = $a;
                }
            }

            $reg = new regularizacion_stock();
            $this->regularizaciones = $reg->all_from_articulo($this->articulo->referencia);

            $this->equivalentes = $this->articulo->get_equivalentes();
            $this->agrupar_stock_fecha = isset($_GET['agrupar_stock_fecha']);
        } else {
            $this->new_error_msg("Artículo no encontrado.", 'error', FALSE, FALSE);
        }
    }

    public function url()
    {
        return $this->articulo ? $this->articulo->url() : $this->page->url();
    }

    private function check_extensions()
    {
        /**
         * Si hay alguna extensión de tipo config y texto no_button_publicar,
         * desactivamos el botón publicar.
         */
        $this->mostrar_boton_publicar = TRUE;
        foreach ($this->extensions as $ext) {
            if ($ext->type == 'config' && $ext->text == 'no_button_publicar') {
                $this->mostrar_boton_publicar = FALSE;
                break;
            }
        }

        /**
         * Si hay atributos, mostramos el tab atributos.
         */
        $this->hay_atributos = FALSE;
        $this->mostrar_tab_atributos = FALSE;
        $atri0 = new atributo();
        if (count($atri0->all()) > 0) {
            $this->mostrar_tab_atributos = TRUE;
            $this->hay_atributos = TRUE;
        }

        /**
         * Si hay alguna extensión de tipo config y texto no_tab_recios,
         * desactivamos la pestaña precios.
         */
        $this->mostrar_tab_precios = TRUE;
        foreach ($this->extensions as $ext) {
            if ($ext->type == 'config' && $ext->text == 'no_tab_precios') {
                $this->mostrar_tab_precios = FALSE;
                break;
            }
        }

        /**
         * Si hay alguna extensión de tipo config y texto no_tab_stock,
         * desactivamos la pestaña stock.
         */
        $this->mostrar_tab_stock = TRUE;
        foreach ($this->extensions as $ext) {
            if ($ext->type == 'config' && $ext->text == 'no_tab_stock') {
                $this->mostrar_tab_stock = FALSE;
                break;
            }
        }
    }

    /**
     * Decide qué modificación hacer en función de los parametros del formulario.
     */
    private function modificar()
    {
        if (isset($_POST['pvpiva'])) {
            $this->edit_precio();
        } else if (isset($_POST['almacen'])) {
            $this->edit_stock();
        } else if (isset($_GET['deletereg'])) {
            $this->eliminar_regulacion();
        } else if (isset($_POST['imagen'])) {
            $this->edit_imagen();
        } else if (isset($_GET['delete_img'])) {
            $this->eliminar_imagen();
        } else if (isset($_POST['referencia'])) {
            $this->modificar_articulo();
        } else if (isset($_GET['recalcular_stock'])) {
            $this->calcular_stock_real();
        } else if (isset($_POST['nueva_combi'])) {
            $this->nueva_combinacion();
        } else if (isset($_POST['editar_combi'])) {
            $this->edit_combinacion();
        } else if (isset($_GET['delete_combi'])) {
            $this->eliminar_combinacion();
        }
    }

    private function edit_precio()
    {
        $this->articulo->set_impuesto($_POST['codimpuesto']);
        $this->articulo->set_pvp_iva(floatval($_POST['pvpiva']));
        $this->articulo->preciocoste = isset($_POST['preciocoste']) ? floatval($_POST['preciocoste']) : $this->articulo->preciocoste;

        if ($this->articulo->save()) {
            $this->new_message("Precio modificado correctamente.");
        } else {
            $this->new_error_msg("Error al modificar el precio.");
        }
    }

    private function edit_stock()
    {
        if ($_POST['cantidadini'] == $_POST['cantidad']) {
            /// sin cambios de stock, pero aún así guardamos la ubicación
            $encontrado = FALSE;
            foreach ($this->articulo->get_stock() as $stock) {
                if ($stock->codalmacen == $_POST['almacen']) {
                    /// forzamos que se asigne el nombre del almacén
                    $stock->nombre();

                    $stock->ubicacion = $_POST['ubicacion'];
                    if ($stock->save()) {
                        $this->new_message('Cambios guardados correctamente.');
                    }

                    $encontrado = TRUE;
                    break;
                }
            }

            if (!$encontrado) {
                $nstock = new stock();
                $nstock->referencia = $this->articulo->referencia;
                $nstock->codalmacen = $_POST['almacen'];
                $nstock->ubicacion = $_POST['ubicacion'];
                $nstock->nombre();

                if ($nstock->save()) {
                    $this->new_message('Cambios guardados correctamente.');
                }
            }
        } else if ($this->articulo->set_stock($_POST['almacen'], $_POST['cantidad'])) {
            $this->new_message("Stock guardado correctamente.");

            /// añadimos la regularización
            foreach ($this->articulo->get_stock() as $stock) {
                if ($stock->codalmacen == $_POST['almacen']) {
                    /// forzamos que se asigne el nombre del almacén
                    $stock->nombre();

                    $stock->ubicacion = $_POST['ubicacion'];
                    $stock->save();

                    $regularizacion = new regularizacion_stock();
                    $regularizacion->idstock = $stock->idstock;
                    $regularizacion->cantidadini = floatval($_POST['cantidadini']);
                    $regularizacion->cantidadfin = floatval($_POST['cantidad']);
                    $regularizacion->codalmacendest = $_POST['almacen'];
                    $regularizacion->motivo = $_POST['motivo'];
                    $regularizacion->nick = $this->user->nick;
                    if ($regularizacion->save()) {
                        $this->new_message('Cambios guardados correctamente.');
                    }
                    break;
                }
            }
        } else {
            $this->new_error_msg("Error al guardar el stock.");
        }
    }

    private function eliminar_regulacion()
    {
        $reg = new regularizacion_stock();
        $regularizacion = $reg->get($_GET['deletereg']);
        if ($regularizacion) {
            if (!$this->allow_delete) {
                $this->new_error_msg('No tienes permiso para eliminar en esta página.');
            } else if ($regularizacion->delete()) {
                $this->new_message('Regularización eliminada correctamente.');
            } else {
                $this->new_error_msg('Error al eliminar la regularización.');
            }
        } else {
            $this->new_error_msg('Regularización no encontrada.');
        }
    }

    private function edit_imagen()
    {
        if (is_uploaded_file($_FILES['fimagen']['tmp_name'])) {
            $png = ( substr(strtolower($_FILES['fimagen']['name']), -3) == 'png' );
            $this->articulo->set_imagen(file_get_contents($_FILES['fimagen']['tmp_name']), $png);
            if ($this->articulo->save()) {
                $this->new_message("Imagen del articulo modificada correctamente");
            } else {
                $this->new_error_msg("¡Error al guardar la imagen del articulo!");
            }
        }
    }

    private function eliminar_imagen()
    {
        $this->articulo->set_imagen(NULL);
        if ($this->articulo->save()) {
            $this->new_message("Imagen del articulo eliminada correctamente");
        } else {
            $this->new_error_msg("¡Error al eliminar la imagen del articulo!");
        }
    }

    private function modificar_articulo()
    {
        $this->articulo->descripcion = $_POST['descripcion'];
        $this->articulo->tipo = empty($_POST['tipo']) ? NULL : $_POST['tipo'];
        $this->articulo->codfamilia = empty($_POST['codfamilia']) ? NULL : $_POST['codfamilia'];
        $this->articulo->codfabricante = empty($_POST['codfabricante']) ? NULL : $_POST['codfabricante'];
        $this->modificar_codbarras();
        $this->articulo->partnumber = $_POST['partnumber'];
        $this->articulo->equivalencia = $_POST['equivalencia'];
        $this->articulo->bloqueado = isset($_POST['bloqueado']);
        $this->articulo->controlstock = isset($_POST['controlstock']);
        $this->articulo->nostock = isset($_POST['nostock']);
        $this->articulo->secompra = isset($_POST['secompra']);
        $this->articulo->sevende = isset($_POST['sevende']);
        $this->articulo->publico = isset($_POST['publico']);
        $this->articulo->observaciones = $_POST['observaciones'];
        $this->articulo->stockmin = floatval($_POST['stockmin']);
        $this->articulo->stockmax = floatval($_POST['stockmax']);
        $this->articulo->trazabilidad = isset($_POST['trazabilidad']);
        if (!$this->articulo->save()) {
            $this->new_error_msg("¡Error al guardar el articulo!");
            return false;
        }

        /// save ok
        if ($_POST['nreferencia'] == $this->articulo->referencia) {
            $this->new_message("Datos del articulo modificados correctamente");
            return true;
        }

        /// cambio de referencia
        if ($this->articulo->set_referencia($_POST['nreferencia'])) {
            /**
             * Renombramos la referencia en el resto de tablas: lineasalbaranes, lineasfacturas...
             */
            $tables = ['lineasalbaranescli', 'lineasalbaranesprov', 'lineasfacturascli', 'lineasfacturasprov'];
            foreach ($tables as $table) {
                if ($this->db->table_exists($table)) {
                    $this->db->exec("UPDATE " . $table . " SET referencia = " . $this->empresa->var2str($_POST['nreferencia'])
                        . " WHERE referencia = " . $this->empresa->var2str($_POST['referencia']) . ";");
                }
            }

            $this->new_message("Datos del articulo modificados correctamente");
            return true;
        }

        return false;
    }

    private function modificar_codbarras()
    {
        /// sin cambios?
        if ($this->articulo->codbarras == $_POST['codbarras']) {
            return;
        }

        /// ¿Existe ya ese código de barras?
        if ($_POST['codbarras'] != '') {
            foreach ($this->articulo->search_by_codbar($_POST['codbarras']) as $art2) {
                if ($art2->referencia != $this->articulo->referencia) {
                    $this->new_advice('Ya hay un artículo con este mismo código de barras. '
                        . 'En concreto, el artículo <a href="' . $art2->url() . '">' . $art2->referencia . '</a>.');
                    return;
                }
            }
        }

        $this->articulo->codbarras = $_POST['codbarras'];
    }

    private function nueva_combinacion()
    {
        $comb1 = new articulo_combinacion();
        $comb1->referencia = $this->articulo->referencia;
        $comb1->impactoprecio = floatval($_POST['impactoprecio']);
        $comb1->refcombinacion = isset($_POST['refcombinacion']) ? $_POST['refcombinacion'] : NULL;
        $comb1->codbarras = isset($_POST['codbarras']) ? $_POST['codbarras'] : NULL;

        $error = TRUE;
        $valor0 = new atributo_valor();
        for ($i = 0; $i < 100; $i++) {
            if (!isset($_POST['idvalor_' . $i])) {
                break;
            }

            if ($_POST['idvalor_' . $i]) {
                $valor = $valor0->get($_POST['idvalor_' . $i]);
                if ($valor) {
                    $comb1->id = NULL;
                    $comb1->idvalor = $valor->id;
                    $comb1->nombreatributo = $valor->nombre();
                    $comb1->valor = $valor->valor;
                    $error = !$comb1->save();
                }
            }
        }

        if ($error) {
            $this->new_error_msg('Error al guardar la combinación.');
        } else {
            $this->new_message('Combinación guardada correctamente.');
        }
    }

    private function edit_combinacion()
    {
        $comb1 = new articulo_combinacion();
        foreach ($comb1->all_from_codigo($_POST['editar_combi']) as $com) {
            $com->refcombinacion = isset($_POST['refcombinacion']) ? $_POST['refcombinacion'] : NULL;
            $com->codbarras = isset($_POST['codbarras']) ? $_POST['codbarras'] : NULL;
            $com->impactoprecio = floatval($_POST['impactoprecio']);
            $com->stockfis = floatval($_POST['stockcombinacion']);
            $com->save();
        }

        /// recalculamos el stock, por si acaso
        $stock = 0;
        foreach ($this->combinaciones() as $combi) {
            $stock += $combi->stockfis;
        }

        if ($stock != $this->articulo->stockfis) {
            $this->articulo->set_stock($this->default_items->codalmacen(), $stock);
        }

        $this->new_message('Combinación modificada.');
    }

    private function eliminar_combinacion()
    {
        $comb1 = new articulo_combinacion();
        foreach ($comb1->all_from_codigo($_GET['delete_combi']) as $com) {
            $com->delete();
        }

        $this->new_message('Combinación eliminada.');
    }

    public function get_tarifas()
    {
        $tarlist = [];
        $tarifa = new tarifa();

        foreach ($tarifa->all() as $tar) {
            $articulo = $this->articulo->get($this->articulo->referencia);
            if ($articulo) {
                $articulo->dtopor = 0;
                $aux = array($articulo);
                $tar->set_precios($aux);
                $tarlist[] = $aux[0];
            }
        }

        return $tarlist;
    }

    public function get_articulo_proveedores()
    {
        $artprov = new articulo_proveedor();
        $alist = $artprov->all_from_ref($this->articulo->referencia);

        /// revismos el impuesto y la descripción
        foreach ($alist as $i => $value) {
            $guardar = FALSE;
            if (is_null($value->codimpuesto)) {
                $alist[$i]->codimpuesto = $this->articulo->codimpuesto;
                $guardar = TRUE;
            }

            if (is_null($value->descripcion)) {
                $alist[$i]->descripcion = $this->articulo->descripcion;
                $guardar = TRUE;
            }

            if ($guardar) {
                $alist[$i]->save();
            }
        }

        return $alist;
    }

    /**
     * Devuelve un array con los movimientos de stock del artículo.
     * @return array
     */
    public function get_movimientos($codalmacen)
    {
        $recstock = new recalcular_stock();
        $mlist = $recstock->get_movimientos($this->articulo->referencia, $codalmacen);

        if ($this->agrupar_stock_fecha) {
            foreach ($mlist as $item) {
                if (!isset($this->mgrupo[$item['fecha']])) {
                    $this->mgrupo[$item['fecha']]['ingreso'] = FALSE;
                    $this->mgrupo[$item['fecha']]['salida'] = FALSE;
                }

                if ($item['movimiento'] > 0) {
                    $this->mgrupo[$item['fecha']]['ingreso'] += $item['movimiento'];
                } else if ($item['movimiento'] < 0) {
                    $this->mgrupo[$item['fecha']]['salida'] += $item['movimiento'];
                }
            }
        }

        return $mlist;
    }

    /**
     * Calcula el stock real del artículo en función de los movimientos y regularizaciones
     */
    private function calcular_stock_real()
    {
        $almacenes = $this->almacen->all();
        foreach ($almacenes as $alm) {
            $total = 0;
            $movimientos = $this->get_movimientos($alm->codalmacen);
            foreach ($movimientos as $mov) {
                if ($mov['codalmacen'] == $alm->codalmacen) {
                    $total += $mov['movimiento'];
                }
            }

            if ($this->articulo->set_stock($alm->codalmacen, $total)) {
                $this->new_message('Recarculado el stock del almacén ' . $alm->codalmacen . '.');
            } else {
                $this->new_error_msg('Error al recarcular el stock del almacén ' . $alm->codalmacen . '.');
            }
        }

        $this->new_advice("Puedes recalcular el stock de todos los artículos desde"
            . " el menú <b>Informes &gt; Artículos &gt; Stock</b>");
    }

    public function combinaciones()
    {
        $lista = [];

        $comb1 = new articulo_combinacion();
        foreach ($comb1->all_from_ref($this->articulo->referencia) as $com) {
            if (isset($lista[$com->codigo])) {
                $lista[$com->codigo]->txt .= ', ' . $com->nombreatributo . ' - ' . $com->valor;
            } else {
                $com->txt = $com->nombreatributo . ' - ' . $com->valor;
                $lista[$com->codigo] = $com;
            }
        }

        return $lista;
    }

    public function atributos()
    {
        $atri0 = new atributo();
        return $atri0->all();
    }
}
