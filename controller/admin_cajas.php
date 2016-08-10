<?php

require_model('agent.php');
require_model('conf_caja.php');

/**
 * Created by IntelliJ IDEA.
 * User: ggarcia
 * Date: 30/06/2016
 * Time: 06:53 PM
 */

class admin_cajas extends fs_controller {

    /**
     * @var bool
     */
    public $allow_delete;

    /**
     * @var conf_caja
     */
    public $conf_caja;

    /**
     * @var string
     */
    public $action;

    public function __construct() {
        parent::__construct(__CLASS__, 'Cajas', 'admin', true);
    }

    public function new_url() {
        $this->page->extra_url = '&action=add';
        return $this->url();
    }

    public function edit_url(conf_caja $conf_caja) {
        $this->page->extra_url = '&action=edit&id=' . (int) $conf_caja->getId();
        return $this->url();
    }

    public function delete_url(conf_caja $conf_caja) {
        $this->page->extra_url = '&action=delete&id=' . (int) $conf_caja->getId();
        return $this->url();
    }

    protected function private_core() {
        /// ¿El usuario tiene permiso para eliminar en esta página?
        $this->allow_delete = $this->user->allow_delete_on(__CLASS__);
        $this->conf_caja = new conf_caja();

        $action = (string) isset($_GET['action']) ? $_GET['action'] : 'list';

        switch($action) {
            default:
            case 'list':
                $this->indexAction();
                break;
            case 'add':
                $this->addAction();
                break;
            case 'edit':
                $this->editAction();
                break;
            case 'delete':
                $this->deleteAction();
                break;
            case 'find':
                $this->findAction();
                break;
        }
    }

    public function indexAction() {
        $this->page->extra_url = '&action=find';
        $this->template = 'admin_cajas/index';
    }

    public function addAction() {
        $this->page->extra_url = '&action=add';

        $this->template = 'admin_cajas/form';
        if($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->conf_caja->setValues($_POST);
            if ($this->conf_caja->save()) {
                $this->new_message("Configuración de caja guardada correctamente!");
                $this->indexAction();
            } else {
                $this->new_error_msg("¡Imposible agregar Configuracion de caja!");
            }
        }
    }

    public function editAction() {
        $id = (int) isset($_GET['id']) ? $_GET['id'] : 0;
        $this->conf_caja = conf_caja::get($id);
        $this->page->extra_url = '&action=edit&id=' . (int) $this->conf_caja->getId();
        $this->action = 'edit';

        $this->template = 'admin_cajas/form';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->conf_caja->setValues($_POST);
            $this->conf_caja->setEdit(true);
            if ($this->conf_caja->save()) {
                $this->new_message("Configuracion de caja actualizada correctamente!");
                $this->indexAction();
            } else {
                $this->new_error_msg("¡Imposible actualizar configuracion de caja!");
            }
        }

    }

    private function deleteAction() {
        $this->page->extra_url = '&action=delete';
        $id = (int) isset($_GET['id']) ? $_GET['id'] : 0;
        $conf_caja = conf_caja::get($id);
        if($conf_caja && $this->allow_delete && $conf_caja->delete()) {
            $this->new_message("Configuracion de caja eliminada corectamente!");
        } else {
            if(!$this->allow_delete) {
                $this->new_error_msg("No tiene permisos para eliminar en esta página");
            }
            $this->new_error_msg("Error al eliminar configracion de caja!");
        }
        $this->indexAction();
    }

    public function findAction() {
        $this->page->extra_url = '&action=find';


    }


}