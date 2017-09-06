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

class ventas_cliente extends fbase_controller
{

    public $agente;
    public $cliente;
    public $cuenta_banco;
    public $divisa;
    public $forma_pago;
    public $grupo;
    public $pais;
    public $serie;
    public $tarifa;

    public function __construct()
    {
        parent::__construct(__CLASS__, 'Cliente', 'ventas', FALSE, FALSE);
    }

    protected function private_core()
    {
        parent::private_core();

        $this->ppage = $this->page->get('ventas_clientes');
        $this->agente = new agente();
        $this->cuenta_banco = new cuenta_banco_cliente();
        $this->divisa = new divisa();
        $this->forma_pago = new forma_pago();
        $this->grupo = new grupo_clientes();
        $this->pais = new pais();
        $this->serie = new serie();
        $this->tarifa = new tarifa();

        /// cargamos el cliente
        $cliente = new cliente();
        $this->cliente = FALSE;
        if (isset($_POST['codcliente'])) {
            $this->cliente = $cliente->get($_POST['codcliente']);
        } else if (isset($_GET['cod'])) {
            $this->cliente = $cliente->get($_GET['cod']);
        }

        if ($this->cliente) {
            $this->page->title = $this->cliente->codcliente;

            /// ¿Hay que hacer algo más?
            if (isset($_GET['delete_cuenta'])) { /// eliminar cuenta bancaria
                $this->delete_cuenta_banco();
            } else if (isset($_GET['delete_dir'])) { /// eliminar dirección
                $this->delete_direccion();
            } else if (isset($_POST['coddir'])) { /// añadir/modificar dirección
                $this->edit_direccion();
            } else if (isset($_POST['iban'])) { /// añadir/modificar iban
                $this->edit_cuenta_banco();
            } else if (isset($_POST['codcliente'])) { /// modificar cliente
                $this->modificar();
            } else if (isset($_GET['convertir'])) {
                $this->convertir();
            }
        } else {
            $this->new_error_msg("¡Cliente no encontrado!", 'error', FALSE, FALSE);
        }
    }

    public function url()
    {
        if (!isset($this->cliente)) {
            return parent::url();
        } else if ($this->cliente) {
            return $this->cliente->url();
        }

        return $this->ppage->url();
    }

    private function modificar()
    {
        $this->cliente->nombre = $_POST['nombre'];
        $this->cliente->razonsocial = $_POST['razonsocial'];
        $this->cliente->tipoidfiscal = $_POST['tipoidfiscal'];
        $this->cliente->cifnif = $_POST['cifnif'];
        $this->cliente->telefono1 = $_POST['telefono1'];
        $this->cliente->telefono2 = $_POST['telefono2'];
        $this->cliente->fax = $_POST['fax'];
        $this->cliente->web = $_POST['web'];
        $this->cliente->email = $_POST['email'];
        $this->cliente->observaciones = $_POST['observaciones'];
        $this->cliente->codpago = $_POST['codpago'];
        $this->cliente->coddivisa = $_POST['coddivisa'];
        $this->cliente->regimeniva = $_POST['regimeniva'];
        $this->cliente->recargo = isset($_POST['recargo']);
        $this->cliente->debaja = isset($_POST['debaja']);
        $this->cliente->personafisica = isset($_POST['personafisica']);
        $this->cliente->diaspago = $_POST['diaspago'];

        $this->cliente->codserie = NULL;
        if ($_POST['codserie'] != '') {
            $this->cliente->codserie = $_POST['codserie'];
        }

        $this->cliente->codagente = NULL;
        if ($_POST['codagente'] != '') {
            $this->cliente->codagente = $_POST['codagente'];
        }

        $this->cliente->codgrupo = NULL;
        if ($_POST['codgrupo'] != '') {
            $this->cliente->codgrupo = $_POST['codgrupo'];
        }

        $this->cliente->codtarifa = NULL;
        if ($_POST['codtarifa'] != '') {
            $this->cliente->codtarifa = $_POST['codtarifa'];
        }

        if ($this->cliente->save()) {
            $this->new_message("Datos del cliente modificados correctamente.");
            $this->propagar_cifnif();
        } else
            $this->new_error_msg("¡Imposible modificar los datos del cliente!");
    }

    private function edit_cuenta_banco()
    {
        if (isset($_POST['codcuenta'])) {
            $cuentab = $this->cuenta_banco->get($_POST['codcuenta']);
        } else {
            $cuentab = new cuenta_banco_cliente();
            $cuentab->codcliente = $_POST['codcliente'];
        }

        $cuentab->descripcion = $_POST['descripcion'];
        $cuentab->iban = $_POST['iban'];
        $cuentab->swift = $_POST['swift'];
        $cuentab->principal = isset($_POST['principal']);
        $cuentab->fmandato = NULL;

        if (isset($_POST['fmandato'])) {
            if ($_POST['fmandato'] != '') {
                $cuentab->fmandato = $_POST['fmandato'];
            }
        }

        if ($cuentab->save()) {
            $this->new_message('Cuenta bancaria guardada correctamente.');
        } else {
            $this->new_error_msg('Imposible guardar la cuenta bancaria.');
        }
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
        $dir = new direccion_cliente();
        if ($_POST['coddir'] != '') {
            $dir = $dir->get($_POST['coddir']);
        }
        $dir->apartado = $_POST['apartado'];
        $dir->ciudad = $_POST['ciudad'];
        $dir->codcliente = $this->cliente->codcliente;
        $dir->codpais = $_POST['pais'];
        $dir->codpostal = $_POST['codpostal'];
        $dir->descripcion = $_POST['descripcion'];
        $dir->direccion = $_POST['direccion'];
        $dir->domenvio = isset($_POST['direnvio']);
        $dir->domfacturacion = isset($_POST['dirfact']);
        $dir->provincia = $_POST['provincia'];
        if ($dir->save()) {
            $this->new_message("Dirección guardada correctamente.");
        } else {
            $this->new_message("¡Imposible guardar la dirección!");
        }
    }

