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

class compras_proveedor extends fbase_controller
{

    public $cuenta_banco;
    public $divisa;
    public $forma_pago;
    public $pais;
    public $proveedor;
    public $serie;

    public function __construct()
    {
        parent::__construct(__CLASS__, 'Proveedor', 'compras', FALSE, FALSE);
    }

    protected function private_core()
    {
        parent::private_core();

        $this->ppage = $this->page->get('compras_proveedores');
        $this->cuenta_banco = new cuenta_banco_proveedor();
        $this->divisa = new divisa();
        $this->forma_pago = new forma_pago();
        $this->pais = new pais();
        $this->serie = new serie();

        /// cargamos el proveedor
        $proveedor = new proveedor();
        $this->proveedor = FALSE;
        if (isset($_POST['codproveedor'])) {
            $this->proveedor = $proveedor->get($_POST['codproveedor']);
        } else if (isset($_GET['cod'])) {
            $this->proveedor = $proveedor->get($_GET['cod']);
        }

        if ($this->proveedor) {
            $this->page->title = $this->proveedor->codproveedor;

            /// ¿Hay que hacer algo más?
            if (isset($_GET['delete_cuenta'])) { /// eliminar una cuenta bancaria
                $this->delete_cuenta_banco();
            } else if (isset($_GET['delete_dir'])) { /// eliminar una dirección
                $this->delete_direccion();
            } else if (isset($_POST['coddir'])) { /// añadir/modificar una dirección
                $this->edit_direccion();
            } else if (isset($_POST['iban'])) { /// añadir/modificar una cuenta bancaria
                $this->edit_cuenta_banco();
            } else if (isset($_POST['codproveedor'])) { /// modificar el proveedor
                $this->modificar();
            } else if (isset($_GET['convertir'])) { /// convertir a cliente
                $this->convertir();
            }
        } else {
            $this->new_error_msg("¡Proveedor no encontrado!", 'error', FALSE, FALSE);
        }
    }

    public function url()
    {
        if (!isset($this->proveedor)) {
            return parent::url();
        } else if ($this->proveedor) {
            return $this->proveedor->url();
        }
        
        return $this->ppage->url();
    }

    private function modificar()
    {
        $this->proveedor->nombre = $_POST['nombre'];
        $this->proveedor->razonsocial = $_POST['razonsocial'];
        $this->proveedor->tipoidfiscal = $_POST['tipoidfiscal'];
        $this->proveedor->cifnif = $_POST['cifnif'];
        $this->proveedor->telefono1 = $_POST['telefono1'];
        $this->proveedor->telefono2 = $_POST['telefono2'];
        $this->proveedor->fax = $_POST['fax'];
        $this->proveedor->email = $_POST['email'];
        $this->proveedor->web = $_POST['web'];
        $this->proveedor->observaciones = $_POST['observaciones'];
        $this->proveedor->codpago = $_POST['codpago'];
        $this->proveedor->coddivisa = $_POST['coddivisa'];
        $this->proveedor->regimeniva = $_POST['regimeniva'];
        $this->proveedor->acreedor = isset($_POST['acreedor']);
        $this->proveedor->personafisica = isset($_POST['personafisica']);

        $this->proveedor->codserie = NULL;
        if ($_POST['codserie'] != '') {
            $this->proveedor->codserie = $_POST['codserie'];
        }

        $this->proveedor->debaja = isset($_POST['debaja']);

        if ($this->proveedor->save()) {
            $this->new_message('Datos del proveedor modificados correctamente.');
        } else {
            $this->new_error_msg('¡Imposible modificar los datos del proveedor!');
        }
    }

    private function edit_cuenta_banco()
    {
        if (isset($_POST['codcuenta'])) {
            $cuentab = $this->cuenta_banco->get($_POST['codcuenta']);
        } else {
            $cuentab = new cuenta_banco_proveedor();
            $cuentab->codproveedor = $this->proveedor->codproveedor;
        }

        $cuentab->descripcion = $_POST['descripcion'];
        $cuentab->iban = $_POST['iban'];
        $cuentab->swift = $_POST['swift'];
        $cuentab->principal = isset($_POST['principal']);

        if ($cuentab->save()) {
            $this->new_message('Cuenta bancaria guardada correctamente.');
        } else
            $this->new_error_msg('Imposible guardar la cuenta bancaria.');
    }

    private function delete_cuenta_banco()
    {
        $cuenta = $this->cuenta_banco->get($_GET['delete_cuenta']);
        if ($cuenta) {
            if ($cuenta->delete()) {
                $this->new_message('Cuenta bancaria eliminada correctamente.');
            } else {
                $this->new_error_msg('Imposible eliminar la cuenta bancaria.');
            }
        } else {
            $this->new_error_msg('Cuenta bancaria no encontrada.');
        }
    }

