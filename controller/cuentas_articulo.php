<?php

require_model('articulo.php');

/**
 * Created by IntelliJ IDEA.
 * User: ggarcia
 * Date: 15/04/2016
 * Time: 04:03 PM
 */
class cuentas_articulo extends fs_controller {

    /**
     * @var articulo
     */
    protected $articulo = false;

    public function __construct() {
        parent::__construct(__CLASS__, 'Factura Custom', 'ventas', FALSE, FALSE);
    }

    protected function process() {
        //Add the tab!
        $this->share_extensions();

        $ref_articulo = isset($_GET['ref']) ? $_GET['ref'] : '';
        $art = new articulo();
        $this->articulo = $art->get($ref_articulo);


        $action = (string) isset($_GET['action']) ? $_GET['action'] : 'list';

        switch($action) {
            case 'edit':
                $this->editAction();
                break;
            default:
        }
    }

    protected function editAction() {
        $this->page->extra_url = '&action=edit&ref=' . urlencode($this->articulo->referencia);
        $this->template = 'cuentas_articulo/form';

        if($_SERVER['REQUEST_METHOD'] === 'POST') {
            foreach ($_POST as $name => $value) {
                if (property_exists($this->articulo, $name)) {
                    $this->articulo->$name = $value;
                }
            }
            if ($this->articulo->save()) {
                $this->new_message('Articulo actualizado correctamente!');
            } else {
                $this->new_error_msg('Error al actualizar el artículo!');
            }
        }
    }

    private function share_extensions() {
        if($this->page->get('cuentas_articulo')) {
            $fsext = new fs_extension(
                array(
                    'name' => 'cuentas_articulo',
                    'page_from' => 'cuentas_articulo',
                    'page_to' => 'ventas_articulo',
                    'type' => 'tab',
                    'text' => 'Cuentas',
                    'params' => '&action=edit'
                )
            );
            $fsext->save();
        }
    }

    public function getArticulo() {
        return $this->articulo;
    }
}