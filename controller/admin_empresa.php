<?php
/**
 * This file is part of facturacion_base
 * Copyright (C) 2013-2020 Carlos Garcia Gomez <neorazorx@gmail.com>
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
require_once 'extras/phpmailer/class.phpmailer.php';
require_once 'extras/phpmailer/class.smtp.php';

class admin_empresa extends fs_controller
{

    /**
     *
     * @var \almacen
     */
    public $almacen;

    /**
     *
     * @var \cuenta_banco
     */
    public $cuenta_banco;

    /**
     *
     * @var \divisa
     */
    public $divisa;

    /**
     *
     * @var \ejercicio
     */
    public $ejercicio;

    /**
     *
     * @var array
     */
    public $email_plantillas;

    /**
     *
     * @var \forma_pago
     */
    public $forma_pago;

    /**
     *
     * @var array
     */
    public $impresion;

    /**
     *
     * @var string
     */
    public $logo;

    /**
     *
     * @var \pais
     */
    public $pais;

    /**
     *
     * @var \serie
     */
    public $serie;

    public function __construct()
    {
        parent::__construct(__CLASS__, 'Empresa', 'admin', TRUE, TRUE);
    }

    protected function private_core()
    {
        $this->almacen = new almacen();
        $this->cuenta_banco = new cuenta_banco();
        $this->divisa = new divisa();
        $this->ejercicio = new ejercicio();
        $this->forma_pago = new forma_pago();
        $this->pais = new pais();
        $this->serie = new serie();

        $fsvar = new fs_var();

        /// obtenemos los datos de configuración de impresión
        $this->impresion = array(
            'print_ref' => '1',
            'print_dto' => '1',
            'print_alb' => '0',
            'print_formapago' => '1'
        );
        $this->impresion = $fsvar->array_get($this->impresion, FALSE);

        /// obtenemos los datos de las plantillas de emails
        $this->email_plantillas = array(
            'mail_factura' => "Buenos días, le adjunto su #DOCUMENTO#.\n#FIRMA#",
            'mail_albaran' => "Buenos días, le adjunto su #DOCUMENTO#.\n#FIRMA#",
            'mail_pedido' => "Buenos días, le adjunto su #DOCUMENTO#.\n#FIRMA#",
            'mail_presupuesto' => "Buenos días, le adjunto su #DOCUMENTO#.\n#FIRMA#",
        );
        $this->email_plantillas = $fsvar->array_get($this->email_plantillas, FALSE);

        if (isset($_POST['cifnif'])) {
            /// guardamos los datos de la empresa
            $fields = [
                'nombre', 'nombrecorto', 'cifnif', 'administrador', 'codpais', 'provincia',
                'ciudad', 'direccion', 'codpostal', 'apartado', 'telefono', 'fax', 'web',
                'email', 'lema', 'horario', 'codejercicio', 'codserie', 'coddivisa',
                'codpago', 'codalmacen', 'pie_factura'
            ];
            foreach ($fields as $field) {
                $this->empresa->{$field} = $_POST[$field];
            }
            $this->empresa->contintegrada = isset($_POST['contintegrada']);
            $this->empresa->recequivalencia = isset($_POST['recequivalencia']);

            /// configuración de email
            $this->empresa->email_config['mail_password'] = $_POST['mail_password'];
            $this->empresa->email_config['mail_bcc'] = $_POST['mail_bcc'];
            $this->empresa->email_config['mail_firma'] = $_POST['mail_firma'];
            $this->empresa->email_config['mail_mailer'] = $_POST['mail_mailer'];
            $this->empresa->email_config['mail_host'] = $_POST['mail_host'];
            $this->empresa->email_config['mail_port'] = intval($_POST['mail_port']);
            $this->empresa->email_config['mail_enc'] = strtolower($_POST['mail_enc']);
            $this->empresa->email_config['mail_user'] = $_POST['mail_user'];
            $this->empresa->email_config['mail_low_security'] = isset($_POST['mail_low_security']);

            if ($this->empresa->save()) {
                /// guardamos las opciones por defecto de almacén y forma de pago
                $this->save_codalmacen($_POST['codalmacen']);
                $this->save_codpago($_POST['codpago']);

                $this->new_message('Datos guardados correctamente.');
                if (!$this->empresa->contintegrada) {
                    $this->new_message('¿Quieres activar la <b>contabilidad integrada</b>?'
                        . ' Haz clic en la sección <a href="#facturacion">facturación</a>.');
                }

                $step = $fsvar->simple_get('install_step');
                if ($step == 2) {
                    $step = 3;
                    $fsvar->simple_save('install_step', $step);
                }

                if ($step == 3 && $this->empresa->contintegrada) {
                    $this->new_message('Recuerda que tienes que <a href="index.php?page=contabilidad_ejercicio&cod=' .
                        $this->empresa->codejercicio . '">importar los datos del ejercicio</a>.');
                }

                $this->mail_test();
            } else {
                $this->new_error_msg('Error al guardar los datos.');
            }

            /// guardamos los datos de impresión
            $this->impresion['print_ref'] = ( isset($_POST['print_ref']) ? 1 : 0 );
            $this->impresion['print_dto'] = ( isset($_POST['print_dto']) ? 1 : 0 );
            $this->impresion['print_alb'] = ( isset($_POST['print_alb']) ? 1 : 0 );
            $this->impresion['print_formapago'] = ( isset($_POST['print_formapago']) ? 1 : 0 );
            $fsvar->array_save($this->impresion);

            /// guardamos las plantillas de emails
            $this->email_plantillas['mail_factura'] = $_POST['mail_factura'];
            $this->email_plantillas['mail_albaran'] = $_POST['mail_albaran'];
            if (isset($_POST['mail_pedido'])) {
                $this->email_plantillas['mail_pedido'] = $_POST['mail_pedido'];
                $this->email_plantillas['mail_presupuesto'] = $_POST['mail_presupuesto'];
            }
            $fsvar->array_save($this->email_plantillas);
        } else if (isset($_POST['logo'])) {
            $this->cambiar_logo();
        } else if (isset($_GET['delete_logo'])) {
            $this->delete_logo();
        } else if (isset($_GET['delete_cuenta'])) { /// eliminar cuenta bancaria
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
        } else if (isset($_POST['iban'])) { /// añadir/modificar cuenta bancaria
            if (isset($_POST['codcuenta'])) {
                $cuentab = $this->cuenta_banco->get($_POST['codcuenta']);
            } else {
                $cuentab = new cuenta_banco();
            }

            $cuentab->descripcion = $_POST['descripcion'];
            $cuentab->iban = $_POST['iban'];
            $cuentab->swift = $_POST['swift'];

            $cuentab->codsubcuenta = NULL;
            if ($_POST['codsubcuenta']) {
                $cuentab->codsubcuenta = $_POST['codsubcuenta'];
            }

            if ($cuentab->save()) {
                $this->new_message('Cuenta bancaria guardada correctamente.');
            } else {
                $this->new_error_msg('Imposible guardar la cuenta bancaria.');
            }
        } else {
            $this->fix_logo();
        }

        $this->logo();

        // Llamando la funcion que realiza el autocomplete
        if (isset($_GET['subcuenta'])) {
            $this->buscar_subcuenta($_GET['subcuenta']);
        }
    }

    private function mail_test()
    {
        if (false === $this->empresa->can_send_mail()) {
            return;
        }

        /// Es imprescindible OpenSSL para enviar emails con los principales proveedores
        if (false === extension_loaded('openssl')) {
            $this->new_error_msg('No se encuentra la extensión OpenSSL, imprescindible para enviar emails.');
            return;
        }

        $mail = $this->empresa->new_mail();
        $mail->Timeout = 3;
        $mail->FromName = $this->user->nick;
        $mail->Subject = 'TEST';
        $mail->AltBody = 'TEST';
        $mail->msgHTML('TEST');
        $mail->isHTML(TRUE);

        if ($this->empresa->mail_connect($mail)) {
            /// OK
            return;
        }

        $this->new_error_msg('No se ha podido conectar por email. ¿La contraseña es correcta?');
        if ($mail->Host == 'smtp.gmail.com') {
            $this->new_error_msg('Aunque la contraseña de gmail sea correcta, en ciertas '
                . 'situaciones los servidores de gmail bloquean la conexión. '
                . 'Para superar esta situación debes crear y usar una '
                . '<a href="https://support.google.com/accounts/answer/185833?hl=es" '
                . 'target="_blank">contraseña de aplicación</a>');
            return;
        }

        $this->new_error_msg("¿<a href='" . FS_COMMUNITY_URL . "/contacto' target='_blank'>Necesitas ayuda</a>?");
    }

    private function fix_logo()
    {
        if (!file_exists(FS_MYDOCS . 'images')) {
            @mkdir(FS_MYDOCS . 'images', 0777, TRUE);
        }

        /**
         * Antes se guardaba el logo en el temporal.
         * Mala decisión, lo movemos.
         */
        if (file_exists('tmp/' . FS_TMP_NAME . 'logo.png')) {
            rename('tmp/' . FS_TMP_NAME . 'logo.png', FS_MYDOCS . 'images/logo.png');
        } else if (file_exists('tmp/' . FS_TMP_NAME . 'logo.jpg')) {
            rename('tmp/' . FS_TMP_NAME . 'logo.jpg', FS_MYDOCS . 'images/logo.jpg');
        }
    }

    private function logo()
    {
        $this->logo = '';
        if (file_exists(FS_MYDOCS . 'images/logo.png')) {
            $this->logo = 'images/logo.png';
        } else if (file_exists(FS_MYDOCS . 'images/logo.jpg')) {
            $this->logo = 'images/logo.jpg';
        }
    }

    private function cambiar_logo()
    {
        if (is_uploaded_file($_FILES['fimagen']['tmp_name'])) {
            if (!file_exists(FS_MYDOCS . 'images')) {
                @mkdir(FS_MYDOCS . 'images', 0777, TRUE);
            }
            $this->delete_logo();

            if (substr(strtolower($_FILES['fimagen']['name']), -3) == 'png') {
                copy($_FILES['fimagen']['tmp_name'], FS_MYDOCS . "images/logo.png");
            } else {
                copy($_FILES['fimagen']['tmp_name'], FS_MYDOCS . "images/logo.jpg");
            }

            $this->new_message('Logotipo guardado correctamente.');
        }
    }

    private function delete_logo()
    {
        if (file_exists(FS_MYDOCS . 'images/logo.png')) {
            unlink(FS_MYDOCS . 'images/logo.png');
            $this->new_message('Logotipo borrado correctamente.');
        } else if (file_exists(FS_MYDOCS . 'images/logo.jpg')) {
            unlink(FS_MYDOCS . 'images/logo.jpg');
            $this->new_message('Logotipo borrado correctamente.');
        }
    }

    public function encriptaciones()
    {
        return array(
            'ssl' => 'SSL',
            'tls' => 'TLS',
            '' => 'Ninguna'
        );
    }

    public function mailers()
    {
        return array(
            'mail' => 'Mail',
            'sendmail' => 'SendMail',
            'smtp' => 'SMTP'
        );
    }

    private function buscar_subcuenta($aux)
    {
        /// desactivamos la plantilla HTML
        $this->template = FALSE;

        $json = [];
        $subcuenta = new subcuenta();
        $ejercicio = $this->ejercicio->get_by_fecha($this->today());
        foreach ($subcuenta->search_by_ejercicio($ejercicio->codejercicio, $aux) as $subc) {
            $json[] = [
                'value' => $subc->codsubcuenta . ' - ' . $subc->descripcion,
                'data' => $subc->codsubcuenta,
                'saldo' => $subc->saldo,
                'link' => $subc->url()
            ];
        }

        header('Content-Type: application/json');
        echo json_encode(array('query' => $aux, 'suggestions' => $json));
    }
}