    private function delete_direccion()
    {
        $dir = new direccion_cliente();
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

    private function convertir()
    {
        $proveedor = new proveedor();
        $proveedor->nombre = $this->cliente->nombre;
        $proveedor->razonsocial = $this->cliente->razonsocial;
        $proveedor->tipoidfiscal = $this->cliente->tipoidfiscal;
        $proveedor->cifnif = $this->cliente->cifnif;
        $proveedor->telefono1 = $this->cliente->telefono1;
        $proveedor->telefono2 = $this->cliente->telefono2;
        $proveedor->fax = $this->cliente->fax;
        $proveedor->web = $this->cliente->web;
        $proveedor->email = $this->cliente->email;
        $proveedor->observaciones = $this->cliente->observaciones;
        $proveedor->codpago = $this->cliente->codpago;
        $proveedor->coddivisa = $this->cliente->coddivisa;
        $proveedor->regimeniva = $this->cliente->regimeniva;
        $proveedor->personafisica = $this->cliente->personafisica;
        $proveedor->codserie = $this->cliente->codserie;
        $proveedor->codcliente = $this->cliente->codcliente;

        if ($proveedor->save()) {
            $this->cliente->codproveedor = $proveedor->codproveedor;
            $proveedor_ok = $this->cliente->save();
        } else {
            $proveedor_ok = FALSE;
        }

        if ($proveedor_ok) {
            /* cuentas de banco */
            foreach ($this->cuenta_banco->all_from_cliente($this->cliente->codcliente) as $c) {
                $c_banco_proveedor = new cuenta_banco_proveedor();
                $c_banco_proveedor->codproveedor = $proveedor->codproveedor;
                $c_banco_proveedor->descripcion = $c->descripcion;
                $c_banco_proveedor->iban = $c->iban;
                $c_banco_proveedor->swift = $c->swift;
                $c_banco_proveedor->save();
            }

            /* direcciones */
            foreach ($this->cliente->get_direcciones() as $d) {
                $direcc_pro = new direccion_proveedor();
                $direcc_pro->codproveedor = $proveedor->codproveedor;
                $direcc_pro->codpais = $d->codpais;
                $direcc_pro->apartado = $d->apartado;
                $direcc_pro->provincia = $d->provincia;
                $direcc_pro->ciudad = $d->ciudad;
                $direcc_pro->codpostal = $d->codpostal;
                $direcc_pro->direccion = $d->direccion;
                $direcc_pro->descripcion = $d->descripcion;
                $direcc_pro->save();
            }

            $this->new_message('Proveedor creado correctamente.');
        } else {
            $this->new_error_msg("¡Imposible crear el proveedor!");
        }
    }

    public function tiene_facturas()
    {
        $tiene = FALSE;

        if ($this->db->table_exists('facturascli')) {
            $sql = "SELECT * FROM facturascli WHERE codcliente = " . $this->cliente->var2str($this->cliente->codcliente);

            $data = $this->db->select_limit($sql, 5, 0);
            if ($data) {
                $tiene = TRUE;
            }
        }

        if (!$tiene && $this->db->table_exists('albaranescli')) {
            $sql = "SELECT * FROM albaranescli WHERE codcliente = " . $this->cliente->var2str($this->cliente->codcliente);

            $data = $this->db->select_limit($sql, 5, 0);
            if ($data) {
                $tiene = TRUE;
            }
        }

        return $tiene;
    }

    /**
     * Asigna el cif/nif a todos los albaranes y facturas del cliente que no tengan cif/nif
     */
    private function propagar_cifnif()
    {
        if ($this->cliente->cifnif) {
            /// actualizamos albaranes
            $sql = "UPDATE albaranescli SET cifnif = " . $this->cliente->var2str($this->cliente->cifnif)
                . " WHERE codcliente = " . $this->cliente->var2str($this->cliente->codcliente)
                . " AND cifnif = '' AND fecha >= " . $this->cliente->var2str(date('01-01-Y')) . ";";
            $this->db->exec($sql);

            /// actualizamos facturas
            $sql = "UPDATE facturascli SET cifnif = " . $this->cliente->var2str($this->cliente->cifnif)
                . " WHERE codcliente = " . $this->cliente->var2str($this->cliente->codcliente)
                . " AND cifnif = '' AND fecha >= " . $this->cliente->var2str(date('01-01-Y')) . ";";
            $this->db->exec($sql);
        }
    }
}
