<?php
/**
 * This file is part of facturacion_base
 * Copyright (C) 2013-2019 Carlos Garcia Gomez <neorazorx@gmail.com>
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
 * 
 */
class compras_proveedores extends fs_list_controller
{

    /**
     *
     * @var \pais
     */
    public $pais;

    public function __construct()
    {
        parent::__construct(__CLASS__, 'Proveedores y acreedores', 'compras');
    }

    protected function create_tab_articulosprov($name = 'articulosprov')
    {
        $this->add_tab($name, 'artículos', 'articulosprov', 'fa-cubes');
        $this->add_search_columns($name, ['refproveedor', 'referencia', 'descripcion', 'codbarras', 'partnumber']);
        $this->add_sort_option($name, ['refproveedor'], 1);
        $this->add_sort_option($name, ['referencia']);
        $this->add_sort_option($name, ['descripcion']);
        $this->add_sort_option($name, ['precio']);
        $this->add_sort_option($name, ['dto']);

        /// botones
        $this->add_button($name, 'Nuevo', 'index.php?page=edit_articulo_proveedor', 'fa-plus', 'btn-success');

        /// filtros
        $proveedores = $this->get_all_proveedores();
        $this->add_filter_select($name, 'codproveedor', 'Proveedor', $proveedores);
        $this->add_filter_checkbox($name, 'codbarras', 'cod. barras', 'IS NOT', null);
        $this->add_filter_checkbox($name, 'dto', 'descuento', '>', 0);

        /// decoración
        $this->decoration->add_column($name, 'codproveedor', 'string', 'Proveedor');
        $this->decoration->add_column($name, 'refproveedor', 'string', 'Ref. Proveedor');
        $this->decoration->add_column($name, 'referencia', 'string', 'Referencia', '', 'index.php?page=ventas_articulo&ref=');
        $this->decoration->add_column($name, 'descripcion', 'string', 'Descripción');
        $this->decoration->add_column($name, 'precio', 'money', 'Precio', 'text-right');
        $this->decoration->add_column($name, 'dto', 'number', '% dto.', 'text-right');
        $this->decoration->add_row_option($name, 'precio', 0, 'warning');
        $this->decoration->add_row_url($name, 'index.php?page=edit_articulo_proveedor&code=', 'id');
    }

    protected function create_tab_proveedores($name = 'proveedores')
    {
        $this->add_tab($name, 'proveedores', 'proveedores', 'fa-users');
        $this->add_search_columns($name, ['codproveedor', 'nombre', 'razonsocial', 'cifnif', 'email', 'telefono1', 'observaciones']);
        $this->add_sort_option($name, ['nombre']);
        $this->add_sort_option($name, ['razonsocial']);
        $this->add_sort_option($name, ['cifnif']);
        $this->add_sort_option($name, ['codproveedor']);

        /// botones
        $this->add_button($name, 'Nuevo', '#', 'fa-plus', 'btn-success', 'btn_nuevo_proveedor');

        /// filtros
        $this->add_filter_checkbox($name, 'personafisica', 'persona física');
        $this->add_filter_checkbox($name, 'acreedor', 'acreedor');
        $this->add_filter_checkbox($name, 'debaja', 'de baja');

        /// columnas
        $this->decoration->add_column($name, 'codproveedor', 'string', 'Código', '', 'index.php?page=compras_proveedor&cod=');
        $this->decoration->add_column($name, 'nombre', 'string', 'Nombre');
        $this->decoration->add_column($name, 'cifnif', 'string', FS_CIFNIF);
        $this->decoration->add_column($name, 'email');
        $this->decoration->add_column($name, 'telefono1', 'string', 'Teléfono');
        $this->decoration->add_column($name, 'observaciones');

        /// clic
        $this->decoration->add_row_url($name, 'index.php?page=compras_proveedor&cod=', 'codproveedor');

        /// decoración
        $this->decoration->add_row_option($name, 'debaja', true, 'danger');
        $this->decoration->add_row_option($name, 'acreedor', true, 'warning');
    }

    protected function create_tabs()
    {
        $this->template_bottom = 'block/compras_proveedores_bottom';
        $this->pais = new pais();

        $this->create_tab_proveedores();
        $this->create_tab_articulosprov();
    }

    protected function exec_previous_action($action)
    {
        if (isset($_GET['delete'])) {
            $this->eliminar_proveedor();
        } else if (isset($_POST['cifnif'])) {
            $this->nuevo_proveedor();
        }

        return parent::exec_previous_action($action);
    }

    /**
     * 
     * @return array
     */
    protected function get_all_proveedores()
    {
        $proveedores = [];

        $proveedor = new proveedor();
        foreach ($proveedor->all_full() as $pro) {
            $proveedores[$pro->codproveedor] = $pro->nombre;
        }

        return $proveedores;
    }

    private function nuevo_proveedor()
    {
        $proveedor = new proveedor();
        $proveedor->codproveedor = $proveedor->get_new_codigo();
        if (isset($_POST['codigo']) && !empty($_POST['codigo'])) {
            $proveedor->codproveedor = $_POST['codigo'];
        }

        $proveedor->nombre = $_POST['nombre'];
        $proveedor->razonsocial = $_POST['nombre'];
        $proveedor->tipoidfiscal = $_POST['tipoidfiscal'];
        $proveedor->cifnif = $_POST['cifnif'];
        $proveedor->acreedor = isset($_POST['acreedor']);
        $proveedor->personafisica = isset($_POST['personafisica']);

        if ($proveedor->exists()) {
            $this->new_error_msg("El proveedor ya existe.");
        } elseif ($proveedor->save()) {
            $dirproveedor = new direccion_proveedor();
            $dirproveedor->codproveedor = $proveedor->codproveedor;
            $dirproveedor->descripcion = "Principal";
            $dirproveedor->codpais = $_POST['pais'];
            $dirproveedor->provincia = $_POST['provincia'];
            $dirproveedor->ciudad = $_POST['ciudad'];
            $dirproveedor->codpostal = $_POST['codpostal'];
            $dirproveedor->direccion = $_POST['direccion'];
            $dirproveedor->apartado = $_POST['apartado'];

            if ($dirproveedor->save()) {
                if ($this->empresa->contintegrada) {
                    /// forzamos crear la subcuenta
                    $proveedor->get_subcuenta($this->empresa->codejercicio);
                }

                /// redireccionamos a la página del proveedor
                header('location: ' . $proveedor->url());
            } else {
                $this->new_error_msg("¡Imposible guardar la dirección el proveedor!");
            }
        } else {
            $this->new_error_msg("¡Imposible guardar el proveedor!");
        }
    }

    private function eliminar_proveedor()
    {
        $proveedor0 = new proveedor();
        $proveedor = $proveedor0->get($_GET['delete']);
        if ($proveedor) {
            if (FS_DEMO) {
                $this->new_error_msg('En el modo demo no se pueden eliminar proveedores.
               Otros usuarios podrían necesitarlos.');
            } else if (!$this->allow_delete) {
                $this->new_error_msg('No tienes permiso para eliminar en esta página.');
            } else if ($proveedor->delete()) {
                $this->new_message('Proveedor eliminado correctamente.');
            } else {
                $this->new_error_msg('Ha sido imposible borrar el proveedor.');
            }
        } else {
            $this->new_message('Proveedor no encontrado.');
        }
    }
}