    private function edit_direccion()
    {
        $direccion = new direccion_proveedor();
        if ($_POST['coddir'] != '') {
            $direccion = $direccion->get($_POST['coddir']);
        }

        $direccion->apartado = $_POST['apartado'];
        $direccion->ciudad = $_POST['ciudad'];
        $direccion->codpais = $_POST['pais'];
        $direccion->codpostal = $_POST['codpostal'];
        $direccion->codproveedor = $this->proveedor->codproveedor;
        $direccion->descripcion = $_POST['descripcion'];
        $direccion->direccion = $_POST['direccion'];
        $direccion->direccionppal = isset($_POST['direccionppal']);
        $direccion->provincia = $_POST['provincia'];
        if ($direccion->save()) {
            $this->new_message("Dirección guardada correctamente.");
        } else {
            $this->new_error_msg("¡Imposible guardar la dirección!");
        }
    }

    private function delete_direccion()
    {
        $dir = new direccion_proveedor();
        $dir0 = $dir->get($_GET['delete_dir']);
        if ($dir0) {
            if ($dir0->delete()) {
                $this->new_message('Dirección eliminada correctamente.');
            } else {
                $this->new_error_msg('Imposible eliminar la dirección.');
            }
        } else {
            $this->new_error_msg('Dirección no encontrada.');
        }
    }

    /**
     * Creamos un nuevo cliente a partir de los datos de este proveedor
     */
    private function convertir()
    {
        $cliente = new cliente();
        $cliente->nombre = $this->proveedor->nombre;
        $cliente->razonsocial = $this->proveedor->razonsocial;
        $cliente->tipoidfiscal = $this->proveedor->tipoidfiscal;
        $cliente->cifnif = $this->proveedor->cifnif;
        $cliente->telefono1 = $this->proveedor->telefono1;
        $cliente->telefono2 = $this->proveedor->telefono2;
        $cliente->fax = $this->proveedor->fax;
        $cliente->web = $this->proveedor->web;
        $cliente->email = $this->proveedor->email;
        $cliente->observaciones = $this->proveedor->observaciones;
        $cliente->codpago = $this->proveedor->codpago;
        $cliente->coddivisa = $this->proveedor->coddivisa;
        $cliente->regimeniva = $this->proveedor->regimeniva;
        $cliente->personafisica = $this->proveedor->personafisica;
        $cliente->codserie = $this->proveedor->codserie;
        $cliente->codproveedor = $this->proveedor->codproveedor;

        $cliente_ok = TRUE;
        if ($cliente->save()) {
            $this->proveedor->codcliente = $cliente->codcliente;
            $cliente_ok = $this->proveedor->save();
        } else {
            $cliente_ok = FALSE;
        }

        if ($cliente_ok) {
            /* cuentas de banco */
            foreach ($this->cuenta_banco->all_from_proveedor($this->proveedor->codproveedor) as $c) {
                $c_banco_cliente = new cuenta_banco_cliente();
                $c_banco_cliente->codcliente = $cliente->codcliente;
                $c_banco_cliente->descripcion = $c->descripcion;
                $c_banco_cliente->iban = $c->iban;
                $c_banco_cliente->swift = $c->swift;
                $c_banco_cliente->save();
            }

            /* direcciones */
            foreach ($this->proveedor->get_direcciones() as $d) {
                $direcc_cli = new direccion_cliente();
                $direcc_cli->codcliente = $cliente->codcliente;
                $direcc_cli->codpais = $d->codpais;
                $direcc_cli->apartado = $d->apartado;
                $direcc_cli->provincia = $d->provincia;
                $direcc_cli->ciudad = $d->ciudad;
                $direcc_cli->codpostal = $d->codpostal;
                $direcc_cli->direccion = $d->direccion;
                $direcc_cli->descripcion = $d->descripcion;
                $direcc_cli->save();
            }

            $this->new_message('Cliente creado correctamente.');
        } else {
            $this->new_error_msg("¡Imposible crear el cliente!");
        }
    }

    public function tiene_facturas()
    {
        $tiene = FALSE;

        if ($this->db->table_exists('facturasprov')) {
            $sql = "SELECT * FROM facturasprov WHERE codproveedor = " . $this->proveedor->var2str($this->proveedor->codproveedor);

            $data = $this->db->select_limit($sql, 5, 0);
            if ($data) {
                $tiene = TRUE;
            }
        }

        if (!$tiene && $this->db->table_exists('albaranesprov')) {
            $sql = "SELECT * FROM albaranesprov WHERE codproveedor = " . $this->proveedor->var2str($this->proveedor->codproveedor);

            $data = $this->db->select_limit($sql, 5, 0);
            if ($data) {
                $tiene = TRUE;
            }
        }

        return $tiene;
    }
}
