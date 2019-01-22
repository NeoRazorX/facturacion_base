<?php
/**
 * @author Carlos García Gómez      neorazorx@gmail.com
 * @copyright 2015-2018, Carlos García Gómez. All Rights Reserved. 
 */

/**
 * Description of edit_articulo_proveedor
 *
 * @author carlos
 */
class edit_articulo_proveedor extends fs_edit_controller
{

    public function __construct($name = __CLASS__, $title = 'Artículo proveedor', $folder = 'Compras')
    {
        parent::__construct($name, $title, $folder);
    }

    public function get_model_class_name()
    {
        return 'articulo_proveedor';
    }

    protected function set_edit_columns()
    {
        $proveedores = $this->sql_distinct('proveedores', 'codproveedor', 'nombre');
        $this->form->add_column_select('codproveedor', $proveedores, 'Proveedor', 4, true);

        $this->form->add_column('refproveedor', 'string', 'Ref. proveedor', 4, true);
        $this->form->add_column('referencia', 'string', 'Referencia', 4);
        $this->form->add_column('descripcion', 'textarea', 'Descripcion', 12);
        $this->form->add_column('codbarras', 'string', 'Cod. Barras', 3);
        $this->form->add_column('partnumber', 'string', 'Partnumber', 3);
        $this->form->add_column('precio', 'money', 'Precio', 3);
        $this->form->add_column('dto', 'number', 'Descuento', 3);
    }
}
